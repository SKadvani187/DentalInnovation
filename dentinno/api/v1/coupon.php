<?php
// Validate a coupon against a cart subtotal.
// GET /api/v1/coupon.php?code=DENT10&subtotal=5000
//   -> {valid, discount, code, type, value, message}
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_pricing.php';

$code = strtoupper(qstr('code'));
$subtotal = (float)qint('subtotal', 0);
if ($code === '') jsonErr('Coupon code required', 422);

// Single source of truth: same evaluator the order path uses (see _pricing.php).
$ev = couponEvaluate($code, $subtotal);
if (!$ev['valid']) {
    jsonOut(['success' => true, 'valid' => false, 'discount' => 0, 'message' => $ev['message']]);
}
$c = $ev['coupon'];
jsonOut([
    'success'  => true,
    'valid'    => true,
    'code'     => $c['code'],
    'type'     => $c['type'],
    'value'    => (float)$c['value'],
    'discount' => $ev['discount'],
    'message'  => $ev['message'],
]);
