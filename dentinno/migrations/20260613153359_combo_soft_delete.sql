-- Soft-delete for combos: keep the row (so order history referencing the combo stays intact)
-- but hide it from the admin list and the storefront. A deleted combo is also set is_active=0,
-- so the storefront (which always filters is_active=1) excludes it automatically.
ALTER TABLE combos ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
