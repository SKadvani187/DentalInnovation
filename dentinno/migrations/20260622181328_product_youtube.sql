-- Per-product YouTube video (embed URL + gallery position) scraped from the live site.
-- Shown on the product page when present. Idempotent: only adds the column if missing.
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'youtube_video_url'
);
SET @ddl := IF(@col_exists = 0,
  'ALTER TABLE products ADD COLUMN youtube_video_url VARCHAR(500) NULL AFTER catalogue_url',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
