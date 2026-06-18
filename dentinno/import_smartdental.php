<?php
/**
 * Bulk importer for the Smart Dental Innovations catalogue (smartdental_import.json,
 * derived from SmartDental_Products.xlsx — 555 products).
 *
 * Usage (CLI only):
 *   php import_smartdental.php                 # import all (no purge)
 *   php import_smartdental.php --limit=3       # import first 3 (smoke test)
 *   php import_smartdental.php --purge         # soft-delete demo rows NOT in this file, then import all
 *   php import_smartdental.php --file=path.json
 *
 * Idempotent: upserts products by SKU (revives soft-deleted matches), so re-running is safe.
 * Mapping mirrors pages/products.php: products.price = MRP, products.discount_price = selling price.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once __DIR__ . '/includes/config.php';

$opts  = getopt('', ['file:', 'limit:', 'purge', 'help']);
if (isset($opts['help'])) { fwrite(STDERR, "See header for usage.\n"); exit(0); }
$file  = $opts['file']  ?? __DIR__ . '/smartdental_import.json';
$limit = isset($opts['limit']) ? max(0, (int)$opts['limit']) : 0;
$purge = isset($opts['purge']);

if (!is_file($file)) { fwrite(STDERR, "File not found: $file\n"); exit(1); }
$rows = json_decode(file_get_contents($file), true);
if (!is_array($rows)) { fwrite(STDERR, "Invalid JSON in $file\n"); exit(1); }

$db = db();

// ---- helpers -------------------------------------------------------------
$num = fn($s) => (float)preg_replace('/[^0-9.]/', '', (string)$s);

// Split a newline list (images), trim, keep non-empty http(s) URLs.
$urlList = function ($s) {
    $out = [];
    foreach (preg_split('/\r?\n/', (string)$s) as $u) {
        $u = trim($u);
        if ($u !== '' && preg_match('#^https?://#i', $u)) $out[] = $u;
    }
    return array_values(array_unique($out));
};

// Parse "Q: ...\nA: ..." blocks into [{question, answer}].
$parseFaqs = function ($s) {
    $s = trim((string)$s);
    if ($s === '') return [];
    $out = [];
    // Each FAQ starts at "Q:"; capture up to the next "Q:" or end.
    if (preg_match_all('/Q:\s*(.+?)\s*\n\s*A:\s*(.+?)(?=\n\s*\n\s*Q:|\n\s*Q:|$)/su', $s, $m, PREG_SET_ORDER)) {
        foreach ($m as $g) {
            $q = trim($g[1]); $a = trim($g[2]);
            if ($q !== '' && $a !== '') $out[] = ['question' => $q, 'answer' => $a];
        }
    }
    return $out;
};

// Parse "Label: value" lines (HTML stripped) into [{key,value}]. Messy/blank -> [].
$parseSpecs = function ($s) {
    $txt = trim(html_entity_decode(strip_tags((string)$s)));
    if ($txt === '') return [];
    $out = [];
    foreach (preg_split('/\r?\n/', $txt) as $line) {
        $line = trim($line);
        if ($line === '' || mb_strlen($line) > 200) continue;
        if (preg_match('/^([A-Za-z][^:]{1,50}):\s*(.+)$/u', $line, $m)) {
            $out[] = ['key' => trim($m[1]), 'value' => trim($m[2])];
        }
        if (count($out) >= 30) break;
    }
    return $out;
};

// Category resolution: first non-price-bucket tag becomes the primary category.
$catAlias = [
    'handpieces' => 'Handpiece', 'smartmed scrubs' => 'Smartmed Scrub',
    'unique product' => 'Unique Products', 'new product' => 'New Products',
];
$catCache = [];
foreach ($db->fetchAll("SELECT id, name FROM categories") as $c) {
    $catCache[mb_strtolower($c['name'])] = (int)$c['id'];
}
$resolveCat = function ($tags) use (&$catCache, $catAlias, $db) {
    foreach (explode('|', (string)$tags) as $raw) {
        $t = trim($raw);
        if ($t === '' || mb_strpos($t, '₹') !== false) continue; // skip price buckets
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

$uniqueSlug = function ($name, $sku) use ($db) {
    $slug = generateSlug($name) ?: ('product-' . strtolower($sku));
    $base = $slug; $n = 1;
    // Allow the slug to belong to the same SKU (upsert); otherwise bump.
    while (($r = $db->fetchOne("SELECT id, sku FROM products WHERE slug=?", [$slug])) && $r['sku'] !== $sku) {
        $slug = $base . '-' . (++$n);
    }
    return $slug;
};

// ---- purge demo rows -----------------------------------------------------
if ($purge) {
    $skus = array_values(array_filter(array_map(fn($r) => trim((string)($r['sku'] ?? '')), $rows)));
    if ($skus) {
        $ph = implode(',', array_fill(0, count($skus), '?'));
        $n = $db->execute("UPDATE products SET is_deleted=1, is_active=0 WHERE is_deleted=0 AND (sku IS NULL OR sku NOT IN ($ph))", $skus);
        echo "Purged (soft-deleted) demo products not in this import.\n";
    }
}

// ---- import --------------------------------------------------------------
$slice = $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
$created = $updated = $skipped = $faqCount = 0;
$usedSku = [];   // within-run de-dup for the 3 duplicate SKUs

foreach ($slice as $i => $r) {
    try {
        $name = trim((string)($r['name'] ?? ''));
        if ($name === '') { $skipped++; continue; }

        $mrp  = $num($r['mrp'] ?? '');
        $sell = $num($r['selling_price'] ?? '');
        if ($mrp <= 0)  $mrp  = $sell;          // fall back if MRP missing
        if ($sell <= 0) $sell = $mrp;
        if ($mrp <= 0)  { $skipped++; continue; } // no usable price
        $price = $mrp;
        $discPrice = ($sell > 0 && $sell < $mrp) ? $sell : null;
        $discPct   = $discPrice ? round((($price - $discPrice) / $price) * 100, 2) : 0;

        // SKU (de-dup within this run so duplicate-SKU rows both survive).
        $sku = trim((string)($r['sku'] ?? '')) ?: ('SDX-' . strtoupper(substr(md5($name), 0, 10)));
        if (isset($usedSku[$sku])) { $sku = $sku . '-' . (++$usedSku[$sku]); }
        else { $usedSku[$sku] = 1; }

        $catId = $resolveCat($r['categories'] ?? '');
        $slug  = $uniqueSlug($name, $sku);

        $shortFull = trim((string)($r['short_description'] ?? ''));
        $shortCol  = $shortFull !== '' ? mb_substr($shortFull, 0, 500) : null;
        $images    = $urlList($r['images'] ?? '');

        // "Key Specifications" (col14) is inconsistent in the source: sometimes a clean
        // "Label: value" list, sometimes rich HTML. Use the structured spec table only when
        // it's clearly a plain multi-line spec list; otherwise keep the raw content as extra
        // highlights so nothing is lost.
        $rawSpec  = trim((string)($r['key_specifications'] ?? ''));
        $specPairs = $parseSpecs($rawSpec);
        $hasHtml   = (bool)preg_match('/<[a-z]/i', $rawSpec);
        $specs = (!$hasHtml && count($specPairs) >= 2) ? $specPairs : [];
        $keyFeatures = trim((string)($r['highlights'] ?? ''));
        if (!$specs && $rawSpec !== '') {
            $keyFeatures = $keyFeatures !== '' ? ($keyFeatures . "\n" . $rawSpec) : $rawSpec;
        }
        $weightG   = 0;
        if (preg_match('/Weight:\s*([0-9.]+)/i', (string)($r['dimensions'] ?? ''), $wm)) $weightG = (float)$wm[1];
        $weightKg  = $weightG > 0 ? round($weightG / 1000, 3) : null;

        $addl = trim((string)($r['other_info'] ?? ''));
        $bulk = trim((string)($r['bulk_offers'] ?? ''));
        if ($bulk !== '') $addl = ($addl !== '' ? $addl . "\n\n" : '') . "Bulk Offers:\n" . $bulk;

        $isNew = (stripos((string)($r['categories'] ?? ''), 'new product') !== false) ? 1 : 0;

        $cols = [
            'name'                  => $name,
            'slug'                  => $slug,
            'sku'                   => $sku,
            'category_id'           => $catId,
            'price'                 => $price,
            'discount_price'        => $discPrice,
            'discount_percent'      => $discPct,
            'stock'                 => 100,
            'min_stock_alert'       => 5,
            'description'           => $shortFull !== '' ? $shortFull : null,
            'short_description'     => $shortCol,
            'full_description'      => trim((string)($r['description'] ?? '')) ?: null,
            'key_features'          => $keyFeatures ?: null,
            'key_specifications'    => $specs ? json_encode($specs) : null,
            'directions_for_use'    => trim((string)($r['directions'] ?? '')) ?: null,
            'packing_info'          => trim((string)($r['packaging'] ?? '')) ?: null,
            'additional_information'=> $addl ?: null,
            'warranty_info'         => trim((string)($r['warranty'] ?? '')) ?: null,
            'images'                => $images ? json_encode($images) : null,
            'weight_kg'             => $weightKg,
            'is_active'             => 1,
            'is_new'                => $isNew,
            'is_deleted'            => 0,
        ];

        $existing = $db->fetchOne("SELECT id FROM products WHERE sku=?", [$sku]);
        if ($existing) {
            $set = implode('=?,', array_keys($cols)) . '=?';
            $db->execute("UPDATE products SET $set WHERE id=?", array_merge(array_values($cols), [$existing['id']]));
            $pid = (int)$existing['id'];
            $updated++;
        } else {
            $ph = implode(',', array_fill(0, count($cols), '?'));
            $pid = (int)$db->insert("INSERT INTO products (" . implode(',', array_keys($cols)) . ") VALUES ($ph)", array_values($cols));
            $created++;
        }

        // FAQs (replace per product).
        $faqs = $parseFaqs($r['faqs'] ?? '');
        $db->execute("DELETE FROM product_faqs WHERE product_id=?", [$pid]);
        foreach ($faqs as $k => $f) {
            $db->insert("INSERT INTO product_faqs (product_id, question, answer, sort_order) VALUES (?,?,?,?)", [$pid, $f['question'], $f['answer'], $k]);
            $faqCount++;
        }

        if (($created + $updated) % 50 === 0) echo "  ... " . ($created + $updated) . " done\n";
    } catch (Throwable $e) {
        $skipped++;
        fwrite(STDERR, "  ! row " . ($i + 1) . " ('" . ($r['name'] ?? '?') . "'): " . $e->getMessage() . "\n");
    }
}

echo "\n=== Import complete ===\n";
echo "Created: $created | Updated: $updated | Skipped: $skipped | FAQs: $faqCount\n";
echo "Categories now: " . (int)$db->fetchOne("SELECT COUNT(*) c FROM categories")['c'] . "\n";
echo "Active products: " . (int)$db->fetchOne("SELECT COUNT(*) c FROM products WHERE is_deleted=0")['c'] . "\n";
