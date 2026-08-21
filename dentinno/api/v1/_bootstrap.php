<?php
// Shared bootstrap for all /api/v1 endpoints.
// CORS for React dev origin, JSON headers, DB access, helpers.

require_once __DIR__ . '/../../includes/config.php';

// --- CORS (allow React dev + same-origin) ---
$allowed = ['http://localhost:5173', 'http://localhost:4173', 'http://localhost:3000'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *"); // public read API
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');
header('Content-Type: application/json; charset=utf-8');

// Preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- JSON response helpers ---
function jsonOut($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function jsonErr(string $msg, int $code = 400): void {
    jsonOut(['success' => false, 'error' => $msg], $code);
}

// Decode JSON column safely -> array (or default)
function jcol($v, $default = []) {
    if ($v === null || $v === '') return $default;
    $d = json_decode($v, true);
    return $d === null ? $default : $d;
}

// Query param helpers
function qstr(string $k, string $def = ''): string { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $def; }
function qint(string $k, int $def = 0): int { return isset($_GET[$k]) ? (int)$_GET[$k] : $def; }

// Phone helpers for storefront forms — Indian mobile only (same rule as login/OTP).
// Strips +91 / leading 0 / spaces / dashes, then validates the final 10-digit number.
function cleanPhone(string $p): string {
    $d = preg_replace('/\D/', '', $p);
    if (strlen($d) > 10 && strncmp($d, '91', 2) === 0) $d = substr($d, 2);   // drop +91
    if (strlen($d) === 11 && $d[0] === '0') $d = substr($d, 1);              // drop leading 0
    return substr($d, -10) ?: '';                                            // last 10
}
function validPhone(string $p): bool {
    return (bool) preg_match('/^[6-9]\d{9}$/', cleanPhone($p));
}

// Read JSON request body -> array
function jsonBody(): array {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

// Generate an opaque API token
function makeToken(): string {
    return bin2hex(random_bytes(24));
}

// Read the Authorization header. Apache frequently strips it from $_SERVER, so we
// fall back to the rewrite/redirect var and to apache_request_headers()/getallheaders()
// (mod_php keeps the real header there even when $_SERVER does not).
function authHeader(): string {
    if (!empty($_SERVER['HTTP_AUTHORIZATION']))          return trim($_SERVER['HTTP_AUTHORIZATION']);
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    foreach (['apache_request_headers', 'getallheaders'] as $fn) {
        if (function_exists($fn)) {
            foreach ((array)$fn() as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) return trim($v);
            }
        }
    }
    return '';
}

// Resolve the bearer token -> customer row (or null). Used to protect order endpoints.
function authCustomer(): ?array {
    $token = '';
    $hdr = authHeader();
    if (stripos($hdr, 'Bearer ') === 0) {
        $token = trim(substr($hdr, 7));
    }
    // Fallback: custom header that Apache never strips (works even when the standard
    // Authorization header is dropped by the server). The client sends both.
    if ($token === '' && !empty($_SERVER['HTTP_X_AUTH_TOKEN'])) {
        $token = trim((string)$_SERVER['HTTP_X_AUTH_TOKEN']);
    }
    if ($token !== '') {
        // A soft-deleted customer's token must stop working (they get logged out).
        return db()->fetchOne("SELECT * FROM customers WHERE api_token=? AND is_deleted=0", [$token]) ?: null;
    }
    return null;
}

function requireCustomer(): array {
    $c = authCustomer();
    if (!$c) jsonErr('Unauthorized', 401);
    return $c;
}

// Public-facing customer shape (no token/internal fields leaked beyond token on login)
function customerPublic(array $c): array {
    return [
        'id'        => (int)$c['id'],
        'name'      => $c['name'],
        'email'     => $c['email'],
        'mobile'    => $c['phone'],
        'city'      => $c['city'],
        'state'     => $c['state'],
        'address'   => $c['address'],
        'pincode'   => $c['pincode'],
        'clinicName'=> $c['clinic_name'] ?? null,
        'addresses' => jcol($c['addresses'] ?? null, []),
        // Provisional = account auto-created at login but the buyer hasn't given their real
        // name yet (placeholder "Customer 1234"). The storefront uses this to still show the
        // name-prompt instead of logging in with the placeholder.
        'isProvisional' => (int)($c['is_provisional'] ?? 0) === 1,
    ];
}
