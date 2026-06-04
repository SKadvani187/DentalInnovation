<?php
// Seeds dentinno_crm from React storefront data (seed-data.json).
// Run from CLI: php seed_from_react.php
// Maps React shapes -> DB columns. React string id -> slug. Computed fields preserved.

require_once __DIR__ . '/includes/config.php';

$jsonPath = __DIR__ . '/../smart-dental-innovation/seed-data.json';
if (!file_exists($jsonPath)) {
    fwrite(STDERR, "seed-data.json not found at $jsonPath\n");
    exit(1);
}
$data = json_decode(file_get_contents($jsonPath), true);
if (!$data) { fwrite(STDERR, "bad json\n"); exit(1); }

$db = db();
$pdo = $db->getConnection();

echo "=== Seeding from React data ===\n";

// Wipe existing product/category sample rows (keep schema). FK: products.category_id -> categories.
// order_items references products; clear dependent demo rows safely.
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$pdo->exec("TRUNCATE TABLE products");
$pdo->exec("TRUNCATE TABLE categories");
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
echo "cleared products + categories\n";

// --- Categories ---
$catIdBySlug = [];
$insCat = $pdo->prepare(
    "INSERT INTO categories (name, slug, image, is_active, sort_order) VALUES (?,?,?,1,?)"
);
$order = 0;
foreach ($data['categories'] as $c) {
    $slug = $c['id'];               // React id is the slug
    $name = $c['title'];
    $img  = $c['img'] ?? null;
    $insCat->execute([$name, $slug, $img, $order++]);
    $catIdBySlug[$slug] = (int)$pdo->lastInsertId();
}
echo "inserted " . count($catIdBySlug) . " categories\n";

// Helper: resolve React product.category (slug) -> category_id (or null)
function catId($slug, $map) { return $map[$slug] ?? null; }

// --- Products ---
$insProd = $pdo->prepare(
    "INSERT INTO products
     (name, slug, sku, category_id, description, short_description,
      price, discount_price, discount_percent, stock, images, variants, specifications,
      is_active, is_featured, total_sales)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);

$prodCount = 0;
foreach ($data['products'] as $p) {
    // React: mrp = original, price = selling. DB: price = selling (or mrp?), discount_price = selling.
    // Keep it intuitive for admin: price = MRP, discount_price = selling price, discount_percent = p.discount.
    $mrp   = (float)$p['mrp'];
    $sell  = (float)$p['price'];
    $disc  = isset($p['discount']) ? (float)$p['discount'] : 0;
    $stock = !empty($p['inStock']) ? 25 : 0;   // React only has boolean; give a default qty
    $images   = json_encode($p['images'] ?? [$p['image'] ?? null]);
    $variants = json_encode($p['variants'] ?? []);
    $specs    = json_encode([
        'rating'   => $p['rating']   ?? null,
        'reviews'  => $p['reviews']  ?? null,
        'warranty' => $p['warranty'] ?? null,
    ]);
    $isFeatured = 0;

    $insProd->execute([
        $p['name'],
        $p['id'],                       // slug
        strtoupper($p['id']),           // sku from id (unique)
        catId($p['category'] ?? '', $catIdBySlug),
        $p['description'] ?? '',
        mb_substr($p['description'] ?? '', 0, 480),
        $mrp,
        $sell,
        $disc,
        $stock,
        $images,
        $variants,
        $specs,
        1,
        $isFeatured,
        $p['reviews'] ?? 0,
    ]);
    $prodCount++;
}
echo "inserted $prodCount products\n";

// --- Events (schema has events table from database_additions.sql) ---
$evCount = 0;
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $pdo->exec("TRUNCATE TABLE events");
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    $insEv = $pdo->prepare(
        "INSERT INTO events
         (title, slug, description, event_type, status, registration_fee, is_free, banner_image, organizer)
         VALUES (?,?,?,?, 'published', ?, ?, ?, ?)"
    );
    foreach ($data['events'] as $e) {
        $slug = $e['id'];
        $fee  = (float)($e['price'] ?? 0);
        $insEv->execute([
            $e['name'],
            $slug,
            $e['description'] ?? '',
            'training',
            $fee,
            $fee == 0 ? 1 : 0,
            $e['image'] ?? null,
            'Smart Dental Innovations',
        ]);
        $evCount++;
    }
    echo "inserted $evCount events\n";
} catch (Throwable $t) {
    echo "events skipped: " . $t->getMessage() . "\n";
}

// --- Combos ---
$pdo->exec("TRUNCATE TABLE combos");
$insCombo = $pdo->prepare(
    "INSERT INTO combos (slug, name, description, mrp, price, discount_percent, image, images, in_stock, sort_order)
     VALUES (?,?,?,?,?,?,?,?,?,?)"
);
$cnt = 0;
foreach ($data['combos'] as $c) {
    $insCombo->execute([
        $c['id'], $c['name'], $c['description'] ?? '',
        (float)$c['mrp'], (float)$c['price'], (float)($c['discount'] ?? 0),
        $c['image'] ?? null, json_encode($c['images'] ?? []),
        !empty($c['inStock']) ? 1 : 1, $cnt++,
    ]);
}
echo "inserted $cnt combos\n";

// --- Offers ---
$pdo->exec("TRUNCATE TABLE offers");
$insOffer = $pdo->prepare(
    "INSERT INTO offers (slug, title, subtitle, theme, accent, gradient, cta, main_product, free_items,
        special_price, total_mrp, you_save, save_extra, valid_till, sort_order)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);
$cnt = 0;
foreach ($data['offers'] as $o) {
    $insOffer->execute([
        $o['id'], $o['title'], $o['subtitle'] ?? '', $o['theme'] ?? null,
        $o['accent'] ?? null, $o['gradient'] ?? null, $o['cta'] ?? null,
        json_encode($o['mainProduct'] ?? null), json_encode($o['freeItems'] ?? []),
        (float)($o['specialPrice'] ?? 0), (float)($o['totalMrp'] ?? 0), (float)($o['youSave'] ?? 0),
        $o['saveExtra'] ?? null, $o['validTill'] ?? null, $cnt++,
    ]);
}
echo "inserted $cnt offers\n";

// --- Testimonials ---
$pdo->exec("TRUNCATE TABLE testimonials");
$insT = $pdo->prepare(
    "INSERT INTO testimonials (slug, name, avatar, product_image, text, sort_order) VALUES (?,?,?,?,?,?)"
);
$cnt = 0;
foreach ($data['testimonials'] as $t) {
    $insT->execute([
        $t['id'], $t['name'], $t['avatar'] ?? null, $t['productImage'] ?? null, $t['text'], $cnt++,
    ]);
}
echo "inserted $cnt testimonials\n";

// --- Site settings (key-value JSON) ---
if (!empty($data['site']) && is_array($data['site'])) {
    $upSetting = $pdo->prepare(
        "INSERT INTO site_settings (skey, svalue) VALUES (?,?)
         ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)"
    );
    $n = 0;
    foreach ($data['site'] as $key => $val) {
        $upSetting->execute([$key, json_encode($val)]);
        $n++;
    }
    // featured + premiumCategories as settings too (home showcase config)
    if (!empty($data['featured']))          { $upSetting->execute(['featured', json_encode($data['featured'])]); $n++; }
    if (!empty($data['premiumCategories'])) { $upSetting->execute(['premiumCategories', json_encode($data['premiumCategories'])]); $n++; }
    echo "seeded $n site settings\n";
}

echo "=== DONE ===\n";
