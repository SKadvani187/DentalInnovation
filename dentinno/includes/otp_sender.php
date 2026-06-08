<?php
// OTP delivery — SMS (Fast2SMS / 2Factor / MSG91) and Email (SMTP).
// SMS provider is chosen in Admin → Settings → OTP (super-admin), stored in site_settings.otpConfig.
// Falls back to FAST2SMS_* constants in config.php when otpConfig is empty.
require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// Resolve the active OTP provider config from DB (site_settings.otpConfig),
// merged over constant-based defaults. DB non-empty values win.
// ---------------------------------------------------------------------------
function getOtpConfig(): array {
    $defaults = [
        'provider'  => 'fast2sms',
        'fast2sms'  => ['apiKey' => FAST2SMS_API_KEY, 'route' => FAST2SMS_ROUTE, 'senderId' => FAST2SMS_SENDER_ID, 'message' => ''],
        'twofactor' => ['apiKey' => '', 'senderId' => '', 'templateName' => ''],
        'msg91'     => ['authKey' => '', 'senderId' => '', 'templateId' => ''],
    ];
    try {
        $row = db()->fetchOne("SELECT svalue FROM site_settings WHERE skey='otpConfig'");
        if ($row && !empty($row['svalue'])) {
            $cfg = json_decode($row['svalue'], true);
            if (is_array($cfg)) {
                $allowed = ['fast2sms', 'twofactor', 'msg91'];
                if (!empty($cfg['provider']) && in_array($cfg['provider'], $allowed, true)) {
                    $defaults['provider'] = $cfg['provider'];
                }
                foreach (['fast2sms', 'twofactor', 'msg91'] as $p) {
                    if (!empty($cfg[$p]) && is_array($cfg[$p])) {
                        foreach ($defaults[$p] as $k => $v) {
                            if (isset($cfg[$p][$k]) && $cfg[$p][$k] !== '') $defaults[$p][$k] = $cfg[$p][$k];
                        }
                    }
                }
            }
        }
    } catch (Throwable $t) { /* table missing / bad json -> use defaults */ }
    return $defaults;
}

// Normalize an Indian mobile: strip non-digits, drop a leading 91/0, return bare 10 digits.
// $withCC=true returns the 91-prefixed form (some gateways require the country code).
function normalizeMobileIN(string $m, bool $withCC = false): string {
    $d = preg_replace('/\D+/', '', $m);
    if (strlen($d) === 12 && strpos($d, '91') === 0) $d = substr($d, 2);
    elseif (strlen($d) === 11 && $d[0] === '0')      $d = substr($d, 1);
    return $withCC ? ('91' . $d) : $d;
}

// Shared HTTP POST with the project's SSL handling (relaxed for local dev, cacert in prod).
// Returns ['code'=>int, 'body'=>string, 'err'=>string].
function otpHttpPost(string $url, $payload, array $headers = [], bool $form = true): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $form ? (is_array($payload) ? http_build_query($payload) : $payload)
                                        : (is_array($payload) ? json_encode($payload) : $payload),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
    ];
    $caBundle = __DIR__ . '/cacert.pem';
    if (defined('OTP_SSL_INSECURE') && OTP_SSL_INSECURE) {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
    } elseif (file_exists($caBundle)) {
        $opts[CURLOPT_CAINFO] = $caBundle;
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => (int)$code, 'body' => (string)$body, 'err' => (string)$err];
}

// ---------------------------------------------------------------------------
// SMS dispatcher — picks the provider from getOtpConfig(). All adapters return
// ['ok'=>bool, 'error'=>string|null].
// ---------------------------------------------------------------------------
function sendOtpSms(string $mobile, string $otp): array {
    $cfg = getOtpConfig();
    switch ($cfg['provider']) {
        case 'twofactor': return sendSms_twofactor($mobile, $otp, $cfg['twofactor']);
        case 'msg91':     return sendSms_msg91($mobile, $otp, $cfg['msg91']);
        case 'fast2sms':
        default:          return sendSms_fast2sms($mobile, $otp, $cfg['fast2sms']);
    }
}

// --- Fast2SMS ---
function sendSms_fast2sms(string $mobile, string $otp, array $c): array {
    if (empty($c['apiKey'])) return ['ok' => false, 'error' => 'Fast2SMS not configured (missing API key)'];
    $num = normalizeMobileIN($mobile);
    $route = $c['route'] ?: 'otp';
    if ($route === 'q') {
        // Quick (non-DLT) route: message text is admin-editable (Admin → Settings → OTP →
        // Fast2SMS → Message Template). Placeholders {otp} and {mins} are filled in here.
        // Blank template falls back to the default wording. (The 'otp'/DLT route below
        // can't use this — its text lives in the provider's DLT-approved template.)
        $tpl = trim((string)($c['message'] ?? ''));
        if ($tpl === '') $tpl = 'Your OTP is {otp}. Valid for {mins} min. - Smart Dental Innovations';
        $message = strtr($tpl, ['{otp}' => $otp, '{mins}' => (string)(OTP_TTL / 60)]);
        $payload = [
            'route'   => 'q',
            'message' => $message,
            'numbers' => $num,
            'flash'   => 0,
        ];
    } else {
        $payload = ['route' => $route, 'variables_values' => $otp, 'numbers' => $num];
    }
    $r = otpHttpPost('https://www.fast2sms.com/dev/bulkV2', $payload, [
        'authorization: ' . $c['apiKey'],
        'Content-Type: application/x-www-form-urlencoded',
    ], true);
    if ($r['err']) return ['ok' => false, 'error' => "SMS gateway error: {$r['err']}"];
    $json = json_decode($r['body'], true);
    if ($r['code'] === 200 && !empty($json['return'])) return ['ok' => true, 'error' => null];
    return ['ok' => false, 'error' => $json['message'] ?? ('SMS failed (HTTP ' . $r['code'] . ')')];
}

// --- 2Factor.in (transactional SMS; our locally-generated OTP carried in VAR1) ---
function sendSms_twofactor(string $mobile, string $otp, array $c): array {
    if (empty($c['apiKey']))       return ['ok' => false, 'error' => '2Factor not configured (missing API key)'];
    if (empty($c['templateName'])) return ['ok' => false, 'error' => '2Factor not configured (missing template name)'];
    $num = normalizeMobileIN($mobile);
    $url = 'https://2factor.in/API/V1/' . rawurlencode($c['apiKey']) . '/ADDON_SERVICES/SEND/TSMS';
    $payload = [
        'From'         => $c['senderId'],
        'To'           => $num,
        'TemplateName' => $c['templateName'],
        'VAR1'         => $otp,
    ];
    $r = otpHttpPost($url, $payload, ['Content-Type: application/x-www-form-urlencoded'], true);
    if ($r['err']) return ['ok' => false, 'error' => "SMS gateway error: {$r['err']}"];
    $json = json_decode($r['body'], true);
    if ($r['code'] === 200 && isset($json['Status']) && $json['Status'] === 'Success') {
        return ['ok' => true, 'error' => null];
    }
    return ['ok' => false, 'error' => $json['Details'] ?? ('2Factor failed (HTTP ' . $r['code'] . ')')];
}

// --- MSG91 (Flow API; OTP carried as a template variable) ---
function sendSms_msg91(string $mobile, string $otp, array $c): array {
    if (empty($c['authKey']))    return ['ok' => false, 'error' => 'MSG91 not configured (missing auth key)'];
    if (empty($c['templateId'])) return ['ok' => false, 'error' => 'MSG91 not configured (missing template id)'];
    $num = normalizeMobileIN($mobile, true); // 91-prefixed
    $payload = [
        'template_id' => $c['templateId'],
        'short_url'   => 0,
        'recipients'  => [['mobiles' => $num, 'var1' => $otp, 'otp' => $otp]],
    ];
    if (!empty($c['senderId'])) $payload['sender'] = $c['senderId'];
    $r = otpHttpPost('https://control.msg91.com/api/v5/flow/', $payload, [
        'authkey: ' . $c['authKey'],
        'Content-Type: application/json',
        'Accept: application/json',
    ], false);
    if ($r['err']) return ['ok' => false, 'error' => "SMS gateway error: {$r['err']}"];
    $json = json_decode($r['body'], true);
    if ($r['code'] === 200 && isset($json['type']) && $json['type'] === 'success') {
        return ['ok' => true, 'error' => null];
    }
    return ['ok' => false, 'error' => $json['message'] ?? ('MSG91 failed (HTTP ' . $r['code'] . ')')];
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

    // WhatsApp OTP routing (config-gated; SMS remains the default channel).
    // whatsapp_sender.php is loaded by api/v1/otp.php; guard so this stays safe if not.
    if (function_exists('getWhatsAppConfig')) {
        $wa = getWhatsAppConfig();
        if (!empty($wa['enabled']) && !empty($wa['otpViaWhatsApp']) && !empty($wa['templates']['otp'])) {
            $r = waSendOtp($identifier, $otp);
            if (!empty($r['ok'])) return ['ok' => true, 'error' => null];
            if (empty($wa['otpSmsFallback'])) return ['ok' => false, 'error' => $r['error'] ?? 'WhatsApp OTP failed'];
            // else fall through to SMS fallback
        }
    }
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
