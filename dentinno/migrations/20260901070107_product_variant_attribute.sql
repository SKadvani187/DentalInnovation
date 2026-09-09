-- product_variant_attribute
--
-- The name of the attribute a product's variants vary by — "Size", "Pack", "Diameter", "Type".
-- The storefront shows it beside each option ("Size : 24 x 48 mm"), matching the reference
-- catalogue, which carries the same thing as `variant_title`. NULL for a product whose options
-- need no heading, or one with no variants at all.
--
-- The per-variant fields (sku, images) live inside the existing `variants` JSON column, so they
-- need no schema change here.

ALTER TABLE products ADD COLUMN IF NOT EXISTS variant_attribute VARCHAR(60) NULL AFTER variants;
