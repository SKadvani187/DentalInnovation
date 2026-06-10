<?php
// GET  /api/v1/questions.php?product=slug -> answered + approved customer questions
// POST /api/v1/questions.php              -> submit a question { product, question, name?, email? }
//                                           (saved pending; appears after admin answers + approves)
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$db = db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $b = jsonBody();

    // Helpful vote: { action:'vote', id, dir:'up'|'down', undo?:bool }. Dedup is enforced
    // client-side (localStorage); this just adjusts the stored counter (never below 0).
    if (($b['action'] ?? '') === 'vote') {
        $id  = (int)($b['id'] ?? 0);
        $dir = ($b['dir'] ?? '') === 'down' ? 'down' : 'up';
        $col = $dir === 'down' ? 'helpful_down' : 'helpful_up';
        $delta = !empty($b['undo']) ? -1 : 1;
        if ($id <= 0) jsonErr('Invalid question', 422);
        $db->execute("UPDATE product_questions SET $col = GREATEST(0, $col + ?) WHERE id=? AND is_approved=1", [$delta, $id]);
        $row = $db->fetchOne("SELECT helpful_up, helpful_down FROM product_questions WHERE id=?", [$id]);
        jsonOut(['success' => true, 'up' => (int)($row['helpful_up'] ?? 0), 'down' => (int)($row['helpful_down'] ?? 0)]);
    }

    $slug = trim((string)($b['product'] ?? ''));
    $q    = trim((string)($b['question'] ?? ''));
    $name = trim((string)($b['name'] ?? ''));
    $mail = trim((string)($b['email'] ?? ''));
    if ($slug === '' || $q === '') jsonErr('Question is required.', 422);

    $prod = $db->fetchOne("SELECT id FROM products WHERE slug=? AND is_active=1", [$slug]);
    if (!$prod) jsonErr('Product not found', 404);

    $cust = authCustomer();
    $db->execute(
        "INSERT INTO product_questions (product_id, customer_id, asker_name, asker_email, question)
         VALUES (?,?,?,?,?)",
        [$prod['id'], $cust['id'] ?? null, $name ?: ($cust['name'] ?? null), $mail ?: ($cust['email'] ?? null), $q]
    );
    jsonOut(['success' => true, 'message' => "Thanks! We'll answer your question soon."]);
}

$slug = qstr('product');
if ($slug === '') jsonErr('Missing product', 400);

$prod = $db->fetchOne("SELECT id FROM products WHERE slug=?", [$slug]);
if (!$prod) jsonOut(['success' => true, 'questions' => []]);

$rows = $db->fetchAll(
    "SELECT * FROM product_questions WHERE product_id=? AND is_approved=1 AND is_answered=1 ORDER BY answered_at DESC, created_at DESC",
    [(int)$prod['id']]
);
jsonOut(['success' => true, 'questions' => array_map('mapQuestion', $rows)]);
