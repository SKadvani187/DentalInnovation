<?php
// Live shipping quote for the cart (server-authoritative — same engine as the order path).
// POST /api/v1/shipping_quote.php  { items:[{id|slug, qty}], pincode? }
//   -> { success, shipping, weight, zoneId, free, methods:[{name,type,cost,free,applicable}] }
// Prices/weights are resolved from the DB by slug; client-sent money is never trusted.
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_pricing.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jsonErr('POST required', 405);
$body  = jsonBody();
$items = is_array($body['items'] ?? null) ? $body['items'] : [];
$pincode = (string)($body['pincode'] ?? '');
// Optional: the option the customer has selected. Priced from the DB like every other path.
$chosenMethodId = isset($body['shippingMethodId']) && (int)$body['shippingMethodId'] > 0
    ? (int)$body['shippingMethodId'] : null;

$db = db();
$lines = [];      // resolved lines (product_id, qty, price) — mirrors orders.php resolution
$subtotal = 0.0;
$qty = 0;
foreach ($items as $it) {
    $slug = trim((string)($it['slug'] ?? $it['id'] ?? ''));
    $q    = max(1, (int)($it['qty'] ?? 1));
    if ($slug === '') continue;
    $p = $db->fetchOne("SELECT id, price, discount_price FROM products WHERE slug=? AND is_active=1", [$slug]);
    if ($p) {
        $price = $p['discount_price'] !== null ? (float)$p['discount_price'] : (float)$p['price'];
        $lines[] = ['product_id' => (int)$p['id'], 'qty' => $q, 'price' => $price, 'line_type' => 'product'];
        $subtotal += $price * $q; $qty += $q;
        continue;
    }
    // combos: no product_id (no weight), still contribute to subtotal/qty
    $combo = $db->fetchOne("SELECT price FROM combos WHERE slug=? AND is_active=1", [$slug]);
    if ($combo) {
        $lines[] = ['product_id' => null, 'qty' => $q, 'price' => (float)$combo['price'], 'line_type' => 'product'];
        $subtotal += (float)$combo['price'] * $q; $qty += $q;
    }
}

$subtotal = round($subtotal, 2);
$weight   = linesWeight($lines);
$zoneId   = resolveShippingZone($pincode);
$shipping = computeShipping($lines, $subtotal, $weight, $qty, $zoneId, $chosenMethodId);

// Base transit time for the destination, from delivery_pincodes (longest matching prefix), used
// for any method that doesn't state its own. Same resolution as api/v1/delivery.php.
$baseDays = null;
if (strlen(preg_replace('/\D/', '', $pincode)) === 6) {
    $pin = preg_replace('/\D/', '', $pincode);
    foreach ($db->fetchAll("SELECT pincode_prefix, delivery_days FROM delivery_pincodes
                             WHERE is_active=1 ORDER BY CHAR_LENGTH(pincode_prefix) DESC") as $r) {
        $pfx = (string)$r['pincode_prefix'];
        if ($pfx !== '' && strpos($pin, $pfx) === 0) { $baseDays = max(0, (int)$r['delivery_days']); break; }
    }
}
/** Delivery date for a method: its own delivery_days if set, else the pincode's. */
$etaFor = function ($methodDays) use ($baseDays) {
    $days = $methodDays !== null ? max(0, (int)$methodDays) : $baseDays;
    if ($days === null) return [null, null];
    return [$days, (new DateTime('now'))->modify("+{$days} day")->format('Y-m-d')];
};

// The delivery options for this order. `id` is what checkout sends back when the customer picks
// one; `classes` is needed so a product-class rule prices the same here as it will at checkout.
$classes = linesClasses($lines);
$methods = [];
foreach ($db->fetchAll("SELECT * FROM shipping_methods WHERE is_active=1 ORDER BY sort_order, id") as $m) {
    $r = methodShippingCost($m, $subtotal, $weight, $qty, $zoneId, $classes);
    [$mDays, $mEta] = $etaFor($m['delivery_days'] ?? null);
    $methods[] = [
        'id'          => (int)$m['id'],
        'name'        => $m['name'],
        'description' => $m['description'] ?: null,
        'type'        => $m['type'],
        'applicable'  => $r !== null,
        'cost'        => $r['cost'] ?? null,
        'free'        => $r['free'] ?? false,
        // Per-option delivery promise — what makes a faster (dearer) service worth choosing.
        'deliveryDays' => $mDays,
        'eta'          => $mEta,
    ];
}
// The option the engine picks on its own — checkout preselects it. Mirrors computeShipping's
// preference (free beats paid, then cheapest) rather than matching on the quoted amount, which
// would point at whatever the customer has already chosen.
$defaultMethodId = null; $bestCost = null; $bestFree = false;
foreach ($methods as $m) {
    if (!$m['applicable']) continue;
    $free = (bool)$m['free']; $cost = (float)$m['cost'];
    if ($defaultMethodId === null || ($free && !$bestFree) || (!$bestFree && $cost < $bestCost)) {
        $defaultMethodId = $m['id']; $bestCost = $cost; $bestFree = $free;
    }
}

jsonOut([
    'success'  => true,
    'shipping' => $shipping,
    'free'     => $shipping <= 0 && count($lines) > 0,
    'weight'   => $weight,
    'zoneId'   => $zoneId,
    'subtotal' => $subtotal,
    'methods'  => $methods,
    'defaultMethodId' => $defaultMethodId,
    // What COD would add on this order (0 when disabled, waived, or not applicable). Lets the
    // cart show the surcharge the moment COD is picked, without a second round trip.
    'codFee'   => codFeeFor('cod', $subtotal),
]);
