<?php
// GET /api/v1/settings.php -> all site settings as one object { company, payments, ... featured, premiumCategories }
require_once __DIR__ . '/_bootstrap.php';

$rows = db()->fetchAll("SELECT skey, svalue FROM site_settings");
// Private keys that must never be exposed to the public storefront (contain secrets / admin-only).
$PRIVATE = ['otpConfig'];
$out = [];
foreach ($rows as $r) {
    if (in_array($r['skey'], $PRIVATE, true)) continue;
    $out[$r['skey']] = jcol($r['svalue'] ?? null, null);
}
// Live counts (real-time, not stored) — used by trust badges "Live count".
$out['liveCounts'] = [
    'products' => (int)(db()->fetchOne("SELECT COUNT(*) c FROM products WHERE is_active=1")['c'] ?? 0),
];
jsonOut(['success' => true, 'settings' => $out]);
