-- GST invoice support: a per-product HSN/SAC code (printed on the tax invoice). The seller
-- GSTIN + default GST rate live in site_settings (company.gstin, taxConfig.rate) — no schema
-- change needed for those. This only adds the HSN column.
-- Idempotent: ADD COLUMN IF NOT EXISTS.

USE dentinno_crm;

ALTER TABLE products
  ADD COLUMN IF NOT EXISTS hsn_code VARCHAR(12) DEFAULT NULL;

-- Snapshot the HSN onto the order line at purchase time (so a later product edit doesn't
-- change a past invoice). NULL on old rows / non-catalog lines.
ALTER TABLE order_items
  ADD COLUMN IF NOT EXISTS hsn_code VARCHAR(12) DEFAULT NULL;
