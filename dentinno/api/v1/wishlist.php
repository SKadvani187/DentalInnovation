<?php
// Customer wishlist (slug id array). Auth required.
// GET  /api/v1/wishlist.php           -> {ids:[...]}
// POST /api/v1/wishlist.php  {ids:[]} -> replaces server list, returns merged {ids}
require_once __DIR__ . '/_bootstrap.php';

$db = db();
$c = requireCustomer();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    jsonOut(['success' => true, 'ids' => jcol($c['wishlist'] ?? null, [])]);
}

if ($method === 'POST') {
    $body = jsonBody();
    $incoming = is_array($body['ids'] ?? null) ? $body['ids'] : [];
    // merge with existing (union) so multiple devices don't lose items
    $existing = jcol($c['wishlist'] ?? null, []);
    $merged = array_values(array_unique(array_merge($existing, array_map('strval', $incoming))));
    $db->execute("UPDATE customers SET wishlist=? WHERE id=?", [json_encode($merged), $c['id']]);
    jsonOut(['success' => true, 'ids' => $merged]);
}

jsonErr('Method not allowed', 405);
