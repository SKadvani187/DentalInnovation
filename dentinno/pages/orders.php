<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Orders';

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'update_status') {
        $extra = [];
        if ($data['status'] === 'shipped')   $extra[] = "shipped_at = NOW()";
        if ($data['status'] === 'delivered') $extra[] = "delivered_at = NOW()";
        $extraStr = $extra ? ', ' . implode(', ', $extra) : '';
        db()->execute("UPDATE orders SET status = ? $extraStr WHERE id = ?", [$data['status'], $data['id']]);
        echo json_encode(['success' => true, 'message' => 'Order status updated']);
    } elseif ($action === 'update_payment') {
        db()->execute("UPDATE orders SET payment_status = ?, payment_method = ? WHERE id = ?",
            [$data['payment_status'], $data['payment_method'], $data['id']]);
        echo json_encode(['success' => true, 'message' => 'Payment updated']);
    } elseif ($action === 'update_tracking') {
        db()->execute("UPDATE orders SET tracking_number = ?, courier_name = ? WHERE id = ?",
            [$data['tracking_number'], $data['courier_name'], $data['id']]);
        echo json_encode(['success' => true, 'message' => 'Tracking updated']);
    }
    exit;
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
    $order_detail = db()->fetchOne("SELECT o.*, c.name as customer_name, c.phone, c.email as customer_email, c.clinic_name FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.id=?", [$view_id]);
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
            <span class="badge badge-<?= statusBadge($order_detail['status']) ?>" style="margin-left:10px;"><?= $order_detail['status'] ?></span>
        </div>
        <a href="orders.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>
    <div class="card-body">
        <div class="grid-2" style="margin-bottom:20px;">
            <div>
                <h3 style="font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Customer Info</h3>
                <div class="font-bold"><?= htmlspecialchars($order_detail['customer_name']) ?></div>
                <?php if($order_detail['clinic_name']): ?><div class="text-muted"><?= $order_detail['clinic_name'] ?></div><?php endif; ?>
                <div class="text-muted"><?= $order_detail['phone'] ?></div>
                <div class="text-muted"><?= $order_detail['customer_email'] ?></div>
            </div>
            <div>
                <h3 style="font-size:0.8rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Order Summary</h3>
                <div>Subtotal: <strong><?= formatCurrency($order_detail['subtotal']) ?></strong></div>
                <div>Discount: <strong><?= formatCurrency($order_detail['discount']) ?></strong></div>
                <div>Shipping: <strong><?= formatCurrency($order_detail['shipping_charge']) ?></strong></div>
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
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
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
                    <select class="form-control" id="detailStatus" style="margin-bottom:10px;">
                        <?php foreach(['pending','processing','confirmed','shipped','delivered','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $order_detail['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" class="form-control" id="detailTracking" placeholder="Tracking number" value="<?= $order_detail['tracking_number'] ?>" style="margin-bottom:10px;">
                    <input type="text" class="form-control" id="detailCourier" placeholder="Courier name (e.g. Blue Dart)" value="<?= $order_detail['courier_name'] ?>" style="margin-bottom:10px;">
                    <button class="btn btn-gold btn-sm" onclick="updateOrderDetail(<?= $order_detail['id'] ?>)">
                        <i class="fa-solid fa-floppy-disk"></i> Update
                    </button>
                </div>
            </div>
            <div class="card" style="background:var(--bg-elevated);">
                <div class="card-body" style="padding:16px;">
                    <h4 style="font-size:0.82rem;margin-bottom:12px;color:var(--text-secondary);">UPDATE PAYMENT</h4>
                    <select class="form-control" id="detailPayStatus" style="margin-bottom:10px;">
                        <?php foreach(['unpaid','paid','partial','refunded'] as $s): ?>
                        <option value="<?= $s ?>" <?= $order_detail['payment_status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-control" id="detailPayMethod" style="margin-bottom:10px;">
                        <option value="">Payment Method</option>
                        <?php foreach(['UPI','Card','Net Banking','Bank Transfer','Cash','Cheque'] as $m): ?>
                        <option value="<?= $m ?>" <?= $order_detail['payment_method']===$m?'selected':'' ?>><?= $m ?></option>
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
        <?php foreach(['pending','processing','confirmed','shipped','delivered','cancelled','refunded'] as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-control" id="payFilter" style="max-width:150px;">
        <option value="">All Payments</option>
        <?php foreach(['unpaid','paid','partial','refunded'] as $s): ?>
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
                        <div class="font-bold" style="font-size:0.84rem;"><?= htmlspecialchars($o['customer_name']) ?></div>
                        <div class="text-muted" style="font-size:0.73rem;"><?= $o['phone'] ?></div>
                    </td>
                    <td>
                        <div><?= formatDate($o['created_at']) ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= timeAgo($o['created_at']) ?></div>
                    </td>
                    <td class="font-bold"><?= formatCurrency($o['total']) ?></td>
                    <td>
                        <select class="form-control" style="padding:4px 8px;font-size:0.78rem;max-width:120px;"
                            onchange="quickUpdateStatus(<?= $o['id'] ?>, this.value)">
                            <?php foreach(['pending','processing','confirmed','shipped','delivered','cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <span class="badge badge-<?= statusBadge($o['payment_status']) ?>"><?= $o['payment_status'] ?></span>
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
function applyFilters() {
    const s  = document.getElementById('searchInput').value;
    const st = document.getElementById('statusFilter').value;
    const p  = document.getElementById('payFilter').value;
    window.location.href = `orders.php?search=${s}&status=${st}&payment=${p}`;
}
function goPage(p) { window.location.href = `orders.php?page=${p}`; }

async function quickUpdateStatus(id, status) {
    const res = await fetch('orders.php', {
        method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({action:'update_status', id, status})
    });
    const r = await res.json();
    if(r.success) showToast('Order status updated', 'success');
}

async function updateOrderDetail(id) {
    const status   = document.getElementById('detailStatus').value;
    const tracking = document.getElementById('detailTracking').value;
    const courier  = document.getElementById('detailCourier').value;
    const r1 = await fetch('orders.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'update_status',id,status})});
    const r2 = await fetch('orders.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'update_tracking',id,tracking_number:tracking,courier_name:courier})});
    showToast('Order updated successfully', 'success');
}

async function updatePaymentDetail(id) {
    const payment_status = document.getElementById('detailPayStatus').value;
    const payment_method = document.getElementById('detailPayMethod').value;
    const res = await fetch('orders.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'update_payment',id,payment_status,payment_method})});
    const r = await res.json();
    if(r.success) showToast('Payment status updated', 'success');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
