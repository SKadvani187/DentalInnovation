<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Payments';

// Stats
$total_paid    = db()->fetchOne("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE payment_status='paid'")['v'];
$total_unpaid  = db()->fetchOne("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE payment_status='unpaid'")['v'];
$total_partial = db()->fetchOne("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE payment_status='partial'")['v'];
$total_refund  = db()->fetchOne("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE payment_status='refunded'")['v'];

// Filters
$search  = sanitize($_GET['search'] ?? '');
$status  = sanitize($_GET['status'] ?? '');
$method  = sanitize($_GET['method'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));
$per_page = 15; $offset = ($page-1)*$per_page;

$where = ["1=1"]; $params = [];
if ($search) { $where[] = "(o.order_number LIKE ? OR c.name LIKE ?)"; $params = array_merge($params,["%$search%","%$search%"]); }
if ($status) { $where[] = "o.payment_status = ?"; $params[] = $status; }
if ($method) { $where[] = "o.payment_method = ?"; $params[] = $method; }
$whereStr = implode(' AND ', $where);

$total   = db()->fetchOne("SELECT COUNT(*) as cnt FROM orders o JOIN customers c ON o.customer_id=c.id WHERE $whereStr", $params)['cnt'];
$pages   = ceil($total/$per_page);
$payments = db()->fetchAll("SELECT o.id,o.order_number,o.total,o.payment_status,o.payment_method,o.created_at,c.name as customer_name FROM orders o JOIN customers c ON o.customer_id=c.id WHERE $whereStr ORDER BY o.created_at DESC LIMIT $per_page OFFSET $offset", $params);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Payments</h1>
        <p>Track all payment transactions and statuses</p>
    </div>
</div>

<!-- Payment Stats -->
<div class="stats-grid fade-in" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card">
        <div class="stat-card-icon stat-icon-green"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-label">Total Received</div>
        <div class="stat-value text-success" style="font-size:1.5rem;"><?= formatCurrency($total_paid) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-icon-red"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-label">Pending Payment</div>
        <div class="stat-value" style="font-size:1.5rem;color:var(--danger);"><?= formatCurrency($total_unpaid) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-icon-orange"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-label">Partial Payment</div>
        <div class="stat-value" style="font-size:1.5rem;color:var(--warning);"><?= formatCurrency($total_partial) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-icon-purple"><i class="fa-solid fa-rotate-left"></i></div>
        <div class="stat-label">Refunded</div>
        <div class="stat-value" style="font-size:1.5rem;color:var(--purple);"><?= formatCurrency($total_refund) ?></div>
    </div>
</div>

<!-- Filters -->
<div class="filter-bar fade-in">
    <div class="search-wrapper">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search order, customer..." value="<?= $search ?>">
    </div>
    <select class="form-control" id="statusFilter" style="max-width:150px;">
        <option value="">All Status</option>
        <?php foreach(['paid','unpaid','partial','refunded'] as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-control" id="methodFilter" style="max-width:160px;">
        <option value="">All Methods</option>
        <?php foreach(['UPI','Card','Net Banking','Bank Transfer','Cash','Cheque'] as $m): ?>
        <option value="<?= $m ?>" <?= $method===$m?'selected':'' ?>><?= $m ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="payments.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<!-- Table -->
<div class="card fade-in">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($payments as $p): ?>
                <tr>
                    <td><a href="orders.php?view=<?= $p['id'] ?>" class="text-gold font-bold"><?= $p['order_number'] ?></a></td>
                    <td><?= htmlspecialchars($p['customer_name']) ?></td>
                    <td class="font-bold"><?= formatCurrency($p['total']) ?></td>
                    <td><?= $p['payment_method'] ?: '<span class="text-muted">—</span>' ?></td>
                    <td><span class="badge badge-<?= statusBadge($p['payment_status']) ?>"><?= $p['payment_status'] ?></span></td>
                    <td><?= formatDate($p['created_at']) ?></td>
                    <td><a href="orders.php?view=<?= $p['id'] ?>" class="btn btn-ghost btn-sm btn-icon"><i class="fa-solid fa-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($payments)): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-indian-rupee-sign"></i><p>No payments found</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function applyFilters() {
    const s  = document.getElementById('searchInput').value;
    const st = document.getElementById('statusFilter').value;
    const m  = document.getElementById('methodFilter').value;
    window.location.href = `payments.php?search=${s}&status=${st}&method=${m}`;
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
