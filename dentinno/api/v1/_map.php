<?php
// DB row -> React storefront shape mappers.
// React expects string `id` (the slug), numeric mrp/price/discount, arrays for images/variants.

function mapProduct(array $r): array {
    $specs = jcol($r['specifications'] ?? null, []);
    $mrp   = (float)$r['price'];                       // DB price = MRP (see seed)
    $sell  = $r['discount_price'] !== null ? (float)$r['discount_price'] : $mrp;
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
        'rating'      => $specs['rating']  ?? null,
        'reviews'     => $specs['reviews'] ?? ($r['total_sales'] ?? 0),
        'category'    => $r['category_slug'] ?? null,
        'warranty'    => $specs['warranty'] ?? null,
        'inStock'     => (int)$r['stock'] > 0,
        'stock'       => (int)$r['stock'],
        'description' => $r['description'],
        'variants'    => jcol($r['variants'] ?? null, []),
        'isFeatured'  => (bool)($r['is_featured'] ?? 0),
    ];
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
    return [
        'id'       => $r['slug'],
        'dbId'     => (int)$r['id'],
        'name'     => $r['name'],
        'image'    => $r['image'],
        'images'   => jcol($r['images'] ?? null, []),
        'mrp'      => (float)$r['mrp'],
        'price'    => (float)$r['price'],
        'discount' => (float)$r['discount_percent'],
        'category' => 'combo',
        'stock'    => isset($r['stock']) ? (int)$r['stock'] : null,
        'inStock'  => isset($r['stock']) ? ((int)$r['stock'] > 0) : (bool)$r['in_stock'],
        'description' => $r['description'],
        'items'    => jcol($r['items'] ?? null, []),
        'variants' => [],
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

function mapOffer(array $r): array {
    $main      = jcol($r['main_product'] ?? null, null);
    $freeItems = jcol($r['free_items'] ?? null, []);
    $special   = (float)$r['special_price'];
    // Authoritative: recompute totalMrp + youSave from parts so stored values can never drift.
    $totalMrp = (float)($main['mrp'] ?? 0);
    foreach ($freeItems as $fi) $totalMrp += (float)($fi['mrp'] ?? 0);
    if ($totalMrp <= 0) $totalMrp = (float)$r['total_mrp'];   // fallback for legacy rows
    $youSave = max(0, $totalMrp - $special);
    return [
        'id'           => $r['slug'],
        'dbId'         => (int)$r['id'],
        'title'        => $r['title'],
        'subtitle'     => $r['subtitle'],
        'theme'        => $r['theme'],
        'accent'       => $r['accent'],
        'gradient'     => $r['gradient'],
        'cta'          => $r['cta'],
        'mainProduct'  => $main,
        'freeItems'    => $freeItems,
        'specialPrice' => $special,
        'totalMrp'     => $totalMrp,
        'youSave'      => $youSave,
        'saveExtra'    => $r['save_extra'],
        'validTill'    => $r['valid_till'],
        'isTopDeal'    => (bool)($r['is_top_deal'] ?? 0),
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
