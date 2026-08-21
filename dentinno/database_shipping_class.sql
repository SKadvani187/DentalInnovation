-- ───────────────────────────────────────────────────────────────────────────
-- Migration: connect the product "Shipping Class" dropdown to the shipping engine.
-- Adds products.shipping_class and shipping_rules.product_class.
-- Idempotent (safe to re-run) and compatible with MariaDB AND MySQL 8.
-- Prerequisite: the shipping engine tables already exist (database_additions.sql).
-- ───────────────────────────────────────────────────────────────────────────

-- 1) Make sure shipping_rules.rule_type allows the 'product' value (no-op if it already does).
ALTER TABLE shipping_rules
    MODIFY rule_type ENUM('weight','price','quantity','product') NOT NULL;

-- 2) products.shipping_class — add only if missing (guarded for MySQL 8 + MariaDB).
SET @add_pc := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'products'
      AND COLUMN_NAME  = 'shipping_class'
);
SET @sql := IF(@add_pc = 0,
    "ALTER TABLE products ADD COLUMN shipping_class ENUM('standard','bulky','fragile','express_only','free') NOT NULL DEFAULT 'standard'",
    "DO 0"
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) shipping_rules.product_class — add only if missing.
SET @add_rc := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'shipping_rules'
      AND COLUMN_NAME  = 'product_class'
);
SET @sql := IF(@add_rc = 0,
    "ALTER TABLE shipping_rules ADD COLUMN product_class ENUM('standard','bulky','fragile','express_only','free') DEFAULT NULL",
    "DO 0"
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4) Backfill any existing rows so the class is never NULL/empty.
UPDATE products SET shipping_class = 'standard'
WHERE shipping_class IS NULL OR shipping_class = '';
