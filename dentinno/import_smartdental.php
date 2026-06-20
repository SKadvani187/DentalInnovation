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

// HTML -> clean plain text. The storefront renders these fields as plain text
// (whitespace-pre-line), so any markup must be stripped. Block tags become line breaks and
// <li> becomes a bullet so paragraphs/lists stay readable; entities are decoded.
$htmlToText = function ($s) {
    $s = (string)$s;
    if ($s === '') return '';
    $s = preg_replace('#<\s*br\s*/?>#i', "\n", $s);
    $s = preg_replace('#<\s*li[^>]*>#i', "\n• ", $s);
    $s = preg_replace('#<\s*/\s*(p|div|h[1-6]|tr|ul|ol|li)\s*>#i', "\n", $s);
    $s = preg_replace('#<[^>]+>#', '', $s);                 // drop any remaining well-formed tags
    // Drop a stray MALFORMED/unclosed tag (e.g. a truncated "<li style=...") that never closes:
    // a "<" + letter/slash + run with no "<" or ">" up to end-of-string. "<" before a space or
    // digit (a real "less-than") is left untouched.
    $s = preg_replace('#<\s*/?[a-zA-Z][^<>]*$#s', '', $s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace("\xc2\xa0", ' ', $s);                  // NBSP -> space
    $s = preg_replace('/[ \t]+/', ' ', $s);
    $s = preg_replace('/ *\n */', "\n", $s);
    $s = preg_replace('/\n{3,}/', "\n\n", $s);
    return trim($s);
};

// Parse a key_features blob into [{title,text}] bullets for the storefront "Product Highlights"
// box (mapProduct reads the `features` column). "Short Label: value" -> {title,text}; other
// lines -> {title:'', text}.
$parseHighlights = function ($text) {
    $text = (string)$text;
    if (trim($text) === '') return [];
    // Strip a leading bullet glyph (the list adds its own) and drop redundant generic headers.
    $bullet  = '/^[\x{2022}\x{00B7}\x{25AA}\x{25E6}\x{2023}\x{2219}\x{2043}*\-]\s+/u';
    $generic = ['key features', 'features', 'highlights', 'product highlights', 'specifications', 'key specifications', 'specs'];
    $out = [];
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $line = preg_replace($bullet, '', trim($line));
        if ($line === '') continue;
        if (preg_match('/^([A-Za-z][A-Za-z0-9 \/&\x27-]{0,38}):\s*(.+)$/', $line, $m)) {
            $title = trim($m[1]);
            $body  = preg_replace($bullet, '', trim($m[2]));
            $out[] = in_array(strtolower($title), $generic, true)
                ? ['title' => '', 'text' => $body]
                : ['title' => $title, 'text' => $body];
        } else {
            $out[] = ['title' => '', 'text' => $line];
        }
    }
    return $out;
};

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

// Sanitise HTML for storage: keep only the structural tags RichText/DOMPurify renders, drop all
// attributes (data-*, style, class bloat) except href on <a>, and remove empty wrappers. Keeps
// lists/bold/headers so the storefront shows the same structure as the source.
$cleanHtml = function ($s) {
    $s = (string)$s;
    if (trim($s) === '') return '';
    $s = strip_tags($s, '<p><br><b><strong><i><em><u><ul><ol><li><h3><h4><h5><h6><a><div><span>');
    $s = preg_replace_callback('#<([a-zA-Z][a-z0-9]*)\b[^>]*>#i', function ($m) {
        $tag = strtolower($m[1]);
        if ($tag === 'a' && preg_match('#href\s*=\s*("[^"]*"|\'[^\']*\')#i', $m[0], $h)) {
            return '<a href=' . $h[1] . ' target="_blank" rel="noopener noreferrer">';
        }
        return '<' . $tag . '>';
    }, $s);
    $s = preg_replace('#<(p|li|h[3-6]|strong|b|em|i|u)>\s*</\1>#i', '', $s);  // empty wrappers
    $s = preg_replace('#(\s*<br>\s*){2,}#i', '<br>', $s);
    $s = preg_replace('/[ \t]{2,}/', ' ', $s);
    $s = trim($s);
    // If nothing but whitespace/markup is left, treat as empty.
    return trim(strip_tags($s)) === '' ? '' : $s;
};

// Junk "labels" that are auto-generated metadata, never real highlights/specs (dropped).
$JUNK_LABELS = ['product name','price','price inr','price in inr','mrp','discount','category',
    'brand','short description','availability','sku','seller','hsn','gst','usage',
    'warranty','warranty & support','warranty and support','support & warranty','support'];
// Labels whose "Label: value" / "Label - value" lines belong in Key Specifications.
$SPEC_LABELS = ['dimensions','weight','net weight','gross weight','material','materials','type',
    'product type','file type','size','sizes','length','taper','interface type','motion','diameter',
    'colour','color','gear ratio','head type','chuck type','speed','torque','power','voltage',
    'frequency','capacity','model','model no','range','resolution','sensor','battery','warranty period'];

// Classify content into Key Specifications [{key,value}] vs Product Highlights bullets [{title,text}].
// $specBlobs = the dedicated Key Specifications column (col14) + spec sections from other_info:
//   EVERY "Label: value" / "Label - value" line with a short value is a spec (no whitelist), so a
//   product's full spec list survives like the reference site. $highlightBlobs = the highlights
//   column (col12) + other_info feature sections: junk metadata dropped, only whitelisted labels
//   become specs, the rest are bullets. Captures any embedded "Description:".
$classifyMeta = function (array $specBlobs, array $highlightBlobs) use ($JUNK_LABELS, $SPEC_LABELS) {
    $bullet  = '/^[\x{2022}\x{00B7}\x{25AA}\x{25E6}\x{2023}\x{2219}\x{2043}*\-]\s+/u';
    $generic = ['key features', 'features', 'highlights', 'product highlights', 'specifications', 'key specifications', 'specs'];
    $toText  = fn($s) => trim(html_entity_decode(strip_tags(preg_replace('#<\s*/?(br|p|li|div|h[1-6]|ul|ol|tr)\b[^>]*>#i', "\n", (string)$s)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    // "Label: value" (colon may be tight) OR "Label - value" (dash needs surrounding spaces so
    // hyphenated labels like "Anti-Microbial" aren't split). Captures label + value.
    $LV = '/^([A-Za-z][A-Za-z0-9 ()\/&\x27.+%-]{0,48}?)(?:\s*:\s*|\s+[-\x{2013}\x{2014}]\s+)(.+)$/u';
    $specs = []; $bullets = []; $desc = ''; $seen = [];
    $addSpec = function ($k, $v) use (&$specs, &$seen) {
        $kl = strtolower(trim($k));
        if (trim($k) !== '' && trim($v) !== '' && !isset($seen[$kl])) { $specs[] = ['key' => trim($k), 'value' => trim($v)]; $seen[$kl] = 1; }
    };

    // Spec column: any short "Label: value" pair is a spec. Generic headers / long prose fall
    // through to bullets; junk metadata is dropped; an embedded "Description:" is captured.
    foreach ($specBlobs as $blob) {
        foreach (preg_split('/\r?\n/', $toText($blob)) as $line) {
            $line = preg_replace($bullet, '', trim($line));
            if ($line === '') continue;
            if (preg_match($LV, $line, $m) && mb_strlen($m[2]) <= 90) {
                $lbl = strtolower(trim($m[1]));
                if (in_array($lbl, $JUNK_LABELS, true)) continue;
                if (in_array($lbl, ['description', 'descriptions'], true)) { if ($desc === '') $desc = trim($m[2]); continue; }
                if (in_array($lbl, $generic, true)) { if (trim($m[2]) !== '') $bullets[] = ['title' => trim($m[1]), 'text' => trim($m[2])]; continue; }
                $addSpec($m[1], $m[2]);
            } else {
                $bullets[] = ['title' => '', 'text' => $line];
            }
        }
    }

    // Highlight column: junk dropped; only whitelisted labels become specs; everything else bullets.
    foreach ($highlightBlobs as $blob) {
        foreach (preg_split('/\r?\n/', $toText($blob)) as $line) {
            $line = preg_replace($bullet, '', trim($line));
            if ($line === '') continue;
            if (preg_match('/^([A-Za-z][A-Za-z0-9 \/&\x27()-]{0,38}):\s*(.*)$/u', $line, $m)) {
                $lbl = strtolower(trim($m[1])); $val = preg_replace($bullet, '', trim($m[2]));
                if (in_array($lbl, $JUNK_LABELS, true)) continue;
                if (in_array($lbl, ['description', 'descriptions'], true) && $val !== '') { if ($desc === '') $desc = $val; continue; }
                if (in_array($lbl, $SPEC_LABELS, true) && $val !== '' && mb_strlen($val) <= 80) { $addSpec($m[1], $val); continue; }
                if (in_array($lbl, $generic, true) && $val === '') continue;   // lone "Key Features:" header — skip
                if ($val === '') { $bullets[] = ['title' => '', 'text' => trim($m[1])]; continue; }
                $bullets[] = ['title' => trim($m[1]), 'text' => $val];   // keep the label as a bold title (e.g. "Key Features:")
            } else {
                $bullets[] = ['title' => '', 'text' => $line];
            }
        }
    }
    return ['specs' => array_slice($specs, 0, 40), 'bullets' => array_slice($bullets, 0, 25), 'desc' => $desc];
};

// Highlights text -> [{title,text}] bullets for the "Product Highlights" box. Strips a leading
// bullet glyph, drops junk-metadata lines, and drops redundant generic headers.
$htmlToBullets = function ($text) use ($JUNK_LABELS) {
    $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>', '</div>'], "\n", (string)$text)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($text === '') return [];
    $bullet  = '/^[\x{2022}\x{00B7}\x{25AA}\x{25E6}\x{2023}\x{2219}\x{2043}*\-]\s+/u';
    $generic = ['key features', 'features', 'highlights', 'product highlights', 'specifications', 'key specifications', 'specs'];
    $out = [];
    foreach (preg_split('/\r?\n/', $text) as $line) {
        $line = preg_replace($bullet, '', trim($line));
        if ($line === '') continue;
        if (preg_match('/^([A-Za-z][A-Za-z0-9 \/&\x27-]{0,38}):\s*(.*)$/', $line, $m)) {
            $title = trim($m[1]); $body = preg_replace($bullet, '', trim($m[2]));
            if (in_array(strtolower($title), $JUNK_LABELS, true)) continue;        // drop junk line
            if ($body === '') { $out[] = ['title' => '', 'text' => $title]; continue; }
            $out[] = in_array(strtolower($title), $generic, true)
                ? ['title' => '', 'text' => $body]
                : ['title' => $title, 'text' => $body];
        } else {
            $out[] = ['title' => '', 'text' => $line];
        }
    }
    return $out;
};

// Pull a labelled section's body out of a blob (for mashed columns where e.g. the description
// lives under a "Descriptions:" prefix). Returns '' if no matching label is found.
$extractLabel = function ($text, array $labels) {
    $text = (string)$text;
    if (trim($text) === '') return '';
    // Allow the label to sit at start, after a newline, or right after a tag (>).
    foreach ($labels as $lbl) {
        if (preg_match('#(?:^|\n|>)\s*' . preg_quote($lbl, '#') . '\s*:\s*#i', $text, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1] + strlen($m[0][0]);
            // Body runs until the next "Label:" marker or end.
            $rest = substr($text, $start);
            if (preg_match('#(?:^|\n|>)\s*[A-Z][A-Za-z0-9 .&\x27/()-]{1,44}?\s*:\s#', $rest, $m2, PREG_OFFSET_CAPTURE)) {
                $rest = substr($rest, 0, $m2[0][1]);
            }
            return trim($rest);
        }
    }
    return '';
};

// Parse the bulk_offers column ("Buy 2+ : ₹15400 each (7% off vs unit price)\n…") into per-product
// quantity tiers [{minQty, rate, label}]. rate is derived from the explicit tier price vs the unit
// (selling) price so the storefront shows the exact same ₹ as the source. Non-discount tiers skipped.
$parseBulkOffers = function ($s, $unit) {
    $s = html_entity_decode((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (trim($s) === '' || $unit <= 0) return [];
    $out = []; $seen = [];
    foreach (preg_split('/\r?\n/', $s) as $line) {
        if (preg_match('/Buy\s*(\d+)\s*\+?\s*:\s*\x{20B9}?\s*([\d,]+(?:\.\d+)?)\s*each/iu', $line, $m)) {
            $minQty = (int)$m[1];
            $tierPrice = (float)str_replace(',', '', $m[2]);
            if ($minQty < 2 || $tierPrice <= 0 || $tierPrice >= $unit || isset($seen[$minQty])) continue;
            $seen[$minQty] = 1;
            $out[] = ['minQty' => $minQty, 'rate' => round(($unit - $tierPrice) / $unit, 6), 'label' => 'Buy ' . $minQty . ' or above'];
        }
    }
    usort($out, fn($a, $b) => $a['minQty'] - $b['minQty']);
    return $out;
};

// Route a section label to a bucket. Mirrors the storefront's section set.
$routeLabel = function ($label) {
    static $map = null;
    if ($map === null) {
        $map = [];
        $add = function ($b, $a) use (&$map) { foreach ($a as $k) $map[$k] = $b; };
        $add('JUNK', ['product name','price','price inr','price in inr','mrp','discount','category','brand','short description','availability','sku','seller','hsn','gst','bulk offers','bulk offer','offers','offer']);
        $add('DESC', ['description','descriptions','product description','overview','product overview','about','about product']);
        $add('DIR',  ['how to use','how to use it','direction of use','directions of use','direction to use','directions to use','directions for use','directions','direction','usage','use','intended use','instructions for use','instructions','method of use','steps','procedure','application','indications','indication']);
        $add('PACK', ['packaging info','packaging information','packaging','package contents','package content','contents','what\'s in the box','in the box','box contents','package','package includes','kit contents','set includes']);
        $add('WARR', ['warranty','warranty & support','warranty and support','support & warranty','warranty info','warranty information','warranty & service','warranty details']);
        $add('SPEC', ['technical specifications','technical specification','specifications','specification','key specifications','key specification','product specifications','specs']);
    }
    return $map[strtolower(trim($label))] ?? 'FEAT';
};

// Split a (cleaned) HTML/text blob into [{label, body}] sections by line-leading or post-tag
// "Label:" markers, keeping each body's HTML intact. Used for the other_info grab-bag column.
$splitHtmlSections = function ($html) {
    $html = trim((string)$html);
    if ($html === '') return [];
    $pat = '/(?:^|>|\n)\s*([A-Z][A-Za-z0-9 &\/\x27()-]{1,40}?)\s*:\s*/';
    if (!preg_match_all($pat, $html, $m, PREG_OFFSET_CAPTURE)) return [['label' => '', 'body' => $html]];
    $out = []; $n = count($m[0]);
    if (($pre = trim(substr($html, 0, $m[0][0][1]))) !== '') $out[] = ['label' => '', 'body' => $pre];
    for ($i = 0; $i < $n; $i++) {
        // Start body right after the matched "Label:" (the match may include a leading > or \n).
        $lblPos = strpos($html, $m[1][$i][0], $m[0][$i][1]);
        $bodyStart = $lblPos + strlen($m[1][$i][0]);
        $bodyStart = strpos($html, ':', $bodyStart) + 1;
        $bodyEnd = ($i + 1 < $n) ? $m[0][$i + 1][1] : strlen($html);
        $out[] = ['label' => trim($m[1][$i][0]), 'body' => trim(substr($html, $bodyStart, $bodyEnd - $bodyStart))];
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
$created = $updated = $skipped = $faqCount = $comboCount = 0;
$usedSku = [];   // within-run de-dup for the 3 duplicate SKUs

foreach ($slice as $i => $r) {
    try {
        $name = trim((string)($r['name'] ?? ''));
        if ($name === '') { $skipped++; continue; }

        // Categories carry HTML entities from the source (e.g. "&#8377;999" for ₹999); decode so
        // the price-bucket skip (which looks for ₹) works and no junk category is created.
        $r['categories'] = html_entity_decode((string)($r['categories'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

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

        // Detect the primary (first non-price-bucket) category so combo-category items can be
        // routed to the Combos table instead of the product catalog.
        $primaryCatName = '';
        foreach (explode('|', (string)($r['categories'] ?? '')) as $raw) {
            $t = trim($raw);
            if ($t === '' || mb_strpos($t, '₹') !== false) continue;
            $primaryCatName = $catAlias[mb_strtolower($t)] ?? $t;
            break;
        }
        $isCombo = mb_strtolower($primaryCatName) === 'combo';

        $shortFull = $htmlToText($r['short_description'] ?? '');
        $shortCol  = $shortFull !== '' ? mb_substr($shortFull, 0, 500) : null;
        $images    = $urlList($r['images'] ?? '');

        // --- Rich content fields: keep the source HTML (rendered via RichText) so the storefront
        // shows the same structure as the reference (numbered steps, nested bullets, bold). Each
        // section maps from its own column; the other_info grab-bag (col18) is split by label and
        // routed into whichever section is still empty, plus extra features/specs. ----------------
        $fullDesc   = $cleanHtml($r['description'] ?? '');   // col17
        $directions = $cleanHtml($r['directions'] ?? '');    // col15
        $packing    = $cleanHtml($r['packaging'] ?? '');     // col16
        $warranty   = $htmlToText($r['warranty'] ?? '');     // col13 (short)
        if (in_array(strtolower(trim($warranty)), ['', 'no', 'na', 'n/a', 'none', '-', 'not specified'], true)) $warranty = '';

        $otherBullets = []; $otherSpecText = '';
        foreach ($splitHtmlSections($cleanHtml($r['other_info'] ?? '')) as $sec) {
            $body = trim($sec['body']);
            if ($body === '' || trim(strip_tags($body)) === '') continue;
            switch ($routeLabel($sec['label'])) {
                case 'JUNK': break;
                case 'DESC': if ($fullDesc === '')   $fullDesc   = $body; break;
                case 'DIR':  if ($directions === '') $directions = $body; break;
                case 'PACK': if ($packing === '')    $packing    = $body; break;
                case 'WARR': if ($warranty === '')   $warranty   = trim(strip_tags($body)); break;
                case 'SPEC': $otherSpecText .= "\n" . $body; break;
                default:     $otherBullets[] = $body;   // FEAT -> bulletised below
            }
        }

        // Key Specifications + Product Highlights, classified out of the highlights (col12) and
        // key_specifications (col14) columns plus any feature/spec sections found in other_info:
        // specs -> {key,value} table, real features -> bullets, junk dropped, "Description:" -> desc.
        $meta = $classifyMeta(
            [$r['key_specifications'] ?? '', $otherSpecText],         // Key Specifications column + other_info spec sections
            [$r['highlights'] ?? '', implode("\n", $otherBullets)]    // highlights column + other_info feature sections
        );
        $specs      = $meta['specs'];
        $highlights = $meta['bullets'];
        if ($fullDesc === '' && $meta['desc'] !== '') $fullDesc = nl2br(htmlspecialchars($meta['desc'], ENT_QUOTES));

        $weightG   = 0;
        if (preg_match('/Weight:\s*([0-9.]+)/i', (string)($r['dimensions'] ?? ''), $wm)) $weightG = (float)$wm[1];
        $weightKg  = $weightG > 0 ? round($weightG / 1000, 3) : null;

        $isNew = (stripos((string)($r['categories'] ?? ''), 'new product') !== false) ? 1 : 0;

        // Combo-category items are bundle products — store them as Combos (storefront Combos page),
        // not in the products catalog. Just the card (name/image/price/mrp); no item breakdown.
        if ($isCombo) {
            $comboCols = [
                'slug'             => $slug,
                'name'             => $name,
                'description'      => $shortFull !== '' ? $shortFull : null,
                'mrp'              => $price,
                'price'            => $discPrice ?? $price,
                'discount_percent' => $discPct,
                'image'            => $images[0] ?? null,
                'images'           => $images ? json_encode($images) : null,
                'items'            => '[]',
                'in_stock'         => 1,
                'stock'            => 100,
                'is_active'        => 1,
                'is_deleted'       => 0,
            ];
            $exC = $db->fetchOne("SELECT id FROM combos WHERE slug=?", [$slug]);
            if ($exC) {
                $setC = implode('=?,', array_keys($comboCols)) . '=?';
                $db->execute("UPDATE combos SET $setC WHERE id=?", array_merge(array_values($comboCols), [$exC['id']]));
            } else {
                $phC = implode(',', array_fill(0, count($comboCols), '?'));
                $db->insert("INSERT INTO combos (" . implode(',', array_keys($comboCols)) . ") VALUES ($phC)", array_values($comboCols));
            }
            $comboCount++;
            continue;
        }

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
            'full_description'      => $fullDesc ?: null,
            'key_features'          => null,   // the Product Highlights box (features) shows these now
            'features'              => $highlights ? json_encode($highlights, JSON_UNESCAPED_UNICODE) : null,
            'key_specifications'    => $specs ? json_encode($specs) : null,
            'bulk_offers'           => ($bo = $parseBulkOffers($r['bulk_offers'] ?? '', $discPrice ?? $price)) ? json_encode($bo) : null,
            'directions_for_use'    => $directions ?: null,
            'packing_info'          => $packing ?: null,
            'additional_information'=> null,   // redistributed into the proper sections (matches reference)
            'warranty_info'         => $warranty ?: null,
            'images'                => $images ? json_encode($images) : null,
            'weight_kg'             => $weightKg,
            'is_active'             => 1,
            'is_new'                => $isNew,
            'is_deleted'            => 0,
        ];

        $existing = $db->fetchOne("SELECT id FROM products WHERE sku=?", [$sku]);
        if ($existing) {
            // is_new is INSERT-ONLY: re-importing must not clobber manual "New Arrivals" curation
            // done in admin (the same applies to is_featured, which isn't in $cols at all).
            $upd = $cols; unset($upd['is_new']);
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
        $faqs = $parseFaqs($r['faqs'] ?? '');
        $db->execute("DELETE FROM product_faqs WHERE product_id=?", [$pid]);
        foreach ($faqs as $k => $f) {
            $q = $htmlToText($f['question']); $a = $htmlToText($f['answer']);
            if ($q === '' || $a === '') continue;
            $db->insert("INSERT INTO product_faqs (product_id, question, answer, sort_order) VALUES (?,?,?,?)", [$pid, $q, $a, $k]);
            $faqCount++;
        }

        if (($created + $updated) % 50 === 0) echo "  ... " . ($created + $updated) . " done\n";
    } catch (Throwable $e) {
        $skipped++;
        fwrite(STDERR, "  ! row " . ($i + 1) . " ('" . ($r['name'] ?? '?') . "'): " . $e->getMessage() . "\n");
    }
}

echo "\n=== Import complete ===\n";
echo "Created: $created | Updated: $updated | Combos: $comboCount | Skipped: $skipped | FAQs: $faqCount\n";
echo "Categories now: " . (int)$db->fetchOne("SELECT COUNT(*) c FROM categories")['c'] . "\n";
echo "Active products: " . (int)$db->fetchOne("SELECT COUNT(*) c FROM products WHERE is_deleted=0")['c'] . "\n";
