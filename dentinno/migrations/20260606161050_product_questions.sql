-- Customer-submitted product questions (QnA page). Admin answers + approves them.
CREATE TABLE IF NOT EXISTS product_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    asker_name VARCHAR(150) DEFAULT NULL,
    asker_email VARCHAR(150) DEFAULT NULL,
    question TEXT NOT NULL,
    answer TEXT DEFAULT NULL,
    is_answered TINYINT(1) NOT NULL DEFAULT 0,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
    helpful_up INT DEFAULT 0,
    helpful_down INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    answered_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);
