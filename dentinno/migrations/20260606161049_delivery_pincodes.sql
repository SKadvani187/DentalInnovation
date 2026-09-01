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

-- Seed full pan-India coverage by the first digit of the PIN (India's postal zones 1-8),
-- so every real pincode is serviceable by default. Finer prefixes (e.g. Gujarat 36-39)
-- override the single-digit row via longest-prefix matching, and admin can add specific
-- 395006-style rows for per-pincode days/COD. (Zone 9 = APO/FPO army post, left out.)
INSERT IGNORE INTO delivery_pincodes (pincode_prefix, label, delivery_days, cod_available, sort_order) VALUES
  -- Gujarat (home region) — faster, finer-grained than the zone-3 default.
  ('36', 'Saurashtra (Rajkot region)', 3, 1, 1),
  ('37', 'Saurashtra & Kutch',         3, 1, 2),
  ('38', 'Ahmedabad & North Gujarat',  3, 1, 3),
  ('39', 'Surat & South Gujarat',      3, 1, 4),
  -- Pan-India postal zones (first digit) — covers everything else.
  ('1',  'North India (DL/HR/PB/HP/JK)', 6, 1, 11),
  ('2',  'North/Central India (UP/UK)',  6, 1, 12),
  ('3',  'West India (RJ/GJ)',           4, 1, 13),
  ('4',  'West India (MH/MP/CG/GA)',     4, 1, 14),
  ('5',  'South India (AP/TS/KA)',       5, 1, 15),
  ('6',  'South India (TN/KL)',          5, 1, 16),
  ('7',  'East India (WB/OR/NE)',        6, 1, 17),
  ('8',  'East India (BR/JH)',           6, 1, 18);
