-- =====================================================================
-- RBAC Redesign — Phase 4: admin UIs wiring
--   1) admin_users.role: ENUM -> VARCHAR so CUSTOM role slugs fit (role_id stays the FK source of truth).
--   2) Activate the Roles + Permissions admin pages (their files now exist).
-- Idempotent: MODIFY to the same type is a no-op; UPDATE is repeatable.
-- =====================================================================

ALTER TABLE admin_users MODIFY COLUMN role VARCHAR(80) DEFAULT 'staff';

UPDATE page_registry SET is_active = 1 WHERE page_key IN ('roles', 'permissions');
