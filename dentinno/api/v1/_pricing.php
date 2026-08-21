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

// ---- Shipping engine (DB-driven: shipping_methods / shipping_rules / shipping_zones) ----

// Resolve a destination zone id from a 6-digit pincode. A zone matches if any of its
// JSON `pincodes` entries is a prefix of the pincode; longest prefix wins. Returns the
// zone id, or null (meaning "use zone-agnostic rules only").
function resolveShippingZone(?string $pincode): ?int {
    $pin = preg_replace('/\D/', '', (string)$pincode);
    if (strlen($pin) !== 6) return null;
    $zones = db()->fetchAll("SELECT id, pincodes FROM shipping_zones WHERE is_active=1");
    $bestId = null; $bestLen = -1;
    foreach ($zones as $z) {
        $pfxs = json_decode($z['pincodes'] ?? 'null', true);
        if (!is_array($pfxs)) continue;
        foreach ($pfxs as $pfx) {
            $pfx = preg_replace('/\D/', '', (string)$pfx);
            if ($pfx !== '' && strpos($pin, $pfx) === 0 && strlen($pfx) > $bestLen) {
                $bestId = (int)$z['id']; $bestLen = strlen($pfx);
            }
        }
    }
    return $bestId;
}

// Cost of one shipping method for the given order facts. Returns ['cost'=>float,'free'=>bool]
// or null if the method has no applicable rule (so it's excluded from the options).
function methodShippingCost(array $method, float $price, float $weight, int $qty, ?int $zoneId, array $classes = []): ?array {
    $type = $method['type'] ?? 'flat';
    if ($type === 'free') return ['cost' => 0.0, 'free' => true];
    if ($type === 'flat') return ['cost' => (float)($method['base_cost'] ?? 0), 'free' => false];

    // weight / price / product / flexible -> best matching rule (zone-specific beats global).
    $rules = db()->fetchAll(
        "SELECT * FROM shipping_rules
         WHERE method_id=? AND is_active=1 AND (zone_id IS NULL " . ($zoneId ? "OR zone_id=?" : "") . ")
         ORDER BY (zone_id IS NOT NULL) DESC, min_value ASC",
        $zoneId ? [(int)$method['id'], $zoneId] : [(int)$method['id']]
    );
    foreach ($rules as $rule) {
        // Product-class rules match on the cart's shipping classes, not a numeric range:
        // the rule applies when ANY line in the cart has its target class.
        if (($rule['rule_type'] ?? '') === 'product') {
            $cls = $rule['product_class'] ?? null;
            if ($cls !== null && in_array($cls, $classes, true)) {
                $free = (bool)$rule['is_free'];
                return ['cost' => $free ? 0.0 : (float)$rule['cost'], 'free' => $free];
            }
            continue;
        }
        $val = match ($rule['rule_type']) {
            'weight'   => $weight,
            'price'    => $price,
            'quantity' => $qty,
            default    => 0.0,
        };
        $max = $rule['max_value'] !== null ? (float)$rule['max_value'] : PHP_FLOAT_MAX;
        if ($val >= (float)$rule['min_value'] && $val <= $max) {
            $free = (bool)$rule['is_free'];
            return ['cost' => $free ? 0.0 : (float)$rule['cost'], 'free' => $free];
        }
    }
    return null;  // no rule matched
}

// Authoritative shipping cost for an order. Picks the CHEAPEST applicable active method
// (free beats paid). Falls back to the flat shippingConfig if no methods table/rows
// exist (back-compat with the pre-engine behaviour).
function computeShipping(array $lines, float $subtotal, float $weight, int $qty, ?int $zoneId): float {
    if (count($lines) === 0) return 0.0;

    $methods = db()->fetchAll("SELECT * FROM shipping_methods WHERE is_active=1 ORDER BY sort_order");
    if (!$methods) {
        // No engine configured — legacy flat rate.
        $ship = settingVal('shippingConfig', ['freeThreshold' => 20000, 'flatRate' => 600]);
        $freeThreshold = (float)($ship['freeThreshold'] ?? 20000);
        $flatRate      = (float)($ship['flatRate'] ?? 600);
        return ($subtotal >= $freeThreshold) ? 0.0 : $flatRate;
    }

    $classes = linesClasses($lines);

    // Per-product method override (admin-assigned in the product's Shipping tab). If every
    // non-gift line points at the SAME active method, that method wins outright. Mixed or
    // unassigned carts fall through to the global cheapest pick below.
    $forcedId = linesAssignedMethod($lines);
    if ($forcedId !== null) {
        foreach ($methods as $m) {
            if ((int)$m['id'] === $forcedId) {
                $r = methodShippingCost($m, $subtotal, $weight, $qty, $zoneId, $classes);
                if ($r !== null) return round((float)$r['cost'], 2);
                break;  // assigned method has no applicable rule -> fall back to cheapest
            }
        }
    }

    $best = null;
    foreach ($methods as $m) {
        $r = methodShippingCost($m, $subtotal, $weight, $qty, $zoneId, $classes);
        if ($r === null) continue;
        if ($best === null
            || ($r['free'] && !$best['free'])
            || (!($best['free']) && $r['cost'] < $best['cost'])) {
            $best = $r;
        }
    }
    // No method applied at all -> free (don't block the order on a config gap).
    return $best ? round((float)$best['cost'], 2) : 0.0;
}

// The single shipping method assigned to ALL non-gift product lines, or null when the
// cart has no assignment / a mix of methods (then global rules decide).
function linesAssignedMethod(array $lines): ?int {
    $ids = [];
    foreach ($lines as $l) {
        if (($l['line_type'] ?? 'product') === 'gift') continue;
        $pid = $l['product_id'] ?? null;
        if (!$pid) return null;  // combos / non-products have no assignment -> global rules
        $row = db()->fetchOne("SELECT shipping_method_id FROM products WHERE id=?", [(int)$pid]);
        $mid = $row['shipping_method_id'] ?? null;
        if (!$mid) return null;  // an unassigned line -> global rules
        $ids[(int)$mid] = true;
    }
    return count($ids) === 1 ? (int)array_key_first($ids) : null;
}

// Distinct shipping classes present across the order lines (from products.shipping_class).
// Used by product-class shipping rules, which apply when ANY line has the target class.
function linesClasses(array $lines): array {
    $seen = [];
    foreach ($lines as $l) {
        $pid = $l['product_id'] ?? null;
        if (!$pid) continue;
        $row = db()->fetchOne("SELECT shipping_class FROM products WHERE id=?", [(int)$pid]);
        $cls = $row['shipping_class'] ?? null;
        if ($cls && !in_array($cls, $seen, true)) $seen[] = $cls;
    }
    return $seen;
}

// Total billable weight (kg) for the resolved order lines. Gift lines count too (they
// still ship). Per-product weight comes from products.weight_kg (0 when unset).
function linesWeight(array $lines): float {
    $w = 0.0;
    foreach ($lines as $l) {
        $pid = $l['product_id'] ?? null;
        if (!$pid) continue;
        $row = db()->fetchOne("SELECT weight_kg FROM products WHERE id=?", [(int)$pid]);
        $w += (float)($row['weight_kg'] ?? 0) * (int)($l['qty'] ?? 1);
    }
    return round($w, 3);
}

// Evaluate a coupon code against a subtotal.
// Returns: ['valid'=>bool, 'message'=>string, 'discount'=>float, 'coupon'=>row|null]
// Discount rule: percent (capped at max_discount) or fixed; CAP first, THEN round to
// 2dp (this order must match the storefront util to avoid ±₹1 drift).
function couponEvaluate(string $code, float $subtotal): array {
    $code = strtoupper(trim($code));
    $no = fn($m) => ['valid'=>false, 'message'=>$m, 'discount'=>0.0, 'coupon'=>null];
    if ($code === '') return $no('Coupon code required');

    $c = db()->fetchOne("SELECT * FROM coupons WHERE code=? AND is_active=1 AND is_deleted=0", [$code]);
    if (!$c) return $no('Invalid coupon code');
    if (!empty($c['start_date']) && strtotime($c['start_date']) > strtotime(date('Y-m-d'))) return $no('This coupon is not active yet');
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

// Quantity-discount rate for one line. Tiers are the primary engine: pick the highest
// tier whose minQty <= qty. When NO tiers are configured, fall back to the legacy single
// bulk rule (kept dormant in the DB). Mirrors tierRateFor() in src/lib/pricing.js.
function tierRateForQty(int $qty, $tiers, $bulk): float {
    if (is_array($tiers) && count($tiers) > 0) {
        $best = null;
        foreach ($tiers as $t) {
            $mq = (int)($t['minQty'] ?? 0);
            if ($qty >= $mq && ($best === null || $mq > (int)($best['minQty'] ?? 0))) {
                $best = $t;
            }
        }
        return $best ? (float)($best['rate'] ?? 0) : 0.0;
    }
    $mq = (int)($bulk['minQty'] ?? 2);
    return $qty >= $mq ? (float)($bulk['rate'] ?? 0.1) : 0.0;
}

// Compute all order-level money from an authoritative line subtotal + the resolved
// order lines (each ['price','qty','line_type']) + an optional coupon code.
// Mirrors the storefront cart util (src/lib/pricing.js). Returns a breakdown incl.
// the coupon row (so the caller can bump uses_count) and the final rounded total.
function computeOrderTotals(float $subtotal, array $lines, ?string $couponCode, ?string $pincode = null): array {
    $subtotal = round($subtotal, 2);

    // Quantity discount: tier table first, single bulk rule as a dormant fallback.
    // rate% off each non-gift line based on its qty. The result is still reported as
    // `bulkSavings` so downstream consumers/columns don't change.
    $tiers = settingVal('tierOffers', []);
    $bulk  = settingVal('bulkRule', ['minQty'=>2, 'rate'=>0.1]);
    $bulkSavings = 0.0;
    foreach ($lines as $l) {
        if (($l['line_type'] ?? 'product') === 'gift') continue;
        $qty  = (int)$l['qty'];
        // Quantity discounts come ONLY from a product's own tiers (the reference-style per-product
        // "Available Offers"). No global/legacy fallback — a product with no tiers gets no discount.
        $lineTiers = null;
        if (!empty($l['product_id'])) {
            $pj = json_decode((string)(db()->fetchOne("SELECT bulk_offers FROM products WHERE id=?", [(int)$l['product_id']])['bulk_offers'] ?? ''), true);
            if (is_array($pj) && count($pj) > 0) $lineTiers = $pj;
        }
        if (!$lineTiers) continue;
        $rate = tierRateForQty($qty, $lineTiers, null);
        if ($rate > 0) $bulkSavings += (float)$l['price'] * $rate * $qty;
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

    // Shipping: DB-driven engine (shipping_methods/rules/zones). Picks the cheapest
    // applicable method using the order's weight, subtotal, qty and destination zone
    // (resolved from the delivery pincode). Falls back to flat shippingConfig if the
    // engine has no methods configured. See computeShipping().
    $shipQty  = 0;
    foreach ($lines as $l) $shipQty += (int)($l['qty'] ?? 0);
    $weight   = linesWeight($lines);
    $zoneId   = resolveShippingZone($pincode);
    $shipping = computeShipping($lines, $subtotal, $weight, $shipQty, $zoneId);

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
