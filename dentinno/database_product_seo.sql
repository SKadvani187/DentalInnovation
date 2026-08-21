-- SEO fields for products (admin-editable meta tags surfaced on the storefront PDP).
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS meta_title VARCHAR(255) DEFAULT NULL AFTER slug,
  ADD COLUMN IF NOT EXISTS meta_description VARCHAR(320) DEFAULT NULL AFTER meta_title;
