<?php
// Verify rbacCrudVerb() maps every real handler action to the intended CRUD verb. CLI only.
if (php_sapi_name() !== 'cli') { http_response_code(403); die("CLI only\n"); }
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/rbac.php';

$cases = [
    // [action, data, expected]
    ['save', [], 'create'],                 ['save', ['id'=>5], 'edit'],
    ['delete', [], 'delete'],               ['restore', [], 'edit'],
    ['toggle', [], 'edit'],                 ['bulk', ['op'=>'delete'], 'delete'],
    ['bulk', ['op'=>'activate'], 'edit'],
    // products
    ['approve_review', [], 'edit'],         ['delete_review', [], 'delete'],
    ['get_reviews', [], 'view'],
    // coupons
    ['generate', [], 'create'],
    // orders
    ['update_status', [], 'edit'],          ['update_payment', [], 'edit'],
    ['update_tracking', [], 'edit'],        ['save_notes', ['id'=>1], 'edit'],
    ['bulk_status', [], 'edit'],
    // reviews
    ['approve', [], 'edit'],                ['verify', [], 'edit'],
    ['bulk_approve', [], 'edit'],           ['bulk_delete', [], 'delete'],
    // questions
    ['answer', [], 'edit'],
    // messages / bulk_quotes
    ['read', [], 'edit'],                   ['status', [], 'edit'],
    // events
    ['change_status', [], 'edit'],          ['mark_attended', [], 'edit'],
    ['get_registrations', [], 'view'],
    // courses
    ['toggle_status', [], 'edit'],          ['save_module', [], 'create'],
    ['save_module', ['id'=>3], 'edit'],     ['delete_module', [], 'delete'],
    ['get_enrollments', [], 'view'],        ['get_modules', [], 'view'],
    // shipping
    ['save_method', [], 'create'],          ['save_method', ['id'=>2], 'edit'],
    ['delete_method', [], 'delete'],        ['toggle_method', [], 'edit'],
    ['save_zone', [], 'create'],            ['save_rule', ['id'=>9], 'edit'],
    ['delete_rule', [], 'delete'],          ['save_pincode', [], 'create'],
    ['delete_pincode', [], 'delete'],       ['calc', [], 'view'],
];
$pass=0; $fail=0;
foreach ($cases as [$a, $d, $want]) {
    $got = rbacCrudVerb($a, $d);
    $ok = ($got === $want); $ok ? $pass++ : $fail++;
    printf("  [%s] %-22s %-18s => %-7s (want %s)\n", $ok?'PASS':'FAIL', $a, json_encode($d), $got, $want);
}
echo "\n=== $pass passed, $fail failed ===\n";
exit($fail ? 1 : 0);
