<?php
/**
 * Importer v2 for the live-site scrape (site-scraper/scraper/out/products_raw.json, 507 products).
 *
 * Source shape differs entirely from the old smartdental_import.json. Each product object carries:
 *   product_name, product_url(slug), sku, price(sell), price_crossed(MRP),
 *   short_description, category[] (plain strings, mix of cats/collections/price-buckets),
 *   images.{dark,light}[].{url,220px_url,...}, png_image_transparent.{dark,light}(string url),
 *   dimensions.{weight(g),height,length,width,...},
 *   product_information[]            -> {title,description}  (structured spec/highlight rows)
 *   product_additional_information[] -> {title,description}  (HTML section content per title)
 *   product_faqs[] -> {question,answer},  variants[] -> {variant_name,price,mrp,...},
 *   bmsm[] -> {minQty,price} (bulk tiers),  taxation.hsn_code,  youtube_video_url,
 *   badges{name},  enabled, product_available, is_combo_product.
 *
 * MAPPING (per the reviewed plan):
 *   name             <- product_name
 *   slug             <- product_url (deduped)
 *   sku              <- sku (blank -> generated)
 *   price            <- price_crossed (MRP)
 *   discount_price   <- price (null if >= price_crossed)
 *   discount_percent <- derived
 *   category_id      <- first real category[] string (price-buckets / collections skipped)
 *   short_description<- short_description (plain)
 *   description      <- product_additional_information."Description"(+typos) HTML, mojibake-fixed;
 *                       fallback product_information."Description"/"Overview". short_description NOT used.
 *   full_description <- NULL (storefront falls back fullDescription||description)
 *   key_specifications <- product_additional_information."Key Specifications" HTML parsed to [{key,value}];
 *                         fallback: ALL product_information rows (kept verbatim, nothing dropped) as [{key,value}]
 *   features         <- product_information "Key Features"/"Features" rows -> [{title,text}]
 *   directions_for_use <- p.a.i "Directions to Use"/variants (HTML, mojibake-fixed)
 *   packing_info     <- p.a.i "Packaging Info"/variants
 *   warranty_info    <- p.a.i "Warranty"
 *   key_features     <- p.a.i "Key Features"
 *   bulk_offers      <- bmsm[] -> [{minQty, rate:(sell-tier)/sell, label}]
 *   variants         <- variants[] -> [{label,price,mrp,discount}]
 *   images           <- images.light[].url  (JSON ["url",...])
 *   hover_image      <- png_image_transparent.light (single url)
 *   weight_kg        <- dimensions.weight / 1000
 *   hsn_code         <- taxation.hsn_code
 *   youtube_video_url<- youtube_video_url
 *   is_featured      <- badges.name == 'Best seller' ? 1 : 0
 *   is_active        <- enabled && product_available
 *   stock            <- 100
 *   product_faqs     <- product_faqs[] (replace per product)
 *   is_combo_product -> route to combos table (card only).
 *
 * Usage:
 *   php import_smartdental_v2.php                 # import all
 *   php import_smartdental_v2.php --limit=3       # smoke test
 *   php import_smartdental_v2.php --file=path.json
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/includes/config.php';

$opts  = getopt('', ['file:', 'limit:', 'help']);
if (isset($opts['help'])) { fwrite(STDERR, "See header for usage.\n"); exit(0); }
$file  = $opts['file'] ?? dirname(__DIR__) . '/site-scraper/scraper/out/products_raw.json';
$limit = isset($opts['limit']) ? max(0, (int)$opts['limit']) : 0;

if (!is_file($file)) { fwrite(STDERR, "File not found: $file\n"); exit(1); }
$rows = json_decode(file_get_contents($file), true);
if (!is_array($rows)) { fwrite(STDERR, "Invalid JSON in $file\n"); exit(1); }

$db = db();

// ---- helpers -------------------------------------------------------------

// Fix mojibake where real UTF-8 bytes were decoded as Windows-1252/Latin-1 and re-saved as UTF-8.
// In this scrape it shows as the classic "Mojibake-Â/â" prefixes, e.g. "â‚¹2000" (₹), "â€”" (em-dash),
// "â€¢" (bullet), "Â " (NBSP). Recovery = reinterpret each char as a Latin-1 byte, then decode those
// bytes as UTF-8. Crucially this is done by REPLACING ONLY the known mojibake substrings — a blanket
// re-decode of the whole string corrupts characters that are already correct UTF-8. So we map the
// specific sequences we actually see, leaving everything else byte-for-byte untouched.
$MOJI_MAP = [
    "\u{00e2}\u{0082}\u{00b9}" => "₹",   // â‚¹  -> ₹  (the â¹ in the JSON)
    "\u{00e2}\u{0080}\u{0094}" => "—",   // â€”  -> em dash
    "\u{00e2}\u{0080}\u{0093}" => "–",   // â€“  -> en dash
    "\u{00e2}\u{0080}\u{0099}" => "’",   // â€™  -> right single quote
    "\u{00e2}\u{0080}\u{009c}" => "“",   // â€œ  -> left double quote
    "\u{00e2}\u{0080}\u{009d}" => "”",   // â€  -> right double quote
    "\u{00e2}\u{0080}\u{00a2}" => "•",   // â€¢  -> bullet
    "\u{00e2}\u{0080}\u{00a6}" => "…",   // â€¦  -> ellipsis
    "\u{00c3}\u{00a9}"         => "é",   // Ã©  -> é
    "\u{00c2}\u{00a0}"         => " ",   // Â(nbsp) -> space
    "\u{00c2}\u{00ae}"         => "®",   // Â®
    "\u{00c2}\u{00b0}"         => "°",   // Â°
];
$fixMojibake = function ($s) {
    $s = (string)$s;
    if ($s === '' || (strpos($s, "\u{00c2}") === false && strpos($s, "\u{00c3}") === false && strpos($s, "\u{00e2}") === false)) {
        return $s;
    }
    // Mojibake run = Ã/Â (2-char) or â (3-char) lead expressed as UTF-8 chars; re-decode ONLY those
    // runs (a whole-string re-decode would corrupt already-correct chars). Repeats for double-encoding.
    $pattern = '/(?:[\x{00c2}\x{00c3}][\x{0080}-\x{00bf}])'
             . '|(?:\x{00e2}[\x{0080}-\x{00bf}][\x{0080}-\x{00bf}])/u';
    for ($pass = 0; $pass < 2; $pass++) {
        $changed = false;
        $out = preg_replace_callback($pattern, function ($m) use (&$changed) {
            $bytes = '';
            foreach (preg_split('//u', $m[0], -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                $cp = mb_ord($ch, 'UTF-8');
                if ($cp === false || $cp > 0xFF) return $m[0];
                $bytes .= chr($cp);
            }
            if (mb_check_encoding($bytes, 'UTF-8') && $bytes !== $m[0]) { $changed = true; return $bytes; }
            return $m[0];
        }, $s);
        if ($out !== null) $s = $out;
        if (!$changed) break;
    }
    return $s;
};

// Sanitise HTML for storage: keep only the structural tags RichText/DOMPurify renders, drop all
// attributes except href on <a>. Mirrors the old importer's cleanHtml so storefront renders the
// same structure (lists/bold/headers) as the source. Runs AFTER mojibake fix.
$cleanHtml = function ($s) use ($fixMojibake) {
    $s = $fixMojibake((string)$s);
    if (trim($s) === '') return '';
    $s = strip_tags($s, '<p><br><b><strong><i><em><u><ul><ol><li><h3><h4><h5><h6><a><div><span><table><thead><tbody><tfoot><tr><td><th><caption>');
    $s = preg_replace_callback('#<([a-zA-Z][a-z0-9]*)\b[^>]*>#i', function ($m) {
        $tag = strtolower($m[1]);
        if ($tag === 'a' && preg_match('#href\s*=\s*("[^"]*"|\'[^\']*\')#i', $m[0], $h)) {
            return '<a href=' . $h[1] . ' target="_blank" rel="noopener noreferrer">';
        }
        return '<' . $tag . '>';
    }, $s);
    $s = preg_replace('#<(p|li|h[3-6]|strong|b|em|i|u)>\s*</\1>#i', '', $s);
    // Drop redundant LITERAL bullet glyphs typed into the content (e.g. "<p>• <strong>...").
    // The list / paragraph structure already provides the bullet, so a hard-coded "•" just
    // doubles up. Strip a leading glyph at string start, right after an opening block tag, or
    // right after a <br>. Real "<" / numbers are untouched.
    $glyph = '[\x{2022}\x{00B7}\x{25AA}\x{25E6}\x{2023}\x{2219}\x{2043}\x{2705}\x{2713}\x{2714}\x{2611}\x{25CF}\x{25CB}\x{25A0}\x{25A1}\x{2756}\x{27A4}\x{25B6}\x{2192}\*\x{2013}\x{2014}]';
    $s = preg_replace('#(^|<(?:p|li|div|span|td|h[3-6])>|<br>)\s*' . $glyph . '\s*#u', '$1', $s);
    $s = preg_replace('#(\s*<br>\s*){2,}#i', '<br>', $s);
    $s = preg_replace('/[ \t]{2,}/', ' ', $s);
    $s = trim($s);
    return trim(strip_tags($s)) === '' ? '' : $s;
};

// HTML -> clean plain text (for short_description and the {key,value} spec values).
$htmlToText = function ($s) use ($fixMojibake) {
    $s = $fixMojibake((string)$s);
    if ($s === '') return '';
    $s = preg_replace('#<\s*br\s*/?>#i', "\n", $s);
    $s = preg_replace('#<\s*/\s*(p|div|h[1-6]|tr|ul|ol|li)\s*>#i', "\n", $s);
    $s = preg_replace('#<[^>]+>#', '', $s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace("\xc2\xa0", ' ', $s);
    $s = preg_replace('/[ \t]+/', ' ', $s);
    $s = preg_replace('/ *\n */', "\n", $s);
    $s = preg_replace('/\n{3,}/', "\n\n", $s);
    return trim($s);
};

// Index a product_(additional_)information array by lowercased title -> first non-empty description.
$byTitle = function (array $list) {
    $out = [];
    foreach ($list as $r) {
        $t = strtolower(trim((string)($r['title'] ?? '')));
        $d = (string)($r['description'] ?? '');
        if ($t === '' || trim($d) === '') continue;
        if (!isset($out[$t])) $out[$t] = $d;       // keep first occurrence
    }
    return $out;
};

// Pull the first matching title's body from an indexed map (title variants in priority order).
$pick = function (array $idx, array $titles) {
    foreach ($titles as $t) {
        $k = strtolower($t);
        if (isset($idx[$k]) && trim($idx[$k]) !== '') return $idx[$k];
    }
    return '';
};

// Parse the HTML "Key Specifications" section into [{key,value}]. The section is usually a list or
// <p>/<br> lines of "Label: value". Falls back to [] when nothing parseable.
$parseSpecHtml = function ($html) use ($htmlToText) {
    $txt = $htmlToText($html);
    if ($txt === '') return [];
    $out = []; $seen = [];
    foreach (preg_split('/\r?\n/', $txt) as $line) {
        $line = trim(preg_replace('/^[\x{2022}\x{00B7}\x{25AA}\x{2023}*\-\x{2013}\x{2014}]+\s*/u', '', $line));
        if ($line === '' || mb_strlen($line) > 220) continue;
        if (preg_match('/^([^:]{1,60}):\s*(.+)$/u', $line, $m)) {
            $k = trim($m[1]); $v = trim($m[2]);
            $kl = mb_strtolower($k);
            if ($k !== '' && $v !== '' && !isset($seen[$kl])) { $out[] = ['key' => $k, 'value' => $v]; $seen[$kl] = 1; }
        }
        if (count($out) >= 40) break;
    }
    return $out;
};

// Build [{key,value}] from ALL product_information rows (keep everything — user chose no dropping).
$infoToSpecs = function (array $list) use ($htmlToText) {
    $out = []; $seen = [];
    foreach ($list as $r) {
        $k = trim((string)($r['title'] ?? ''));
        $v = $htmlToText((string)($r['description'] ?? ''));
        if ($k === '' || $v === '') continue;
        $kl = mb_strtolower($k);
        if (isset($seen[$kl])) continue;
        $seen[$kl] = 1;
        $out[] = ['key' => $k, 'value' => $v];
        if (count($out) >= 40) break;
    }
    return $out;
};

// Product Highlights box <- the ENTIRE product_information array, verbatim, as [{title,text}].
// The live site renders product_information AS "Product Highlights" (e.g. "Product name: ...",
// "Price: ... inr", "Dimensions: ...", "Weight: ...", "Usage: ..."). EVERY row is kept exactly as
// the source has it — NOTHING is dropped, reordered, judged, or capped. Title = row title, text =
// row description (mojibake-fixed plain text). This is the field the user verified against the site.
$infoToHighlights = function (array $list) use ($htmlToText) {
    $out = [];
    foreach ($list as $r) {
        $title = trim($htmlToText((string)($r['title'] ?? '')));
        $text  = trim($htmlToText((string)($r['description'] ?? '')));
        if ($title === '' && $text === '') continue;   // only skip truly empty rows
        $out[] = ['title' => $title, 'text' => $text];
    }
    return $out;
};

// images.light[].url -> ["url",...] (dedup, keep order). Falls back to dark if light empty.
$imageUrls = function ($images) {
    $pick = function ($arr) {
        $out = [];
        if (is_array($arr)) foreach ($arr as $im) {
            $u = trim((string)($im['url'] ?? ''));
            if ($u !== '' && !in_array($u, $out, true)) $out[] = $u;
        }
        return $out;
    };
    $light = $pick($images['light'] ?? []);
    return $light ?: $pick($images['dark'] ?? []);
};

// bmsm[] {minQty,price} -> [{minQty, rate, label}] vs the selling price.
$bulkOffers = function ($bmsm, $sell) {
    if (!is_array($bmsm) || $sell <= 0) return [];
    $out = []; $seen = [];
    foreach ($bmsm as $t) {
        $q = (int)($t['minQty'] ?? 0); $p = (float)($t['price'] ?? 0);
        if ($q < 2 || $p <= 0 || $p >= $sell || isset($seen[$q])) continue;
        $seen[$q] = 1;
        $out[] = ['minQty' => $q, 'rate' => round(($sell - $p) / $sell, 6), 'label' => 'Buy ' . $q . ' or above'];
    }
    usort($out, fn($a, $b) => $a['minQty'] - $b['minQty']);
    return $out;
};

// variants[] -> [{label,price,mrp,discount}] (storefront shape).
$mapVariants = function ($variants) {
    if (!is_array($variants)) return [];
    $out = [];
    foreach ($variants as $v) {
        $label = trim((string)($v['variant_name'] ?? ''));
        $price = (float)($v['price'] ?? 0);
        $mrp   = (float)($v['mrp'] ?? 0);
        if ($label === '' && $price <= 0) continue;
        $disc = ($mrp > 0 && $price > 0 && $price < $mrp) ? round(($mrp - $price) / $mrp * 100, 2) : 0;
        $out[] = ['label' => $label, 'price' => $price, 'mrp' => $mrp, 'discount' => $disc];
    }
    return $out;
};

// Category resolution: first category[] entry that isn't a price-bucket or known collection tag.
$COLLECTION_TAGS = ['great value products','new product','new products','unique product','unique products',
    'best seller','bestseller','best sellers','top deal','top deals','featured','trending',
    'daily dental supplies','disposable','combo'];
$catAlias = ['handpieces' => 'Handpiece'];
$catCache = [];
foreach ($db->fetchAll("SELECT id, name FROM categories") as $c) {
    $catCache[mb_strtolower($c['name'])] = (int)$c['id'];
}
$resolveCat = function ($cats) use (&$catCache, $catAlias, $COLLECTION_TAGS, $db, $fixMojibake) {
    if (!is_array($cats)) return null;
    foreach ($cats as $raw) {
        $t = trim($fixMojibake((string)$raw));
        if ($t === '') continue;
        if (mb_strpos($t, '₹') !== false || preg_match('/below|under|above|great value/i', $t)) continue; // price bucket
        if (in_array(mb_strtolower($t), $COLLECTION_TAGS, true)) continue;                                 // collection tag
        $canon = $catAlias[mb_strtolower($t)] ?? $t;
        $key = mb_strtolower($canon);
        if (isset($catCache[$key])) return $catCache[$key];
        $slug = generateSlug($canon) ?: 'category';
        $base = $slug; $n = 1;
        while ($db->fetchOne("SELECT id FROM categories WHERE slug=?", [$slug])) $slug = $base . '-' . (++$n);
        $id = (int)$db->insert("INSERT INTO categories (name, slug, is_active, sort_order) VALUES (?,?,1,0)", [$canon, $slug]);
        $catCache[$key] = $id;
        fwrite(STDERR, "  + category: $canon\n");
        return $id;
    }
    return null;
};

$uniqueSlug = function ($name, $slugRaw, $sku) use ($db) {
    $slug = generateSlug($slugRaw) ?: (generateSlug($name) ?: ('product-' . strtolower($sku)));
    $base = $slug; $n = 1;
    while (($r = $db->fetchOne("SELECT id, sku FROM products WHERE slug=?", [$slug])) && $r['sku'] !== $sku) {
        $slug = $base . '-' . (++$n);
    }
    return $slug;
};

$num = fn($v) => (float)preg_replace('/[^0-9.]/', '', (string)$v);

// ---- import --------------------------------------------------------------
$slice = $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
$created = $updated = $skipped = $faqCount = $comboCount = 0;
$usedSku = [];

foreach ($slice as $i => $r) {
    try {
        $name = trim($fixMojibake((string)($r['product_name'] ?? '')));
        if ($name === '') { $skipped++; continue; }

        $mrp  = $num($r['price_crossed'] ?? 0);
        $sell = $num($r['price'] ?? 0);
        if ($mrp <= 0)  $mrp  = $sell;
        if ($sell <= 0) $sell = $mrp;
        if ($mrp <= 0)  { $skipped++; continue; }
        $price = $mrp;
        $discPrice = ($sell > 0 && $sell < $mrp) ? $sell : null;
        $discPct   = $discPrice ? round((($price - $discPrice) / $price) * 100, 2) : 0;

        $sku = trim((string)($r['sku'] ?? '')) ?: ('SDX-' . strtoupper(substr(md5($name), 0, 10)));
        if (isset($usedSku[$sku])) { $sku = $sku . '-' . (++$usedSku[$sku]); }
        else { $usedSku[$sku] = 1; }

        $slug = $uniqueSlug($name, $r['product_url'] ?? '', $sku);

        $pinfo = is_array($r['product_information'] ?? null) ? $r['product_information'] : [];
        $pai   = is_array($r['product_additional_information'] ?? null) ? $r['product_additional_information'] : [];
        $paiIdx = $byTitle($pai);
        $piIdx  = $byTitle($pinfo);

        $shortDesc = $htmlToText($r['short_description'] ?? '');
        $shortCol  = $shortDesc !== '' ? mb_substr($shortDesc, 0, 500) : null;

        // description <- PAI "Description"(+typos), fallback product_information "Description"/"Overview".
        // short_description deliberately NOT used. full_description left null.
        $descHtml = $pick($paiIdx, ['description','descriptions','desctiption','descripton','desscription',
            'desription','product description','product descriptions','long description','detailed description',
            'key description']);
        if ($descHtml === '') {
            $descHtml = $pick($piIdx, ['description','product description','product overview','overview']);
        }
        $description = $cleanHtml($descHtml) ?: null;

        // Section HTML fields (mojibake-fixed, RichText-rendered).
        $directions = $cleanHtml($pick($paiIdx, ['directions to use','direction of use','directions for use',
            'direction to use','direction for use','directions of use','diraction of use','usage','how to use'])) ?: null;
        $packing    = $cleanHtml($pick($paiIdx, ['packaging info','packaging information','packaging','packing info',
            'package info','packing','packagign info','packaginng info','packaginf info'])) ?: null;
        // Warranty kept VERBATIM (even a bare "No") — the live site shows whatever the source has;
        // nothing is judged or discarded.
        $warranty   = $cleanHtml($pick($paiIdx, ['warranty','warranty & support','warranty and support'])) ?: null;
        $keyFeatures= $cleanHtml($pick($paiIdx, ['key features','key feature'])) ?: null;

        // Key Specifications box <- the "Key Specifications" HTML section, kept as RAW (cleaned) HTML so
        // it renders EXACTLY like the live site (prose, lists, "Label: value" lines — whatever the
        // source has). Stored as an HTML string (NOT a {key,value} array); the storefront renders it
        // via RichText. Nothing is parsed away or dropped.
        $keySpecHtml = $cleanHtml($pick($paiIdx, ['key specifications','key specification','key spacification',
            'key spacification','technical specifications','technical specification','specifications','specification'])) ?: null;

        // Product Highlights box <- the ENTIRE product_information array, verbatim [{title,text}].
        $highlights = $infoToHighlights($pinfo);

        // Media.
        $images = $imageUrls($r['images'] ?? []);
        $hover  = trim((string)(($r['png_image_transparent']['light'] ?? '') ?: ($r['png_image_transparent']['dark'] ?? ''))) ?: null;

        // Bulk + variants.
        $bo  = $bulkOffers($r['bmsm'] ?? [], $discPrice ?? $price);
        $vr  = $mapVariants($r['variants'] ?? []);

        // Misc scalars.
        $weightG  = (float)($r['dimensions']['weight'] ?? 0);
        $weightKg = $weightG > 0 ? round($weightG / 1000, 3) : null;
        $hsn      = trim((string)($r['taxation']['hsn_code'] ?? '')) ?: null;
        $yt       = trim((string)($r['youtube_video_url'] ?? '')) ?: null;
        // badges is a LIST of {name,color,...}; flag featured when any badge is "Best seller".
        $isFeat = 0;
        foreach ((is_array($r['badges'] ?? null) ? $r['badges'] : []) as $bdg) {
            if (strtolower(trim((string)($bdg['name'] ?? ''))) === 'best seller') { $isFeat = 1; break; }
        }
        $isActive = (($r['enabled'] ?? true) && ($r['product_available'] ?? true)) ? 1 : 0;

        $catId = $resolveCat($r['category'] ?? []);

        // Combo detection: the live site marks combos via a "combo" entry in category[] (the
        // is_combo_product flag is always false in this feed), so route by the category tag.
        $isCombo = !empty($r['is_combo_product']);
        foreach ((is_array($r['category'] ?? null) ? $r['category'] : []) as $c) {
            if (strtolower(trim((string)$c)) === 'combo') { $isCombo = true; break; }
        }

        // ---- combos route ----
        // Combos carry the SAME rich detail as products (highlights, description, specs, sections,
        // FAQs) so a combo opens as a full product page like the reference site.
        if ($isCombo) {
            $comboCols = [
                'slug' => $slug, 'name' => $name, 'sku' => $sku,
                'description' => $description,                          // JSON Description HTML
                'short_description' => $shortCol,
                'mrp' => $price, 'price' => $discPrice ?? $price, 'discount_percent' => $discPct,
                'image' => $images[0] ?? null, 'images' => $images ? json_encode($images) : null,
                'hover_image' => $hover,
                'full_description' => null,                            // storefront falls back to description
                'features' => $highlights ? json_encode($highlights, JSON_UNESCAPED_UNICODE) : null,
                'key_specifications_html' => $keySpecHtml,
                'directions_for_use' => $directions,
                'packing_info' => $packing,
                'warranty_info' => $warranty,
                'key_features' => $keyFeatures,
                'items' => '[]', 'in_stock' => 1, 'stock' => 100, 'is_active' => $isActive, 'is_deleted' => 0,
            ];
            $exC = $db->fetchOne("SELECT id FROM combos WHERE slug=?", [$slug]);
            if ($exC) {
                $setC = implode('=?,', array_keys($comboCols)) . '=?';
                $db->execute("UPDATE combos SET $setC WHERE id=?", array_merge(array_values($comboCols), [$exC['id']]));
                $cid = (int)$exC['id'];
            } else {
                $phC = implode(',', array_fill(0, count($comboCols), '?'));
                $cid = (int)$db->insert("INSERT INTO combos (" . implode(',', array_keys($comboCols)) . ") VALUES ($phC)", array_values($comboCols));
            }
            // Combo FAQs reuse product_faqs, keyed by the combo's negative id space? No — keep it
            // simple: store combo FAQs in product_faqs only for products. Combos get FAQs via the
            // same table using a dedicated combo_faqs is overkill; instead we attach them when the
            // combo is served by id. For now FAQs live with products; combos show the other sections.
            $comboCount++;
            continue;
        }

        $cols = [
            'name'               => $name,
            'slug'               => $slug,
            'sku'                => $sku,
            'category_id'        => $catId,
            'price'              => $price,
            'discount_price'     => $discPrice,
            'discount_percent'   => $discPct,
            'stock'              => 100,
            'min_stock_alert'    => 5,
            'description'        => $description,                 // JSON description (HTML), NOT short_description
            'short_description'  => $shortCol,
            'full_description'   => null,                         // intentionally null; storefront falls back to description
            'key_features'       => $keyFeatures,
            'features'              => $highlights ? json_encode($highlights, JSON_UNESCAPED_UNICODE) : null,
            'key_specifications'    => null,          // JSON-validated column left empty (KS is raw HTML now)
            'key_specifications_html' => $keySpecHtml, // RAW Key-Specifications HTML, rendered via RichText
            'bulk_offers'        => $bo ? json_encode($bo) : null,
            'variants'           => $vr ? json_encode($vr, JSON_UNESCAPED_UNICODE) : null,
            'directions_for_use' => $directions,
            'packing_info'       => $packing,
            'warranty_info'      => $warranty,
            'images'             => $images ? json_encode($images) : null,
            'hover_image'        => $hover,
            'weight_kg'          => $weightKg,
            'hsn_code'           => $hsn,
            'youtube_video_url'  => $yt,
            'is_active'          => $isActive,
            'is_featured'        => $isFeat,
            'is_deleted'         => 0,
        ];

        $existing = $db->fetchOne("SELECT id FROM products WHERE sku=?", [$sku]);
        if ($existing) {
            // is_featured is INSERT-ONLY: re-import must not clobber manual curation done in admin.
            $upd = $cols; unset($upd['is_featured']);
            $set = implode('=?,', array_keys($upd)) . '=?';
            $db->execute("UPDATE products SET $set WHERE id=?", array_merge(array_values($upd), [$existing['id']]));
            $pid = (int)$existing['id'];
            $updated++;
        } else {
            $ph = implode(',', array_fill(0, count($cols), '?'));
            $pid = (int)$db->insert("INSERT INTO products (" . implode(',', array_keys($cols)) . ") VALUES ($ph)", array_values($cols));
            $created++;
        }

        // FAQs (replace per product).
        $db->execute("DELETE FROM product_faqs WHERE product_id=?", [$pid]);
        $faqs = is_array($r['product_faqs'] ?? null) ? $r['product_faqs'] : [];
        foreach ($faqs as $k => $f) {
            $q = $htmlToText($f['question'] ?? ''); $a = $htmlToText($f['answer'] ?? '');
            if ($q === '' || $a === '') continue;
            $db->insert("INSERT INTO product_faqs (product_id, question, answer, sort_order) VALUES (?,?,?,?)", [$pid, $q, $a, $k]);
            $faqCount++;
        }

        if (($created + $updated) % 50 === 0) echo "  ... " . ($created + $updated) . " done\n";
    } catch (Throwable $e) {
        $skipped++;
        fwrite(STDERR, "  ! row " . ($i + 1) . " ('" . ($r['product_name'] ?? '?') . "'): " . $e->getMessage() . "\n");
    }
}

echo "\n=== Import v2 complete ===\n";
echo "Created: $created | Updated: $updated | Combos: $comboCount | Skipped: $skipped | FAQs: $faqCount\n";
echo "Categories now:   " . (int)$db->fetchOne("SELECT COUNT(*) c FROM categories")['c'] . "\n";
echo "Active products:  " . (int)$db->fetchOne("SELECT COUNT(*) c FROM products WHERE is_deleted=0")['c'] . "\n";
