-- Soft-delete for events: keep the row AND its event_registrations (paid attendee records:
-- name/email/phone/payment) so an accidental delete can be fully restored. The storefront and
-- admin default both exclude is_deleted=1; a "Deleted" filter surfaces them for restore.
ALTER TABLE events ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
