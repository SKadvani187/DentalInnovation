# Admin Panel + Storefront Integration Plan

**Decision:** Reuse existing PHP admin (`dentinno/`) + build REST API → connect React storefront (`smart-dental-innovation/`) end-to-end.

**Date:** 2026-06-04

---

## Current State

| Part | Tech | State |
|------|------|-------|
| `dentinno/` | PHP 8 + MySQL (PDO), session auth | Admin panel already built — 20 tables, 13 CRUD pages. HTML-coupled, no JSON API for storefront. |
| `smart-dental-innovation/` | React 19 + Vite + Tailwind 4 | Storefront UI complete. 100% static (`src/data/*.js`), no fetch, cart/wishlist/auth in localStorage. |

**Core gap:** No JSON API connecting them. Two separate worlds.

**Key mismatch:** PHP product `id` = INT auto-increment. React product `id` = string (`"p-001"`). React data has computed variants/discounts. → API must map DB rows → React-shaped JSON. Use `slug` or keep string IDs in DB.

---

## Architecture (target)

```
[PHP Admin UI]  --writes-->  [MySQL dentinno_crm]  <--reads--  [PHP REST API /api/v1/*]
                                                                       ^
                                                                       | fetch (JSON)
                                                                       |
                                                              [React Storefront]
```

---

## Phase 1 — DB + Admin running locally
- [ ] Install/confirm MySQL (XAMPP/WAMP since Windows + Apache).
- [ ] Create DB `dentinno_crm`, import `database.sql` then `database_additions.sql`.
- [ ] Set `includes/config.php` creds (DB_USER/DB_PASS).
- [ ] Run PHP admin (XAMPP htdocs or `php -S`), login (admin@dentinno.com / password).
- [ ] Verify CRUD on products, categories, orders works.

## Phase 2 — Seed DB with real storefront data
- [ ] Script to import `src/data/*.js` (products, categories, combos, events, offers, testimonials) → DB tables.
- [ ] Decide ID strategy: keep string IDs (`p-001`) as `slug`/`sku`, OR remap. **Recommend: add/keep `slug` column, storefront uses slug.**
- [ ] Map React fields → DB columns (e.g. React `mrp/price/discount` → DB `price/discount_price/discount_percent`; `images[]` → JSON; `variants[]` → JSON).

## Phase 3 — Build PHP REST API (public, read for storefront)
New folder `dentinno/api/v1/`. JSON only, CORS for React dev origin (localhost:5173).
- [ ] `GET /api/v1/products` (list, filter by category, sort, price range, pagination)
- [ ] `GET /api/v1/products/{slug}` (single + variants + faqs + reviews)
- [ ] `GET /api/v1/categories`
- [ ] `GET /api/v1/combos`, `GET /api/v1/events`, `GET /api/v1/offers`, `GET /api/v1/testimonials`
- [ ] `GET /api/v1/site` (company/site config — or keep static)
- [ ] Each returns React-shaped JSON (match existing `src/data` object shapes so components need minimal change).

## Phase 4 — Auth + customer/cart/order API (write)
- [ ] `POST /api/v1/auth/register`, `POST /api/v1/auth/login` (customer, not admin). JWT or token.
- [ ] `GET/PUT /api/v1/account`, addresses CRUD.
- [ ] `POST /api/v1/orders` (place order → writes `orders` + `order_items`).
- [ ] `GET /api/v1/orders` (customer's orders → OrdersPage).
- [ ] Wishlist sync (optional; can stay localStorage first).
- [ ] Coupon validate endpoint.

## Phase 5 — Wire React to API
- [ ] Add `VITE_API_URL` env + small `api.js` fetch client.
- [ ] Replace `src/data/*` static imports with fetch in components/contexts (keep static as fallback).
- [ ] Auth/Cart/Order contexts → call API instead of localStorage-only.
- [ ] OrdersPage: real orders. CheckoutModal: real order POST (currently order not even persisted).

## Phase 6 — Polish
- [ ] Image hosting (currently external CDNs) — decide upload to admin or keep URLs.
- [ ] Error/loading states, env config for prod, deploy.

---

## Notes / Risks
- Order persistence is MISSING in React today (CheckoutModal builds order, never saves). API fixes this.
- Admin DB schema is richer than storefront (shipping, courses, reviews approval). Storefront uses subset.
- Session auth (admin) stays as-is. Customer-facing auth is separate (new).
- Start small: Phase 1–3 (read-only products live) proves the pipe before write APIs.
