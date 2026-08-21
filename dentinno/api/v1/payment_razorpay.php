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
    // Checked BEFORE the binding lookup: once paid, the pending rzp-order row has been
    // rewritten to the pay_ id, so the order_ lookup below would no longer find it.
    if ($o['payment_status'] === 'paid') {
        jsonOut(['success' => true, 'orderId' => $o['order_number'], 'paymentStatus' => 'paid']);
    }

    // SECURITY: the signature only proves these ids belong together — NOT that they belong to
    // THIS storefront order. Without binding, a customer could pay a cheap rzp order they
    // created and submit those (validly-signed) ids against an expensive order. Bind the
    // submitted razorpay_order_id to the one we stored at create time for this order.
    $pendingPay = $db->fetchOne(
        "SELECT transaction_id FROM payments WHERE order_id=? AND transaction_id LIKE 'order\_%' ESCAPE '\\\\' ORDER BY id DESC LIMIT 1",
        [$o['id']]
    );
    $storedRzpOrderId = $pendingPay['transaction_id'] ?? '';
    if ($storedRzpOrderId === '' || !hash_equals($storedRzpOrderId, $rzpOrderId)) {
        jsonErr('Payment does not match this order', 400);
    }

    // Fetch the payment: record the real method AND assert the captured amount + order id match
    // what we expect. The signature is the integrity anchor; the amount check is the fraud anchor.
    $payMethod = 'upi';
    $rawMethod = '';
    try {
        $p = rzpRequest('GET', '/payments/' . rawurlencode($rzpPaymentId));
        $rawMethod = (string)($p['method'] ?? '');
        $payMethod = clampMethod($rawMethod);

        $expectedPaise = (int) round(((float)$o['total']) * 100);
        $paidPaise     = (int)($p['amount'] ?? 0);
        if ($paidPaise !== $expectedPaise) {
            jsonErr('Paid amount does not match order total', 400);
        }
        if (($p['order_id'] ?? '') !== $rzpOrderId) {
            jsonErr('Payment is not for this order', 400);
        }
        if (($p['status'] ?? '') !== 'captured' && ($p['status'] ?? '') !== 'authorized') {
            jsonErr('Payment not captured', 400);
        }
    } catch (Throwable $t) {
        // A gateway-fetch failure must NOT silently pass an unverified amount. Reject.
        jsonErr('Could not verify payment with gateway. Please contact support if charged.', 502);
    }

    $pdo = $db->getConnection();
    $pdo->beginTransaction();
    try {
        // Conditional transition — only the caller that flips unpaid->paid proceeds. A
        // concurrent webhook (or retry) gets rowCount()===0 and bails, so WhatsApp/record
        // side-effects fire exactly once.
        // Mark paid AND advance the order from 'pending' to 'confirmed' — a paid online order
        // is confirmed and ready for fulfilment (a still-'pending' paid order is misleading and
        // would be picked up by the abandoned-order cleanup). Only touch 'pending' so a later
        // admin transition (shipped, etc.) is never rolled back by a duplicate webhook.
        $changed = $db->execute(
            "UPDATE orders
                SET payment_status='paid',
                    status = CASE WHEN status='pending' THEN 'confirmed' ELSE status END,
                    payment_failed_at = NULL
              WHERE id=? AND payment_status<>'paid'",
            [$o['id']]
        );
        if ($changed < 1) {
            $pdo->rollBack();
            jsonOut(['success' => true, 'orderId' => $o['order_number'], 'paymentStatus' => 'paid']);
        }
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
        // Audit trail: payment confirmed -> order moved pending->confirmed (verify path).
        try { $db->execute("INSERT INTO order_status_history (order_id, status, note) VALUES (?, 'confirmed', 'payment received')", [$o['id']]); } catch (Throwable $e) {}
        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        jsonErr('Failed to record payment: ' . $t->getMessage(), 500);
    }

    // Best-effort WhatsApp payment confirmation. Fires once: this point is only reached
    // on the real unpaid->paid transition (the idempotency guard above returns early otherwise).
    $ord = $db->fetchOne("SELECT * FROM orders WHERE id=?", [$o['id']]);
    try {
        require_once __DIR__ . '/../../includes/whatsapp_sender.php';
        if (!empty($cust['phone']) && $ord) waPaymentSuccess($cust, $ord);
    } catch (Throwable $e) { error_log('WA payVerify: ' . $e->getMessage()); }

    // Best-effort order emails — fire once, on the real paid transition:
    //   * admin "order placed (paid)" notification, and
    //   * customer confirmation + PDF invoice.
    try {
        require_once __DIR__ . '/../../includes/order_mailer.php';
        if ($ord) {
            $omItems = $db->fetchAll("SELECT * FROM order_items WHERE order_id=?", [$o['id']]);
            sendOrderAdminMail($ord, $omItems, $cust, 'placed');
            sendOrderCustomerMail($ord, $omItems, $cust);
        }
    } catch (Throwable $e) { error_log('orderMail paid: ' . $e->getMessage()); }

    jsonOut(['success' => true, 'orderId' => $o['order_number'], 'paymentStatus' => 'paid']);
}

// Notify the admin that an online payment was cancelled / failed for an order. The storefront
// calls this when the customer dismisses or the gateway rejects the Razorpay popup. Only
// notifies while the order is still unpaid, so a later successful retry isn't contradicted.
if ($action === 'failed') {
    $o = loadOwnedOrder($db, $cust, (string)($body['orderId'] ?? ''));
    if (($o['payment_status'] ?? '') !== 'paid') {
        // Stamp the payment failure so the admin can immediately tell an abandoned/failed-payment
        // order from a fresh one mid-checkout — without waiting 30 min for the cleanup cron. The
        // order stays 'pending' (still retry-able from the customer's Order Details page); the
        // stamp is cleared if a later retry succeeds (see the paid transitions above).
        $db->execute(
            "UPDATE orders SET payment_failed_at = NOW() WHERE id = ? AND payment_status <> 'paid'",
            [$o['id']]
        );
        try {
            require_once __DIR__ . '/../../includes/order_mailer.php';
            $items = $db->fetchAll("SELECT * FROM order_items WHERE order_id=?", [$o['id']]);
            sendOrderAdminMail($o, $items, $cust, 'failed');
        } catch (Throwable $e) { error_log('orderMail failed: ' . $e->getMessage()); }
    }
    jsonOut(['success' => true]);
}

jsonErr('Unknown action', 400);
