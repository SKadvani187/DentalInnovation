<?php
// GET /api/v1/settings.php -> all site settings as one object { company, payments, ... featured, premiumCategories }
require_once __DIR__ . '/_bootstrap.php';

$rows = db()->fetchAll("SELECT skey, svalue FROM site_settings");
$out = [];
foreach ($rows as $r) {
    $out[$r['skey']] = jcol($r['svalue'] ?? null, null);
}
// Live counts (real-time, not stored) — used by trust badges "Live count".
$out['liveCounts'] = [
    'products' => (int)(db()->fetchOne("SELECT COUNT(*) c FROM products WHERE is_active=1")['c'] ?? 0),
];
jsonOut(['success' => true, 'settings' => $out]);
