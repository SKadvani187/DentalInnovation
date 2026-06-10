<?php
// Shared, server-authoritative pricing + coupon logic.
// Used by coupon.php (validate a code) and orders.php (apply at order time) so the
// number shown in the cart, the number charged at checkout, and the number persisted
// on the order are computed the SAME way from the SAME source of truth (the DB +
// site_settings). Never trust client-sent money values.

require_once __DIR__ . '/_bootstrap.php';

// Read a site_settings JSON value (decoded) or a fallback default.
function settingVal(string $key, $default = null) {
    $row = db()->fetchOne("SELECT svalue FROM site_settings WHERE skey=?", [$key]);
    if (!$row) return $default;
    $v = json_decode($row['svalue'] ?? 'null', true);
    return $v === null ? $default : $v;
}

// Evaluate a coupon code against a subtotal.
// Returns: ['valid'=>bool, 'message'=>string, 'discount'=>float, 'coupon'=>row|null]
// Discount rule: percent (capped at max_discount) or fixed; CAP first, THEN round to
// 2dp (this order must match the storefront util to avoid ±₹1 drift).
function couponEvaluate(string $code, float $subtotal): array {
    $code = strtoupper(trim($code));
    $no = fn($m) => ['valid'=>false, 'message'=>$m, 'discount'=>0.0, 'coupon'=>null];
    if ($code === '') return $no('Coupon code required');

    $c = db()->fetchOne("SELECT * FROM coupons WHERE code=? AND is_active=1", [$code]);
    if (!$c) return $no('Invalid coupon code');
    if (!empty($c['expires_at']) && strtotime($c['expires_at']) < strtotime(date('Y-m-d'))) return $no('Coupon expired');
    if ($c['uses_limit'] !== null && (int)$c['uses_count'] >= (int)$c['uses_limit'])       return $no('Coupon usage limit reached');
    if ($subtotal < (float)$c['min_order']) return $no('Minimum order ₹' . number_format((float)$c['min_order'], 0) . ' required');

    if ($c['type'] === 'percent') {
        $discount = $subtotal * ((float)$c['value'] / 100);
        if ($c['max_discount'] !== null) $discount = min($discount, (float)$c['max_discount']);
    } else { // fixed
        $discount = (float)$c['value'];
    }
    $discount = round(min($discount, $subtotal), 2);

    return ['valid'=>true, 'message'=>'Coupon applied — you save ₹' . number_format($discount, 0), 'discount'=>$discount, 'coupon'=>$c];
}

// Compute all order-level money from an authoritative line subtotal + the resolved
// order lines (each ['price','qty','line_type']) + an optional coupon code.
// Mirrors the storefront cart util (src/lib/pricing.js). Returns a breakdown incl.
// the coupon row (so the caller can bump uses_count) and the final rounded total.
function computeOrderTotals(float $subtotal, array $lines, ?string $couponCode): array {
    $subtotal = round($subtotal, 2);

    // Bulk savings: rate% off each line with qty >= minQty (gift lines excluded).
    $bulk   = settingVal('bulkRule', ['minQty'=>2, 'rate'=>0.1]);
    $minQty = (int)($bulk['minQty'] ?? 2);
    $rate   = (float)($bulk['rate'] ?? 0.1);
    $bulkSavings = 0.0;
    foreach ($lines as $l) {
        if (($l['line_type'] ?? 'product') === 'gift') continue;
        if ((int)$l['qty'] >= $minQty) $bulkSavings += (float)$l['price'] * $rate * (int)$l['qty'];
    }
    $bulkSavings = round($bulkSavings, 2);

    // Coupon (revalidated against the raw subtotal, same as the cart).
    $couponDiscount = 0.0; $couponRow = null;
    if ($couponCode) {
        $ev = couponEvaluate($couponCode, $subtotal);
        if ($ev['valid']) { $couponDiscount = $ev['discount']; $couponRow = $ev['coupon']; }
    }

    $discount      = round($bulkSavings + $couponDiscount, 2);
    $afterDiscount = max(0.0, round($subtotal - $discount, 2));

    // Shipping: flat rate unless free over a threshold (both admin-configurable).
    $ship          = settingVal('shippingConfig', ['freeThreshold'=>20000, 'flatRate'=>600]);
    $freeThreshold = (float)($ship['freeThreshold'] ?? 20000);
    $flatRate      = (float)($ship['flatRate'] ?? 600);
    $shipping      = (count($lines) === 0 || $subtotal >= $freeThreshold) ? 0.0 : $flatRate;

    // Tax: disabled by default (prices treated as tax-inclusive). When enabled and
    // NOT inclusive, add rate% of the discounted amount.
    $taxCfg = settingVal('taxConfig', ['enabled'=>false, 'rate'=>0, 'inclusive'=>true]);
    $tax = 0.0;
    if (!empty($taxCfg['enabled']) && empty($taxCfg['inclusive'])) {
        $tax = round($afterDiscount * ((float)($taxCfg['rate'] ?? 0) / 100), 2);
    }

    $total = round(max(0.0, $afterDiscount + $shipping + $tax), 2);

    return [
        'subtotal'       => $subtotal,
        'bulkSavings'    => $bulkSavings,
        'couponDiscount' => $couponDiscount,
        'couponRow'      => $couponRow,
        'discount'       => $discount,
        'shipping'       => $shipping,
        'tax'            => $tax,
        'total'          => $total,
    ];
}
