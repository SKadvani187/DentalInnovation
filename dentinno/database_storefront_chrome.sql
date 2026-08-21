-- Storefront chrome / config keys -> site_settings (read by the React storefront via
-- /api/v1/settings.php, editable in Admin -> Settings). Previously hardcoded as static
-- fallbacks in the frontend (src/data/site.js); moved to the DB so the storefront is
-- fully admin-managed. ON DUPLICATE KEY UPDATE so re-running refreshes safely.
--
-- Keys: combosPage, gvpPage, shopByPricePage, aboutSections, contactSections, paymentOptions, shippingConfig, taxConfig, lowStockThreshold.
-- (navMenu, branding, gvpThreshold, sortOptions, priceBounds, sectionToCategory seeded
--  elsewhere; featured/premiumCategories/etc. in database_initial_data_insert.sql.)
USE dentinno_crm;

-- Combos page chrome (hero + trust strip + labels)
INSERT INTO site_settings (skey, svalue) VALUES ('combosPage', '{"heroBadge":"Bundle & Save","heroTitle":"Combo Packs","savePrefix":"Save up to","saveSuffix":"across","subtitle":"Hand-picked product bundles \u2014 clinic essentials grouped together at a better price than buying separately.","bundleNote":"Multi-product bundle","trust":[{"icon":"shield","title":"100% Genuine","desc":"Manufacturer-sourced"},{"icon":"save","title":"Bundle Savings","desc":"Better than buying separately"},{"icon":"ship","title":"Pan-India Shipping","desc":"5\u20137 day delivery"},{"icon":"help","title":"Need help?","desc":"We''re here to help"}]}')
  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- Great Value Products page chrome (hero copy)
INSERT INTO site_settings (skey, svalue) VALUES ('gvpPage', '{"heroBadge":"Great Value Deals","heroTitle":"Best Value Products","savePrefix":"Save up to","saveSuffix":"across","subtitle":"Hand-picked products with the biggest discounts \u2014 clinic essentials at unbeatable prices.","statDeals":"Live deals","statDiscount":"Max discount","statSavings":"Total savings"}')
  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- Shop by Price page chrome (hero copy)
INSERT INTO site_settings (skey, svalue) VALUES ('shopByPricePage', '{"heroBadge":"Shop by Budget","heroTitle":"Shop by Price","subtitle":"Pick a budget \u2014 we''ll show every product that fits, from quick buys to clinic essentials.","customLabel":"Custom Range","customDesc":"Set your own budget"}')
  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- About page section layout (show/hide & reorder)
INSERT INTO site_settings (skey, svalue) VALUES ('aboutSections', '[{"key":"hero","label":"Hero","enabled":true},{"key":"story","label":"Our Story","enabled":true},{"key":"stats","label":"Stats Strip","enabled":true},{"key":"milestones","label":"Milestones","enabled":true},{"key":"coreValues","label":"Core Values","enabled":true},{"key":"leadership","label":"Leadership \/ Team","enabled":true},{"key":"whyTrust","label":"Why Trust Us","enabled":true},{"key":"missionVision","label":"Mission & Vision","enabled":true},{"key":"testimonials","label":"Testimonials","enabled":true},{"key":"certifications","label":"Certifications","enabled":true},{"key":"whatWeOffer","label":"What We Offer","enabled":true},{"key":"cta","label":"Bottom CTA","enabled":true},{"key":"contactStrip","label":"Contact Strip","enabled":true},{"key":"socialStrip","label":"Social Strip","enabled":true}]')
  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- Contact page section layout (show/hide & reorder)
INSERT INTO site_settings (skey, svalue) VALUES ('contactSections', '[{"key":"hero","label":"Hero","enabled":true},{"key":"quickActions","label":"Quick Actions","enabled":true},{"key":"form","label":"Contact Form","enabled":true},{"key":"contactMethods","label":"Reach Us","enabled":true},{"key":"businessHours","label":"Business Hours","enabled":true},{"key":"officeMap","label":"Our Office (Map)","enabled":true},{"key":"faq","label":"FAQs","enabled":true}]')
  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- Product page Payment Options card (icon=rupee|bank|card|upi ; span=grid cols 1-12)
INSERT INTO site_settings (skey, svalue) VALUES ('paymentOptions', '[{"id":"cod","label":"COD","icon":"rupee","span":5,"desc":"Experience Convenience and Trust with Our Cash on Delivery (COD) Payment Service"},{"id":"nb","label":"Net Banking","icon":"bank","span":7,"desc":"Net banking, also known as online banking or internet banking, is a digital platform that allows customers to perform various financial transactions and manage their bank accounts through the internet."},{"id":"upi","label":"UPI","icon":"upi","span":5,"desc":"UPI (Unified Payments Interface) is a real-time payment system that allows you to link multiple bank accounts to a single mobile application, enabling seamless and instant money transfers and payments."},{"id":"partial","label":"Partial Payment","icon":"rupee","span":7,"desc":"You can partially pay for your order now and the remaining amount can be paid at the time of delivery."},{"id":"card","label":"Credit / Debit cards","icon":"card","span":12,"desc":"Pay securely with your Credit or Debit card via our trusted payment gateway."}]')
  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- Shipping rule — MUST mirror dentinno/api/v1/_pricing.php
INSERT INTO site_settings (skey, svalue) VALUES ('shippingConfig', '{"flatRate":300,"freeThreshold":20000}')
  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- Tax (GST) rule — MUST mirror dentinno/api/v1/_pricing.php
INSERT INTO site_settings (skey, svalue) VALUES ('taxConfig', '{"enabled":false,"rate":0,"inclusive":true}')
  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

-- Combos low-stock urgency ribbon threshold
INSERT INTO site_settings (skey, svalue) VALUES ('lowStockThreshold', '10')
  ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);

