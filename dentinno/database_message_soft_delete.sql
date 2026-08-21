-- Soft-delete for contact-form messages: keep the row so an accidental delete can be restored.
-- The admin list hides deleted messages by default; a "Deleted" filter surfaces them for restore.
ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_read;
