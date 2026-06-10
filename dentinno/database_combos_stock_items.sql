-- Combos: add the `items` and `stock` columns the admin Combos page writes to.
-- Run via the migrator:  php migrate.php
--
-- Bug fixed: pages/combos.php saves with `... items=?, stock=? ...`, but the base
-- combos table (database_storefront.sql) only had `in_stock` and no `items`/`stock`
-- columns. The missing columns made every save/update throw a SQL error, which PHP
-- emitted as HTML ("<br /><b>...") into the JSON response — producing the browser
-- console error: Unexpected token '<', "<br /> ... is not valid JSON.
--
-- Idempotent: ADD COLUMN IF NOT EXISTS, so re-running is safe.
--   * items — bundled product list [{productId,name,mrp,image}] as JSON
--   * stock — combo stock quantity (admin field; 0 = out of stock)

USE dentinno_crm;

ALTER TABLE combos
  ADD COLUMN IF NOT EXISTS items JSON DEFAULT NULL AFTER images,
  ADD COLUMN IF NOT EXISTS stock INT DEFAULT 0 AFTER in_stock;

-- Backfill stock for existing rows so currently-active combos stay available
-- (new `stock` defaults to 0, which would otherwise read as "out of stock").
-- Out-of-stock combos (in_stock=0) keep stock 0; in-stock combos get a default 50.
UPDATE combos SET stock = 50 WHERE in_stock = 1 AND (stock IS NULL OR stock = 0);