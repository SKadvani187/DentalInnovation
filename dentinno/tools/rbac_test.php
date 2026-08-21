<?php
// Phase 2 logic test: load each role and assert can()/hasPermission() behave correctly. CLI only.
if (php_sapi_name() !== 'cli') { http_response_code(403); die("CLI only\n"); }
require __DIR__ . '/../includes/auth.php';   // loads config + rbac + defines hasPermission()

$pass = 0; $fail = 0;
function check($label, $got, $want) {
    global $pass, $fail;
    $ok = ($got === $want);
    if ($ok) $pass++; else $fail++;
    printf("  [%s] %-55s got=%s want=%s\n", $ok ? 'PASS' : 'FAIL', $label, var_export($got, true), var_export($want, true));
}

function roleId($slug){ $r = db()->fetchOne("SELECT id FROM roles WHERE slug=?", [$slug]); return (int)($r['id'] ?? 0); }

// ---- SUPER ADMIN ----
echo "== super_admin ==\n";
$_SESSION = ['admin_id'=>1]; rbacLoad(roleId('super_admin'));
check('userIsSuper', userIsSuper(), true);
check("can(settings,edit)", can('settings','edit'), true);
check("can(refunds,edit)",  can('refunds','edit'),  true);
check("can(products,delete)",can('products','delete'),true);
check("hasPermission(manage_admins)", hasPermission('manage_admins'), true);

// ---- ADMIN ----
echo "\n== admin ==\n";
$_SESSION = ['admin_id'=>2]; rbacLoad(roleId('admin'));
check('userIsSuper', userIsSuper(), false);
check("can(products,create)", can('products','create'), true);
check("can(products,delete)", can('products','delete'), true);
check("can(orders,edit)",     can('orders','edit'),     true);
check("can(orders,create)",   can('orders','create'),   false);  // orders: view+edit only
check("can(reports,view)",    can('reports','view'),    true);
check("can(settings,view)",   can('settings','view'),   false);  // super-only
check("can(refunds,edit)",    can('refunds','edit'),    false);  // super-only
check("hasPermission(manage_products)", hasPermission('manage_products'), true);
check("hasPermission(manage_settings)", hasPermission('manage_settings'), false);
check("hasPermission(view_reports)",    hasPermission('view_reports'),    true);
check("hasPermission(manage_refunds)",  hasPermission('manage_refunds'),  false);

// ---- STAFF ----
echo "\n== staff ==\n";
$_SESSION = ['admin_id'=>3]; rbacLoad(roleId('staff'));
check('userIsSuper', userIsSuper(), false);
check("can(orders,edit)",       can('orders','edit'),       true);
check("can(products,view)",     can('products','view'),     false); // staff can't see products
check("can(products,create)",   can('products','create'),   false);
check("can(reports,view)",      can('reports','view'),      false); // staff: no reports
check("can(testimonials,create)",can('testimonials','create'),true);
check("can(reviews,edit)",      can('reviews','edit'),      true);
check("can(reviews,create)",    can('reviews','create'),    false); // reviews: no create
check("hasPermission(manage_orders)",  hasPermission('manage_orders'),  true);
check("hasPermission(manage_products)",hasPermission('manage_products'),false);
check("hasPermission(view_reports)",   hasPermission('view_reports'),   false);
check("hasPermission(manage_content)", hasPermission('manage_content'), true);

// ---- navTree shape for staff (groups visible) ----
echo "\n== navTree(staff) groups ==\n";
$tree = navTree();
echo "  groups: " . implode(', ', array_keys($tree)) . "\n";
$flat = array_merge(...array_values($tree ?: [[]]));
echo "  visible pages: " . count($flat) . " (expected 12)\n";
check('staff sees CATALOG (testimonials only)', isset($tree['CATALOG']), true);
check('staff sees SALES group', isset($tree['SALES']), true);
check('staff does NOT see REPORTS group', isset($tree['REPORTS']), false);
check('staff does NOT see SYSTEM group', isset($tree['SYSTEM']), false);

echo "\n=== $pass passed, $fail failed ===\n";
exit($fail ? 1 : 0);
