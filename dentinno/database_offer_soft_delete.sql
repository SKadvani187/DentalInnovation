-- Soft-delete for offers: keep the row (and its offer_items gift rows) so an accidentally
-- deleted offer can be restored. A deleted offer is also set is_active=0, so the storefront
-- (which filters is_active=1) excludes it automatically.
ALTER TABLE offers ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
