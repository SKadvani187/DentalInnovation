<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/rbac.php';   // DB-driven RBAC service (can()/requireView()/requireAction()/navTree())

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

// Load/refresh this admin's DB-driven permissions for the request. Handles sessions created
// before RBAC existed (loads from the DB) and live permission changes (reloads on version bump).
if (function_exists('rbacEnsureLoaded')) { rbacEnsureLoaded(); }

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

        // Resolve the role_id (fall back to the legacy role slug) and load DB-driven
        // permissions into the session for this login.
        $roleId = (int)($admin['role_id'] ?? 0);
        if (!$roleId && !empty($admin['role'])) {
            $r = db()->fetchOne("SELECT id FROM roles WHERE slug=?", [$admin['role']]);
            $roleId = (int)($r['id'] ?? 0);
        }
        $_SESSION['admin_role_id'] = $roleId;
        rbacLoad($roleId);

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

// NOTE: the old static rolePermissions() map was removed — RBAC is now fully DB-driven
// (roles / page_registry / role_permissions; see includes/rbac.php). hasPermission() below is
// a compatibility shim over can() for pages not yet migrated to requireView()/requireAction().

// Check permission — COMPATIBILITY SHIM over the DB-driven model (includes/rbac.php).
// Used by pages not yet migrated to requireView()/requireAction(). It maps the legacy
// module permissions onto the new page model: 'manage_*' means "any access to that module"
// (view/create/edit/delete), 'view_reports' means reports-view. Super admin bypasses.
function hasPermission($permission) {
    if (userIsSuper()) return true;
    static $map = [
        'manage_products'  => 'products',    'manage_categories' => 'categories',
        'manage_orders'    => 'orders',      'manage_coupons'    => 'coupons',
        'manage_customers' => 'customers',   'manage_combos'     => 'combos',
        'manage_offers'    => 'offers',      'manage_reviews'    => 'reviews',
        'manage_content'   => 'testimonials','manage_shipping'   => 'shipping',
        'view_reports'     => 'reports',     'manage_refunds'    => 'refunds',
        'manage_admins'    => 'admins',      'manage_settings'   => 'settings',
    ];
    $key = $map[$permission] ?? null;
    if ($key === null) return false;
    if ($permission === 'view_reports') return can($key, 'view');
    $p = $_SESSION['rbac'][$key] ?? null;          // "manage_*" = any access to the module
    return $p ? (bool)($p['v'] || $p['c'] || $p['e'] || $p['d']) : false;
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

// Hard gate for whole HTML pages (GET). Renders a styled 403 and stops if the current
// admin lacks the permission. Call near the top of a page, after the header include is
// NOT yet done (so we can short-circuit before rendering page content).
function requirePermissionPage(string $permission): void {
    if (!hasPermission($permission)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403 — Forbidden</title>'
           . '<style>body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#111;color:#eee;'
           . 'display:grid;place-items:center;min-height:100vh;margin:0}div{text-align:center}'
           . 'a{color:#D4A017}</style></head><body><div><h1>403 — Access denied</h1>'
           . '<p>You do not have permission to view this page.</p>'
           . '<p><a href="' . htmlspecialchars(APP_URL) . '/index.php">Back to dashboard</a></p>'
           . '</div></body></html>';
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
    // Per-admin unread: hide notifications this admin has already dismissed.
    try {
        $aid = (int)($_SESSION['admin_id'] ?? 0);
        $s['notifications'] = db()->fetchAll(
            "SELECT n.* FROM notifications n
              WHERE NOT EXISTS (SELECT 1 FROM notification_reads nr WHERE nr.notification_id=n.id AND nr.admin_id=?)
              ORDER BY n.created_at DESC LIMIT 10",
            [$aid]
        );
    } catch (Throwable $e) {
        $s['notifications'] = db()->fetchAll("SELECT * FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 10");
    }
    try {
        $pendingReviews = db()->fetchOne("SELECT COUNT(*) as val FROM product_reviews WHERE is_approved=0 AND is_deleted=0")['val'] ?? 0;
    } catch (Throwable $e) { $pendingReviews = 0; }
    $s['pending_reviews'] = (int)$pendingReviews;
    // The bell badge must equal what the dropdown LISTS — i.e. the notifications rows only.
    // pending_reviews used to be added here, but those rows are NOT shown in the dropdown,
    // so the badge said "3" while the dropdown showed 1. (getDashboardStats() already counts
    // notifications only — this keeps the two code paths consistent across pages.)
    $s['notif_count'] = count($s['notifications']);

    // Actionable badges for the sidebar — kept cheap (single COUNTs), each guarded so a missing
    // table/column never breaks the header on an un-migrated DB.
    try { $s['pending_refunds']      = (int)(db()->fetchOne("SELECT COUNT(*) as val FROM refund_requests WHERE status='pending'")['val'] ?? 0); } catch (Throwable $e) { $s['pending_refunds'] = 0; }
    try { $s['unread_messages']      = (int)(db()->fetchOne("SELECT COUNT(*) as val FROM contact_messages WHERE is_read=0 AND is_deleted=0")['val'] ?? 0); } catch (Throwable $e) { $s['unread_messages'] = 0; }
    try { $s['new_quotes']           = (int)(db()->fetchOne("SELECT COUNT(*) as val FROM bulk_quotes WHERE is_read=0 AND is_deleted=0")['val'] ?? 0); } catch (Throwable $e) { $s['new_quotes'] = 0; }
    try { $s['unanswered_questions'] = (int)(db()->fetchOne("SELECT COUNT(*) as val FROM product_questions WHERE is_answered=0 AND is_deleted=0")['val'] ?? 0); } catch (Throwable $e) { $s['unanswered_questions'] = 0; }
    return $s;
}

// Dashboard Stats (full — dashboard/index.php only). Superset of getSidebarBadges().
function getDashboardStats() {
    // Short-TTL cache: the dashboard fires ~25 aggregate queries. Serving them from a 30s file
    // cache keeps repeated loads cheap while staying fresh enough for an ops view. Degrades
    // gracefully — any cache read/write failure just falls through to a live recompute.
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dentinno_dashboard_stats.cache';
    $ttl = 30;
    if (is_readable($cacheFile) && (time() - (int)@filemtime($cacheFile)) < $ttl) {
        $cached = @unserialize((string)@file_get_contents($cacheFile));
        if (is_array($cached) && !empty($cached)) return $cached;
    }

    $stats = [];

    // Revenue = NET SALES (subtotal, excl. tax & shipping) on PAID orders. Tax is a pass-through
    // liability and shipping is largely pass-through, so they're not sales revenue. Cash-collected
    // metrics (payments "Total Received", customer "Total Spent") intentionally still use `total`.
    $stats['total_revenue'] = db()->fetchOne(
        "SELECT COALESCE(SUM(subtotal), 0) as val FROM orders WHERE payment_status = 'paid'"
    )['val'];

    // Total Orders excludes cancelled orders (they were never fulfilled). Refunded orders are kept
    // (they were real, completed sales that were later reversed).
    $stats['total_orders'] = db()->fetchOne(
        "SELECT COUNT(*) as val FROM orders WHERE status <> 'cancelled'"
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
        "SELECT COALESCE(SUM(subtotal), 0) as val FROM orders
         WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())"
    )['val'];

    // Active only, so this "+X this month" is a true subset of Total Customers (which filters is_active=1).
    $stats['new_customers_month'] = db()->fetchOne(
        "SELECT COUNT(*) as val FROM customers
         WHERE is_active = 1 AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())"
    )['val'];

    // Recent orders
    $stats['recent_orders'] = db()->fetchAll(
        "SELECT o.*, c.name as customer_name, c.phone 
         FROM orders o JOIN customers c ON o.customer_id = c.id 
         ORDER BY o.created_at DESC LIMIT 8"
    );

    // Monthly PAID revenue for the last 6 CALENDAR months (current month + 5 prior).
    // Uses the SAME basis as the Total/Monthly Revenue cards (payment_status='paid' on `total`)
    // so the chart reconciles with them. Result is zero-filled in PHP so a month with no orders
    // still appears (no collapsed gaps), and grouped by a SELECTed expression so it's safe under
    // MySQL's ONLY_FULL_GROUP_BY.
    $revRows = db()->fetchAll(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym,
                COALESCE(SUM(subtotal), 0) AS revenue,
                COUNT(*) AS orders
         FROM orders
         WHERE payment_status = 'paid'
           AND created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 5 MONTH), '%Y-%m-01')
         GROUP BY ym"
    );
    $revByMonth = [];
    foreach ($revRows as $r) { $revByMonth[$r['ym']] = $r; }
    $stats['revenue_chart'] = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime(date('Y-m-01') . " -$i month");   // first day of each of the last 6 months
        $ym = date('Y-m', $ts);
        $stats['revenue_chart'][] = [
            'month'   => date('M Y', $ts),
            'revenue' => isset($revByMonth[$ym]) ? (float)$revByMonth[$ym]['revenue'] : 0,
            'orders'  => isset($revByMonth[$ym]) ? (int)$revByMonth[$ym]['orders']  : 0,
        ];
    }

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
        $stats['pending_reviews'] = db()->fetchOne("SELECT COUNT(*) as val FROM product_reviews WHERE is_approved=0 AND is_deleted=0")['val'] ?? 0;
        $stats['avg_rating']      = db()->fetchOne("SELECT ROUND(AVG(rating),1) as val FROM product_reviews WHERE is_approved=1 AND is_deleted=0")['val'] ?? 0;
        $stats['rating_count']    = db()->fetchOne("SELECT COUNT(*) as val FROM product_reviews WHERE is_approved=1 AND is_deleted=0")['val'] ?? 0;
    } catch(Exception $e) { $stats['pending_reviews']=$stats['avg_rating']=$stats['rating_count']=0; }

    // Shipping methods
    try {
        $stats['active_shipping_methods'] = db()->fetchOne("SELECT COUNT(*) as val FROM shipping_methods WHERE is_active=1")['val'] ?? 0;
    } catch(Exception $e) { $stats['active_shipping_methods']=0; }

    // --- Period comparisons (REAL deltas, not decorative arrows) ---
    // Last full calendar month, paid revenue — to compute month-over-month % vs this month.
    $stats['revenue_last_month'] = db()->fetchOne(
        "SELECT COALESCE(SUM(total),0) as val FROM orders
         WHERE payment_status='paid'
           AND created_at >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')
           AND created_at <  DATE_FORMAT(NOW(), '%Y-%m-01')"
    )['val'];
    $stats['orders_this_month'] = db()->fetchOne(
        "SELECT COUNT(*) as val FROM orders WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"
    )['val'];
    $stats['orders_last_month'] = db()->fetchOne(
        "SELECT COUNT(*) as val FROM orders
         WHERE created_at >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')
           AND created_at <  DATE_FORMAT(NOW(), '%Y-%m-01')"
    )['val'];

    // --- Today snapshot (the #1 glance metric on Shopify/Amazon) ---
    $stats['today_orders']  = db()->fetchOne("SELECT COUNT(*) as val FROM orders WHERE DATE(created_at)=CURDATE()")['val'];
    $stats['today_revenue'] = db()->fetchOne("SELECT COALESCE(SUM(total),0) as val FROM orders WHERE payment_status='paid' AND DATE(created_at)=CURDATE()")['val'];
    $stats['today_customers']= db()->fetchOne("SELECT COUNT(*) as val FROM customers WHERE DATE(created_at)=CURDATE()")['val'];

    // --- Pending actions (the "needs attention" hub) — each links to its page ---
    $stats['pa_orders']  = (int)($stats['pending_orders'] ?? 0);
    $stats['pa_reviews'] = (int)($stats['pending_reviews'] ?? 0);
    try { $stats['pa_refunds']   = (int)(db()->fetchOne("SELECT COUNT(*) as val FROM refund_requests WHERE status='pending'")['val'] ?? 0); } catch(Throwable $e){ $stats['pa_refunds']=0; }
    try { $stats['pa_messages']  = (int)(db()->fetchOne("SELECT COUNT(*) as val FROM contact_messages WHERE is_read=0 AND is_deleted=0")['val'] ?? 0); } catch(Throwable $e){ $stats['pa_messages']=0; }
    try { $stats['pa_quotes']    = (int)(db()->fetchOne("SELECT COUNT(*) as val FROM bulk_quotes WHERE is_read=0 AND is_deleted=0")['val'] ?? 0); } catch(Throwable $e){ $stats['pa_quotes']=0; }
    try { $stats['pa_questions'] = (int)(db()->fetchOne("SELECT COUNT(*) as val FROM product_questions WHERE is_answered=0 AND is_deleted=0")['val'] ?? 0); } catch(Throwable $e){ $stats['pa_questions']=0; }
    // Sidebar-badge aliases (header reads these key names on every page — keep them in sync).
    $stats['pending_refunds']      = $stats['pa_refunds'];
    $stats['unread_messages']      = $stats['pa_messages'];
    $stats['new_quotes']           = $stats['pa_quotes'];
    $stats['unanswered_questions'] = $stats['pa_questions'];

    // --- Recent customers (new signups) ---
    $stats['recent_customers'] = db()->fetchAll(
        "SELECT name, clinic_name, customer_type, created_at FROM customers WHERE is_active=1 ORDER BY created_at DESC LIMIT 6"
    );

    // --- Low-stock product list (actionable — not just a count) ---
    $stats['low_stock_list'] = db()->fetchAll(
        "SELECT name, sku, stock, min_stock_alert FROM products WHERE stock <= min_stock_alert AND is_active=1 ORDER BY stock ASC LIMIT 8"
    );

    // Unread notifications, per-admin (guarded — tables may be absent on an un-migrated DB).
    try {
        $aid = (int)($_SESSION['admin_id'] ?? 0);
        $stats['notifications'] = db()->fetchAll(
            "SELECT n.* FROM notifications n
              WHERE NOT EXISTS (SELECT 1 FROM notification_reads nr WHERE nr.notification_id=n.id AND nr.admin_id=?)
              ORDER BY n.created_at DESC LIMIT 10", [$aid]);
    } catch(Throwable $e) { $stats['notifications'] = []; }
    $stats['notif_count'] = count($stats['notifications']);

    // Persist to the short-TTL cache (atomic-ish; failure is non-fatal).
    @file_put_contents($cacheFile, serialize($stats), LOCK_EX);

    return $stats;
}
