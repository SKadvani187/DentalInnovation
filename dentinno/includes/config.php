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
define('APP_URL', 'http://localhost/dentinno');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Kolkata');

// Session Settings
define('SESSION_NAME', 'dentinno_session');
define('SESSION_LIFETIME', 3600); // 1 hour

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
