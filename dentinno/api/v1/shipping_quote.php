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
$shipping = computeShipping($lines, $subtotal, $weight, $qty, $zoneId);

// Per-method breakdown (informational — lets the cart show options / why it's free).
$methods = [];
foreach ($db->fetchAll("SELECT * FROM shipping_methods WHERE is_active=1 ORDER BY sort_order") as $m) {
    $r = methodShippingCost($m, $subtotal, $weight, $qty, $zoneId);
    $methods[] = [
        'name'       => $m['name'],
        'type'       => $m['type'],
        'applicable' => $r !== null,
        'cost'       => $r['cost'] ?? null,
        'free'       => $r['free'] ?? false,
    ];
}

jsonOut([
    'success'  => true,
    'shipping' => $shipping,
    'free'     => $shipping <= 0 && count($lines) > 0,
    'weight'   => $weight,
    'zoneId'   => $zoneId,
    'subtotal' => $subtotal,
    'methods'  => $methods,
]);
