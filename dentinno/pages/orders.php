<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/order_effects.php';
$page_title = 'Orders';
requireView('orders');

// Order status lifecycle — forward-only. SINGLE source of truth used by both the POST
// handler (validation) and the UI (which next-states to offer). 'refunded' is reachable
// only via the Refunds module so the gateway refund + effect reversal stay coupled.
$ORDER_TRANSITIONS = [
    'pending'          => ['processing','confirmed','cancelled','rejected'],
    'processing'       => ['confirmed','shipped','cancelled','rejected'],
    'confirmed'        => ['shipped','out_for_delivery','cancelled','rejected'],
    'shipped'          => ['out_for_delivery','delivered','returning'],
    'out_for_delivery' => ['delivered','returning'],
    'delivered'        => ['returning'],
    'returning'        => ['returned'],
    'returned'         => [], 'cancelled' => [], 'rejected' => [], 'refunded' => [],
];
// Statuses to OFFER for an order currently in $cur: itself + its legal next states.
function orderStatusOptions(string $cur, array $T): array {
    return array_values(array_unique(array_merge([$cur], $T[$cur] ?? [])));
}
// Accent colour per PAYMENT status (Shopify/Razorpay-style coloured pills).
function paymentColor(string $p): string {
    return [
        'paid'     => '#27AE60',
        'unpaid'   => '#E74C3C',
        'partial'  => '#F39C12',
        'pending'  => '#95A5A6',
        'refunded' => '#7F8C8D',
    ][$p] ?? '#95A5A6';
}
// Font Awesome icon per payment method.
function paymentMethodIcon(string $m): string {
    $m = strtolower($m);
    if ($m === 'upi') return 'fa-mobile-screen-button';
    if ($m === 'card' || strpos($m,'credit')!==false || strpos($m,'debit')!==false) return 'fa-credit-card';
    if (strpos($m,'bank')!==false || strpos($m,'net')!==false) return 'fa-building-columns';
    if ($m === 'cod' || strpos($m,'cash')!==false) return 'fa-money-bill-wave';
    if (strpos($m,'cheque')!==false) return 'fa-money-check-dollar';
    return 'fa-wallet';
}
// Friendly label for a payment method (cod -> COD, etc.).
function paymentMethodLabel(string $m): string {
    return strtolower($m) === 'cod' ? 'COD' : $m;
}

// One accent colour per status — drives the colour-coded pills/selects so the list is
// scannable at a glance (green = delivered, red = cancelled, etc.).
function statusColor(string $status): string {
    return [
        'pending'          => '#F39C12',
        'processing'       => '#3498DB',
        'confirmed'        => '#2980B9',
        'shipped'          => '#9B59B6',
        'out_for_delivery' => '#16A085',
        'delivered'        => '#27AE60',
        'returning'        => '#E67E22',
        'returned'         => '#7F8C8D',
        'cancelled'        => '#E74C3C',
        'rejected'         => '#C0392B',
        'refunded'         => '#7F8C8D',
    ][$status] ?? '#7F8C8D';
}
$PAY_STATUSES = ['unpaid','paid','partial'];          // 'refunded' only via Refunds module
$PAY_METHODS  = ['UPI','Card','Net Banking','Bank Transfer','Cash','Cheque'];
$TERMINAL_STATUSES = ['cancelled','rejected','returned'];

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    requireAction('orders', rbacCrudVerb($action, $data));

    if ($action === 'update_status') {
        $newStatus = (string)($data['status'] ?? '');
        $oid       = (int)($data['id'] ?? 0);

        $cur = db()->fetchOne("SELECT status, payment_method, payment_status FROM orders WHERE id=?", [$oid]);
        if (!$cur) { echo json_encode(['success'=>false,'message'=>'Order not found']); exit; }
        $curStatus = (string)$cur['status'];

        if ($newStatus === $curStatus) { echo json_encode(['success'=>true,'message'=>'No change']); exit; }
        $allowed = $ORDER_TRANSITIONS[$curStatus] ?? null;
        if ($allowed === null) { echo json_encode(['success'=>false,'message'=>"Unknown current status '$curStatus'"]); exit; }
        if (!in_array($newStatus, $allowed, true)) {
            echo json_encode(['success'=>false,'message'=>"Can't move an order from '$curStatus' to '$newStatus'."]); exit;
        }

        $extra = [];
        if ($newStatus === 'shipped')   $extra[] = "shipped_at = NOW()";
        if ($newStatus === 'delivered') $extra[] = "delivered_at = NOW()";
        // COD: cash is collected at delivery, so auto-mark a COD order Paid when it's delivered.
        $codCollected = ($newStatus === 'delivered'
            && strtolower((string)($cur['payment_method'] ?? '')) === 'cod'
            && ($cur['payment_status'] ?? '') !== 'paid');
        if ($codCollected) $extra[] = "payment_status = 'paid'";
        $extraStr = $extra ? ', ' . implode(', ', $extra) : '';
        $isTerminal = in_array($newStatus, ['cancelled', 'rejected', 'returned'], true);

        if ($isTerminal) {
            // Status change + effect reversal must be ATOMIC: if the reversal (restock /
            // counter decrement) fails, the status change must roll back too — otherwise the
            // order is terminal with effects_reversed=0 and stock leaks permanently with no
            // path to retry. (refunds.php / cleanup do the same single-txn wrap.)
            $pdo = db()->getConnection();
            $pdo->beginTransaction();
            try {
                db()->execute("UPDATE orders SET status = ? $extraStr WHERE id = ?", [$newStatus, $oid]);
                reverseOrderEffects($oid);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('reverseOrderEffects(status): ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Could not complete the status change (inventory reversal failed). Please retry.']); exit;
            }
        } else {
            db()->execute("UPDATE orders SET status = ? $extraStr WHERE id = ?", [$newStatus, $oid]);
        }
        // Log the change for the order timeline (supplementary — never fail the request on it).
        try {
            db()->execute("INSERT INTO order_status_history (order_id, status, changed_by) VALUES (?,?,?)",
                [$oid, $newStatus, $_SESSION['admin_id'] ?? null]);
        } catch (Throwable $e) { error_log('status history: ' . $e->getMessage()); }
        // Best-effort WhatsApp status update (only for customer-relevant transitions).
        if (in_array($newStatus, ['confirmed', 'shipped', 'out_for_delivery', 'delivered', 'returning', 'returned', 'cancelled', 'rejected'], true)) {
            notifyOrderStatusWA($oid, $newStatus);
        }
        $msg = $codCollected
            ? 'Order delivered — COD payment marked as Paid (cash collected).'
            : 'Order status updated';
        echo json_encode(['success' => true, 'message' => $msg]);
    } elseif ($action === 'update_payment') {
        $oid       = (int)($data['id'] ?? 0);
        $payStatus = (string)($data['payment_status'] ?? '');
        $payMethod = trim((string)($data['payment_method'] ?? ''));
        // 'refunded' must be reached through the refunds module so the gateway refund and
        // effect reversal are coupled — block setting it manually here (it would desync).
        if ($payStatus === 'refunded') {
            echo json_encode(['success'=>false,'message'=>'Use the Refunds page to refund an order.']); exit;
        }
        // Whitelist the payment status — never write an arbitrary client-supplied string.
        if (!in_array($payStatus, $PAY_STATUSES, true)) {
            echo json_encode(['success'=>false,'message'=>'Invalid payment status.']); exit;
        }
        if ($payMethod !== '' && !in_array($payMethod, $PAY_METHODS, true)) {
            echo json_encode(['success'=>false,'message'=>'Invalid payment method.']); exit;
        }
        if (!db()->fetchOne("SELECT id FROM orders WHERE id=?", [$oid])) {
            echo json_encode(['success'=>false,'message'=>'Order not found']); exit;
        }
        db()->execute("UPDATE orders SET payment_status = ?, payment_method = ? WHERE id = ?",
            [$payStatus, $payMethod ?: null, $oid]);
        echo json_encode(['success' => true, 'message' => 'Payment updated']);
    } elseif ($action === 'update_tracking') {
        $oid = (int)($data['id'] ?? 0);
        $tn  = trim((string)($data['tracking_number'] ?? ''));
        $cn  = trim((string)($data['courier_name'] ?? ''));
        if (!db()->fetchOne("SELECT id FROM orders WHERE id=?", [$oid])) {
            echo json_encode(['success'=>false,'message'=>'Order not found']); exit;
        }
        db()->execute("UPDATE orders SET tracking_number = ?, courier_name = ? WHERE id = ?",
            [$tn ?: null, $cn ?: null, $oid]);
        // Best-effort WhatsApp shipping update with the new tracking details.
        notifyOrderStatusWA($oid, null);
        echo json_encode(['success' => true, 'message' => 'Tracking updated']);
    } elseif ($action === 'save_notes') {
        $oid   = (int)($data['id'] ?? 0);
        $notes = trim((string)($data['notes'] ?? ''));
        if (mb_strlen($notes) > 2000) { echo json_encode(['success'=>false,'message'=>'Note is too long (max 2000 chars).']); exit; }
        if (!db()->fetchOne("SELECT id FROM orders WHERE id=?", [$oid])) {
            echo json_encode(['success'=>false,'message'=>'Order not found']); exit;
        }
        db()->execute("UPDATE orders SET notes = ? WHERE id = ?", [$notes ?: null, $oid]);
        echo json_encode(['success' => true, 'message' => 'Notes saved']);
    } elseif ($action === 'bulk_status') {
        // Apply one target status to many orders — each is validated against the transition
        // map; illegal ones are skipped (not failed) and reported back.
        $ids       = array_values(array_filter(array_map('intval', (array)($data['ids'] ?? []))));
        $newStatus = (string)($data['status'] ?? '');
        if (!$ids) { echo json_encode(['success'=>false,'message'=>'No orders selected']); exit; }
        if (!array_key_exists($newStatus, $ORDER_TRANSITIONS)) { echo json_encode(['success'=>false,'message'=>'Invalid status']); exit; }
        $updated = 0; $skipped = 0;
        foreach ($ids as $oid) {
            $cur = db()->fetchOne("SELECT status, payment_method, payment_status FROM orders WHERE id=?", [$oid]);
            if (!$cur) { $skipped++; continue; }
            if ($newStatus === $cur['status']) { continue; } // no-op, not counted
            if (!in_array($newStatus, $ORDER_TRANSITIONS[$cur['status']] ?? [], true)) { $skipped++; continue; }
            $extra = [];
            if ($newStatus === 'shipped')   $extra[] = "shipped_at = NOW()";
            if ($newStatus === 'delivered') $extra[] = "delivered_at = NOW()";
            if ($newStatus === 'delivered' && strtolower((string)($cur['payment_method'] ?? '')) === 'cod' && ($cur['payment_status'] ?? '') !== 'paid') {
                $extra[] = "payment_status = 'paid'";
            }
            $extraStr   = $extra ? ', ' . implode(', ', $extra) : '';
            $isTerminal = in_array($newStatus, ['cancelled','rejected','returned'], true);
            $pdo = db()->getConnection();
            try {
                if ($isTerminal) {
                    $pdo->beginTransaction();
                    db()->execute("UPDATE orders SET status = ? $extraStr WHERE id = ?", [$newStatus, $oid]);
                    reverseOrderEffects($oid);
                    $pdo->commit();
                } else {
                    db()->execute("UPDATE orders SET status = ? $extraStr WHERE id = ?", [$newStatus, $oid]);
                }
                db()->execute("INSERT INTO order_status_history (order_id, status, changed_by) VALUES (?,?,?)",
                    [$oid, $newStatus, $_SESSION['admin_id'] ?? null]);
                notifyOrderStatusWA($oid, $newStatus);
                $updated++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('bulk_status: ' . $e->getMessage());
                $skipped++;
            }
        }
        $msg = "$updated order(s) updated" . ($skipped ? ", $skipped skipped (illegal transition)" : "");
        echo json_encode(['success' => true, 'message' => $msg, 'updated' => $updated, 'skipped' => $skipped]);
    }
    exit;
}

// Send a WhatsApp order-status/shipping message for an order (best-effort, never throws).
// $status null = use the order's current status (e.g. after a tracking update).
function notifyOrderStatusWA(int $orderId, ?string $status): void {
    try {
        require_once __DIR__ . '/../includes/whatsapp_sender.php';
        $row = db()->fetchOne(
            "SELECT o.*, c.name AS cust_name, c.phone AS cust_phone
               FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ?",
            [$orderId]
        );
        if (!$row || empty($row['cust_phone'])) return;
        $cust = ['name' => $row['cust_name'], 'phone' => $row['cust_phone']];
        waOrderStatus($cust, $row, $status ?: ($row['status'] ?? ''), $row['tracking_number'] ?? '', $row['courier_name'] ?? '');
    } catch (Throwable $e) { error_log('WA orderStatus: ' . $e->getMessage()); }
}

// Filters
$search   = sanitize($_GET['search'] ?? '');
$status   = sanitize($_GET['status'] ?? '');
$payment  = sanitize($_GET['payment'] ?? '');
$dateFrom = sanitize($_GET['from'] ?? '');
$dateTo   = sanitize($_GET['to'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset  = ($page - 1) * $per_page;

$where  = ["1=1"];
$params = [];
if ($search)  { $where[] = "(o.order_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($status)  { $where[] = "o.status = ?"; $params[] = $status; }
if ($payment) { $where[] = "o.payment_status = ?"; $params[] = $payment; }
// Date-range filter (inclusive). Only applied when the value parses as a date.
if ($dateFrom && strtotime($dateFrom)) { $where[] = "DATE(o.created_at) >= ?"; $params[] = date('Y-m-d', strtotime($dateFrom)); }
if ($dateTo   && strtotime($dateTo))   { $where[] = "DATE(o.created_at) <= ?"; $params[] = date('Y-m-d', strtotime($dateTo)); }
$whereStr = implode(' AND ', $where);

// CSV export of the CURRENT (filtered) result set — respects search/status/payment/date.
if (($_GET['export'] ?? '') === 'csv') {
    $rows = db()->fetchAll(
        "SELECT o.order_number, c.name AS customer_name, c.phone, o.created_at, o.total,
                o.status, o.payment_status, o.payment_method
           FROM orders o JOIN customers c ON o.customer_id=c.id
          WHERE $whereStr ORDER BY o.created_at DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order #','Customer','Phone','Date','Amount','Status','Payment Status','Payment Method']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['order_number'], $r['customer_name'], $r['phone'], $r['created_at'],
                       $r['total'], $r['status'], $r['payment_status'], $r['payment_method']]);
    }
    fclose($out); exit;
}

$total  = db()->fetchOne("SELECT COUNT(*) as cnt FROM orders o JOIN customers c ON o.customer_id=c.id WHERE $whereStr", $params)['cnt'];
$pages  = ceil($total / $per_page);
$orders = db()->fetchAll("SELECT o.*, c.name as customer_name, c.phone, c.email as customer_email FROM orders o JOIN customers c ON o.customer_id=c.id WHERE $whereStr ORDER BY o.created_at DESC LIMIT $per_page OFFSET $offset", $params);

// View single order detail
$view_id = (int)($_GET['view'] ?? 0);
$order_detail = null;
if ($view_id) {
    $order_detail = db()->fetchOne("SELECT o.*, c.name as customer_name, c.phone, c.email as customer_email, c.clinic_name, cp.code AS coupon_code FROM orders o JOIN customers c ON o.customer_id=c.id LEFT JOIN coupons cp ON cp.id=o.coupon_id WHERE o.id=?", [$view_id]);
    if ($order_detail) {
        // Pull each item's product thumbnail (first image) for a visual order view.
        $order_detail['items'] = db()->fetchAll(
            "SELECT oi.*, JSON_UNQUOTE(JSON_EXTRACT(p.images,'$[0]')) AS product_image
               FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
              WHERE oi.order_id=?", [$view_id]);
        // Status change history (who/when) for the timeline.
        $order_detail['history'] = db()->fetchAll(
            "SELECT h.status, h.note, h.created_at, a.name AS admin_name
               FROM order_status_history h LEFT JOIN admin_users a ON a.id = h.changed_by
              WHERE h.order_id=? ORDER BY h.created_at ASC, h.id ASC", [$view_id]);
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Orders</h1>
        <p>Manage all orders — <?= $total ?> total orders</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="?status=pending" class="btn btn-outline btn-sm"><i class="fa-solid fa-clock"></i> Pending (<?= db()->fetchOne("SELECT COUNT(*) as c FROM orders WHERE status='pending'")['c'] ?>)</a>
    </div>
</div>

<!-- Order Detail Panel -->
<?php if ($order_detail): ?>
<div class="card fade-in" style="margin-bottom:24px;">
    <div class="card-header">
        <div>
            <span class="card-title">Order: <span class="text-gold"><?= htmlspecialchars($order_detail['order_number']) ?></span></span>
            <span class="badge badge-<?= statusBadge($order_detail['status']) ?>" style="margin-left:10px;"><?= $order_detail['status'] ?></span>
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn btn-outline btn-sm" onclick="printInvoice()"><i class="fa-solid fa-print"></i> Print Invoice</button>
            <button class="btn btn-outline btn-sm" onclick="printPacking()"><i class="fa-solid fa-box-open"></i> Packing Slip</button>
            <a href="orders.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
    </div>
    <div class="card-body">
        <?php $ship = json_decode($order_detail['shipping_address'] ?? '', true) ?: []; ?>
        <style>
            .od-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-bottom:20px; }
            @media (max-width:900px){ .od-grid { grid-template-columns:1fr; } }
            .od-h { font-size:0.8rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; }
            /* Order status timeline */
            .otl { padding-left:4px; }
            .otl-row { position:relative; display:flex; gap:12px; padding:0 0 14px 16px; border-left:2px solid var(--border-color); }
            .otl-row:last-child { border-left-color:transparent; padding-bottom:0; }
            .otl-dot { position:absolute; left:-7px; top:2px; width:12px; height:12px; border-radius:50%; border:2px solid var(--bg-elevated); box-shadow:0 0 0 1px var(--border-color); }
        </style>
        <div class="od-grid">
            <div>
                <h3 class="od-h">Customer Info</h3>
                <div class="font-bold"><a href="customers.php?view=<?= (int)$order_detail['customer_id'] ?>" class="text-gold" title="View customer profile"><?= htmlspecialchars($order_detail['customer_name']) ?></a></div>
                <?php if($order_detail['clinic_name']): ?><div class="text-muted"><?= htmlspecialchars($order_detail['clinic_name']) ?></div><?php endif; ?>
                <div class="text-muted"><?= htmlspecialchars($order_detail['phone'] ?? '') ?></div>
                <div class="text-muted"><?= htmlspecialchars($order_detail['customer_email'] ?? '') ?></div>
            </div>
            <div>
                <h3 class="od-h">Delivery Address</h3>
                <?php if($ship): ?>
                    <?php if(!empty($ship['name'])): ?><div class="font-bold"><?= htmlspecialchars($ship['name']) ?></div><?php endif; ?>
                    <?php if(!empty($ship['address'])): ?><div class="text-muted"><?= htmlspecialchars($ship['address']) ?></div><?php endif; ?>
                    <?php $loc = trim(implode(', ', array_filter([$ship['city'] ?? '', $ship['state'] ?? '', $ship['pincode'] ?? '']))); ?>
                    <?php if($loc): ?><div class="text-muted"><?= htmlspecialchars($loc) ?></div><?php endif; ?>
                    <?php if(!empty($ship['phone'])): ?><div class="text-muted"><i class="fa-solid fa-phone" style="font-size:.7rem;"></i> <?= htmlspecialchars($ship['phone']) ?></div><?php endif; ?>
                <?php else: ?>
                    <div class="text-muted">No delivery address on file</div>
                <?php endif; ?>
            </div>
            <div>
                <h3 class="od-h">Order Summary</h3>
                <div class="text-muted" style="font-size:.78rem;margin-bottom:6px;"><?= formatDate($order_detail['created_at'], 'd M Y') ?> · <?= date('h:i A', strtotime($order_detail['created_at'])) ?></div>
                <div>Subtotal: <strong><?= formatCurrency($order_detail['subtotal']) ?></strong></div>
                <div>Discount: <strong><?= formatCurrency($order_detail['discount']) ?></strong><?php if(!empty($order_detail['coupon_code'])): ?> <span class="badge badge-success" style="font-size:.62rem;"><?= htmlspecialchars($order_detail['coupon_code']) ?></span><?php endif; ?></div>
                <div>Shipping: <strong><?= formatCurrency($order_detail['shipping_charge']) ?></strong></div>
                <?php if((float)($order_detail['tax'] ?? 0) > 0): ?><div>Tax: <strong><?= formatCurrency($order_detail['tax']) ?></strong></div><?php endif; ?>
                <div style="margin-top:6px;font-size:1.1rem;" class="text-gold font-bold">Total: <?= formatCurrency($order_detail['total']) ?></div>
                <div style="margin-top:10px;display:flex;align-items:center;gap:8px;">
                    <?php $dpc = paymentColor($order_detail['payment_status']); ?>
                    <span class="pay-pill" style="color:<?= $dpc ?>;background:<?= $dpc ?>14;border:1px solid <?= $dpc ?>40;">
                        <span class="pay-dot" style="background:<?= $dpc ?>;"></span><?= ucfirst($order_detail['payment_status']) ?>
                    </span>
                    <?php if($order_detail['payment_method']): ?><span class="pay-method" style="margin-top:0;"><i class="fa-solid <?= paymentMethodIcon($order_detail['payment_method']) ?>"></i> <?= htmlspecialchars(paymentMethodLabel($order_detail['payment_method'])) ?></span><?php endif; ?>
                </div>
                <?php if(!empty($order_detail['tracking_number'])): ?>
                <div class="text-muted" style="font-size:.75rem;margin-top:6px;"><i class="fa-solid fa-truck"></i> <?= htmlspecialchars($order_detail['tracking_number']) ?><?= !empty($order_detail['courier_name']) ? ' · '.htmlspecialchars($order_detail['courier_name']) : '' ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items -->
        <h3 style="font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Order Items</h3>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach($order_detail['items'] as $item): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <?php $img = $item['product_image'] ?? ''; if ($img && !preg_match('#^https?://#', $img)) $img = APP_URL . '/' . ltrim($img, '/'); ?>
                                <?php if ($img): ?><img src="<?= htmlspecialchars($img) ?>" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:6px;background:#fff;border:1px solid var(--border-color);flex-shrink:0;" onerror="this.style.display='none'"><?php endif; ?>
                                <span><?= htmlspecialchars($item['product_name'] ?? '') ?><?php if(!empty($item['variant'])): ?><span class="text-muted" style="font-size:.72rem;"> · <?= htmlspecialchars($item['variant']) ?></span><?php endif; ?></span>
                            </div>
                        </td>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= formatCurrency($item['price']) ?></td>
                        <td class="font-bold"><?= formatCurrency($item['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($order_detail['items'])): ?>
                    <tr><td colspan="4" class="text-center text-muted">No items found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Timeline + Internal Notes -->
        <?php
        // Build the timeline: always "Order Placed", then logged history. For orders that
        // predate the history feature, fall back to the shipped/delivered timestamps.
        $timeline = [['label'=>'Order Placed', 'time'=>$order_detail['created_at'], 'by'=>'Customer', 'color'=>'#27AE60']];
        foreach ($order_detail['history'] as $h) {
            $timeline[] = ['label'=>ucwords(str_replace('_',' ',$h['status'])), 'time'=>$h['created_at'], 'by'=>($h['admin_name'] ?: 'Admin'), 'color'=>statusColor($h['status'])];
        }
        if (empty($order_detail['history'])) {
            if (!empty($order_detail['shipped_at']))   $timeline[] = ['label'=>'Shipped', 'time'=>$order_detail['shipped_at'], 'by'=>'', 'color'=>statusColor('shipped')];
            if (!empty($order_detail['delivered_at'])) $timeline[] = ['label'=>'Delivered', 'time'=>$order_detail['delivered_at'], 'by'=>'', 'color'=>statusColor('delivered')];
            if (!in_array($order_detail['status'], ['pending','shipped','delivered'], true))
                $timeline[] = ['label'=>ucwords(str_replace('_',' ',$order_detail['status'])), 'time'=>($order_detail['updated_at'] ?? $order_detail['created_at']), 'by'=>'', 'color'=>statusColor($order_detail['status'])];
        }
        ?>
        <div class="grid-2" style="margin-top:20px;gap:20px;">
            <div class="card" style="background:var(--bg-elevated);">
                <div class="card-body" style="padding:16px;">
                    <h4 style="font-size:0.82rem;margin-bottom:14px;color:var(--text-secondary);">ORDER TIMELINE</h4>
                    <div class="otl">
                        <?php foreach($timeline as $t): ?>
                        <div class="otl-row">
                            <span class="otl-dot" style="background:<?= $t['color'] ?>;"></span>
                            <div>
                                <div style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars($t['label']) ?></div>
                                <div class="text-muted" style="font-size:.72rem;"><?= date('d M Y, h:i A', strtotime($t['time'])) ?><?= !empty($t['by']) ? ' · '.htmlspecialchars($t['by']) : '' ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="card" style="background:var(--bg-elevated);">
                <div class="card-body" style="padding:16px;">
                    <h4 style="font-size:0.82rem;margin-bottom:12px;color:var(--text-secondary);">INTERNAL NOTES <span class="text-muted" style="font-weight:400;text-transform:none;">(admin only — not shown to customer)</span></h4>
                    <textarea class="form-control" id="orderNotes" rows="4" placeholder="Add a private note about this order..." style="margin-bottom:10px;resize:vertical;"><?= htmlspecialchars($order_detail['notes'] ?? '') ?></textarea>
                    <button class="btn btn-gold btn-sm" onclick="saveNotes(<?= $order_detail['id'] ?>)"><i class="fa-solid fa-floppy-disk"></i> Save Notes</button>
                </div>
            </div>
        </div>

        <!-- Update Controls -->
        <div class="grid-2" style="margin-top:20px;gap:20px;">
            <div class="card" style="background:var(--bg-elevated);">
                <div class="card-body" style="padding:16px;">
                    <h4 style="font-size:0.82rem;margin-bottom:12px;color:var(--text-secondary);">UPDATE ORDER STATUS</h4>
                    <?php $detailOpts = orderStatusOptions($order_detail['status'], $ORDER_TRANSITIONS); ?>
                    <select class="form-control" id="detailStatus" style="margin-bottom:10px;" <?= count($detailOpts) <= 1 ? 'disabled' : '' ?>>
                        <?php foreach($detailOpts as $s): ?>
                        <option value="<?= $s ?>" <?= $order_detail['status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" class="form-control" id="detailTracking" placeholder="Tracking number" value="<?= htmlspecialchars($order_detail['tracking_number'] ?? '') ?>" style="margin-bottom:10px;">
                    <input type="text" class="form-control" id="detailCourier" placeholder="Courier name (e.g. Blue Dart)" value="<?= htmlspecialchars($order_detail['courier_name'] ?? '') ?>" style="margin-bottom:10px;">
                    <button class="btn btn-gold btn-sm" onclick="updateOrderDetail(<?= $order_detail['id'] ?>)">
                        <i class="fa-solid fa-floppy-disk"></i> Update
                    </button>
                </div>
            </div>
            <div class="card" style="background:var(--bg-elevated);">
                <div class="card-body" style="padding:16px;">
                    <h4 style="font-size:0.82rem;margin-bottom:12px;color:var(--text-secondary);">UPDATE PAYMENT</h4>
                    <select class="form-control" id="detailPayStatus" style="margin-bottom:10px;">
                        <?php foreach($PAY_STATUSES as $s): ?>
                        <option value="<?= $s ?>" <?= $order_detail['payment_status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                        <?php if($order_detail['payment_status']==='refunded'): ?><option value="refunded" selected>Refunded</option><?php endif; ?>
                    </select>
                    <select class="form-control" id="detailPayMethod" style="margin-bottom:10px;">
                        <option value="">Payment Method</option>
                        <?php $methods = $PAY_METHODS; if(!empty($order_detail['payment_method']) && !in_array($order_detail['payment_method'], $methods, true)) $methods[] = $order_detail['payment_method']; ?>
                        <?php foreach($methods as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= $order_detail['payment_method']===$m?'selected':'' ?>><?= htmlspecialchars($m==='cod'?'COD (Cash on Delivery)':$m) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-gold btn-sm" onclick="updatePaymentDetail(<?= $order_detail['id'] ?>)">
                        <i class="fa-solid fa-floppy-disk"></i> Update Payment
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filter-bar fade-in">
    <div class="search-wrapper">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search order, customer..." value="<?= $search ?>">
    </div>
    <select class="form-control" id="statusFilter" style="max-width:150px;">
        <option value="">All Status</option>
        <?php foreach(['pending','processing','confirmed','shipped','out_for_delivery','delivered','returning','returned','cancelled','rejected','refunded'] as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-control" id="payFilter" style="max-width:150px;">
        <option value="">All Payments</option>
        <?php foreach(['unpaid','paid','partial','refunded'] as $s): ?>
        <option value="<?= $s ?>" <?= $payment===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" class="form-control" id="fromDate" title="From date" style="max-width:150px;" value="<?= htmlspecialchars($dateFrom) ?>">
    <input type="date" class="form-control" id="toDate" title="To date" style="max-width:150px;" value="<?= htmlspecialchars($dateTo) ?>">
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="orders.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
    <button class="btn btn-outline btn-sm" onclick="exportCsv()" title="Export current results to CSV"><i class="fa-solid fa-file-csv"></i> Export</button>
</div>

<!-- Orders Table -->
<style>
    /* Colour-coded status pill (clickable dropdown). Colour set inline per status. */
    .status-select {
        -webkit-appearance:none; -moz-appearance:none; appearance:none;
        border:1.5px solid; border-radius:20px; padding:5px 28px 5px 12px;
        font-size:.76rem; font-weight:700; cursor:pointer; max-width:160px; outline:none;
        background-repeat:no-repeat; background-position:right 10px center;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
        transition:filter .15s;
    }
    .status-select:hover:not(:disabled){ filter:brightness(.95); }
    .status-select:disabled{ cursor:default; padding-right:12px; background-image:none; opacity:.9; }
    .orders-table tbody tr{ transition:background .15s; }
    .orders-table tbody tr:hover{ background:var(--bg-elevated); }
    .orders-table tbody td{ vertical-align:middle; }
    /* Payment status pill + method chip (Shopify/Razorpay style) */
    .pay-pill{ display:inline-flex; align-items:center; gap:6px; padding:3px 11px; border-radius:20px; font-size:.74rem; font-weight:700; white-space:nowrap; }
    .pay-dot{ width:7px; height:7px; border-radius:50%; flex-shrink:0; }
    .pay-method{ display:inline-flex; align-items:center; gap:5px; font-size:.68rem; color:var(--text-muted); margin-top:4px; letter-spacing:.4px; text-transform:uppercase; }
    .pay-method i{ font-size:.72rem; opacity:.8; }
</style>
<div class="card fade-in">
    <!-- Bulk action bar (shown when rows are selected) -->
    <div id="bulkBar" style="display:none;padding:12px 16px;border-bottom:1px solid var(--border-color);gap:12px;align-items:center;background:var(--bg-elevated);">
        <span id="bulkCount" style="font-size:.82rem;font-weight:600;"></span>
        <select class="form-control" id="bulkStatus" style="max-width:190px;">
            <option value="">Set status to…</option>
            <?php foreach(['processing','confirmed','shipped','out_for_delivery','delivered','cancelled','rejected'] as $s): ?>
            <option value="<?= $s ?>"><?= ucwords(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-gold btn-sm" onclick="applyBulkStatus()"><i class="fa-solid fa-check"></i> Apply</button>
        <button class="btn btn-ghost btn-sm" onclick="clearBulk()">Clear</button>
    </div>
    <div class="table-responsive">
        <table class="orders-table">
            <thead>
                <tr>
                    <th style="width:34px;"><input type="checkbox" id="selectAllOrders" onchange="toggleAllOrders(this)" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;"></th>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($orders as $o): ?>
                <tr id="order-row-<?= $o['id'] ?>">
                    <td><input type="checkbox" class="order-check" value="<?= $o['id'] ?>" onchange="updateBulkBar()" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;"></td>
                    <td><a href="?view=<?= $o['id'] ?>" class="text-gold font-bold"><?= htmlspecialchars($o['order_number']) ?></a></td>
                    <td>
                        <div class="font-bold" style="font-size:0.84rem;"><?= htmlspecialchars($o['customer_name']) ?></div>
                        <div class="text-muted" style="font-size:0.73rem;"><?= htmlspecialchars($o['phone'] ?? '') ?></div>
                    </td>
                    <td>
                        <div><?= formatDate($o['created_at']) ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= timeAgo($o['created_at']) ?></div>
                    </td>
                    <td class="font-bold"><?= formatCurrency($o['total']) ?></td>
                    <td>
                        <?php $rowOpts = orderStatusOptions($o['status'], $ORDER_TRANSITIONS); $sc = statusColor($o['status']); $locked = count($rowOpts) <= 1; ?>
                        <select class="status-select" title="<?= $locked ? 'Final status' : 'Change status' ?>"
                            style="border-color:<?= $sc ?>;color:<?= $sc ?>;background:<?= $sc ?>14;"
                            data-current="<?= $o['status'] ?>"
                            onchange="quickUpdateStatus(<?= $o['id'] ?>, this.value, this)" <?= $locked ? 'disabled' : '' ?>>
                            <?php foreach($rowOpts as $s): ?>
                            <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <?php $pc = paymentColor($o['payment_status']); ?>
                        <span class="pay-pill" style="color:<?= $pc ?>;background:<?= $pc ?>14;border:1px solid <?= $pc ?>40;">
                            <span class="pay-dot" style="background:<?= $pc ?>;"></span><?= ucfirst($o['payment_status']) ?>
                        </span>
                        <?php if($o['payment_method']): ?>
                        <div class="pay-method"><i class="fa-solid <?= paymentMethodIcon($o['payment_method']) ?>"></i> <?= htmlspecialchars(paymentMethodLabel($o['payment_method'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?view=<?= $o['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="View Details">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($orders)): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="fa-solid fa-cart-shopping"></i><p>No orders found</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($pages > 1): ?>
    <div style="padding:16px 20px;border-top:1px solid var(--border-color);">
        <div class="pagination">
            <?php for($i=1;$i<=$pages;$i++): ?>
            <div class="page-item <?= $i==$page?'active':'' ?>" onclick="goPage(<?= $i ?>)"><?= $i ?></div>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const TERMINAL_STATUSES = ['cancelled','rejected','returned'];
const ORDER_DETAIL = <?= $order_detail ? json_encode([
    'order_number' => $order_detail['order_number'],
    'created_at'   => $order_detail['created_at'],
    'status'       => $order_detail['status'],
    'payment_status'=> $order_detail['payment_status'],
    'payment_method'=> $order_detail['payment_method'],
    'customer_name'=> $order_detail['customer_name'],
    'phone'        => $order_detail['phone'],
    'ship'         => $ship,
    'items'        => array_map(fn($i)=>['name'=>$i['product_name'],'qty'=>$i['quantity'],'price'=>$i['price'],'total'=>$i['total']], $order_detail['items']),
    'subtotal'     => $order_detail['subtotal'],
    'discount'     => $order_detail['discount'],
    'shipping'     => $order_detail['shipping_charge'],
    'tax'          => $order_detail['tax'],
    'total'        => $order_detail['total'],
], JSON_UNESCAPED_UNICODE) : 'null' ?>;
const COMPANY_NAME = <?= json_encode(APP_NAME) ?>;

function buildQuery(extra = {}) {
    const p = new URLSearchParams({
        search:  document.getElementById('searchInput')?.value || '',
        status:  document.getElementById('statusFilter')?.value || '',
        payment: document.getElementById('payFilter')?.value || '',
        from:    document.getElementById('fromDate')?.value || '',
        to:      document.getElementById('toDate')?.value || '',
    });
    Object.entries(extra).forEach(([k, v]) => p.set(k, v));
    [...p.entries()].forEach(([k, v]) => { if (!v) p.delete(k); });
    return p.toString();
}
function applyFilters() { window.location.href = 'orders.php?' + buildQuery(); }
function exportCsv()    { window.location.href = 'orders.php?' + buildQuery({ export: 'csv' }); }
function goPage(p)      { const q = new URLSearchParams(window.location.search); q.set('page', p); window.location.href = 'orders.php?' + q.toString(); }

async function postOrder(payload) {
    const res = await fetch('orders.php', {
        method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify(payload)
    });
    return res.json();
}

async function quickUpdateStatus(id, status, sel) {
    const current = sel?.getAttribute('data-current');
    if (TERMINAL_STATUSES.includes(status) &&
        !confirm(`Set this order to "${status}"? This is final and will restock the items.`)) {
        if (sel && current) sel.value = current;   // revert the dropdown
        return;
    }
    const r = await postOrder({ action:'update_status', id, status });
    if (r.success) {
        showToast(r.message || 'Order status updated', 'success');
        setTimeout(() => location.reload(), 600);   // refresh badges + allowed next states
    } else {
        showToast(r.message || 'Could not update status', 'error');
        if (sel && current) sel.value = current;     // revert on rejection
    }
}

async function updateOrderDetail(id) {
    const status   = document.getElementById('detailStatus').value;
    const tracking = document.getElementById('detailTracking').value;
    const courier  = document.getElementById('detailCourier').value;
    const rs = await postOrder({ action:'update_status', id, status });
    const rt = await postOrder({ action:'update_tracking', id, tracking_number:tracking, courier_name:courier });
    if (rs.success && rt.success) {
        showToast('Order updated successfully', 'success');
        setTimeout(() => location.reload(), 700);
    } else {
        // Surface the real reason (e.g. an illegal status transition).
        showToast((!rs.success ? rs.message : rt.message) || 'Update failed', 'error');
    }
}

async function updatePaymentDetail(id) {
    const payment_status = document.getElementById('detailPayStatus').value;
    const payment_method = document.getElementById('detailPayMethod').value;
    const r = await postOrder({ action:'update_payment', id, payment_status, payment_method });
    if (r.success) { showToast(r.message || 'Payment updated', 'success'); setTimeout(() => location.reload(), 600); }
    else showToast(r.message || 'Could not update payment', 'error');
}

async function saveNotes(id) {
    const notes = document.getElementById('orderNotes').value;
    const r = await postOrder({ action:'save_notes', id, notes });
    showToast(r.message || (r.success ? 'Notes saved' : 'Could not save notes'), r.success ? 'success' : 'error');
}

// ---- Bulk selection + bulk status update ----
function selectedOrderIds() {
    return [...document.querySelectorAll('.order-check:checked')].map(c => parseInt(c.value));
}
function updateBulkBar() {
    const n = selectedOrderIds().length;
    const bar = document.getElementById('bulkBar');
    bar.style.display = n ? 'flex' : 'none';
    if (n) document.getElementById('bulkCount').textContent = n + ' selected';
    const all = document.getElementById('selectAllOrders');
    const total = document.querySelectorAll('.order-check').length;
    if (all) all.checked = n > 0 && n === total;
}
function toggleAllOrders(cb) {
    document.querySelectorAll('.order-check').forEach(c => c.checked = cb.checked);
    updateBulkBar();
}
function clearBulk() {
    document.querySelectorAll('.order-check').forEach(c => c.checked = false);
    const all = document.getElementById('selectAllOrders'); if (all) all.checked = false;
    updateBulkBar();
}
async function applyBulkStatus() {
    const ids = selectedOrderIds();
    const status = document.getElementById('bulkStatus').value;
    if (!ids.length) return;
    if (!status) { showToast('Choose a status to set', 'info'); return; }
    if (!confirm(`Set ${ids.length} order(s) to "${status}"? Orders where this isn't a valid next step are skipped.`)) return;
    const r = await postOrder({ action:'bulk_status', ids, status });
    showToast(r.message || 'Done', r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 800);
}

function printInvoice() {
    const o = ORDER_DETAIL;
    if (!o) return;
    const inr = (n) => '₹' + Number(n || 0).toLocaleString('en-IN');
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    const ship = o.ship || {};
    const addr = [ship.name, ship.address, [ship.city, ship.state, ship.pincode].filter(Boolean).join(', '), ship.phone].filter(Boolean).map(esc).join('<br>');
    const rows = (o.items || []).map(it => `<tr>
        <td>${esc(it.name)}</td><td style="text-align:center">${esc(it.qty)}</td>
        <td style="text-align:right">${inr(it.price)}</td><td style="text-align:right">${inr(it.total)}</td></tr>`).join('');
    const html = `<!doctype html><html><head><meta charset="utf-8"><title>Invoice ${esc(o.order_number)}</title>
        <style>body{font-family:Arial,sans-serif;color:#222;padding:30px;max-width:780px;margin:auto}
        h1{margin:0;font-size:22px}.muted{color:#666;font-size:12px}table{width:100%;border-collapse:collapse;margin-top:16px}
        th,td{border-bottom:1px solid #ddd;padding:8px;font-size:13px;text-align:left}th{background:#f5f5f5}
        .tot{text-align:right;font-size:13px}.tot strong{font-size:15px}.flex{display:flex;justify-content:space-between;gap:24px;margin-top:20px}</style></head>
        <body>
        <div class="flex"><div><h1>${esc(COMPANY_NAME)}</h1><div class="muted">Tax Invoice</div></div>
        <div style="text-align:right"><div><strong>${esc(o.order_number)}</strong></div>
        <div class="muted">${esc(new Date(o.created_at).toLocaleString('en-IN'))}</div>
        <div class="muted">Status: ${esc(o.status)} · ${esc(o.payment_status)}</div></div></div>
        <div class="flex"><div><div class="muted">BILL TO</div>${esc(o.customer_name)}<br>${esc(o.phone || '')}</div>
        <div><div class="muted">SHIP TO</div>${addr || '—'}</div></div>
        <table><thead><tr><th>Product</th><th style="text-align:center">Qty</th><th style="text-align:right">Unit</th><th style="text-align:right">Total</th></tr></thead><tbody>${rows}</tbody></table>
        <div style="margin-top:16px" class="tot">Subtotal: ${inr(o.subtotal)}<br>Discount: ${inr(o.discount)}<br>Shipping: ${inr(o.shipping)}<br>${Number(o.tax)>0?('Tax: '+inr(o.tax)+'<br>'):''}<strong>Total: ${inr(o.total)}</strong></div>
        </body></html>`;
    const w = window.open('', '_blank');
    w.document.write(html); w.document.close(); w.focus();
    setTimeout(() => { w.print(); }, 300);
}

// Packing slip — warehouse pick/pack doc: items + qty + ship-to, NO prices, with a packed checkbox.
function printPacking() {
    const o = ORDER_DETAIL; if (!o) return;
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    const ship = o.ship || {};
    const addr = [ship.name, ship.address, [ship.city, ship.state, ship.pincode].filter(Boolean).join(', '), ship.phone].filter(Boolean).map(esc).join('<br>');
    const totalQty = (o.items || []).reduce((s,it)=>s+(parseInt(it.qty)||0),0);
    const rows = (o.items || []).map(it => `<tr>
        <td>${esc(it.name)}${it.sku?`<div class="muted">SKU: ${esc(it.sku)}</div>`:''}</td>
        <td style="text-align:center;font-size:17px;font-weight:bold">${esc(it.qty)}</td>
        <td style="text-align:center;width:64px"><span style="display:inline-block;width:18px;height:18px;border:1.5px solid #333;border-radius:3px"></span></td></tr>`).join('');
    const html = `<!doctype html><html><head><meta charset="utf-8"><title>Packing Slip ${esc(o.order_number)}</title>
        <style>body{font-family:Arial,sans-serif;color:#222;padding:30px;max-width:780px;margin:auto}
        h1{margin:0;font-size:22px}.muted{color:#666;font-size:12px}table{width:100%;border-collapse:collapse;margin-top:16px}
        th,td{border-bottom:1px solid #ddd;padding:10px;font-size:13px;text-align:left}th{background:#f5f5f5}
        .flex{display:flex;justify-content:space-between;gap:24px;margin-top:20px}.box{border:1px solid #ddd;padding:12px;border-radius:6px}</style></head>
        <body>
        <div class="flex"><div><h1>${esc(COMPANY_NAME)}</h1><div class="muted">Packing Slip — not a tax invoice</div></div>
        <div style="text-align:right"><div><strong>${esc(o.order_number)}</strong></div>
        <div class="muted">${esc(new Date(o.created_at).toLocaleDateString('en-IN'))}</div></div></div>
        <div class="flex"><div class="box" style="flex:1"><div class="muted">SHIP TO</div>${addr || '—'}</div>
        <div class="box" style="text-align:center"><div class="muted">TOTAL ITEMS</div><div style="font-size:24px;font-weight:bold">${totalQty}</div><div class="muted">${(o.items||[]).length} line(s)</div></div></div>
        <table><thead><tr><th>Product</th><th style="text-align:center">Qty</th><th style="text-align:center">Packed</th></tr></thead><tbody>${rows}</tbody></table>
        <div style="margin-top:34px;font-size:12px;color:#666">Packed by: ______________________&nbsp;&nbsp;&nbsp; Checked by: ______________________&nbsp;&nbsp;&nbsp; Date: ____________</div>
        </body></html>`;
    const w = window.open('', '_blank');
    w.document.write(html); w.document.close(); w.focus();
    setTimeout(() => { w.print(); }, 300);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
