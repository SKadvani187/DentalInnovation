-- Offer Zone redesign — Phase 1c: tag order lines with their type + offer.
-- Run via the migrator:  php migrate.php
--
-- Lets fulfilment distinguish a ₹0 free-gift line from a paid line and trace it back
-- to the offer it came from.
--   * line_type — 'product' (default), 'offer' (discounted main line), 'gift' (₹0)
--   * offer_id  — the offer this line belongs to (NULL for normal product lines)
--
-- Idempotent: ADD COLUMN IF NOT EXISTS.

USE dentinno_crm;

ALTER TABLE order_items
    ADD COLUMN IF NOT EXISTS line_type VARCHAR(16) DEFAULT 'product',
    ADD COLUMN IF NOT EXISTS offer_id  INT NULL;