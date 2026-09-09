-- Soft-delete for customers: keep the row (so their order history stays intact) but hide it
-- from the admin list and block storefront login/token use. A deleted customer is also
-- set is_active=0.
ALTER TABLE customers ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
