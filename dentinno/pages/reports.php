<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Analytics & Reports';

// Financial reports are business-sensitive: gate to roles that can VIEW the reports page.
requireView('reports');

// Date range
$from = sanitize($_GET['from'] ?? date('Y-m-01'));
$to   = sanitize($_GET['to']   ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

// Summary stats for range.
// Revenue = NET SALES (subtotal, excl. tax & shipping) on PAID orders — same basis as the dashboard.
// (by_status order value, pay_methods received, and customer total_spent below are CASH metrics
//  and intentionally remain on `total`.)
$range_revenue  = db()->fetchOne("SELECT COALESCE(SUM(subtotal),0) as v FROM orders WHERE payment_status='paid' AND DATE(created_at) BETWEEN ? AND ?", [$from,$to])['v'];
$range_orders   = db()->fetchOne("SELECT COUNT(*) as v FROM orders WHERE DATE(created_at) BETWEEN ? AND ?", [$from,$to])['v'];
$range_paid_orders = db()->fetchOne("SELECT COUNT(*) as v FROM orders WHERE payment_status='paid' AND DATE(created_at) BETWEEN ? AND ?", [$from,$to])['v'];
$range_customers= db()->fetchOne("SELECT COUNT(*) as v FROM customers WHERE DATE(created_at) BETWEEN ? AND ?", [$from,$to])['v'];
// AOV = paid revenue / paid orders (both on the same 'paid' basis, otherwise the value is understated).
$avg_order_val  = $range_paid_orders > 0 ? ($range_revenue / $range_paid_orders) : 0;

// --- Returns / refunds (range, by completion date) ---
$returns = db()->fetchOne("SELECT COUNT(*) cnt, COALESCE(SUM(refund_amount),0) amt FROM refund_requests WHERE status='completed' AND DATE(completed_at) BETWEEN ? AND ?", [$from,$to]);
$returns_rate = $range_revenue > 0 ? round(((float)$returns['amt'] / (float)$range_revenue) * 100, 1) : 0;

// --- Tax collected (range, paid) ---
$tax_collected = (float)(db()->fetchOne("SELECT COALESCE(SUM(tax),0) v FROM orders WHERE payment_status='paid' AND DATE(created_at) BETWEEN ? AND ?", [$from,$to])['v'] ?? 0);

// --- Approximate gross margin (range, paid, only items whose product has a cost_price set) ---
$margin = db()->fetchOne(
    "SELECT COALESCE(SUM(oi.total),0) rev, COALESCE(SUM(oi.quantity * p.cost_price),0) cogs, COUNT(DISTINCT p.id) prods
       FROM order_items oi JOIN orders o ON o.id=oi.order_id JOIN products p ON p.id=oi.product_id
      WHERE o.payment_status='paid' AND p.cost_price IS NOT NULL AND DATE(o.created_at) BETWEEN ? AND ?",
    [$from,$to]);
$margin_rev    = (float)($margin['rev'] ?? 0);
$gross_profit  = $margin_rev - (float)($margin['cogs'] ?? 0);
$margin_pct    = $margin_rev > 0 ? round(($gross_profit / $margin_rev) * 100, 1) : 0;
$margin_prods  = (int)($margin['prods'] ?? 0);

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

// --- CSV export (finance) — one workbook-style file with each section, before any HTML output ---
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reports-' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Report Period', $from . ' to ' . $to]);
    fputcsv($out, ['Revenue (period)', $range_revenue]);
    fputcsv($out, ['Orders (period)', $range_orders]);
    fputcsv($out, ['New Customers (period)', $range_customers]);
    fputcsv($out, ['Avg Order Value', round($avg_order_val, 2)]);
    fputcsv($out, ['Refunds (count)', (int)$returns['cnt']]);
    fputcsv($out, ['Refunded amount', round((float)$returns['amt'], 2)]);
    fputcsv($out, ['Returns rate (%)', $returns_rate]);
    fputcsv($out, ['Tax collected', round($tax_collected, 2)]);
    fputcsv($out, ['Gross profit (approx)', round($gross_profit, 2)]);
    fputcsv($out, ['Margin % (approx)', $margin_pct]);
    fputcsv($out, []);
    fputcsv($out, ['Revenue by Category (all-time, paid)']);
    fputcsv($out, ['Category','Orders','Revenue']);
    foreach ($by_category as $r) fputcsv($out, [$r['name'],$r['orders'],$r['revenue']]);
    fputcsv($out, []);
    fputcsv($out, ['Top Products']);
    fputcsv($out, ['Product','SKU','Category','Price','Total Sales']);
    foreach ($top_products as $r) fputcsv($out, [$r['name'],$r['sku'],$r['category'],$r['price'],$r['total_sales']]);
    fputcsv($out, []);
    fputcsv($out, ['Top Customers']);
    fputcsv($out, ['Customer','Clinic / Type','Orders','Total Spent']);
    foreach ($top_customers as $r) fputcsv($out, [$r['name'],($r['clinic_name'] ?: $r['customer_type']),$r['total_orders'],$r['total_spent']]);
    fputcsv($out, []);
    fputcsv($out, ['Payment Methods (paid)']);
    fputcsv($out, ['Method','Transactions','Total']);
    foreach ($pay_methods as $r) fputcsv($out, [$r['payment_method'],$r['cnt'],$r['total']]);
    fclose($out);
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Analytics & Reports</h1>
        <p>Business insights and performance metrics</p>
    </div>
    <!-- Date Filter -->
    <div style="display:flex;gap:10px;align-items:center;">
        <input type="date" class="form-control" id="fromDate" value="<?= htmlspecialchars($from) ?>" style="max-width:140px;">
        <span class="text-muted">to</span>
        <input type="date" class="form-control" id="toDate" value="<?= htmlspecialchars($to) ?>" style="max-width:140px;">
        <button class="btn btn-gold btn-sm" onclick="applyDateFilter()"><i class="fa-solid fa-chart-line"></i> Apply</button>
        <button class="btn btn-ghost btn-sm" onclick="exportCsv()"><i class="fa-solid fa-file-csv"></i> Export</button>
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

<!-- Profit & Returns (period) -->
<div class="stats-grid fade-in" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-card-icon stat-icon-green"><i class="fa-solid fa-coins"></i></div>
        <div class="stat-label">Gross Profit <small class="text-muted">(approx)</small></div>
        <div class="stat-value" style="font-size:1.4rem;color:<?= $gross_profit>=0?'var(--success)':'var(--danger)' ?>;"><?= formatCurrency($gross_profit) ?></div>
        <div class="stat-change neutral" style="font-size:.7rem;"><?= $margin_pct ?>% margin<?= $margin_prods ? ' · '.$margin_prods.' costed' : '' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-icon-red"><i class="fa-solid fa-rotate-left"></i></div>
        <div class="stat-label">Refunds</div>
        <div class="stat-value" style="font-size:1.4rem;color:var(--danger);"><?= formatCurrency($returns['amt']) ?></div>
        <div class="stat-change neutral" style="font-size:.7rem;"><?= (int)$returns['cnt'] ?> refund(s) · <?= $returns_rate ?>% of revenue</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-icon-blue"><i class="fa-solid fa-receipt"></i></div>
        <div class="stat-label">Tax Collected</div>
        <div class="stat-value" style="font-size:1.4rem;"><?= formatCurrency($tax_collected) ?></div>
        <div class="stat-change neutral" style="font-size:.7rem;">on paid orders in range</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon stat-icon-gold"><i class="fa-solid fa-chart-pie"></i></div>
        <div class="stat-label">Net (rev − refunds)</div>
        <div class="stat-value text-gold" style="font-size:1.4rem;"><?= formatCurrency((float)$range_revenue - (float)$returns['amt']) ?></div>
        <div class="stat-change neutral" style="font-size:.7rem;">period net revenue</div>
    </div>
</div>
<div class="text-muted fade-in" style="font-size:.72rem;margin:-12px 0 20px;"><i class="fa-solid fa-circle-info"></i> Gross profit is approximate — it counts only items whose product has a <b>Cost Price</b> set, using the current cost. Tax shows ₹0 until tax is configured on orders.</div>

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
                    <span class="badge <?= statusBadge($s['status']) ?>"><?= ucfirst(htmlspecialchars($s['status'])) ?></span>
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
                    <span style="font-size:0.84rem;font-weight:600;"><?= htmlspecialchars($pm['payment_method'] ?? '') ?></span>
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
                            <div class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($p['sku'] ?? '') ?></div>
                        </td>
                        <td><?= htmlspecialchars($p['category'] ?? '—') ?></td>
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
                            <div class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($c['clinic_name'] ?: ucfirst($c['customer_type'] ?? '')) ?></div>
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
function exportCsv() {
    const from = document.getElementById('fromDate').value;
    const to   = document.getElementById('toDate').value;
    window.location.href = `reports.php?from=${from}&to=${to}&export=csv`;
}

// Monthly chart
const monthlyData = <?= json_encode($monthly) ?>;
const ctx = document.getElementById('monthlyChart');
if (ctx && (!monthlyData || !monthlyData.length)) {
    ctx.parentElement.innerHTML = '<div style="text-align:center;padding:60px 0;color:var(--text-muted);"><i class="fa-solid fa-chart-line" style="font-size:2rem;opacity:.25;display:block;margin-bottom:10px;"></i>No paid revenue in this period</div>';
} else if (ctx) {
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
