-- Fix: Bestsellers / New Arrivals not showing on storefront + admin product save failing.
-- 1) products table was missing is_new and hover_image (admin save writes both -> save failed).
-- 2) site_settings table never existed (home.php + settings.php query it -> API crashed -> React fell back to static).

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS is_new TINYINT(1) DEFAULT 0 AFTER is_featured,
    ADD COLUMN IF NOT EXISTS hover_image VARCHAR(500) DEFAULT NULL AFTER images;

CREATE TABLE IF NOT EXISTS site_settings (
    skey       VARCHAR(100) PRIMARY KEY,
    svalue     LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3) customers.email was NOT NULL UNIQUE, but storefront registration (api/v1/auth.php)
--    inserts new customers with email = NULL -> "Column 'email' cannot be null" -> customer
--    never created. Make email nullable; the UNIQUE index still allows multiple NULLs in MySQL.
ALTER TABLE customers MODIFY email VARCHAR(150) NULL;
