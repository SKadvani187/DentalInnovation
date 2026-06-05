// Exports React static data (with computed variants/discounts) to seed-data.json
// for importing into the dentinno_crm MySQL DB. Run: node export-seed.mjs
import { writeFileSync } from "node:fs";

import { allProducts, premiumCategories } from "./src/data/products.js";
import { categories } from "./src/data/categories.js";
import { combos } from "./src/data/combos.js";
import { events } from "./src/data/events.js";
import { offerZone as offers } from "./src/data/offers.js";
import { testimonials } from "./src/data/testimonials.js";
import { featured } from "./src/data/featured.js";
import * as site from "./src/data/site.js";

const out = {
  categories,        // [{id(slug), title, img?}]
  products: allProducts,  // 52 products, computed variants/discount/inStock
  premiumCategories, // 3 promo tiles
  combos,
  events,
  offers,
  testimonials,
  featured,
  // Site config (all exports from site.js) -> stored as settings rows
  site: {
    company: site.company,
    stats: site.stats,
    socials: site.socials,
    payments: site.payments,
    fbtItems: site.fbtItems,
    freeGifts: site.freeGifts,
    bulkRule: site.bulkRule,
    coupons: site.coupons,
    sortOptions: site.sortOptions,
    pricePresets: site.pricePresets,
    priceBounds: site.priceBounds,
    gvpThreshold: site.gvpThreshold,
    policies: site.policies,
    offerZoneHero: site.offerZoneHero,
    tierOffers: site.tierOffers,
    productDefaults: site.productDefaults,
    sectionToCategory: site.sectionToCategory,
    sampleReviews: site.sampleReviews,
    productBenefits: site.productBenefits,
    productContent: site.productContent,
    heroSlides: site.heroSlides,
    banners: site.banners,
    trustBadges: site.trustBadges,
    rfSection: site.rfSection,
    homeSections: site.homeSections,
    contactConfig: site.contactConfig,
    aboutConfig: site.aboutConfig,
  },
  counts: {
    categories: categories.length,
    products: allProducts.length,
    combos: combos.length,
    events: events.length,
    offers: offers.length,
    testimonials: testimonials.length,
  },
};

writeFileSync("seed-data.json", JSON.stringify(out, null, 2), "utf8");
console.log("WROTE seed-data.json", JSON.stringify(out.counts));
