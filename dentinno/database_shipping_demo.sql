-- ═══════════════════════════════════════════════════════════════════════════
-- SHIPPING — CLEAN DEMO CONFIGURATION
-- Wipes ALL existing shipping config (methods/rules/zones/pincodes were seeded
-- 6× = duplicates) and rebuilds one coherent set that exercises every branch of
-- the engine in api/v1/_pricing.php → computeShipping() / methodShippingCost().
--
-- Engine recap (so the numbers below make sense):
--   • The engine picks the CHEAPEST applicable active method; FREE beats any paid.
--   • A zone is resolved from the delivery pincode by LONGEST matching prefix.
--   • If EVERY non-gift line points at the same products.shipping_method_id, that
--     method is FORCED (bypasses the cheapest picker) — this is the only reliable
--     way a *surcharge* (weight/flat/class) can apply, since otherwise the cheap
--     Standard rate or a FREE rule always undercuts it.
--   • weight = Σ products.weight_kg × qty ; combos have no weight / no force.
--
-- Idempotent: safe to re-run. Re-runnable IDs (TRUNCATE resets AUTO_INCREMENT).
-- ═══════════════════════════════════════════════════════════════════════════
USE dentinno_crm;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE shipping_rules;
TRUNCATE TABLE shipping_methods;
TRUNCATE TABLE shipping_zones;
TRUNCATE TABLE delivery_pincodes;
SET FOREIGN_KEY_CHECKS = 1;

-- ─── ZONES (longest pincode prefix wins) ───────────────────────────────────
-- Z1 Local-Gujarat (36–39).  Z2 Metro (3-digit, more specific than Z1's "39").
-- Any pincode matching neither → zone NULL → only global (zone-NULL) rules apply.
INSERT INTO shipping_zones (id, name, states, pincodes, is_active) VALUES
(1, 'Local — Gujarat', '["Gujarat"]',                                  '["36","37","38","39"]',                  1),
(2, 'Metro Cities',    '["Maharashtra","Delhi","Karnataka","Tamil Nadu","West Bengal"]', '["110","400","560","600","700"]', 1);

-- ─── METHODS ───────────────────────────────────────────────────────────────
-- Ensure the type ENUM allows 'quantity' (older schemas lack it → 1265 truncation).
ALTER TABLE shipping_methods
  MODIFY type ENUM('flat','free','product','weight','price','flexible','quantity') DEFAULT 'flat';

INSERT INTO shipping_methods (id, name, description, type, base_cost, is_active, sort_order) VALUES
(1, 'Standard Delivery',       'National baseline — applies everywhere by order value', 'price',    0.00,   1, 1),
(2, 'Local Gujarat Delivery',  'Cheaper rate for Gujarat (zone 1)',                     'price',    0.00,   1, 2),
(3, 'Metro Express Zone',      'Faster/cheaper free threshold for metro pincodes',      'price',    0.00,   1, 3),
(4, 'Heavy Equipment Freight', 'Flat freight — assigned per-product to heavy machines', 'flat',     600.00, 1, 4),
(5, 'Weight-Based Freight',    'Charged by package weight — assigned per-product',      'weight',   0.00,   1, 5),
(6, 'Product-Class Handling',  'Surcharge/free by product shipping class',              'product',  0.00,   1, 6),
(7, 'Bulk Order Free Shipping','Free shipping when ordering 10+ units',                 'quantity', 0.00,   1, 7);

-- ─── RULES ─────────────────────────────────────────────────────────────────
-- M1 Standard (global): <₹10,000 → ₹99 ; ₹10,000+ → FREE
INSERT INTO shipping_rules (method_id, zone_id, rule_type, min_value, max_value, product_class, cost, is_free, is_active) VALUES
(1, NULL, 'price',     0,    9999.99, NULL, 99,  0, 1),
(1, NULL, 'price', 10000,    NULL,    NULL,  0,  1, 1),

-- M2 Local Gujarat (zone 1 only): <₹1,500 → ₹25 ; ₹1,500+ → FREE
(2, 1,    'price',     0,    1499.99, NULL, 25,  0, 1),
(2, 1,    'price',  1500,    NULL,    NULL,  0,  1, 1),

-- M3 Metro (zone 2 only): <₹3,000 → ₹75 ; ₹3,000+ → FREE
(3, 2,    'price',     0,    2999.99, NULL, 75,  0, 1),
(3, 2,    'price',  3000,    NULL,    NULL,  0,  1, 1),

-- M5 Weight-Based (global): 5–15kg → ₹300 ; 15kg+ → ₹600  (below 5kg = not applicable)
(5, NULL, 'weight',    5,    14.999,  NULL, 300, 0, 1),
(5, NULL, 'weight',   15,    NULL,    NULL, 600, 0, 1),

-- M6 Product-Class (global): applies when ANY cart line carries the class
(6, NULL, 'product',   0,    NULL, 'fragile',      150, 0, 1),
(6, NULL, 'product',   0,    NULL, 'bulky',        450, 0, 1),
(6, NULL, 'product',   0,    NULL, 'express_only', 250, 0, 1),
(6, NULL, 'product',   0,    NULL, 'free',           0, 1, 1),

-- M7 Bulk quantity (global): 10+ total units → FREE
(7, NULL, 'quantity', 10,    NULL,    NULL,  0,  1, 1);

-- ─── PRODUCT ASSIGNMENTS (the worked examples) ─────────────────────────────
-- Reset everything to the clean baseline first (no weight, standard class, no override).
UPDATE products SET shipping_method_id = NULL, shipping_class = 'standard', weight_kg = NULL;

-- Autoclave 18L (heavy machine): FORCE flat freight ₹600, even on a ₹64,999 order
-- that Standard would ship FREE. weight + bulky class set for realism.
UPDATE products SET shipping_method_id = 4, shipping_class = 'bulky', weight_kg = 28.000 WHERE slug = 'p-012';

-- Implant Surgical Drill Kit: FORCE weight-based freight. 6.5kg → ₹300 band,
-- charged even though the ₹28,999 value would otherwise be FREE.
UPDATE products SET shipping_method_id = 5, weight_kg = 6.500 WHERE slug = 'p-007';

-- Dental Mirror Set (delicate): FORCE Product-Class method; fragile class → ₹150 handling.
UPDATE products SET shipping_method_id = 6, shipping_class = 'fragile' WHERE slug = 'p-006';

-- Disposable Dental Tray: shipping_class = 'free' but NO forced method. The global
-- Product-Class FREE rule wins via free-beats-paid, so any cart containing it ships FREE.
UPDATE products SET shipping_class = 'free' WHERE slug = 'n-007';

-- ─── LEGACY FLAT FALLBACK (only used if ALL methods are deactivated) ────────
INSERT INTO site_settings (skey, svalue) VALUES ('shippingConfig', '{"freeThreshold":10000,"flatRate":99}')
  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- ─── DELIVERY PINCODES (serviceability / ETA / COD — separate engine) ───────
-- Longest prefix wins. 400072 row overrides the "400" metro with No-COD + own ETA.
INSERT INTO delivery_pincodes (pincode_prefix, label, delivery_days, cod_available, is_active, sort_order) VALUES
('39',     'Surat & South Gujarat',      2, 1, 1, 1),
('38',     'Ahmedabad & North Gujarat',  3, 1, 1, 2),
('36',     'Saurashtra (Rajkot)',        3, 1, 1, 3),
('37',     'Kutch',                      4, 1, 1, 4),
('1',      'North India (DL/HR/PB/HP/JK)',6, 1, 1, 11),
('2',      'North/Central India (UP/UK)', 5, 1, 1, 12),
('3',      'West India (RJ/GJ)',          4, 1, 1, 13),
('4',      'West India (MH/MP/CG/GA)',     4, 1, 1, 14),
('5',      'South India (AP/TS/KA)',       5, 1, 1, 15),
('6',      'South India (TN/KL)',          5, 1, 1, 16),
('7',      'East India (WB/OR/NE)',        6, 1, 1, 17),
('8',      'East India (BR/JH)',           6, 1, 1, 18),
('400072', 'Mumbai – Saki Naka (No COD)',  3, 0, 1, 20);
