-- Per-product quantity-discount tiers (like the reference site's "Available Offers" table).
-- JSON array of {minQty, rate, label}; when set, these override the global tierOffers for that
-- product in both the storefront display and the server-authoritative order total.
ALTER TABLE products
  ADD COLUMN bulk_offers JSON NULL AFTER variants;
