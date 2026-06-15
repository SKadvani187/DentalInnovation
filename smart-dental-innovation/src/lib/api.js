// Tiny fetch client for the Dentinno storefront API.
// Base URL from VITE_API_URL (.env). Falls back to localhost dev API.
// ROOT is one level above /v1 — the AI image/voice search endpoints live at /api/*.php.

const BASE = import.meta.env.VITE_API_URL || "http://localhost/dentinno/api/v1";

const ROOT = BASE.replace(/\/v1\/?$/, "");

// Bearer token (set after login). Persisted by AuthContext to localStorage.
let authToken = null;
export function setAuthToken(t) { authToken = t || null; }

function authHeaders() {
  // Send the token two ways: the standard Authorization header AND a custom
  // X-Auth-Token header. Apache frequently strips Authorization on shared/XAMPP
  // hosts, which breaks Bearer auth; X-Auth-Token is never stripped, so auth keeps
  // working regardless of server config.
  return authToken
    ? { Authorization: `Bearer ${authToken}`, "X-Auth-Token": authToken }
    : {};
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
  // Live shipping quote (server computes via DB shipping engine). { items:[{slug,qty}], pincode? }
  shippingQuote: (payload) => post("shipping_quote.php", payload), // -> { shipping, free, weight, methods }
  // Frequently Bought Together for the cart. { slugs:[...] } -> { items:[product] }
  fbt: (slugs) => post("fbt.php", { slugs }).then((j) => j.items),
  // Per-product free gifts for the cart. { slugs:[...] } -> { items:[{...,price:0,parentSlug}] }
  gifts: (slugs) => post("gifts.php", { slugs }).then((j) => j.items),
  // per-product FAQs (active)
  faqs: (slug) => get("faqs.php", { product: slug }).then((j) => j.faqs),
  // per-product customer Q&A (answered + approved) + submit
  questions: (slug) => get("questions.php", { product: slug }).then((j) => j.questions),
  submitQuestion: (payload) => post("questions.php", payload), // { product, question, name?, email? }
  // Helpful vote on an answered question. { id, dir:"up"|"down", undo? } -> { up, down }
  voteQuestion: (payload) => post("questions.php", { action: "vote", ...payload }),
  // product reviews (real DB; approved only) + aggregate summary
  reviews: (slug) => get("reviews.php", { product: slug }), // { reviews, summary }
  submitReview: (payload) => post("reviews.php", payload),  // { product, name, email?, rating, title?, review }
  // combined home feed
  home: () => get("home.php"),
  // site settings (company, payments, featured, etc)
  settings: () => get("settings.php").then((j) => j.settings),
  // pincode delivery ETA + COD check
  checkDelivery: (pincode) => get("delivery.php", { pincode }), // { serviceable, days, cod, label, eta }
  // contact form submit
  contact: (payload) => post("contact.php", payload),
  // Bulk quote request (product page form). { name, phone, email, pincode, address, productSlug, productName, quantity, expectedPrice }
  bulkQuote: (payload) => post("bulk_quote.php", payload),
  // AI image search (Claude Vision) — endpoint lives at /api/image_search.php (above /v1)
  imageSearch: async (dataUrl) => {
    const res = await fetch(`${ROOT}/image_search.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ image: dataUrl }),
    });
    const json = await res.json().catch(() => ({ success: false }));
    if (!res.ok || json.success === false) throw new Error(json.message || `image_search -> ${res.status}`);
    return json; // { success, query, products, message }
  },
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
  getOrder: (id) => get("orders.php", { id }).then((j) => j.order),
  // refunds / returns
  requestRefund: (payload) => post("refunds.php", payload), // { orderId, reason } -> { refundId, status }
  myRefunds: () => get("refunds.php").then((j) => j.refunds),
  getRefundForOrder: (orderId) => get("refunds.php", { orderId }).then((j) => j.refund), // null if none
  // razorpay online payment (keyId/amount come back from the server — never trusted from client)
  createRazorpayOrder: (orderId) => post("payment_razorpay.php?action=create", { orderId }),
  verifyRazorpayPayment: (payload) => post("payment_razorpay.php?action=verify", payload),
  // coupon
  validateCoupon: (code, subtotal) => get("coupon.php", { code, subtotal }),
  // wishlist (auth)
  getWishlist: () => get("wishlist.php").then((j) => j.ids),
  syncWishlist: (ids) => post("wishlist.php", { ids }).then((j) => j.ids),
  // cart (auth) — server-side cart for logged-in customers.
  // mode "merge" (default) unions the guest cart with the saved one on login;
  // "replace" overwrites the saved cart with the current items.
  getCart: () => get("cart.php").then((j) => j.items),
  syncCart: (items, mode = "replace") => post("cart.php", { items, mode }).then((j) => j.items),
};

// Inject Razorpay's hosted checkout.js once; resolves true when window.Razorpay is ready.
let razorpayScriptPromise = null;
export function loadRazorpayScript() {
  if (typeof window !== "undefined" && window.Razorpay) return Promise.resolve(true);
  if (razorpayScriptPromise) return razorpayScriptPromise;
  razorpayScriptPromise = new Promise((resolve, reject) => {
    const s = document.createElement("script");
    s.src = "https://checkout.razorpay.com/v1/checkout.js";
    s.onload = () => resolve(true);
    s.onerror = () => {
      razorpayScriptPromise = null;
      reject(new Error("Failed to load Razorpay checkout"));
    };
    document.body.appendChild(s);
  });
  return razorpayScriptPromise;
}

export default api;
