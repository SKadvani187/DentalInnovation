-- Header branding: two logos (no text) + storefront WhatsApp number.
-- Run via the migrator:  php migrate.php   (records itself in schema_migrations)
--
-- Adds one site_settings key read by the React storefront header & WhatsApp button,
-- editable in Admin → Settings → General → "Logos & WhatsApp":
--   * branding = { logo1, logo2, whatsappNumber }
--       logo1 / logo2  — header logo image URLs (logo2 optional; empty = single logo)
--       whatsappNumber — country code + number, digits only (floating chat button)
--
-- INSERT IGNORE: if the key already exists (admin already set logos / number), the
-- existing value is kept. Logos start empty so the header falls back to the bundled
-- logo asset until the admin uploads. The admin can change all of this any time.

USE dentinno_crm;

-- Safety: ensure the settings table exists (matches database_bestsellers_fix.sql).
CREATE TABLE IF NOT EXISTS site_settings (
    skey       VARCHAR(100) PRIMARY KEY,
    svalue     LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO site_settings (skey, svalue) VALUES
('branding', '{\"logo1\":\"\",\"logo2\":\"\",\"whatsappNumber\":\"919328762586\"}');