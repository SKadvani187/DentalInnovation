<?php
// Razorpay webhook receiver (public endpoint — NO bearer auth).
// Razorpay calls this server-to-server when a payment is captured, so the order is
// reconciled even if the customer closes the browser before the verify step runs.
//
// Configure in Razorpay Dashboard -> Settings -> Webhooks:
//   URL    : https://YOUR-DOMAIN/api/v1/razorpay_webhook.php
//   Events : payment.captured  (order.paid optional)
//   Secret : paste the same value into RAZORPAY_WEBHOOK_SECRET in config.php
//
// Security: the raw body is HMAC-verified against the X-Razorpay-Signature header.
require_once __DIR__ . '/_bootstrap.php';

// Read the raw body ONCE (signature is computed over the exact bytes).
$raw = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (RAZORPAY_WEBHOOK_SECRET === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Webhook not configured']);
    exit;
}

$expected = hash_hmac('sha256', $raw, RAZORPAY_WEBHOOK_SECRET);
if ($sigHeader === '' || !hash_equals($expected, $sigHeader)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid signature']);
    exit;
}

$payload = json_decode($raw, true);
$event   = is_array($payload) ? ($payload['event'] ?? '') : '';

// Only payment-capture events change order state. Ack everything else with 200.
if ($event !== 'payment.captured' && $event !== 'order.paid') {
    http_response_code(200);
    echo json_encode(['success' => true, 'ignored' => $event]);
    exit;
}

$payment    = $payload['payload']['payment']['entity'] ?? [];
$rzpOrderId = (string)($payment['order_id'] ?? ($payload['payload']['order']['entity']['id'] ?? ''));
$rzpPayId   = (string)($payment['id'] ?? '');
$rawMethod  = (string)($payment['method'] ?? '');

if ($rzpOrderId === '') {
    http_response_code(200); // nothing actionable, but ack so Razorpay stops retrying
    echo json_encode(['success' => true, 'note' => 'no order id in payload']);
    exit;
}

$db = db();

// Locate our order via the rzp order id we stored in payments.notes at create time.
$row = $db->fetchOne(
    "SELECT p.id AS pid, o.id AS oid, o.payment_status AS pay, o.total AS total
       FROM payments p JOIN orders o ON o.id = p.order_id
      WHERE p.notes LIKE ? ORDER BY p.id DESC LIMIT 1",
    ['%rzp_order=' . $rzpOrderId . '%']
);

if (!$row) {
    http_response_code(200); // unknown order — ack to avoid endless retries
    echo json_encode(['success' => true, 'note' => 'order not found']);
    exit;
}

// Idempotent: if already paid, do nothing.
if ($row['pay'] === 'paid') {
    http_response_code(200);
    echo json_encode(['success' => true, 'note' => 'already paid']);
    exit;
}

$allowed   = ['upi', 'card', 'netbanking'];
$payMethod = in_array($rawMethod, $allowed, true) ? $rawMethod : 'upi';
$notes     = trim('razorpay; rzp_order=' . $rzpOrderId . ($rawMethod ? '; method=' . $rawMethod : '') . '; via=webhook');

$pdo = $db->getConnection();
$pdo->beginTransaction();
try {
    // Mark paid AND advance 'pending' -> 'confirmed' (same as the verify path) so a webhook-
    // confirmed order isn't left looking abandoned. Only touches 'pending'.
    $db->execute(
        "UPDATE orders
            SET payment_status='paid',
                status = CASE WHEN status='pending' THEN 'confirmed' ELSE status END,
                payment_failed_at = NULL
          WHERE id=?",
        [$row['oid']]
    );
    $db->execute(
        "UPDATE payments SET method=?, transaction_id=?, status='completed', payment_date=NOW(), notes=? WHERE id=?",
        [$payMethod, ($rzpPayId ?: $rzpOrderId), $notes, $row['pid']]
    );
    // Audit trail: payment confirmed via webhook -> pending->confirmed.
    try { $db->execute("INSERT INTO order_status_history (order_id, status, note) VALUES (?, 'confirmed', 'payment received (webhook)')", [$row['oid']]); } catch (Throwable $e) {}
    $pdo->commit();
} catch (Throwable $t) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'reconcile failed']);
    exit;
}

// Best-effort notifications. Reached only on the real unpaid->paid transition (the
// 'already paid' guard above returns early), so none of these can double-send.
$c   = $db->fetchOne("SELECT * FROM customers WHERE id=(SELECT customer_id FROM orders WHERE id=?)", [$row['oid']]);
$ord = $db->fetchOne("SELECT * FROM orders WHERE id=?", [$row['oid']]);

// WhatsApp payment confirmation.
try {
    require_once __DIR__ . '/../../includes/whatsapp_sender.php';
    if ($c && !empty($c['phone']) && $ord) waPaymentSuccess($c, $ord);
} catch (Throwable $e) { error_log('WA payWebhook: ' . $e->getMessage()); }

// Admin notification + customer confirmation email with PDF invoice — same as the verify
// path, so a browser-closed payment that lands only via webhook still emails the customer.
try {
    require_once __DIR__ . '/../../includes/order_mailer.php';
    if ($ord) {
        $omItems = $db->fetchAll("SELECT * FROM order_items WHERE order_id=?", [$row['oid']]);
        sendOrderAdminMail($ord, $omItems, $c ?: null, 'placed');
        sendOrderCustomerMail($ord, $omItems, $c ?: null);
    }
} catch (Throwable $e) { error_log('orderMail payWebhook: ' . $e->getMessage()); }

http_response_code(200);
echo json_encode(['success' => true]);
