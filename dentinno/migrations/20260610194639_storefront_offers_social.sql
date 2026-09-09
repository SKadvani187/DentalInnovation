-- Fix: Offers admin save failing with "Unexpected token '<' ... is not valid JSON".
-- The offers admin (pages/offers.php) and storefront API (api/v1/offers.php, _map.php)
-- read/write social_mode, social_count and is_top_deal, but these columns were never
-- added to the `offers` table (only the base columns exist in database_storefront.sql).
-- The INSERT/UPDATE therefore failed with "Unknown column", the uncaught PDOException
-- printed an HTML error, and the JSON response was corrupted.
--
-- Named to sort right AFTER database_storefront.sql so the offers table exists first on
-- a clean install; on an existing DB it just runs as a pending migration.

ALTER TABLE offers
    ADD COLUMN IF NOT EXISTS social_mode  VARCHAR(10)  NOT NULL DEFAULT 'live' AFTER sort_order,
    ADD COLUMN IF NOT EXISTS social_count INT          NOT NULL DEFAULT 0      AFTER social_mode,
    ADD COLUMN IF NOT EXISTS is_top_deal  TINYINT(1)   NOT NULL DEFAULT 0      AFTER social_count;
