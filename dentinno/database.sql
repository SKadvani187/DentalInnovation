-- DentInno CRM Database Schema
-- Run this SQL in your MySQL/MariaDB

CREATE DATABASE IF NOT EXISTS dentinno_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dentinno_crm;

-- Admin Users
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin','admin','staff') DEFAULT 'staff',
    permissions JSON DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default admin: admin@dentinno.com / Admin@123
INSERT INTO admin_users (name, email, password, role) VALUES 
('Super Admin', 'admin@dentinno.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- Categories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) UNIQUE NOT NULL,
    description TEXT,
    parent_id INT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categories (name, slug, description) VALUES
('Implantology', 'implantology', 'Dental implant systems and accessories'),
('Endodontics', 'endodontics', 'Root canal treatment tools'),
('Orthodontics', 'orthodontics', 'Braces and aligners'),
('Surgical Instruments', 'surgical-instruments', 'Dental surgical tools'),
('Sterilization', 'sterilization', 'Sterilization equipment'),
('Radiology', 'radiology', 'X-ray and imaging equipment');

-- Products
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(300) UNIQUE NOT NULL,
    sku VARCHAR(100) UNIQUE,
    category_id INT,
    description TEXT,
    short_description VARCHAR(500),
    price DECIMAL(10,2) NOT NULL,
    discount_price DECIMAL(10,2) DEFAULT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    stock INT DEFAULT 0,
    min_stock_alert INT DEFAULT 5,
    images JSON DEFAULT NULL,
    variants JSON DEFAULT NULL,
    specifications JSON DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    total_sales INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

INSERT INTO products (name, slug, sku, category_id, description, price, discount_price, stock, is_featured) VALUES
('RF Cautery Machine Pro', 'rf-cautery-machine-pro', 'RFC-001', 4, 'High-frequency RF cautery for precise dental surgery', 19000.00, 17500.00, 15, 1),
('Premium Implant Kit', 'premium-implant-kit', 'IMP-001', 1, 'Complete implant kit with titanium implants', 65000.00, 60000.00, 8, 1),
('Rotary Endodontic System', 'rotary-endodontic-system', 'END-001', 2, 'Advanced rotary NiTi file system', 28000.00, NULL, 20, 0),
('Digital X-Ray Sensor', 'digital-xray-sensor', 'RAD-001', 6, 'High resolution RVG sensor', 45000.00, 42000.00, 5, 1),
('Autoclave Sterilizer 22L', 'autoclave-sterilizer-22l', 'STE-001', 5, 'Class B autoclave sterilizer', 35000.00, NULL, 12, 0);

-- Customers
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    city VARCHAR(100),
    state VARCHAR(100),
    address TEXT,
    pincode VARCHAR(10),
    clinic_name VARCHAR(200),
    customer_type ENUM('individual','clinic','hospital','distributor') DEFAULT 'individual',
    total_orders INT DEFAULT 0,
    total_spent DECIMAL(12,2) DEFAULT 0.00,
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO customers (name, email, phone, city, state, clinic_name, customer_type, total_orders, total_spent) VALUES
('Dr. Rajesh Sharma', 'rajesh@example.com', '9876543210', 'Mumbai', 'Maharashtra', 'Sharma Dental Clinic', 'clinic', 5, 185000.00),
('Dr. Priya Patel', 'priya@example.com', '9876543211', 'Ahmedabad', 'Gujarat', 'Patel Dentistry', 'clinic', 3, 95000.00),
('Dr. Amit Kumar', 'amit@example.com', '9876543212', 'Delhi', 'Delhi', 'Kumar Orthodontics', 'clinic', 8, 320000.00),
('MediDent Distributors', 'info@medident.com', '9876543213', 'Bangalore', 'Karnataka', NULL, 'distributor', 12, 580000.00);

-- Orders
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    status ENUM('pending','processing','confirmed','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
    payment_status ENUM('unpaid','paid','partial','refunded') DEFAULT 'unpaid',
    payment_method VARCHAR(50),
    subtotal DECIMAL(12,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    shipping_charge DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    shipping_address JSON,
    notes TEXT,
    tracking_number VARCHAR(100),
    courier_name VARCHAR(100),
    shipped_at DATETIME DEFAULT NULL,
    delivered_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- Order Items
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255),
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Insert sample orders
INSERT INTO orders (order_number, customer_id, status, payment_status, payment_method, subtotal, total) VALUES
('ORD-2024-001', 1, 'delivered', 'paid', 'UPI', 19000, 19000),
('ORD-2024-002', 2, 'processing', 'paid', 'Bank Transfer', 65000, 65500),
('ORD-2024-003', 3, 'shipped', 'paid', 'UPI', 45000, 45800),
('ORD-2024-004', 4, 'pending', 'unpaid', NULL, 93000, 94500);

-- Payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method ENUM('upi','card','netbanking','cash','cheque','bank_transfer') NOT NULL,
    transaction_id VARCHAR(200),
    status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
    payment_date DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- Coupons
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    type ENUM('percent','fixed') DEFAULT 'percent',
    value DECIMAL(10,2) NOT NULL,
    min_order DECIMAL(10,2) DEFAULT 0,
    max_discount DECIMAL(10,2) DEFAULT NULL,
    uses_limit INT DEFAULT NULL,
    uses_count INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    expires_at DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO coupons (code, type, value, min_order, max_discount) VALUES
('DENT10', 'percent', 10, 10000, 5000),
('WELCOME500', 'fixed', 500, 5000, NULL),
('BULK20', 'percent', 20, 50000, 15000);

-- Wishlist
CREATE TABLE IF NOT EXISTS wishlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    UNIQUE KEY unique_wishlist (customer_id, product_id)
);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('order','payment','stock','customer','system') DEFAULT 'system',
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(300),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO notifications (title, message, type) VALUES
('New Order Received', 'Order #ORD-2024-004 placed by MediDent Distributors', 'order'),
('Low Stock Alert', 'Digital X-Ray Sensor stock is below 5 units', 'stock'),
('Payment Received', 'Payment of ₹65,000 received for Order #ORD-2024-002', 'payment');
