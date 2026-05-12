<?php
requireLogin();
$admin = currentAdmin();
$stats = getDashboardStats();
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' — ' : '' ?>DentInno CRM</title>
    <link rel="icon" href="<?= APP_URL ?>/assets/images/logo.png">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <img src="<?= APP_URL ?>/assets/images/logo.png" alt="DentInno" class="logo-img">
        <div class="logo-text">
            <span class="logo-name">DentInno</span>
            <span class="logo-sub">CRM Dashboard</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">
            <span class="nav-section-label">MAIN</span>
            <a href="<?= APP_URL ?>/index.php" class="nav-item <?= $current_page === 'index' ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-label">CATALOG</span>
            <a href="<?= APP_URL ?>/pages/products.php" class="nav-item <?= $current_page === 'products' ? 'active' : '' ?>">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span>Products</span>
                <?php if($stats['low_stock'] > 0): ?>
                <span class="nav-badge warn"><?= $stats['low_stock'] ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= APP_URL ?>/pages/categories.php" class="nav-item <?= $current_page === 'categories' ? 'active' : '' ?>">
                <i class="fa-solid fa-layer-group"></i>
                <span>Categories</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-label">SALES</span>
            <a href="<?= APP_URL ?>/pages/orders.php" class="nav-item <?= $current_page === 'orders' ? 'active' : '' ?>">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>Orders</span>
                <?php if($stats['pending_orders'] > 0): ?>
                <span class="nav-badge"><?= $stats['pending_orders'] ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= APP_URL ?>/pages/customers.php" class="nav-item <?= $current_page === 'customers' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-group"></i>
                <span>Customers</span>
            </a>
            <a href="<?= APP_URL ?>/pages/payments.php" class="nav-item <?= $current_page === 'payments' ? 'active' : '' ?>">
                <i class="fa-solid fa-indian-rupee-sign"></i>
                <span>Payments</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-label">MARKETING</span>
            <a href="<?= APP_URL ?>/pages/coupons.php" class="nav-item <?= $current_page === 'coupons' ? 'active' : '' ?>">
                <i class="fa-solid fa-tag"></i>
                <span>Coupons</span>
            </a>
            <a href="<?= APP_URL ?>/pages/reviews.php" class="nav-item <?= $current_page === 'reviews' ? 'active' : '' ?>">
                <i class="fa-regular fa-star"></i>
                <span>Reviews</span>
            </a>
            <a href="<?= APP_URL ?>/pages/wishlists.php" class="nav-item <?= $current_page === 'wishlists' ? 'active' : '' ?>">
                <i class="fa-solid fa-heart"></i>
                <span>Wishlists</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-label">SHIPPING</span>
            <a href="<?= APP_URL ?>/pages/shipping.php" class="nav-item <?= $current_page === 'shipping' ? 'active' : '' ?>">
                <i class="fa-solid fa-truck"></i>
                <span>Shipping</span>
            </a>
            <a href="<?= APP_URL ?>/pages/shipping_calculator.php" class="nav-item <?= $current_page === 'shipping_calculator' ? 'active' : '' ?>">
                <i class="fa-solid fa-calculator"></i>
                <span>Calc</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-label">ENGAGE</span>
            <a href="<?= APP_URL ?>/pages/events.php" class="nav-item <?= $current_page === 'events' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-star"></i>
                <span>Events</span>
            </a>
            <a href="<?= APP_URL ?>/pages/courses.php" class="nav-item <?= $current_page === 'courses' ? 'active' : '' ?>">
                <i class="fa-solid fa-graduation-cap"></i>
                <span>Courses</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-label">REPORTS</span>
            <a href="<?= APP_URL ?>/pages/reports.php" class="nav-item <?= $current_page === 'reports' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span>Analytics</span>
            </a>
        </div>

        <div class="nav-section">
            <span class="nav-section-label">SYSTEM</span>
            <a href="<?= APP_URL ?>/pages/admins.php" class="nav-item <?= $current_page === 'admins' ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Admin Users</span>
            </a>
            <a href="<?= APP_URL ?>/pages/settings.php" class="nav-item <?= $current_page === 'settings' ? 'active' : '' ?>">
                <i class="fa-solid fa-gear"></i>
                <span>Settings</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar"><?= strtoupper(substr($admin['name'], 0, 1)) ?></div>
            <div class="admin-details">
                <span class="admin-name"><?= htmlspecialchars($admin['name']) ?></span>
                <span class="admin-role"><?= ucfirst(str_replace('_', ' ', $admin['role'])) ?></span>
            </div>
        </div>
        <a href="<?= APP_URL ?>/logout.php" class="logout-btn" title="Logout">
            <i class="fa-solid fa-right-from-bracket"></i>
        </a>
    </div>
</aside>

<!-- Main Content -->
<div class="main-wrapper">
    <!-- Top Bar -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="topbar-breadcrumb">
                <span class="breadcrumb-home">DentInno</span>
                <i class="fa-solid fa-chevron-right"></i>
                <span><?= isset($page_title) ? $page_title : 'Dashboard' ?></span>
            </div>
        </div>
        <div class="topbar-right">
            <!-- Search -->
            <div class="topbar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Search products, orders..." id="globalSearch">
            </div>

            <!-- Notifications -->
            <div class="notif-wrapper">
                <button class="icon-btn" id="notifBtn">
                    <i class="fa-solid fa-bell"></i>
                    <?php if($stats['notif_count'] > 0): ?>
                    <span class="notif-dot"><?= $stats['notif_count'] ?></span>
                    <?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <span>Notifications</span>
                        <a href="#">Mark all read</a>
                    </div>
                    <?php foreach($stats['notifications'] as $notif): ?>
                    <div class="notif-item notif-<?= $notif['type'] ?>">
                        <div class="notif-icon">
                            <?php
                            $icons = ['order'=>'cart-shopping','payment'=>'indian-rupee-sign','stock'=>'boxes-stacked','customer'=>'user','system'=>'gear'];
                            echo '<i class="fa-solid fa-' . ($icons[$notif['type']] ?? 'bell') . '"></i>';
                            ?>
                        </div>
                        <div class="notif-content">
                            <span class="notif-title"><?= htmlspecialchars($notif['title']) ?></span>
                            <span class="notif-msg"><?= htmlspecialchars($notif['message']) ?></span>
                            <span class="notif-time"><?= timeAgo($notif['created_at']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if(empty($stats['notifications'])): ?>
                    <div class="notif-empty">No new notifications</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Admin -->
            <div class="admin-chip">
                <div class="admin-chip-avatar"><?= strtoupper(substr($admin['name'], 0, 1)) ?></div>
                <span><?= htmlspecialchars(explode(' ', $admin['name'])[0]) ?></span>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <main class="page-content">
