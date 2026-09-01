-- Navbar Menu (Settings → General Config → Navbar Menu).
-- Stored in site_settings under skey='navMenu' as a JSON array of menu items.
-- Each item: { id, label, view, enabled, auth? }.
--   view  = storefront route the item points to ("price" = Shop-by-Price dropdown)
--   auth  = true means the item only shows for logged-in customers (e.g. Wishlist)
-- ON DUPLICATE KEY UPDATE so re-running refreshes the value without erroring.
INSERT INTO site_settings (skey, svalue) VALUES (
  'navMenu',
  '[
    {"id":"category","label":"Category","view":"category","enabled":true},
    {"id":"offers","label":"Offer Zone","view":"offers","enabled":true},
    {"id":"combos","label":"Combos","view":"combos","enabled":true},
    {"id":"gvp","label":"Great Value Products","view":"gvp","enabled":true},
    {"id":"price","label":"Shop by Price","view":"price","enabled":true},
    {"id":"events","label":"Events","view":"events","enabled":true},
    {"id":"wishlist","label":"Wishlist","view":"wishlist","enabled":true,"auth":true},
    {"id":"about","label":"About Us","view":"about","enabled":true},
    {"id":"contact","label":"Contact Us","view":"contact","enabled":true}
  ]'
)
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);
