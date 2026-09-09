-- =====================================================================
-- RBAC Redesign — Phase 1: schema + seed (database-driven page registry)
-- Run with:  php migrate.php
-- Idempotent: CREATE TABLE IF NOT EXISTS / INSERT IGNORE / guarded ALTER.
-- The seed reproduces the EXACT access each role had under the old static
-- rolePermissions(), so no existing user loses or gains access on cut-over.
-- =====================================================================

-- ---------- 1. roles ----------
CREATE TABLE IF NOT EXISTS roles (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  slug        VARCHAR(80)  NOT NULL UNIQUE,
  description VARCHAR(255) DEFAULT NULL,
  is_super    TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = super_admin (bypasses all checks, hidden from matrix)
  is_system   TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = built-in, cannot be deleted
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (name, slug, description, is_super, is_system) VALUES
  ('Super Admin', 'super_admin', 'Full, unrestricted access to everything.', 1, 1),
  ('Admin',       'admin',       'Manages catalog, sales, content, shipping and reports.', 0, 1),
  ('Staff',       'staff',       'Limited: orders, reviews/Q&A and content.', 0, 1);

-- ---------- 2. page_registry (single source of truth for menu + matrix) ----------
CREATE TABLE IF NOT EXISTS page_registry (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  page_key        VARCHAR(50)  NOT NULL UNIQUE,
  label           VARCHAR(80)  NOT NULL,
  url             VARCHAR(160) NOT NULL,
  icon            VARCHAR(50)  DEFAULT NULL,
  nav_group       VARCHAR(40)  DEFAULT NULL,
  group_order     INT NOT NULL DEFAULT 0,
  sort_order      INT NOT NULL DEFAULT 0,
  supports_view   TINYINT(1) NOT NULL DEFAULT 1,
  supports_create TINYINT(1) NOT NULL DEFAULT 0,
  supports_edit   TINYINT(1) NOT NULL DEFAULT 0,
  supports_delete TINYINT(1) NOT NULL DEFAULT 0,
  is_super_only   TINYINT(1) NOT NULL DEFAULT 0,
  show_in_nav     TINYINT(1) NOT NULL DEFAULT 1,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  is_system       TINYINT(1) NOT NULL DEFAULT 0,
  description     VARCHAR(255) DEFAULT NULL,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_nav (is_active, show_in_nav, group_order, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- page_key, label, url, icon, group, grp_ord, sort, V,C,E,D, super_only, show_nav, active, system, desc
INSERT IGNORE INTO page_registry
 (page_key, label, url, icon, nav_group, group_order, sort_order,
  supports_view, supports_create, supports_edit, supports_delete,
  is_super_only, show_in_nav, is_active, is_system, description) VALUES
-- MAIN
('dashboard','Dashboard','index.php','fa-gauge-high','MAIN',0,0, 1,0,0,0, 0,1,1,1,'Landing dashboard. Always visible to any logged-in user.'),
-- CATALOG
('products','Products','pages/products.php','fa-boxes-stacked','CATALOG',1,0, 1,1,1,1, 0,1,1,0,'Product catalog (incl. CSV import = create, review moderation = edit/delete).'),
('categories','Categories','pages/categories.php','fa-layer-group','CATALOG',1,1, 1,1,1,1, 0,1,1,0,'Product categories.'),
('combos','Combos','pages/combos.php','fa-boxes-packing','CATALOG',1,2, 1,1,1,1, 0,1,1,0,'Product bundles.'),
('offers','Offers','pages/offers.php','fa-tags','CATALOG',1,3, 1,1,1,1, 0,1,1,0,'Promotional offers.'),
('testimonials','Testimonials','pages/testimonials.php','fa-quote-left','CATALOG',1,4, 1,1,1,1, 0,1,1,0,'Homepage testimonials.'),
-- SALES
('orders','Orders','pages/orders.php','fa-cart-shopping','SALES',2,0, 1,0,1,0, 0,1,1,0,'Orders. Status/payment/tracking updates = edit. Orders originate from the storefront.'),
('refunds','Refunds','pages/refunds.php','fa-rotate-left','SALES',2,1, 1,0,1,0, 1,1,1,0,'Refund approvals. Real-money payouts — SUPER ADMIN ONLY.'),
('customers','Customers','pages/customers.php','fa-user-group','SALES',2,2, 1,1,1,1, 0,1,1,0,'Customer CRM (CSV import = create).'),
('payments','Payments','pages/payments.php','fa-indian-rupee-sign','SALES',2,3, 1,0,0,0, 0,1,1,0,'Payment ledger (read-only).'),
('messages','Messages','pages/messages.php','fa-envelope','SALES',2,4, 1,0,1,1, 0,1,1,0,'Contact inquiries (mark-read = edit).'),
('bulk_quotes','Bulk Quotes','pages/bulk_quotes.php','fa-file-invoice-dollar','SALES',2,5, 1,0,1,1, 0,1,1,0,'Bulk quote requests (status change = edit).'),
-- MARKETING
('coupons','Coupons','pages/coupons.php','fa-tag','MARKETING',3,0, 1,1,1,1, 0,1,1,0,'Discount coupons (generate codes = create).'),
('reviews','Reviews','pages/reviews.php','fa-star','MARKETING',3,1, 1,0,1,1, 0,1,1,0,'Product reviews (approve/verify = edit).'),
('questions','Q&A','pages/questions.php','fa-circle-question','MARKETING',3,2, 1,0,1,1, 0,1,1,0,'Product questions (answer/approve = edit).'),
('wishlists','Wishlists','pages/wishlists.php','fa-heart','MARKETING',3,3, 1,0,0,0, 0,1,1,0,'Customer wishlists (read-only).'),
-- SHIPPING
('shipping','Shipping','pages/shipping.php','fa-truck','SHIPPING',4,0, 1,1,1,1, 0,1,1,0,'Shipping methods, zones, rules, pincodes.'),
('shipping_calculator','Calculator','pages/shipping_calculator.php','fa-calculator','SHIPPING',4,1, 1,0,0,0, 0,1,1,0,'Shipping rate calculator (tool, read-only).'),
-- ENGAGE
('events','Events','pages/events.php','fa-calendar-star','ENGAGE',5,0, 1,1,1,1, 0,1,1,0,'Events and registrations.'),
('courses','Courses','pages/courses.php','fa-graduation-cap','ENGAGE',5,1, 1,1,1,1, 0,1,1,0,'Courses and enrollments.'),
-- REPORTS
('reports','Analytics','pages/reports.php','fa-chart-line','REPORTS',6,0, 1,0,0,0, 0,1,1,0,'Financial analytics (read-only).'),
-- SYSTEM (all super-admin only)
('settings','Settings','pages/settings.php','fa-gear','SYSTEM',7,0, 1,0,1,0, 1,1,1,1,'Storefront config + secrets. SUPER ADMIN ONLY. Sub-pages are settings.php?page=...'),
('admins','Admin Users','pages/admins.php','fa-shield-halved','SYSTEM',7,1, 1,1,1,1, 1,1,1,1,'Admin user management. SUPER ADMIN ONLY.'),
('roles','Roles','pages/roles.php','fa-user-shield','SYSTEM',7,2, 1,1,1,1, 1,1,1,1,'RBAC role management. SUPER ADMIN ONLY.'),
('permissions','Permissions','pages/permissions.php','fa-table-cells','SYSTEM',7,3, 1,0,1,0, 1,1,1,1,'RBAC permission matrix. SUPER ADMIN ONLY.');

-- ---------- 3. role_permissions (the matrix) ----------
CREATE TABLE IF NOT EXISTS role_permissions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  role_id     INT NOT NULL,
  page_id     INT NOT NULL,
  can_view    TINYINT(1) NOT NULL DEFAULT 0,
  can_create  TINYINT(1) NOT NULL DEFAULT 0,
  can_edit    TINYINT(1) NOT NULL DEFAULT 0,
  can_delete  TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_role_page (role_id, page_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id)         ON DELETE CASCADE,
  CONSTRAINT fk_rp_page FOREIGN KEY (page_id) REFERENCES page_registry(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----- ADMIN role grants (mirror the old admin permission set) -----
-- Full CRUD pages
INSERT IGNORE INTO role_permissions (role_id, page_id, can_view, can_create, can_edit, can_delete)
SELECT r.id, p.id, 1,1,1,1 FROM roles r JOIN page_registry p
  ON p.page_key IN ('products','categories','combos','offers','testimonials','customers','coupons','shipping','events','courses')
 WHERE r.slug='admin';
-- View + Edit + Delete
INSERT IGNORE INTO role_permissions (role_id, page_id, can_view, can_create, can_edit, can_delete)
SELECT r.id, p.id, 1,0,1,1 FROM roles r JOIN page_registry p
  ON p.page_key IN ('reviews','questions','messages','bulk_quotes')
 WHERE r.slug='admin';
-- View + Edit
INSERT IGNORE INTO role_permissions (role_id, page_id, can_view, can_create, can_edit, can_delete)
SELECT r.id, p.id, 1,0,1,0 FROM roles r JOIN page_registry p
  ON p.page_key IN ('orders')
 WHERE r.slug='admin';
-- View only (read-only pages the admin could already see)
INSERT IGNORE INTO role_permissions (role_id, page_id, can_view, can_create, can_edit, can_delete)
SELECT r.id, p.id, 1,0,0,0 FROM roles r JOIN page_registry p
  ON p.page_key IN ('reports','payments','wishlists','shipping_calculator','dashboard')
 WHERE r.slug='admin';

-- ----- STAFF role grants (mirror the old staff permission set) -----
-- Full CRUD content pages
INSERT IGNORE INTO role_permissions (role_id, page_id, can_view, can_create, can_edit, can_delete)
SELECT r.id, p.id, 1,1,1,1 FROM roles r JOIN page_registry p
  ON p.page_key IN ('testimonials','events','courses')
 WHERE r.slug='staff';
-- View + Edit + Delete
INSERT IGNORE INTO role_permissions (role_id, page_id, can_view, can_create, can_edit, can_delete)
SELECT r.id, p.id, 1,0,1,1 FROM roles r JOIN page_registry p
  ON p.page_key IN ('reviews','questions','messages','bulk_quotes')
 WHERE r.slug='staff';
-- View + Edit
INSERT IGNORE INTO role_permissions (role_id, page_id, can_view, can_create, can_edit, can_delete)
SELECT r.id, p.id, 1,0,1,0 FROM roles r JOIN page_registry p
  ON p.page_key IN ('orders')
 WHERE r.slug='staff';
-- View only (read-only pages staff could already see; NOT reports — that is admin+)
INSERT IGNORE INTO role_permissions (role_id, page_id, can_view, can_create, can_edit, can_delete)
SELECT r.id, p.id, 1,0,0,0 FROM roles r JOIN page_registry p
  ON p.page_key IN ('payments','wishlists','shipping_calculator','dashboard')
 WHERE r.slug='staff';

-- Note: super_admin gets NO rows here on purpose — is_super bypasses all checks.

-- ---------- 4. rbac_meta (live-change version counter) ----------
CREATE TABLE IF NOT EXISTS rbac_meta (
  id           TINYINT PRIMARY KEY DEFAULT 1,
  perm_version INT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO rbac_meta (id, perm_version) VALUES (1, 1);

-- ---------- 5. admin_users.role_id (guarded, idempotent) ----------
-- Add the column only if it doesn't already exist (portable across MySQL/MariaDB; no DELIMITER needed).
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_users' AND COLUMN_NAME='role_id');
SET @sql := IF(@col=0, 'ALTER TABLE admin_users ADD COLUMN role_id INT NULL AFTER role', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Add the FK only if it doesn't already exist.
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admin_users' AND CONSTRAINT_NAME='fk_admin_users_role');
SET @sql := IF(@fk=0, 'ALTER TABLE admin_users ADD CONSTRAINT fk_admin_users_role FOREIGN KEY (role_id) REFERENCES roles(id)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill role_id from the legacy role string (idempotent).
-- Explicit COLLATE so the join works regardless of the two columns' collations.
UPDATE admin_users au JOIN roles r ON r.slug = au.role COLLATE utf8mb4_unicode_ci
   SET au.role_id = r.id
 WHERE au.role_id IS NULL OR au.role_id <> r.id;
