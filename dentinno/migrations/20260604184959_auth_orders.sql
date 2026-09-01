-- Phase 4: customer auth + storefront order support.

-- Add auth token to customers (phone is the login key; phone already exists).
ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS api_token VARCHAR(64) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS addresses JSON DEFAULT NULL;

-- Ensure phone is unique for login lookups (skip silently if dup data exists).
-- (Wrapped: if it fails due to existing duplicate phones, handle manually.)
-- ALTER TABLE customers ADD UNIQUE KEY uq_customers_phone (phone);

-- order_items.product_id must allow NULL for non-catalog lines (e.g. combos).
ALTER TABLE order_items MODIFY product_id INT NULL;

-- Add variant + slug snapshot to order_items for storefront orders.
ALTER TABLE order_items
  ADD COLUMN IF NOT EXISTS product_slug VARCHAR(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS variant VARCHAR(120) DEFAULT NULL;
