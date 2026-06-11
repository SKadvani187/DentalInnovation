<?php
// GET /api/v1/settings.php -> all site settings as one object { company, payments, ... featured, premiumCategories }
require_once __DIR__ . '/_bootstrap.php';

$rows = db()->fetchAll("SELECT skey, svalue FROM site_settings");
// Private keys that must never be exposed to the public storefront (contain secrets / admin-only).
// whatsappConfig holds the Meta WhatsApp permanent access token — leaking it = account takeover.
$PRIVATE = ['otpConfig', 'whatsappConfig'];
$out = [];
foreach ($rows as $r) {
    if (in_array($r['skey'], $PRIVATE, true)) continue;
    $out[$r['skey']] = jcol($r['svalue'] ?? null, null);
}
// Live counts (real-time, not stored) — used by trust badges "Live count".
$out['liveCounts'] = [
    'products' => (int)(db()->fetchOne("SELECT COUNT(*) c FROM products WHERE is_active=1")['c'] ?? 0),
];

// priceBounds is AUTO-derived from live products (storefront price filter slider range),
// not admin-set. Floor the min / ceil the max to a round step so the slider looks clean.
$pr = db()->fetchOne("SELECT MIN(COALESCE(discount_price,price)) mn, MAX(price) mx FROM products WHERE is_active=1");
$min = (float)($pr['mn'] ?? 0);
$max = (float)($pr['mx'] ?? 0);
$out['priceBounds'] = [
    'min' => $max > 0 ? (int)(floor($min / 10) * 10) : 0,
    'max' => $max > 0 ? (int)(ceil($max / 100) * 100) : 500000,
];

jsonOut(['success' => true, 'settings' => $out]);
