<?php
/**
 * AI-Powered Image Search Endpoint
 * Uses Claude Vision (Anthropic API) to identify dental products from an uploaded image,
 * then matches the identified keywords against the product catalog.
 *
 * POST { image: "data:image/png;base64,...." }
 *   -> { success, query, products:[{slug,name,price,thumbnail}], message }
 *
 * If ANTHROPIC_API_KEY is not configured the endpoint returns success with an empty
 * query so the frontend can fall back to filename-based matching.
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$imageData = $input['image'] ?? '';
if (empty($imageData)) {
    echo json_encode(['success' => false, 'message' => 'No image provided']); exit;
}

// Extract base64 and media type from data URL
if (preg_match('/^data:(image\/[a-z+]+);base64,(.+)$/', $imageData, $matches)) {
    $mediaType = $matches[1];
    $base64    = $matches[2];
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid image format']); exit;
}

$apiKey = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : '';
$model  = defined('ANTHROPIC_MODEL') ? ANTHROPIC_MODEL : 'claude-haiku-4-5';

if (empty($apiKey)) {
    // No AI key configured — let the frontend fall back to filename hints.
    echo json_encode([
        'success'  => true,
        'query'    => '',
        'products' => [],
        'message'  => 'AI key not configured — using filename hint',
    ]);
    exit;
}

// --- Ask Claude Vision to name the product (short search query only) ---
$payload = [
    'model'      => $model,
    'max_tokens' => 32,
    'messages'   => [[
        'role'    => 'user',
        'content' => [
            [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => $mediaType,
                    'data'       => $base64,
                ],
            ],
            [
                'type' => 'text',
                'text' => 'You are a dental product catalog assistant. Identify the dental product or equipment in this image. '
                        . 'Respond with ONLY a short 2-4 word product search query (e.g. "dental implant kit", "ultrasonic scaler", '
                        . '"composite resin kit", "air rotor handpiece"). No explanation, no punctuation — just the search query.',
            ],
        ],
    ]],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
$curlOpts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT        => 20,
];

// SSL: verify against the bundled CA bundle; allow the dev-only insecure flag
// (same pattern the OTP sender uses for local AV/proxy MITM).
$caBundle = __DIR__ . '/../includes/cacert.pem';
if (defined('OTP_SSL_INSECURE') && OTP_SSL_INSECURE) {
    $curlOpts[CURLOPT_SSL_VERIFYPEER] = false;
    $curlOpts[CURLOPT_SSL_VERIFYHOST] = 0;
} else {
    $curlOpts[CURLOPT_SSL_VERIFYPEER] = true;
    $curlOpts[CURLOPT_SSL_VERIFYHOST] = 2;
    if (is_file($caBundle)) $curlOpts[CURLOPT_CAINFO] = $caBundle;
}

curl_setopt_array($ch, $curlOpts);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    echo json_encode([
        'success'  => false,
        'query'    => '',
        'products' => [],
        'message'  => 'AI service unavailable' . ($curlErr ? ": $curlErr" : " (HTTP $httpCode)"),
    ]);
    exit;
}

$result = json_decode($response, true);
$query  = trim($result['content'][0]['text'] ?? '');

// --- Match the identified query against active products ---
$products = [];
if ($query !== '') {
    $words = array_filter(array_map('trim', explode(' ', $query)), fn($w) => strlen($w) >= 2);
    if (!empty($words)) {
        $likeTerms  = [];
        $conditions = [];
        foreach ($words as $w) {
            $conditions[] = 'p.name LIKE ?';
            $likeTerms[]  = "%$w%";
        }
        $sql = "SELECT p.slug, p.name, p.price, p.discount_price, p.images
                FROM products p
                WHERE (" . implode(' OR ', $conditions) . ") AND p.is_active = 1
                ORDER BY p.is_featured DESC, p.total_sales DESC
                LIMIT 8";
        $rows = db()->fetchAll($sql, $likeTerms);
        foreach ($rows as $r) {
            $imgs = $r['images'] ? json_decode($r['images'], true) : [];
            $products[] = [
                'slug'      => $r['slug'],
                'name'      => $r['name'],
                'price'     => $r['discount_price'] !== null ? (float)$r['discount_price'] : (float)$r['price'],
                'thumbnail' => $imgs[0] ?? null,
            ];
        }
    }
}

echo json_encode([
    'success'  => true,
    'query'    => $query,
    'products' => $products,
    'message'  => $query ? "Identified: $query" : 'Could not identify product',
]);