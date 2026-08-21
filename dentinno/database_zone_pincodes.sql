-- ───────────────────────────────────────────────────────────────────────────
-- Seed shipping_zones.pincodes (pincode prefixes) so zone-based shipping actually
-- resolves at checkout. The engine (api/v1/_pricing.php → resolveShippingZone) matches
-- a delivery pincode against these JSON prefix arrays (longest prefix wins); it does
-- NOT use states[]. Before this, pincodes was NULL on every zone, so zone-specific
-- shipping rules could never fire.
--
-- Prefixes below are first-2-digit Indian PIN regions (illustrative — adjust in the
-- admin Zones tab as needed). Idempotent: only fills zones whose pincodes are empty.
-- ───────────────────────────────────────────────────────────────────────────
USE dentinno_crm;

-- West India (Gujarat/Maharashtra/Rajasthan/Goa) — 36-39 GJ, 40-44 MH, 30-34 RJ, 403 GA
UPDATE shipping_zones SET pincodes = '["36","37","38","39","40","41","42","43","44","30","31","32","33","34","403"]'
 WHERE name = 'West India' AND (pincodes IS NULL OR pincodes = '' OR pincodes = '[]');

-- Metro Cities (MH 40, DL 11, KA 56, TN 60, WB 70)
UPDATE shipping_zones SET pincodes = '["40","11","56","60","70"]'
 WHERE name = 'Metro Cities' AND (pincodes IS NULL OR pincodes = '' OR pincodes = '[]');

-- North India (UP 20-28, RJ 30-34, PB 14-16, HR 12-13, DL 11)
UPDATE shipping_zones SET pincodes = '["20","21","22","23","24","25","26","27","28","30","31","32","33","34","14","15","16","12","13","11"]'
 WHERE name = 'North India' AND (pincodes IS NULL OR pincodes = '' OR pincodes = '[]');

-- South India (KA 56-59, TN 60-64, KL 67-69, AP/TS 50-53)
UPDATE shipping_zones SET pincodes = '["56","57","58","59","60","61","62","63","64","67","68","69","50","51","52","53"]'
 WHERE name = 'South India' AND (pincodes IS NULL OR pincodes = '' OR pincodes = '[]');

-- All India (single-digit catch-all 1-9 — lowest priority since shortest prefix)
UPDATE shipping_zones SET pincodes = '["1","2","3","4","5","6","7","8","9"]'
 WHERE name = 'All India' AND (pincodes IS NULL OR pincodes = '' OR pincodes = '[]');
