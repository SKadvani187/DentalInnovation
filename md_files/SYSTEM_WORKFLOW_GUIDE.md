# Smart Dental Innovations — System Workflow Guide

> How the **Admin panel** and the **Storefront** work together. Part 1 is written for the business owner (plain language). Part 2 is a technical appendix for developers.

---

# Part 1 — For the business owner

## 1. The big picture

Your system is **two separate applications that share one database**:

| | What it is | Who uses it | Where it lives |
|---|---|---|---|
| **Admin panel** | A control room where you add products, set prices, manage orders, write page content, etc. | You and your staff | `dentinno/` (PHP), opened at `…/dentinno/login.php` |
| **Storefront** | The public website customers shop on | Your customers | `smart-dental-innovation/` (React), served at your domain / `http://localhost/` |

They talk to each other through a shared **database** and a set of **API endpoints** (small web addresses the storefront calls to fetch live data).

```
   ┌──────────────┐     writes      ┌──────────────┐     reads via API     ┌──────────────┐
   │  ADMIN PANEL │  ───────────►   │   DATABASE   │   ◄───────────────►   │  STOREFRONT  │
   │  (you/staff) │                 │   (MySQL)    │                       │ (customers)  │
   └──────────────┘                 └──────────────┘                       └──────────────┘
        ▲                                                                         │
        │                         orders, messages, reviews                       │
        └─────────────────────────────────────────────────────────────────────────┘
                         (customers' actions flow back to you)
```

**The golden rule:** whatever you change in the Admin panel shows up on the storefront — usually immediately, after the customer's next page load. You never edit the website's code to change content; you use the Admin panel.

## 2. The two ways your changes reach customers

Everything you manage falls into one of two buckets:

**A) Catalog items** — the "things" you sell or show: products, categories, combos, offers, events, courses, testimonials, and customer reviews/questions. Each has its own Admin screen and its own place on the storefront.

**B) Page content & settings** — the words, images, layout, and rules around those things: your company info, the home page layout, contact/about page text, header logos, coupons, free-gift rules, etc. These are all managed under **Settings**.

> **Safety net:** if the storefront ever can't reach the server, it falls back to a built-in default copy of the content so the site never looks broken. It also remembers the last settings it loaded, so repeat visits are instant. Your real (admin-managed) data always wins once it loads.

## 3. What each Admin screen controls on the storefront

| Admin screen | What you do there | What the customer sees / where |
|---|---|---|
| **Products** | Add/edit products: name, price, discount price, stock, images, description, specs, FAQs | Product listings, product detail pages, search, "featured" sections |
| **Categories** | Create product categories | Category menu and category/filter pages |
| **Combos** | Bundle several products at one price | The **Combos** page; combo cards with savings |
| **Offers** | Build "Offer Zone" deals: a discounted product + free gifts + countdown | The **Offer Zone** page |
| **Events** | Create events/webinars (published ones only) | The **Events** page |
| **Courses** | Create online courses + lessons | The course listings |
| **Testimonials** | Add homepage customer quotes | Homepage testimonials section |
| **Reviews** | Approve / verify / delete customer product reviews | Reviews shown on product pages (only approved ones) |
| **Questions** | Answer & approve customer product questions | Q&A on product pages (only approved ones) |
| **Coupons** | Create discount codes (% or flat, limits, expiry) | Coupon entry/validation in the cart |
| **Shipping** | Define shipping methods, zones, rules, deliverable pincodes | Delivery estimates (see the audit — shipping **cost** is currently a flat rule, not these rules) |
| **Settings** | All page content, layout, branding, company info | Across the whole storefront (see §4) |
| **Orders** | View orders; update status, payment, tracking | Customer's "My Orders"; triggers WhatsApp updates |
| **Customers** | View/edit customer records, their orders & wishlist | (Internal CRM — not shown publicly) |
| **Messages** | Read contact-form submissions | (Internal inbox — see the contact form on the site) |
| **Payments** | See totals received / pending / refunded | (Internal analytics) |
| **Reports** | Revenue, top products, top customers, etc. | (Internal analytics) |
| **Wishlists** | See what customers saved | (Internal analytics; wishlist itself is a customer feature) |
| **Admins** | Manage staff logins and roles | (Internal — admin access control) |

## 4. Settings → which part of the storefront it controls

The **Settings** area is split into tabs. Each tab edits content that appears in a specific place:

| Settings tab | Controls on the storefront |
|---|---|
| **Home Page** | Which homepage sections show and in what order; hero slider; promo banners; stats bar; trust badges |
| **Contact Page** | The whole Contact page: hero text, quick-action cards, the form, business hours, office info, FAQs — and which of those sections are visible |
| **About Page** | The whole About page: story, team, values, milestones, certifications — and section visibility |
| **General → Logos & WhatsApp** | Header logos (one or two, cross-fading) and the floating WhatsApp button's number |
| **General → Navbar / Socials / Policies** | Top menu items, social links, and the Return/Terms/Privacy pages |
| **Catalog** | Offer-zone hero text, combos-page text, product-page extras, **tier (bulk) discounts**, **free-gift threshold**, **coupons** |
| **OTP / WhatsApp** (super-admin only) | The SMS/WhatsApp providers used for login codes and order notifications |

## 5. The customer's journey (and where your admin work shows up)

1. **Browse** — Home, Category, Great Value Products, Shop by Price, Offer Zone, Combos. *(Driven by your Products/Combos/Offers + Settings.)*
2. **Product detail** — full specs, image gallery, approved reviews & Q&A, bulk "tier" pricing, and a pincode delivery check. *(Driven by Products, Reviews, Questions, Catalog settings, Shipping pincodes.)*
3. **Cart** — the customer adds items; the cart shows bulk savings, coupons, free gifts, and a delivery line. *(Driven by Coupons + Catalog settings.)*
4. **Checkout** — they enter an address and choose **Cash on Delivery** or **Pay Online (Razorpay)**.
5. **Order created** — it appears in your **Orders** screen.
6. **You fulfil it** — update status (confirmed → shipped → delivered) and add tracking; the customer gets **WhatsApp** updates automatically (if WhatsApp is configured).

> ⚠️ **Important — please read the companion audit.** Today the **cart total** (with coupon, bulk discount, and delivery) is **not** the number used at checkout — checkout charges the plain items total. Coupons and bulk discounts shown in the cart are not actually applied to the order, and delivery isn't charged. This is the #1 item in [CALCULATION_ISSUES_AUDIT.md](CALCULATION_ISSUES_AUDIT.md) and should be fixed before relying on coupons/shipping.

## 6. Accounts & access

- **Customers** log in with their **phone number + a one-time code (OTP)** — no passwords. Their profile, addresses, orders, and wishlist are tied to that number.
- **Admin staff** log in with email + password and have a **role**:
  - **Super Admin** — full access, including sensitive settings (OTP/WhatsApp providers).
  - **Admin / Staff** — day-to-day management; cannot change the protected provider settings.

## 7. Day-to-day operational notes

- **Publishing content:** change it in Admin → Save. The storefront picks it up on the next load (customers may need a refresh).
- **Images:** uploaded through the Admin screens; stored under `dentinno/assets/images/products/`.
- **Database changes (migrations):** when the system adds new fields/tables, run `php migrate.php` once in the `dentinno` folder. `php migrate.php --status` shows what's applied.
- **Storefront updates:** the public site is a "built" bundle. After a code or settings-default change, it's rebuilt (`npm run build`) and served from the `dist/` folder; hard-refresh (Ctrl+F5) to see changes.

---

# Part 2 — Technical appendix (for developers)

## A. Architecture

- **Admin:** PHP, `dentinno/`. Pages in `dentinno/pages/*.php`, shared helpers in `dentinno/includes/` (`config.php` = `db()` PDO singleton + constants; `auth.php` = sessions/roles + dashboard stats).
- **API:** `dentinno/api/v1/*.php`. Shared bootstrap `_bootstrap.php` (`jsonOut`, `jsonErr`, `jcol`, `requireCustomer`), response mapping `_map.php` (`mapProduct`, `mapOffer`, …).
- **Storefront:** React (Vite), `smart-dental-innovation/`. Routing via `src/App.jsx` + `UIContext` (`view.name`). State in `src/context/*`. API client `src/lib/api.js`. Data hooks `src/hooks/useApiData.js`. Static fallbacks `src/data/*.js`.
- **Config:** `dentinno/includes/config.php` — `APP_URL`, `TIMEZONE='Asia/Kolkata'`, Razorpay keys, OTP/SMS, SMTP, Anthropic (AI image search). Storefront API base via `VITE_API_URL` (`.env` / `.env.production`).

## B. Database tables (one-line purpose)

| Table | Purpose |
|---|---|
| `admin_users` | Admin/staff accounts + roles |
| `categories` | Product categories |
| `products` | Product catalog (price, discount_price, stock, images JSON, specs, etc.) |
| `product_faqs` | Per-product FAQs |
| `product_reviews` | Customer reviews (approval + verified flags, rating 1–5) |
| `product_questions` | Customer Q&A (answer/approve) |
| `customers` | Customer CRM (totals, type, address) |
| `orders` | Order master (subtotal, discount, shipping_charge, tax, total, status, payment_status) |
| `order_items` | Order line items (price, total, line_type, offer_id) |
| `payments` | Payment records (method, transaction_id, status) |
| `coupons` | Discount codes (type, value, min_order, max_discount, uses_limit/count, expires_at) |
| `wishlists` | Customer saved products |
| `notifications` | Admin system alerts |
| `shipping_methods` / `shipping_zones` / `shipping_rules` / `product_shipping` | Admin shipping config (see audit H2 — not wired into checkout cost) |
| `delivery_pincodes` | Pincode → deliverable/ETA/COD |
| `events` / `event_registrations` | Events + sign-ups |
| `courses` / `course_modules` / `course_lessons` / `course_enrollments` | Online courses |
| `combos` | Bundle deals (mrp, price, stock, items JSON) |
| `offers` / `offer_items` | Offer Zone deals + free-gift rows (relational) |
| `testimonials` | Homepage testimonials |
| `contact_messages` | Contact-form inbox |
| `otp_codes` | OTP storage + rate limiting |
| `site_settings` | Key→JSON store for all storefront content/config |
| `schema_migrations` | Applied-migration history |

## C. API endpoints (`src/lib/api.js` → `dentinno/api/v1/*.php`)

| Storefront method | Endpoint | Notes |
|---|---|---|
| `products()` / `product(slug)` | `products.php` | Catalog + single product |
| `categories()` | `categories.php` | |
| `combos()` | `combos.php` | |
| `offers()` | `offers.php` | Excludes expired; relational gifts |
| `events()` | `events.php` | Published only |
| `testimonials()` | `testimonials.php` | |
| `faqs(slug)` / `questions(slug)` / `reviews(slug)` | `faqs.php` / `questions.php` / `reviews.php` | Reviews return `{avg,count,distribution}` |
| `submitQuestion()` / `submitReview()` | `questions.php` / `reviews.php` (POST) | Saved unapproved |
| `home()` | `home.php` | Home sections + categories + testimonials |
| `settings()` | `settings.php` | All `site_settings` (except private keys) |
| `checkDelivery(pincode)` | `delivery.php` | ETA/COD only (no cost) |
| `contact()` | `contact.php` | Contact-form submit |
| `imageSearch()` | `../image_search.php` | Claude Vision |
| `requestOtp()` / `verifyOtp()` | `otp.php` | |
| `login()` / `me()` / `updateProfile()` | `auth.php` | Customer auth (Bearer token) |
| `placeOrder()` / `myOrders()` | `orders.php` | Server re-prices authoritatively |
| `createRazorpayOrder()` / `verifyRazorpayPayment()` | `payment_razorpay.php` | Amount derived from DB `orders.total` |
| `validateCoupon(code, subtotal)` | `coupon.php` | Validation only (not persisted at order — see audit C2) |
| `getWishlist()` / `syncWishlist()` | `wishlist.php` | |

## D. `site_settings` keys → storefront usage (selection)

`company`, `stats`, `socials`, `payments`, `navMenu`, `policies`, `heroSlides`, `banners`, `trustBadges`, `homeSections`, `premiumCategories`, `productContent`, `paymentOptions`, `productBenefits`, `productDefaults`, `tierOffers`, `bulkRule`, `freeGifts`, `coupons`, `pricePresets`/`priceBounds`, `gvpThreshold`, `combosPage`, `offerZoneHero`, `contactConfig`/`contactSections`, `aboutConfig`/`aboutSections`, `branding`, `otpConfig`*, `whatsappConfig`. (*`otpConfig` is server-private and never sent to the storefront.) Loaded by `SettingsContext` (cached in `localStorage: sdi:settings`); static fallback in `src/data/site.js`.

## E. Where the money math lives

- **Cart display:** `src/components/modals/CartDrawer.jsx` (mrpTotal, productDiscount, bulkSavings, couponDiscount, deliveryCharges, finalTotal, totalSaved).
- **Cart state/subtotal:** `src/context/CartContext.jsx`.
- **Product tier pricing:** `src/components/pages/ProductDetailPage.jsx`.
- **Checkout payload & display:** `src/components/modals/CheckoutModal.jsx`.
- **Server order pricing (authoritative):** `dentinno/api/v1/orders.php` — re-resolves every line price, forces gifts to ₹0, decrements product stock, computes subtotal/total.
- **Coupons:** `dentinno/api/v1/coupon.php`.
- **Razorpay amount:** `dentinno/api/v1/payment_razorpay.php` (from DB `orders.total`).
- **Admin authoritative math:** `pages/products.php` (discount %), `pages/combos.php` (MRP = sum of parts), `pages/offers.php` (totalMrp/youSave), `pages/reports.php` & `pages/payments.php` (aggregations).

> Source of truth: the **server** re-prices orders; client prices are not trusted for line items. **However**, order-level discount/shipping/coupon are **not** yet server-authoritative — see [CALCULATION_ISSUES_AUDIT.md](CALCULATION_ISSUES_AUDIT.md) C1/C2.

## F. Migrations

`dentinno/migrate.php` applies `dentinno/database_*.sql` in filename order, once each (tracked in `schema_migrations`). Idempotent (`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, `INSERT IGNORE`). Commands: `php migrate.php`, `--status`, `--force <file>`, `--baseline`.

---

*Companion document: [CALCULATION_ISSUES_AUDIT.md](CALCULATION_ISSUES_AUDIT.md) — verified calculation/logic issues, ranked Critical → Low, with fixes.*
