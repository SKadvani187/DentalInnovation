<?php
// GET /api/v1/categories.php -> all active categories (React shape)
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$rows = db()->fetchAll("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order, name");
jsonOut(['success' => true, 'categories' => array_map('mapCategory', $rows)]);
