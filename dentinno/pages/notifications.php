<?php
// Lightweight endpoint to mark header notifications as read (single or all).
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    echo json_encode(['success' => false, 'message' => 'Bad request']); exit;
}
if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Reload the page.']); exit; }

// Never let a PHP warning/exception leak HTML into the JSON response (breaks res.json()).
try {
    $d = json_decode(file_get_contents('php://input'), true);
    $action = $d['action'] ?? '';
    $aid = (int)($_SESSION['admin_id'] ?? 0);
    if ($action === 'read') {
        // Per-admin: record that THIS admin dismissed this notification (others still see it).
        db()->execute("INSERT IGNORE INTO notification_reads (notification_id, admin_id) VALUES (?, ?)", [(int)($d['id'] ?? 0), $aid]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'read_all') {
        // Mark every currently-unread (for this admin) notification as read by this admin.
        db()->execute(
            "INSERT IGNORE INTO notification_reads (notification_id, admin_id)
             SELECT n.id, ? FROM notifications n
              WHERE NOT EXISTS (SELECT 1 FROM notification_reads nr WHERE nr.notification_id=n.id AND nr.admin_id=?)",
            [$aid, $aid]
        );
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
