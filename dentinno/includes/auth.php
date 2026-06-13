<?php
require_once __DIR__ . '/config.php';

// Start session (hardened cookie flags — HttpOnly + SameSite, Secure when on HTTPS)
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['SERVER_PORT'] ?? '') == 443)
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);
    session_name(SESSION_NAME);
    session_start();
}

// Idle-timeout enforcement (SESSION_LIFETIME). Expire stale admin sessions.
if (isset($_SESSION['admin_id'])) {
    $last = $_SESSION['last_activity'] ?? time();
    if ((time() - $last) > SESSION_LIFETIME) {
        $_SESSION = [];
        session_destroy();
    } else {
        $_SESSION['last_activity'] = time();
    }
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
        // Prevent session fixation — issue a fresh session id on privilege change.
        session_regenerate_id(true);
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        $_SESSION['admin_email']= $admin['email'];
        $_SESSION['admin_role'] = $admin['role'];
        $_SESSION['last_activity'] = time();

        db()->execute(
            "UPDATE admin_users SET last_login = NOW() WHERE id = ?",
            [$admin['id']]
        );
        return true;
    }
    return false;
}

// Logout function — clear session data, destroy, and expire the cookie.
function logoutAdmin() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// ---- Admin login brute-force throttle (per client IP) ----
const LOGIN_MAX_ATTEMPTS = 5;    // consecutive failures before lockout
const LOGIN_LOCK_MINUTES  = 15;  // cool-down window once locked
const ADMIN_MIN_PASSWORD  = 8;   // minimum length for admin account passwords

function clientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Seconds remaining on an active lockout for this IP, else 0.
function loginLockRemaining(): int {
    try {
        $row = db()->fetchOne("SELECT locked_until FROM admin_login_attempts WHERE ip=?", [clientIp()]);
    } catch (Throwable $e) { return 0; } // throttle table missing → fail open, don't block logins
    if ($row && !empty($row['locked_until'])) {
        $remain = strtotime($row['locked_until']) - time();
        return $remain > 0 ? $remain : 0;
    }
    return 0;
}

// Record one failed attempt; lock the IP once the threshold is reached.
function loginRegisterFailure(): void {
    $ip = clientIp();
    try {
        db()->execute(
            "INSERT INTO admin_login_attempts (ip, attempts) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, locked_until = NULL",
            [$ip]
        );
        $row = db()->fetchOne("SELECT attempts FROM admin_login_attempts WHERE ip=?", [$ip]);
        if ($row && (int)$row['attempts'] >= LOGIN_MAX_ATTEMPTS) {
            db()->execute(
                "UPDATE admin_login_attempts SET attempts=0, locked_until=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE ip=?",
                [LOGIN_LOCK_MINUTES, $ip]
            );
        }
    } catch (Throwable $e) { /* throttle is best-effort; never break login on its failure */ }
}

// Clear the throttle for this IP after a successful login.
function loginClearFailures(): void {
    try { db()->execute("DELETE FROM admin_login_attempts WHERE ip=?", [clientIp()]); }
    catch (Throwable $e) { /* ignore */ }
}

// ---- CSRF protection (per-session token) ----
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Hidden form field for CSRF-protected POST forms.
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

// Validate a submitted CSRF token (form field or X-CSRF-Token header). Constant-time.
function verifyCsrf(): bool {
    $sent = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? '';
    return !empty($_SESSION['csrf_token']) && is_string($sent) && hash_equals($_SESSION['csrf_token'], $sent);
}

// ---- Auto-enforce admin auth ----
// Every admin page in /pages/ includes this file at the very top. Enforce login HERE,
// before any page-level request handler can run, so AJAX handlers that `exit` early
// can never execute for an unauthenticated caller. Scripts that must bypass (login.php)
// set $AUTH_PUBLIC = true BEFORE requiring this file.
if (empty($GLOBALS['AUTH_PUBLIC'])) {
    $caller = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    // Enforce for anything under the admin /pages/ directory. stripos (not strpos) so a
    // case-insensitive filesystem (Windows/IIS, some Apache) can't bypass via /Pages/.
    // NOTE: this is a backstop — each handler should still gate itself; see verifyCsrf().
    if (stripos($caller, '/pages/') !== false) {
        requireLogin();
    }
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

// Permissions granted to each non-super role. super_admin implicitly has ALL permissions.
// Sensitive money/admin actions (manage_refunds, manage_admins, manage_settings) are
// intentionally NOT in any list here, so only super_admin can perform them.
function rolePermissions(string $role): array {
    switch ($role) {
        case 'admin':
            return ['manage_products','manage_categories','manage_orders','manage_coupons',
                    'manage_customers','manage_combos','manage_offers','manage_reviews',
                    'manage_content','view_reports'];
        case 'staff':
            return ['manage_orders','manage_reviews','manage_content'];
        default:
            return [];
    }
}

// Check permission
function hasPermission($permission) {
    $role = $_SESSION['admin_role'] ?? '';
    if ($role === 'super_admin') return true;
    return in_array($permission, rolePermissions($role), true);
}

// Hard gate for sensitive AJAX endpoints: emit a 403 JSON and stop if the current
// admin lacks the permission. Call right after verifyCsrf() in the POST handler.
function requirePermissionAjax(string $permission): void {
    if (!hasPermission($permission)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission for this action.']);
        exit;
    }
}

// Lightweight sidebar/topbar badges — the ONLY stats header.php needs. Runs on every
// admin page, so it must stay cheap (a handful of COUNTs), unlike getDashboardStats()
// which fires ~20 aggregate/chart queries and should only run on the dashboard (index.php).
function getSidebarBadges() {
    $s = [];
    $s['low_stock'] = db()->fetchOne(
        "SELECT COUNT(*) as val FROM products WHERE stock <= min_stock_alert AND is_active = 1"
    )['val'];
    $s['pending_orders'] = db()->fetchOne(
        "SELECT COUNT(*) as val FROM orders WHERE status = 'pending'"
    )['val'];
    $s['notifications'] = db()->fetchAll(
        "SELECT * FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 10"
    );
    try {
        $pendingReviews = db()->fetchOne("SELECT COUNT(*) as val FROM product_reviews WHERE is_approved=0")['val'] ?? 0;
    } catch (Throwable $e) { $pendingReviews = 0; }
    $s['notif_count'] = count($s['notifications']) + (int)$pendingReviews;
    return $s;
}

// Dashboard Stats (full — dashboard/index.php only). Superset of getSidebarBadges().
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
