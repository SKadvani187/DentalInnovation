-- ───────────────────────────────────────────────────────────────────────────
-- Per-product free gifts. Each row links a product to a gift product that is added
-- to the cart FREE (₹0) when the first product is purchased. The storefront auto-adds
-- the gift line; removing the parent product removes its gift. Gift price is always
-- forced to 0 server-side (orders.php, line_type='gift').
-- Idempotent (CREATE IF NOT EXISTS). Prereq: products table.
-- ───────────────────────────────────────────────────────────────────────────
USE dentinno_crm;

CREATE TABLE IF NOT EXISTS product_gifts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NOT NULL,            -- buy this product
    gift_product_id INT NOT NULL,            -- get this product free
    sort_order      INT DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id)      REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (gift_product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uq_product_gift (product_id, gift_product_id)
);
