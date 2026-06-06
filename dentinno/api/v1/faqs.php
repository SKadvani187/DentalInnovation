<?php
// GET /api/v1/faqs.php?product=slug -> active FAQs for a product (per-product).
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$slug = qstr('product');
if ($slug === '') jsonErr('Missing product', 400);

$prod = db()->fetchOne("SELECT id FROM products WHERE slug=?", [$slug]);
if (!$prod) jsonOut(['success' => true, 'faqs' => []]);

$rows = db()->fetchAll(
    "SELECT * FROM product_faqs WHERE product_id=? AND is_active=1 ORDER BY sort_order, id",
    [(int)$prod['id']]
);

jsonOut(['success' => true, 'faqs' => array_map('mapFaq', $rows)]);
