<?php
// GET /api/v1/combos.php           -> all combos
// GET /api/v1/combos.php?slug=c-001 -> single combo
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$slug = qstr('slug');
if ($slug !== '') {
    $row = db()->fetchOne("SELECT * FROM combos WHERE slug=? AND is_active=1", [$slug]);
    if (!$row) jsonErr('Combo not found', 404);
    jsonOut(['success' => true, 'combo' => mapCombo($row)]);
}
$rows = db()->fetchAll("SELECT * FROM combos WHERE is_active=1 ORDER BY sort_order");
jsonOut(['success' => true, 'combos' => array_map('mapCombo', $rows)]);
