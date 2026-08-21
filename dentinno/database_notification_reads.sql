-- Per-admin notification read state: one row per (notification, admin) that has dismissed it.
-- A notification is "unread" for an admin while no matching row exists — so one admin marking it
-- read no longer hides it from the others.
CREATE TABLE IF NOT EXISTS notification_reads (
    notification_id INT NOT NULL,
    admin_id        INT NOT NULL,
    read_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id, admin_id)
);
