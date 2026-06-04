<?php
// GET /api/v1/testimonials.php -> active testimonials
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$rows = db()->fetchAll("SELECT * FROM testimonials WHERE is_active=1 ORDER BY sort_order");
jsonOut(['success' => true, 'testimonials' => array_map('mapTestimonial', $rows)]);
