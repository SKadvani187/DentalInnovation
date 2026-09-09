-- coupon_free_shipping
--
-- Lets a coupon waive the delivery charge. Standard storefront promotion ("FREESHIP") that the
-- coupon engine had no way to express: coupons could only be `percent` or `fixed` off the
-- subtotal, never touching shipping.
--
-- A flag rather than a third `type`, so it composes: a coupon can give 10% off AND free
-- shipping, or free shipping alone (percent/fixed value 0). 0 on every existing coupon, so
-- nothing already issued changes behaviour.

ALTER TABLE coupons
  ADD COLUMN IF NOT EXISTS free_shipping TINYINT(1) NOT NULL DEFAULT 0 AFTER value;
