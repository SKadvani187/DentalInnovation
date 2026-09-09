-- Remove the obsolete global "freeGifts" setting (threshold-based gift list). Free gifts
-- are now per-product (product_gifts table + api/v1/gifts.php, auto-added in the cart),
-- so the global threshold gift is no longer used by the storefront or admin. Idempotent.

DELETE FROM site_settings WHERE skey = 'freeGifts';
