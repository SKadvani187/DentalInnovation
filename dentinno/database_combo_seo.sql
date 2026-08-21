-- SEO fields for combos: an admin-editable meta title/description served to the storefront
-- (consumed once SSR/meta-rendering lands). The URL slug is already a column; the admin form
-- now lets it be edited too (still kept UNIQUE, auto-suffixed on collision).
ALTER TABLE combos ADD COLUMN IF NOT EXISTS meta_title VARCHAR(255) NULL AFTER description;
ALTER TABLE combos ADD COLUMN IF NOT EXISTS meta_description VARCHAR(500) NULL AFTER meta_title;
