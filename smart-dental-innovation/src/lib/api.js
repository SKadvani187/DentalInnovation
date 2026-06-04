// Tiny fetch client for the Dentinno storefront API.
// Base URL from VITE_API_URL (.env). Falls back to localhost dev API.

const BASE = import.meta.env.VITE_API_URL || "http://localhost:8088/api/v1";

// Bearer token (set after login). Persisted by AuthContext to localStorage.
let authToken = null;
export function setAuthToken(t) { authToken = t || null; }

function authHeaders() {
  return authToken ? { Authorization: `Bearer ${authToken}` } : {};
}

async function get(path, params) {
  const url = new URL(`${BASE}/${path}`);
  if (params) {
    for (const [k, v] of Object.entries(params)) {
      if (v !== undefined && v !== null && v !== "") url.searchParams.set(k, v);
    }
  }
  const res = await fetch(url, { headers: { Accept: "application/json", ...authHeaders() } });
  if (!res.ok) throw new Error(`API ${path} -> ${res.status}`);
  const json = await res.json();
  if (json.success === false) throw new Error(json.error || `API ${path} failed`);
  return json;
}

async function post(path, body) {
  const res = await fetch(`${BASE}/${path}`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json", ...authHeaders() },
    body: JSON.stringify(body || {}),
  });
  const json = await res.json().catch(() => ({ success: false, error: `HTTP ${res.status}` }));
  if (!res.ok || json.success === false) throw new Error(json.error || `API ${path} -> ${res.status}`);
  return json;
}

export const api = {
  base: BASE,
  // products
  products: (params) => get("products.php", params).then((j) => j.products),
  product: (slug) => get("products.php", { slug }).then((j) => j.product),
  // taxonomy / content
  categories: () => get("categories.php").then((j) => j.categories),
  combos: () => get("combos.php").then((j) => j.combos),
  events: () => get("events.php").then((j) => j.events),
  offers: () => get("offers.php").then((j) => j.offers),
  testimonials: () => get("testimonials.php").then((j) => j.testimonials),
  // combined home feed
  home: () => get("home.php"),
  // site settings (company, payments, featured, etc)
  settings: () => get("settings.php").then((j) => j.settings),
  // otp
  requestOtp: (payload) => post("otp.php?action=request", payload), // {mobile} or {email}
  verifyOtp: (payload) => post("otp.php?action=verify", payload),   // {mobile, otp}
  // auth (customer)
  login: (payload) => post("auth.php?action=login", payload),       // {mobile,name?,email?}
  me: () => get("auth.php", { action: "me" }).then((j) => j.customer),
  updateProfile: (payload) => post("auth.php?action=profile", payload).then((j) => j.customer),
  // orders
  placeOrder: (payload) => post("orders.php", payload).then((j) => j.order),
  myOrders: () => get("orders.php").then((j) => j.orders),
  // coupon
  validateCoupon: (code, subtotal) => get("coupon.php", { code, subtotal }),
  // wishlist (auth)
  getWishlist: () => get("wishlist.php").then((j) => j.ids),
  syncWishlist: (ids) => post("wishlist.php", { ids }).then((j) => j.ids),
};

export default api;
