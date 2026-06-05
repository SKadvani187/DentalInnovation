-- WhatsApp Cloud API send log (best-effort, non-blocking).
-- Written by includes/whatsapp_sender.php -> waLog(). Safe to skip if you don't want logging.
USE dentinno_crm;

CREATE TABLE IF NOT EXISTS whatsapp_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    event         VARCHAR(40) NOT NULL,          -- order_placed | payment_success | order_status | otp
    recipient     VARCHAR(20) NOT NULL,          -- 91XXXXXXXXXX
    template      VARCHAR(120) DEFAULT NULL,      -- Meta template name used
    order_id      INT DEFAULT NULL,
    wa_message_id VARCHAR(120) DEFAULT NULL,      -- messages[0].id returned by Meta on success
    status        ENUM('sent','failed') NOT NULL,
    error         TEXT DEFAULT NULL,             -- Graph API / cURL error on failure
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event (event),
    INDEX idx_recipient (recipient),
    INDEX idx_order (order_id)
);
