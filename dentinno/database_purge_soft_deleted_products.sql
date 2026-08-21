-- =============================================================================
-- HARD-DELETE all soft-deleted products (is_deleted = 1) and every child row
-- that references them via a foreign key.
--
-- Context: after importing the new Smart Dental catalogue, the old demo products
-- were soft-deleted (is_deleted = 1, is_active = 0). This script removes them
-- permanently along with their FK-linked data.
--
-- !!! IRREVERSIBLE !!!  Soft-delete could be undone (set is_deleted = 0); a hard
-- delete cannot. BACK UP the database first:
--     mysqldump -u root dentinno_crm > backup_before_purge.sql
--
-- FK delete rules referencing products(id):
--   CASCADE  : product_faqs, product_fbt, product_gifts, product_questions,
--              product_reviews, product_shipping  -> would auto-delete, but we
--              delete them explicitly so this script is self-documenting and does
--              not depend on the FK config being present.
--   RESTRICT : order_items, wishlists  -> MUST be deleted first or the final
--              DELETE FROM products is blocked. (order_items rows are ORDER
--              HISTORY — review them before running.)
--
-- All deletes target ONLY products where is_deleted = 1, via a subquery, so the
-- script is safe to re-run and never touches live products.
-- =============================================================================

USE dentinno_crm;

-- Optional: see exactly what will be removed before committing.
-- SELECT id, name, sku FROM products WHERE is_deleted = 1;
-- SELECT * FROM order_items WHERE product_id IN (SELECT id FROM products WHERE is_deleted = 1);

START TRANSACTION;

-- 1) RESTRICT children (must go first) ---------------------------------------
DELETE FROM order_items
 WHERE product_id IN (SELECT id FROM products WHERE is_deleted = 1);

DELETE FROM wishlists
 WHERE product_id IN (SELECT id FROM products WHERE is_deleted = 1);

-- 2) CASCADE children (explicit for clarity) ---------------------------------
DELETE FROM product_faqs
 WHERE product_id IN (SELECT id FROM products WHERE is_deleted = 1);

DELETE FROM product_questions
 WHERE product_id IN (SELECT id FROM products WHERE is_deleted = 1);

DELETE FROM product_reviews
 WHERE product_id IN (SELECT id FROM products WHERE is_deleted = 1);

DELETE FROM product_shipping
 WHERE product_id IN (SELECT id FROM products WHERE is_deleted = 1);

-- product_fbt / product_gifts each reference products via TWO columns: the owning
-- product AND the suggested/gift product. Delete a row if EITHER side points at a
-- soft-deleted product.
DELETE FROM product_fbt
 WHERE product_id     IN (SELECT id FROM products WHERE is_deleted = 1)
    OR fbt_product_id IN (SELECT id FROM products WHERE is_deleted = 1);

DELETE FROM product_gifts
 WHERE product_id      IN (SELECT id FROM products WHERE is_deleted = 1)
    OR gift_product_id IN (SELECT id FROM products WHERE is_deleted = 1);

-- 3) Finally, the products themselves ----------------------------------------
DELETE FROM products WHERE is_deleted = 1;

-- Review the row counts above, then keep the result:
COMMIT;
-- ...or undo everything if something looks wrong:
-- ROLLBACK;
