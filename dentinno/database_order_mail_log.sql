-- Admin order-notification delivery log (Settings → Order Emails).
-- One row per (order_id, mail_type): records whether the admin email for an order was
-- SENT, is still PENDING (enabled but no recipient / SMTP creds), or FAILED (attempted but
-- rejected — retryable from the admin panel). Written by includes/order_mailer.php (omcLog).
-- Best-effort: if this table is absent the mailer still sends; it just can't track status.
USE dentinno_crm;

CREATE TABLE IF NOT EXISTS order_mail_log (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  order_id      INT          NULL,
  order_number  VARCHAR(64)  NULL,
  mail_type     VARCHAR(16)  NOT NULL,                -- 'placed' | 'failed'
  recipient     VARCHAR(255) NULL,                    -- admin recipient(s), comma/;-separated
  subject       VARCHAR(255) NULL,
  status        ENUM('sent','failed','pending','skipped') NOT NULL DEFAULT 'pending',
  error         TEXT         NULL,                    -- last failure reason (NULL when sent)
  attempts      INT          NOT NULL DEFAULT 0,      -- send attempts (incl. retries)
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NULL,
  sent_at       DATETIME     NULL,                    -- when it was first accepted (status=sent)
  UNIQUE KEY uq_order_type (order_id, mail_type),     -- upsert target; allows NULL order_id
  KEY idx_status (status),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
