<?php
// GET /api/v1/offers.php -> active offers (offer zone)
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

// Exclude expired offers (valid_till in the past). Compute "now" in PHP using the
// app timezone (config sets Asia/Kolkata) rather than trusting the MySQL session tz.
$now = date('Y-m-d H:i:s');
$rows = db()->fetchAll(
    "SELECT * FROM offers
     WHERE is_active=1 AND (valid_till IS NULL OR valid_till >= ?)
     ORDER BY sort_order",
    [$now]
);

// Batch-load free gift items relationally (with each gift's product slug for the cart).
$giftsByOffer = [];
if ($rows) {
    $offerIds = array_map(fn($r) => (int)$r['id'], $rows);
    $ph = implode(',', array_fill(0, count($offerIds), '?'));
    $giftRows = db()->fetchAll(
        "SELECT oi.*, p.slug AS product_slug
         FROM offer_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.offer_id IN ($ph)
         ORDER BY oi.offer_id, oi.sort_order, oi.id",
        $offerIds
    );
    foreach ($giftRows as $g) $giftsByOffer[(int)$g['offer_id']][] = $g;
}

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

$offers = array_map(function ($r) use ($soldToday, $prodBySlug, $giftsByOffer) {
    $o = mapOffer($r, $giftsByOffer[(int)$r['id']] ?? []);
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
