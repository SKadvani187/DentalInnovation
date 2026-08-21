<?php
// Verify Phase 1 RBAC seed. CLI only.
if (php_sapi_name() !== 'cli') { http_response_code(403); die("CLI only\n"); }
require __DIR__ . '/../includes/config.php';

echo "== roles ==\n";
foreach (db()->fetchAll("SELECT id,name,slug,is_super,is_system FROM roles ORDER BY id") as $r)
    echo sprintf("  #%d %-12s super=%d system=%d\n", $r['id'], $r['slug'], $r['is_super'], $r['is_system']);

echo "\n== page_registry (" . db()->fetchOne("SELECT COUNT(*) c FROM page_registry")['c'] . " pages) ==\n";
foreach (db()->fetchAll("SELECT page_key,nav_group,supports_view v,supports_create c,supports_edit e,supports_delete d,is_super_only so FROM page_registry ORDER BY group_order,sort_order") as $p)
    echo sprintf("  %-20s %-10s V%d C%d E%d D%d %s\n", $p['page_key'], $p['nav_group'], $p['v'], $p['c'], $p['e'], $p['d'], $p['so'] ? '[SUPER-ONLY]' : '');

foreach (['admin','staff'] as $slug) {
    echo "\n== role_permissions: $slug ==\n";
    $rows = db()->fetchAll(
        "SELECT pr.page_key, rp.can_view v, rp.can_create c, rp.can_edit e, rp.can_delete d
           FROM role_permissions rp
           JOIN roles ro ON ro.id=rp.role_id
           JOIN page_registry pr ON pr.id=rp.page_id
          WHERE ro.slug=? ORDER BY pr.group_order, pr.sort_order", [$slug]);
    foreach ($rows as $r) echo sprintf("  %-20s V%d C%d E%d D%d\n", $r['page_key'], $r['v'], $r['c'], $r['e'], $r['d']);
    echo "  (total " . count($rows) . " pages granted)\n";
}

echo "\n== admin_users backfill ==\n";
foreach (db()->fetchAll("SELECT au.id, au.name, au.role, au.role_id, r.slug FROM admin_users au LEFT JOIN roles r ON r.id=au.role_id ORDER BY au.id") as $u)
    echo sprintf("  #%d %-14s role='%s' role_id=%s -> %s %s\n", $u['id'], $u['name'], $u['role'], $u['role_id'] ?? 'NULL', $u['slug'] ?? 'NULL',
        ($u['role'] === $u['slug']) ? 'OK' : '*** MISMATCH ***');

echo "\n== rbac_meta ==\n";
print_r(db()->fetchOne("SELECT * FROM rbac_meta"));
