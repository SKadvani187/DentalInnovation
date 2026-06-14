-- Cost (purchase) price per product — enables an approximate gross-margin report. Nullable so
-- existing products are unaffected; margin only counts products that have a cost set.
ALTER TABLE products ADD COLUMN IF NOT EXISTS cost_price DECIMAL(10,2) NULL AFTER price;
