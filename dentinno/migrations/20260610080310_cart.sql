-- Server-side cart for logged-in customers.
-- Stores the full cart line-item array as JSON on the customer row (mirrors customers.wishlist).
-- Idempotent: ADD COLUMN IF NOT EXISTS, so re-running is safe.

ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS cart JSON DEFAULT NULL;
