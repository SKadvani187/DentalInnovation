<?php
// Razorpay payment endpoints (storefront). Auth required (Bearer token).
//
// POST /api/v1/payment_razorpay.php?action=create  {orderId}
//      -> creates a Razorpay order for an existing (pending) storefront order.
//         Amount is recomputed from the DB order total — never trusted from the client.
//         Returns {keyId, rzpOrderId, amount, currency, prefill}.
//
// POST /api/v1/payment_razorpay.php?action=verify  {orderId, razorpay_payment_id, razorpay_order_id, razorpay_signature}
//      -> verifies the checkout signature (HMAC-SHA256), marks the order paid and
//         records the payment. Idempotent (safe if the webhook already ran).
//
// The webhook (razorpay_webhook.php) is the source of truth for capture; this verify
// step gives the customer instant feedback on the success screen.
require_once __DIR__ . '/_bootstrap.php';

$cust   = requireCustomer();
$action = qstr('action');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') jsonErr('Method not allowed', 405);

if (RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '') {
    jsonErr('Online payments are not configured', 503);
}

$db   = db();
$body = jsonBody();

// Load an order owned by the authenticated customer (ownership check).
function loadOwnedOrder($db, $cust, string $orderNumber): array {
    if ($orderNumber === '') jsonErr('orderId is required', 422);
    $o = $db->fetchOne(
        "SELECT * FROM orders WHERE order_number=? AND customer_id=?",
        [$orderNumber, $cust['id']]
    );
    if (!$o) jsonErr('Order not found', 404);
    return $o;
}

// Clamp a Razorpay payment method to the payments.method ENUM. The true raw value
// (e.g. 'wallet', 'emi') is preserved in payments.notes.
function clampMethod(?string $raw): string {
    $allowed = ['upi', 'card', 'netbanking'];
    return in_array($raw, $allowed, true) ? $raw : 'upi';
}

// Minimal Razorpay REST call via cURL (no Composer/SDK needed on shared hosting).
function rzpRequest(string $httpMethod, string $path, ?array $payload = null): array {
    $ch = curl_init('https://api.razorpay.com/v1' . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_CUSTOMREQUEST  => $httpMethod,
        CURLOPT_TIMEOUT        => 30,
    ];
    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
    // DEV-only: skip SSL verification (mirrors OTP sender behaviour). Set OTP_SSL_INSECURE=false in prod.
    if (defined('OTP_SSL_INSECURE') && OTP_SSL_INSECURE) {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
    } elseif (is_readable(__DIR__ . '/../../includes/cacert.pem')) {
        $opts[CURLOPT_CAINFO] = __DIR__ . '/../../includes/cacert.pem';
    }
    curl_setopt_array($ch, $opts);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($raw === false) jsonErr('Payment gateway unreachable: ' . $err, 502);
    $json = json_decode($raw, true);
    if (!is_array($json)) jsonErr('Invalid gateway response', 502);
    if ($code >= 400) {
        $msg = $json['error']['description'] ?? 'Gateway error';
        jsonErr('Razorpay: ' . $msg, 502);
    }
    return $json;
}

// ----------------- action=create -----------------
if ($action === 'create') {
    $o = loadOwnedOrder($db, $cust, (string)($body['orderId'] ?? ''));
    if ($o['payment_status'] === 'paid') jsonErr('Order is already paid', 409);

    $amountPaise = (int) round(((float)$o['total']) * 100);
    if ($amountPaise < 100) jsonErr('Order amount too low for online payment', 422);

    $rzp = rzpRequest('POST', '/orders', [
        'amount'          => $amountPaise,
        'currency'        => RAZORPAY_CURRENCY,
        'receipt'         => $o['order_number'],
        'payment_capture' => 1,
        'notes'           => ['order_id' => (string)$o['id'], 'order_number' => $o['order_number']],
    ]);
    $rzpOrderId = $rzp['id'] ?? '';
    if ($rzpOrderId === '') jsonErr('Failed to create gateway order', 502);

    // Upsert a pending payment row keyed to this order (transaction_id holds the rzp order id for now).
    $existing = $db->fetchOne(
        "SELECT id FROM payments WHERE order_id=? AND status='pending' ORDER BY id DESC LIMIT 1",
        [$o['id']]
    );
    if ($existing) {
        $db->execute(
            "UPDATE payments SET amount=?, transaction_id=?, notes=? WHERE id=?",
            [(float)$o['total'], $rzpOrderId, 'razorpay; rzp_order=' . $rzpOrderId, $existing['id']]
        );
    } else {
        $db->insert(
            "INSERT INTO payments (order_id, amount, method, transaction_id, status, notes)
             VALUES (?,?,?,?, 'pending', ?)",
            [$o['id'], (float)$o['total'], 'upi', $rzpOrderId, 'razorpay; rzp_order=' . $rzpOrderId]
        );
    }

    jsonOut([
        'success'   => true,
        'keyId'     => RAZORPAY_KEY_ID,
        'rzpOrderId'=> $rzpOrderId,
        'amount'    => $amountPaise,
        'currency'  => RAZORPAY_CURRENCY,
        'orderId'   => $o['order_number'],
        'prefill'   => [
            'name'    => $cust['name'] ?? '',
            'email'   => $cust['email'] ?? '',
            'contact' => $cust['phone'] ?? '',
        ],
    ]);
}

// ----------------- action=verify -----------------
if ($action === 'verify') {
    $o = loadOwnedOrder($db, $cust, (string)($body['orderId'] ?? ''));

    $rzpPaymentId = (string)($body['razorpay_payment_id'] ?? '');
    $rzpOrderId   = (string)($body['razorpay_order_id'] ?? '');
    $signature    = (string)($body['razorpay_signature'] ?? '');
    if ($rzpPaymentId === '' || $rzpOrderId === '' || $signature === '') {
        jsonErr('Missing payment confirmation fields', 422);
    }

    $expected = hash_hmac('sha256', $rzpOrderId . '|' . $rzpPaymentId, RAZORPAY_KEY_SECRET);
    if (!hash_equals($expected, $signature)) {
        jsonErr('Payment signature verification failed', 400);
    }

    // Already reconciled (e.g. webhook landed first) — return success idempotently.
    if ($o['payment_status'] === 'paid') {
        jsonOut(['success' => true, 'orderId' => $o['order_number'], 'paymentStatus' => 'paid']);
    }

    // Best-effort: fetch the payment to record the real method (signature is the trust anchor).
    $payMethod = 'upi';
    $rawMethod = '';
    try {
        $p = rzpRequest('GET', '/payments/' . rawurlencode($rzpPaymentId));
        $rawMethod = (string)($p['method'] ?? '');
        $payMethod = clampMethod($rawMethod);
    } catch (Throwable $t) {
        // ignore — verification already succeeded via signature
    }

    $pdo = $db->getConnection();
    $pdo->beginTransaction();
    try {
        $db->execute("UPDATE orders SET payment_status='paid' WHERE id=?", [$o['id']]);
        $notes = trim('razorpay; rzp_order=' . $rzpOrderId . ($rawMethod ? '; method=' . $rawMethod : ''));
        $existing = $db->fetchOne(
            "SELECT id FROM payments WHERE order_id=? ORDER BY (status='pending') DESC, id DESC LIMIT 1",
            [$o['id']]
        );
        if ($existing) {
            $db->execute(
                "UPDATE payments SET method=?, transaction_id=?, status='completed', payment_date=NOW(), notes=? WHERE id=?",
                [$payMethod, $rzpPaymentId, $notes, $existing['id']]
            );
        } else {
            $db->insert(
                "INSERT INTO payments (order_id, amount, method, transaction_id, status, payment_date, notes)
                 VALUES (?,?,?,?, 'completed', NOW(), ?)",
                [$o['id'], (float)$o['total'], $payMethod, $rzpPaymentId, $notes]
            );
        }
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        jsonErr('Failed to record payment: ' . $t->getMessage(), 500);
    }

    // Best-effort WhatsApp payment confirmation. Fires once: this point is only reached
    // on the real unpaid->paid transition (the idempotency guard above returns early otherwise).
    try {
        require_once __DIR__ . '/../../includes/whatsapp_sender.php';
        $ord = $db->fetchOne("SELECT * FROM orders WHERE id=?", [$o['id']]);
        if (!empty($cust['phone']) && $ord) waPaymentSuccess($cust, $ord);
    } catch (Throwable $e) { error_log('WA payVerify: ' . $e->getMessage()); }

    jsonOut(['success' => true, 'orderId' => $o['order_number'], 'paymentStatus' => 'paid']);
}

jsonErr('Unknown action', 400);
