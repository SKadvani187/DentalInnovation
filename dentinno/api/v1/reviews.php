<?php
// GET  /api/v1/reviews.php?product=slug -> approved reviews + aggregate { avg, count, distribution }
// POST /api/v1/reviews.php              -> submit a review { product, name, email?, rating, title?, review }
//                                          (saved with is_approved=0; appears after admin approval)
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$db = db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $b = jsonBody();
    $slug   = trim((string)($b['product'] ?? ''));
    $name   = trim((string)($b['name'] ?? ''));
    $email  = trim((string)($b['email'] ?? ''));
    $rating = (int)($b['rating'] ?? 0);
    $title  = trim((string)($b['title'] ?? ''));
    $text   = trim((string)($b['review'] ?? ''));

    if ($slug === '' || $name === '' || $text === '' || $rating < 1 || $rating > 5) {
        jsonErr('Name, rating (1-5) and review text are required.', 422);
    }

    $prod = $db->fetchOne("SELECT id FROM products WHERE slug=? AND is_active=1", [$slug]);
    if (!$prod) jsonErr('Product not found', 404);

    // Logged-in customer (optional) — link + auto-verify their purchase status later if desired.
    $cust = authCustomer();

    $db->execute(
        "INSERT INTO product_reviews (product_id, customer_id, reviewer_name, reviewer_email, rating, title, review, is_approved)
         VALUES (?,?,?,?,?,?,?,0)",
        [$prod['id'], $cust['id'] ?? null, $name, $email ?: null, $rating, $title ?: null, $text]
    );

    jsonOut(['success' => true, 'message' => 'Thanks! Your review is awaiting moderation.']);
}

// --- GET: approved reviews for a product ---
$slug = qstr('product');
if ($slug === '') jsonErr('Missing product', 400);

$prod = $db->fetchOne("SELECT id FROM products WHERE slug=?", [$slug]);
if (!$prod) jsonOut(['success' => true, 'reviews' => [], 'summary' => emptySummary()]);

$pid  = (int)$prod['id'];
$rows = $db->fetchAll(
    "SELECT * FROM product_reviews WHERE product_id=? AND is_approved=1 AND is_deleted=0 ORDER BY created_at DESC",
    [$pid]
);
$reviews = array_map('mapReview', $rows);

// Aggregate from the same approved set so storefront stars match what is shown.
$dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$sum = 0;
foreach ($rows as $r) {
    $s = (int)$r['rating'];
    if (isset($dist[$s])) $dist[$s]++;
    $sum += $s;
}
$count = count($rows);
$avg = $count ? round($sum / $count, 1) : 0.0;

jsonOut([
    'success' => true,
    'reviews' => $reviews,
    'summary' => [
        'avg'          => $avg,
        'count'        => $count,
        'distribution' => $dist,
    ],
]);

function emptySummary(): array {
    return ['avg' => 0.0, 'count' => 0, 'distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];
}
