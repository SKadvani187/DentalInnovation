<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Analytics & Reports';

// Date range
$from = sanitize($_GET['from'] ?? date('Y-m-01'));
$to   = sanitize($_GET['to']   ?? date('Y-m-d'));

// Summary stats for range
$range_revenue  = db()->fetchOne("SELECT COALESCE(SUM(total),0) as v FROM orders WHERE payment_status='paid' AND DATE(created_at) BETWEEN ? AND ?", [$from,$to])['v'];
$range_orders   = db()->fetchOne("SELECT COUNT(*) as v FROM orders WHERE DATE(created_at) BETWEEN ? AND ?", [$from,$to])['v'];
$range_customers= db()->fetchOne("SELECT COUNT(*) as v FROM customers WHERE DATE(created_at) BETWEEN ? AND ?", [$from,$to])['v'];
$avg_order_val  = $range_orders > 0 ? ($range_revenue / $range_orders) : 0;

// Monthly revenue (12 months)
$monthly = db()->fetchAll("SELECT DATE_FORMAT(created_at,'%b %Y') as month, DATE_FORMAT(created_at,'%Y-%m') as ym, COALESCE(SUM(total),0) as revenue, COUNT(*) as orders FROM orders WHERE payment_status='paid' AND created_at >= DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY ym ORDER BY ym ASC");

// Top products by sales value
$top_products = db()->fetchAll("SELECT p.name, p.sku, p.price, p.total_sales, c.name as category FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.is_active=1 ORDER BY p.total_sales DESC LIMIT 10");

// Top customers
$top_customers = db()->fetchAll("SELECT c.name, c.clinic_name, c.customer_type, c.total_orders, c.total_spent FROM customers c ORDER BY total_spent DESC LIMIT 10");

// Orders by status
$by_status = db()->fetchAll("SELECT status, COUNT(*) as cnt, COALESCE(SUM(total),0) as total FROM orders GROUP BY status");

// Revenue by category
$by_category = db()->fetchAll("SELECT cat.name, COUNT(DISTINCT o.id) as orders, COALESCE(SUM(oi.total),0) as revenue FROM order_items oi JOIN products p ON oi.product_id=p.id JOIN categories cat ON p.category_id=cat.id JOIN orders o ON oi.order_id=o.id WHERE o.payment_status='paid' GROUP BY cat.id ORDER BY revenue DESC");

// Payment methods
$pay_methods = db()->fetchAll("SELECT payment_method, COUNT(*) as cnt, COALESCE(SUM(total),0) as total FROM orders WHERE payment_status='paid' AND payment_method IS NOT NULL GROUP BY payment_method ORDER BY total DESC");

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Analytics & Reports</h1>
        <p>Business insights and performance metrics</p>
    </div>
    <!-- Date Filter -->
    <div style="display:flex;gap:10px;align-items:center;">
        <input type="date" class="form-control" id="fromDate" value="<?= $from ?>" style="max-width:140px;">
        <span class="text-muted">to</span>
        <input type="date" class="form-control" id="toDate" value="<?= $to ?>" style="max-width:140px;">
        <button class="btn btn-gold btn-sm" onclick="applyDateFilter()"><i class="fa-solid fa-chart-line"></i> Apply</button>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid fade-in" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card">
        <div class="stat-card-icon stat-icon-gold"><i class="fa-solid fa-indian-rupee-sign"></i></div>
        <div class="stat-label">Revenue (Period)</div>
        <div class="stat-value" data-count="<?= $range_revenue ?>" data-type="amount">₹0</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-icon-blue"><i class="fa-solid fa-cart-shopping"></i></div>
        <div class="stat-label">Orders (Period)</div>
        <div class="stat-value" data-count="<?= $range_orders ?>">0</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-icon-green"><i class="fa-solid fa-user-plus"></i></div>
        <div class="stat-label">New Customers</div>
        <div class="stat-value" data-count="<?= $range_customers ?>">0</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-icon-purple"><i class="fa-solid fa-calculator"></i></div>
        <div class="stat-label">Avg Order Value</div>
        <div class="stat-value text-gold" style="font-size:1.4rem;"><?= formatCurrency($avg_order_val) ?></div>
    </div>
</div>

<!-- Monthly Revenue Chart -->
<div class="card fade-in" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-chart-line text-gold" style="margin-right:8px;"></i>Monthly Revenue — Last 12 Months</span>
    </div>
    <div class="card-body">
        <div class="chart-container" style="height:280px;">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>
</div>

<!-- Row 2 -->
<div class="grid-2 fade-in" style="margin-bottom:24px;">
    <!-- Order Status Breakdown -->
    <div class="card">
        <div class="card-header"><span class="card-title">Orders by Status</span></div>
        <div class="card-body">
            <?php foreach($by_status as $s): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="badge badge-<?= statusBadge($s['status']) ?>"><?= ucfirst($s['status']) ?></span>
                    <span class="text-muted" style="font-size:0.8rem;"><?= $s['cnt'] ?> orders</span>
                </div>
                <span class="font-bold"><?= formatCurrency($s['total']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="card">
        <div class="card-header"><span class="card-title">Payment Methods</span></div>
        <div class="card-body">
            <?php if(empty($pay_methods)): ?>
            <div class="empty-state"><i class="fa-solid fa-credit-card"></i><p>No paid orders yet</p></div>
            <?php else: ?>
            <?php $max_total = max(array_column($pay_methods,'total')); ?>
            <?php foreach($pay_methods as $pm): ?>
            <div style="margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-size:0.84rem;font-weight:600;"><?= $pm['payment_method'] ?></span>
                    <span class="text-gold font-bold"><?= formatCurrency($pm['total']) ?></span>
                </div>
                <div style="background:var(--bg-elevated);border-radius:99px;height:5px;">
                    <div style="height:100%;width:<?= $max_total > 0 ? round(($pm['total']/$max_total)*100) : 0 ?>%;background:var(--gold-gradient);border-radius:99px;"></div>
                </div>
                <div class="text-muted" style="font-size:0.72rem;margin-top:2px;"><?= $pm['cnt'] ?> transactions</div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Top Products & Customers -->
<div class="grid-2 fade-in">
    <!-- Top Products -->
    <div class="card">
        <div class="card-header"><span class="card-title">Top 10 Products</span></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>#</th><th>Product</th><th>Category</th><th>Price</th><th>Sales</th></tr></thead>
                <tbody>
                    <?php foreach($top_products as $i=>$p): ?>
                    <tr>
                        <td class="text-muted"><?= $i+1 ?></td>
                        <td>
                            <div class="font-bold" style="font-size:0.82rem;"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="text-muted" style="font-size:0.7rem;"><?= $p['sku'] ?></div>
                        </td>
                        <td><?= $p['category'] ?? '—' ?></td>
                        <td><?= formatCurrency($p['price']) ?></td>
                        <td class="text-gold font-bold"><?= $p['total_sales'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($top_products)): ?><tr><td colspan="5" class="text-center text-muted">No data</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Customers -->
    <div class="card">
        <div class="card-header"><span class="card-title">Top 10 Customers</span></div>
        <div class="table-responsive">
            <table>
                <thead><tr><th>#</th><th>Customer</th><th>Orders</th><th>Total Spent</th></tr></thead>
                <tbody>
                    <?php foreach($top_customers as $i=>$c): ?>
                    <tr>
                        <td class="text-muted"><?= $i+1 ?></td>
                        <td>
                            <div class="font-bold" style="font-size:0.82rem;"><?= htmlspecialchars($c['name']) ?></div>
                            <div class="text-muted" style="font-size:0.7rem;"><?= $c['clinic_name'] ?: ucfirst($c['customer_type']) ?></div>
                        </td>
                        <td><?= $c['total_orders'] ?></td>
                        <td class="text-gold font-bold"><?= formatCurrency($c['total_spent']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($top_customers)): ?><tr><td colspan="4" class="text-center text-muted">No data</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function applyDateFilter() {
    const from = document.getElementById('fromDate').value;
    const to   = document.getElementById('toDate').value;
    window.location.href = `reports.php?from=${from}&to=${to}`;
}

// Monthly chart
const monthlyData = <?= json_encode($monthly) ?>;
const ctx = document.getElementById('monthlyChart');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: monthlyData.map(d => d.month),
            datasets: [
                {
                    label: 'Revenue (₹)',
                    data: monthlyData.map(d => d.revenue),
                    backgroundColor: 'rgba(201,168,76,0.7)',
                    borderColor: '#C9A84C',
                    borderWidth: 1,
                    borderRadius: 6,
                    yAxisID: 'y',
                },
                {
                    label: 'Orders',
                    data: monthlyData.map(d => d.orders),
                    type: 'line',
                    borderColor: '#3498DB',
                    backgroundColor: 'rgba(52,152,219,0.1)',
                    borderWidth: 2,
                    pointBackgroundColor: '#3498DB',
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' }, tooltip: {
                backgroundColor: '#161820', borderColor: 'rgba(201,168,76,0.3)', borderWidth: 1
            }},
            scales: {
                x: { grid: { color: 'rgba(255,255,255,0.04)' } },
                y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { callback: v => '₹'+(v>=1000?(v/1000).toFixed(0)+'K':v) }, position: 'left' },
                y1: { grid: { display:false }, position: 'right', ticks: { callback: v => v+' orders' } }
            }
        }
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
