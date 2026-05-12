<?php
/**
 * Voice Search API — returns matching products from transcript
 */
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'POST required']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$query = sanitize($input['query'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['success'=>true,'products'=>[],'message'=>'Query too short']); exit;
}

// Clean spoken filler words
$fillers = ['show me','find','search for','look for','where is','i want','give me','do you have','product','products'];
$clean   = strtolower($query);
foreach ($fillers as $f) { $clean = str_replace($f, '', $clean); }
$clean = trim($clean);

$words = array_filter(array_unique(explode(' ', $clean)), fn($w) => strlen($w) > 2);
if (empty($words)) {
    echo json_encode(['success'=>true,'products'=>[],'message'=>'No keywords found']); exit;
}

$conditions = implode(' OR ', array_fill(0, count($words), 'LOWER(p.name) LIKE ? OR LOWER(p.short_description) LIKE ?'));
$params = [];
foreach ($words as $w) { $params[] = "%$w%"; $params[] = "%$w%"; }

$products = db()->fetchAll(
    "SELECT p.id, p.name, p.price, p.discount_price, p.stock, p.images, c.name as category
     FROM products p LEFT JOIN categories c ON p.category_id=c.id
     WHERE ($conditions) AND p.is_active=1 ORDER BY p.is_featured DESC, p.name ASC LIMIT 10",
    $params
);

foreach ($products as &$p) {
    $imgs = $p['images'] ? json_decode($p['images'], true) : [];
    $p['thumbnail'] = $imgs[0] ?? null;
    unset($p['images']);
}

echo json_encode([
    'success'   => true,
    'query'     => $query,
    'keywords'  => array_values($words),
    'products'  => $products,
    'count'     => count($products),
]);
