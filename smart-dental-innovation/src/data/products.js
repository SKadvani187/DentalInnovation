// All product data is DB-only — fetched from the API (see useApiData.js / useHomeData.js).
// These exports remain only so the few static helpers below resolve; the API fills
// real product data at runtime.

// Premium categories are DB-only — fetched via settings API (site_settings key
// 'premiumCategories', merged in SettingsContext). Empty; DB value always overrides.
export const premiumCategories = [];

// Static product list is empty (DB-only). findProductById is kept for components that
// still call it as a synchronous fallback; it simply returns undefined now.
export const allProducts = [];

export const findProductById = (id) => allProducts.find((p) => p.id === id);
