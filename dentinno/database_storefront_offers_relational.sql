-- Offer Zone redesign — Phase 1a: relational schema.
-- Run via the migrator:  php migrate.php
--
-- Moves the offer -> product link and free gift items from JSON snapshots to a
-- relational model, and upgrades valid_till to DATETIME for precise countdowns.
--   * offers.product_id  — FK-by-convention to products.id (nullable, NO hard FK so
--                          deleting a product never cascade-deletes/blocks an offer;
--                          integrity is enforced in the app layer)
--   * valid_till         — DATE -> DATETIME (app timezone Asia/Kolkata)
--   * offer_items        — one row per free gift (replaces free_items JSON as source
--                          of truth; product_id snapshot-only, name/mrp/image kept
--                          even if the catalog product is later deleted)
--
-- Idempotent: ADD COLUMN/INDEX IF NOT EXISTS + CREATE TABLE IF NOT EXISTS.

USE dentinno_crm;

ALTER TABLE offers
    ADD COLUMN IF NOT EXISTS product_id INT NULL AFTER slug;

ALTER TABLE offers
    ADD INDEX IF NOT EXISTS idx_offers_product (product_id);

-- DATE -> DATETIME. Existing DATE values become 00:00:00; the backfill file pushes
-- them to 23:59:59 so currently-live offers don't expire a day early.
ALTER TABLE offers
    MODIFY COLUMN valid_till DATETIME NULL;

CREATE TABLE IF NOT EXISTS offer_items (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    offer_id   INT NOT NULL,
    product_id INT NULL,                 -- catalog product (snapshot only; no hard FK)
    name       VARCHAR(255) NOT NULL,    -- gift name snapshot (display + order record)
    variant    VARCHAR(120) DEFAULT NULL,
    image      VARCHAR(500) DEFAULT NULL,
    mrp        DECIMAL(10,2) DEFAULT 0,  -- gift MRP snapshot (totalMrp + strike-through)
    qty        INT NOT NULL DEFAULT 1,   -- gifts given per 1 main offer line
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_offer_items_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
    INDEX idx_offer_items_offer (offer_id),
    INDEX idx_offer_items_product (product_id)
);