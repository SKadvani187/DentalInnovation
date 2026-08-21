# Smart Dental Import — Field Mapping Reference

How `dentinno/import_smartdental.php` maps each field of `dentinno/smartdental_import.json`
(555 products) into the database. Use this to understand what lands where, and why some
storefront sections can look empty or over-filled.

- **CLI only:** `php import_smartdental.php` (`--limit=N`, `--purge`, `--file=path`)
- **Idempotent upsert keyed by SKU** (revives soft-deleted matches).
- Writes to **4 tables:** `products`, `combos`, `categories`, `product_faqs`.
- **Routing:** the first non-price category tag is the "primary category". If it equals
  `combo` → row goes to `combos`; otherwise → `products`.

---

## 1. JSON → `products`

| DB column | JSON source | Transformation |
|---|---|---|
| `name` | `name` | trimmed; row skipped if empty |
| `slug` | *(from `name`)* | `generateSlug(name)`, unique per SKU. **`url` is NOT used.** |
| `sku` | `sku` | empty → `SDX-<md5>`; duplicate SKUs get `-2` suffix |
| `category_id` | `categories` | first non-₹ tag → looked up/created in `categories` (aliases applied) |
| `price` | **`mrp`** | digits only; falls back to `selling_price` |
| `discount_price` | **`selling_price`** | set only if `< mrp`, else `null` |
| `discount_percent` | *(computed)* | `(price − discount_price)/price × 100`. **JSON `discount` ignored.** |
| `stock` | *(hardcoded)* | `100` |
| `min_stock_alert` | *(hardcoded)* | `5` |
| `description` | **`short_description`** | HTML→plain text, full length |
| `short_description` | `short_description` | same text, truncated to 500 chars |
| `full_description` | **`description`** | `cleanHtml`; fallback from `other_info` (label routed `DESC`) |
| `features` | `highlights` (+ `other_info` `FEAT` sections) | classified into bullets `[{title,text}]` |
| `key_features` | — | always `null` (deprecated) |
| `key_specifications` | `key_specifications` (+ `other_info` `SPEC`) | spec pairs `[{key,value}]` (max 40) |
| `bulk_offers` | `bulk_offers` | `"Buy 2+ : ₹X each"` → `[{minQty,rate,label}]` |
| `directions_for_use` | `directions` | `cleanHtml`; fallback `other_info` `DIR` |
| `packing_info` | `packaging` | `cleanHtml`; fallback `other_info` `PACK` |
| `warranty_info` | `warranty` | plain text; blanked if `No/NA/None/-`; fallback `other_info` `WARR` |
| `additional_information` | — | always `null` |
| `images` | `images` | newline list → http(s) URLs only, deduped, JSON array |
| `weight_kg` | `dimensions` | only `Weight: N` (grams) → `N/1000` kg |
| `is_active` | *(hardcoded)* | `1` |
| `is_new` | `categories` | `1` if contains "new product"; **INSERT-only** |
| `is_deleted` | *(hardcoded)* | `0` |

> **Counter-intuitive:** JSON `short_description` → DB `description`; JSON `description` → DB `full_description`.

## 2. JSON → `combos` (primary category = `combo`)

Card fields only (`slug, name, description=short_description, mrp=mrp, price=selling_price,
discount_percent, image, images, items='[]', in_stock=1, stock=100`). **Combos get no FAQs,
specs, highlights, directions, or packaging.**

## 3. JSON → `product_faqs`

`faqs` `"Q:…\nA:…"` blocks → rows `(product_id, question, answer, sort_order)`, replaced per product.

## 4. JSON → `categories`

`resolveCat` takes the first non-₹ tag, applies aliases, creates the category if missing. Price
buckets (`₹999 - ₹1998`, `Below ₹499`) are skipped.

## 5. `other_info` grab-bag routing

`other_info` is split into `Label: body` sections; each is routed by `routeLabel()` and only
used to fill a still-empty section:

| Bucket | Fills |
|---|---|
| `DESC` | `full_description` (if empty) |
| `DIR` | `directions_for_use` (if empty) |
| `PACK` | `packing_info` (if empty) |
| `WARR` | `warranty_info` (if empty) |
| `SPEC` | merged into `key_specifications` |
| `FEAT` (default) | merged into `features` (highlights) |
| `JUNK` | dropped |

## 6. JSON fields NOT imported

`brand`, `url`, `discount`, `avg_rating`, `total_reviews`, `available`, `variants`, and the
`dimensions` Length/Width/Height/Pkg-weight (only Weight is used).

---

## 7. ✅ Fixed defect (2026-06-21) — label routing pollutes Highlights / empties Description & Packaging

> **Status: FIXED and re-imported.** `routeLabel()` whitelists were expanded (incl. typos +
> numbered `Step N` → directions) and the `other_info` routing was refactored to collect *all*
> sections per bucket (not just the first) and to preserve `Key: value` keys. After re-running
> `php import_smartdental.php`, `Mobiflash Lite` now has a filled Description (655 chars), filled
> Packaging, and 13 Highlights bullets (was 25).
>
> **Spec sub-labels tightened (2026-06-21):** `classifyMeta` now treats a `Label: value` line as a
> spec when the label is a known spec key OR contains a measurement/unit keyword (`torque`, `speed`,
> `range`, `capacity`, `distance`, …) — unless it opens with a feature adjective (`Adjustable`,
> `Wide`, `Compact`, …). Result: short spec values (e.g. `Max torque`, `Battery Capacity`,
> `Working Distance`) now land in **Key Specifications** (2,463 specs across 449 products) instead
> of the Highlights box. Verified: **0** short (≤80-char) spec values remain in Highlights; only
> long descriptive `Label: <sentence>` lines stay as bullets (by design — the value-length guard
> keeps prose out of the spec table).

**Symptom (original):** Storefront *Description* and *Packaging* sections appear empty, while the *Product
Highlights* box shows far more entries than the JSON `highlights` field contains.

**Root cause:** `routeLabel()`'s label whitelists do not cover the real labels and typos used in
the source `other_info`. Any unrecognized label falls through to `FEAT`, so its content is dumped
into the Highlights box instead of the correct section. Because the `FEAT` path keeps only the
section **body** (the label is discarded), spec keys are also lost.

**Verified example — `Mobiflash Lite`** (`sku 465`):

- `other_info` contains `Long Description:`, `Key Features:`, `Directions for Use:`, `Package Info:`.
- `Long Description` → not in `DESC` list → routed to `FEAT` ⇒ `full_description` ends up **empty**.
- `Package Info` → not in `PACK` list (only `packaging info` is) → `FEAT` ⇒ `packing_info` **empty**.
- Result: `features` (Highlights) box = **25 bullets** (the highlights field's 3 usable lines + the
  whole long-description paragraph + 10 key-features + 11 package-content items), vs the 6 lines
  the JSON `highlights` field actually has.

**Unrecognized labels found across the catalogue (all currently routed to `FEAT`):**

- Should be `DESC`: `Long Description`, `Key Description`, and typos `Descripton`, `Desscription`,
  `Desctiption`, `Descripton`, `Desription`.
- Should be `PACK`: `Package Info`, `Packing Info`, and typos `Packagign Info`, `Packaginge info`,
  `Packaginf info`, `Packaginng info`.
- Should be `DIR`: `Direction for Use` (12 products), `Diraction Of Use`, and the `Step 1…7` sequences.
- Should be `SPEC`: many bare `Label: value` lines (`Type`, `Material`, `Max torque`, `Speed range`,
  `Length`, `Weight`, `Battery Capacity`, …) that are split into their own sections and lose their key.

**Scope:** 206 / 555 products have an empty JSON `description` field and depend on `other_info` for
their long description, so mislabeled headers affect a large share of the catalogue.

**Fix direction:** extend the `routeLabel()` maps (and the `splitHtmlSections`/`FEAT` handling so a
spec label keeps its key), then re-run the importer. Track in this doc when applied.
