# Calculation & Logic Audit — Findings + Fixes

> Status: **all severities fixed** in this pass. Each item lists the original problem, its severity, and what was changed. Companion doc: [SYSTEM_WORKFLOW_GUIDE.md](SYSTEM_WORKFLOW_GUIDE.md).

## Summary

| # | Severity | Area | Status |
|---|---|---|---|
| C1 | Critical | Cart total never reached checkout/order | ✅ Fixed |
| C2 | Critical | Coupon not revalidated/recorded at order | ✅ Fixed |
| H1 | High | Combos could be oversold | ✅ Fixed |
| H2 | High | Two disconnected shipping systems | ✅ Fixed (server-side flat rule from settings) |
| H3 | High | GST/Tax never applied | ✅ Fixed (configurable, default off) |
| M1 | Medium | Coupon rounding client≠server | ✅ Fixed |
| M2 | Medium | Free-gift MRP inflated "You saved" | ✅ Fixed |
| M3 | Medium | Order total not rounded (paise drift) | ✅ Fixed |
| M4 | Medium | Offer-expiry string compare fragile | ✅ Fixed |
| L1 | Low | Float accumulation in subtotal | ✅ Fixed |
| L2 | Low | Divide-by-zero guards on discount % | ✅ Fixed |
| L3 | Low | Star-rating rounding | ✅ Verified — not a bug (see note) |
| L4 | Low | Formatter inconsistency in cart | ✅ Fixed |
| L5 | Low | Discount-% logic duplicated | ✅ Fixed (shared util) |

**The big change:** order money is now computed **once, server-side**, and the cart + checkout both read the same shared calculator, so the number shown and the number charged always agree.

---

## Architecture of the fix

- **New server module** [dentinno/api/v1/_pricing.php](dentinno/api/v1/_pricing.php) — `couponEvaluate()` (validate a code) and `computeOrderTotals()` (bulk + coupon + shipping + tax → final total). Single source of truth.
- **New storefront util** [smart-dental-innovation/src/lib/pricing.js](smart-dental-innovation/src/lib/pricing.js) — `computeCartPricing()`, `couponDiscountFor()`, `discountPct()` — **mirrors** the PHP logic so cart/checkout match the server.
- **New settings** `shippingConfig` `{freeThreshold, flatRate}` and `taxConfig` `{enabled, rate, inclusive}` (defaults in [site.js](smart-dental-innovation/src/data/site.js); server falls back to the same defaults). Editable via `site_settings` without code changes.

---

## Critical

### C1 — Cart's discounted total never reached checkout or the order
**Was:** `CheckoutModal` showed/charged the raw `subtotal`; `buildPayload` sent no discount/shipping; `orders.php` defaulted both to 0. Coupon, 10% bulk savings and ₹600 delivery in the cart were cosmetic — the cart footer total ≠ the checkout total, and coupons/bulk weren't honored.
**Fix:**
- `orders.php` now calls `computeOrderTotals($subtotal, $lines, $couponCode)` and **persists** the server-computed `discount`, `shipping_charge`, `tax`, `total`.
- Cart money moved into a shared calculator: [CartContext.jsx](smart-dental-innovation/src/context/CartContext.jsx) exposes `pricing` (mrpTotal, bulkSavings, couponDiscount, deliveryCharges, tax, finalTotal, totalSaved) + the applied coupon (persisted in `localStorage: sdi:coupon`).
- [CartDrawer.jsx](smart-dental-innovation/src/components/modals/CartDrawer.jsx) and [CheckoutModal.jsx](smart-dental-innovation/src/components/modals/CheckoutModal.jsx) both read `pricing.finalTotal`; checkout sends only `couponCode` (the server re-prices).
**Result:** cart total = checkout total = persisted order total.

### C2 — Coupon never revalidated or recorded at order time
**Was:** even if a discount were sent, `orders.php` only clamped it; no validity/limit check, `coupons.uses_count` never incremented.
**Fix:** `orders.php` revalidates the coupon via `couponEvaluate()` inside the order transaction and bumps `uses_count` on success. `coupon.php` now uses the same evaluator (one code path).

---

## High

### H1 — Combos could be oversold
**Was:** stock decrement only ran for lines with a `product_id`; combo lines have `product_id=null`, so `combos.stock` was never reduced.
**Fix:** [orders.php](dentinno/api/v1/orders.php) adds an atomic combo decrement: `UPDATE combos SET stock = stock - ? WHERE slug=? AND stock >= ?` with an affected-row check (rolls back if the last unit was taken concurrently).

### H2 — Two disconnected shipping systems
**Was:** cart used a hardcoded flat ₹600 / free-over-₹20,000; the admin shipping rules/zones/pincodes module was never consulted at checkout.
**Fix:** shipping is now computed **server-side** in `computeOrderTotals()` from `shippingConfig` (`freeThreshold`, `flatRate`), and the cart reads the same setting — one consistent, configurable rule. *(The advanced zones/weight rules engine remains in the admin for a future enhancement; it is intentionally not wired into checkout cost yet.)*

### H3 — GST/Tax never applied
**Was:** `orders.tax` column existed but stayed 0; no tax computed or shown.
**Fix:** a configurable `taxConfig` `{enabled, rate, inclusive}`, **default disabled** (prices treated as tax-inclusive → no behavior change). When enabled with `inclusive:false`, `rate%` is added on the discounted amount server-side, persisted to `orders.tax`, and shown as a "Tax (GST)" line in the cart. **To charge GST: set `taxConfig` in `site_settings`, e.g. `{enabled:true, rate:18, inclusive:false}`.**

---

## Medium

### M1 — Coupon rounding mismatch (client vs server)
**Was:** backend capped-then-rounded; frontend rounded-then-capped → up to ~₹1 drift.
**Fix:** both now **cap then round** (`couponDiscountFor` in `pricing.js`, `couponEvaluate` in `_pricing.php`).

### M2 — Free-gift MRP inflated "You saved"
**Was:** gift lines (price 0, mrp>0) counted their MRP as savings.
**Fix:** `computeCartPricing` excludes `type==='gift'` lines from `mrpTotal`/`totalSaved`; the server's bulk calc also skips gift lines.

### M3 — Order total not rounded → Razorpay paise drift
**Fix:** `computeOrderTotals()` rounds subtotal/discount/shipping/tax/total to 2 decimals; Razorpay derives paise from the rounded `orders.total`.

### M4 — Offer-expiry string comparison fragile
**Fix:** [orders.php](dentinno/api/v1/orders.php) now compares with `strtotime($valid_till) < strtotime($now)`.

---

## Low

- **L1 — Float accumulation:** all cart math rounds to paise via `r2()` in `pricing.js`.
- **L2 / L5 — Discount-% guards + duplication:** single `discountPct(mrp, price)` util (guards `mrp<=0`) used by [ProductDetailPage](smart-dental-innovation/src/components/pages/ProductDetailPage.jsx), [CombosPage](smart-dental-innovation/src/components/pages/CombosPage.jsx), [GreatValuePage](smart-dental-innovation/src/components/pages/GreatValuePage.jsx).
- **L4 — Formatter:** cart MRP line uses the shared `fmt()` (2 decimals) instead of `toFixed(0)`.
- **L3 — Star-rating rounding:** **investigated, not a bug.** JS `Math.round` is half-up (2.5 → 3), so a 2.5 average shows 3 filled stars (rounds up), which is standard. Left as-is; switch to half-star icons later only if preferred.

---

## Verification done
- ✅ PHP lint clean: `_pricing.php`, `coupon.php`, `orders.php`.
- ✅ Storefront build clean (`npm run build`).
- ⚠️ Not run here (no DB): a live end-to-end order. Please test: apply a coupon → cart total = checkout total; place a COD order → confirm `orders.discount/shipping_charge/tax/total` persisted, `coupons.uses_count` incremented, product **and** combo stock decremented; tamper test (bad coupon / expired offer) rejected.

## No migration required
All new behavior reads `site_settings` with safe built-in defaults, so nothing breaks if `shippingConfig`/`taxConfig` rows don't exist. Add them only to change shipping or enable GST.
