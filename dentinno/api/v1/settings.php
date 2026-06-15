<?php
// GET /api/v1/settings.php -> all site settings as one object { company, payments, ... featured, premiumCategories }
require_once __DIR__ . '/_bootstrap.php';

$rows = db()->fetchAll("SELECT skey, svalue FROM site_settings");
// Private keys that must never be exposed to the public storefront (contain secrets / admin-only).
// whatsappConfig holds the Meta WhatsApp permanent access token — leaking it = account takeover.
// orderMailConfig holds the admin SMTP host/user/PASSWORD — leaking it = mailbox takeover.
$PRIVATE = ['otpConfig', 'whatsappConfig', 'orderMailConfig'];
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

// Coupons shown in the cart/checkout drawer come from the admin-managed `coupons` table
// (single source of truth) — NOT the static site_settings.coupons seed, which got out of
// sync (storefront listed codes that don't validate, and hid the real admin ones).
// Map each active, public, non-expired coupon to the shape the drawer expects.
// The same table backs coupon.php validation, so a listed coupon is always a valid coupon.
$couponRows = db()->fetchAll(
    "SELECT code, type, value, min_order, max_discount, expires_at
       FROM coupons
      WHERE is_active = 1 AND is_deleted = 0
        AND (start_date IS NULL OR start_date <= CURDATE())
        AND (expires_at IS NULL OR expires_at >= CURDATE())
      ORDER BY min_order ASC, value DESC"
);
$out['coupons'] = array_map(function ($c) {
    $isPercent = $c['type'] === 'percent';
    $min = (float)($c['min_order'] ?? 0);
    $val = (float)$c['value'];
    $discount = ['type' => $isPercent ? 'percent' : 'flat', 'value' => $val];
    if ($isPercent && $c['max_discount'] !== null) $discount['max'] = (float)$c['max_discount'];
    $title = $isPercent
        ? rtrim(rtrim(number_format($val, 2), '0'), '.') . '% off' . ($c['max_discount'] !== null ? ' (up to ₹' . (int)$c['max_discount'] . ')' : '')
        : 'Flat ₹' . (int)$val . ' off';
    $desc = $min > 0 ? 'On orders above ₹' . number_format($min, 0) : 'No minimum order';
    return [
        'code'        => $c['code'],
        'title'       => $title,
        'desc'        => $desc,
        'minSubtotal' => $min,
        'discount'    => $discount,
    ];
}, $couponRows);

jsonOut(['success' => true, 'settings' => $out]);
