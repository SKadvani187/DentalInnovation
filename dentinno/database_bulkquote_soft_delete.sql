-- Soft-delete for bulk/wholesale quote requests: these are sales leads (real B2B revenue),
-- so keep the row on delete and let it be restored. Admin list hides deleted by default;
-- a "Deleted" filter surfaces them for restore.
ALTER TABLE bulk_quotes ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read;
