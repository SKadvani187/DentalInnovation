-- Coupon abuse fix: track per-customer redemptions and add a per-customer cap.
-- per_user_limit: how many times ONE customer may redeem a coupon (default 1; NULL = unlimited).
ALTER TABLE coupons ADD COLUMN IF NOT EXISTS per_user_limit INT DEFAULT 1;

CREATE TABLE IF NOT EXISTS coupon_redemptions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    coupon_id   INT NOT NULL,
    customer_id INT NOT NULL,
    order_id    INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_coupon_customer (coupon_id, customer_id),
    KEY idx_order (order_id)
);
