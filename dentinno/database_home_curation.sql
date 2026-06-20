-- Curate the storefront home sections to match the reference site (smartdentalinnovations.com):
-- exactly 12 Bestsellers (is_featured) and 12 New Arrivals (is_new), matched by slug so the
-- selection is reproducible across environments. Clears any prior flags first.
UPDATE products SET is_featured = 0, is_new = 0 WHERE is_deleted = 0;

-- Bestsellers
UPDATE products SET is_featured = 1 WHERE slug IN (
  'radio-frequency-advance-cautery',
  'radio-frequency-smart-cautery',
  'r-f-mini-cautery',
  'implant-s-pro-physio-with-2-year-warranty',
  'youni-mobiflash-pro---dental-photography-device',
  'endo-pex-ai-endomotor-with-apex',
  'smart-storm-black-eddition',
  'bone-comprassion-kit',
  'i-smart-endomotor-black',
  'dentoscope',
  'smart-ray-x-ray-machine---portable',
  'ultra-sonic-cleaner'
);

-- New Arrivals
UPDATE products SET is_new = 1 WHERE slug IN (
  'implant-s-plus',
  'scaleblast-pro',
  'smart-portable-suction-unit',
  'smart-ttl-loupe',
  'ttl-loupe-plus',
  'ttl-ergo-loupe',
  'ultra-scaler-pro',
  'mini-aero-blast',
  'smart-cam-12',
  'dental-saddle-stool',
  'younismart-dental-loupe-light',
  'lumi-scope'
);
