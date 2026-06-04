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
        'inStock'  => (bool)$r['in_stock'],
        'description' => $r['description'],
        'variants' => [],
    ];
}

function mapEvent(array $r): array {
    return [
        'id'          => $r['slug'],
        'dbId'        => (int)$r['id'],
        'name'        => $r['title'],
        'type'        => 'Course',
        'description' => $r['description'],
        'price'       => (float)$r['registration_fee'],
        'image'       => $r['banner_image'],
        'isFree'      => (bool)$r['is_free'],
    ];
}

function mapOffer(array $r): array {
    return [
        'id'           => $r['slug'],
        'dbId'         => (int)$r['id'],
        'title'        => $r['title'],
        'subtitle'     => $r['subtitle'],
        'theme'        => $r['theme'],
        'accent'       => $r['accent'],
        'gradient'     => $r['gradient'],
        'cta'          => $r['cta'],
        'mainProduct'  => jcol($r['main_product'] ?? null, null),
        'freeItems'    => jcol($r['free_items'] ?? null, []),
        'specialPrice' => (float)$r['special_price'],
        'totalMrp'     => (float)$r['total_mrp'],
        'youSave'      => (float)$r['you_save'],
        'saveExtra'    => $r['save_extra'],
        'validTill'    => $r['valid_till'],
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
