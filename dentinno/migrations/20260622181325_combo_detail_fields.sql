-- Give combos the same rich detail fields as products, so a combo opens as a full product page
-- (Product Highlights, Description, Key Specifications, Directions, Packaging, Warranty, Key
-- Features) like the reference site — not just a card. MariaDB supports ADD COLUMN IF NOT EXISTS,
-- so this is idempotent.
ALTER TABLE combos ADD COLUMN IF NOT EXISTS sku VARCHAR(100) NULL;
ALTER TABLE combos ADD COLUMN IF NOT EXISTS short_description VARCHAR(500) NULL;
ALTER TABLE combos ADD COLUMN IF NOT EXISTS full_description LONGTEXT NULL;
ALTER TABLE combos ADD COLUMN IF NOT EXISTS features LONGTEXT NULL;
ALTER TABLE combos ADD COLUMN IF NOT EXISTS key_specifications_html LONGTEXT NULL;
ALTER TABLE combos ADD COLUMN IF NOT EXISTS directions_for_use TEXT NULL;
ALTER TABLE combos ADD COLUMN IF NOT EXISTS packing_info TEXT NULL;
ALTER TABLE combos ADD COLUMN IF NOT EXISTS warranty_info TEXT NULL;
ALTER TABLE combos ADD COLUMN IF NOT EXISTS key_features TEXT NULL;
ALTER TABLE combos ADD COLUMN IF NOT EXISTS hover_image VARCHAR(500) NULL;
