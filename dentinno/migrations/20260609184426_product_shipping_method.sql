-- ───────────────────────────────────────────────────────────────────────────
-- Connect a product's shipping to the Shipping Management methods.
-- Replaces the hardcoded products.shipping_class enum with a real reference to
-- shipping_methods. NULL = "use the storefront default" (global cheapest applicable
-- method). When set, the engine charges that method's cost for the order.
-- Idempotent. Prereq: database_additions.sql (shipping_methods table).
-- ───────────────────────────────────────────────────────────────────────────

-- Add products.shipping_method_id only if missing (MySQL 8 + MariaDB safe).
SET @add := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'products'
      AND COLUMN_NAME  = 'shipping_method_id'
);
SET @sql := IF(@add = 0,
    "ALTER TABLE products ADD COLUMN shipping_method_id INT DEFAULT NULL",
    "DO 0"
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
