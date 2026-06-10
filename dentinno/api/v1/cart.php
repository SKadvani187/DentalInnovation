<?php
// Customer cart (full line-item array, stored as JSON on customers.cart). Auth required.
// Mirrors wishlist.php — keyed to the logged-in customer so the cart follows the account
// across devices/browsers.
//
//   GET  /api/v1/cart.php                      -> { items:[...] }
//   POST /api/v1/cart.php  { items, mode }     -> { items:[...] }
//        mode = "merge"   (default) union of server + incoming by line key, max qty
//                          (used on login so a guest cart never wipes the saved one)
//        mode = "replace"           overwrite the server cart with `items` exactly
//                          (used for normal add/remove/qty/clear while logged in)
require_once __DIR__ . '/_bootstrap.php';

$db = db();
$c = requireCustomer();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Keep only well-formed line objects (must carry a unique 'key'); normalise qty; cap size.
function sanitizeCart($v): array {
    if (!is_array($v)) return [];
    $out = [];
    foreach ($v as $line) {
        if (!is_array($line) || !isset($line['key']) || $line['key'] === '') continue;
        if (isset($line['qty'])) $line['qty'] = max(1, (int)$line['qty']);
        $out[] = $line;
        if (count($out) >= 200) break; // hard cap — guard against abusive payloads
    }
    return $out;
}

// Union two carts by line 'key', taking the larger qty for a shared key. Max (not sum)
// so repeated logins/syncs of the same cart don't keep inflating quantities.
function mergeCarts(array $a, array $b): array {
    $byKey = [];
    foreach (array_merge($a, $b) as $line) {
        $k = (string)$line['key'];
        $qty = max(1, (int)($line['qty'] ?? 1));
        if (isset($byKey[$k])) {
            $byKey[$k]['qty'] = max((int)$byKey[$k]['qty'], $qty);
        } else {
            $line['qty'] = $qty;
            $byKey[$k] = $line;
        }
    }
    return array_values($byKey);
}

try {
    if ($method === 'GET') {
        jsonOut(['success' => true, 'items' => jcol($c['cart'] ?? null, [])]);
    }

    if ($method === 'POST') {
        $body = jsonBody();
        $incoming = sanitizeCart($body['items'] ?? []);
        $mode = (($body['mode'] ?? 'merge') === 'replace') ? 'replace' : 'merge';

        if ($mode === 'merge') {
            $existing = sanitizeCart(jcol($c['cart'] ?? null, []));
            $final = mergeCarts($existing, $incoming);
        } else {
            $final = $incoming;
        }

        $db->execute("UPDATE customers SET cart=? WHERE id=?", [json_encode($final), $c['id']]);
        jsonOut(['success' => true, 'items' => $final]);
    }

    jsonErr('Method not allowed', 405);
} catch (Throwable $e) {
    // Cart sync is best-effort and must never break login/checkout. If the `cart` column
    // is missing (migration not yet run) or any DB error occurs, echo the incoming items
    // back so the client keeps working in local-only mode.
    $fallback = (isset($incoming) && is_array($incoming)) ? $incoming : [];
    jsonOut(['success' => true, 'items' => $fallback, 'persisted' => false]);
}
