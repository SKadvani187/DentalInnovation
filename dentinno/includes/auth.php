<?php
require_once __DIR__ . '/config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Check if logged in
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Require login (redirect if not)
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

// Login function
function loginAdmin($email, $password) {
    $admin = db()->fetchOne(
        "SELECT * FROM admin_users WHERE email = ? AND is_active = 1",
        [$email]
    );

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_email']= $admin['email'];
        $_SESSION['admin_role'] = $admin['role'];

        db()->execute(
            "UPDATE admin_users SET last_login = NOW() WHERE id = ?",
            [$admin['id']]
        );
        return true;
    }
    return false;
}

// Logout function
function logoutAdmin() {
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Get current admin
function currentAdmin() {
    if (!isLoggedIn()) return null;
    return [
        'id'    => $_SESSION['admin_id'],
        'name'  => $_SESSION['admin_name'],
        'email' => $_SESSION['admin_email'],
        'role'  => $_SESSION['admin_role'],
    ];
}

// Check permission
function hasPermission($permission) {
    $role = $_SESSION['admin_role'] ?? '';
    if ($role === 'super_admin') return true;
    // Add granular permission logic here
    return false;
}

// Dashboard Stats
function getDashboardStats() {
    $stats = [];

    $stats['total_revenue'] = db()->fetchOne(
        "SELECT COALESCE(SUM(total), 0) as val FROM orders WHERE payment_status = 'paid'"
    )['val'];

    $stats['total_orders'] = db()->fetchOne(
        "SELECT COUNT(*) as val FROM orders"
    )['val'];

    $stats['total_customers'] = db()->fetchOne(
        "SELECT COUNT(*) as val FROM customers WHERE is_active = 1"
    )['val'];

    $stats['total_products'] = db()->fetchOne(
        "SELECT COUNT(*) as val FROM products WHERE is_active = 1"
    )['val'];

    $stats['pending_orders'] = db()->fetchOne(
        "SELECT COUNT(*) as val FROM orders WHERE status = 'pending'"
    )['val'];

    $stats['low_stock'] = db()->fetchOne(
        "SELECT COUNT(*) as val FROM products WHERE stock <= min_stock_alert AND is_active = 1"
    )['val'];

    $stats['monthly_revenue'] = db()->fetchOne(
        "SELECT COALESCE(SUM(total), 0) as val FROM orders 
         WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())"
    )['val'];

    $stats['new_customers_month'] = db()->fetchOne(
        "SELECT COUNT(*) as val FROM customers 
         WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())"
    )['val'];

    // Recent orders
    $stats['recent_orders'] = db()->fetchAll(
        "SELECT o.*, c.name as customer_name, c.phone 
         FROM orders o JOIN customers c ON o.customer_id = c.id 
         ORDER BY o.created_at DESC LIMIT 8"
    );

    // Monthly revenue chart (last 6 months)
    $stats['revenue_chart'] = db()->fetchAll(
        "SELECT DATE_FORMAT(created_at, '%b %Y') as month, 
                COALESCE(SUM(total), 0) as revenue,
                COUNT(*) as orders
         FROM orders 
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY YEAR(created_at), MONTH(created_at)
         ORDER BY created_at ASC"
    );

    // Top products
    $stats['top_products'] = db()->fetchAll(
        "SELECT p.name, p.price, p.total_sales, p.stock,
                c.name as category
         FROM products p LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.is_active = 1
         ORDER BY p.total_sales DESC LIMIT 5"
    );

    // Events stats
    try {
        $stats['total_events']      = db()->fetchOne("SELECT COUNT(*) as val FROM events WHERE status='published'"  )['val'] ?? 0;
        $stats['upcoming_events']   = db()->fetchOne("SELECT COUNT(*) as val FROM events WHERE status='published' AND start_date >= NOW()")['val'] ?? 0;
        $stats['total_registrations']= db()->fetchOne("SELECT COUNT(*) as val FROM event_registrations")['val'] ?? 0;
    } catch(Exception $e) { $stats['total_events']=$stats['upcoming_events']=$stats['total_registrations']=0; }

    // Courses stats
    try {
        $stats['total_courses']    = db()->fetchOne("SELECT COUNT(*) as val FROM courses WHERE status='published'")['val'] ?? 0;
        $stats['total_enrollments']= db()->fetchOne("SELECT COUNT(*) as val FROM course_enrollments")['val'] ?? 0;
    } catch(Exception $e) { $stats['total_courses']=$stats['total_enrollments']=0; }

    // Reviews stats
    try {
        $stats['pending_reviews'] = db()->fetchOne("SELECT COUNT(*) as val FROM product_reviews WHERE is_approved=0")['val'] ?? 0;
        $stats['avg_rating']      = db()->fetchOne("SELECT ROUND(AVG(rating),1) as val FROM product_reviews WHERE is_approved=1")['val'] ?? 0;
    } catch(Exception $e) { $stats['pending_reviews']=$stats['avg_rating']=0; }

    // Shipping methods
    try {
        $stats['active_shipping_methods'] = db()->fetchOne("SELECT COUNT(*) as val FROM shipping_methods WHERE is_active=1")['val'] ?? 0;
    } catch(Exception $e) { $stats['active_shipping_methods']=0; }

    // Unread notifications
    $stats['notifications'] = db()->fetchAll(
        "SELECT * FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 10"
    );
    $stats['notif_count'] = count($stats['notifications']);

    // Add pending reviews to notif count
    $stats['notif_count'] += ($stats['pending_reviews'] ?? 0);

    return $stats;
}
