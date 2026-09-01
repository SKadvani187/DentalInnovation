-- Coupon scheduling: a coupon only becomes usable on/after start_date (NULL = active immediately).
ALTER TABLE coupons ADD COLUMN IF NOT EXISTS start_date DATE DEFAULT NULL AFTER is_active;
