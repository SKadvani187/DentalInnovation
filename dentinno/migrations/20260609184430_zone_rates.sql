-- ───────────────────────────────────────────────────────────────────────────
-- Zone-wise shipping rates (distance-based) on top of the "free above ₹1,000" model.
-- Surat-based dental B2B: nearer zones cheaper, remote dearer. ₹1,000+ is free in
-- every zone. The engine (api/v1/_pricing.php) resolves the order's zone from the
-- delivery pincode and prefers the zone-specific rule over the global "All Zones" one.
--
-- Under ₹1,000:  West ₹49 | Metro ₹79 | North ₹99 | South ₹99 | All-India/remote ₹149
-- ₹1,000+      :  FREE everywhere
--
-- Idempotent: clears any prior zone-specific price rules on the Free Shipping method,
-- then re-inserts. The global All-Zones rules (₹99 / free) stay as the fallback for any
-- pincode that doesn't resolve to a zone. Prereq: database_additions.sql + zone pincodes.
-- ───────────────────────────────────────────────────────────────────────────

SET @m := (SELECT id FROM shipping_methods WHERE type='price' ORDER BY id LIMIT 1);

-- Reset: remove existing zone-specific price rules for this method (keep zone_id IS NULL).
DELETE FROM shipping_rules WHERE method_id=@m AND rule_type='price' AND zone_id IS NOT NULL;

-- Helper zone ids (by name, so this is install-independent).
SET @west  := (SELECT id FROM shipping_zones WHERE name='West India'   LIMIT 1);
SET @metro := (SELECT id FROM shipping_zones WHERE name='Metro Cities' LIMIT 1);
SET @north := (SELECT id FROM shipping_zones WHERE name='North India'  LIMIT 1);
SET @south := (SELECT id FROM shipping_zones WHERE name='South India'  LIMIT 1);
SET @india := (SELECT id FROM shipping_zones WHERE name='All India'    LIMIT 1);

-- West India — ₹49 under ₹1,000, free at/above.
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,is_active) VALUES
(@m,@west,'price',0,999.99,49,0,1),
(@m,@west,'price',1000,NULL,0,1,1);

-- Metro Cities — ₹79 / free.
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,is_active) VALUES
(@m,@metro,'price',0,999.99,79,0,1),
(@m,@metro,'price',1000,NULL,0,1,1);

-- North India — ₹99 / free.
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,is_active) VALUES
(@m,@north,'price',0,999.99,99,0,1),
(@m,@north,'price',1000,NULL,0,1,1);

-- South India — ₹99 / free.
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,is_active) VALUES
(@m,@south,'price',0,999.99,99,0,1),
(@m,@south,'price',1000,NULL,0,1,1);

-- All India (remote catch-all) — ₹149 / free.
INSERT INTO shipping_rules (method_id,zone_id,rule_type,min_value,max_value,cost,is_free,is_active) VALUES
(@m,@india,'price',0,999.99,149,0,1),
(@m,@india,'price',1000,NULL,0,1,1);
