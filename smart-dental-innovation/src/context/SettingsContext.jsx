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
  payments: siteStatic.payments,
  fbtItems: siteStatic.fbtItems,
  freeGifts: siteStatic.freeGifts,
  bulkRule: siteStatic.bulkRule,
  coupons: siteStatic.coupons,
  sortOptions: siteStatic.sortOptions,
  pricePresets: siteStatic.pricePresets,
  priceBounds: siteStatic.priceBounds,
  gvpThreshold: siteStatic.gvpThreshold,
  policies: siteStatic.policies,
  offerZoneHero: siteStatic.offerZoneHero,
  tierOffers: siteStatic.tierOffers,
  productDefaults: siteStatic.productDefaults,
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
  featured: featuredStatic,
  premiumCategories: premiumStatic,
};

const SettingsContext = createContext(STATIC);

export function SettingsProvider({ children }) {
  const [settings, setSettings] = useState(STATIC);

  useEffect(() => {
    let alive = true;
    api.settings()
      .then((s) => {
        if (!alive || !s) return;
        // Merge: API values override static; keep static for any missing key.
        setSettings((prev) => ({ ...prev, ...Object.fromEntries(Object.entries(s).filter(([, v]) => v != null)) }));
      })
      .catch((err) => console.warn("[settings] API fallback to static:", err.message));
    return () => { alive = false; };
  }, []);

  return <SettingsContext.Provider value={settings}>{children}</SettingsContext.Provider>;
}

export const useSettings = () => useContext(SettingsContext);
