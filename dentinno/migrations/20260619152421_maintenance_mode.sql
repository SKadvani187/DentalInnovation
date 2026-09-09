-- Maintenance mode flag.
-- site_settings.maintenanceMode = { enabled: bool, title: string, message: string }
-- When enabled = true:
--   * Admin panel: every non-super-admin (role_id != 1) sees a maintenance page.
--   * Storefront: all visitors see the maintenance page.
--   * Super admin (roles.is_super = 1) bypasses always.

INSERT INTO site_settings (skey, svalue)
VALUES (
    'maintenanceMode',
    JSON_OBJECT(
        'enabled', FALSE,
        'title',   'We''ll be back soon',
        'message', 'Our site is undergoing scheduled maintenance. Please check back in a little while.'
    )
)
ON DUPLICATE KEY UPDATE svalue = svalue;
