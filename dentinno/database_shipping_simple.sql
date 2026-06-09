-- ───────────────────────────────────────────────────────────────────────────
-- Shipping model: "Free above ₹1000, else ₹99 flat" (matches real dental e-commerce
-- like Dentalkart / Pinkblue). The storefront cart, checkout and order all compute
-- shipping via the same engine (api/v1/_pricing.php → computeShipping, auto-cheapest).
--
-- To get a single, predictable price we keep ONLY the price-based "Free Shipping"
-- method active. Weight/Express/Product-Specific methods are deactivated so the
-- auto-cheapest picker can't undercut the intended rate (most products have no
-- weight set, which made the weight method always win at ₹50).
--
-- Idempotent (re-runnable). Prereq: database_additions.sql (engine tables) +
-- database_shipping_class.sql (shipping_class column).
-- ───────────────────────────────────────────────────────────────────────────
USE dentinno_crm;

-- 1) Keep only the price-based "Free Shipping" method active.
UPDATE shipping_methods SET is_active = 1 WHERE type = 'price';
UPDATE shipping_methods SET is_active = 0 WHERE type IN ('weight', 'product', 'flat');

-- 2) Free above ₹1000, ₹99 below. Rewrite the price method's two rules to the new
--    threshold (method id resolved by type so this works on any install).
SET @m := (SELECT id FROM shipping_methods WHERE type = 'price' ORDER BY id LIMIT 1);

-- Below ₹1000 -> ₹99 flat
UPDATE shipping_rules
   SET min_value = 0, max_value = 999.99, cost = 99, is_free = 0
 WHERE method_id = @m AND rule_type = 'price' AND is_free = 0;

-- ₹1000 and above -> free
UPDATE shipping_rules
   SET min_value = 1000, max_value = NULL, cost = 0, is_free = 1
 WHERE method_id = @m AND rule_type = 'price' AND is_free = 1;

-- Mirror the legacy flat fallback (used only if the engine has no active methods).
INSERT INTO site_settings (skey, svalue) VALUES ('shippingConfig', '{"freeThreshold":1000,"flatRate":99}')
  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);
