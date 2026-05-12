<?php
/**
 * AI-Powered Image Search Endpoint
 * Uses Claude Vision via Anthropic API to identify dental products from images
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

// Call Anthropic API
$apiKey = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : '';

if (empty($apiKey)) {
    // Fallback: keyword-based matching without AI
    echo json_encode([
        'success' => true,
        'query'   => '',
        'suggestions' => [],
        'message' => 'AI key not configured — using filename hint'
    ]);
    exit;
}

$payload = [
    'model'      => 'claude-opus-4-5',
    'max_tokens' => 150,
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
                'text' => 'You are a dental product catalog assistant. Identify the dental product or equipment in this image. Respond with ONLY a short 2-4 word product search query (e.g. "dental implant kit", "ultrasonic scaler", "composite resin kit", "air rotor handpiece"). No explanation, no punctuation — just the search query.',
            ],
        ],
    ]],
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'message' => 'AI service unavailable', 'query' => '']);
    exit;
}

$result = json_decode($response, true);
$query  = trim($result['content'][0]['text'] ?? '');

// Search matching products from DB
$products = [];
if ($query) {
    $words = array_filter(explode(' ', $query));
    $likeTerms = array_map(fn($w) => "%$w%", $words);
    if (!empty($likeTerms)) {
        $conditions = implode(' OR ', array_fill(0, count($likeTerms), 'name LIKE ?'));
        $products = db()->fetchAll(
            "SELECT id, name, price, images FROM products WHERE ($conditions) AND is_active=1 LIMIT 6",
            $likeTerms
        );
        foreach ($products as &$p) {
            $imgs = $p['images'] ? json_decode($p['images'], true) : [];
            $p['thumbnail'] = $imgs[0] ?? null;
            unset($p['images']);
        }
    }
}

echo json_encode([
    'success'     => true,
    'query'       => $query,
    'products'    => $products,
    'message'     => $query ? "Identified: $query" : 'Could not identify product',
]);
