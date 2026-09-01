-- ───────────────────────────────────────────────────────────────────────────
-- Per-product "Frequently Bought Together" relations.
-- Each row links a product to a related product that should be suggested when the
-- first product is in the cart. Replaces the global static fbtItems list with a
-- product-specific list (admin sets it per product; storefront shows the union of
-- the cart's products' FBT, minus what's already in the cart).
-- Idempotent (CREATE IF NOT EXISTS). Prereq: products table.
-- ───────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS product_fbt (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NOT NULL,            -- the product being viewed / in cart
    fbt_product_id  INT NOT NULL,            -- the suggested companion product
    sort_order      INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id)     REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (fbt_product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uq_product_fbt (product_id, fbt_product_id)
);
