-- Fix: refunds.php claims a request as 'processing' before calling the gateway, but the
-- original ENUM lacked that value (so the claim UPDATE failed on strict MariaDB and broke
-- the approve / double-refund guard). Add 'processing' between 'approved' and 'completed'.
ALTER TABLE refund_requests
  MODIFY COLUMN status ENUM('pending','approved','rejected','processing','completed')
  NOT NULL DEFAULT 'pending';
