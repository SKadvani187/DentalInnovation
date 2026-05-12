<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
$page_title = 'Dashboard';
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

<!-- Stats Grid -->
<div class="stats-grid fade-in">
    <div class="stat-card">
        <div class="stat-card-icon stat-icon-gold">
            <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
        <div class="stat-label">Total Revenue</div>
        <div class="stat-value" data-count="<?= $stats['total_revenue'] ?>" data-type="amount">₹0</div>
        <div class="stat-change up"><i class="fa-solid fa-arrow-trend-up"></i> All time</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-icon stat-icon-blue">
            <i class="fa-solid fa-cart-shopping"></i>
        </div>
        <div class="stat-label">Total Orders</div>
        <div class="stat-value" data-count="<?= $stats['total_orders'] ?>">0</div>
        <div class="stat-change <?= $stats['pending_orders'] > 0 ? 'down' : 'up' ?>">
            <i class="fa-solid fa-clock"></i> <?= $stats['pending_orders'] ?> pending
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-icon stat-icon-green">
            <i class="fa-solid fa-user-group"></i>
        </div>
        <div class="stat-label">Total Customers</div>
        <div class="stat-value" data-count="<?= $stats['total_customers'] ?>">0</div>
        <div class="stat-change up"><i class="fa-solid fa-arrow-trend-up"></i> +<?= $stats['new_customers_month'] ?> this month</div>
    </div>

    <div class="stat-card">
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

    <div class="stat-card">
        <div class="stat-card-icon stat-icon-orange">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <div class="stat-label">Monthly Revenue</div>
        <div class="stat-value" data-count="<?= $stats['monthly_revenue'] ?>" data-type="amount">₹0</div>
        <div class="stat-change neutral"><i class="fa-solid fa-calendar"></i> This month</div>
    </div>

    <div class="stat-card">
        <div class="stat-card-icon stat-icon-red">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div class="stat-label">Low Stock Items</div>
        <div class="stat-value <?= $stats['low_stock'] > 0 ? 'text-danger' : '' ?>" data-count="<?= $stats['low_stock'] ?>">0</div>
        <div class="stat-change <?= $stats['low_stock'] > 0 ? 'down' : 'up' ?>">
            <i class="fa-solid fa-boxes-stacked"></i>
            <?= $stats['low_stock'] > 0 ? 'Needs restocking' : 'All good' ?>
        </div>
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
        <div class="stat-value <?= ($stats['pending_reviews'] ?? 0) > 0 ? '' : '' ?>"><?= $stats['avg_rating'] ?? '—' ?></div>
        <div class="stat-label">Avg Rating</div>
        <div class="stat-change <?= ($stats['pending_reviews'] ?? 0) > 0 ? 'down' : 'up' ?>" style="margin-top:5px;">
            <i class="fa-solid fa-clock"></i> <?= $stats['pending_reviews'] ?? 0 ?> pending review<?= ($stats['pending_reviews'] ?? 0) !== 1 ? 's' : '' ?>
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
        <div class="stat-change up" style="margin-top:5px;"><i class="fa-solid fa-sliders"></i> <a href="<?= APP_URL ?>/pages/shipping_calculator.php" style="color:inherit;">Open Calculator</a></div>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($stats['recent_orders'] as $order): ?>
                    <tr>
                        <td><a href="pages/orders.php?view=<?= $order['id'] ?>" class="text-gold font-bold"><?= $order['order_number'] ?></a></td>
                        <td>
                            <div><?= htmlspecialchars($order['customer_name']) ?></div>
                            <div class="text-muted" style="font-size:0.75rem;"><?= $order['phone'] ?></div>
                        </td>
                        <td class="font-bold"><?= formatCurrency($order['total']) ?></td>
                        <td><span class="badge badge-<?= statusBadge($order['status']) ?>"><?= $order['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($stats['recent_orders'])): ?>
                    <tr><td colspan="4" class="text-center text-muted">No orders yet</td></tr>
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
                            <div class="text-muted" style="font-size:0.73rem;"><?= $p['category'] ?></div>
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

<script>
// Revenue Chart
const revenueData = <?= json_encode($stats['revenue_chart']) ?>;
initRevenueChart(revenueData);

// Order Status Doughnut
const orderData = <?= json_encode($orderStatusData) ?>;
initOrderChart(orderData);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
