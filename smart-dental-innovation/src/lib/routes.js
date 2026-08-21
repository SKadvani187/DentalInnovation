// Single source of truth mapping the app's legacy "view names" to clean URL paths.
//
// Two consumers:
//  1) <Routes> in App.jsx uses ROUTES[].path as <Route> patterns.
//  2) to(name, params) builds a URL string from a view name + params. This backs
//     useAppNavigate() (so the codebase keeps calling navigate("product", {id})) and
//     the admin/DB-driven links in Footer/Navbar (which navigate by view-name string).

// Route patterns for <Route path=...>. Order doesn't matter (react-router ranks them).
export const ROUTES = [
  { name: "home", path: "/" },
  { name: "category", path: "/category/:category?" },
  { name: "shopByPrice", path: "/shop-by-price" },
  { name: "gvp", path: "/great-value" },
  { name: "combos", path: "/combos" },
  { name: "events", path: "/events/:id?" },
  { name: "product", path: "/product/:id" },
  { name: "qna", path: "/qna/:id?" },
  { name: "about", path: "/about" },
  { name: "contact", path: "/contact" },
  { name: "account", path: "/account" },
  { name: "orders", path: "/orders" },
  { name: "orderDetails", path: "/order/:id" },
  { name: "wishlist", path: "/wishlist" },
  { name: "address", path: "/address" },
  { name: "offers", path: "/offers" },
  { name: "policy", path: "/policy/:type?" },
];

// Static (no-param) base path per view name. Routes that take a path/query param are
// handled explicitly in to() below; anything not listed there falls back to its base.
const BASE_PATH = {
  home: "/",
  category: "/category",
  shopByPrice: "/shop-by-price",
  gvp: "/great-value",
  combos: "/combos",
  events: "/events",
  product: "/product",
  qna: "/qna",
  about: "/about",
  contact: "/contact",
  account: "/account",
  orders: "/orders",
  orderDetails: "/order",
  wishlist: "/wishlist",
  address: "/address",
  offers: "/offers",
  policy: "/policy",
};

// Reverse of to(): derive the legacy view name from a pathname. Used for active-nav
// highlighting and view-name checks (e.g. hide a FAB on the product page). Longest
// matching base wins so "/shop-by-price" doesn't get shadowed by a shorter prefix.
export function nameFromPath(pathname) {
  if (!pathname || pathname === "/") return "home";
  let best = "home";
  let bestLen = -1;
  for (const [name, base] of Object.entries(BASE_PATH)) {
    if (base === "/") continue;
    if ((pathname === base || pathname.startsWith(base + "/")) && base.length > bestLen) {
      best = name;
      bestLen = base.length;
    }
  }
  return best;
}

const seg = (v) => encodeURIComponent(String(v));

// Turn a human name into a URL-safe slug: "Radio Frequency, 2 Yr" -> "radio-frequency-2-yr".
// Used to put readable product/event names in the URL instead of internal codes.
export const slugify = (s) =>
  String(s || "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-") // any run of non-alphanumerics -> single dash
    .replace(/^-+|-+$/g, "");    // trim leading/trailing dashes

// Resolve a URL slug (name-based, e.g. "radio-frequency...") back to an item in a list,
// matching the slugified name first, then falling back to the raw code/id. Used by the
// product / event / Q&A pages so both new name-URLs and old code-URLs resolve.
export const matchBySlug = (list, slug) =>
  (Array.isArray(list) ? list : []).find((x) => x && slugify(x.name) === slug) ||
  (Array.isArray(list) ? list : []).find((x) => x && String(x.id) === String(slug)) ||
  null;

// Build a query string from a plain object, skipping null/undefined/empty values.
const query = (obj) => {
  const sp = new URLSearchParams();
  for (const [k, v] of Object.entries(obj)) {
    if (v === null || v === undefined || v === "") continue;
    sp.set(k, String(v));
  }
  const s = sp.toString();
  return s ? `?${s}` : "";
};

// Translate a legacy view name + params object into a clean URL string.
// Unknown names resolve to "/" so a bad admin link can never crash navigation.
export function to(name, params = null) {
  const p = params || {};
  const base = BASE_PATH[name];
  if (!base) return "/";

  // Prefer a readable name-slug in the URL; fall back to the raw code/id when no name is
  // available (e.g. admin banner links before the product list has loaded). Both forms
  // resolve at the destination via matchBySlug().
  const nameOrCode = () => (p.name ? slugify(p.name) : p.id != null ? seg(p.id) : null);

  switch (name) {
    case "product": {
      // /product/<name-slug>  (+ optional ?from=<category> to remember the originating category)
      const s = nameOrCode();
      return s ? `${base}/${s}${query({ from: p.fromCategory })}` : base;
    }

    case "category":
      // /category/:category?  (+ ?priceMax=&title=)
      return (p.category ? `${base}/${seg(p.category)}` : base) +
        query({ priceMax: p.priceMax, title: p.title });

    case "shopByPrice":
      return `${base}${query({ priceMax: p.priceMax })}`;

    case "events": {
      const s = nameOrCode();
      return s ? `${base}/${s}` : base;
    }

    case "qna": {
      const s = nameOrCode();
      return s ? `${base}/${s}` : base;
    }

    case "policy":
      return p.type ? `${base}/${seg(p.type)}` : base;

    case "orderDetails":
      return p.id != null ? `${base}/${seg(p.id)}` : "/orders";

    default:
      return base;
  }
}
