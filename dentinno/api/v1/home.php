<?php
// GET /api/v1/home.php -> combined feed for storefront home (one round-trip).
// Mirrors App.jsx sections: bestsellers, newArrivals, implantology, handpiece, matrix, endodontics.
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$db = db();
$sel = "p.*, c.slug AS category_slug FROM products p LEFT JOIN categories c ON p.category_id=c.id";

// Section -> product slug-prefix mapping (matches React data id prefixes)
$byPrefix = function (string $prefix) use ($db, $sel) {
    $rows = $db->fetchAll("SELECT $sel WHERE p.is_active=1 AND p.slug LIKE ? ORDER BY p.id", ["$prefix%"]);
    return array_map('mapProduct', $rows);
};
$byCategory = function (string $catSlug) use ($db, $sel) {
    $rows = $db->fetchAll("SELECT $sel WHERE p.is_active=1 AND c.slug=? ORDER BY p.id", [$catSlug]);
    return array_map('mapProduct', $rows);
};

jsonOut([
    'success' => true,
    'sections' => [
        'bestsellers'  => $byPrefix('p-'),
        'newArrivals'  => $byPrefix('n-'),
        'implantology' => $byPrefix('i-'),
        'handpieces'   => $byPrefix('h-'),
        'matrixSystem' => $byPrefix('m-'),
        'endodontics'  => $byPrefix('e-'),
    ],
    'categories'   => array_map('mapCategory', $db->fetchAll("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order, name")),
    'testimonials' => array_map('mapTestimonial', $db->fetchAll("SELECT * FROM testimonials WHERE is_active=1 ORDER BY sort_order")),
]);
