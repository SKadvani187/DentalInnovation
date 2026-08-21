-- Refund / return requests raised by customers and actioned by admin.
-- A request references an order; on approval the admin triggers a Razorpay refund (online
-- orders) or marks it manually (COD), then the order + payment move to 'refunded'.
CREATE TABLE IF NOT EXISTS refund_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    reason TEXT,                                   -- customer's stated reason
    status ENUM('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
    refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0,-- amount to return (defaults to order total)
    razorpay_refund_id VARCHAR(100) DEFAULT NULL,  -- gateway refund id once processed
    admin_note TEXT DEFAULT NULL,                  -- approve/reject note from admin
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actioned_at DATETIME DEFAULT NULL,             -- when approved/rejected
    completed_at DATETIME DEFAULT NULL,            -- when money was actually refunded
    UNIQUE KEY uniq_open_per_order (order_id),     -- one active request per order
    KEY idx_customer (customer_id),
    KEY idx_status (status),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
