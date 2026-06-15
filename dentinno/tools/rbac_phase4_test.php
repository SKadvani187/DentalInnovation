<?php
// Phase 4 end-to-end: custom role -> partial grants -> can()/navTree() reflect it. CLI only.
if (php_sapi_name() !== 'cli') { http_response_code(403); die("CLI only\n"); }
require __DIR__ . '/../includes/auth.php';   // loads config + rbac

$pass=0; $fail=0;
function check($l,$g,$w){ global $pass,$fail; $ok=($g===$w); $ok?$pass++:$fail++; printf("  [%s] %-45s got=%s want=%s\n",$ok?'PASS':'FAIL',$l,var_export($g,true),var_export($w,true)); }

// registry rows active again?
$ra = db()->fetchAll("SELECT page_key, is_active FROM page_registry WHERE page_key IN ('roles','permissions')");
foreach ($ra as $r) check("registry {$r['page_key']} active", (int)$r['is_active'], 1);

// --- create a throwaway custom role ---
db()->execute("DELETE FROM roles WHERE slug='zz-test-catalog'");   // clean any prior run
$rid = (int) db()->insert("INSERT INTO roles (name, slug, description, is_super, is_system, is_active) VALUES ('ZZ Test Catalog','zz-test-catalog','test',0,0,1)");
echo "  (created role #$rid)\n";

// grant products: View + Edit only (no create/delete); nothing else
function pageId($k){ return (int) db()->fetchOne("SELECT id FROM page_registry WHERE page_key=?", [$k])['id']; }
db()->execute("INSERT INTO role_permissions (role_id,page_id,can_view,can_create,can_edit,can_delete) VALUES (?,?,1,0,1,0)", [$rid, pageId('products')]);

// load that role into the session and assert
$_SESSION = ['admin_id'=>999];
rbacLoad($rid);
check('userIsSuper', userIsSuper(), false);
check('can(products,view)',   can('products','view'),   true);
check('can(products,edit)',   can('products','edit'),   true);
check('can(products,create)', can('products','create'), false);
check('can(products,delete)', can('products','delete'), false);
check('can(orders,view)',     can('orders','view'),     false);
check('can(settings,view)',   can('settings','view'),   false);

// navTree should show ONLY CATALOG > Products
$tree = navTree();
$groups = array_keys($tree);
check('navTree groups == [CATALOG]', $groups, ['CATALOG']);
$flat = array_merge(...array_values($tree ?: [[]]));
check('navTree page count == 1', count($flat), 1);
check('navTree page == products', $flat[0]['page_key'] ?? null, 'products');

// rbacCrudVerb sanity for this scenario: a delete attempt would map to 'delete' (which this role lacks)
check("verb('delete')", rbacCrudVerb('delete'), 'delete');
check("can do save(edit)", can('products', rbacCrudVerb('save', ['id'=>5])), true);   // edit -> allowed
check("can do save(create)", can('products', rbacCrudVerb('save', [])), false);       // create -> blocked

// --- cleanup ---
db()->execute("DELETE FROM roles WHERE id=?", [$rid]);  // cascades role_permissions
check('cleanup removed role', (int)(db()->fetchOne("SELECT COUNT(*) c FROM roles WHERE id=?", [$rid])['c']), 0);

echo "\n=== $pass passed, $fail failed ===\n";
exit($fail ? 1 : 0);
