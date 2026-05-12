-- ============================================================
-- DentInno CRM — Database Additions
-- New modules: Shipping, Extended Products, Events, Courses
-- ============================================================

USE dentinno_crm;

-- ───────────────────────────────────────────────────────────
-- SHIPPING MANAGEMENT
-- ───────────────────────────────────────────────────────────

-- Shipping Methods (master table)
CREATE TABLE IF NOT EXISTS shipping_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    type ENUM('flat','free','product','weight','price','flexible') DEFAULT 'flat',
    base_cost DECIMAL(10,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Shipping Zones (region-based)
CREATE TABLE IF NOT EXISTS shipping_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    states JSON DEFAULT NULL,
    pincodes JSON DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Shipping Rules (linked to method + zone)
CREATE TABLE IF NOT EXISTS shipping_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_id INT NOT NULL,
    zone_id INT DEFAULT NULL,
    rule_type ENUM('weight','price','quantity','product') NOT NULL,
    min_value DECIMAL(12,2) DEFAULT 0,
    max_value DECIMAL(12,2) DEFAULT NULL,
    cost DECIMAL(10,2) NOT NULL,
    is_free TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (method_id) REFERENCES shipping_methods(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES shipping_zones(id) ON DELETE SET NULL
);

-- Product Shipping Overrides
CREATE TABLE IF NOT EXISTS product_shipping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    shipping_class ENUM('standard','bulky','fragile','express_only','free') DEFAULT 'standard',
    weight_kg DECIMAL(8,3) DEFAULT NULL,
    length_cm DECIMAL(8,2) DEFAULT NULL,
    width_cm DECIMAL(8,2) DEFAULT NULL,
    height_cm DECIMAL(8,2) DEFAULT NULL,
    override_cost DECIMAL(10,2) DEFAULT NULL,
    is_free_shipping TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uq_product_shipping (product_id)
);

-- Sample shipping methods
INSERT INTO shipping_methods (name, description, type, base_cost) VALUES
('Standard Delivery', 'Delivery in 5-7 business days', 'flat', 99.00),
('Express Delivery', 'Delivery in 1-2 business days', 'flat', 299.00),
('Free Shipping', 'Free on orders above ₹5000', 'price', 0.00),
('Weight-Based Shipping', 'Calculated by package weight', 'weight', 0.00),
('Product-Specific', 'Per product shipping charge', 'product', 0.00);

INSERT INTO shipping_zones (name, states) VALUES
('All India', '["All"]'),
('Metro Cities', '["Maharashtra","Delhi","Karnataka","Tamil Nadu","West Bengal"]'),
('North India', '["Uttar Pradesh","Rajasthan","Punjab","Haryana","Delhi"]'),
('South India', '["Karnataka","Tamil Nadu","Kerala","Andhra Pradesh","Telangana"]'),
('West India', '["Gujarat","Maharashtra","Rajasthan","Goa"]');

INSERT INTO shipping_rules (method_id, zone_id, rule_type, min_value, max_value, cost, is_free) VALUES
(3, NULL, 'price', 5000, NULL, 0, 1),
(3, NULL, 'price', 0, 4999.99, 99, 0),
(4, NULL, 'weight', 0, 1, 50, 0),
(4, NULL, 'weight', 1.01, 5, 100, 0),
(4, NULL, 'weight', 5.01, 10, 180, 0),
(4, NULL, 'weight', 10.01, NULL, 250, 0);

-- ───────────────────────────────────────────────────────────
-- EXTENDED PRODUCT FIELDS
-- ───────────────────────────────────────────────────────────

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS features JSON DEFAULT NULL AFTER description,
    ADD COLUMN IF NOT EXISTS full_description LONGTEXT DEFAULT NULL AFTER features,
    ADD COLUMN IF NOT EXISTS packing_info TEXT DEFAULT NULL AFTER full_description,
    ADD COLUMN IF NOT EXISTS key_specifications JSON DEFAULT NULL AFTER packing_info,
    ADD COLUMN IF NOT EXISTS directions_for_use TEXT DEFAULT NULL AFTER key_specifications,
    ADD COLUMN IF NOT EXISTS additional_information TEXT DEFAULT NULL AFTER directions_for_use,
    ADD COLUMN IF NOT EXISTS warranty_info TEXT DEFAULT NULL AFTER additional_information,
    ADD COLUMN IF NOT EXISTS weight_kg DECIMAL(8,3) DEFAULT NULL AFTER warranty_info;

-- Product FAQs
CREATE TABLE IF NOT EXISTS product_faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Product Reviews
CREATE TABLE IF NOT EXISTS product_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    reviewer_name VARCHAR(150) NOT NULL,
    reviewer_email VARCHAR(150),
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(255),
    review TEXT NOT NULL,
    images JSON DEFAULT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    is_approved TINYINT(1) DEFAULT 0,
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- ───────────────────────────────────────────────────────────
-- EVENT MANAGEMENT
-- ───────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(300) UNIQUE NOT NULL,
    description TEXT,
    event_type ENUM('conference','workshop','webinar','exhibition','training','other') DEFAULT 'other',
    status ENUM('draft','published','cancelled','completed') DEFAULT 'draft',
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    venue VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    is_online TINYINT(1) DEFAULT 0,
    online_link VARCHAR(500),
    max_attendees INT DEFAULT NULL,
    registration_fee DECIMAL(10,2) DEFAULT 0.00,
    is_free TINYINT(1) DEFAULT 1,
    banner_image VARCHAR(255),
    tags JSON DEFAULT NULL,
    organizer VARCHAR(200),
    contact_email VARCHAR(150),
    contact_phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS event_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    clinic_name VARCHAR(200),
    payment_status ENUM('free','pending','paid') DEFAULT 'free',
    payment_amount DECIMAL(10,2) DEFAULT 0,
    registration_code VARCHAR(50) UNIQUE,
    attended TINYINT(1) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

INSERT INTO events (title, slug, description, event_type, status, start_date, end_date, venue, city, state, organizer) VALUES
('DentInno Annual Dental Conference 2025', 'dentinno-annual-2025', 'Annual gathering of dental professionals featuring latest innovations', 'conference', 'published', '2025-11-15 09:00:00', '2025-11-17 18:00:00', 'Taj Lands End', 'Mumbai', 'Maharashtra', 'DentInno'),
('Implantology Masterclass Workshop', 'implantology-masterclass-2025', 'Hands-on workshop on advanced implant techniques', 'workshop', 'published', '2025-10-05 10:00:00', '2025-10-05 17:00:00', 'DentInno Training Centre', 'Ahmedabad', 'Gujarat', 'DentInno'),
('Digital Dentistry Webinar Series', 'digital-dentistry-webinar', 'Online sessions on CAD/CAM and digital workflows', 'webinar', 'published', '2025-09-20 19:00:00', '2025-09-20 21:00:00', NULL, NULL, NULL, 'DentInno');

UPDATE events SET is_online = 1, online_link = 'https://meet.dentinno.com/webinar' WHERE slug = 'digital-dentistry-webinar';

-- ───────────────────────────────────────────────────────────
-- COURSE MANAGEMENT
-- ───────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(300) UNIQUE NOT NULL,
    description TEXT,
    full_description LONGTEXT,
    course_type ENUM('online','offline','hybrid') DEFAULT 'online',
    category VARCHAR(100),
    level ENUM('beginner','intermediate','advanced','expert') DEFAULT 'beginner',
    status ENUM('draft','published','archived') DEFAULT 'draft',
    duration_hours INT DEFAULT NULL,
    total_lessons INT DEFAULT 0,
    price DECIMAL(10,2) DEFAULT 0.00,
    discount_price DECIMAL(10,2) DEFAULT NULL,
    is_free TINYINT(1) DEFAULT 0,
    thumbnail VARCHAR(255),
    preview_video VARCHAR(500),
    instructor_name VARCHAR(200),
    instructor_bio TEXT,
    instructor_avatar VARCHAR(255),
    certificate_offered TINYINT(1) DEFAULT 1,
    max_students INT DEFAULT NULL,
    enrolled_count INT DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 0.00,
    rating_count INT DEFAULT 0,
    tags JSON DEFAULT NULL,
    requirements JSON DEFAULT NULL,
    outcomes JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS course_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS course_lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    lesson_type ENUM('video','document','quiz','live') DEFAULT 'video',
    content TEXT,
    video_url VARCHAR(500),
    duration_minutes INT DEFAULT NULL,
    is_preview TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES course_modules(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS course_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    student_name VARCHAR(150) NOT NULL,
    student_email VARCHAR(150) NOT NULL,
    student_phone VARCHAR(20),
    payment_status ENUM('free','pending','paid') DEFAULT 'free',
    payment_amount DECIMAL(10,2) DEFAULT 0,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completion_date DATETIME DEFAULT NULL,
    progress_percent TINYINT DEFAULT 0,
    certificate_issued TINYINT(1) DEFAULT 0,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

INSERT INTO courses (title, slug, description, course_type, category, level, status, duration_hours, total_lessons, price, is_free, instructor_name, certificate_offered) VALUES
('Fundamentals of Dental Implantology', 'fundamentals-implantology', 'Comprehensive course covering basics to advanced implant procedures', 'online', 'Implantology', 'beginner', 'published', 20, 15, 4999.00, 0, 'Dr. Vikram Desai', 1),
('Advanced Endodontic Techniques', 'advanced-endodontics', 'Master rotary file systems, CBCT interpretation and retreatment', 'online', 'Endodontics', 'advanced', 'published', 15, 12, 3499.00, 0, 'Dr. Meera Shah', 1),
('Digital Smile Design Masterclass', 'digital-smile-design', 'Learn DSD workflow, software tools and patient communication', 'hybrid', 'Aesthetics', 'intermediate', 'published', 10, 8, 0.00, 1, 'Dr. Arjun Mehta', 1);
