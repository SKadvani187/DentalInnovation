<?php
// GET /api/v1/delivery.php?pincode=395006
// -> { serviceable, days, cod, label, eta } using the longest matching pincode prefix.
require_once __DIR__ . '/_bootstrap.php';

$pin = preg_replace('/\D/', '', qstr('pincode'));
if (strlen($pin) !== 6) jsonErr('Enter a valid 6-digit pincode.', 422);

// Longest-prefix wins: a row for "395006" beats "395" beats "39".
$rows = db()->fetchAll(
    "SELECT * FROM delivery_pincodes WHERE is_active=1 ORDER BY CHAR_LENGTH(pincode_prefix) DESC"
);
$match = null;
foreach ($rows as $r) {
    $pfx = (string)$r['pincode_prefix'];
    if ($pfx !== '' && strpos($pin, $pfx) === 0) { $match = $r; break; }
}

if (!$match) {
    jsonOut(['success' => true, 'serviceable' => false, 'pincode' => $pin]);
}

$days = max(0, (int)$match['delivery_days']);
$eta = new DateTime('now');
$eta->modify("+{$days} day");

jsonOut([
    'success'     => true,
    'serviceable' => true,
    'pincode'     => $pin,
    'days'        => $days,
    'cod'         => (bool)$match['cod_available'],
    'label'       => $match['label'] ?: null,
    'eta'         => $eta->format('Y-m-d'),
]);
