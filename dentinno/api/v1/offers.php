<?php
// GET /api/v1/offers.php -> active offers (offer zone)
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_map.php';

$rows = db()->fetchAll("SELECT * FROM offers WHERE is_active=1 ORDER BY sort_order");
jsonOut(['success' => true, 'offers' => array_map('mapOffer', $rows)]);
