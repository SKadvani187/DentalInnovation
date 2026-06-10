// Site settings (company, payments, coupons, featured, etc) fetched once from the API,
// with the static site.js / featured.js values as fallback so the app works offline.
import { createContext, useContext, useEffect, useState } from "react";
import api from "../lib/api";

import * as siteStatic from "../data/site";
import { featured as featuredStatic } from "../data/featured";
import { premiumCategories as premiumStatic } from "../data/products";

const STATIC = {
  company: siteStatic.company,
  stats: siteStatic.stats,
  socials: siteStatic.socials,
  footerConfig: siteStatic.footerConfig,
  payments: siteStatic.payments,
  bulkRule: siteStatic.bulkRule,
  shippingConfig: siteStatic.shippingConfig,
  taxConfig: siteStatic.taxConfig,
  coupons: siteStatic.coupons,
  sortOptions: siteStatic.sortOptions,
  pricePresets: siteStatic.pricePresets,
  priceBounds: siteStatic.priceBounds,
  gvpThreshold: siteStatic.gvpThreshold,
  lowStockThreshold: siteStatic.lowStockThreshold,
  gvpPage: siteStatic.gvpPage,
  shopByPricePage: siteStatic.shopByPricePage,
  navMenu: siteStatic.navMenu,
  policies: siteStatic.policies,
  offerZoneHero: siteStatic.offerZoneHero,
  combosPage: siteStatic.combosPage,
  tierOffers: siteStatic.tierOffers,
  productDefaults: siteStatic.productDefaults,
  paymentOptions: siteStatic.paymentOptions,
  sectionToCategory: siteStatic.sectionToCategory,
  sampleReviews: siteStatic.sampleReviews,
  productBenefits: siteStatic.productBenefits,
  productContent: siteStatic.productContent,
  heroSlides: siteStatic.heroSlides,
  banners: siteStatic.banners,
  trustBadges: siteStatic.trustBadges,
  rfSection: siteStatic.rfSection,
  homeSections: siteStatic.homeSections,
  contactConfig: siteStatic.contactConfig,
  aboutConfig: siteStatic.aboutConfig,
  aboutSections: siteStatic.aboutSections,
  contactSections: siteStatic.contactSections,
  featured: featuredStatic,
  premiumCategories: premiumStatic,
};

const SettingsContext = createContext(STATIC);

// Cache the last API settings so repeat loads have admin values (logo, etc.)
// instantly — no flash of the static/bundled fallback before the API responds.
const CACHE_KEY = "sdi:settings";
function readCache() {
  try { const r = localStorage.getItem(CACHE_KEY); return r ? JSON.parse(r) : null; }
  catch { return null; }
}

export function SettingsProvider({ children }) {
  // Hydrate from cache when available; __loaded tells consumers the real values
  // are present (cached or fetched) so they can avoid showing a fallback too early.
  const [settings, setSettings] = useState(() => {
    const cached = readCache();
    return cached ? { ...STATIC, ...cached, __loaded: true } : { ...STATIC, __loaded: false };
  });

  useEffect(() => {
    let alive = true;
    api.settings()
      .then((s) => {
        if (!alive || !s) return;
        // Merge: API values override static; keep static for any missing key.
        const merged = Object.fromEntries(Object.entries(s).filter(([, v]) => v != null));
        setSettings((prev) => ({ ...prev, ...merged, __loaded: true }));
        try { localStorage.setItem(CACHE_KEY, JSON.stringify(merged)); } catch { /* quota/private mode — ignore */ }
      })
      .catch((err) => {
        console.warn("[settings] API fallback to static:", err.message);
        if (alive) setSettings((prev) => ({ ...prev, __loaded: true }));   // stop waiting → allow fallback
      });
    return () => { alive = false; };
  }, []);

  return <SettingsContext.Provider value={settings}>{children}</SettingsContext.Provider>;
}

export const useSettings = () => useContext(SettingsContext);
