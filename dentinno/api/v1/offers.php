<?php
// GET /api/v1/offers.php -> active offers (offer zone)
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$rows = db()->fetchAll("SELECT * FROM offers WHERE is_active=1 ORDER BY sort_order");

// Real "bought today" count per product slug (distinct orders today containing that product).
$soldRows = db()->fetchAll(
    "SELECT oi.product_slug AS slug, COUNT(DISTINCT oi.order_id) AS cnt
     FROM order_items oi JOIN orders o ON o.id = oi.order_id
     WHERE DATE(o.created_at) = CURDATE() AND oi.product_slug IS NOT NULL
     GROUP BY oi.product_slug"
);
$soldToday = [];
foreach ($soldRows as $r) $soldToday[$r['slug']] = (int)$r['cnt'];

// Product lookup so the offer's main product always reflects the REAL linked product
// (name/image/price) — clicking the card opens exactly what the card shows.
$prodRows = db()->fetchAll("SELECT slug, name, price, discount_price, JSON_EXTRACT(images,'$[0]') AS img FROM products");
$prodBySlug = [];
foreach ($prodRows as $pr) {
    $prodBySlug[$pr['slug']] = [
        'name'  => $pr['name'],
        'image' => trim((string)$pr['img'], '"'),
        'mrp'   => (float)$pr['price'],
        'price' => $pr['discount_price'] !== null ? (float)$pr['discount_price'] : (float)$pr['price'],
    ];
}

$offers = array_map(function ($r) use ($soldToday, $prodBySlug) {
    $o = mapOffer($r);
    $pid = $o['mainProduct']['productId'] ?? null;
    // Sync the main product's NAME + IMAGE from the real linked product so the card matches
    // what opens on click. Keep the offer's own price/mrp (that's the negotiated deal pricing).
    if ($pid && isset($prodBySlug[$pid])) {
        $p = $prodBySlug[$pid];
        $o['mainProduct']['name']  = $p['name'];
        $o['mainProduct']['image'] = $p['image'] ?: ($o['mainProduct']['image'] ?? null);
    }
    // Social-proof count: admin chooses 'manual' (fixed number) or 'live' (real orders today).
    if (($r['social_mode'] ?? 'live') === 'manual') {
        $o['boughtToday'] = max(0, (int)($r['social_count'] ?? 0));
    } else {
        $o['boughtToday'] = $pid && isset($soldToday[$pid]) ? $soldToday[$pid] : 0;
    }
    return $o;
}, $rows);

jsonOut(['success' => true, 'offers' => $offers]);
