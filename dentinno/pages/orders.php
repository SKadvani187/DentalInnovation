<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/order_effects.php';
$page_title = 'Orders';

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
        <a href="orders.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
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
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
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
                        <span class="badge <?= statusBadge($o['payment_status']) ?>"><?= $o['payment_status'] ?></span>
                        <?php if($o['payment_method']): ?>
                        <div class="text-muted" style="font-size:0.72rem;margin-top:2px;"><?= $o['payment_method'] ?></div>
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
                <tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-cart-shopping"></i><p>No orders found</p></div></td></tr>
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
function filterUrl(page) {
    const s  = encodeURIComponent(document.getElementById('searchInput').value);
    const st = encodeURIComponent(document.getElementById('statusFilter').value);
    const p  = encodeURIComponent(document.getElementById('payFilter').value);
    return `orders.php?search=${s}&status=${st}&payment=${p}` + (page ? `&page=${page}` : '');
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
