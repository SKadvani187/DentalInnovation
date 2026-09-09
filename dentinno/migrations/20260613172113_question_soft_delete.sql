-- Soft-delete for product questions: keep the row so an accidental delete can be restored.
-- The storefront (which shows is_approved=1 AND is_answered=1) now also filters is_deleted=0,
-- and the admin list hides deleted by default with a "Deleted" filter to restore.
ALTER TABLE product_questions ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_approved;
