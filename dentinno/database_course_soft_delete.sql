-- Soft-delete for courses: keep the row AND its course_enrollments (paid student records:
-- name/email/phone/payment) so an accidental delete can be restored. The admin list hides
-- deleted by default; a "Deleted" filter surfaces them for restore.
ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
