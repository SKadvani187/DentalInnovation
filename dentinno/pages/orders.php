<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/order_effects.php';
$page_title = 'Orders';

// Accent colour per PAYMENT status — drives the coloured payment pills in the list.
function paymentColor(string $p): string {
    return ['paid'=>'#27AE60','unpaid'=>'#E74C3C','partial'=>'#F39C12','pending'=>'#95A5A6','refunded'=>'#7F8C8D'][$p] ?? '#95A5A6';
}
// Font Awesome icon per payment method.
function paymentMethodIcon(string $m): string {
    $m = strtolower($m);
    if ($m === 'upi') return 'fa-mobile-screen-button';
    if ($m === 'card' || strpos($m,'credit')!==false || strpos($m,'debit')!==false) return 'fa-credit-card';
    if (strpos($m,'bank')!==false || strpos($m,'net')!==false || $m==='online') return 'fa-building-columns';
    if ($m === 'cod' || strpos($m,'cash')!==false) return 'fa-money-bill-wave';
    if (strpos($m,'cheque')!==false) return 'fa-money-check-dollar';
    return 'fa-wallet';
}
// Friendly label for a payment method (cod -> COD, online -> Online, etc.).
function paymentMethodLabel(string $m): string {
    $m2 = strtolower($m);
    if ($m2 === 'cod') return 'COD';
    if ($m2 === 'online') return 'Online';
    return $m;
}
// One accent colour per ORDER status — green=delivered, red=cancelled, etc.
function statusColor(string $status): string {
    return ['pending'=>'#F39C12','processing'=>'#3498DB','confirmed'=>'#2980B9','shipped'=>'#9B59B6',
        'out_for_delivery'=>'#16A085','delivered'=>'#27AE60','returning'=>'#E67E22','returned'=>'#7F8C8D',
        'cancelled'=>'#E74C3C','rejected'=>'#C0392B','refunded'=>'#7F8C8D'][$status] ?? '#7F8C8D';
}

// Allowed next-states per current state. Forward-only lifecycle; terminal states are
// dead ends. 'refunded' is NOT settable here — it must go through the refunds module
// (pages/refunds.php) so the gateway refund + effect reversal stay coupled.
// Shared by the AJAX guard below AND the status dropdowns, so the UI can only offer
// transitions the server will accept.
$ORDER_TRANSITIONS = [
    'pending'          => ['processing','confirmed','cancelled','rejected'],
    'processing'       => ['confirmed','shipped','cancelled','rejected'],
    'confirmed'        => ['shipped','out_for_delivery','cancelled','rejected'],
    'shipped'          => ['out_for_delivery','delivered','returning'],
    'out_for_delivery' => ['delivered','returning'],
    'delivered'        => ['returning'],
    'returning'        => ['returned'],
    'returned'         => [],
    'cancelled'        => [],
    'rejected'         => [],
    'refunded'         => [],
];

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'update_status') {
        $newStatus = (string)($data['status'] ?? '');
        $oid       = (int)$data['id'];
        $TRANSITIONS = $ORDER_TRANSITIONS;

        $cur = db()->fetchOne("SELECT status FROM orders WHERE id=?", [$oid]);
        if (!$cur) { echo json_encode(['success'=>false,'message'=>'Order not found']); exit; }
        $curStatus = (string)$cur['status'];

        if ($newStatus === $curStatus) { echo json_encode(['success'=>true,'message'=>'No change']); exit; }
        $allowed = $TRANSITIONS[$curStatus] ?? null;
        if ($allowed === null) { echo json_encode(['success'=>false,'message'=>"Unknown current status '$curStatus'"]); exit; }
        if (!in_array($newStatus, $allowed, true)) {
            echo json_encode(['success'=>false,'message'=>"Can't move an order from '$curStatus' to '$newStatus'."]); exit;
        }

        $extra = [];
        if ($newStatus === 'shipped')   $extra[] = "shipped_at = NOW()";
        if ($newStatus === 'delivered') $extra[] = "delivered_at = NOW()";
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
        // COD cash is collected at the doorstep — standard ecommerce behaviour (Woo/Shopify)
        // is to mark the payment received the moment the order is delivered. Only 'unpaid'
        // flips; partial/disputed payments stay manual.
        $codPaid = false;
        if ($newStatus === 'delivered') {
            $codPaid = db()->execute(
                "UPDATE orders SET payment_status='paid' WHERE id=? AND payment_method='cod' AND payment_status='unpaid'",
                [$oid]
            ) > 0;
        }
        // Best-effort WhatsApp status update (only for customer-relevant transitions).
        if (in_array($newStatus, ['confirmed', 'shipped', 'out_for_delivery', 'delivered', 'returning', 'returned', 'cancelled', 'rejected'], true)) {
            notifyOrderStatusWA($oid, $newStatus);
        }
        echo json_encode(['success' => true, 'message' => 'Order status updated' . ($codPaid ? ' — COD payment marked paid' : '')]);
    } elseif ($action === 'update_payment') {
        // 'refunded' must be reached through the refunds module so the gateway refund and
        // effect reversal are coupled — block setting it manually here (it would desync).
        $ps = (string)($data['payment_status'] ?? '');
        if ($ps === 'refunded') {
            echo json_encode(['success'=>false,'message'=>'Use the Refunds page to refund an order.']); exit;
        }
        // 'pending' = online order awaiting gateway capture (set by the storefront).
        if (!in_array($ps, ['unpaid','pending','paid','partial'], true)) {
            echo json_encode(['success'=>false,'message'=>"Invalid payment status '$ps'."]); exit;
        }
        db()->execute("UPDATE orders SET payment_status = ?, payment_method = ? WHERE id = ?",
            [$ps, $data['payment_method'], $data['id']]);
        echo json_encode(['success' => true, 'message' => 'Payment updated']);
    } elseif ($action === 'update_tracking') {
        db()->execute("UPDATE orders SET tracking_number = ?, courier_name = ? WHERE id = ?",
            [$data['tracking_number'], $data['courier_name'], $data['id']]);
        // Best-effort WhatsApp shipping update with the new tracking details.
        notifyOrderStatusWA((int)$data['id'], null);
        echo json_encode(['success' => true, 'message' => 'Tracking updated']);
    } elseif ($action === 'bulk_status') {
        // Apply one target status to many orders — each validated against the same transition
        // map; illegal ones are skipped (not failed) and reported back.
        $ids       = array_values(array_filter(array_map('intval', (array)($data['ids'] ?? []))));
        $newStatus = (string)($data['status'] ?? '');
        if (!$ids) { echo json_encode(['success'=>false,'message'=>'No orders selected']); exit; }
        if (!array_key_exists($newStatus, $ORDER_TRANSITIONS)) { echo json_encode(['success'=>false,'message'=>'Invalid status']); exit; }
        $updated = 0; $skipped = 0;
        foreach ($ids as $oid) {
            $cur = db()->fetchOne("SELECT status, payment_method, payment_status FROM orders WHERE id=?", [$oid]);
            if (!$cur) { $skipped++; continue; }
            if ($newStatus === $cur['status']) { continue; } // no-op
            if (!in_array($newStatus, $ORDER_TRANSITIONS[$cur['status']] ?? [], true)) { $skipped++; continue; }
            $extra = [];
            if ($newStatus === 'shipped')   $extra[] = "shipped_at = NOW()";
            if ($newStatus === 'delivered') $extra[] = "delivered_at = NOW()";
            // COD cash collected on delivery — auto-mark paid (same rule as single update).
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
$search  = sanitize($_GET['search'] ?? '');
$status  = sanitize($_GET['status'] ?? '');
$payment = sanitize($_GET['payment'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset  = ($page - 1) * $per_page;

$where  = ["1=1"];
$params = [];
if ($search)  { $where[] = "(o.order_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($status)  { $where[] = "o.status = ?"; $params[] = $status; }
if ($payment) { $where[] = "o.payment_status = ?"; $params[] = $payment; }
$whereStr = implode(' AND ', $where);

// CSV export of the CURRENT (filtered) result set — respects search/status/payment.
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
        $order_detail['items'] = db()->fetchAll("SELECT * FROM order_items WHERE order_id=?", [$view_id]);
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
        <button class="btn btn-outline btn-sm" onclick="exportCsv()" title="Export the current filtered list to CSV"><i class="fa-solid fa-file-csv"></i> Export</button>
        <a href="?status=pending" class="btn btn-outline btn-sm"><i class="fa-solid fa-clock"></i> Pending (<?= db()->fetchOne("SELECT COUNT(*) as c FROM orders WHERE status='pending'")['c'] ?>)</a>
    </div>
</div>

<!-- Order Detail Panel -->
<?php if ($order_detail): ?>
<div class="card fade-in" style="margin-bottom:24px;">
    <div class="card-header">
        <div>
            <span class="card-title">Order: <span class="text-gold"><?= $order_detail['order_number'] ?></span></span>
            <span class="badge <?= statusBadge($order_detail['status']) ?>" style="margin-left:10px;"><?= $order_detail['status'] ?></span>
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn btn-outline btn-sm" onclick="printInvoice()"><i class="fa-solid fa-print"></i> Print Invoice</button>
            <button class="btn btn-outline btn-sm" onclick="printPacking()"><i class="fa-solid fa-box-open"></i> Packing Slip</button>
            <a href="orders.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="grid-2" style="margin-bottom:20px;">
            <div>
                <h3 style="font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Customer Info</h3>
                <div class="font-bold"><?= htmlspecialchars((string)$order_detail['customer_name']) ?></div>
                <?php if($order_detail['clinic_name']): ?><div class="text-muted"><?= htmlspecialchars((string)$order_detail['clinic_name']) ?></div><?php endif; ?>
                <div class="text-muted"><?= htmlspecialchars((string)$order_detail['phone']) ?></div>
                <?php $oEmail = (string)($order_detail['customer_email'] ?? ''); if($oEmail !== '' && !str_ends_with($oEmail, '@storefront.local')): ?>
                <div class="text-muted"><?= htmlspecialchars($oEmail) ?></div>
                <?php endif; ?>

                <?php
                // Delivery address captured at checkout (free-form JSON from the storefront;
                // key names follow the React address form, with older fallbacks).
                $ship = json_decode((string)($order_detail['shipping_address'] ?? ''), true);
                ?>
                <h3 style="font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin:16px 0 10px;">Delivery Address</h3>
                <?php if (is_array($ship) && $ship): ?>
                    <?php if(!empty($ship['name'])): ?><div class="font-bold"><?= htmlspecialchars($ship['name']) ?></div><?php endif; ?>
                    <?php if(!empty($ship['mobile'])): ?><div class="text-muted"><?= htmlspecialchars($ship['mobile']) ?></div><?php endif; ?>
                    <div class="text-muted">
                        <?= htmlspecialchars(implode(', ', array_filter([
                            $ship['line1'] ?? $ship['building'] ?? '',
                            $ship['line2'] ?? $ship['area'] ?? '',
                            $ship['landmark'] ?? '',
                            $ship['city'] ?? '',
                            $ship['district'] ?? '',
                            $ship['state'] ?? '',
                        ]))) ?><?= !empty($ship['pincode']) ? ' — ' . htmlspecialchars($ship['pincode']) : '' ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted">No address on file (order created in admin)</div>
                <?php endif; ?>
            </div>
            <div>
                <h3 style="font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Order Summary</h3>
                <div>Subtotal: <strong><?= formatCurrency($order_detail['subtotal']) ?></strong></div>
                <div>Discount: <strong><?= formatCurrency($order_detail['discount']) ?></strong>
                    <?php if(!empty($order_detail['coupon_code'])): ?><span class="text-muted" style="font-size:0.8rem;">(coupon: <?= htmlspecialchars($order_detail['coupon_code']) ?>)</span><?php endif; ?>
                </div>
                <div>Shipping: <strong><?= formatCurrency($order_detail['shipping_charge']) ?></strong></div>
                <div>Tax: <strong><?= formatCurrency($order_detail['tax']) ?></strong></div>
                <div style="margin-top:8px;font-size:1.1rem;" class="text-gold font-bold">Total: <?= formatCurrency($order_detail['total']) ?></div>
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
                            <?= htmlspecialchars((string)$item['product_name']) ?>
                            <?php if(($item['line_type'] ?? 'product') === 'gift'): ?>
                                <span class="badge badge-success" style="margin-left:6px;">FREE GIFT</span>
                            <?php elseif(($item['line_type'] ?? '') === 'offer'): ?>
                                <span class="badge badge-info" style="margin-left:6px;">OFFER</span>
                            <?php endif; ?>
                            <?php if(!empty($item['variant'])): ?>
                                <div class="text-muted" style="font-size:0.75rem;">Variant: <?= htmlspecialchars($item['variant']) ?></div>
                            <?php endif; ?>
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

        <!-- Update Controls -->
        <div class="grid-2" style="margin-top:20px;gap:20px;">
            <div class="card" style="background:var(--bg-elevated);">
                <div class="card-body" style="padding:16px;">
                    <h4 style="font-size:0.82rem;margin-bottom:12px;color:var(--text-secondary);">UPDATE ORDER STATUS</h4>
                    <?php
                    // Offer only the current status + transitions the server will accept;
                    // anything else fails the AJAX guard anyway.
                    $detailOpts = array_merge([$order_detail['status']], $ORDER_TRANSITIONS[$order_detail['status']] ?? []);
                    ?>
                    <select class="form-control" id="detailStatus" style="margin-bottom:10px;" <?= count($detailOpts) < 2 ? 'disabled title="Terminal status — no further transitions"' : '' ?>>
                        <?php foreach($detailOpts as $s): ?>
                        <option value="<?= $s ?>" <?= $order_detail['status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" class="form-control" id="detailTracking" placeholder="Tracking number" value="<?= htmlspecialchars((string)$order_detail['tracking_number']) ?>" style="margin-bottom:10px;">
                    <input type="text" class="form-control" id="detailCourier" placeholder="Courier name (e.g. Blue Dart)" value="<?= htmlspecialchars((string)$order_detail['courier_name']) ?>" style="margin-bottom:10px;">
                    <button class="btn btn-gold btn-sm" onclick="updateOrderDetail(<?= $order_detail['id'] ?>)">
                        <i class="fa-solid fa-floppy-disk"></i> Update
                    </button>
                </div>
            </div>
            <div class="card" style="background:var(--bg-elevated);">
                <div class="card-body" style="padding:16px;">
                    <h4 style="font-size:0.82rem;margin-bottom:12px;color:var(--text-secondary);">UPDATE PAYMENT</h4>
                    <?php
                    // 'pending' = storefront online order awaiting gateway capture. 'refunded'
                    // is display-only (set via the Refunds page) — listed only when already set.
                    $payOpts = ['unpaid','pending','paid','partial'];
                    if ($order_detail['payment_status'] === 'refunded') $payOpts[] = 'refunded';
                    // Storefront writes lowercase 'cod'/'online' (CheckoutDrawer buildPayload);
                    // the rest are admin-entered.
                    $payMethods = ['cod'=>'COD','online'=>'Online (Razorpay)','UPI'=>'UPI','Card'=>'Card','Net Banking'=>'Net Banking','Bank Transfer'=>'Bank Transfer','Cash'=>'Cash','Cheque'=>'Cheque'];
                    ?>
                    <select class="form-control" id="detailPayStatus" style="margin-bottom:10px;">
                        <?php foreach($payOpts as $s): ?>
                        <option value="<?= $s ?>" <?= $order_detail['payment_status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-control" id="detailPayMethod" style="margin-bottom:10px;">
                        <option value="">Payment Method</option>
                        <?php foreach($payMethods as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $order_detail['payment_method']===$val?'selected':'' ?>><?= $label ?></option>
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
        <?php foreach(['unpaid','pending','paid','partial','refunded'] as $s): ?>
        <option value="<?= $s ?>" <?= $payment===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="orders.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<!-- Orders Table -->
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
        <table>
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
                    <td><a href="?view=<?= $o['id'] ?>" class="text-gold font-bold"><?= $o['order_number'] ?></a></td>
                    <td>
                        <div class="font-bold" style="font-size:0.84rem;"><?= htmlspecialchars((string)$o['customer_name']) ?></div>
                        <div class="text-muted" style="font-size:0.73rem;"><?= htmlspecialchars((string)$o['phone']) ?></div>
                    </td>
                    <td>
                        <div><?= formatDate($o['created_at']) ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= timeAgo($o['created_at']) ?></div>
                    </td>
                    <td class="font-bold"><?= formatCurrency($o['total']) ?></td>
                    <td>
                        <?php $rowOpts = array_merge([$o['status']], $ORDER_TRANSITIONS[$o['status']] ?? []); ?>
                        <select class="form-control" style="padding:4px 8px;font-size:0.78rem;max-width:140px;"
                            data-prev="<?= $o['status'] ?>" onchange="quickUpdateStatus(<?= $o['id'] ?>, this)"
                            <?= count($rowOpts) < 2 ? 'disabled title="Terminal status — no further transitions"' : '' ?>>
                            <?php foreach($rowOpts as $s): ?>
                            <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <?php $pc = paymentColor($o['payment_status']); ?>
                        <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:0.72rem;font-weight:600;color:<?= $pc ?>;background:<?= $pc ?>1a;border:1px solid <?= $pc ?>33;"><?= ucfirst($o['payment_status']) ?></span>
                        <?php if($o['payment_method']): ?>
                        <div class="text-muted" style="font-size:0.72rem;margin-top:3px;"><i class="fa-solid <?= paymentMethodIcon($o['payment_method']) ?>" style="margin-right:3px;"></i><?= htmlspecialchars(paymentMethodLabel($o['payment_method'])) ?></div>
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
<?php
// Order detail exposed to JS for the Invoice / Packing Slip print views (open-in-new-window,
// browser print dialog). Only present when viewing a single order.
$printShip = $order_detail ? (json_decode((string)($order_detail['shipping_address'] ?? ''), true) ?: []) : [];
?>
const ORDER_DETAIL = <?= $order_detail ? json_encode([
    'order_number'  => $order_detail['order_number'],
    'created_at'    => $order_detail['created_at'],
    'status'        => $order_detail['status'],
    'payment_status'=> $order_detail['payment_status'],
    'payment_method'=> $order_detail['payment_method'],
    'customer_name' => $order_detail['customer_name'],
    'phone'         => $order_detail['phone'],
    'ship'          => $printShip,
    'items'         => array_map(fn($i)=>['name'=>$i['product_name'],'qty'=>$i['quantity'],'price'=>$i['price'],'total'=>$i['total']], $order_detail['items'] ?? []),
    'subtotal'      => $order_detail['subtotal'],
    'discount'      => $order_detail['discount'],
    'shipping'      => $order_detail['shipping_charge'],
    'tax'           => $order_detail['tax'],
    'total'         => $order_detail['total'],
], JSON_UNESCAPED_UNICODE) : 'null' ?>;
const COMPANY_NAME = <?= json_encode(APP_NAME) ?>;

function printInvoice() {
    const o = ORDER_DETAIL;
    if (!o) return;
    const inr = (n) => '₹' + Number(n || 0).toLocaleString('en-IN');
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    const ship = o.ship || {};
    const addrLine = [ship.line1 || ship.building, ship.line2 || ship.area, ship.landmark, [ship.city, ship.district, ship.state].filter(Boolean).join(', '), ship.pincode].filter(Boolean).map(esc).join('<br>');
    const addr = [ship.name ? esc(ship.name) : '', addrLine, ship.mobile ? esc(ship.mobile) : ''].filter(Boolean).join('<br>');
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
    const addrLine = [ship.line1 || ship.building, ship.line2 || ship.area, ship.landmark, [ship.city, ship.district, ship.state].filter(Boolean).join(', '), ship.pincode].filter(Boolean).map(esc).join('<br>');
    const addr = [ship.name ? esc(ship.name) : '', addrLine, ship.mobile ? esc(ship.mobile) : ''].filter(Boolean).join('<br>');
    const totalQty = (o.items || []).reduce((s,it)=>s+(parseInt(it.qty)||0),0);
    const rows = (o.items || []).map(it => `<tr>
        <td>${esc(it.name)}</td>
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

function filterUrl(page) {
    const s  = encodeURIComponent(document.getElementById('searchInput').value);
    const st = encodeURIComponent(document.getElementById('statusFilter').value);
    const p  = encodeURIComponent(document.getElementById('payFilter').value);
    return `orders.php?search=${s}&status=${st}&payment=${p}` + (page ? `&page=${page}` : '');
}
// Download the current filtered order list as CSV (server streams it; filters preserved).
function exportCsv() { window.location.href = filterUrl() + '&export=csv'; }

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
    const r = await postOrders({ action:'bulk_status', ids, status });
    showToast(r.message || 'Done', r.success ? 'success' : 'danger');
    if (r.success) setTimeout(() => location.reload(), 800);
}
function applyFilters() { window.location.href = filterUrl(); }
function goPage(p) { window.location.href = filterUrl(p); }

async function postOrders(body) {
    try {
        const res = await fetch('orders.php', {
            method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify(body)
        });
        return await res.json();
    } catch (e) { return {success:false, message:'Server error — request failed'}; }
}

async function quickUpdateStatus(id, sel) {
    const r = await postOrders({action:'update_status', id, status: sel.value});
    if (r.success) {
        sel.dataset.prev = sel.value;
        showToast(r.message || 'Order status updated', 'success');
        // Reload so the dropdown re-renders with the new status's allowed transitions.
        setTimeout(() => location.reload(), 600);
    } else {
        sel.value = sel.dataset.prev;
        showToast(r.message || 'Status update failed', 'danger');
    }
}

async function updateOrderDetail(id) {
    const status   = document.getElementById('detailStatus').value;
    const tracking = document.getElementById('detailTracking').value;
    const courier  = document.getElementById('detailCourier').value;
    const r1 = await postOrders({action:'update_status', id, status});
    const r2 = await postOrders({action:'update_tracking', id, tracking_number:tracking, courier_name:courier});
    if (r1.success && r2.success) {
        showToast('Order updated successfully', 'success');
        setTimeout(() => location.reload(), 600);
    } else {
        showToast((!r1.success ? r1.message : r2.message) || 'Update failed', 'danger');
    }
}

async function updatePaymentDetail(id) {
    const payment_status = document.getElementById('detailPayStatus').value;
    const payment_method = document.getElementById('detailPayMethod').value;
    const r = await postOrders({action:'update_payment', id, payment_status, payment_method});
    if (r.success) {
        showToast(r.message || 'Payment status updated', 'success');
        setTimeout(() => location.reload(), 600);
    } else {
        showToast(r.message || 'Payment update failed', 'danger');
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
