<?php
// DentInno CRM - Database Configuration
// ⚠️ Change these values to your server settings

define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Apna MySQL username
define('DB_PASS', '');              // Apna MySQL password
define('DB_NAME', 'dentinno_crm');
define('DB_CHARSET', 'utf8mb4');

// App Settings
define('APP_NAME', 'DentInno CRM');
define('APP_URL', 'http://localhost:80/dentinno');
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
define('OTP_RESEND_COOLDOWN', 30);// min seconds between resends
define('OTP_DEV_RETURN', false);   // DEV: return OTP in API response (set false in prod)
define('OTP_SSL_INSECURE', true); // DEV-only: skip SSL verify (local AV/proxy MITM). SET false IN PRODUCTION.

// ---- OTP SMS provider ----
// The LIVE provider (Fast2SMS / 2Factor / MSG91) is chosen in Admin -> Settings -> OTP
// (super admin only), stored privately in site_settings.otpConfig. The constants below are
// FALLBACK defaults used when otpConfig is empty. Production: set OTP_DEV_RETURN=false and
// OTP_SSL_INSECURE=false (ship includes/cacert.pem).
//
// Fast2SMS (https://www.fast2sms.com/ -> Dev API -> API Key). Paste your key:
define('FAST2SMS_API_KEY', 'Li3OedADUckqG0gmfuhxSCWKVIwXJ1HRnEB4Mvbl76ytoQZYjNAtYFvI73NyxMLhO4ul9Skw1VbXazR6');   // <-- PASTE FAST2SMS KEY HERE
define('FAST2SMS_SENDER_ID', 'TXTIND');
define('FAST2SMS_ROUTE', 'q');    // 'q'=Quick SMS (no verification needed). 'otp'=needs account verification.

// Email OTP via SMTP (used when OTP_CHANNEL='email'). Gmail app password recommended.
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');          // <-- your gmail address
define('SMTP_PASS', '');          // <-- gmail app password
define('SMTP_FROM', 'no-reply@smartdentalinnovations.com');
define('SMTP_FROM_NAME', 'Smart Dental Innovations');

// ---- AI Image Search (Anthropic Claude Vision) ----
// Paste your Anthropic API key to enable real visual product recognition.
// Get one at https://console.anthropic.com/ -> Settings -> API Keys.
// Leave blank to fall back to filename-based matching (no AI).
define('ANTHROPIC_API_KEY', '');                 // <-- PASTE ANTHROPIC API KEY HERE
define('ANTHROPIC_MODEL', 'claude-haiku-4-5');   // fast + cheap; ideal for short image classification

// ---- Razorpay Payment Gateway ----
// Free to integrate (₹0 setup/AMC; per-transaction fee only). Get keys at
// https://dashboard.razorpay.com/ -> Settings -> API Keys. Start in TEST MODE (rzp_test_...).
// The webhook secret is set when you create a webhook (Settings -> Webhooks) pointing at
// /api/v1/razorpay_webhook.php with the 'payment.captured' event.
// SECURITY: like the Fast2SMS key above these are committed in plaintext for now; for
// production prefer a git-ignored config.local.php or environment variables.
define('RAZORPAY_KEY_ID', 'rzp_test_Sy7B3yIhK8TdMF');        // <-- PASTE RAZORPAY KEY ID HERE (rzp_test_... or rzp_live_...)
define('RAZORPAY_KEY_SECRET', 'a4ytKAf1vtSLIPhoIQ55prwV');    // <-- PASTE RAZORPAY KEY SECRET HERE
define('RAZORPAY_WEBHOOK_SECRET', ''); // <-- PASTE WEBHOOK SECRET HERE (after creating the webhook)
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
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
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

// Helper: Format currency in INR
function formatCurrency($amount) {
    return '₹' . number_format($amount, 0, '.', ',');
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
    return htmlspecialchars(strip_tags(trim($input)));
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
