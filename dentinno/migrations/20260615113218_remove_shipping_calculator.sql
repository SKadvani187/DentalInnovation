-- Remove the Shipping Calculator from the admin: the standalone pages/shipping_calculator.php
-- page was deleted (the calculator tab inside pages/shipping.php was also removed). This drops
-- its RBAC registry row + permission grants so the broken sidebar link / 404 goes away.
-- Idempotent: matches nothing on re-run.


DELETE FROM role_permissions
 WHERE page_id IN (SELECT id FROM page_registry WHERE page_key='shipping_calculator');

DELETE FROM page_registry WHERE page_key='shipping_calculator';

-- Bump the live RBAC version so already-logged-in sessions reload their menu/permissions.
UPDATE rbac_meta SET perm_version = perm_version + 1 WHERE id=1;
