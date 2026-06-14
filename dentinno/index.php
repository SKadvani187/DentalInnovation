<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
$page_title = 'Dashboard';
// Full dashboard aggregates (header.php uses $stats if already set; other pages get the
// cheap sidebar badges instead). Must be set BEFORE the header include.
$stats = getDashboardStats();
include __DIR__ . '/includes/header.php';

// Order status counts for doughnut chart
$orderStatusData = [];
$statuses = db()->fetchAll("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status");
foreach ($statuses as $s) $orderStatusData[ucfirst($s['status'])] = (int)$s['cnt'];
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Dashboard</h1>
        <p>Welcome back, <?= htmlspecialchars($admin['name']) ?>! Here's what's happening today.</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="pages/orders.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-plus"></i> New Order
        </a>
        <a href="pages/reports.php" class="btn btn-gold btn-sm">
            <i class="fa-solid fa-chart-line"></i> View Reports
        </a>
    </div>
</div>

<!-- Today snapshot + Needs Attention (operational cockpit) -->
<div class="grid-2 fade-in" style="margin-bottom:24px;align-items:stretch;">
    <div class="card" style="padding:18px 20px;">
        <div style="font-size:.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;"><i class="fa-solid fa-bolt text-gold"></i> Today</div>
        <div style="display:flex;gap:28px;flex-wrap:wrap;">
            <div><div class="stat-label">Orders</div><div style="font-size:1.5rem;font-weight:700;"><?= (int)$stats['today_orders'] ?></div></div>
            <div><div class="stat-label">Revenue</div><div style="font-size:1.5rem;font-weight:700;color:var(--gold-primary);"><?= formatCurrency($stats['today_revenue']) ?></div></div>
            <div><div class="stat-label">New Customers</div><div style="font-size:1.5rem;font-weight:700;"><?= (int)$stats['today_customers'] ?></div></div>
        </div>
    </div>
    <div class="card" style="padding:18px 20px;">
        <div style="font-size:.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;"><i class="fa-solid fa-bell text-gold"></i> Needs Attention</div>
        <?php
        $pa = [
            ['Pending Orders',      (int)$stats['pa_orders'],    'pages/orders.php?status=pending',   'cart-shopping',        '#3498DB'],
            ['Refund Requests',     (int)$stats['pa_refunds'],   'pages/refunds.php?status=pending',  'rotate-left',          '#E74C3C'],
            ['Reviews to Moderate', (int)$stats['pa_reviews'],   'pages/reviews.php?approved=0',      'star',                 '#C9A84C'],
            ['Unread Messages',     (int)$stats['pa_messages'],  'pages/messages.php?status=unread',  'envelope',             '#9B59B6'],
            ['New Bulk Quotes',     (int)$stats['pa_quotes'],    'pages/bulk_quotes.php?status=unread','file-invoice-dollar', '#2ECC71'],
            ['Unanswered Q&A',      (int)$stats['pa_questions'], 'pages/questions.php?status=pending','circle-question',      '#F39C12'],
        ];
        $anyPending = false; foreach($pa as $x){ if($x[1] > 0){ $anyPending = true; break; } }
        ?>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach($pa as $item): if($item[1] <= 0) continue; ?>
            <a href="<?= APP_URL ?>/<?= $item[2] ?>" class="pa-chip" style="text-decoration:none;display:flex;align-items:center;gap:8px;background:var(--bg-elevated);border:1px solid var(--border-color);border-radius:99px;padding:7px 14px;">
                <i class="fa-solid fa-<?= $item[3] ?>" style="color:<?= $item[4] ?>;"></i>
                <span style="font-size:.82rem;color:var(--text-secondary);"><?= $item[0] ?></span>
                <span class="badge badge-danger" style="font-weight:700;"><?= $item[1] ?></span>
            </a>
            <?php endforeach; ?>
            <?php if(!$anyPending): ?><div class="text-muted" style="font-size:.85rem;"><i class="fa-solid fa-circle-check" style="color:var(--success);"></i> All caught up — nothing pending 🎉</div><?php endif; ?>
        </div>
    </div>
</div>

<?php if(!empty($stats['low_stock_list'])): ?>
<!-- Low-stock alert list (actionable) -->
<div class="card fade-in" style="margin-bottom:24px;border-left:3px solid var(--danger);">
    <div class="card-header">
        <span class="card-title"><i class="fa-solid fa-triangle-exclamation" style="color:var(--danger);margin-right:8px;"></i>Low Stock — Restock Soon (<?= count($stats['low_stock_list']) ?>)</span>
        <a href="pages/products.php?stock=restock" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Product</th><th>SKU</th><th>In Stock</th><th>Alert At</th></tr></thead>
            <tbody>
                <?php foreach($stats['low_stock_list'] as $ls): ?>
                <tr>
                    <td class="font-bold" style="font-size:0.84rem;"><?= htmlspecialchars($ls['name']) ?></td>
                    <td class="text-muted" style="font-size:0.78rem;"><?= htmlspecialchars($ls['sku'] ?? '') ?></td>
                    <td><span class="badge badge-<?= (int)$ls['stock'] === 0 ? 'danger' : 'warning' ?>"><?= (int)$ls['stock'] ?><?= (int)$ls['stock']===0?' — OUT':'' ?></span></td>
                    <td class="text-muted" style="font-size:0.8rem;">≤ <?= (int)$ls['min_stock_alert'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid fade-in">
    <div class="stat-card" style="cursor:pointer;" onclick="location.href='<?= APP_URL ?>/pages/reports.php'">
        <div class="stat-card-icon stat-icon-gold">
            <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
        <div class="stat-label">Total Revenue</div>
        <div class="stat-value" data-count="<?= $stats['total_revenue'] ?>" data-type="amount">₹0</div>
        <div class="stat-change neutral"><i class="fa-solid fa-infinity"></i> All time (paid)</div>
    </div>

    <div class="stat-card" style="cursor:pointer;" onclick="location.href='<?= APP_URL ?>/pages/orders.php'">
        <div class="stat-card-icon stat-icon-blue">
            <i class="fa-solid fa-cart-shopping"></i>
        </div>
        <div class="stat-label">Total Orders</div>
        <div class="stat-value" data-count="<?= $stats['total_orders'] ?>">0</div>
        <div class="stat-change neutral">
            <i class="fa-solid fa-calendar"></i> <?= (int)($stats['orders_this_month'] ?? 0) ?> this month
        </div>
    </div>

    <div class="stat-card" style="cursor:pointer;" onclick="location.href='<?= APP_URL ?>/pages/customers.php'">
        <div class="stat-card-icon stat-icon-green">
            <i class="fa-solid fa-user-group"></i>
        </div>
        <div class="stat-label">Total Customers</div>
        <div class="stat-value" data-count="<?= $stats['total_customers'] ?>">0</div>
        <div class="stat-change up"><i class="fa-solid fa-arrow-trend-up"></i> +<?= $stats['new_customers_month'] ?> this month</div>
    </div>

    <div class="stat-card" style="cursor:pointer;" onclick="location.href='<?= APP_URL ?>/pages/products.php'">
        <div class="stat-card-icon stat-icon-purple">
            <i class="fa-solid fa-boxes-stacked"></i>
        </div>
        <div class="stat-label">Total Products</div>
        <div class="stat-value" data-count="<?= $stats['total_products'] ?>">0</div>
        <div class="stat-change <?= $stats['low_stock'] > 0 ? 'down' : 'up' ?>">
            <i class="fa-solid fa-warehouse"></i>
            <?= $stats['low_stock'] > 0 ? $stats['low_stock'] . ' low stock' : 'Stock OK' ?>
        </div>
    </div>

    <div class="stat-card" style="cursor:pointer;" onclick="location.href='<?= APP_URL ?>/pages/reports.php'">
        <div class="stat-card-icon stat-icon-orange">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <div class="stat-label">Monthly Revenue</div>
        <div class="stat-value" data-count="<?= $stats['monthly_revenue'] ?>" data-type="amount">₹0</div>
        <?php
        // Real month-over-month delta (not a decorative arrow).
        $rlm = (float)($stats['revenue_last_month'] ?? 0);
        $rtm = (float)$stats['monthly_revenue'];
        $delta = $rlm > 0 ? (int)round((($rtm - $rlm) / $rlm) * 100) : ($rtm > 0 ? 100 : 0);
        $dir   = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'neutral');
        $arrow = $delta > 0 ? 'arrow-trend-up' : ($delta < 0 ? 'arrow-trend-down' : 'minus');
        ?>
        <div class="stat-change <?= $dir ?>"><i class="fa-solid fa-<?= $arrow ?>"></i> <?= ($delta>0?'+':'').$delta ?>% vs last month</div>
    </div>

</div>

<!-- New Modules Quick Stats -->
<div class="stats-grid fade-in" style="grid-template-columns:repeat(auto-fill,minmax(190px,1fr));margin-bottom:24px;">
    <!-- Events -->
    <a href="<?= APP_URL ?>/pages/events.php" style="text-decoration:none;">
    <div class="stat-card" style="cursor:pointer;">
        <div class="stat-card-icon" style="background:rgba(52,152,219,.12);color:#3498DB;width:42px;height:42px;border-radius:10px;display:grid;place-items:center;font-size:1.1rem;margin-bottom:12px;">
            <i class="fa-solid fa-calendar-star"></i>
        </div>
        <div class="stat-value" data-count="<?= $stats['upcoming_events'] ?? 0 ?>">0</div>
        <div class="stat-label">Upcoming Events</div>
        <div class="stat-change up" style="margin-top:5px;"><i class="fa-solid fa-users"></i> <?= number_format($stats['total_registrations'] ?? 0) ?> registrations</div>
    </div></a>

    <!-- Courses -->
    <a href="<?= APP_URL ?>/pages/courses.php" style="text-decoration:none;">
    <div class="stat-card" style="cursor:pointer;">
        <div class="stat-card-icon" style="background:rgba(155,89,182,.12);color:#9B59B6;width:42px;height:42px;border-radius:10px;display:grid;place-items:center;font-size:1.1rem;margin-bottom:12px;">
            <i class="fa-solid fa-graduation-cap"></i>
        </div>
        <div class="stat-value" data-count="<?= $stats['total_courses'] ?? 0 ?>">0</div>
        <div class="stat-label">Published Courses</div>
        <div class="stat-change up" style="margin-top:5px;"><i class="fa-solid fa-users"></i> <?= number_format($stats['total_enrollments'] ?? 0) ?> enrolled</div>
    </div></a>

    <!-- Reviews -->
    <a href="<?= APP_URL ?>/pages/reviews.php" style="text-decoration:none;">
    <div class="stat-card" style="cursor:pointer;">
        <div class="stat-card-icon" style="background:rgba(201,168,76,.12);color:var(--gold-primary);width:42px;height:42px;border-radius:10px;display:grid;place-items:center;font-size:1.1rem;margin-bottom:12px;">
            <i class="fa-regular fa-star"></i>
        </div>
        <div class="stat-value"><?= $stats['avg_rating'] ?: '—' ?></div>
        <div class="stat-label">Avg Rating</div>
        <div class="stat-change up" style="margin-top:5px;">
            <i class="fa-solid fa-star"></i> from <?= number_format((int)($stats['rating_count'] ?? 0)) ?> review<?= ((int)($stats['rating_count'] ?? 0)) !== 1 ? 's' : '' ?>
        </div>
    </div></a>

    <!-- Shipping -->
    <a href="<?= APP_URL ?>/pages/shipping.php" style="text-decoration:none;">
    <div class="stat-card" style="cursor:pointer;">
        <div class="stat-card-icon" style="background:rgba(46,204,113,.12);color:#2ECC71;width:42px;height:42px;border-radius:10px;display:grid;place-items:center;font-size:1.1rem;margin-bottom:12px;">
            <i class="fa-solid fa-truck"></i>
        </div>
        <div class="stat-value" data-count="<?= $stats['active_shipping_methods'] ?? 0 ?>">0</div>
        <div class="stat-label">Shipping Methods</div>
        <div class="stat-change up" style="margin-top:5px;"><i class="fa-solid fa-sliders"></i> <a href="<?= APP_URL ?>/pages/shipping.php#calculator" style="color:inherit;">Open Calculator</a></div>
    </div></a>
</div>

<!-- Charts Row -->
<div class="grid-2 fade-in" style="margin-bottom:24px;">
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-chart-line text-gold" style="margin-right:8px;"></i>Revenue — Last 6 Months</span>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fa-solid fa-chart-donut text-gold" style="margin-right:8px;"></i>Order Status Breakdown</span>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="orderChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders + Top Products -->
<div class="grid-2 fade-in">
    <!-- Recent Orders -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Orders</span>
            <a href="pages/orders.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($stats['recent_orders'] as $order): ?>
                    <tr>
                        <td><a href="pages/orders.php?view=<?= (int)$order['id'] ?>" class="text-gold font-bold"><?= htmlspecialchars($order['order_number']) ?></a></td>
                        <td>
                            <div><?= htmlspecialchars($order['customer_name']) ?></div>
                            <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($order['phone'] ?? '') ?></div>
                        </td>
                        <td class="font-bold"><?= formatCurrency($order['total']) ?></td>
                        <td><span class="badge badge-<?= statusBadge($order['status']) ?>"><?= htmlspecialchars($order['status']) ?></span></td>
                        <td class="text-muted" style="font-size:0.76rem;white-space:nowrap;"><?= !empty($order['created_at']) ? formatDate($order['created_at'], 'd M, h:i A') : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($stats['recent_orders'])): ?>
                    <tr><td colspan="5" class="text-center text-muted">No orders yet</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Products -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Top Selling Products</span>
            <a href="pages/products.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($stats['top_products'] as $p): ?>
                    <tr>
                        <td>
                            <div class="font-bold" style="font-size:0.84rem;"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="text-muted" style="font-size:0.73rem;"><?= htmlspecialchars($p['category'] ?? '') ?></div>
                        </td>
                        <td><?= formatCurrency($p['price']) ?></td>
                        <td class="<?= $p['stock'] <= 5 ? 'stock-low' : 'stock-ok' ?>"><?= $p['stock'] ?></td>
                        <td><?= $p['total_sales'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Customers -->
<div class="card fade-in" style="margin-top:24px;">
    <div class="card-header">
        <span class="card-title">Recent Customers</span>
        <a href="pages/customers.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Customer</th><th>Clinic / Type</th><th>Joined</th></tr></thead>
            <tbody>
                <?php foreach($stats['recent_customers'] as $rc): ?>
                <tr>
                    <td class="font-bold" style="font-size:0.84rem;"><?= htmlspecialchars($rc['name']) ?></td>
                    <td class="text-muted" style="font-size:0.8rem;"><?= htmlspecialchars($rc['clinic_name'] ?: ucfirst($rc['customer_type'] ?? '')) ?></td>
                    <td class="text-muted" style="font-size:0.78rem;"><?= formatDate($rc['created_at'],'d M Y') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($stats['recent_customers'])): ?><tr><td colspan="3" class="text-center text-muted">No customers yet</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Replace an empty chart canvas with a friendly placeholder (no blank boxes on a fresh store).
function chartEmpty(id, msg){
  const c=document.getElementById(id);
  if(c&&c.parentElement) c.parentElement.innerHTML='<div style="text-align:center;padding:48px 0;color:var(--text-muted);"><i class="fa-solid fa-chart-simple" style="font-size:2rem;opacity:.25;display:block;margin-bottom:10px;"></i>'+msg+'</div>';
}
// Revenue Chart
const revenueData = <?= json_encode($stats['revenue_chart']) ?>;
if(!revenueData || !revenueData.length) chartEmpty('revenueChart','No paid revenue yet'); else initRevenueChart(revenueData);

// Order Status Doughnut
const orderData = <?= json_encode($orderStatusData) ?>;
if(!orderData || !Object.keys(orderData).length) chartEmpty('orderChart','No orders yet'); else initOrderChart(orderData);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
