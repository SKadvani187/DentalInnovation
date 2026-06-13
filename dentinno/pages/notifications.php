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
    if ($action === 'read') {
        db()->execute("UPDATE notifications SET is_read=1 WHERE id=?", [(int)($d['id'] ?? 0)]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'read_all') {
        db()->execute("UPDATE notifications SET is_read=1 WHERE is_read=0");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
