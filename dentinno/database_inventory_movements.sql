-- Inventory movement ledger: an append-only audit of EVERY stock change (sale, refund-restock,
-- manual adjustment, product-edit). Lets the admin answer "why did this product's stock change?"
CREATE TABLE IF NOT EXISTS inventory_movements (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    product_id    INT NOT NULL,
    delta         INT NOT NULL,                 -- +received / -removed
    type          VARCHAR(20) NOT NULL,         -- sale | refund | manual | edit | initial
    reason        VARCHAR(255) NULL,
    reference     VARCHAR(100) NULL,            -- e.g. order number
    balance_after INT NULL,                     -- product stock immediately after this movement
    admin_id      INT NULL,                     -- who (null = system/customer-driven)
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_im_product (product_id, created_at),
    INDEX idx_im_created (created_at)
);
