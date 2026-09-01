-- Contact Us page: seed storefront content + show/hide layout into site_settings.
-- Run via the migrator:  php migrate.php   (records itself in schema_migrations)
--
-- Adds three site_settings keys read by the React storefront Contact page and
-- editable in Admin → Settings → Contact Page:
--   * contactSections — the show/hide + reorder layout the admin toggles (Page Layout tab)
--   * contactConfig   — hero, quick-action labels, form, hours, office, FAQs, stat chips
--   * company         — shared company info used by the "Reach us" / quick-action cards
--
-- INSERT IGNORE is used on purpose: if a key already exists (admin already
-- customised it), the existing value is kept and this row is skipped. To force
-- a reset to these defaults, delete the key first or edit it in the admin panel.


-- Safety: ensure the settings table exists (matches database_bestsellers_fix.sql).
CREATE TABLE IF NOT EXISTS site_settings (
    skey       VARCHAR(100) PRIMARY KEY,
    svalue     LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 1) Contact page layout — the array the admin shows/hides & reorders.
--    A section is visible on the storefront unless "enabled":false.
INSERT IGNORE INTO site_settings (skey, svalue) VALUES
('contactSections', '[{\"key\":\"hero\",\"label\":\"Hero\",\"enabled\":true},{\"key\":\"quickActions\",\"label\":\"Quick Actions\",\"enabled\":true},{\"key\":\"form\",\"label\":\"Contact Form\",\"enabled\":true},{\"key\":\"contactMethods\",\"label\":\"Reach Us\",\"enabled\":true},{\"key\":\"businessHours\",\"label\":\"Business Hours\",\"enabled\":true},{\"key\":\"officeMap\",\"label\":\"Our Office (Map)\",\"enabled\":true},{\"key\":\"faq\",\"label\":\"FAQs\",\"enabled\":true}]');

-- 2) Contact page content (hero, quick-action labels, form, hours, office, FAQs, chips).
INSERT IGNORE INTO site_settings (skey, svalue) VALUES
('contactConfig', '{\"departments\":[{\"id\":\"sales\",\"label\":\"Sales Inquiry\",\"icon\":\"💼\",\"desc\":\"Bulk orders, demos, quotes\"},{\"id\":\"support\",\"label\":\"Product Support\",\"icon\":\"🛠️\",\"desc\":\"Warranty, repairs, returns\"},{\"id\":\"partnership\",\"label\":\"Partnerships\",\"icon\":\"🤝\",\"desc\":\"Distributors, resellers\"},{\"id\":\"general\",\"label\":\"General Query\",\"icon\":\"💬\",\"desc\":\"Anything else\"}],\"faqs\":[{\"q\":\"How fast do you respond?\",\"a\":\"Sales & support inquiries: under 4 business hours (Mon–Sat, 10 AM–7 PM IST). General queries: within 24 hours.\"},{\"q\":\"Do you ship pan-India?\",\"a\":\"Yes — 5–7 business days to most pincodes. Free shipping above ₹20,000. COD available with verified pincode.\"},{\"q\":\"Can I visit your office?\",\"a\":\"Walk-ins welcome Mon–Sat, 10 AM–7 PM. We recommend booking via call to ensure product samples & demo handpieces are ready.\"},{\"q\":\"How do bulk orders work?\",\"a\":\"Use the Bulk Quote form on any product page (orders above ₹10,000). Our team replies with a custom quote within 24 hours.\"}],\"businessHours\":[{\"day\":\"Monday – Friday\",\"hours\":\"10:00 AM – 7:00 PM\"},{\"day\":\"Saturday\",\"hours\":\"10:00 AM – 7:00 PM\"},{\"day\":\"Sunday\",\"hours\":\"Closed\"}],\"responseNote\":\"our team replies within 4 business hours. No bots.\",\"timezone\":\"India Standard Time (UTC+5:30)\",\"openHours\":{\"openHour\":10,\"closeHour\":19,\"openDays\":[1,2,3,4,5,6],\"openLabel\":\"Open now\",\"closedLabel\":\"Closed\"},\"heroBadge\":\"We\'re online now\",\"heroBadgeClosed\":\"We\'re currently offline\",\"heroTitle\":\"Let\'s talk about your clinic\",\"heroSubtitle\":\"Bulk orders, product demos, technical support, partnership — our team replies within 4 business hours. No bots.\",\"formTitle\":\"Send us a message\",\"formChip\":\"Replies in 4 hrs\",\"officeSubtitle\":\"Walk-in welcome — see products in action, talk to our team, leave with a demo.\",\"officeBullets\":[\"Near Yogi Chowk metro stop\",\"Free parking in basement\",\"Book a slot via call to skip wait\"],\"labels\":{\"whatsapp\":\"Chat on WhatsApp\",\"whatsappSub\":\"Instant reply\",\"call\":\"Call us\",\"email\":\"Email us\",\"visit\":\"Visit our office\",\"reachHeading\":\"Reach us directly\",\"faqHeading\":\"Common questions\",\"successTitle\":\"Message received!\",\"formSubtitle\":\"Fill the form or email us directly.\",\"msgHint\":\"Be specific — helps us reply faster\",\"sendBtn\":\"Send Message\",\"deptHelp\":\"What can we help with?\",\"fieldName\":\"Full Name *\",\"fieldPhone\":\"Phone Number *\",\"fieldEmail\":\"Email *\",\"fieldMsg\":\"Your message *\",\"visitBadge\":\"Visit Us\",\"officeHeading\":\"Our Office\",\"reachSales\":\"Sales\",\"reachSupport\":\"Support\",\"reachEmailSales\":\"Email Sales\",\"reachGeneral\":\"General Info\",\"privacyNote\":\"By submitting, you agree to our Privacy Policy. We never share your data.\",\"followHeading\":\"Follow us\",\"hoursHeading\":\"Business hours\"},\"statChips\":[{\"icon\":\"⚡\",\"label\":\"Response: under 4 hrs\"},{\"icon\":\"🕐\",\"label\":\"Mon–Sat • 10 AM – 7 PM IST\"},{\"icon\":\"🦷\",\"label\":\"Trusted by 1000+ clinics\"}]}');

-- 3) Shared company info — feeds the Reach Us cards (phone, email, address, hours).
INSERT IGNORE INTO site_settings (skey, svalue) VALUES
('company', '{\"name\":\"Smart Dental Innovations\",\"shortName\":\"Dentinno\",\"parent\":\"Younique Dental Innovations\",\"tagline\":\"Innovating Dentistry, One Tool at a Time\",\"description\":\"A division of Younique Dental Innovations, we are Surat\'s premier destination for advanced dental products — designed to empower clinicians, elevate care, and deliver clinical excellence in every procedure.\",\"city\":\"Surat\",\"state\":\"Gujarat\",\"pincode\":\"395006\",\"address\":\"Third Floor, Swastik Plaza, 308, Savlia Cir, Yogi Chowk Ground, Chikuwadi, Varachha, Surat, Gujarat 395006\",\"addressShort\":\"Third Floor, Swastik Plaza, Varachha, Surat, Gujarat 395006\",\"email\":\"info@smartdentalinnovations.com\",\"emailSales\":\"smartdentalinnovations.web@gmail.com\",\"phone\":\"+91 92653 18584\",\"phoneSales\":\"+91 93287 62586\",\"hours\":\"Mon to Sat (10:00 AM to 7:00 PM)\"}');