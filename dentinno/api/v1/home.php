<?php
// GET /api/v1/home.php -> combined feed for storefront home.
// Product sections are resolved from the homeSections setting:
//   source "featured" -> is_featured=1 ; "new" -> is_new=1 ; otherwise -> category slug.
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$db = db();
$sel = "p.*, c.slug AS category_slug FROM products p LEFT JOIN categories c ON p.category_id=c.id";

$bySource = function (string $source) use ($db, $sel) {
    if ($source === 'featured') {
        $rows = $db->fetchAll("SELECT $sel WHERE p.is_active=1 AND p.is_featured=1 ORDER BY p.total_sales DESC, p.id");
    } elseif ($source === 'new') {
        $rows = $db->fetchAll("SELECT $sel WHERE p.is_active=1 AND p.is_new=1 ORDER BY p.id DESC");
    } else { // category slug
        $rows = $db->fetchAll("SELECT $sel WHERE p.is_active=1 AND c.slug=? ORDER BY p.id", [$source]);
    }
    return array_map('mapProduct', $rows);
};

// Read configured home sections (fallback to legacy set if unset)
$hs = $db->fetchOne("SELECT svalue FROM site_settings WHERE skey='homeSections'");
$homeSections = $hs ? json_decode($hs['svalue'], true) : [];

// Build product lists keyed by section `key` for any productSection block.
$sections = [];
foreach ($homeSections as $s) {
    if (($s['type'] ?? '') === 'productSection' && !empty($s['source'])) {
        $sections[$s['key']] = $bySource($s['source']);
    }
}
// Legacy keys (so older frontends keep working)
if (!isset($sections['bestsellers'])) $sections['bestsellers'] = $bySource('featured');
if (!isset($sections['newArrivals'])) $sections['newArrivals'] = $bySource('new');

jsonOut([
    'success' => true,
    'sections' => $sections,
    'categories'   => array_map('mapCategory', $db->fetchAll("SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order, name")),
    'testimonials' => array_map('mapTestimonial', $db->fetchAll("SELECT * FROM testimonials WHERE is_active=1 ORDER BY sort_order")),
]);
