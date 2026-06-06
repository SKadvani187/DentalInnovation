-- Delivery pincode serviceability + ETA (admin-managed).
-- Matching is by longest pincode PREFIX, so "39" covers all Surat-region 39xxxx,
-- and a specific "395006" row can override with its own days/COD.
CREATE TABLE IF NOT EXISTS delivery_pincodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pincode_prefix VARCHAR(6) NOT NULL,        -- full pincode or a leading prefix (e.g. 39, 395, 395006)
    label VARCHAR(120) DEFAULT NULL,           -- e.g. "Surat & South Gujarat"
    delivery_days INT NOT NULL DEFAULT 5,      -- business days to deliver
    cod_available TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_prefix (pincode_prefix)
);

-- Seed a couple of sensible defaults (safe to edit/delete in admin).
INSERT IGNORE INTO delivery_pincodes (pincode_prefix, label, delivery_days, cod_available, sort_order) VALUES
  ('39', 'Gujarat', 3, 1, 1),
  ('4',  'Maharashtra & West', 4, 1, 2),
  ('5',  'South India', 5, 1, 3),
  ('1',  'North India', 6, 1, 4);
