<?php
// OTP delivery — SMS (Fast2SMS) and Email (SMTP). Channel chosen via OTP_CHANNEL.
require_once __DIR__ . '/config.php';

// Returns ['ok'=>bool, 'error'=>string|null]
function sendOtpSms(string $mobile, string $otp): array {
    if (FAST2SMS_API_KEY === '') {
        return ['ok' => false, 'error' => 'SMS not configured (missing Fast2SMS key)'];
    }
    // Route-specific payload:
    //  'otp' -> needs account/website verification, uses variables_values
    //  'q'   -> Quick SMS, works without DLT/verification, uses message text
    if (FAST2SMS_ROUTE === 'q') {
        $payload = [
            'route'   => 'q',
            'message' => "Your OTP is $otp. Valid for " . (OTP_TTL / 60) . " min. - Smart Dental Innovations",
            'numbers' => $mobile,
            'flash'   => 0,
        ];
    } else {
        $payload = [
            'route'            => FAST2SMS_ROUTE, // 'otp'
            'variables_values' => $otp,
            'numbers'          => $mobile,
        ];
    }
    $ch = curl_init('https://www.fast2sms.com/dev/bulkV2');
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_HTTPHEADER => [
            'authorization: ' . FAST2SMS_API_KEY,
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT => 20,
    ];
    // SSL: local dev networks (AV/proxy MITM) often break cert chain.
    // OTP_SSL_INSECURE=true relaxes verify for local dev; use cacert + false in prod.
    $caBundle = __DIR__ . '/cacert.pem';
    if (defined('OTP_SSL_INSECURE') && OTP_SSL_INSECURE) {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
    } elseif (file_exists($caBundle)) {
        $opts[CURLOPT_CAINFO] = $caBundle;
    }
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) return ['ok' => false, 'error' => "SMS gateway error: $err"];
    $json = json_decode($res, true);
    if ($code === 200 && !empty($json['return'])) {
        return ['ok' => true, 'error' => null];
    }
    return ['ok' => false, 'error' => $json['message'] ?? ('SMS failed (HTTP ' . $code . ')')];
}

// Minimal SMTP send (no external lib) for Email OTP.
function sendOtpEmail(string $email, string $otp): array {
    if (SMTP_USER === '' || SMTP_PASS === '') {
        return ['ok' => false, 'error' => 'Email not configured (missing SMTP creds)'];
    }
    $subject = 'Your verification code';
    $body = "Your OTP is: $otp\n\nValid for " . (OTP_TTL / 60) . " minutes.\nIf you didn't request this, ignore this email.";
    try {
        $ok = smtpSend(SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM, SMTP_FROM_NAME, $email, $subject, $body);
        return $ok ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => 'SMTP send failed'];
    } catch (Throwable $t) {
        return ['ok' => false, 'error' => 'Email error: ' . $t->getMessage()];
    }
}

// Dispatch by configured channel. $identifier is mobile (sms) or email (email).
function deliverOtp(string $identifier, string $otp): array {
    if (OTP_CHANNEL === 'email') return sendOtpEmail($identifier, $otp);
    return sendOtpSms($identifier, $otp);
}

// --- tiny SMTP client (STARTTLS) ---
function smtpSend($host, $port, $user, $pass, $from, $fromName, $to, $subject, $body): bool {
    $fp = stream_socket_client("tcp://$host:$port", $errno, $errstr, 20);
    if (!$fp) throw new Exception("connect failed: $errstr");
    $read = function () use ($fp) { return fgets($fp, 515); };
    $cmd  = function ($c) use ($fp) { fputs($fp, $c . "\r\n"); };
    $read();
    $cmd("EHLO localhost"); while (($l = $read()) && $l[3] === '-') {}
    $cmd("STARTTLS"); $read();
    stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $cmd("EHLO localhost"); while (($l = $read()) && $l[3] === '-') {}
    $cmd("AUTH LOGIN"); $read();
    $cmd(base64_encode($user)); $read();
    $cmd(base64_encode($pass)); $resp = $read();
    if (strpos($resp, '235') !== 0) throw new Exception('auth failed');
    $cmd("MAIL FROM:<$from>"); $read();
    $cmd("RCPT TO:<$to>"); $read();
    $cmd("DATA"); $read();
    $headers = "From: $fromName <$from>\r\nTo: <$to>\r\nSubject: $subject\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $cmd($headers . "\r\n" . $body . "\r\n."); $resp = $read();
    $cmd("QUIT"); fclose($fp);
    return strpos($resp, '250') === 0;
}
