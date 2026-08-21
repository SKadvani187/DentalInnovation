<?php
// DentInno CRM - Database Configuration
// ⚠️ Change these values to your server settings

// ── Secret loading order (highest priority first) ──
// 1. Environment variables (set on the server / in the host panel).
// 2. includes/config.local.php (git-ignored — put real prod secrets here).
// 3. The hardcoded fallbacks below (dev defaults; safe placeholders for prod).
// config.local.php is plain PHP that may define() any of the constants below; because we
// only define a constant if it isn't already set, anything it defines wins.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}
// define()-if-absent, preferring an env var of the same name when present.
function defv(string $key, $fallback): void {
    if (defined($key)) return;                 // already set by config.local.php
    $env = getenv($key);
    define($key, $env !== false && $env !== '' ? $env : $fallback);
}

defv('DB_HOST', 'localhost');
defv('DB_USER', 'root');          // dev fallback; set prod creds via env/config.local.php
defv('DB_PASS', '');              // dev fallback; set prod creds via env/config.local.php
defv('DB_NAME', 'dentinno_crm');
define('DB_CHARSET', 'utf8mb4');

// App Settings
define('APP_NAME', 'DentInno CRM');
defv('APP_URL', 'http://localhost:8088');   // dev fallback; set prod URL via env/config.local.php
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Kolkata');

// Session Settings
define('SESSION_NAME', 'dentinno_session');
define('SESSION_LIFETIME', 3600); // 1 hour

// ---- OTP Settings ----
// Channel: 'sms' (Fast2SMS) or 'email' (SMTP). Switch here to change delivery.
define('OTP_CHANNEL', 'sms');
define('OTP_TTL', 300);            // OTP valid for 5 minutes (seconds)
define('OTP_MAX_ATTEMPTS', 5);    // send+verify attempts before block
define('OTP_BLOCK_MINUTES', 60);  // block duration after limit (1 hour)
define('OTP_RESEND_COOLDOWN', 30);// min seconds between resend
// Boolean dev flags — env value '0'/'false'/'off' => false. Set both to false in production
// (env: OTP_DEV_RETURN=false OTP_SSL_INSECURE=false, or define them in config.local.php).
function defv_bool(string $key, bool $fallback): void {
    if (defined($key)) return;
    $env = getenv($key);
    if ($env === false || $env === '') { define($key, $fallback); return; }
    define($key, !in_array(strtolower($env), ['0','false','off','no',''], true));
}
defv_bool('OTP_DEV_RETURN', false);   // never return the OTP in API responses by default; enable in dev via env/config.local.php
defv_bool('OTP_SSL_INSECURE', false); // verify TLS on outbound gateway/SMS calls by default; relax only in dev via env/config.local.php

// ---- Error display ----
// Leaking PHP/PDO errors to the client exposes stack traces and query/credential hints.
// Default: debug ON only on local dev (APP_URL points at localhost), OFF everywhere else.
// Override explicitly with APP_DEBUG via env / config.local.php.
$__isLocal = (stripos(APP_URL, 'localhost') !== false || strpos(APP_URL, '127.0.0.1') !== false);
defv_bool('APP_DEBUG', $__isLocal);
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

// ---- OTP SMS provider ----
// The LIVE provider (Fast2SMS / 2Factor / MSG91) is chosen in Admin -> Settings -> OTP
// (super admin only), stored privately in site_settings.otpConfig. The constants below are
// FALLBACK defaults used when otpConfig is empty. Production: set OTP_DEV_RETURN=false and
// OTP_SSL_INSECURE=false (ship includes/cacert.pem).
//
// Fast2SMS (https://www.fast2sms.com/ -> Dev API -> API Key). Paste your key:
defv('FAST2SMS_API_KEY', '');   // env: FAST2SMS_API_KEY  (or define in config.local.php)
defv('FAST2SMS_SENDER_ID', '');
defv('FAST2SMS_ROUTE', '');    // 'q'=Quick SMS (no verification needed). 'otp'=needs account verification.

// Email OTP via SMTP (used when OTP_CHANNEL='email'). Gmail app password recommended.
defv('SMTP_HOST', 'smtp.gmail.com');
defv('SMTP_PORT', 587);
defv('SMTP_USER', '');          // env: SMTP_USER
defv('SMTP_PASS', '');          // env: SMTP_PASS
defv('SMTP_FROM', 'no-reply@smartdentalinnovations.com');
defv('SMTP_FROM_NAME', 'Reetzdent Innovations Private limited');

// ---- AI Image Search (Anthropic Claude Vision) ----
// Set via env ANTHROPIC_API_KEY or config.local.php. Leave blank to fall back to
// filename-based matching (no AI).
defv('ANTHROPIC_API_KEY', '');                 // env: ANTHROPIC_API_KEY
defv('ANTHROPIC_MODEL', 'claude-haiku-4-5');   // fast + cheap; ideal for short image classification

// ---- Razorpay Payment Gateway ----
// Free to integrate (₹0 setup/AMC; per-transaction fee only). Get keys at
// https://dashboard.razorpay.com/ -> Settings -> API Keys. Start in TEST MODE (rzp_test_...).
// The webhook secret is set when you create a webhook (Settings -> Webhooks) pointing at
// /api/v1/razorpay_webhook.php with the 'payment.captured' event.
// SECURITY: the values below are TEST/sandbox fallbacks. For production set the live keys via
// env vars (RAZORPAY_KEY_ID/RAZORPAY_KEY_SECRET/RAZORPAY_WEBHOOK_SECRET) or config.local.php
// and rotate the committed test key. Never commit a live (rzp_live_) secret to this file.
// SECRETS: set these via env vars or includes/config.local.php (git-ignored) — never commit real keys.
defv('RAZORPAY_KEY_ID', '');        // env: RAZORPAY_KEY_ID (rzp_test_/rzp_live_) or config.local.php
defv('RAZORPAY_KEY_SECRET', '');    // env: RAZORPAY_KEY_SECRET or config.local.php
defv('RAZORPAY_WEBHOOK_SECRET', ''); // env: RAZORPAY_WEBHOOK_SECRET (after creating the webhook)
define('RAZORPAY_CURRENCY', 'INR');

date_default_timezone_set(TIMEZONE);

// Database Connection (Singleton)
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            // Log the real reason server-side; never leak PDO internals to the client.
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            $msg = (defined('APP_DEBUG') && APP_DEBUG)
                ? 'Database connection failed: ' . $e->getMessage()
                : 'Service temporarily unavailable. Please try again later.';
            die(json_encode(['error' => $msg]));
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }
}

// Helper: Get DB instance
function db() {
    return Database::getInstance();
}

// Inventory ledger helper (recordStockMovement) — available everywhere config is loaded.
require_once __DIR__ . '/inventory.php';
// Activity-log + notification helpers (logActivity / pushNotification).
require_once __DIR__ . '/activity.php';

// Helper: Format currency in INR
function formatCurrency($amount) {
    return '₹' . number_format($amount, 0, '.', ',');
}

// Validation helpers --------------------------------------------------------
// True only if $tmp is a real image within sane pixel bounds (rejects corrupt files,
// zero-dimension images, and absurdly large dimensions that could DoS image processing).
function imageDimsOk(string $tmp, int $maxPx = 6000): bool {
    $d = @getimagesize($tmp);
    return is_array($d) && ($d[0] ?? 0) >= 1 && ($d[1] ?? 0) >= 1 && $d[0] <= $maxPx && $d[1] <= $maxPx;
}
// Trim + hard-cap a string to a max length (multibyte-safe). Use to enforce DB column limits
// gracefully server-side instead of relying on the client's maxlength.
function clip($value, int $max): string {
    $s = trim((string)$value);
    return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : $s;
}

// Helper: Format date
function formatDate($date, $format = 'd M Y') {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

// Helper: Time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}

// Helper: Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input ?? '')));
}

// Helper: Generate order number
function generateOrderNumber() {
    return 'ORD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// Helper: Generate slug
function generateSlug($string) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
}

// Helper: Status badge class
function statusBadge($status) {
    $map = [
        'pending'    => 'badge-warning',
        'processing' => 'badge-info',
        'confirmed'  => 'badge-primary',
        'shipped'    => 'badge-purple',
        'delivered'  => 'badge-success',
        'cancelled'  => 'badge-danger',
        'refunded'   => 'badge-secondary',
        'paid'       => 'badge-success',
        'unpaid'     => 'badge-danger',
        'partial'    => 'badge-warning',
        'active'     => 'badge-success',
        'inactive'   => 'badge-secondary',
    ];
    return $map[$status] ?? 'badge-secondary';
}
