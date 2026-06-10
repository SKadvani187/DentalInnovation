-- Product catalogue PDF (admin Content tab) -> storefront "Open Catalogue" button.
-- Stores the uploaded PDF URL; the button is hidden when this is empty. Idempotent.
USE dentinno_crm;

SET @add := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='products' AND COLUMN_NAME='catalogue_url');
SET @sql := IF(@add=0, "ALTER TABLE products ADD COLUMN catalogue_url VARCHAR(500) DEFAULT NULL AFTER direction_of_use", "DO 0");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
