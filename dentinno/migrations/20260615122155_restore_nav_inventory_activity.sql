-- The 2026-06-15 RBAC nav rewrite (DB-driven page_registry) accidentally omitted two pages
-- that existed in the old hardcoded sidebar: Inventory and Activity Log. The page files were
-- never deleted, they just lost their sidebar links. This re-registers them.
-- Idempotent: INSERT IGNORE + version bump.


INSERT IGNORE INTO page_registry
 (page_key, label, url, icon, nav_group, group_order, sort_order,
  supports_view, supports_create, supports_edit, supports_delete,
  is_super_only, show_in_nav, is_active, is_system, description) VALUES
('inventory','Inventory','pages/inventory.php','fa-warehouse','CATALOG',1,5, 1,0,0,0, 0,1,1,0,'Inventory ledger / stock movements (read-only).'),
('activity','Activity Log','pages/activity.php','fa-clock-rotate-left','REPORTS',6,1, 1,0,0,0, 1,1,1,1,'Admin activity audit log. SUPER ADMIN ONLY.');

-- Inventory is not super-only — grant admin + staff view (mirrors the old sidebar access).
INSERT IGNORE INTO role_permissions (role_id, page_id, can_view, can_create, can_edit, can_delete)
SELECT r.id, p.id, 1,0,0,0 FROM roles r JOIN page_registry p ON p.page_key='inventory'
 WHERE r.slug IN ('admin','staff');

UPDATE rbac_meta SET perm_version = perm_version + 1 WHERE id=1;
