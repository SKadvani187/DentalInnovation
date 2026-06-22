-- Key Specifications stored as RAW HTML (the live-site section can be prose / lists / "Label: value"
-- lines — not always a {key,value} table), rendered via RichText so it matches the source exactly.
-- Separate from the JSON-validated `key_specifications` column so neither constraint nor contract
-- is broken. Idempotent: only adds the column if missing.
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'key_specifications_html'
);
SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE products ADD COLUMN key_specifications_html LONGTEXT NULL AFTER key_specifications',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
