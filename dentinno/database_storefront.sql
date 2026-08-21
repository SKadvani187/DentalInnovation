-- Storefront-specific tables: combos, offers, testimonials
-- (Not in original CRM schema. Added for React storefront content.)
USE dentinno_crm;

-- Combos (bundle deals) — shape mirrors products
CREATE TABLE IF NOT EXISTS combos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) UNIQUE NOT NULL,       -- React id e.g. c-001
    name VARCHAR(255) NOT NULL,
    description TEXT,
    mrp DECIMAL(10,2) NOT NULL,              -- original price
    price DECIMAL(10,2) NOT NULL,            -- selling price
    discount_percent DECIMAL(5,2) DEFAULT 0,
    image VARCHAR(500),
    images JSON DEFAULT NULL,
    in_stock TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Offers (offer zone cards) — nested mainProduct/freeItems kept as JSON
CREATE TABLE IF NOT EXISTS offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) UNIQUE NOT NULL,       -- React id e.g. offer-1
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500),
    theme VARCHAR(40),
    accent VARCHAR(20),
    gradient VARCHAR(255),
    cta VARCHAR(255),
    main_product JSON DEFAULT NULL,          -- {productId,name,variant,image,price,mrp,rating?,reviews?}
    free_items JSON DEFAULT NULL,            -- [{name,mrp,image,variant?}]
    special_price DECIMAL(10,2),
    total_mrp DECIMAL(10,2),
    you_save DECIMAL(10,2),
    save_extra VARCHAR(120),
    valid_till DATE,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Testimonials (customer reviews on home)
CREATE TABLE IF NOT EXISTS testimonials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) UNIQUE NOT NULL,       -- React id e.g. t-1
    name VARCHAR(150) NOT NULL,
    avatar VARCHAR(500),
    product_image VARCHAR(500),
    text TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
