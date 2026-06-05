<?php
/**
 * build_initial_data.php  —  one-time generator for the "Initial Data Insert" migration.
 *
 * Reads the curated storefront snapshot (smart-dental-innovation/seed-data.json), downloads
 * every referenced image into dentinno/assets/images/seed/<bucket>/, rewrites the data to use
 * those local URLs, and emits a static SQL migration: dentinno/database_initial_data_insert.sql
 *
 * Field mapping mirrors seed_from_react.php (the authoritative seeder) so the storefront
 * (api/v1/_map.php) reads the data exactly as before — only image URLs become local.
 *
 * Run:  php dentinno/tools/build_initial_data.php
 */

require_once __DIR__ . '/../includes/config.php';   // defines APP_URL (no DB connection needed yet)

$ROOT      = dirname(__DIR__);                         // dentinno/
$JSON_PATH = $ROOT . '/../smart-dental-innovation/seed-data.json';
$IMG_ROOT  = $ROOT . '/assets/images/seed';           // download target
$SQL_OUT   = $ROOT . '/database_initial_data_insert.sql';
$URL_BASE  = str_replace(':80', '', rtrim(APP_URL, '/')) . '/assets/images/seed'; // local URL base

// Image strategy: false = reference the original CDN URLs directly (no download);
//                 true  = download every image into /assets/images/seed and rewrite to local URLs.
$LOCALIZE_IMAGES = false;

if (!is_file($JSON_PATH)) { fwrite(STDERR, "seed-data.json not found at $JSON_PATH\n"); exit(1); }
$data = json_decode(file_get_contents($JSON_PATH), true);
if (!$data) { fwrite(STDERR, "bad json\n"); exit(1); }

// --- DB connection only for reliable value quoting ---
$pdo = db()->getConnection();
function q($v) { global $pdo; return $v === null ? 'NULL' : $pdo->quote((string)$v); }
function qjson($v) { global $pdo; return $pdo->quote(json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); }

// =====================================================================================
// 1) Collect + download images, build oldUrl => localUrl map
// =====================================================================================
// Matches image URLs, including Storedum's transform suffix appended right after the
// extension with no separator (e.g. ".webpv1759486811width1946") and normal ?query strings.
$IMG_RE = '#^https?://[^\s"\']+\.(png|jpe?g|webp|gif|svg|avif)([^\s"\']*)?$#i';
// Image hosts that serve photos without a file extension (placeholder avatars/photos).
$EXTRA_HOSTS = ['picsum.photos', 'i.pravatar.cc'];
$urlMap = [];          // oldUrl => localUrl
$failed = [];          // urls that did not download (kept as original)
$downloaded = 0; $skipped = 0;

function isImageUrl($s) {
    global $IMG_RE, $EXTRA_HOSTS;
    if (!is_string($s) || stripos($s, 'http') !== 0) return false;
    if (preg_match($IMG_RE, $s)) return true;
    $host = parse_url($s, PHP_URL_HOST);
    return $host && in_array(strtolower($host), $EXTRA_HOSTS, true);
}

function collectUrls($node, &$out) {
    if (is_string($node)) { if (isImageUrl($node)) $out[$node] = true; }
    elseif (is_array($node)) { foreach ($node as $v) collectUrls($v, $out); }
}

// bucket = top-level seed-data key (site/featured/premiumCategories collapse to "site")
$buckets = ['products','categories','combos','offers','events','testimonials'];
$sections = [];
foreach ($data as $key => $val) {
    $bucket = in_array($key, $buckets, true) ? $key : 'site';
    $found = [];
    collectUrls($val, $found);
    foreach (array_keys($found) as $u) {
        if (!isset($sections[$u])) $sections[$u] = $bucket; // first bucket wins
    }
}

if (!$LOCALIZE_IMAGES) {
    echo "=== Image strategy: referencing original CDN URLs (no download) ===\n";
}

if ($LOCALIZE_IMAGES) {
@mkdir($IMG_ROOT, 0775, true);
foreach (array_unique(array_merge($buckets, ['site'])) as $b) @mkdir("$IMG_ROOT/$b", 0775, true);

echo "=== Downloading " . count($sections) . " unique images ===\n";
foreach ($sections as $url => $bucket) {
    // build a safe, collision-free local filename
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $base = $path !== '' ? basename($path) : 'img';
    $base = preg_replace('/[^A-Za-z0-9._-]/', '_', urldecode($base));
    if (!preg_match('/\.(png|jpe?g|webp|gif|svg|avif)$/i', $base)) {
        preg_match($IMG_RE, $url, $m); $base .= '.' . strtolower($m[1] ?? 'jpg');
    }
    $fname = substr(md5($url), 0, 8) . '_' . $base;
    $dest  = "$IMG_ROOT/$bucket/$fname";
    $local = "$URL_BASE/$bucket/$fname";

    if (is_file($dest) && filesize($dest) > 0) { $urlMap[$url] = $local; $skipped++; continue; }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,   // local dev (matches config OTP_SSL_INSECURE intent)
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36',
        CURLOPT_HTTPHEADER     => ['Accept: image/avif,image/webp,image/*,*/*'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body !== false && $code === 200 && strlen($body) > 0) {
        file_put_contents($dest, $body);
        $urlMap[$url] = $local;
        $downloaded++;
    } else {
        $failed[$url] = $code;   // keep original URL for this one
    }
}
echo "downloaded=$downloaded  skipped(existing)=$skipped  failed=" . count($failed) . "\n";
if ($failed) { echo "  (kept original URL for failed downloads:)\n"; foreach ($failed as $u=>$c) echo "   [$c] $u\n"; }

// =====================================================================================
// 2) Rewrite the data structure to local URLs
// =====================================================================================
function rewrite($node, $map) {
    if (is_string($node)) return $map[$node] ?? $node;
    if (is_array($node)) { $r = []; foreach ($node as $k=>$v) $r[$k] = rewrite($v, $map); return $r; }
    return $node;
}
$data = rewrite($data, $urlMap);

} // end if ($LOCALIZE_IMAGES)

// =====================================================================================
// 3) Emit SQL  (mirrors seed_from_react.php mapping exactly)
// =====================================================================================
$out = [];
$out[] = "-- ============================================================================";
$out[] = "-- Initial Data Insert";
$out[] = "-- Seeds dentinno_crm with the curated storefront catalog + full site config.";
$out[] = "-- Generated by dentinno/tools/build_initial_data.php (do not hand-edit).";
$out[] = $LOCALIZE_IMAGES
    ? "-- Images are served locally from /assets/images/seed/."
    : "-- Images reference the original Storedum CDN URLs (merchant-cdn.storedum.com).";
$out[] = "-- ============================================================================";
$out[] = "SET NAMES utf8mb4;";
$out[] = "SET FOREIGN_KEY_CHECKS=0;";
$out[] = "TRUNCATE TABLE products;";
$out[] = "TRUNCATE TABLE categories;";
$out[] = "TRUNCATE TABLE combos;";
$out[] = "TRUNCATE TABLE offers;";
$out[] = "TRUNCATE TABLE events;";
$out[] = "TRUNCATE TABLE testimonials;";
$out[] = "";

// --- Categories (explicit ids so products can reference them deterministically) ---
$slugToId = [];
$cid = 0; $sort = 0;
$out[] = "-- Categories";
foreach ($data['categories'] as $c) {
    $cid++; $slug = $c['id']; $slugToId[$slug] = $cid;
    $out[] = "INSERT INTO categories (id,name,slug,image,is_active,sort_order) VALUES "
           . "($cid," . q($c['title']) . "," . q($slug) . "," . q($c['img'] ?? null) . ",1," . ($sort++) . ");";
}
// Prosthodontics fixup (curated section not present 1:1 in React categories)
if (!isset($slugToId['prosthodontics'])) {
    $cid++; $slugToId['prosthodontics'] = $cid;
    $out[] = "INSERT INTO categories (id,name,slug,is_active,sort_order) VALUES ($cid,'Prosthodontics','prosthodontics',1,$sort);";
}
$prosthoId = $slugToId['prosthodontics'];
$prosthoSlugs = ['i-001','i-002','p-006','i-003','h-001','p-007','m-001','p-008','m-002','m-003'];
$out[] = "";

// --- Products ---
$out[] = "-- Products";
foreach ($data['products'] as $p) {
    $slug = (string)$p['id'];
    $mrp  = (float)$p['mrp'];
    $sell = (float)$p['price'];
    $disc = isset($p['discount']) ? (float)$p['discount'] : 0;
    $stock = !empty($p['inStock']) ? 25 : 0;
    $images   = $p['images'] ?? [$p['image'] ?? null];
    $variants = $p['variants'] ?? [];
    $specs    = ['rating'=>$p['rating']??null,'reviews'=>$p['reviews']??null,'warranty'=>$p['warranty']??null];
    $isFeat   = strpos($slug,'p-') === 0 ? 1 : 0;
    $isNew    = strpos($slug,'n-') === 0 ? 1 : 0;
    $catId    = in_array($slug, $prosthoSlugs, true) ? $prosthoId : ($slugToId[$p['category'] ?? ''] ?? null);
    $desc     = $p['description'] ?? '';
    $shortD   = mb_substr($desc, 0, 480);

    $out[] = "INSERT INTO products (name,slug,sku,category_id,description,short_description,full_description,"
           . "price,discount_price,discount_percent,stock,images,variants,specifications,hover_image,"
           . "is_active,is_featured,is_new,total_sales) VALUES ("
           . q($p['name']) . "," . q($slug) . "," . q(strtoupper($slug)) . ","
           . ($catId === null ? 'NULL' : (int)$catId) . ","
           . q($desc) . "," . q($shortD) . "," . q($desc) . ","
           . $mrp . "," . $sell . "," . $disc . "," . $stock . ","
           . qjson($images) . "," . qjson($variants) . "," . qjson($specs) . ",NULL,"
           . "1," . $isFeat . "," . $isNew . "," . (int)($p['reviews'] ?? 0) . ");";
}
$out[] = "";

// --- Combos ---
$out[] = "-- Combos";
$i = 0;
foreach ($data['combos'] as $c) {
    $out[] = "INSERT INTO combos (slug,name,description,mrp,price,discount_percent,image,images,in_stock,sort_order) VALUES ("
           . q($c['id']) . "," . q($c['name']) . "," . q($c['description'] ?? '') . ","
           . (float)$c['mrp'] . "," . (float)$c['price'] . "," . (float)($c['discount'] ?? 0) . ","
           . q($c['image'] ?? null) . "," . qjson($c['images'] ?? []) . ",1," . ($i++) . ");";
}
$out[] = "";

// --- Offers ---
$out[] = "-- Offers";
$i = 0;
foreach ($data['offers'] as $o) {
    $out[] = "INSERT INTO offers (slug,title,subtitle,theme,accent,gradient,cta,main_product,free_items,"
           . "special_price,total_mrp,you_save,save_extra,valid_till,sort_order) VALUES ("
           . q($o['id']) . "," . q($o['title']) . "," . q($o['subtitle'] ?? '') . "," . q($o['theme'] ?? null) . ","
           . q($o['accent'] ?? null) . "," . q($o['gradient'] ?? null) . "," . q($o['cta'] ?? null) . ","
           . qjson($o['mainProduct'] ?? null) . "," . qjson($o['freeItems'] ?? []) . ","
           . (float)($o['specialPrice'] ?? 0) . "," . (float)($o['totalMrp'] ?? 0) . "," . (float)($o['youSave'] ?? 0) . ","
           . q($o['saveExtra'] ?? null) . "," . q($o['validTill'] ?? null) . "," . ($i++) . ");";
}
$out[] = "";

// --- Events (events table has no mrp column; start/end dates are required) ---
$out[] = "-- Events";
foreach ($data['events'] as $e) {
    $fee = (float)($e['price'] ?? 0);
    $out[] = "INSERT INTO events (title,slug,description,event_type,status,start_date,end_date,"
           . "registration_fee,is_free,banner_image,organizer) VALUES ("
           . q($e['name']) . "," . q($e['id']) . "," . q($e['description'] ?? '') . ",'training','published',"
           . "'2026-12-01 10:00:00','2026-12-01 18:00:00',"
           . $fee . "," . ($fee == 0 ? 1 : 0) . "," . q($e['image'] ?? null) . ",'Smart Dental Innovations');";
}
$out[] = "";

// --- Testimonials ---
$out[] = "-- Testimonials";
$i = 0;
foreach ($data['testimonials'] as $t) {
    $out[] = "INSERT INTO testimonials (slug,name,avatar,product_image,text,sort_order) VALUES ("
           . q($t['id']) . "," . q($t['name']) . "," . q($t['avatar'] ?? null) . ","
           . q($t['productImage'] ?? null) . "," . q($t['text']) . "," . ($i++) . ");";
}
$out[] = "";

// --- Site settings (home sections, banners, RF showcase, about/contact, payments, etc.) ---
$out[] = "-- Site settings (full storefront configuration)";
$settings = [];
if (!empty($data['site']) && is_array($data['site'])) {
    foreach ($data['site'] as $k => $v) $settings[$k] = $v;
}
if (isset($data['featured']))          $settings['featured'] = $data['featured'];
if (isset($data['premiumCategories'])) $settings['premiumCategories'] = $data['premiumCategories'];
foreach ($settings as $k => $v) {
    $out[] = "INSERT INTO site_settings (skey,svalue) VALUES (" . q($k) . "," . qjson($v) . ") "
           . "ON DUPLICATE KEY UPDATE svalue=VALUES(svalue);";
}
$out[] = "";
$out[] = "SET FOREIGN_KEY_CHECKS=1;";
$out[] = "";

file_put_contents($SQL_OUT, implode("\n", $out));
echo "=== Wrote migration: $SQL_OUT (" . count($out) . " lines) ===\n";
echo "categories=" . count($data['categories']) . " products=" . count($data['products'])
   . " combos=" . count($data['combos']) . " offers=" . count($data['offers'])
   . " events=" . count($data['events']) . " testimonials=" . count($data['testimonials'])
   . " settings=" . count($settings) . "\n";
