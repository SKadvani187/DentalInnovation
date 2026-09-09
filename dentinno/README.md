# DentInno CRM — Installation Guide

## 🦷 About
Complete PHP + MySQL CRM system for DentInno dental products company.
Gold/Black premium design with full management features.

---

## 📁 Folder Structure
```
dentinno/
├── assets/
│   ├── css/style.css          ← All styling
│   ├── js/app.js              ← JavaScript
│   └── images/logo.png        ← DentInno logo
├── includes/
│   ├── config.php             ← DB config + helpers
│   ├── auth.php               ← Login/logout functions
│   ├── header.php             ← Sidebar + topbar
│   └── footer.php             ← Scripts + footer
├── pages/
│   ├── products.php           ← Product management
│   ├── categories.php         ← Categories
│   ├── orders.php             ← Order management
│   ├── customers.php          ← Customer CRM
│   ├── payments.php           ← Payment tracking
│   ├── coupons.php            ← Discount coupons
│   ├── wishlists.php          ← Wishlist data
│   ├── reports.php            ← Analytics
│   ├── admins.php             ← Admin users
│   └── settings.php           ← Settings
├── index.php                  ← Dashboard
├── login.php                  ← Login page
├── logout.php                 ← Logout
├── migrations/                ← Schema history: <version>_<name>.sql, run by migrate.php
├── migrate.php                ← Migration runner
└── .htaccess                  ← Security config
```

---

## 🚀 Installation Steps

### Step 1: Web Server Setup
- Install XAMPP / WAMP / LAMP on your computer or server
- Copy the `dentinno/` folder to:
  - XAMPP: `C:/xampp/htdocs/dentinno/`
  - WAMP: `C:/wamp/www/dentinno/`
  - Linux: `/var/www/html/dentinno/`

### Step 2: Database Setup
1. Open phpMyAdmin → `http://localhost/phpmyadmin`
2. Create an empty database: `dentinno_crm` (collation `utf8mb4_unicode_ci`)
3. From the `dentinno/` folder, run the migrations:
   ```bash
   php migrate.php
   ```
   That applies the base schema and every migration in `migrations/`, in version order.
   Nothing to import by hand.

**Working with migrations**

```bash
php migrate.php                  # apply everything pending
php migrate.php --status         # what's applied / pending / changed since it ran
php migrate.php --new add_thing  # create the next migration (stamps its version)
```

Migrations live in `migrations/` and are named `<version>_<name>.sql`, where the version is a
UTC `YYYYMMDDHHMMSS` stamp. They run in ascending version order, and `schema_migrations` records
the **version**, not the filename. Always create new ones with `--new` — never hand-name a file,
and never change the version of a migration that has already shipped.

Two rules for the SQL inside:
- **No `USE` / `CREATE DATABASE`.** Migrations run inside whatever `DB_NAME` the runner connected
  with (production overrides it via env). The runner rejects a file that carries one.
- **Keep it idempotent** (`IF NOT EXISTS`, `INSERT IGNORE`). MySQL auto-commits DDL, so a file
  that fails halfway cannot be rolled back — it has to be safe to run again.

### Step 3: Configure
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password
define('DB_NAME', 'dentinno_crm');
define('APP_URL', 'http://localhost/dentinno');
```

### Step 4: Login
Open browser: `http://localhost/dentinno/login.php`

**Default Credentials:**
- Email: `admin@dentinno.com`
- Password: `password`

⚠️ **Change the password immediately after first login!**

---

## ✅ Features Included

| Module | Features |
|--------|----------|
| 🏠 Dashboard | Stats, charts, recent orders, top products |
| 📦 Products | Add/Edit/Delete, variants, stock, discount |
| 🗂️ Categories | Manage dental product categories |
| 🛒 Orders | Status tracking, shipping, invoice view |
| 👤 Customers | CRM with order history, wishlist, notes |
| 💳 Payments | UPI/Card/Bank tracking, refunds |
| 🏷️ Coupons | Percent/Fixed discounts, expiry, limits |
| ❤️ Wishlists | Customer wishlist analytics |
| 📊 Analytics | Revenue charts, top products, date filters |
| 🔐 Admin Users | Multi-user with roles (Super Admin/Admin/Staff) |
| ⚙️ Settings | Profile, password change, system info |

---

## 🛠️ Tech Stack
- **Backend:** PHP 8+ (PDO)
- **Database:** MySQL / MariaDB
- **Frontend:** Vanilla HTML/CSS/JS
- **Charts:** Chart.js
- **Icons:** Font Awesome 6
- **Fonts:** Playfair Display + DM Sans

---

## 🎨 Design
- Gold & Black premium CRM theme
- Responsive (mobile + desktop)
- DentInno logo integrated
- Animated stat counters
- Toast notifications
- Confirm modals

---

## 📞 Support
For customization: Add your contact here
DentInno — Where Innovation Meets Dentistry 🦷
