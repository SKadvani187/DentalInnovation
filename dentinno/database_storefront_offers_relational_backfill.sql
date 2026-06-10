-- Offer Zone redesign — Phase 1b: backfill JSON snapshots into the relational model.
-- Run via the migrator AFTER database_storefront_offers_relational.sql (name sorts after).
--
-- Idempotent:
--   A) only sets product_id where still NULL
--   B) only shifts valid_till values that are exactly midnight (the DATE->DATETIME ones)
--   C) only inserts offer_items for offers that have none yet (NOT EXISTS guard)
--
-- Portability: uses indexed JSON_EXTRACT (handles up to 5 gifts/offer) instead of
-- JSON_TABLE, so it works on MariaDB 10.2+ / MySQL 5.7+ (this project uses MariaDB
-- "ADD COLUMN IF NOT EXISTS" syntax). Realistic offers have 0-2 gift items.

USE dentinno_crm;

-- A. Link offer -> product from the main_product JSON slug.
UPDATE offers o
JOIN products p
  ON p.slug = JSON_UNQUOTE(JSON_EXTRACT(o.main_product, '$.productId'))
SET o.product_id = p.id
WHERE o.product_id IS NULL
  AND o.main_product IS NOT NULL;

-- B. Push DATE-derived midnight values to end-of-day so nothing expires a day early.
UPDATE offers
SET valid_till = DATE_ADD(DATE(valid_till), INTERVAL '23:59:59' HOUR_SECOND)
WHERE valid_till IS NOT NULL
  AND TIME(valid_till) = '00:00:00';

-- C. Backfill gift rows from the free_items JSON array (indices 0..4), once per offer.
INSERT INTO offer_items (offer_id, product_id, name, variant, image, mrp, qty, sort_order)
SELECT o.id,
       p.id,
       JSON_UNQUOTE(JSON_EXTRACT(o.free_items, CONCAT('$[', n.i, '].name'))),
       JSON_UNQUOTE(JSON_EXTRACT(o.free_items, CONCAT('$[', n.i, '].variant'))),
       JSON_UNQUOTE(JSON_EXTRACT(o.free_items, CONCAT('$[', n.i, '].image'))),
       CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(o.free_items, CONCAT('$[', n.i, '].mrp'))), '0') AS DECIMAL(10,2)),
       1,
       n.i
FROM offers o
JOIN (SELECT 0 AS i UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4) n
  ON o.free_items IS NOT NULL AND JSON_LENGTH(o.free_items) > n.i
LEFT JOIN products p
  ON p.slug = JSON_UNQUOTE(JSON_EXTRACT(o.free_items, CONCAT('$[', n.i, '].productId')))
WHERE NOT EXISTS (SELECT 1 FROM offer_items oi WHERE oi.offer_id = o.id);