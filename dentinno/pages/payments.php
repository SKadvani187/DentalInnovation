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
$from    = sanitize($_GET['from'] ?? '');
$to      = sanitize($_GET['to'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));
$per_page = 15; $offset = ($page-1)*$per_page;

$where = ["1=1"]; $params = [];
if ($search) { $where[] = "(o.order_number LIKE ? OR c.name LIKE ?)"; $params = array_merge($params,["%$search%","%$search%"]); }
if ($status) { $where[] = "o.payment_status = ?"; $params[] = $status; }
if ($method) { $where[] = "o.payment_method = ?"; $params[] = $method; }
// Date range on the order date (whole 'to' day included).
if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = "o.created_at >= ?"; $params[] = $from.' 00:00:00'; }
if ($to   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = "o.created_at <= ?"; $params[] = $to.' 23:59:59'; }
$whereStr = implode(' AND ', $where);

// --- CSV export (finance) — full filtered set, before any HTML output ---
if (isset($_GET['export'])) {
    $rows = db()->fetchAll("SELECT o.order_number, c.name AS customer_name, o.total, o.payment_method, o.payment_status, o.created_at
                              FROM orders o JOIN customers c ON o.customer_id=c.id
                             WHERE $whereStr ORDER BY o.created_at DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order #','Customer','Amount','Method','Status','Date']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['order_number'], $r['customer_name'], $r['total'], $r['payment_method'], $r['payment_status'], $r['created_at']]);
    }
    fclose($out);
    exit;
}

$total   = db()->fetchOne("SELECT COUNT(*) as cnt FROM orders o JOIN customers c ON o.customer_id=c.id WHERE $whereStr", $params)['cnt'];
$pages   = ceil($total/$per_page);
$payments = db()->fetchAll("SELECT o.id,o.order_number,o.total,o.payment_status,o.payment_method,o.created_at,c.name as customer_name FROM orders o JOIN customers c ON o.customer_id=c.id WHERE $whereStr ORDER BY o.created_at DESC LIMIT $per_page OFFSET $offset", $params);

// Method dropdown options derived from real data (a hardcoded list missed 'cod' and listed
// methods that never occur). Keeps the filter in sync with what's actually in the orders.
$methodOpts = array_column(db()->fetchAll("SELECT DISTINCT payment_method FROM orders WHERE payment_method IS NOT NULL AND payment_method <> '' ORDER BY payment_method"), 'payment_method');

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
        <input type="text" class="search-input" id="searchInput" placeholder="Search order, customer..." value="<?= htmlspecialchars($search) ?>" onkeydown="if(event.key==='Enter')applyFilters()">
    </div>
    <select class="form-control" id="statusFilter" style="max-width:150px;">
        <option value="">All Status</option>
        <?php foreach(['paid','unpaid','partial','refunded'] as $s): ?>
        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-control" id="methodFilter" style="max-width:160px;">
        <option value="">All Methods</option>
        <?php foreach($methodOpts as $m): ?>
        <option value="<?= htmlspecialchars($m) ?>" <?= $method===$m?'selected':'' ?>><?= htmlspecialchars(strtoupper($m)==='COD' ? 'COD' : ucwords($m)) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" class="form-control" id="fromDate" value="<?= htmlspecialchars($from) ?>" style="max-width:150px;" title="From (order date)">
    <input type="date" class="form-control" id="toDate" value="<?= htmlspecialchars($to) ?>" style="max-width:150px;" title="To (order date)">
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="payments.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
    <button class="btn btn-ghost btn-sm" onclick="exportCsv()"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
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
                    <td><?= $p['payment_method'] ? htmlspecialchars($p['payment_method']) : '<span class="text-muted">—</span>' ?></td>
                    <td><span class="badge badge-<?= statusBadge($p['payment_status']) ?>"><?= htmlspecialchars($p['payment_status'] ?? '') ?></span></td>
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
    <?php if($pages > 1): ?>
    <div style="padding:16px 20px;border-top:1px solid var(--border-color);">
      <div class="pagination">
        <?php
        // Compact pagination: first, last, and a window around the current page (… for gaps).
        $range = 2; $shown = [];
        for ($i = 1; $i <= $pages; $i++) {
            if ($i == 1 || $i == $pages || ($i >= $page - $range && $i <= $page + $range)) $shown[] = $i;
        }
        if ($page > 1): ?><div class="page-item" onclick="goPage(<?= $page-1 ?>)">‹</div><?php endif;
        $prev = 0;
        foreach ($shown as $i):
            if ($prev && $i - $prev > 1): ?><div class="page-item" style="pointer-events:none;opacity:.5;">…</div><?php endif; ?>
            <div class="page-item <?= $i==$page?'active':'' ?>" onclick="goPage(<?= $i ?>)"><?= $i ?></div>
            <?php $prev = $i;
        endforeach;
        if ($page < $pages): ?><div class="page-item" onclick="goPage(<?= $page+1 ?>)">›</div><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
</div>

<script>
function buildPaymentQuery(extra) {
    const p = new URLSearchParams();
    const s  = document.getElementById('searchInput').value; if(s) p.set('search', s);
    const st = document.getElementById('statusFilter').value; if(st) p.set('status', st);
    const m  = document.getElementById('methodFilter').value; if(m) p.set('method', m);
    const f  = document.getElementById('fromDate').value; if(f) p.set('from', f);
    const t  = document.getElementById('toDate').value; if(t) p.set('to', t);
    if(extra) Object.entries(extra).forEach(([k,v])=>p.set(k,v));
    return p.toString();
}
function applyFilters(){ window.location.href = 'payments.php?' + buildPaymentQuery(); }
function exportCsv(){ window.location.href = 'payments.php?' + buildPaymentQuery({export:'csv'}); }
function goPage(p){const q=new URLSearchParams(window.location.search);q.set('page',p);window.location.href='payments.php?'+q.toString();}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
