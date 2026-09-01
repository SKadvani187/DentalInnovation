-- Contact form inbox: create the `contact_messages` table.
-- Run via the migrator:  php migrate.php
--
-- Bug fixed: the storefront contact form (api/v1/contact.php) inserts into
-- contact_messages, and the admin inbox (pages/messages.php) reads/updates/deletes
-- it — but no migration ever created the table, so both threw:
--   SQLSTATE[42S02] Base table or view not found: 1146 Table
--   'dentinno_crm.contact_messages' doesn't exist
--
-- Columns match the exact fields used by both files.
-- Idempotent: CREATE TABLE IF NOT EXISTS, so re-running is safe.


CREATE TABLE IF NOT EXISTS contact_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    phone      VARCHAR(20)  DEFAULT NULL,
    email      VARCHAR(190) DEFAULT NULL,
    department VARCHAR(50)  DEFAULT NULL,
    message    TEXT NOT NULL,
    is_read    TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact_messages_read (is_read),
    INDEX idx_contact_messages_created (created_at)
);