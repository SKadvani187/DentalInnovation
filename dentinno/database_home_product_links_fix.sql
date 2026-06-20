-- Fix: Home page banners/sections opened "Product not found".
--
-- Cause: the hero slides, promo banner grid, premium categories and RF cautery
-- showcase were still linked to the original demo/seed products (slugs p-001,
-- p-002, i-001, ...). Those seed rows were set is_active=0 when the real catalogue
-- (descriptive slugs) was imported. The storefront only serves is_active=1 products,
-- so every click resolved to /product/<inactive-slug> -> no match -> "Product not found".
--
-- Fix: re-link each banner/section to the matching ACTIVE product. The target product
-- per banner was chosen from the banner ARTWORK (the image's advertised product), not
-- the stale link — e.g. the ScaleBlast Pro slide was pointing at p-001 (RF Cautery).
--
-- JSON_SET only touches the id/productId fields; titles, descriptions and images are
-- left untouched. Safe to re-run (idempotent).

-- 1) Hero slides (settings key: heroSlides) -- order matches the stored slide order.
UPDATE site_settings
SET svalue = JSON_SET(svalue,
  '$[0].productId', 'scaleblast-pro',                                       -- ScaleBlast Pro
  '$[1].productId', 'antifog-mirror-pack-of-4',                            -- Antifog Mirror
  '$[2].productId', 'radio-frequency-advance-cautery',                     -- RF Advance Cautery
  '$[3].productId', 'implant-s-pro-physio-with-2-year-warranty',           -- Implant S Pro Physio
  '$[4].productId', 'smart-hex-driver-kit',                                -- Smart Hex Driver
  '$[5].productId', 'youni-mobiflash-pro---dental-photography-device',     -- MobiFlash Pro
  '$[6].productId', 'healing-abutment-korean-d---implant-prosthetic-component') -- Implant Prosthetics
WHERE skey = 'heroSlides';

-- 2) Promo Banner Grid (settings key: banners)
--    left = Implant Surgical Kit, top-right = Implant S Pro, bottom-right = Implant S Lite
UPDATE site_settings
SET svalue = JSON_SET(svalue,
  '$.promo.leftId',        'surgical-kit',
  '$.promo.topRightId',    'implant-s-pro-physio-with-2-year-warranty',
  '$.promo.bottomRightId', 'implant-s-lite-physio---implant-physio-dispenser')
WHERE skey = 'banners';

-- 3) Premium Categories (settings key: premiumCategories) -- Micromotor / Endomotor / Straight Long Handpiece
UPDATE site_settings
SET svalue = JSON_SET(svalue,
  '$[0].id', 'electric-portable-micromotor',
  '$[1].id', 'endomotor',
  '$[2].id', 'surgical-straight-long-handpiece')
WHERE skey = 'premiumCategories';

-- 4) RF Cautery Showcase (settings key: rfSection)
UPDATE site_settings
SET svalue = JSON_SET(svalue, '$.productId', 'radio-frequency-advance-cautery')
WHERE skey = 'rfSection';
