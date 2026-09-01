-- ───────────────────────────────────────────────────────────────────────────
-- Extra product content fields (admin Content tab) -> product page accordions.
-- key_features, warranty_no, direction_of_use. Each renders as its own accordion on
-- the storefront product page when filled (hidden when blank). Idempotent.
-- ───────────────────────────────────────────────────────────────────────────

SET @add := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='key_features');
SET @sql := IF(@add=0, "ALTER TABLE products ADD COLUMN key_features TEXT DEFAULT NULL AFTER warranty_info", "DO 0");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='warranty_no');
SET @sql := IF(@add=0, "ALTER TABLE products ADD COLUMN warranty_no VARCHAR(190) DEFAULT NULL AFTER key_features", "DO 0");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @add := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='direction_of_use');
SET @sql := IF(@add=0, "ALTER TABLE products ADD COLUMN direction_of_use TEXT DEFAULT NULL AFTER warranty_no", "DO 0");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
