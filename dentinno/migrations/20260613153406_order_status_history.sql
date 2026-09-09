-- Per-order status change log — powers the "Track Order" timeline in the admin order detail.
-- One row is written each time an order's status changes (who/when/optional note).
CREATE TABLE IF NOT EXISTS order_status_history (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    order_id   INT NOT NULL,
    status     VARCHAR(40) NOT NULL,
    note       VARCHAR(255) DEFAULT NULL,
    changed_by INT DEFAULT NULL,                 -- admin_users.id (NULL = system/customer)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_order (order_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
