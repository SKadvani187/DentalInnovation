-- shipping_method_delivery_days
--
-- How long a shipping method takes, so the checkout options can show different dates.
--
-- Until now the delivery estimate came only from the destination pincode (delivery_pincodes),
-- which meant every option quoted the SAME date. That is fine when the buyer has no choice, but
-- the checkout now lets them pick — and nobody pays extra for "Express" that arrives on the same
-- day as Standard. This is what makes a faster service sellable.
--
-- NULL = fall back to the pincode's own delivery_days, i.e. exactly the old behaviour, so no
-- existing method changes until an admin sets a number on it.

ALTER TABLE shipping_methods
  ADD COLUMN IF NOT EXISTS delivery_days INT NULL AFTER max_order_value;
