-- Audit trail for real-money refund payouts: record WHICH admin approved/rejected each request.
-- Nullable (older rows + system actions have no actor); references admin_users(id) loosely
-- (no FK so deleting an admin never blocks a historical refund record).
ALTER TABLE refund_requests ADD COLUMN IF NOT EXISTS actioned_by INT NULL AFTER admin_note;
