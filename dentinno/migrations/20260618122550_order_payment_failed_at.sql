-- When a customer dismisses or fails the Razorpay popup, the storefront now reports it to the
-- server (payment_razorpay.php action=failed). The order is kept 'pending' (still retry-able),
-- but payment_failed_at is stamped so the ADMIN can immediately tell an abandoned/failed-payment
-- order apart from a brand-new one still mid-checkout — without waiting for the 30-min cleanup.
--
-- Cleared if the order is later paid (a successful retry). The payment_status ENUM has no
-- 'failed' value and changing it would touch many code paths, so a nullable timestamp is used.
-- Idempotent: ADD COLUMN IF NOT EXISTS.


ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS payment_failed_at DATETIME DEFAULT NULL;
