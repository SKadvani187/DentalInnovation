<?php
// Validate a coupon against a cart subtotal.
// GET /api/v1/coupon.php?code=DENT10&subtotal=5000
//   -> {valid, discount, code, type, value, message}
require_once __DIR__ . '/_bootstrap.php';

$code = strtoupper(qstr('code'));
$subtotal = (float)qint('subtotal', 0);
if ($code === '') jsonErr('Coupon code required', 422);

$c = db()->fetchOne("SELECT * FROM coupons WHERE code=? AND is_active=1", [$code]);
if (!$c) {
    jsonOut(['success' => true, 'valid' => false, 'discount' => 0, 'message' => 'Invalid coupon code']);
}

// expiry
if (!empty($c['expires_at']) && strtotime($c['expires_at']) < strtotime(date('Y-m-d'))) {
    jsonOut(['success' => true, 'valid' => false, 'discount' => 0, 'message' => 'Coupon expired']);
}
// usage limit
if ($c['uses_limit'] !== null && (int)$c['uses_count'] >= (int)$c['uses_limit']) {
    jsonOut(['success' => true, 'valid' => false, 'discount' => 0, 'message' => 'Coupon usage limit reached']);
}
// min order
if ($subtotal < (float)$c['min_order']) {
    jsonOut([
        'success' => true, 'valid' => false, 'discount' => 0,
        'message' => 'Minimum order ₹' . number_format((float)$c['min_order'], 0) . ' required',
    ]);
}

// compute discount
$discount = 0.0;
if ($c['type'] === 'percent') {
    $discount = $subtotal * ((float)$c['value'] / 100);
    if ($c['max_discount'] !== null) $discount = min($discount, (float)$c['max_discount']);
} else { // fixed
    $discount = (float)$c['value'];
}
$discount = round(min($discount, $subtotal), 2);

jsonOut([
    'success'  => true,
    'valid'    => true,
    'code'     => $c['code'],
    'type'     => $c['type'],
    'value'    => (float)$c['value'],
    'discount' => $discount,
    'message'  => 'Coupon applied — you save ₹' . number_format($discount, 0),
]);
