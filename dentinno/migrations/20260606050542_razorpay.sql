-- Razorpay payment integration — schema changes
-- Run once on each environment (local + production).

-- 1. orders.payment_status needs a 'pending' state for online orders that are
--    created before the customer completes payment at the gateway. The original
--    enum only had unpaid/paid/partial/refunded, so 'pending' was silently stored
--    as '' (empty) on MySQL in non-strict mode.
ALTER TABLE orders
    MODIFY payment_status ENUM('unpaid','pending','paid','partial','refunded')
    NOT NULL DEFAULT 'unpaid';

-- 2. (Optional) one-time backfill for any orders created before this migration
--    that got an empty payment_status.
UPDATE orders SET payment_status='pending' WHERE payment_status='' AND payment_method <> 'cod';
UPDATE orders SET payment_status='unpaid'  WHERE payment_status='' AND payment_method  = 'cod';

-- Note: payments.method ENUM is intentionally left unchanged. Razorpay methods
-- outside the enum (e.g. 'wallet') are clamped to a valid value and the true raw
-- method is preserved in payments.notes (see payment_razorpay.php / razorpay_webhook.php).
