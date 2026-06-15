<?php
// Admin order-notification emails (best-effort).
// Config lives in site_settings.orderMailConfig (super-admin only; never exposed to the
// storefront — see api/v1/settings.php $PRIVATE). Subject + body are stored as templates
// using {{placeholder}} tokens, so the admin can edit/extend the email content from the
// admin panel WITHOUT any code change. Sends HTML via the same raw-SMTP approach used by
// includes/otp_sender.php (no external library).
require_once __DIR__ . '/config.php';

// ---- Config ----------------------------------------------------------------

// Read the saved admin email config (decoded). [] when unset/invalid.
function orderMailConfig(): array {
    try {
        $row = db()->fetchOne("SELECT svalue FROM site_settings WHERE skey='orderMailConfig'");
        $cfg = $row ? json_decode($row['svalue'] ?? 'null', true) : null;
        return is_array($cfg) ? $cfg : [];
    } catch (Throwable $e) { return []; }
}

// ---- Template rendering ----------------------------------------------------

// Replace {{key}} tokens from $vars. Unknown tokens are left as-is so typos stay visible.
function omcRender(string $tpl, array $vars): string {
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($vars) {
        return array_key_exists($m[1], $vars) ? (string)$vars[$m[1]] : $m[0];
    }, $tpl);
}

function omcMoney($n): string { return '₹' . number_format((float)$n, 2); }

// Build the {{items_table}} HTML from order_items rows (free/gift lines shown as FREE).
function omcItemsTable(array $items): string {
    $rows = '';
    foreach ($items as $it) {
        $name    = htmlspecialchars((string)($it['product_name'] ?? ''));
        $variant = !empty($it['variant']) ? ' <span style="color:#888;">(' . htmlspecialchars((string)$it['variant']) . ')</span>' : '';
        $isGift  = (($it['line_type'] ?? '') === 'gift');
        $qty     = (int)($it['quantity'] ?? 0);
        $price   = $isGift ? 'FREE' : omcMoney($it['price'] ?? 0);
        $total   = $isGift ? 'FREE' : omcMoney(((float)($it['price'] ?? 0)) * $qty);
        $rows .= '<tr>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;">' . $name . $variant . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center;">' . $qty . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">' . $price . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">' . $total . '</td>'
            . '</tr>';
    }
    return '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
        . '<thead><tr style="background:#f5f5f5;text-align:left;">'
        . '<th style="padding:8px;">Product</th><th style="padding:8px;text-align:center;">Qty</th>'
        . '<th style="padding:8px;text-align:right;">Price</th><th style="padding:8px;text-align:right;">Total</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>';
}

// Build the placeholder map from an order + items + customer. type = 'placed' | 'failed'.
function omcVars(array $order, array $items, ?array $customer, string $type): array {
    $addr = json_decode($order['shipping_address'] ?? 'null', true) ?: [];
    $statusMsg = $type === 'failed'
        ? 'PAYMENT FAILED / CANCELLED — the customer did not complete the online payment for this order.'
        : (($order['payment_method'] ?? '') === 'cod'
            ? 'New COD order placed successfully.'
            : 'Order placed and payment received successfully.');
    $itemCount = 0; foreach ($items as $it) $itemCount += (int)($it['quantity'] ?? 0);
    // Customer-supplied fields are HTML-escaped: they land in the admin email body verbatim,
    // so an order placed with markup in the name/address would otherwise inject into the email.
    $addressRaw = trim(($addr['address'] ?? '') . ', ' . ($addr['city'] ?? '') . ', ' . ($addr['state'] ?? '') . ' ' . ($addr['pincode'] ?? ''), " ,");
    return [
        'order_id'       => $order['order_number'] ?? '',
        'order_date'     => $order['created_at'] ?? '',
        'customer_name'  => htmlspecialchars((string)($customer['name']  ?? ($addr['name']  ?? ''))),
        'customer_email' => htmlspecialchars((string)($customer['email'] ?? '')),
        'customer_phone' => htmlspecialchars((string)($customer['phone'] ?? ($addr['phone'] ?? ''))),
        'address'        => htmlspecialchars($addressRaw),
        'payment_method' => strtoupper((string)($order['payment_method'] ?? '')),
        'payment_status' => (string)($order['payment_status'] ?? ''),
        'status_message' => $statusMsg,
        'item_count'     => $itemCount,
        'items_table'    => omcItemsTable($items),
        'subtotal'       => omcMoney($order['subtotal'] ?? 0),
        'discount'       => omcMoney($order['discount'] ?? 0),
        'shipping'       => omcMoney($order['shipping_charge'] ?? 0),
        'tax'            => omcMoney($order['tax'] ?? 0),
        'total'          => omcMoney($order['total'] ?? 0),
    ];
}

// Default subject/body templates (used when the admin hasn't configured one).
function omcDefaultSubject(string $type): string {
    return $type === 'failed'
        ? '❌ Payment Failed — Order {{order_id}}'
        : '✅ New Order {{order_id}} — {{total}} ({{payment_status}})';
}
function omcDefaultBody(string $type): string {
    $accent = $type === 'failed' ? '#dc2626' : '#16a34a';
    $title  = $type === 'failed' ? '❌ Payment Failed / Cancelled' : '✅ New Order Received';
    return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:660px;margin:auto;color:#222;">'
        . '<h2 style="color:' . $accent . ';margin:0 0 4px;">' . $title . ' — {{order_id}}</h2>'
        . '<p style="margin:0 0 16px;color:#555;">{{status_message}}</p>'
        . '<table style="width:100%;font-size:14px;margin-bottom:16px;">'
        . '<tr><td style="padding:4px 0;color:#888;">Customer</td><td style="padding:4px 0;text-align:right;font-weight:600;">{{customer_name}}</td></tr>'
        . '<tr><td style="padding:4px 0;color:#888;">Phone</td><td style="padding:4px 0;text-align:right;">{{customer_phone}}</td></tr>'
        . '<tr><td style="padding:4px 0;color:#888;">Email</td><td style="padding:4px 0;text-align:right;">{{customer_email}}</td></tr>'
        . '<tr><td style="padding:4px 0;color:#888;">Address</td><td style="padding:4px 0;text-align:right;">{{address}}</td></tr>'
        . '<tr><td style="padding:4px 0;color:#888;">Payment</td><td style="padding:4px 0;text-align:right;">{{payment_method}} — {{payment_status}}</td></tr>'
        . '<tr><td style="padding:4px 0;color:#888;">Date</td><td style="padding:4px 0;text-align:right;">{{order_date}}</td></tr>'
        . '</table>'
        . '<h3 style="border-bottom:2px solid ' . $accent . ';padding-bottom:6px;">Items ({{item_count}})</h3>'
        . '{{items_table}}'
        . '<table style="width:100%;font-size:14px;margin-top:16px;">'
        . '<tr><td style="padding:4px 0;color:#888;">Subtotal</td><td style="padding:4px 0;text-align:right;">{{subtotal}}</td></tr>'
        . '<tr><td style="padding:4px 0;color:#888;">Discount</td><td style="padding:4px 0;text-align:right;">-{{discount}}</td></tr>'
        . '<tr><td style="padding:4px 0;color:#888;">Shipping</td><td style="padding:4px 0;text-align:right;">{{shipping}}</td></tr>'
        . '<tr><td style="padding:4px 0;color:#888;">Tax</td><td style="padding:4px 0;text-align:right;">{{tax}}</td></tr>'
        . '<tr><td style="padding:8px 0;font-weight:700;font-size:16px;border-top:2px solid #222;">Total</td><td style="padding:8px 0;text-align:right;font-weight:700;font-size:16px;border-top:2px solid #222;">{{total}}</td></tr>'
        . '</table>'
        . '<p style="margin-top:24px;color:#aaa;font-size:12px;">Automated notification • {{order_id}}</p>'
        . '</div>';
}

// ---- Delivery log ----------------------------------------------------------
// Every live send records its outcome in order_mail_log so the admin can see, per order,
// which notification was SENT and which is still PENDING / FAILED (and retry it). The table
// is created by database_order_mail_log.sql. Logging is best-effort: a missing table or DB
// error is swallowed (logged to error_log) and never breaks the order/payment flow.
//
// status values: 'sent'    = SMTP accepted the message (250)
//                'failed'  = enabled + attempted but the server/connection rejected it (retry)
//                'pending' = enabled but not yet deliverable (no recipient / SMTP creds missing)
//
// One row per (order_id, mail_type) — a retry updates the same row (upsert) and bumps attempts.
function omcLog(array $order, string $type, string $recipient, string $subject, string $status, ?string $error): void {
    try {
        $orderId = isset($order['id']) ? (int)$order['id'] : null;
        if (!$orderId) return; // no order context (e.g. admin test send) -> nothing to track
        $orderNo = (string)($order['order_number'] ?? '');
        $sentAt  = $status === 'sent' ? date('Y-m-d H:i:s') : null;
        db()->query(
            "INSERT INTO order_mail_log (order_id, order_number, mail_type, recipient, subject, status, error, attempts, sent_at)
             VALUES (?,?,?,?,?,?,?,1,?)
             ON DUPLICATE KEY UPDATE recipient=VALUES(recipient), subject=VALUES(subject),
               status=VALUES(status), error=VALUES(error), attempts=attempts+1,
               sent_at=COALESCE(VALUES(sent_at), sent_at), updated_at=NOW()",
            [$orderId, $orderNo, $type, $recipient, mb_substr($subject, 0, 250), $status, $error, $sentAt]
        );
    } catch (Throwable $e) {
        error_log('omcLog (table missing? run database_order_mail_log.sql): ' . $e->getMessage());
    }
}

// ---- Sending ---------------------------------------------------------------

// Send using an EXPLICIT config (used by both the live hooks and the admin "send test").
// Returns true if at least one recipient was accepted. Never throws.
// $logResult=false skips the delivery-log write (used by the admin "send test", which has no
// real order to track and reports its result inline instead).
function omcSendWithConfig(array $cfg, array $order, array $items, ?array $customer, string $type, bool $logResult = true): bool {
    try {
        if (empty($cfg['enabled'])) return false; // feature off entirely -> not tracked
        $to = trim((string)($cfg['adminEmail'] ?? ''));
        if ($to === '') {
            if ($logResult) omcLog($order, $type, '', '', 'pending', 'No admin recipient configured (adminEmail is blank).');
            return false;
        }

        // SMTP: admin-panel values win; fall back to config.php constants when blank.
        $host     = trim((string)($cfg['smtpHost'] ?? '')) ?: SMTP_HOST;
        $port     = (int)($cfg['smtpPort'] ?? 0) ?: SMTP_PORT;
        $user     = trim((string)($cfg['smtpUser'] ?? '')) ?: SMTP_USER;
        $pass     = (string)($cfg['smtpPass'] ?? '') !== '' ? (string)$cfg['smtpPass'] : SMTP_PASS;
        $from     = trim((string)($cfg['fromEmail'] ?? '')) ?: SMTP_FROM;
        $fromName = trim((string)($cfg['fromName'] ?? '')) ?: SMTP_FROM_NAME;
        if ($user === '' || $pass === '') { // no credentials -> cannot send (yet)
            if ($logResult) omcLog($order, $type, $to, '', 'pending', 'SMTP credentials missing (smtpUser / smtpPass blank and no config.php fallback).');
            return false;
        }

        $vars    = omcVars($order, $items, $customer, $type);
        $subjTpl = $type === 'failed' ? (string)($cfg['failedSubject'] ?? '') : (string)($cfg['successSubject'] ?? '');
        $bodyTpl = $type === 'failed' ? (string)($cfg['failedBody'] ?? '')    : (string)($cfg['successBody'] ?? '');
        if (trim($subjTpl) === '') $subjTpl = omcDefaultSubject($type);
        if (trim($bodyTpl) === '') $bodyTpl = omcDefaultBody($type);
        $subject = omcRender($subjTpl, $vars);
        $body    = omcRender($bodyTpl, $vars);

        $okAny = false; $errors = [];
        foreach (preg_split('/[,;]+/', $to) as $rcpt) {
            $rcpt = trim($rcpt);
            if ($rcpt === '') continue;
            try {
                $ok = omcSmtpSendHtml($host, $port, $user, $pass, $from, $fromName, $rcpt, $subject, $body);
                $okAny = $ok || $okAny;
                if (!$ok) $errors[] = $rcpt . ': server did not confirm delivery (250 expected).';
            } catch (Throwable $e) {
                $errors[] = $rcpt . ': ' . $e->getMessage();
                error_log('orderMail send ' . $rcpt . ': ' . $e->getMessage());
            }
        }
        if ($logResult) {
            omcLog($order, $type, $to, $subject, $okAny ? 'sent' : 'failed', $errors ? implode(' | ', $errors) : null);
        }
        return $okAny;
    } catch (Throwable $e) {
        error_log('omcSendWithConfig: ' . $e->getMessage());
        if ($logResult) omcLog($order, $type, trim((string)($cfg['adminEmail'] ?? '')), '', 'failed', $e->getMessage());
        return false;
    }
}

// Public entry for the live hooks: load the saved config and send. type = 'placed' | 'failed'.
function sendOrderAdminMail(array $order, array $items, ?array $customer, string $type): bool {
    return omcSendWithConfig(orderMailConfig(), $order, $items, $customer, $type);
}

// Re-send the admin notification for an existing order (admin "Retry" button). Reloads the
// order + items + customer from the DB so the email is rebuilt from current data, then sends
// via the live config (which updates the same order_mail_log row). type = 'placed' | 'failed'.
function resendOrderAdminMail(int $orderId, string $type): bool {
    try {
        $db    = db();
        $order = $db->fetchOne("SELECT * FROM orders WHERE id=?", [$orderId]);
        if (!$order) return false;
        $items = $db->fetchAll("SELECT * FROM order_items WHERE order_id=?", [$orderId]);
        $customer = !empty($order['customer_id'])
            ? $db->fetchOne("SELECT * FROM customers WHERE id=?", [$order['customer_id']])
            : null;
        return sendOrderAdminMail($order, $items, $customer ?: null, $type === 'failed' ? 'failed' : 'placed');
    } catch (Throwable $e) {
        error_log('resendOrderAdminMail: ' . $e->getMessage());
        return false;
    }
}

// A representative fake order used by the admin "Send test email" button so the templates
// render with realistic data WITHOUT touching a real order or the delivery log.
function omcSampleOrder(): array {
    return [
        'order' => [
            'id'               => 0,
            'order_number'     => 'TEST-0000',
            'created_at'       => date('Y-m-d H:i:s'),
            'payment_method'   => 'razorpay',
            'payment_status'   => 'paid',
            'shipping_address' => json_encode([
                'name' => 'Test Customer', 'phone' => '9000000000',
                'address' => '123 Demo Street', 'city' => 'Ahmedabad',
                'state' => 'Gujarat', 'pincode' => '380001',
            ]),
            'subtotal' => 1000, 'discount' => 100, 'shipping_charge' => 50, 'tax' => 45, 'total' => 995,
        ],
        'items' => [
            ['product_name' => 'Sample Dental Kit', 'variant' => 'Standard', 'line_type' => 'normal', 'quantity' => 2, 'price' => 500],
            ['product_name' => 'Free Gift Item',    'variant' => '',         'line_type' => 'gift',   'quantity' => 1, 'price' => 0],
        ],
        'customer' => ['name' => 'Test Customer', 'email' => 'test@example.com', 'phone' => '9000000000'],
    ];
}

// Admin "Send test email": sends a sample to the FIRST configured recipient and returns the
// PRECISE outcome { ok, message } so the admin sees the real reason on failure (blank creds vs.
// auth rejected vs. connection refused) instead of a generic "send failed". No log row written.
function omcSendTest(array $cfg, string $type): array {
    if (empty($cfg['enabled'])) return ['ok' => false, 'message' => 'Enable order emails first, then Save, then Test.'];
    $to = trim((string)($cfg['adminEmail'] ?? ''));
    if ($to === '') return ['ok' => false, 'message' => 'Enter an Admin recipient email first.'];

    $host     = trim((string)($cfg['smtpHost'] ?? '')) ?: SMTP_HOST;
    $port     = (int)($cfg['smtpPort'] ?? 0) ?: SMTP_PORT;
    $user     = trim((string)($cfg['smtpUser'] ?? '')) ?: SMTP_USER;
    $pass     = (string)($cfg['smtpPass'] ?? '') !== '' ? (string)$cfg['smtpPass'] : SMTP_PASS;
    $from     = trim((string)($cfg['fromEmail'] ?? '')) ?: SMTP_FROM;
    $fromName = trim((string)($cfg['fromName'] ?? '')) ?: SMTP_FROM_NAME;
    if ($user === '' || $pass === '') {
        return ['ok' => false, 'message' => 'SMTP Username and Password are required — both are blank. Enter the sending mailbox (e.g. a Gmail address) and its App Password.'];
    }

    $s    = omcSampleOrder();
    $vars = omcVars($s['order'], $s['items'], $s['customer'], $type);
    $subjTpl = $type === 'failed' ? (string)($cfg['failedSubject'] ?? '') : (string)($cfg['successSubject'] ?? '');
    $bodyTpl = $type === 'failed' ? (string)($cfg['failedBody'] ?? '')    : (string)($cfg['successBody'] ?? '');
    if (trim($subjTpl) === '') $subjTpl = omcDefaultSubject($type);
    if (trim($bodyTpl) === '') $bodyTpl = omcDefaultBody($type);
    $subject  = omcRender($subjTpl, $vars);
    $body     = omcRender($bodyTpl, $vars);
    $firstTo  = trim((preg_split('/[,;]+/', $to)[0]) ?? '');

    try {
        $ok = omcSmtpSendHtml($host, $port, $user, $pass, $from, $fromName, $firstTo, $subject, $body);
        return $ok
            ? ['ok' => true,  'message' => "Test email sent to {$firstTo}."]
            : ['ok' => false, 'message' => "Connected to {$host}, but the server did not confirm delivery (250 expected)."];
    } catch (Throwable $e) {
        $msg  = $e->getMessage();
        $hint = (stripos($msg, 'auth') !== false)
            ? ' — for Gmail use a 16-char App Password (Google Account → Security → App passwords), not your normal login password.'
            : (stripos($msg, 'connect') !== false ? ' — check the SMTP host/port and that outbound port is open.' : '');
        return ['ok' => false, 'message' => "SMTP error: {$msg}{$hint}"];
    }
}

// Raw-SMTP HTML send over STARTTLS. Mirrors otp_sender::smtpSend but sends an HTML body and
// a UTF-8 MIME-encoded subject so emoji (✅ / ❌) render in the subject line.
function omcSmtpSendHtml($host, $port, $user, $pass, $from, $fromName, $to, $subject, $htmlBody): bool {
    $fp = stream_socket_client("tcp://$host:$port", $errno, $errstr, 20);
    if (!$fp) throw new Exception("connect failed: $errstr");
    $read = function () use ($fp) { return fgets($fp, 515); };
    $cmd  = function ($c) use ($fp) { fputs($fp, $c . "\r\n"); };
    $read();
    $cmd("EHLO localhost"); while (($l = $read()) && isset($l[3]) && $l[3] === '-') {}
    $cmd("STARTTLS"); $read();
    stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $cmd("EHLO localhost"); while (($l = $read()) && isset($l[3]) && $l[3] === '-') {}
    $cmd("AUTH LOGIN"); $read();
    $cmd(base64_encode($user)); $read();
    $cmd(base64_encode($pass)); $resp = $read();
    if (strpos((string)$resp, '235') !== 0) throw new Exception('SMTP auth failed');
    $cmd("MAIL FROM:<$from>"); $read();
    $cmd("RCPT TO:<$to>"); $read();
    $cmd("DATA"); $read();
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = "From: $fromName <$from>\r\nTo: <$to>\r\nSubject: $encSubject\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    // SMTP dot-stuffing: lines beginning with '.' must be doubled.
    $safeBody = preg_replace('/^\./m', '..', $htmlBody);
    $cmd($headers . "\r\n" . $safeBody . "\r\n."); $resp = $read();
    $cmd("QUIT"); fclose($fp);
    return strpos((string)$resp, '250') === 0;
}
