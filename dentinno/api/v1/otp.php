<?php
// OTP request + verify with rate limiting (OTP_MAX_ATTEMPTS -> OTP_BLOCK_MINUTES block).
// POST /api/v1/otp.php?action=request  {mobile}   (or {email} when OTP_CHANNEL=email)
// POST /api/v1/otp.php?action=verify   {mobile, otp}
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../includes/otp_sender.php';
require_once __DIR__ . '/../../includes/whatsapp_sender.php'; // enables WhatsApp OTP routing in deliverOtp()

$db = db();
$action = qstr('action');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jsonErr('POST required', 405);
$body = jsonBody();

// Identifier depends on channel.
$identifier = OTP_CHANNEL === 'email'
    ? strtolower(trim((string)($body['email'] ?? '')))
    : trim((string)($body['mobile'] ?? ''));

if ($identifier === '') jsonErr(OTP_CHANNEL === 'email' ? 'Email required' : 'Mobile required', 422);
if (OTP_CHANNEL === 'sms' && !preg_match('/^[6-9]\d{9}$/', $identifier)) {
    jsonErr('Enter a valid 10-digit mobile number.', 422);
}
if (OTP_CHANNEL === 'email' && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
    jsonErr('Enter a valid email.', 422);
}

$now = new DateTime();
$row = $db->fetchOne("SELECT * FROM otp_codes WHERE identifier=?", [$identifier]);

// --- check block ---
if ($row && $row['blocked_until'] && new DateTime($row['blocked_until']) > $now) {
    $mins = ceil((strtotime($row['blocked_until']) - time()) / 60);
    jsonErr("Too many attempts. Try again in $mins minute(s).", 429);
}

// =================== REQUEST (send OTP) ===================
if ($action === 'request') {
    // resend cooldown
    if ($row && $row['last_sent_at'] && (time() - strtotime($row['last_sent_at'])) < OTP_RESEND_COOLDOWN) {
        $wait = OTP_RESEND_COOLDOWN - (time() - strtotime($row['last_sent_at']));
        jsonErr("Please wait {$wait}s before requesting another OTP.", 429);
    }

    // Sending a fresh OTP does NOT consume verify attempts — it resets them.
    // Only WRONG verify attempts count toward the block (OTP_MAX_ATTEMPTS wrong = block).
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = password_hash($otp, PASSWORD_DEFAULT);
    $expires = (clone $now)->modify('+' . OTP_TTL . ' seconds')->format('Y-m-d H:i:s');
    $sentAt  = $now->format('Y-m-d H:i:s');

    if ($row) {
        $db->execute(
            "UPDATE otp_codes SET channel=?, otp_hash=?, expires_at=?, attempts=0, verified=0, last_sent_at=?, blocked_until=NULL WHERE identifier=?",
            [OTP_CHANNEL, $hash, $expires, $sentAt, $identifier]
        );
    } else {
        $db->insert(
            "INSERT INTO otp_codes (identifier, channel, otp_hash, expires_at, attempts, last_sent_at) VALUES (?,?,?,?,0,?)",
            [$identifier, OTP_CHANNEL, $hash, $expires, $sentAt]
        );
    }

    // deliver
    $send = deliverOtp($identifier, $otp);
    // In dev mode, delivery failure is non-fatal — OTP is shown on screen instead.
    $devMode = OTP_DEV_RETURN;
    $resp = [
        'success'       => true,
        'sent'          => $send['ok'],
        'channel'       => OTP_CHANNEL,
        'devMode'       => $devMode,
        'attemptsLeft'  => OTP_MAX_ATTEMPTS,
        'message'       => $send['ok']
            ? ('OTP sent via ' . OTP_CHANNEL)
            : ($devMode ? 'Demo mode — OTP shown on screen' : ('Delivery failed: ' . ($send['error'] ?? 'unknown'))),
    ];
    if ($devMode || !$send['ok']) $resp['devOtp'] = $otp;
    jsonOut($resp);
}

// =================== VERIFY ===================
if ($action === 'verify') {
    $otp = trim((string)($body['otp'] ?? ''));
    if ($otp === '') jsonErr('OTP required', 422);
    if (!$row) jsonErr('Request an OTP first.', 400);

    // expired?
    if (new DateTime($row['expires_at']) < $now) {
        jsonErr('OTP expired. Request a new one.', 400);
    }

    if (password_verify($otp, $row['otp_hash'])) {
        // success — clear attempts, mark verified
        $db->execute("UPDATE otp_codes SET verified=1, attempts=0, blocked_until=NULL WHERE identifier=?", [$identifier]);
        jsonOut(['success' => true, 'verified' => true, 'message' => 'OTP verified']);
    }

    // wrong OTP -> count attempt, maybe block
    $attempts = (int)$row['attempts'] + 1;
    if ($attempts >= OTP_MAX_ATTEMPTS) {
        $blockUntil = (clone $now)->modify('+' . OTP_BLOCK_MINUTES . ' minutes')->format('Y-m-d H:i:s');
        $db->execute("UPDATE otp_codes SET attempts=?, blocked_until=? WHERE identifier=?", [$attempts, $blockUntil, $identifier]);
        jsonErr('Too many wrong attempts. Try again in ' . OTP_BLOCK_MINUTES . ' minutes.', 429);
    }
    $db->execute("UPDATE otp_codes SET attempts=? WHERE identifier=?", [$attempts, $identifier]);
    jsonErr('Invalid OTP. ' . (OTP_MAX_ATTEMPTS - $attempts) . ' attempt(s) left.', 401);
}

jsonErr('Unknown action', 400);
