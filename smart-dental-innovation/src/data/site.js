// Site-wide config — single source of truth for company info, contact, social, marketing copy.
// Edit here to update across all pages.

// Home page section order + visibility. DB-only via settings API (key 'homeSections').
export const homeSections = [];

// RF Cautery showcase section (home). DB-only via settings API (key 'rfSection').
export const rfSection = {};

// About page config — DB-only via settings API (key 'aboutConfig').
export const aboutConfig = {};

// About page section layout — DB-only via settings API (key 'aboutSections').
// AboutPage falls back to its default section order when this is empty.
export const aboutSections = [];

// Contact page section layout — DB-only via settings API (key 'contactSections').
export const contactSections = [];

// Combos page chrome — DB-only via settings API (key 'combosPage').
export const combosPage = {};

// Contact page config (FAQs, departments, hours). DB-only via settings API (key 'contactConfig').
export const contactConfig = {};

// Trust badges strip (home). DB-only via settings API (key 'trustBadges').
export const trustBadges = [];

// Home secondary banners — DB-only via settings API (key 'banners').
export const banners = {};

// Home hero carousel slides — DB-only via settings API (key 'heroSlides').
export const heroSlides = [];

// Header branding (logos + WhatsApp number) — DB-only via settings API (key 'branding').
// Header/WhatsApp button fall back to bundled assets + hardcoded number when empty.
export const branding = {};

// Company info — DB-only via settings API (key 'company').
export const company = {};

// Storefront stats strip — DB-only via settings API (key 'stats').
export const stats = [];

// Social links — DB-only via settings API (key 'socials').
export const socials = [];

// Footer config — DB-only via settings API (key 'footerConfig', seeded in
// database_footerconfig.sql). Footer.jsx guards every field with || defaults.
export const footerConfig = {};

// Payment methods — DB-only via settings API (key 'payments').
export const payments = [];



// Bulk savings rule — DB-only via settings API (key 'bulkRule').
// lib/pricing.js defaults each field, so an empty object can't produce NaN totals.
export const bulkRule = {};

// Shipping rule — DB-only via settings API (key 'shippingConfig'). Server mirror:
// dentinno/api/v1/_pricing.php. lib/pricing.js defaults each field (no NaN on empty).
export const shippingConfig = {};

// Tax (GST) rule — DB-only via settings API (key 'taxConfig'). Server mirror:
// dentinno/api/v1/_pricing.php. lib/pricing.js defaults each field (no NaN on empty).
export const taxConfig = {};

// Coupon offers — DB-only via settings API (key 'coupons').
export const coupons = [];

// Sort options (category & combos) — DB-only via settings API (key 'sortOptions').
export const sortOptions = [];

// Price range presets — DB-only via settings API (key 'pricePresets').
export const pricePresets = [];

// Price filter bounds — DB-only via settings API (key 'priceBounds').
export const priceBounds = {};

// Great Value discount threshold (%). DB-served (key 'gvpThreshold'); kept as a
// meaningful scalar so first-paint before the API doesn't mis-flag every product.
export const gvpThreshold = 10;

// Combos low-stock ribbon threshold. DB-served (key 'lowStockThreshold'); kept as a
// meaningful scalar to avoid a wrong first-paint before the API resolves.
export const lowStockThreshold = 10;

// Great Value Products page chrome — DB-only via settings API (key 'gvpPage').
export const gvpPage = {};

// Shop by Price page chrome — DB-only via settings API (key 'shopByPricePage').
export const shopByPricePage = {};

// Main navbar menu — DB-only via settings API (key 'navMenu', seeded in database_navmenu.sql).
export const navMenu = [];

// Offer Zone hero copy — DB-only via settings API (key 'offerZoneHero').
export const offerZoneHero = {};

// Policy pages (Return / Terms / Privacy) — DB-only via settings API (key 'policies').
// PolicyPage falls back to { title:"Policy", sections:[] } when a type is missing.
export const policies = {};

// Product detail tiered offers — DB-only via settings API (key 'tierOffers').
export const tierOffers = [];

// Product detail page defaults — DB-only via settings API (key 'productDefaults').
export const productDefaults = {};

// Product page "Payment Options" card — DB-only via settings API (key 'paymentOptions').
export const paymentOptions = [];

// Section title → category filter map (home ProductSection View All) — DB-only via
// settings API (key 'sectionToCategory'). ProductSection guards with {} default.
export const sectionToCategory = {};

// Sample reviews — DB-only via settings API (key 'sampleReviews').
export const sampleReviews = [];

// Product detail benefits strip — DB-only via settings API (key 'productBenefits').
export const productBenefits = [];

// Default product detail content (Highlights / Accordions / FAQs) — DB-only via
// settings API (key 'productContent'). Consumers guard each sub-field (|| []).
export const productContent = {};

