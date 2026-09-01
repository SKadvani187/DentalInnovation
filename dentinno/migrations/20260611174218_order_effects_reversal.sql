-- Order effects reversal support.
--
-- At order creation (api/v1/orders.php) the app applies side-effects: it decrements
-- product/combo stock, bumps products.total_sales, customers.total_orders/total_spent,
-- and coupons.uses_count. Until now NONE of these were reversed when an order was
-- cancelled / rejected / returned / refunded, causing silent inventory leakage and
-- inflated bestseller / LTV / coupon-usage figures.
--
-- This migration adds:
--   * orders.coupon_id        — which coupon (if any) was applied, so uses_count can be
--                               decremented on reversal (orders had no coupon link before).
--   * orders.effects_reversed — one-shot guard so the compensating restock/decrement runs
--                               at most once per order even if it hits several terminal
--                               transitions (cancel then refund, double clicks, etc.).
--
-- Idempotent (ADD COLUMN IF NOT EXISTS) so migrate.php can re-run safely.

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS coupon_id INT DEFAULT NULL AFTER total,
    ADD COLUMN IF NOT EXISTS effects_reversed TINYINT(1) NOT NULL DEFAULT 0 AFTER coupon_id;
