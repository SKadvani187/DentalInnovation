<?php
// Frequently Bought Together for the current cart.
// POST /api/v1/fbt.php  { slugs: ["p-001","p-003"] }
//   -> { success, items: [ <mapped product>, ... ] }
// Returns the union of every cart product's per-product FBT list (product_fbt table),
// de-duplicated, excluding products already in the cart, only active products.
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$body  = jsonBody();
$slugs = array_values(array_filter(array_map('strval', (array)($body['slugs'] ?? []))));
if (!$slugs) jsonOut(['success' => true, 'items' => []]);

$db = db();

// Resolve cart slugs -> product ids.
$in   = implode(',', array_fill(0, count($slugs), '?'));
$rows = $db->fetchAll("SELECT id, slug FROM products WHERE slug IN ($in)", $slugs);
if (!$rows) jsonOut(['success' => true, 'items' => []]);

$cartIds   = array_map(fn($r) => (int)$r['id'], $rows);
$cartSlugs = array_map(fn($r) => $r['slug'], $rows);

// Collect FBT product ids for every cart product (ordered by sort_order).
$inIds = implode(',', array_fill(0, count($cartIds), '?'));
$fbt   = $db->fetchAll(
    "SELECT DISTINCT fbt_product_id FROM product_fbt WHERE product_id IN ($inIds) ORDER BY sort_order",
    $cartIds
);
$fbtIds = array_map(fn($r) => (int)$r['fbt_product_id'], $fbt);
// Drop any that are already in the cart.
$fbtIds = array_values(array_diff($fbtIds, $cartIds));
if (!$fbtIds) jsonOut(['success' => true, 'items' => []]);

// Fetch + map the suggested products (active only).
$inF  = implode(',', array_fill(0, count($fbtIds), '?'));
$prods = $db->fetchAll(
    "SELECT * FROM products WHERE id IN ($inF) AND is_active=1",
    $fbtIds
);
$items = array_map('mapProduct', $prods);

jsonOut(['success' => true, 'items' => $items]);
