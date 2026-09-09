-- shipping_method_limits
--
-- Eligibility limits per shipping method, so a method can be ruled out for an order instead of
-- only being priced. Every large storefront has these: a courier that tops out at 30 kg simply
-- must not be offered for a 40 kg consignment, and a same-day service is usually capped by
-- order value. Until now any active method was offered for every order — a 28 kg autoclave
-- could be quoted on the same service as a 200 g pack of paper points.
--
-- NULL means "no limit", so existing methods keep behaving exactly as before.
--   min_weight_kg / max_weight_kg  — total cart weight the method accepts
--   min_order_value / max_order_value — order subtotal the method accepts
-- The engine (api/v1/_pricing.php :: methodShippingCost) drops a method whose limits the order
-- falls outside, which also removes it from the checkout options list.

ALTER TABLE shipping_methods
  ADD COLUMN IF NOT EXISTS min_weight_kg   DECIMAL(10,3) NULL AFTER base_cost,
  ADD COLUMN IF NOT EXISTS max_weight_kg   DECIMAL(10,3) NULL AFTER min_weight_kg,
  ADD COLUMN IF NOT EXISTS min_order_value DECIMAL(12,2) NULL AFTER max_weight_kg,
  ADD COLUMN IF NOT EXISTS max_order_value DECIMAL(12,2) NULL AFTER min_order_value;

-- No limits are seeded on purpose. Setting one changes what customers are charged: putting a
-- 5 kg floor on "Heavy Equipment Freight", for instance, would drop it for every product that
-- has no weight recorded — including the Endomotor that is deliberately assigned to it, whose
-- shipping would silently fall from ₹600 to free. Limits are a pricing decision, so they are
-- left for the admin to set per method in Shipping → Methods.
