<?php
// DB row -> React storefront shape mappers.
// React expects string `id` (the slug), numeric mrp/price/discount, arrays for images/variants.

function mapProduct(array $r): array {
    $specs = jcol($r['specifications'] ?? null, []);
    $mrp   = (float)$r['price'];                       // DB price = MRP (see seed)
    $sell  = $r['discount_price'] !== null ? (float)$r['discount_price'] : $mrp;
    // Per-product highlights live in `features` as [{title,text}].
    // Legacy rows are plain strings -> normalize to {title:'', text}.
    $highlights = [];
    foreach (jcol($r['features'] ?? null, []) as $f) {
        if (is_array($f)) {
            $highlights[] = ['title' => $f['title'] ?? '', 'text' => $f['text'] ?? ''];
        } elseif (is_string($f) && $f !== '') {
            $highlights[] = ['title' => '', 'text' => $f];
        }
    }
    return [
        'id'          => $r['slug'],
        'dbId'        => (int)$r['id'],
        'name'        => $r['name'],
        'image'       => ($imgs = jcol($r['images'] ?? null, []))[0] ?? null,
        'images'      => $imgs,
        'hoverImage'  => $r['hover_image'] ?: null,
        'mrp'         => $mrp,
        'price'       => $sell,
        'discount'    => (float)$r['discount_percent'],
        // Real approved-review data (from the product_reviews subquery in products.php). Falls back
        // to null/0 — NOT to total_sales — so a product with no reviews shows no rating at all.
        'rating'      => isset($r['review_count']) && (int)$r['review_count'] > 0 ? round((float)$r['review_avg'], 1) : ($specs['rating'] ?? null),
        'reviews'     => isset($r['review_count']) ? (int)$r['review_count'] : 0,
        'category'    => $r['category_slug'] ?? null,
        'warranty'    => $specs['warranty'] ?? null,
        'inStock'     => (int)$r['stock'] > 0,
        'stock'       => (int)$r['stock'],
        'description' => $r['description'],
        'shortDescription' => $r['short_description'] ?? null,   // brief blurb shown under the price
        // Per-product accordion content (product detail page). Empty -> storefront falls back to global.
        'fullDescription' => $r['full_description'] ?? null,
        'keySpecifications' => normalizeSpecs($r['key_specifications'] ?? null),
        // Key Specifications scraped from the live site are kept as RAW HTML (prose/lists/label:value)
        // in key_specifications_html and rendered via RichText, matching the source exactly.
        'keySpecificationsHtml' => (isset($r['key_specifications_html']) && trim((string)$r['key_specifications_html']) !== '')
            ? $r['key_specifications_html'] : null,
        'directions'  => $r['directions_for_use'] ?? null,
        'packingInfo' => $r['packing_info'] ?? null,
        'additionalInfo' => $r['additional_information'] ?? null,
        'warrantyInfo' => $r['warranty_info'] ?? null,
        'keyFeatures' => $r['key_features'] ?? null,
        'warrantyNo'  => $r['warranty_no'] ?? null,
        'directionOfUse' => $r['direction_of_use'] ?? null,
        'catalogueUrl' => $r['catalogue_url'] ?? null,
        'variants'    => jcol($r['variants'] ?? null, []),
        'bulkOffers'  => jcol($r['bulk_offers'] ?? null, []),   // per-product quantity tiers (override global)
        'highlights'  => $highlights,
        'isFeatured'  => (bool)($r['is_featured'] ?? 0),
        'isNew'       => (bool)($r['is_new'] ?? 0),
    ];
}

// Key specifications -> always [{key,value}]. Stored either as that array or as a {key:value} object.
function normalizeSpecs($v): array {
    $out = [];
    foreach (jcol($v, []) as $k => $row) {
        if (is_array($row) && isset($row['key'])) {
            $out[] = ['key' => $row['key'], 'value' => $row['value'] ?? ''];
        } elseif (!is_array($row)) {
            $out[] = ['key' => (string)$k, 'value' => (string)$row];
        }
    }
    return $out;
}

function mapCategory(array $r): array {
    return [
        'id'    => $r['slug'],
        'dbId'  => (int)$r['id'],
        'title' => $r['name'],
        'img'   => $r['image'] ?: null,
    ];
}

function mapCombo(array $r): array {
    $items = jcol($r['items'] ?? null, []);
    $price = (float)$r['price'];
    // Selling total = Σ each component's normal selling price (sell), falling back to mrp for
    // old combos saved before per-item sell existed. Drives the honest "save vs buying separately".
    $sellingTotal = 0;
    foreach ($items as $it) {
        $sell = isset($it['sell']) ? (float)$it['sell'] : (float)($it['mrp'] ?? 0);
        $qty  = max(1, (int)($it['qty'] ?? 1));
        $sellingTotal += $sell * $qty;
    }
    return [
        'id'       => $r['slug'],
        'dbId'     => (int)$r['id'],
        'name'     => $r['name'],
        'image'    => $r['image'],
        'images'   => jcol($r['images'] ?? null, []),
        'mrp'      => (float)$r['mrp'],
        'price'    => $price,
        'discount' => (float)$r['discount_percent'],
        'sellingTotal'      => $sellingTotal,
        'youSaveVsSeparate' => max(0, $sellingTotal - $price),
        'category' => 'combo',
        'stock'    => isset($r['stock']) ? (int)$r['stock'] : null,
        'inStock'  => isset($r['stock']) ? ((int)$r['stock'] > 0) : (bool)$r['in_stock'],
        'description' => $r['description'],
        'metaTitle'        => $r['meta_title'] ?? null,
        'metaDescription'  => $r['meta_description'] ?? null,
        'items'    => $items,
        'variants' => [],
        // Rich detail (combos now carry the same sections as products so they render a full
        // product page). Mirrors mapProduct's field names so ProductDetailPage works unchanged.
        'shortDescription'  => $r['short_description'] ?? null,
        'fullDescription'   => $r['full_description'] ?? null,
        'hoverImage'        => $r['hover_image'] ?? null,
        'highlights'        => array_map(
            fn($f) => is_array($f) ? ['title' => $f['title'] ?? '', 'text' => $f['text'] ?? ''] : ['title' => '', 'text' => (string)$f],
            jcol($r['features'] ?? null, [])
        ),
        'keySpecificationsHtml' => (isset($r['key_specifications_html']) && trim((string)$r['key_specifications_html']) !== '') ? $r['key_specifications_html'] : null,
        'directions'   => $r['directions_for_use'] ?? null,
        'packingInfo'  => $r['packing_info'] ?? null,
        'warrantyInfo' => $r['warranty_info'] ?? null,
        'keyFeatures'  => $r['key_features'] ?? null,
    ];
}

function mapEvent(array $r): array {
    $price = (float)$r['registration_fee'];
    $mrp   = isset($r['mrp']) && $r['mrp'] !== null ? (float)$r['mrp'] : $price;
    $img   = $r['banner_image'];
    return [
        'id'              => $r['slug'],
        'dbId'            => (int)$r['id'],
        'name'            => $r['title'],
        'type'            => $r['event_type'] ? ucfirst($r['event_type']) : 'Course',
        'description'     => $r['description'],
        'price'           => $price,
        'mrp'             => $mrp > 0 ? $mrp : $price,
        'image'           => $img,
        'images'          => $img ? [$img] : [],
        'videoThumb'      => $img,
        'videoUrl'        => $r['online_link'] ?: null,
        'rating'          => 0,
        'reviews'         => 0,
        'brand'           => $r['organizer'] ?: 'Smart Dental Innovations',
        'breadcrumb'      => ['Home', 'Events', $r['title']],
        'extraCategories' => [],
        'isFree'          => (bool)$r['is_free'],
    ];
}

// $giftRows: relational offer_items rows (each may carry a joined product_slug).
// When present they are the source of truth; otherwise we fall back to the legacy
// free_items JSON so un-migrated rows still render. Output shape stays
// {name, mrp, image, variant} plus optional {productId, qty} for cart building.
function mapOffer(array $r, array $giftRows = [], array $mainById = []): array {
    $main    = jcol($r['main_product'] ?? null, null) ?: [];
    $special = (float)$r['special_price'];

    // Source of truth for WHICH product this offer is + its live pricing: the relational
    // offers.product_id (resolved via $mainById = [id => ['slug','name','image','mrp','price']]).
    // The JSON main_product snapshot is legacy and drifts; use it only when the relational link
    // is missing (very old rows). Fixes the offers JSON-drift gap (productId + mrp/price + totalMrp).
    $rel = $mainById[(int)($r['product_id'] ?? 0)] ?? null;
    if ($rel) {
        $main['productId'] = $rel['slug'];
        $main['name']      = $rel['name'];
        $main['image']     = $rel['image'] ?: ($main['image'] ?? null);
        $main['mrp']       = $rel['mrp'];
        $main['price']     = $rel['price'];
    }

    if (!empty($giftRows)) {
        $freeItems = array_map(fn($g) => [
            'productId' => $g['product_slug'] ?? null,
            'name'      => $g['name'],
            'variant'   => $g['variant'],
            'image'     => $g['image'],
            'mrp'       => (float)$g['mrp'],
            'qty'       => max(1, (int)($g['qty'] ?? 1)),
        ], $giftRows);
    } else {
        // Legacy JSON fallback (productId may be absent on old snapshots).
        $freeItems = array_map(fn($fi) => [
            'productId' => $fi['productId'] ?? null,
            'name'      => $fi['name'] ?? '',
            'variant'   => $fi['variant'] ?? null,
            'image'     => $fi['image'] ?? null,
            'mrp'       => (float)($fi['mrp'] ?? 0),
            'qty'       => max(1, (int)($fi['qty'] ?? 1)),
        ], jcol($r['free_items'] ?? null, []));
    }

    // Authoritative: recompute totalMrp + youSave from parts so stored values can never drift.
    $totalMrp = (float)($main['mrp'] ?? 0);
    foreach ($freeItems as $fi) $totalMrp += $fi['mrp'] * $fi['qty'];
    if ($totalMrp <= 0) $totalMrp = (float)$r['total_mrp'];   // fallback for legacy rows
    $youSave = max(0, $totalMrp - $special);

    // valid_till is stored in the app timezone (Asia/Kolkata); emit ISO-8601 with the
    // explicit offset so the browser's `new Date()` is unambiguous.
    $validTill = !empty($r['valid_till']) ? date('c', strtotime($r['valid_till'])) : null;

    // Card styling: fall back to sensible defaults so an offer created without theme
    // colours still renders a proper card (background gradient + accent border + CTA).
    $accent   = trim((string)($r['accent'] ?? '')) ?: '#3684bf';
    $gradient = trim((string)($r['gradient'] ?? '')) ?: 'linear-gradient(135deg, #ffffff 0%, #f6f9fc 100%)';
    $cta      = trim((string)($r['cta'] ?? '')) ?: $accent;

    return [
        'id'           => $r['slug'],
        'dbId'         => (int)$r['id'],
        'title'        => $r['title'],
        'subtitle'     => $r['subtitle'],
        'theme'        => $r['theme'] ?: 'default',
        'accent'       => $accent,
        'gradient'     => $gradient,
        'cta'          => $cta,
        'mainProduct'  => $main,
        'freeItems'    => $freeItems,
        'specialPrice' => $special,
        'totalMrp'     => $totalMrp,
        'youSave'      => $youSave,
        'saveExtra'    => $r['save_extra'],
        'validTill'    => $validTill,
        'isTopDeal'    => (bool)($r['is_top_deal'] ?? 0),
    ];
}

function mapQuestion(array $r): array {
    return [
        'id'   => (int)$r['id'],
        'q'    => $r['question'],
        'a'    => $r['answer'],
        'name' => $r['asker_name'] ?: 'Customer',
        'date' => date('d M Y', strtotime($r['answered_at'] ?: $r['created_at'])),
        'up'   => (int)($r['helpful_up'] ?? 0),
        'down' => (int)($r['helpful_down'] ?? 0),
    ];
}

function mapFaq(array $r): array {
    return [
        'id' => (int)$r['id'],
        'q'  => $r['question'],
        'a'  => $r['answer'],
    ];
}

function mapReview(array $r): array {
    return [
        'id'         => (int)$r['id'],
        'name'       => $r['reviewer_name'],
        'stars'      => (int)$r['rating'],
        'title'      => $r['title'] ?: null,
        'text'       => $r['review'],
        'date'       => date('d M Y', strtotime($r['created_at'])),
        'verified'   => (bool)($r['is_verified'] ?? 0),
        'helpful'    => (int)($r['helpful_count'] ?? 0),
    ];
}

function mapTestimonial(array $r): array {
    return [
        'id'           => $r['slug'],
        'dbId'         => (int)$r['id'],
        'name'         => $r['name'],
        'avatar'       => $r['avatar'],
        'productImage' => $r['product_image'],
        'productName'  => $r['product_name'] ?? null,
        'rating'       => isset($r['rating']) ? (int)$r['rating'] : 5,
        'text'         => $r['text'],
    ];
}
