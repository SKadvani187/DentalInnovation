# Offer Zone — Robust E-commerce Redesign Plan

> Reference document. No code has been changed. When you say to implement, follow this plan.

## Context

The Offer Zone lets admins create promotional "deal" cards (a discounted main product + free gift items, a countdown, social proof). Review of the current implementation surfaced correctness, security, and fulfilment gaps that make it unsafe for real e-commerce:

1. **Checkout trusts the client price.** `dentinno/api/v1/orders.php` (lines 62-65, 105-108) computes `subtotal` and stores `order_items.price` straight from the request body — no validation against `products`/`offers`. A user can pay any price. (Affects all products; offers make it worse.)
2. **No stock enforcement.** `products.stock` exists (`database.sql:59`) but orders.php never checks or decrements it — only bumps `total_sales`. Overselling is possible.
3. **Free gifts are display-only.** `OfferZonePage.jsx` `onAdd()` adds only the main product; gifts are never added to cart or recorded in the order, so fulfilment has no record of what to ship.
4. **Expired offers aren't filtered server-side.** `offers.php:6` returns all `is_active=1` offers regardless of `valid_till`; only the client disables the button. `valid_till` is `DATE` (no time), so countdowns are imprecise.
5. **Loose data model.** Offer→product is a JSON snapshot (`main_product`, `free_items`) with no FK, so it isn't queryable/joinable and product changes drift.
6. **Cart key collision.** `CartContext.jsx` keys items by product id, so an offer line and a normal line for the same product merge and share one price.

**Owner decisions for this redesign:** full robust redesign; free gifts added as **₹0 line items** recorded in the order; enforce the **product's own stock** at checkout (no separate offer stock); move offer→product to a **relational FK** model (`offers.product_id` + new `offer_items` table). Outcome: offers that are price-safe, stock-correct, fulfilment-complete, and queryable.

## How an Offer Zone should work (target behavior)

- An offer references a **real product** (FK) at a **special price**, optionally bundling **gift products** (also real products) given at ₹0.
- Grabbing a deal adds the **main line at the offer price** + **gift lines at ₹0** to the cart, all tied to the offer.
- The **server is the source of truth** for every price: it ignores client prices, resolves offer/product prices from the DB, forces gifts to ₹0, validates gifts belong to a live offer, and rejects expired offers.
- **Stock is reserved atomically** on order creation (main + gifts decrement `products.stock`); races can't oversell.
- Expired offers (`valid_till` passed) are **excluded from the API** and un-grabbable.
- The order records gift lines at ₹0 so the warehouse ships them.

---

## Implementation Plan (phased, each step independently deployable)

### Phase 1 — Schema (new idempotent `database_*.sql` migrations, run via `dentinno/migrate.php`)

**`dentinno/database_storefront_offers_relational.sql`**
- `ALTER TABLE offers ADD COLUMN IF NOT EXISTS product_id INT NULL AFTER slug;` + `ADD INDEX IF NOT EXISTS idx_offers_product (product_id)`. Nullable, **no hard FK** (so deleting a product doesn't cascade-delete/block the offer — integrity enforced in app).
- `ALTER TABLE offers MODIFY COLUMN valid_till DATETIME NULL;` (precise countdowns; app TZ is `Asia/Kolkata`).
- `CREATE TABLE IF NOT EXISTS offer_items` (gift rows): `id, offer_id INT NOT NULL (FK offers ON DELETE CASCADE), product_id INT NULL, name, variant, image, mrp DECIMAL(10,2), qty INT DEFAULT 1, sort_order, created_at`. `product_id` no hard FK (keep snapshot if product deleted).

**`dentinno/database_storefront_offers_relational_backfill.sql`** (sorts after; idempotent guards)
- Backfill `offers.product_id` from `main_product->productId` slug where NULL.
- Push existing midnight `valid_till` to `23:59:59` so DATE→DATETIME doesn't expire live offers a day early.
- Backfill `offer_items` from `free_items` JSON using `JSON_TABLE`, guarded by `NOT EXISTS` so re-runs don't duplicate. **Risk:** `JSON_TABLE` needs MySQL 8.0.4+; if host is 5.7/older MariaDB, use a one-off PHP backfill script instead — confirm DB version first.

**`dentinno/database_order_items_offer.sql`**
- `ALTER TABLE order_items ADD COLUMN IF NOT EXISTS line_type VARCHAR(16) DEFAULT 'product'`, `ADD COLUMN IF NOT EXISTS offer_id INT NULL`. Lets fulfilment see ₹0 gift lines tied to an offer.

### Phase 2 — Backend read path
- **`dentinno/api/v1/offers.php`:** compute `$now = date('Y-m-d H:i:s')` (PHP TZ) and add `AND (valid_till IS NULL OR valid_till >= ?)` bound param to exclude expired. Batch-load `offer_items WHERE offer_id IN (...)`, group by offer. Keep existing `soldToday`/product-sync.
- **`dentinno/api/v1/_map.php` `mapOffer`:** accept gift rows; build `freeItems` relational-first, **fall back to `free_items` JSON** for un-migrated rows. **Keep output shape identical** (`freeItems:[{name,mrp,image,variant}]`) so the storefront renders unchanged; add optional `productId` + `qty` per gift for cart building. Emit `validTill` as ISO-8601 with IST offset (`...+05:30`) so JS `new Date()` is unambiguous.

### Phase 3 — Admin (`dentinno/pages/offers.php`)
- The form already picks real products for main + gifts. In `action==='save'` (inside a transaction): resolve main slug→`products.id` into `offers.product_id` (reject if it isn't an active product); keep writing JSON columns + `total_mrp`/`you_save` for back-compat; then `DELETE FROM offer_items WHERE offer_id=?` and re-insert one row per gift (resolve slug→id, snapshot name/mrp/image, `qty=1`). Recompute MRPs from live `products` prices, not client values. `valid_till` date input → append ` 23:59:59` on save.
- `delete` unchanged (FK cascade removes `offer_items`).

### Phase 4 — Checkout rewrite (`dentinno/api/v1/orders.php`) — the security-critical change
**New cart payload contract** (backward compatible — missing `type` ⇒ `product`):
```
{ id, name, qty, variant, type:"product" }                         // catalog line
{ id, name, qty, variant, type:"offer", offerId }                  // offer main line
{ id, name, qty, variant, type:"gift",  offerId, parentId }        // free gift line
```
Server **ignores any client price** and resolves authoritatively, inside the existing transaction:
- `type:"offer"` → load offer by slug; must be `is_active=1` and not past `valid_till` (PHP `$now`), else reject (409/422 "offer expired"). Price = `offers.special_price`. Posted slug must match `offers.product_id`'s product (anti-tamper). Stock-check the product.
- `type:"gift"` → must reference an offer line present in the same cart, and the gift must belong to that offer (`offer_items`). **Force price 0.00**, `total 0`. Gift qty = parent offer line qty (recomputed server-side). Decrement gift product stock too; if out of stock, reject the parent offer line.
- `type:"product"` → resolve active product; price = `discount_price ?? price`; stock-check.

Then: **recompute `subtotal`/`total` server-side** (ignore client values; gifts contribute 0). **Atomically decrement stock** per catalog line: `UPDATE products SET stock=stock-?, total_sales=total_sales+? WHERE id=? AND stock>=?` and verify affected-rows==1 (else roll back → out-of-stock, prevents oversell on the last-unit race). Persist `order_items` with authoritative price/total + `line_type`/`offer_id`. Keep COD vs online `payment_status` logic and the post-commit WhatsApp send unchanged.

### Phase 5 — Storefront
- **`smart-dental-innovation/src/components/pages/OfferZonePage.jsx` `onAdd`:** add main line (`type:"offer"`, `offerId`) + one ₹0 line per `freeItems` (`type:"gift"`, `offerId`, `parentId`, `qty`). (Expired-button disable already done.)
- **`smart-dental-innovation/src/context/CartContext.jsx`:** fix key collision — key offer/gift lines as `` `${type}:${offerId}:${id}` `` so they don't merge with normal lines or across offers; persist `type/offerId/parentId`. On `updateQty`/`removeFromCart` of an offer line, recompute/remove its `parentId` gift lines (simplest: derive gift lines from the offer line on every mutation).
- **CartDrawer.jsx:** render ₹0 lines as **FREE** (existing badge), strike-through gift `mrp`; hide qty stepper/remove on gift lines.
- **CheckoutModal.jsx `buildPayload`:** include `type/offerId/parentId`; stop relying on client `subtotal`/`price` for trust (display only). Surface server stock/expiry errors to the toast.

### Phase 6 — Razorpay (no change needed)
`dentinno/api/v1/payment_razorpay.php:87` already recomputes `amountPaise` from DB `orders.total`. Once Phase 4 writes authoritative totals, the gateway amount is automatically correct; invalid carts are rejected before any Razorpay order is created.

### Rollout order
SQL (Phase 1) → backend read (Phase 2) → admin (Phase 3) → checkout + order_items migration (Phase 4) → storefront (Phase 5). At every step the API response shape stays valid (additive optional fields), and the server treats untyped/legacy cart items as price-authoritative `product` lines, so old client builds keep placing safe orders mid-deploy.

---

## Edge cases to handle
- **Product deleted/renamed:** offer keeps `product_id` + gift snapshots; API skips missing slugs (already guarded); checkout rejects an offer line whose product no longer resolves to an active product.
- **Offer expires while in cart:** server rejects at checkout with a clear message; client clears/flags the line.
- **Gift out of stock:** reject the parent offer line (the deal is the bundle).
- **Multiple offers / same product as normal + offer line:** distinct cart keys keep them separate; stock decrements summed atomically.
- **Offer line qty > 1:** gifts scale 1:1; server recomputes gift qty from parent.
- **Abandoned online order:** stock is decremented at creation (soft reservation); decide later whether to reconcile/restock stale unpaid online orders (follow-up, non-blocking).

## Decisions to confirm before coding
1. DATE→DATETIME `valid_till` end-of-day backfill (changes expiry semantics) — sign off.
2. MySQL version ≥ 8.0.4 for `JSON_TABLE` backfill, else PHP fallback script.
3. Gift qty scales 1:1 with offer line qty (recommended) vs always 1.
4. Gift-out-of-stock → reject whole offer line (recommended) vs ship main only.

## Critical files
- `dentinno/api/v1/orders.php` — checkout rewrite (authoritative pricing, ₹0 gifts, atomic stock decrement, offer validation)
- `dentinno/api/v1/offers.php` — filter expired, relational gift load
- `dentinno/api/v1/_map.php` — relational `mapOffer`, ISO datetime, gift productId/qty
- `dentinno/pages/offers.php` — write `offers.product_id` + `offer_items`
- `smart-dental-innovation/src/components/pages/OfferZonePage.jsx` / `src/context/CartContext.jsx` / `CartDrawer.jsx` / `CheckoutModal.jsx` — multi-line add, key fix, ₹0 gift display, payload
- New SQL: `database_storefront_offers_relational.sql`, `database_storefront_offers_relational_backfill.sql`, `database_order_items_offer.sql`

## Verification (end-to-end)
1. `php dentinno/migrate.php --status` → `migrate.php` → `--status` (applied). Spot-check `offers.product_id` populated, `valid_till` end-of-day, `offer_items` has one row per former JSON gift.
2. `GET /api/v1/offers.php`: same `freeItems` shape; an offer with past `valid_till` is absent.
3. Happy path: Grab This Deal → cart shows main at special price + gift(s) FREE → checkout COD. Verify `order_items` main at `special_price`, gift at `price=0,total=0,line_type='gift'`; `orders.subtotal` excludes gifts; `products.stock` decremented for main + gift.
4. Tamper test: POST a doctored low `price` and a `gift` line for a product not in the offer → server uses `special_price`, rejects bogus gift, rejects expired-offer line.
5. Stock race: product `stock=1`, two concurrent orders → exactly one succeeds, stock never negative.
6. Razorpay: online order amount equals server `orders.total` even if client `subtotal` is tampered.