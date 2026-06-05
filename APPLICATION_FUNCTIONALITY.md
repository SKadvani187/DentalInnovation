# DentInno / Smart Dental Innovation — Application Functionality Guide

A single reference that explains **what each side of the application does** (Admin Panel & Customer Storefront), **how a feature works end‑to‑end**, and — most importantly — **which values you change in the Admin Panel and where they show up on the Customer side.**

---

## 1. The Two Applications

| Part | Folder | Tech | Who uses it | Purpose |
|------|--------|------|-------------|---------|
| **Admin Panel** | `dentinno/` | PHP 8 + MySQL (PDO), session login | Internal staff / admin | Manage catalog, orders, customers, content, settings |
| **Customer Storefront** | `smart-dental-innovation/` | React 19 + Vite + Tailwind | End customers (dentists/clinics) | Browse, search, cart, checkout, account |
| **The Bridge (REST API)** | `dentinno/api/v1/` | PHP, JSON only | Machine‑to‑machine | Reads the same MySQL DB the admin writes to, returns JSON the React app consumes |

### How they connect (the golden rule)

```
 ┌────────────────┐   writes    ┌──────────────────┐   reads    ┌─────────────────────┐   fetch(JSON)   ┌──────────────────┐
 │  ADMIN PANEL   │ ──────────► │  MySQL Database  │ ◄───────── │  REST API /api/v1/* │ ◄────────────── │ REACT STOREFRONT │
 │  (dentinno/)   │             │  (dentinno_crm)  │            │  (PHP, read-mostly) │                 │ (smart-dental..) │
 └────────────────┘             └──────────────────┘            └─────────────────────┘                 └──────────────────┘
```

> **The Admin Panel and the Storefront never talk directly.** They share **one MySQL database**.
> The admin **writes** to the DB; the API **reads** the DB and hands JSON to React.
> **Therefore: any value you edit in the admin reflects on the storefront — as long as the API is running and the storefront is fetching live data (see §7 on the static‑fallback caveat).**

---

## 2. How a value travels: Admin → Customer (worked example)

**Scenario: Admin changes a product price.**

1. Admin opens **Products → Edit** in `dentinno/pages/products.php`, changes `discount_price` from ₹5,000 → ₹4,500, saves.
2. The PHP page runs an `UPDATE products SET discount_price=4500 WHERE id=…` on table **`products`**.
3. Customer opens a product page. React calls `api.product("p-001")` → `GET /api/v1/products.php?slug=p-001`.
4. `products.php` (API) selects the row and runs it through `mapProduct()` in `_map.php`:
   - DB `price` → storefront `mrp` (the struck‑through price)
   - DB `discount_price` → storefront `price` (the live selling price)
   - DB `discount_percent` → storefront `discount`
   - DB `stock > 0` → storefront `inStock: true`
5. React renders ₹4,500 as the new selling price. **No code change, no redeploy — the edit reflects immediately on next page load.**

This same write → DB → API map → React pattern applies to **every** module below.

---

## 3. Admin Panel — Modules & What They Control

Admin authenticates via **session login** (`login.php` → `includes/auth.php`): email + `password_verify()` against the `admin_users` table. Roles: `super_admin`, `admin`, `staff`. Default dev login: `admin@dentinno.com` / `password`.

### 3.1 Dashboard (`index.php`)
Read‑only overview: revenue, order counts, pending orders, low‑stock items, top products, recent orders, event registrations, pending reviews. **No edits here** — purely reporting.

### 3.2 Products (`pages/products.php`) — table `products` (+ `product_faqs`, `product_reviews`)
The single most impactful module for the storefront.

| Admin field | DB column | Reflects on storefront as |
|-------------|-----------|---------------------------|
| Name | `name` | Product title everywhere |
| Category | `category_id` | Which category page / filter it appears under |
| MRP | `price` | Struck‑through `mrp` |
| Selling price | `discount_price` | Live `price` |
| Discount % | `discount_percent` | `discount` badge; sort‑by‑discount; GVP zone |
| Stock | `stock` | `inStock` (>0) / "Out of stock"; `stock` count |
| **Active** toggle | `is_active` | **Visible at all? `is_active=0` hides it from the API entirely** |
| **Featured** | `is_featured` | Appears in Home "Bestsellers / Featured" section |
| **New** | `is_new` | Appears in Home "New Arrivals" section |
| Images | `images` (JSON) | Gallery; first image is the card thumbnail |
| Hover image | `hover_image` | Card hover image |
| Description | `description` | Product detail body |
| Specs / rating / warranty | `specifications` (JSON) | `rating`, `reviews`, `warranty` on product page |
| Variants | `variants` (JSON) | Variant selector |
| FAQs | `product_faqs` | Q&A / FAQ accordions |

> **Key visibility switch:** the API only ever returns products where `is_active=1` (see `products.php` line 22: `WHERE p.is_active=1`). Flip a product inactive in admin and it disappears from the storefront on next fetch.

### 3.3 Categories (`pages/categories.php`) — table `categories`
Fields: `name`, `description`, `slug` (auto), `is_active`, `sort_order`, `image`.
Reflects as: category navigation, filter chips, and category landing pages. API returns only `is_active=1`, ordered by `sort_order` (`home.php` line 40 / `categories.php`). `slug` is the join key between a product's category and the storefront URL.

### 3.4 Combos (`pages/combos.php`) — table `combos`
Bundle deals. Fields: `name`, `description`, `mrp`, `price` (auto `discount_percent`), `image`, `images`, `in_stock`, `is_active`, `sort_order`.
Reflects on the **Combos page** and in cart. Mapped by `mapCombo()`. Only `is_active` combos are returned.

### 3.5 Coupons (`pages/coupons.php`) — table `coupons`
Fields: `code`, `type` (percent/fixed), `value`, `min_order`, `max_discount`, `uses_limit`, `uses_count`, `expires_at`, `is_active`.
Reflects at **Checkout**: customer enters a code → React calls `api.validateCoupon(code, subtotal)` → `coupon.php`. The API enforces, in order: active flag → expiry → usage limit → minimum order → then computes discount (percent capped by `max_discount`, or fixed). **Edit any of these in admin and checkout behaviour changes immediately.**

### 3.6 Offers / Offer Zone (`pages/offers.php`) — table `offers`
Promotional cards. Fields: `title`, `subtitle`, `special_price`, `total_mrp`, `you_save`, `valid_till`, `theme`/`accent`/`gradient`/`cta` (styling), `main_product` (JSON), `free_items` (JSON), `is_active`.
Reflects on the **Offer Zone page** (countdown uses `valid_till`, colours come from `theme`/`accent`/`gradient`). Mapped by `mapOffer()`. Only active offers returned.

### 3.7 Events / Courses (`pages/events.php`, `pages/courses.php`) — tables `events`, `courses`
Fields (events): `title`, `event_type`, `start_date`/`end_date`, `description`, `venue`/`city`/`state`, `is_online`/`online_link`, `registration_fee`, `is_free`, `organizer`, `status` (draft/published/cancelled/completed).
Reflects on the **Events page**. Mapped by `mapEvent()`. **Only `published` events appear** — set `status=draft` to hide. `registration_fee` → price, `is_free` → "Free" badge.

### 3.8 Testimonials (`pages/testimonials.php`) — table `testimonials`
Fields: `name`, `rating`, `text`, `avatar`, `product_name`, `product_image`, `is_active`, `sort_order`.
Reflects in the **Home testimonials carousel** and **About page**. Only `is_active=1`, ordered by `sort_order`.

### 3.9 Orders (`pages/orders.php`) — tables `orders`, `order_items`
Admin **manages** orders the customer **created**. Editable: `status` (pending→processing→confirmed→shipped→delivered→cancelled, auto‑stamps `shipped_at`/`delivered_at`), `tracking_number`, `courier_name`, `payment_status`, `payment_method`.
Reflects on the customer's **My Orders page**: when admin marks an order "shipped" + adds tracking, the customer sees the new status and tracking on next load (`api.myOrders()`).

### 3.10 Customers (`pages/customers.php`) — table `customers`
View/edit customer profiles, see order history, total spent, wishlist. `total_orders`/`total_spent` are auto‑bumped when orders are placed (see `orders.php` API lines 114–117). Mostly a support/CRM view.

### 3.11 Messages (`pages/messages.php`) — table `contact_messages`
**Inbound** from the storefront contact form. Admin can mark read/unread, delete. (Data flows customer → admin here, the reverse of most modules.)

### 3.12 Reviews (`pages/reviews.php`) — table `product_reviews`
Moderate customer reviews. **`is_approved`** is the gate — only approved reviews should surface on product pages. Also `is_verified` (verified‑purchase badge). Bulk approve/delete supported.

### 3.13 Settings (`pages/settings.php`) — tables `admin_users`, `site_settings`
Two halves:
- **Admin account**: name, email, password change, role (read‑only).
- **Storefront CMS** (saved as JSON into `site_settings`, key/value): company info, home sections, hero slides, banners, contact config, about config, payments, policies, etc. **This is what powers `GET /api/v1/settings.php`** — see §5.

### 3.14 Shipping (`pages/shipping.php`, `shipping_calculator.php`) — tables `shipping_methods`, `shipping_zones`, `shipping_rules`
Configure shipping methods/zones/rules used to compute delivery cost at checkout. Calculator page is a test tool (no writes).

### 3.15 Payments / Reports / Wishlists (`pages/payments.php`, `reports.php`, `wishlists.php`)
**Read‑only analytics** aggregated from `orders`, `customers`, `products`, `wishlists`. No storefront‑facing edits.

---

## 4. Customer Storefront — Pages & Where Their Data Comes From

Every list view uses the `useApiData.js` hooks (or `SettingsContext`) which **fetch from the API and fall back to bundled static data** if the API is unreachable.

| Storefront page / feature | Component | Data source (API method → endpoint) | Login needed? |
|---------------------------|-----------|-------------------------------------|---------------|
| **Home** | home components | `api.home()` → `home.php` (sections + categories + testimonials) | No |
| **Category / product listing** | `CategoryPage` | `api.products()`, `api.categories()`, `api.combos()` | No |
| **Product detail** | `ProductDetailPage` / `ProductDetailModal` | `api.product(slug)` → `products.php?slug=` | No (wishlist syncs if logged in) |
| **Combos** | `CombosPage` | `api.combos()` | No |
| **Offer Zone** | `OfferZonePage` | `api.offers()` | No |
| **Events / Courses** | `EventsPage` | `api.events()` | No |
| **Search** | `SearchModal` | `api.products()` (filtered client‑side) | No |
| **Cart** | `CartDrawer` | `CartContext` (localStorage `sdi:cart`) | No |
| **Checkout** | `CheckoutModal` | `api.validateCoupon()`, `api.placeOrder()` | **Yes** |
| **Wishlist** | `WishlistDrawer` / `WishlistPage` | `WishlistContext` (localStorage); `api.syncWishlist()` if logged in | Optional |
| **Login / OTP** | `AuthModal` | `api.requestOtp()`, `api.verifyOtp()`, `api.login()` | — |
| **Account** | `AccountPage` | `AuthContext`; `api.updateProfile()` | **Yes** |
| **My Orders** | `OrdersPage` | `api.myOrders()` → `orders.php` | **Yes** |
| **Addresses** | `AddressPage` | `AuthContext` (localStorage) | **Yes** |
| **Contact** | `ContactPage` | `api.contact()` → `messages` table; `contactConfig` from settings | No |
| **About** | `AboutPage` | `aboutConfig` from `api.settings()`; `api.testimonials()` | No |
| **Policies** | `PolicyPage` | `policies` from `api.settings()` | No |

---

## 5. Site Settings — the CMS that controls the storefront's "skin"

`GET /api/v1/settings.php` returns **every row of `site_settings`** as one JSON object. React's `SettingsContext` fetches it once on load and **merges API values over the static defaults** (`site.js`). So editing settings in admin changes content site‑wide.

Settings‑driven content includes:

| Setting key | Controls (storefront) |
|-------------|-----------------------|
| `company` | Header/footer name, phone, email, address |
| `heroSlides`, `banners` | Home hero carousel & promo banners |
| `homeSections` | **Which product sections render on Home and in what order** (see §6) |
| `contactConfig` | Contact page departments, FAQs, business hours, open/closed status |
| `aboutConfig` | About page hero, story, stats, milestones, values |
| `policies` | Return / Terms / Privacy documents |
| `payments` | Payment methods shown at checkout |
| `coupons`, `bulkRule`, `tierOffers` | Pricing / bulk‑discount rules |
| `productContent`, `sampleReviews`, `productDefaults` | Product page FAQs, highlights, delivery defaults |
| `gvpThreshold` | Discount cut‑off for the "Great Value Products" zone |
| `liveCounts` | Real‑time active‑product count for trust badges (computed live, not stored) |

> **Reflection rule:** edit a `site_settings` row in admin → it's in the next `settings.php` response → `SettingsContext` overrides the static default → the storefront shows your new content.

---

## 6. Special case: how the Home page sections are built

`home.php` reads the **`homeSections`** setting and, for each block of type `productSection`, resolves products by its `source`:

- `source: "featured"` → products where `is_featured=1`
- `source: "new"` → products where `is_new=1`
- otherwise → treated as a **category slug** → products in that category

So to change what shows on the Home page you have **two admin levers**:
1. **Per product** — toggle `is_featured` / `is_new` (Products module) to add/remove a product from those sections.
2. **Section config** — edit `homeSections` (Settings) to add/reorder/rename sections or point a section at a different category.

---

## 7. ⚠️ Important caveat: live data vs. static fallback

The storefront is designed to **keep working even if the API is down**. Both `useApiData.js` and `SettingsContext` ship with **bundled static copies** of the data (`src/data/*.js`) and use them as a fallback:

- If `api.products()` (etc.) **succeeds and returns rows** → storefront shows **live DB data** (your admin edits reflect). `source: "api"`.
- If the API call **fails, or returns an empty list** → storefront silently shows the **old static data**, and your admin changes will **not** appear. `source: "static"`.

**So "will my admin change reflect on the customer side?" = YES, provided:**
1. The PHP API (`dentinno/api/v1/`) is running and reachable at `VITE_API_URL` (default `http://localhost:8088/api/v1`).
2. The database is **seeded** (the table actually has rows — empty tables make React fall back to static).
3. The record is **visible**: `is_active=1` (products/categories/combos/offers/testimonials), or `status=published` (events). Inactive/draft rows are filtered out by the API.
4. The customer reloads / re‑fetches (data is fetched on mount, not pushed in real time).

---

## 8. Customer‑written data (storefront → admin direction)

Not everything flows admin → customer. These customer actions **write back** to the DB and show up in the admin:

| Customer action | API endpoint | DB effect | Where admin sees it |
|-----------------|--------------|-----------|---------------------|
| Login via OTP | `otp.php`, `auth.php?action=login` | Creates/updates `customers` row, issues `api_token` | Customers module |
| Place order | `POST orders.php` | Inserts `orders` + `order_items`; bumps `products.total_sales`, `customers.total_orders/total_spent` | Orders, Dashboard, Reports |
| Update profile | `auth.php?action=profile` | Updates `customers` | Customers |
| Sync wishlist | `POST wishlist.php` | Updates wishlist (auth) | Wishlists analytics |
| Submit contact form | `POST contact.php` | Inserts `contact_messages` | Messages module |
| Apply coupon (on order) | (via order) | Increments `coupons.uses_count` | Coupons usage bar |

**Order placement flow (detail):** `CheckoutModal` (login required) → `api.placeOrder({items, address, paymentMethod, subtotal, discount, shipping})` → `orders.php` opens a DB transaction → generates `order_number` (`SDI-YYYYMMDD-XXXXXX`) → inserts order + items → updates product sales & customer totals → commits → returns the order. The admin then manages its lifecycle in the Orders module (§3.9), and the customer tracks it on **My Orders**.

---

## 9. Quick "change this → see it there" cheat sheet

| If I change this in Admin… | …the customer sees it here |
|----------------------------|----------------------------|
| Product price / MRP / discount | Product card & detail page, sort & filter results |
| Product `is_active` off | Product disappears from the whole storefront |
| Product `is_featured` / `is_new` | Home "Featured" / "New Arrivals" sections |
| Product stock = 0 | "Out of stock" on the product |
| Category name / `is_active` | Navigation, filters, category pages |
| Combo price / fields | Combos page & cart |
| Coupon value / min order / expiry / active | Checkout coupon validation result |
| Offer special price / free items / valid till / colours | Offer Zone cards & countdown |
| Event `status=published` + fee | Events page listing & price |
| Testimonial text / rating / active | Home & About testimonials |
| Order status + tracking | Customer's My Orders page |
| Review `is_approved` | Review visible on product page |
| `site_settings` (company, hero, banners, policies, contact, about) | Header, footer, Home, Contact, About, Policies pages |
| `homeSections` config | Which sections render on Home, and their order |

---

## 10. Reference: API endpoints (the bridge)

Base URL: `VITE_API_URL` (default `http://localhost:8088/api/v1`). All return JSON; reads are public, writes need a `Bearer` token.

| Method & path | Purpose |
|---------------|---------|
| `GET /products.php` | List products (filters: `category`, `search`, `sort`, `min`, `max`, `page`, `limit`) |
| `GET /products.php?slug=` | Single product |
| `GET /categories.php` | Active categories |
| `GET /combos.php` | Active combos |
| `GET /events.php` | Published events |
| `GET /offers.php` | Active offers |
| `GET /testimonials.php` | Active testimonials |
| `GET /home.php` | Combined home feed (sections + categories + testimonials) |
| `GET /settings.php` | All site settings (CMS) |
| `POST /contact.php` | Submit contact form → `contact_messages` |
| `POST /otp.php?action=request` / `verify` | OTP send / verify |
| `POST /auth.php?action=login` | Customer login/register → token |
| `GET /auth.php?action=me` / `POST ?action=profile` | Current customer / update profile |
| `POST /orders.php` / `GET /orders.php` | Place order / list my orders (auth) |
| `GET /coupon.php?code=&subtotal=` | Validate a coupon |
| `GET /wishlist.php` / `POST /wishlist.php` | Get / sync wishlist (auth) |

---

*Generated as a functional reference for the DentInno admin panel and Smart Dental Innovation storefront. Field names map directly to the source files in `dentinno/` (admin + API) and `smart-dental-innovation/src/` (storefront).*