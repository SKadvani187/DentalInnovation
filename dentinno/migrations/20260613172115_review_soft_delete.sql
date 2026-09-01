-- Soft-delete for product reviews: keep the row so an accidental (or bulk) delete can be undone.
-- A deleted review is hidden from the admin list (default) and the storefront (which now also
-- filters is_deleted=0 on top of is_approved=1). Restore from the "Deleted" filter.
ALTER TABLE product_reviews ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_approved;
