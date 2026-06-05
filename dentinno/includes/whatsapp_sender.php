<?php
// WhatsApp automated notifications via Meta WhatsApp Cloud API (direct, no BSP).
// Mirrors includes/otp_sender.php: DB-backed config (site_settings.whatsappConfig,
// super-admin protected), the shared cURL helper otpHttpPost(), and mobile normalization.
//
// All sends are BEST-EFFORT: callers must wrap in try/catch so a WhatsApp failure
// never breaks order placement / payment / OTP. When disabled or unconfigured every
// function returns ['ok'=>false, 'error'=>'...'] without throwing.
//
// Templates must be pre-created + approved in Meta (WhatsApp Manager). The admin enters
// the approved template NAME in Settings -> WhatsApp; body params are passed in a FIXED
// order that MUST match the template placeholders {{1}}, {{2}}, ...
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/otp_sender.php'; // reuse otpHttpPost(), normalizeMobileIN()

// ---------------------------------------------------------------------------
// Resolve WhatsApp config from DB (site_settings.whatsappConfig), merged over
// defaults. DB non-empty values win. Tolerant of a missing table / bad JSON.
// ---------------------------------------------------------------------------
function getWhatsAppConfig(): array {
    $defaults = [
        'enabled'        => false,
        'accessToken'    => '',        // permanent System User token (secret)
        'phoneNumberId'  => '',        // Cloud API phone number ID
        'apiVersion'     => 'v21.0',
        'languageCode'   => 'en_US',
        'otpViaWhatsApp' => false,     // route login OTP over WhatsApp instead of SMS
        'otpSmsFallback' => true,      // if WA OTP fails, fall back to SMS
        'templates'      => [
            'orderPlaced'    => '',    // UTILITY
            'paymentSuccess' => '',    // UTILITY
            'orderStatus'    => '',    // UTILITY (tracking)
            'otp'            => '',    // AUTHENTICATION (copy-code button)
        ],
    ];
    try {
        $row = db()->fetchOne("SELECT svalue FROM site_settings WHERE skey='whatsappConfig'");
        if ($row && !empty($row['svalue'])) {
            $cfg = json_decode($row['svalue'], true);
            if (is_array($cfg)) {
                foreach (['enabled', 'accessToken', 'phoneNumberId', 'apiVersion', 'languageCode', 'otpViaWhatsApp', 'otpSmsFallback'] as $k) {
                    if (array_key_exists($k, $cfg) && $cfg[$k] !== '') $defaults[$k] = $cfg[$k];
                }
                if (!empty($cfg['templates']) && is_array($cfg['templates'])) {
                    foreach ($defaults['templates'] as $k => $v) {
                        if (isset($cfg['templates'][$k]) && $cfg['templates'][$k] !== '') {
                            $defaults['templates'][$k] = $cfg['templates'][$k];
                        }
                    }
                }
            }
        }
    } catch (Throwable $t) { /* table missing / bad json -> defaults */ }
    // normalize booleans (JSON may store them as 0/1/"true")
    $defaults['enabled']        = filter_var($defaults['enabled'], FILTER_VALIDATE_BOOLEAN);
    $defaults['otpViaWhatsApp'] = filter_var($defaults['otpViaWhatsApp'], FILTER_VALIDATE_BOOLEAN);
    $defaults['otpSmsFallback'] = filter_var($defaults['otpSmsFallback'], FILTER_VALIDATE_BOOLEAN);
    return $defaults;
}

// Is WhatsApp usable? (enabled + token + phone number id present)
function waConfigured(array $cfg): bool {
    return !empty($cfg['enabled']) && $cfg['accessToken'] !== '' && $cfg['phoneNumberId'] !== '';
}

// ---------------------------------------------------------------------------
// Generic template sender. Returns ['ok'=>bool, 'error'=>string|null].
// $bodyParams: ordered list of strings for the template body ({{1}}..{{n}}).
// $extraComponents: raw component objects (used by OTP for the button), merged after body.
// ---------------------------------------------------------------------------
function sendWhatsAppTemplate(string $mobile, string $templateName, array $bodyParams = [], array $extraComponents = []): array {
    $cfg = getWhatsAppConfig();
    if (!waConfigured($cfg))     return ['ok' => false, 'error' => 'WhatsApp not configured'];
    if ($templateName === '')    return ['ok' => false, 'error' => 'WhatsApp template name missing'];

    $to = normalizeMobileIN($mobile, true); // 91XXXXXXXXXX (no +)

    $components = [];
    if (!empty($bodyParams)) {
        $components[] = [
            'type'       => 'body',
            'parameters' => array_map(fn($t) => ['type' => 'text', 'text' => (string)$t], $bodyParams),
        ];
    }
    if (!empty($extraComponents)) {
        $components = array_merge($components, $extraComponents);
    }

    $template = ['name' => $templateName, 'language' => ['code' => $cfg['languageCode']]];
    if (!empty($components)) $template['components'] = $components;

    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'template',
        'template'          => $template,
    ];

    $url = 'https://graph.facebook.com/' . rawurlencode($cfg['apiVersion']) . '/' . rawurlencode($cfg['phoneNumberId']) . '/messages';
    $r = otpHttpPost($url, $payload, [
        'Authorization: Bearer ' . $cfg['accessToken'],
        'Content-Type: application/json',
    ], false); // $form=false -> JSON

    if ($r['err']) return ['ok' => false, 'error' => 'WhatsApp gateway error: ' . $r['err'], 'wamid' => null];

    $json = json_decode($r['body'], true);
    if ($r['code'] === 200 && !empty($json['messages'][0]['id'])) {
        return ['ok' => true, 'error' => null, 'wamid' => $json['messages'][0]['id']];
    }
    $msg = $json['error']['message'] ?? ('WhatsApp send failed (HTTP ' . $r['code'] . ')');
    return ['ok' => false, 'error' => $msg, 'wamid' => null];
}

// ---------------------------------------------------------------------------
// Event wrappers. Param ORDER must match the approved template placeholders.
// Each logs best-effort and returns ['ok','error'].
// ---------------------------------------------------------------------------

// orderPlaced: {{1}} name, {{2}} order#, {{3}} total, {{4}} item count
function waOrderPlaced(array $cust, array $order, array $items = []): array {
    $cfg = getWhatsAppConfig();
    if (empty($cust['phone'])) return ['ok' => false, 'error' => 'no phone'];
    $params = [
        $cust['name'] ?? 'Customer',
        $order['order_number'] ?? '',
        number_format((float)($order['total'] ?? 0), 2),
        (string)count($items),
    ];
    $res = sendWhatsAppTemplate($cust['phone'], $cfg['templates']['orderPlaced'], $params);
    waLog('order_placed', $cust['phone'], $cfg['templates']['orderPlaced'], $order['id'] ?? null, $res);
    return $res;
}

// paymentSuccess: {{1}} name, {{2}} order#, {{3}} amount
function waPaymentSuccess(array $cust, array $order): array {
    $cfg = getWhatsAppConfig();
    if (empty($cust['phone'])) return ['ok' => false, 'error' => 'no phone'];
    $params = [
        $cust['name'] ?? 'Customer',
        $order['order_number'] ?? '',
        number_format((float)($order['total'] ?? 0), 2),
    ];
    $res = sendWhatsAppTemplate($cust['phone'], $cfg['templates']['paymentSuccess'], $params);
    waLog('payment_success', $cust['phone'], $cfg['templates']['paymentSuccess'], $order['id'] ?? null, $res);
    return $res;
}

// orderStatus: {{1}} name, {{2}} order#, {{3}} status, {{4}} courier, {{5}} tracking
function waOrderStatus(array $cust, array $order, string $status, string $tracking = '', string $courier = ''): array {
    $cfg = getWhatsAppConfig();
    if (empty($cust['phone'])) return ['ok' => false, 'error' => 'no phone'];
    $params = [
        $cust['name'] ?? 'Customer',
        $order['order_number'] ?? '',
        ucfirst($status),
        $courier !== '' ? $courier : '-',
        $tracking !== '' ? $tracking : '-',
    ];
    $res = sendWhatsAppTemplate($cust['phone'], $cfg['templates']['orderStatus'], $params);
    waLog('order_status', $cust['phone'], $cfg['templates']['orderStatus'], $order['id'] ?? null, $res);
    return $res;
}

// OTP: AUTHENTICATION template. The code goes in the body param AND the copy-code URL button.
function waSendOtp(string $mobile, string $otp): array {
    $cfg = getWhatsAppConfig();
    $tpl = $cfg['templates']['otp'];
    if ($tpl === '') return ['ok' => false, 'error' => 'WhatsApp OTP template not set'];
    $components = [
        ['type' => 'body',   'parameters' => [['type' => 'text', 'text' => $otp]]],
        ['type' => 'button', 'sub_type' => 'url', 'index' => '0',
         'parameters' => [['type' => 'text', 'text' => $otp]]],
    ];
    $res = sendWhatsAppTemplate($mobile, $tpl, [], $components);
    waLog('otp', $mobile, $tpl, null, $res);
    return $res;
}

// Best-effort log into whatsapp_logs (own try/catch; no-op if table missing).
function waLog(string $event, string $recipient, ?string $template, $orderId, array $res): void {
    // Don't log when WhatsApp simply isn't in play (disabled/unconfigured/no phone) —
    // only log real attempts (sent, or genuine send failures from Meta).
    $ok = !empty($res['ok']);
    $noop = ['WhatsApp not configured', 'WhatsApp template name missing', 'WhatsApp OTP template not set', 'no phone'];
    if (!$ok && in_array($res['error'] ?? '', $noop, true)) return;
    try {
        db()->execute(
            "INSERT INTO whatsapp_logs (event, recipient, template, order_id, wa_message_id, status, error)
             VALUES (?,?,?,?,?,?,?)",
            [
                $event,
                normalizeMobileIN($recipient, true),
                $template ?: null,
                $orderId ? (int)$orderId : null,
                $res['wamid'] ?? null,
                !empty($res['ok']) ? 'sent' : 'failed',
                $res['ok'] ? null : ($res['error'] ?? 'unknown'),
            ]
        );
    } catch (Throwable $t) { /* logging is non-critical */ }
}
