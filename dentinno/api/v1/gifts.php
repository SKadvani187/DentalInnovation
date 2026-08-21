<?php
// Per-product free gifts for the current cart.
// POST /api/v1/gifts.php  { slugs: ["p-001","p-003"] }
//   -> { success, items: [ { ...mapped product, price:0, parentSlug } ] }
// For every product in the cart, returns its configured gift products (product_gifts),
// each forced to price 0 and tagged with the parentSlug that unlocked it so the cart can
// drop the gift when its parent product is removed. Only active gift products are returned.
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$body  = jsonBody();
$slugs = array_values(array_filter(array_map('strval', (array)($body['slugs'] ?? []))));
if (!$slugs) jsonOut(['success' => true, 'items' => []]);

$db = db();

// Resolve cart slugs -> {id, slug}.
$in   = implode(',', array_fill(0, count($slugs), '?'));
$rows = $db->fetchAll("SELECT id, slug FROM products WHERE slug IN ($in)", $slugs);
if (!$rows) jsonOut(['success' => true, 'items' => []]);

$idToSlug = [];
foreach ($rows as $r) $idToSlug[(int)$r['id']] = $r['slug'];
$cartIds = array_keys($idToSlug);

// Each parent product -> its gift product ids (keep the link for parentSlug).
$inIds = implode(',', array_fill(0, count($cartIds), '?'));
$links = $db->fetchAll(
    "SELECT product_id, gift_product_id FROM product_gifts WHERE product_id IN ($inIds) ORDER BY sort_order",
    $cartIds
);
if (!$links) jsonOut(['success' => true, 'items' => []]);

// gift product id -> the (first) parent slug that grants it.
$giftParent = [];
foreach ($links as $l) {
    $gid = (int)$l['gift_product_id'];
    if (!isset($giftParent[$gid])) $giftParent[$gid] = $idToSlug[(int)$l['product_id']] ?? null;
}

// Fetch + map the gift products (active only), forced to FREE.
$giftIds = array_keys($giftParent);
$inG = implode(',', array_fill(0, count($giftIds), '?'));
$prods = $db->fetchAll("SELECT * FROM products WHERE id IN ($inG) AND is_active=1", $giftIds);

$items = [];
foreach ($prods as $p) {
    $m = mapProduct($p);
    $m['price']      = 0;                                  // gift is free
    $m['parentSlug'] = $giftParent[(int)$p['id']] ?? null; // who unlocked it
    $items[] = $m;
}

jsonOut(['success' => true, 'items' => $items]);
