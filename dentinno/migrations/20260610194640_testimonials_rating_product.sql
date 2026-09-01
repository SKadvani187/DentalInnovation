-- Testimonials: add the `product_name` and `rating` columns the admin page writes to.
-- Run via the migrator:  php migrate.php
--
-- Bug fixed: pages/testimonials.php saves with `... product_name=?, rating=? ...`,
-- but the base testimonials table (database_storefront.sql) had neither column.
-- The missing columns made every save/update throw a SQL error, which PHP emitted
-- as HTML ("<br /><b>...") into the JSON response — breaking res.json() on the
-- admin side (Unexpected token '<' ... is not valid JSON).
--
-- Idempotent: ADD COLUMN IF NOT EXISTS, so re-running is safe.
--   * product_name — product the review refers to (optional label)
--   * rating       — star rating 1-5 (admin clamps to this range; default 5)


ALTER TABLE testimonials
  ADD COLUMN IF NOT EXISTS product_name VARCHAR(255) DEFAULT NULL AFTER product_image,
  ADD COLUMN IF NOT EXISTS rating TINYINT DEFAULT 5 AFTER text;