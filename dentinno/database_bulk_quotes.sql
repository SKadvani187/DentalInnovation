-- ───────────────────────────────────────────────────────────────────────────
-- Bulk quote requests (product page "Get Bulk Quote" form, orders ≥ ₹10,000).
-- Mirrors contact_messages: a simple inbox the admin works through. Previously the
-- form only saved to the customer's browser localStorage and never reached the admin.
-- Idempotent (CREATE IF NOT EXISTS).
-- ───────────────────────────────────────────────────────────────────────────
USE dentinno_crm;

CREATE TABLE IF NOT EXISTS bulk_quotes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(150) NOT NULL,
    phone          VARCHAR(20)  DEFAULT NULL,
    email          VARCHAR(150) DEFAULT NULL,
    pincode        VARCHAR(10)  DEFAULT NULL,
    address        TEXT         DEFAULT NULL,
    product_slug   VARCHAR(190) DEFAULT NULL,   -- the product the quote is for (storefront slug)
    product_name   VARCHAR(255) DEFAULT NULL,   -- name snapshot at submit time
    quantity       INT          DEFAULT NULL,
    expected_price DECIMAL(12,2) DEFAULT NULL,  -- customer's expected per-piece price
    status         ENUM('new','contacted','quoted','closed') NOT NULL DEFAULT 'new',
    is_read        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);
