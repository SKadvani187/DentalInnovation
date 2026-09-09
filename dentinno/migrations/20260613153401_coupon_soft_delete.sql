-- Soft-delete for coupons: keep the row (so order history, redemption records and usage
-- analytics stay intact) but hide it from the admin list and stop it applying at checkout.
-- A deleted coupon is also set is_active=0 (couponEvaluate already requires is_active=1).
ALTER TABLE coupons ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
