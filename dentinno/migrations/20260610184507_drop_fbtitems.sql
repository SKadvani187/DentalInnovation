-- Remove the obsolete global "fbtItems" setting. Frequently-Bought-Together is now
-- per-product (see product_fbt table + api/v1/fbt.php), so the single global cross-sell
-- list is no longer read by the storefront. Idempotent.

DELETE FROM site_settings WHERE skey = 'fbtItems';
