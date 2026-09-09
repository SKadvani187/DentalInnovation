-- Curate the storefront home sections to match the reference site (smartdentalinnovations.com).
-- Source: the homepage builder config (premium-products-view blocks "Bestsellers" / "New Arrivals"),
-- scraped to site-scraper/scraper/out/home_curation.json and matched to imported products by SKU/slug.
-- 12 Bestsellers (is_featured) + 12 New Arrivals (is_new). Clears prior flags first. Idempotent.
UPDATE products SET is_featured = 0, is_new = 0 WHERE is_deleted = 0;

-- Bestsellers
UPDATE products SET is_featured = 1 WHERE slug IN (
  'radio-frequency-advance-cautery',
  'cautery-machine',
  'radio-frequency-mini-cautery',
  'implant-s-pro-physio-dispenser-fiber-optic-implant-motor',
  'youni-mobiflash-pro',
  'endo-pex-ai',
  'smart-storm-black-eddition',
  'bone-expander-drills',
  'i-smart-endomotor-black',
  'dento-scope-10mm',
  'smart-ray-x-ray-machine',
  'ultra-sonic-cleaner'
);

-- New Arrivals
UPDATE products SET is_new = 1 WHERE slug IN (
  'implant-s-plus',
  'scaleblast-pro',
  'smart-vacura-portable-suction-unit',
  'smart-ttl-loupe',
  'smart-ttl-loop',
  'ttl-ergo-loupe',
  'ultra-scaler-pro',
  'mini-aero-blast',
  'smart-cam-12',
  'dental-saddle-stool',
  'younismart-dental-loops-light',
  'lumi-scope'
);
