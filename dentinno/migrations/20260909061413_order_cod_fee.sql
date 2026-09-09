-- order_cod_fee
--
-- Cash-on-delivery handling fee, charged only on COD orders. Standard on Indian storefronts:
-- COD costs the business a collection fee and a much higher return rate, so it carries a small
-- surcharge that online payment does not.
--
-- Its own column rather than folding it into shipping_charge, because the two are different
-- things on a tax invoice, and a refund has to be able to reason about them separately.
-- 0.00 on every existing order, so nothing already placed changes.
--
-- The amount itself lives in site_settings under `codConfig`:
--   {"enabled":true,"fee":50,"freeAbove":5000}
--   enabled   — off by default, so this migration alone charges nobody
--   fee       — flat ₹ added to a COD order
--   freeAbove — order subtotal at/above which the fee is waived (0/null = never waived)

ALTER TABLE orders ADD COLUMN IF NOT EXISTS cod_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER shipping_charge;

-- Seed the setting in its OFF state. Storefronts that already tuned it keep their own values.
INSERT INTO site_settings (skey, svalue)
SELECT 'codConfig', '{"enabled":false,"fee":0,"freeAbove":0}'
WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE skey = 'codConfig');
