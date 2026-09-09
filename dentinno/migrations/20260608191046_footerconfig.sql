-- Footer config (Settings → General → Footer). Stored in site_settings under skey='footerConfig'.
-- Link columns, payment strip, address heading, rating label, bottom tagline.
-- "show" map = per-block visibility (socials/linkColumns/address/payment/rating/copyright). Missing key = shown.
-- Storefront falls back to bundled defaults if this row is absent; this seeds the editable copy.
INSERT INTO site_settings (skey, svalue) VALUES (
  'footerConfig',
  '{"sections":[{"title":"ABOUT","links":[{"label":"Contact Us","route":"contact"},{"label":"About Us","route":"about"},{"label":"Careers","external":"https://www.linkedin.com/in/smart-dental-innovations-017331382/"}]},{"title":"CONTACT WITH US","links":[{"label":"Buying Guide","route":"about"},{"label":"Bulk Price Inquiry","route":"contact"}]},{"title":"HELP","links":[{"label":"Orders","route":"orders","requireAuth":true},{"label":"Refunds","route":"orders","requireAuth":true},{"label":"Payments","route":"orders","requireAuth":true}]},{"title":"POLICY","links":[{"label":"Return Policy","route":"policy","params":{"type":"return"}},{"label":"Term Of Use","route":"policy","params":{"type":"terms"}},{"label":"Privacy","route":"policy","params":{"type":"privacy"}},{"label":"Sitemap","external":"/sitemap.xml"}]}],"addressHeading":"REGISTERED OFFICE ADDRESS","paymentBox":{"title":"100% Secure Payments","subtitle":"Secure SSL Encrypted Payment","methods":[{"id":"card","label":"Credit/Debit Card"},{"id":"netbanking","label":"Net Banking"},{"id":"upi","label":"UPI"}]},"ratingLabel":"Average online rating","tagline":"Crafted with \\u2665 in India","show":{"socials":true,"linkColumns":true,"address":true,"payment":true,"rating":true,"copyright":true}}'
)
ON DUPLICATE KEY UPDATE svalue = VALUES(svalue);
