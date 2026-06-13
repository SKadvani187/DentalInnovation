<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Products';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    // Surface the real DB error (e.g. "Unknown column …" when a migration is missing)
    // instead of letting a PDOException corrupt the JSON response and show a generic
    // "Save failed" in the UI. Run `php migrate.php` if you see an Unknown column error.
    try {

    if ($action === 'delete') {
        // Soft-delete: keep the row so order history / invoices stay intact; hide it everywhere.
        db()->execute("UPDATE products SET is_deleted = 1, is_active = 0 WHERE id = ?", [(int)($data['id'] ?? 0)]);
        echo json_encode(['success' => true, 'message' => 'Product deleted']);
    } elseif ($action === 'restore') {
        db()->execute("UPDATE products SET is_deleted = 0 WHERE id = ?", [(int)($data['id'] ?? 0)]);
        echo json_encode(['success' => true, 'message' => 'Product restored']);
    } elseif ($action === 'toggle') {
        db()->execute("UPDATE products SET is_active = NOT is_active WHERE id = ?", [$data['id']]);
        echo json_encode(['success' => true, 'message' => 'Status updated']);
    } elseif ($action === 'save') {
        $d = $data;
        // --- Server-side validation (never trust the client form) ---
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '')                                                 { echo json_encode(['success'=>false,'message'=>'Product name is required']); exit; }
        if (!is_numeric($d['price'] ?? null) || (float)$d['price'] <= 0)   { echo json_encode(['success'=>false,'message'=>'Price must be greater than 0']); exit; }
        if (!is_numeric($d['stock'] ?? null) || (int)$d['stock'] < 0)      { echo json_encode(['success'=>false,'message'=>'Stock must be 0 or more']); exit; }
        $price      = (float)$d['price'];
        $disc_price = (!empty($d['discount_price']) && is_numeric($d['discount_price'])) ? (float)$d['discount_price'] : null;
        if ($disc_price !== null && $disc_price >= $price)                 { echo json_encode(['success'=>false,'message'=>'Discount price must be less than the price']); exit; }
        $disc_pct   = ($disc_price && $price > 0) ? round((($price - $disc_price) / $price) * 100, 2) : 0;

        $features   = !empty($d['features']) ? json_encode($d['features']) : null;
        $key_specs  = !empty($d['key_specifications']) ? json_encode($d['key_specifications']) : null;
        $images_json = !empty($d['images']) ? json_encode($d['images']) : null;
        $hover_image = !empty($d['hover_image']) ? $d['hover_image'] : null;

        // Variants: keep only valid {label, price, mrp}; auto-compute discount %. Stored in the
        // exact JSON shape the storefront reads: [{label,price,mrp,discount}].
        $variants = null;
        if (!empty($d['variants']) && is_array($d['variants'])) {
            $clean = [];
            foreach ($d['variants'] as $v) {
                $vl = trim((string)($v['label'] ?? ''));
                $vp = (float)($v['price'] ?? 0);
                $vm = (float)($v['mrp'] ?? 0);
                if ($vl === '' || $vp <= 0) continue;
                if ($vm < $vp) $vm = $vp;
                $clean[] = ['label'=>$vl, 'price'=>$vp, 'mrp'=>$vm, 'discount'=> $vm > 0 ? (int)round((($vm - $vp) / $vm) * 100) : 0];
            }
            $variants = $clean ? json_encode($clean) : null;
        }

        // Slug: admin-editable, falls back to the name; guaranteed unique (UNIQUE column).
        $selfId   = (int)($d['id'] ?? 0);
        $slug     = generateSlug(trim((string)($d['slug'] ?? '')) !== '' ? $d['slug'] : $name) ?: 'product';
        $slugBase = $slug; $n = 1;
        while (db()->fetchOne("SELECT id FROM products WHERE slug=? AND id<>?", [$slug, $selfId])) { $slug = $slugBase . '-' . (++$n); }

        $metaTitle = trim((string)($d['meta_title'] ?? '')) ?: null;
        $metaDesc  = trim((string)($d['meta_description'] ?? '')) ?: null;
        $stock     = (int)$d['stock'];
        $minStock  = max(0, (int)($d['min_stock_alert'] ?? 5));   // low-stock alert threshold

        if (!empty($d['id'])) {
            db()->execute("UPDATE products SET name=?,slug=?,meta_title=?,meta_description=?,category_id=?,price=?,discount_price=?,discount_percent=?,stock=?,min_stock_alert=?,short_description=?,full_description=?,features=?,packing_info=?,key_specifications=?,directions_for_use=?,additional_information=?,warranty_info=?,key_features=?,warranty_no=?,direction_of_use=?,catalogue_url=?,images=?,hover_image=?,variants=?,weight_kg=?,shipping_method_id=?,is_active=?,is_featured=?,is_new=? WHERE id=?",
                [$name,$slug,$metaTitle,$metaDesc,($d['category_id'] ?? '')?:null,$price,$disc_price,$disc_pct,$stock,$minStock,($d['short_description']??null),($d['full_description']??null),$features,($d['packing_info']??null),$key_specs,($d['directions_for_use']??null),($d['additional_information']??null),($d['warranty_info']??null),($d['key_features'] ?? '')?:null,($d['warranty_no'] ?? '')?:null,($d['direction_of_use'] ?? '')?:null,($d['catalogue_url'] ?? '')?:null,$images_json,$hover_image,$variants,($d['weight_kg'] ?? '')?:null,(!empty($d['shipping_method_id'])?(int)$d['shipping_method_id']:null),$d['is_active']??1,$d['is_featured']??0,$d['is_new']??0,$d['id']]);
            $pid = $d['id'];
            echo json_encode(['success' => true, 'message' => 'Product updated', 'id' => $pid]);
        } else {
            $sku  = 'SKU-' . strtoupper(substr(md5($name . microtime()), 0, 6));
            $pid = db()->insert("INSERT INTO products (name,slug,meta_title,meta_description,sku,category_id,price,discount_price,discount_percent,stock,min_stock_alert,short_description,full_description,features,packing_info,key_specifications,directions_for_use,additional_information,warranty_info,key_features,warranty_no,direction_of_use,catalogue_url,images,hover_image,variants,weight_kg,shipping_method_id,is_active,is_featured,is_new) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$name,$slug,$metaTitle,$metaDesc,$sku,($d['category_id'] ?? '')?:null,$price,$disc_price,$disc_pct,$stock,$minStock,($d['short_description']??null),($d['full_description']??null),$features,($d['packing_info']??null),$key_specs,($d['directions_for_use']??null),($d['additional_information']??null),($d['warranty_info']??null),($d['key_features'] ?? '')?:null,($d['warranty_no'] ?? '')?:null,($d['direction_of_use'] ?? '')?:null,($d['catalogue_url'] ?? '')?:null,$images_json,$hover_image,$variants,($d['weight_kg'] ?? '')?:null,(!empty($d['shipping_method_id'])?(int)$d['shipping_method_id']:null),$d['is_active']??1,$d['is_featured']??0,$d['is_new']??0]);
            echo json_encode(['success' => true, 'message' => 'Product added', 'id' => $pid]);
        }
        if (isset($d['faqs']) && $pid) {
            db()->execute("DELETE FROM product_faqs WHERE product_id = ?", [$pid]);
            foreach ($d['faqs'] as $i => $faq) {
                if (!empty($faq['question']) && !empty($faq['answer'])) {
                    db()->insert("INSERT INTO product_faqs (product_id,question,answer,sort_order) VALUES (?,?,?,?)", [$pid,$faq['question'],$faq['answer'],$i]);
                }
            }
        }
        // Frequently Bought Together (per-product related products).
        if (isset($d['fbt']) && $pid) {
            db()->execute("DELETE FROM product_fbt WHERE product_id = ?", [$pid]);
            foreach ((array)$d['fbt'] as $i => $fid) {
                $fid = (int)$fid;
                if ($fid > 0 && $fid !== (int)$pid) {  // can't suggest itself
                    db()->query("INSERT IGNORE INTO product_fbt (product_id,fbt_product_id,sort_order) VALUES (?,?,?)", [$pid,$fid,$i]);
                }
            }
        }
        // Free gifts (per-product): buy this product, get these products free.
        if (isset($d['gifts']) && $pid) {
            db()->execute("DELETE FROM product_gifts WHERE product_id = ?", [$pid]);
            foreach ((array)$d['gifts'] as $i => $gid) {
                $gid = (int)$gid;
                if ($gid > 0 && $gid !== (int)$pid) {  // can't gift itself
                    db()->query("INSERT IGNORE INTO product_gifts (product_id,gift_product_id,sort_order) VALUES (?,?,?)", [$pid,$gid,$i]);
                }
            }
        }
    } elseif ($action === 'get_faqs') {
        $faqs = db()->fetchAll("SELECT * FROM product_faqs WHERE product_id=? ORDER BY sort_order", [$data['product_id']]);
        echo json_encode(['success' => true, 'faqs' => $faqs]);
    } elseif ($action === 'get_fbt') {
        $fbt = db()->fetchAll("SELECT fbt_product_id FROM product_fbt WHERE product_id=? ORDER BY sort_order", [$data['product_id']]);
        echo json_encode(['success' => true, 'fbt' => array_map(fn($r) => (int)$r['fbt_product_id'], $fbt)]);
    } elseif ($action === 'get_gifts') {
        $g = db()->fetchAll("SELECT gift_product_id FROM product_gifts WHERE product_id=? ORDER BY sort_order", [$data['product_id']]);
        echo json_encode(['success' => true, 'gifts' => array_map(fn($r) => (int)$r['gift_product_id'], $g)]);
    } elseif ($action === 'get_reviews') {
        $reviews = db()->fetchAll("SELECT * FROM product_reviews WHERE product_id=? ORDER BY created_at DESC", [$data['product_id']]);
        echo json_encode(['success' => true, 'reviews' => $reviews]);
    } elseif ($action === 'approve_review') {
        db()->execute("UPDATE product_reviews SET is_approved=? WHERE id=?", [$data['approved'],$data['id']]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'delete_review') {
        db()->execute("DELETE FROM product_reviews WHERE id=?", [$data['id']]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array)($data['ids'] ?? []))));
        $op  = (string)($data['op'] ?? '');
        if (!$ids) { echo json_encode(['success'=>false,'message'=>'No products selected']); exit; }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($op === 'activate')         { db()->execute("UPDATE products SET is_active=1 WHERE id IN ($ph)", $ids); $msg = count($ids).' product(s) activated'; }
        elseif ($op === 'deactivate')   { db()->execute("UPDATE products SET is_active=0 WHERE id IN ($ph)", $ids); $msg = count($ids).' product(s) deactivated'; }
        elseif ($op === 'delete')       { db()->execute("UPDATE products SET is_deleted=1, is_active=0 WHERE id IN ($ph)", $ids); $msg = count($ids).' product(s) deleted'; }
        elseif ($op === 'restore')      { db()->execute("UPDATE products SET is_deleted=0 WHERE id IN ($ph)", $ids); $msg = count($ids).' product(s) restored'; }
        else { echo json_encode(['success'=>false,'message'=>'Unknown bulk action']); exit; }
        echo json_encode(['success'=>true, 'message'=>$msg]);
    }

    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
    }
    exit;
}

// Catalogue PDF upload (product Content tab). Stored under assets/catalogues/.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['catalogue_pdf'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    $dir = __DIR__ . '/../assets/catalogues/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $f = $_FILES['catalogue_pdf'];
    if ($f['error'] !== UPLOAD_ERR_OK) { echo json_encode(['success'=>false,'message'=>'Upload error (code '.$f['error'].')']); exit; }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') { echo json_encode(['success'=>false,'message'=>'Only PDF files are allowed']); exit; }
    $fi = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mime = $fi ? finfo_file($fi, $f['tmp_name']) : '';
    if ($mime && $mime !== 'application/pdf') { echo json_encode(['success'=>false,'message'=>'The file is not a valid PDF (content check failed)']); exit; }
    if ($f['size'] > 15*1024*1024) { echo json_encode(['success'=>false,'message'=>'File too large (max 15MB)']); exit; }
    $fname = 'catalogue_' . time() . '_' . rand(1000,9999) . '.pdf';
    if (move_uploaded_file($f['tmp_name'], $dir . $fname)) {
        echo json_encode(['success'=>true,'url'=> APP_URL.'/assets/catalogues/'.$fname]);
    } else { echo json_encode(['success'=>false,'message'=>'Could not save the PDF']); }
    exit;
}

// Bulk CSV import — upsert products by SKU. Columns (header row, case-insensitive):
// Name, Price, Stock are required; SKU, Category, Discount Price, Active are optional.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['products_csv'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    $f = $_FILES['products_csv'];
    if ($f['error'] !== UPLOAD_ERR_OK) { echo json_encode(['success'=>false,'message'=>'Upload error (code '.$f['error'].')']); exit; }
    if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'csv') { echo json_encode(['success'=>false,'message'=>'Please upload a .csv file']); exit; }
    $fh = fopen($f['tmp_name'], 'r');
    if (!$fh) { echo json_encode(['success'=>false,'message'=>'Could not read the file']); exit; }
    $header = fgetcsv($fh);
    if (!$header) { echo json_encode(['success'=>false,'message'=>'The CSV is empty']); exit; }
    $idx = [];
    foreach ($header as $i => $hcol) $idx[strtolower(trim((string)$hcol))] = $i;
    foreach (['name','price','stock'] as $col) {
        if (!isset($idx[$col])) { echo json_encode(['success'=>false,'message'=>"CSV is missing a required column: $col"]); exit; }
    }
    $catMap = [];
    foreach (db()->fetchAll("SELECT id, LOWER(name) n FROM categories") as $c) $catMap[$c['n']] = $c['id'];
    $get = function(array $row, ?int $i): string { return ($i !== null && isset($row[$i])) ? trim((string)$row[$i]) : ''; };
    $created = 0; $updated = 0; $skipped = 0;
    while (($row = fgetcsv($fh)) !== false) {
        try {
            $name  = $get($row, $idx['name'] ?? null);
            $price = $get($row, $idx['price'] ?? null);
            $stock = $get($row, $idx['stock'] ?? null);
            if ($name === '' || !is_numeric($price) || (float)$price <= 0 || !is_numeric($stock)) { $skipped++; continue; }
            $sku   = $get($row, $idx['sku'] ?? null);
            $catId = $catMap[strtolower($get($row, $idx['category'] ?? null))] ?? null;
            $discRaw = $get($row, $idx['discount price'] ?? null);
            $disc  = (is_numeric($discRaw) && (float)$discRaw > 0 && (float)$discRaw < (float)$price) ? (float)$discRaw : null;
            $discPct = $disc ? round((((float)$price - $disc) / (float)$price) * 100, 2) : 0;
            $activeRaw = strtolower($get($row, $idx['active'] ?? null));
            $active = ($activeRaw === '' ) ? 1 : (in_array($activeRaw, ['1','active','yes','true'], true) ? 1 : 0);
            $existing = $sku !== '' ? db()->fetchOne("SELECT id FROM products WHERE sku=?", [$sku]) : null;
            if ($existing) {
                db()->execute("UPDATE products SET name=?,category_id=?,price=?,discount_price=?,discount_percent=?,stock=?,is_active=? WHERE id=?",
                    [$name, $catId, (float)$price, $disc, $discPct, (int)$stock, $active, $existing['id']]);
                $updated++;
            } else {
                $slug = generateSlug($name) ?: 'product'; $base = $slug; $n = 1;
                while (db()->fetchOne("SELECT id FROM products WHERE slug=?", [$slug])) $slug = $base.'-'.(++$n);
                $newSku = $sku !== '' ? $sku : 'SKU-'.strtoupper(substr(md5($name.microtime()), 0, 6));
                if (db()->fetchOne("SELECT id FROM products WHERE sku=?", [$newSku])) $newSku .= '-'.rand(100,999);
                db()->insert("INSERT INTO products (name,slug,sku,category_id,price,discount_price,discount_percent,stock,is_active) VALUES (?,?,?,?,?,?,?,?,?)",
                    [$name, $slug, $newSku, $catId, (float)$price, $disc, $discPct, (int)$stock, $active]);
                $created++;
            }
        } catch (Throwable $e) { error_log('CSV import row: '.$e->getMessage()); $skipped++; }
    }
    fclose($fh);
    echo json_encode(['success'=>true, 'message'=>"Import complete — $created created, $updated updated, $skipped skipped"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['product_image'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    $upload_dir = __DIR__ . '/../assets/images/products/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $file = $_FILES['product_image'];
    // Check PHP's own upload status FIRST. A file bigger than upload_max_filesize arrives
    // with error=1, empty tmp_name and size=0 (so the size check below would wrongly pass),
    // then move_uploaded_file() fails with a misleading generic "Upload failed".
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit (upload_max_filesize). Increase it in php.ini / .htaccess.',
            UPLOAD_ERR_FORM_SIZE  => 'File is too large.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded — please try again.',
            UPLOAD_ERR_NO_FILE    => 'No file was received.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server is missing its temporary upload folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server could not write the upload to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
        ];
        echo json_encode(['success'=>false,'message'=> $msgs[$file['error']] ?? ('Upload error (code '.$file['error'].')')]); exit;
    }
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) { echo json_encode(['success'=>false,'message'=>'Invalid file type']); exit; }
    // Verify the actual file CONTENT, not just the extension (a .jpg could be a PHP script).
    $fi = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mime = $fi ? finfo_file($fi, $file['tmp_name']) : '';
    if ($mime && !in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true)) {
        echo json_encode(['success'=>false,'message'=>'The file is not a valid image (content check failed)']); exit;
    }
    if ($file['size'] > 5*1024*1024) { echo json_encode(['success'=>false,'message'=>'File too large (max 5MB)']); exit; }
    $fname = 'prod_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $fname)) {
        echo json_encode(['success'=>true,'url'=> APP_URL.'/assets/images/products/'.$fname]);
    } else {
        // Report the concrete reason so the failure is diagnosable instead of generic.
        $why = !is_uploaded_file($file['tmp_name'])
                ? 'PHP did not register the upload (tmp file missing — check file_uploads / upload_tmp_dir in php.ini)'
                : (!is_writable($upload_dir) ? 'upload folder is not writable: ' . realpath($upload_dir)
                : 'move_uploaded_file failed (target: ' . $upload_dir . $fname . ')');
        echo json_encode(['success'=>false,'message'=>'Could not save the file — ' . $why]);
    }
    exit;
}

$search  = sanitize($_GET['search'] ?? '');
$cat_id  = (int)($_GET['cat'] ?? 0);
$status  = sanitize($_GET['status'] ?? '');
$stockF  = sanitize($_GET['stock'] ?? '');
$sort    = sanitize($_GET['sort'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));
$per_page = 15; $offset = ($page-1)*$per_page;
$where = ["1=1"]; $params = [];
// Search across name, SKU and short description (was: name only).
if ($search) { $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($cat_id)  { $where[] = "p.category_id = ?"; $params[] = $cat_id; }
// Stock-status filter (in stock / low / out of stock).
if ($stockF === 'out')          $where[] = "p.stock <= 0";
elseif ($stockF === 'low')      $where[] = "p.stock > 0 AND p.stock <= p.min_stock_alert";
elseif ($stockF === 'in')       $where[] = "p.stock > p.min_stock_alert";
// "restock" = everything at/under the alert threshold (low OR out) — matches the dashboard's
// "Low Stock — Restock Soon" definition so its links land on the right rows.
elseif ($stockF === 'restock')  $where[] = "p.stock <= p.min_stock_alert";
// Soft-delete: hide deleted products unless the "Deleted" filter is chosen.
if ($status === 'deleted') {
    $where[] = "p.is_deleted = 1";
} else {
    $where[] = "p.is_deleted = 0";
    if ($status !== '') { $where[] = "p.is_active = ?"; $params[] = (int)$status; }
}
$whereStr = implode(' AND ', $where);
// Sort — whitelist of safe ORDER BY clauses (never interpolate raw user input).
$orderMap = [
    'newest'      => 'p.id DESC',
    'oldest'      => 'p.id ASC',
    'price_asc'   => 'p.price ASC',
    'price_desc'  => 'p.price DESC',
    'stock_asc'   => 'p.stock ASC',
    'stock_desc'  => 'p.stock DESC',
    'name'        => 'p.name ASC',
    'bestselling' => 'p.total_sales DESC, p.id DESC',
];
$order = $orderMap[$sort] ?? 'p.id DESC';

// CSV export of the current (filtered) product list — same header the importer accepts.
if (($_GET['export'] ?? '') === 'csv') {
    $rows = db()->fetchAll("SELECT p.sku, p.name, c.name AS category, p.price, p.discount_price, p.stock, p.is_active, p.slug
                              FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE $whereStr ORDER BY p.id DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="products-'.date('Ymd-His').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['SKU','Name','Category','Price','Discount Price','Stock','Active','Slug']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['sku'], $r['name'], $r['category'], $r['price'], $r['discount_price'], $r['stock'], $r['is_active'], $r['slug']]);
    }
    fclose($out); exit;
}

$total = db()->fetchOne("SELECT COUNT(*) as cnt FROM products p WHERE $whereStr", $params)['cnt'];
$pages = ceil($total/$per_page);
$products = db()->fetchAll("SELECT p.*,c.name as category FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE $whereStr ORDER BY $order LIMIT $per_page OFFSET $offset", $params);
$categories = db()->fetchAll("SELECT * FROM categories WHERE is_active=1 ORDER BY name");
// Active shipping methods power the product "Shipping Method" dropdown (Shipping Management).
$shipMethods = db()->fetchAll("SELECT id, name, type FROM shipping_methods WHERE is_active=1 ORDER BY sort_order, name");
// All active products power the "Frequently Bought Together" picker.
$allProducts = db()->fetchAll("SELECT id, name, price FROM products WHERE is_active=1 ORDER BY name");
include __DIR__ . '/../includes/header.php';
?>
<style>
.tab-nav{display:flex;flex-wrap:wrap;gap:2px;border-bottom:1px solid var(--border-color);margin-bottom:0;padding:6px 16px 0;}
.tab-btn{padding:9px 12px;background:none;border:none;color:var(--text-secondary);font-size:.8rem;font-weight:500;cursor:pointer;border-bottom:2px solid transparent;transition:all .2s;white-space:nowrap;border-radius:6px 6px 0 0;}
.tab-btn:hover{background:var(--bg-elevated);color:var(--text-primary);}
.tab-btn.active{color:var(--gold-primary);border-bottom-color:var(--gold-primary);background:rgba(201,168,76,.06);}
.tab-btn i{margin-right:5px;}
.tab-pane{display:none;} .tab-pane.active{display:block;}
.spec-row{display:flex;gap:8px;margin-bottom:8px;align-items:center;}
.faq-item{background:var(--bg-elevated);border-radius:10px;padding:14px;margin-bottom:10px;}
.img-preview-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(76px,1fr));gap:8px;margin-top:10px;}
.img-thumb{position:relative;border-radius:8px;overflow:hidden;aspect-ratio:1;background:var(--bg-elevated);border:1px solid var(--border-color);}
.img-thumb img{width:100%;height:100%;object-fit:cover;}
.img-thumb .del-img{position:absolute;top:3px;right:3px;width:20px;height:20px;background:rgba(231,76,60,.9);color:#fff;border:none;border-radius:50%;font-size:.6rem;cursor:pointer;display:grid;place-items:center;}
.voice-btn{background:linear-gradient(135deg,#9B59B6,#6C3483);color:#fff;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;display:flex;align-items:center;gap:6px;font-size:.82rem;font-family:inherit;transition:.2s;}
.voice-btn:hover{opacity:.85;} .voice-btn.listening{animation:pulse-v 1s infinite;}
@keyframes pulse-v{0%,100%{box-shadow:0 0 0 0 rgba(155,89,182,.4);}50%{box-shadow:0 0 0 8px rgba(155,89,182,0);}}
.img-scan-btn{background:linear-gradient(135deg,#2ECC71,#1a8a4a);color:#fff;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;display:flex;align-items:center;gap:6px;font-size:.82rem;font-family:inherit;transition:.2s;}
.img-scan-btn:hover{opacity:.85;}
.review-card{background:var(--bg-elevated);border-radius:10px;padding:14px;margin-bottom:10px;border:1px solid var(--border-color);}
.drop-zone{border:2px dashed var(--border-active);border-radius:12px;padding:26px;text-align:center;cursor:pointer;transition:.2s;}
.drop-zone:hover{border-color:var(--gold-primary);background:rgba(201,168,76,.04);}
.voice-banner{background:linear-gradient(135deg,rgba(155,89,182,.15),rgba(155,89,182,.05));border:1px solid rgba(155,89,182,.3);border-radius:10px;padding:10px 16px;margin-bottom:12px;color:var(--text-primary);font-size:.85rem;display:none;}
</style>

<div class="page-header fade-in">
  <div class="page-header-left">
    <h1>Products</h1>
    <p>Dental product catalog — <?= $total ?> products total</p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <button class="btn btn-outline btn-sm" onclick="exportCsv()" title="Export the filtered list to CSV"><i class="fa-solid fa-file-csv"></i> Export</button>
    <button class="btn btn-outline btn-sm" onclick="document.getElementById('csvImportInput').click()" title="Import/update products from a CSV"><i class="fa-solid fa-file-import"></i> Import</button>
    <input type="file" id="csvImportInput" accept=".csv" style="display:none" onchange="importCsv(this)">
    <button class="btn btn-gold" onclick="openProductModal()"><i class="fa-solid fa-plus"></i> Add Product</button>
  </div>
</div>

<div class="filter-bar fade-in" style="flex-wrap:wrap;gap:8px;">
  <div class="search-wrapper" style="flex:1;min-width:180px;max-width:300px;">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input type="text" class="search-input" id="searchInput" placeholder="Search products..." value="<?= $search ?>">
  </div>
  <button class="voice-btn" id="voiceBtn" onclick="startVoiceSearch()"><i class="fa-solid fa-microphone"></i> Voice</button>
  <button class="img-scan-btn" onclick="document.getElementById('imgSearchFile').click()"><i class="fa-solid fa-camera"></i> Image Search</button>
  <input type="file" id="imgSearchFile" accept="image/*" style="display:none" onchange="doImageSearch(this)">
  <select class="form-control" id="catFilter" style="max-width:170px;">
    <option value="">All Categories</option>
    <?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $cat_id==$c['id']?'selected':'' ?>><?= $c['name'] ?></option><?php endforeach; ?>
  </select>
  <select class="form-control" id="statusFilter" style="max-width:130px;">
    <option value="">All Status</option>
    <option value="1" <?= $status==='1'?'selected':'' ?>>Active</option>
    <option value="0" <?= $status==='0'?'selected':'' ?>>Inactive</option>
    <option value="deleted" <?= $status==='deleted'?'selected':'' ?>>🗑 Deleted</option>
  </select>
  <select class="form-control" id="stockFilter" style="max-width:140px;">
    <option value="">All Stock</option>
    <option value="restock" <?= $stockF==='restock'?'selected':'' ?>>⚠ Low / Out (restock)</option>
    <option value="in"  <?= $stockF==='in'?'selected':'' ?>>In Stock</option>
    <option value="low" <?= $stockF==='low'?'selected':'' ?>>Low Stock (in stock)</option>
    <option value="out" <?= $stockF==='out'?'selected':'' ?>>Out of Stock</option>
  </select>
  <select class="form-control" id="sortBy" style="max-width:170px;" onchange="applyFilters()">
    <?php foreach(['newest'=>'Newest','oldest'=>'Oldest','price_asc'=>'Price: Low → High','price_desc'=>'Price: High → Low','stock_asc'=>'Stock: Low → High','stock_desc'=>'Stock: High → Low','name'=>'Name: A → Z','bestselling'=>'Best Selling'] as $sv=>$sl): ?>
    <option value="<?= $sv ?>" <?= $sort===$sv?'selected':'' ?>>Sort: <?= $sl ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
  <a href="products.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<div class="voice-banner" id="voiceBanner"><i class="fa-solid fa-microphone" style="color:#9B59B6;margin-right:6px;"></i><span id="voiceBannerText"></span><button onclick="document.getElementById('voiceBanner').style.display='none'" style="float:right;background:none;border:none;color:var(--text-muted);cursor:pointer;">✕</button></div>

<div class="card fade-in">
  <!-- Bulk action bar (shown when rows are selected) -->
  <div id="bulkBar" style="display:none;padding:12px 16px;border-bottom:1px solid var(--border-color);gap:10px;align-items:center;background:var(--bg-elevated);">
    <span id="bulkCount" style="font-size:.82rem;font-weight:600;"></span>
    <button class="btn btn-ghost btn-sm" onclick="bulkAction('activate')"><i class="fa-solid fa-circle-check" style="color:var(--success);"></i> Activate</button>
    <button class="btn btn-ghost btn-sm" onclick="bulkAction('deactivate')"><i class="fa-solid fa-ban" style="color:var(--warning);"></i> Deactivate</button>
    <button class="btn btn-ghost btn-sm" onclick="bulkAction('delete')" style="color:var(--danger);"><i class="fa-solid fa-trash"></i> Delete</button>
    <button class="btn btn-ghost btn-sm" onclick="clearBulk()">Clear</button>
  </div>
  <div class="table-responsive">
    <table>
      <thead><tr><th style="width:34px;"><input type="checkbox" id="selectAllProducts" onchange="toggleAllProducts(this)" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;"></th><th>#</th><th>Product</th><th>Category</th><th>Price</th><th>Discount</th><th>Stock</th><th>Weight</th><th>Status</th><th>★</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($products as $i => $p):
          $imgs = $p['images'] ? json_decode($p['images'],true) : null;
          $thumb = $imgs && !empty($imgs[0]) ? $imgs[0] : null;
        ?>
        <tr id="product-row-<?= $p['id'] ?>">
          <td><input type="checkbox" class="product-check" value="<?= $p['id'] ?>" onchange="updateBulkBar()" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;"></td>
          <td class="text-muted"><?= $offset+$i+1 ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="background:var(--bg-elevated);border-radius:8px;width:40px;height:40px;overflow:hidden;flex-shrink:0;">
                <?php if($thumb): ?><img src="<?= htmlspecialchars($thumb) ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?><div style="width:100%;height:100%;display:grid;place-items:center;"><i class="fa-solid fa-tooth" style="color:var(--gold-primary);"></i></div><?php endif; ?>
              </div>
              <div>
                <div class="font-bold" style="font-size:.85rem;"><?= htmlspecialchars($p['name']) ?></div>
                <div class="text-muted" style="font-size:.72rem;">SKU: <?= htmlspecialchars($p['sku'] ?? '') ?></div>
              </div>
            </div>
          </td>
          <td><?= isset($p['category']) ? htmlspecialchars($p['category']) : '<span class="text-muted">—</span>' ?></td>
          <td class="font-bold"><?= formatCurrency($p['price']) ?></td>
          <td><?php if($p['discount_price']): ?><div><?= formatCurrency($p['discount_price']) ?></div><div class="badge badge-success"><?= $p['discount_percent'] ?>% off</div><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
          <td><span class="<?= $p['stock']<=$p['min_stock_alert']?'stock-low':($p['stock']<=10?'stock-warn':'stock-ok') ?>"><?= $p['stock'] ?> units</span></td>
          <td><?= $p['weight_kg'] ? $p['weight_kg'].' kg' : '<span class="text-muted">—</span>' ?></td>
          <td><span class="badge badge-<?= $p['is_active']?'success':'secondary' ?>"><?= $p['is_active']?'Active':'Inactive' ?></span></td>
          <td><?= $p['is_featured']?'<i class="fa-solid fa-star text-gold"></i>':'<i class="fa-regular fa-star text-muted"></i>' ?></td>
          <td>
            <div style="display:flex;gap:4px;">
              <?php if(!empty($p['is_deleted'])): ?>
              <button class="btn btn-ghost btn-sm" onclick="restoreProduct(<?= $p['id'] ?>)" title="Restore product"><i class="fa-solid fa-trash-arrow-up" style="color:var(--success);"></i> Restore</button>
              <?php else: ?>
              <button class="btn btn-ghost btn-sm btn-icon" title="Edit" onclick='openProductModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, "UTF-8") ?>)'><i class="fa-solid fa-pen"></i></button>
              <button class="btn btn-ghost btn-sm btn-icon" title="FAQs" onclick="openFaqModal(<?= $p['id'] ?>)"><i class="fa-regular fa-circle-question"></i></button>
              <button class="btn btn-ghost btn-sm btn-icon" title="Reviews" onclick="openReviewsModal(<?= $p['id'] ?>,'<?= addslashes(htmlspecialchars($p['name'])) ?>')"><i class="fa-regular fa-star"></i></button>
              <button class="btn btn-ghost btn-sm btn-icon" title="Toggle" onclick="toggleProduct(<?= $p['id'] ?>)"><i class="fa-solid fa-power-off" style="color:<?= $p['is_active']?'var(--success)':'var(--text-muted)' ?>;"></i></button>
              <button class="btn btn-ghost btn-sm btn-icon" title="Delete" onclick="deleteProduct(<?= $p['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($products)): ?><tr><td colspan="11"><div class="empty-state"><i class="fa-solid fa-boxes-stacked"></i><p>No products found</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($pages > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border-color);">
    <div class="pagination">
      <?php
      // Compact pagination: first, last, and a window around the current page (… for gaps).
      $range = 2; $shown = [];
      for ($i = 1; $i <= $pages; $i++) {
          if ($i == 1 || $i == $pages || ($i >= $page - $range && $i <= $page + $range)) $shown[] = $i;
      }
      if ($page > 1): ?><div class="page-item" onclick="goPage(<?= $page-1 ?>)">‹</div><?php endif;
      $prev = 0;
      foreach ($shown as $i):
          if ($prev && $i - $prev > 1): ?><div class="page-item" style="pointer-events:none;opacity:.5;">…</div><?php endif; ?>
          <div class="page-item <?= $i==$page?'active':'' ?>" onclick="goPage(<?= $i ?>)"><?= $i ?></div>
          <?php $prev = $i;
      endforeach;
      if ($page < $pages): ?><div class="page-item" onclick="goPage(<?= $page+1 ?>)">›</div><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- PRODUCT MODAL -->
<div class="modal-overlay" id="productModal" style="display:none;" onclick="if(event.target===this)closeModal('productModal')">
  <div class="modal-box" style="max-width:840px;width:96vw;">
    <div class="modal-head"><h2 id="modalTitle">Add New Product</h2><button class="close-btn" onclick="closeModal('productModal')"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body" style="padding:0;">
      <input type="hidden" id="prod_id">
      <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('basic',this)"><i class="fa-solid fa-circle-info" style="margin-right:5px;"></i>Basic</button>
        <button class="tab-btn" onclick="switchTab('variants_tab',this)"><i class="fa-solid fa-layer-group" style="margin-right:5px;"></i>Variants</button>
        <button class="tab-btn" onclick="switchTab('content',this)"><i class="fa-solid fa-align-left" style="margin-right:5px;"></i>Content</button>
        <button class="tab-btn" onclick="switchTab('images',this)"><i class="fa-solid fa-images" style="margin-right:5px;"></i>Images</button>
        <button class="tab-btn" onclick="switchTab('faqs_tab',this)"><i class="fa-regular fa-circle-question" style="margin-right:5px;"></i>FAQs</button>
        <button class="tab-btn" onclick="switchTab('ship_tab',this)"><i class="fa-solid fa-truck" style="margin-right:5px;"></i>Shipping</button>
        <button class="tab-btn" onclick="switchTab('fbt_tab',this)"><i class="fa-solid fa-cart-plus" style="margin-right:5px;"></i>Bought Together</button>
        <button class="tab-btn" onclick="switchTab('gift_tab',this)"><i class="fa-solid fa-gift" style="margin-right:5px;"></i>Free Gift</button>
      </div>
      <div style="padding:20px;max-height:60vh;overflow-y:auto;">

        <!-- BASIC -->
        <div id="tab-basic" class="tab-pane active">
          <div class="form-row">
            <div class="form-group"><label class="form-label">Product Name *</label><input type="text" class="form-control" id="prod_name" placeholder="e.g. RF Cautery Machine Pro"></div>
            <div class="form-group"><label class="form-label">Category</label>
              <select class="form-control" id="prod_category"><option value="">— Select —</option><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= $c['name'] ?></option><?php endforeach; ?></select>
            </div>
          </div>
          <div class="form-group"><label class="form-label">Short Description</label><textarea class="form-control" id="prod_short_desc" rows="2" placeholder="Brief tagline for listings..."></textarea></div>
          <div class="form-row-3">
            <div class="form-group"><label class="form-label">Price (₹) *</label><input type="number" class="form-control" id="prod_price" placeholder="0"></div>
            <div class="form-group"><label class="form-label">Discount Price (₹)</label><input type="number" class="form-control" id="prod_discount" placeholder="Optional"></div>
            <div class="form-group"><label class="form-label">Stock Qty *</label><input type="number" class="form-control" id="prod_stock" placeholder="0"></div>
          </div>
          <div class="form-group" style="max-width:280px;"><label class="form-label">Low Stock Alert <small class="text-muted">(warn when stock ≤ this; default 5)</small></label><input type="number" min="0" class="form-control" id="prod_min_stock" placeholder="5"></div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Status</label><select class="form-control" id="prod_status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
            <div class="form-group"><label class="form-label">Show in Bestsellers</label><select class="form-control" id="prod_featured"><option value="0">No</option><option value="1">Yes</option></select></div>
            <div class="form-group"><label class="form-label">New Arrival</label><select class="form-control" id="prod_new"><option value="0">No</option><option value="1">Yes</option></select></div>
          </div>
          <!-- SEO -->
          <div style="border-top:1px solid var(--border-color);padding-top:14px;margin-top:6px;">
            <label class="form-label" style="margin-bottom:10px;display:block;"><i class="fa-solid fa-magnifying-glass-chart" style="color:var(--gold-primary);margin-right:5px;"></i>SEO <small class="text-muted">(search engines)</small></label>
            <div class="form-group"><label class="form-label">URL Slug <small class="text-muted">(auto from name if blank)</small></label><input type="text" class="form-control" id="prod_slug" placeholder="e.g. rf-cautery-machine-pro"></div>
            <div class="form-group"><label class="form-label">Meta Title <small class="text-muted">(Google/browser-tab title — keep under ~60 chars)</small></label><input type="text" class="form-control" id="prod_meta_title" maxlength="255" placeholder="Defaults to the product name"></div>
            <div class="form-group"><label class="form-label">Meta Description <small class="text-muted">(Google snippet — keep under ~155 chars)</small></label><textarea class="form-control" id="prod_meta_desc" rows="2" maxlength="320" placeholder="Short description shown in search results"></textarea></div>
          </div>
        </div>

        <!-- VARIANTS -->
        <div id="tab-variants_tab" class="tab-pane">
          <label class="form-label">Product Variants <small class="text-muted">(e.g. sizes/models — each with its own MRP &amp; price)</small></label>
          <p class="text-muted" style="font-size:.78rem;margin-bottom:10px;">
            Add variants only if this product is sold in multiple options. The storefront shows them as
            selectable choices with per-variant pricing. Discount % is auto-calculated. Leave empty for a single-price product.
          </p>
          <div style="display:flex;gap:8px;font-size:.72rem;color:var(--text-muted);padding:0 6px 4px;font-weight:600;">
            <span style="flex:2;">LABEL</span><span style="flex:1;">MRP (₹)</span><span style="flex:1;">PRICE (₹)</span><span style="width:34px;"></span>
          </div>
          <div id="variants_container"></div>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addVariantRow()" style="margin-top:8px;"><i class="fa-solid fa-plus"></i> Add Variant</button>
        </div>

        <!-- CONTENT -->
        <div id="tab-content" class="tab-pane">
          <div class="form-group"><label class="form-label">Full Description</label><textarea class="form-control" id="prod_full_desc" rows="4" placeholder="Detailed product description..."></textarea></div>
          <div class="form-group">
            <label class="form-label">Product Highlights <small class="text-muted">(Title + Text — shown as bullets on the product page; leave empty to use the global default)</small></label>
            <div id="highlights_container"></div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addHighlightRow()" style="margin-top:6px;"><i class="fa-solid fa-plus"></i> Add Highlight</button>
          </div>
          <div class="form-group"><label class="form-label">Directions for Use</label><textarea class="form-control" id="prod_directions" rows="3" placeholder="Step-by-step usage instructions..."></textarea></div>
          <div class="form-group"><label class="form-label">Packing Information</label><textarea class="form-control" id="prod_packing" rows="2" placeholder="e.g. 1 Unit, Accessory Kit, Power Adapter, User Manual"></textarea></div>
          <div class="form-group"><label class="form-label">Additional Information</label><textarea class="form-control" id="prod_additional" rows="3" placeholder="Regulatory compliance, certifications, legal disclaimers..."></textarea></div>
          <div class="form-group"><label class="form-label">Warranty</label><textarea class="form-control" id="prod_warranty" rows="2" placeholder="e.g. 2 Year Manufacturer Warranty on unit, 6 months on accessories"></textarea></div>
          <div class="form-group"><label class="form-label">Key Features</label><textarea class="form-control" id="prod_key_features" rows="3" placeholder="Main selling points / features..."></textarea></div>
          <div class="form-group"><label class="form-label">Warranty No</label><input type="text" class="form-control" id="prod_warranty_no" placeholder="e.g. WRN-2024-00123"></div>
          <div class="form-group"><label class="form-label">Direction of Use</label><textarea class="form-control" id="prod_direction_of_use" rows="3" placeholder="How to use / handling directions..."></textarea></div>
          <div class="form-group">
            <label class="form-label">Catalogue PDF <small class="text-muted">(shown as "Open Catalogue" on the product page; max 15MB)</small></label>
            <input type="hidden" id="prod_catalogue_url">
            <div style="display:flex;align-items:center;gap:10px;">
              <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('catalogueInput').click()"><i class="fa-solid fa-file-pdf"></i> Upload PDF</button>
              <span id="catalogue_name" class="text-muted" style="font-size:.82rem;">No file</span>
              <button type="button" class="btn btn-ghost btn-sm" id="catalogue_clear" style="display:none;" onclick="clearCatalogue()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
            </div>
            <input type="file" id="catalogueInput" accept="application/pdf" style="display:none" onchange="uploadCatalogue(this.files[0])">
          </div>
          <div class="form-group" style="border-top:1px solid var(--border-color);padding-top:14px;margin-top:6px;">
            <label class="form-label" style="margin-bottom:10px;">Key Specifications <small class="text-muted">(key : value rows — shown in the product Specifications accordion)</small></label>
            <div id="specs_container"></div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="addSpecRow()" style="margin-top:8px;"><i class="fa-solid fa-plus"></i> Add Row</button>
          </div>
        </div>

        <!-- IMAGES -->
        <div id="tab-images" class="tab-pane">
          <label class="form-label">Product Images <small class="text-muted">(up to 10 images, max 5MB each)</small></label>
          <div class="drop-zone" id="imgDropZone" onclick="document.getElementById('imgUploadInput').click()">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size:2.2rem;color:var(--gold-primary);margin-bottom:10px;display:block;"></i>
            <div style="color:var(--text-secondary);font-size:.9rem;">Click or drag & drop images here</div>
            <div style="color:var(--text-muted);font-size:.75rem;margin-top:4px;">JPG, PNG, WebP — max 5MB each</div>
          </div>
          <input type="file" id="imgUploadInput" accept="image/*" multiple style="display:none" onchange="uploadImages(this.files)">
          <div id="imgPreviewGrid" class="img-preview-grid"></div>
          <input type="hidden" id="prod_images_json" value="[]">

          <!-- HOVER IMAGE (white background, shown on hover in storefront) -->
          <label class="form-label" style="margin-top:18px;">Hover Image <small class="text-muted">(white background — shown when customer hovers the product on the storefront)</small></label>
          <div class="drop-zone" id="hoverDropZone" onclick="document.getElementById('hoverUploadInput').click()">
            <i class="fa-solid fa-wand-magic-sparkles" style="font-size:1.8rem;color:var(--gold-primary);margin-bottom:8px;display:block;"></i>
            <div style="color:var(--text-secondary);font-size:.85rem;">Click to upload hover image</div>
          </div>
          <input type="file" id="hoverUploadInput" accept="image/*" style="display:none" onchange="uploadHoverImage(this.files)">
          <div id="hoverPreview" style="margin-top:10px;"></div>
          <input type="hidden" id="prod_hover_image" value="">
        </div>

        <!-- FAQS -->
        <div id="tab-faqs_tab" class="tab-pane">
          <label class="form-label" style="margin-bottom:10px;">Product FAQs</label>
          <div id="faqs_container"></div>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addFaqRow()" style="margin-top:8px;"><i class="fa-solid fa-plus"></i> Add FAQ</button>
        </div>

        <!-- SHIPPING -->
        <div id="tab-ship_tab" class="tab-pane">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Shipping Method</label>
              <select class="form-control" id="prod_ship_method" onchange="toggleWeightField()">
                <option value="" data-type="">— Default (use global shipping rules) —</option>
                <?php foreach ($shipMethods as $m): ?>
                <option value="<?= (int)$m['id'] ?>" data-type="<?= htmlspecialchars($m['type']) ?>"><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['type']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Product Weight (kg)</label>
              <input type="number" step="0.001" class="form-control" id="prod_weight" placeholder="e.g. 2.500">
              <small class="text-muted" id="prod_weight_hint" style="font-size:.72rem;">Used only by the <strong>Weight-Based</strong> method.</small>
            </div>
          </div>
          <p class="text-muted" style="font-size:.78rem;margin-top:8px;">
            Methods come from <strong>Shipping Management</strong>. Leave method on
            <em>Default</em> to use the storefront global rules (current model:
            <strong>Free above ₹1,000, otherwise ₹99</strong>). Pick a method to force its
            cost for this product. Weight is used only by the Weight-Based method.
          </p>
        </div>

        <!-- FREQUENTLY BOUGHT TOGETHER -->
        <div id="tab-fbt_tab" class="tab-pane">
          <label class="form-label">Frequently Bought Together</label>
          <p class="text-muted" style="font-size:.78rem;margin-bottom:10px;">
            Pick products to suggest in the cart when THIS product is added. Shown on the
            storefront as a "Frequently Bought Together" strip.
          </p>
          <div class="form-group">
            <select class="form-control" id="fbt_picker" onchange="addFbt(this.value); this.value='';">
              <option value="">+ Add a related product…</option>
              <?php foreach ($allProducts as $ap): ?>
              <option value="<?= (int)$ap['id'] ?>" data-name="<?= htmlspecialchars($ap['name']) ?>" data-price="<?= (float)$ap['price'] ?>"><?= htmlspecialchars($ap['name']) ?> — ₹<?= number_format((float)$ap['price'],0) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="fbt_list" style="display:flex;flex-direction:column;gap:6px;"></div>
        </div>

        <!-- FREE GIFT -->
        <div id="tab-gift_tab" class="tab-pane">
          <label class="form-label">Free Gift with this Product</label>
          <p class="text-muted" style="font-size:.78rem;margin-bottom:10px;">
            Pick products to give FREE when THIS product is purchased. They're auto-added to
            the cart at ₹0 and removed if this product is removed.
          </p>
          <div class="form-group">
            <select class="form-control" id="gift_picker" onchange="addGift(this.value); this.value='';">
              <option value="">+ Add a free gift product…</option>
              <?php foreach ($allProducts as $ap): ?>
              <option value="<?= (int)$ap['id'] ?>" data-name="<?= htmlspecialchars($ap['name']) ?>" data-price="<?= (float)$ap['price'] ?>"><?= htmlspecialchars($ap['name']) ?> — ₹<?= number_format((float)$ap['price'],0) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="gift_list" style="display:flex;flex-direction:column;gap:6px;"></div>
        </div>

      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeModal('productModal')">Cancel</button>
      <button class="btn btn-gold" onclick="saveProduct()"><i class="fa-solid fa-floppy-disk"></i> Save Product</button>
    </div>
  </div>
</div>

<!-- FAQ VIEW MODAL -->
<div class="modal-overlay" id="faqModal" style="display:none;" onclick="if(event.target===this)closeModal('faqModal')">
  <div class="modal-box" style="max-width:600px;">
    <div class="modal-head"><h2>Product FAQs</h2><button class="close-btn" onclick="closeModal('faqModal')"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body" id="faqModalBody" style="max-height:60vh;overflow-y:auto;"></div>
  </div>
</div>

<!-- REVIEWS MODAL -->
<div class="modal-overlay" id="reviewsModal" style="display:none;" onclick="if(event.target===this)closeModal('reviewsModal')">
  <div class="modal-box" style="max-width:680px;">
    <div class="modal-head"><h2 id="reviewsTitle">Product Reviews</h2><button class="close-btn" onclick="closeModal('reviewsModal')"><i class="fa-solid fa-xmark"></i></button></div>
    <div class="modal-body" id="reviewsBody" style="max-height:65vh;overflow-y:auto;"></div>
  </div>
</div>

<script>
function switchTab(name,btn){
  document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+name).classList.add('active');
  btn.classList.add('active');
}
function buildProductQuery(extra={}){
  const p=new URLSearchParams({
    search:document.getElementById('searchInput')?.value||'',
    cat:document.getElementById('catFilter')?.value||'',
    status:document.getElementById('statusFilter')?.value||'',
    stock:document.getElementById('stockFilter')?.value||'',
    sort:document.getElementById('sortBy')?.value||'',
  });
  Object.entries(extra).forEach(([k,v])=>p.set(k,v));
  [...p.entries()].forEach(([k,v])=>{if(!v)p.delete(k);});
  return p.toString();
}
function applyFilters(){window.location.href='products.php?'+buildProductQuery();}
function exportCsv(){window.location.href='products.php?'+buildProductQuery({export:'csv'});}
function goPage(p){const q=new URLSearchParams(window.location.search);q.set('page',p);window.location.href='products.php?'+q.toString();}

// CSV import (upsert by SKU)
async function importCsv(input){
  const file=input.files[0]; input.value='';
  if(!file)return;
  if(!confirm(`Import "${file.name}"? Existing products (matched by SKU) will be updated, new SKUs created.`))return;
  const fd=new FormData(); fd.append('products_csv',file);
  showToast('Importing…','info');
  try{
    const res=await fetch('products.php',{method:'POST',body:fd});
    const r=await res.json();
    showToast(r.message||(r.success?'Imported':'Import failed'), r.success?'success':'danger');
    if(r.success) setTimeout(()=>location.reload(),1200);
  }catch(e){ showToast('Import failed','danger'); }
}

// ---- Bulk selection ----
function selectedProductIds(){return [...document.querySelectorAll('.product-check:checked')].map(c=>parseInt(c.value));}
function updateBulkBar(){
  const n=selectedProductIds().length;
  const bar=document.getElementById('bulkBar');
  bar.style.display=n?'flex':'none';
  if(n)document.getElementById('bulkCount').textContent=n+' selected';
  const all=document.getElementById('selectAllProducts');
  const total=document.querySelectorAll('.product-check').length;
  if(all)all.checked=n>0&&n===total;
}
function toggleAllProducts(cb){document.querySelectorAll('.product-check').forEach(c=>c.checked=cb.checked);updateBulkBar();}
function clearBulk(){document.querySelectorAll('.product-check').forEach(c=>c.checked=false);const a=document.getElementById('selectAllProducts');if(a)a.checked=false;updateBulkBar();}
async function bulkAction(op){
  const ids=selectedProductIds();
  if(!ids.length)return;
  const verb={activate:'activate',deactivate:'deactivate',delete:'permanently delete'}[op];
  if(!confirm(`${verb.charAt(0).toUpperCase()+verb.slice(1)} ${ids.length} product(s)?`))return;
  const res=await fetch('products.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'bulk',op,ids})});
  const r=await res.json();
  showToast(r.message||(r.success?'Done':'Failed'), r.success?'success':'danger');
  if(r.success)setTimeout(()=>location.reload(),800);
}

// Live search: search as the user types (debounced) instead of only on the Filter button.
// Each search reloads the page so it queries the full catalog, not just the current 15 rows.
(function(){
  const si = document.getElementById('searchInput');
  if (!si) return;
  let t;
  si.addEventListener('input', () => { clearTimeout(t); t = setTimeout(applyFilters, 400); });
  // Enter searches immediately (skips the debounce wait).
  si.addEventListener('keydown', (e) => { if (e.key === 'Enter') { clearTimeout(t); applyFilters(); } });
  // After the reload, restore focus + put the caret at the end so typing continues seamlessly.
  if (si.value) { const v = si.value; si.focus(); si.value = ''; si.value = v; }
})();

// Spec rows
function addSpecRow(k='',v=''){
  const id='s'+Date.now()+Math.random().toString(36).slice(2,6);
  const d=document.createElement('div');d.className='spec-row';d.id=id;
  d.innerHTML=`<input type="text" class="form-control" placeholder="Specification" value="${k.replace(/"/g,'&quot;')}" data-spec-key>
    <input type="text" class="form-control" placeholder="Value" value="${v.replace(/"/g,'&quot;')}" data-spec-val>
    <button type="button" class="btn btn-ghost btn-sm btn-icon" onclick="this.closest('.spec-row').remove()"><i class="fa-solid fa-minus" style="color:var(--danger);"></i></button>`;
  document.getElementById('specs_container').appendChild(d);
}

// Variant rows -> saved into the `variants` column as [{label,mrp,price,discount}]
function addVariantRow(label='', mrp='', price=''){
  const d=document.createElement('div');d.className='variant-row';
  d.style.cssText='display:flex;gap:8px;margin-bottom:8px;align-items:center;';
  d.innerHTML=`<input type="text" class="form-control" placeholder="e.g. Generic / 4 mm" value="${String(label).replace(/"/g,'&quot;')}" data-var-label style="flex:2;">
    <input type="number" min="0" class="form-control" placeholder="MRP" value="${mrp}" data-var-mrp style="flex:1;">
    <input type="number" min="0" class="form-control" placeholder="Price" value="${price}" data-var-price style="flex:1;">
    <button type="button" class="btn btn-ghost btn-sm btn-icon" onclick="this.closest('.variant-row').remove()"><i class="fa-solid fa-minus" style="color:var(--danger);"></i></button>`;
  document.getElementById('variants_container').appendChild(d);
}

// Highlight rows (Title + Text) -> saved into the `features` column as [{title,text}]
function addHighlightRow(t='',x=''){
  const id='h'+Date.now()+Math.random().toString(36).slice(2,6);
  const d=document.createElement('div');d.className='highlight-row';d.id=id;
  d.style.cssText='display:flex;gap:6px;margin-bottom:6px;align-items:flex-start;';
  d.innerHTML=`<input type="text" class="form-control" placeholder="Title (e.g. Key Features)" value="${t.replace(/"/g,'&quot;')}" data-hl-title style="flex:1;">
    <textarea class="form-control" placeholder="Text..." rows="2" data-hl-text style="flex:2;">${x}</textarea>
    <button type="button" class="btn btn-ghost btn-sm btn-icon" onclick="this.closest('.highlight-row').remove()"><i class="fa-solid fa-minus" style="color:var(--danger);"></i></button>`;
  document.getElementById('highlights_container').appendChild(d);
}

// FAQ rows
function addFaqRow(q='',a=''){
  const id='f'+Date.now()+Math.random().toString(36).slice(2,6);
  const d=document.createElement('div');d.className='faq-item';d.id=id;
  d.innerHTML=`<div style="display:flex;justify-content:space-between;margin-bottom:8px;"><span style="font-size:.78rem;font-weight:600;color:var(--gold-primary);">FAQ</span><button type="button" class="btn btn-ghost btn-sm btn-icon" onclick="this.closest('.faq-item').remove()"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button></div>
    <input type="text" class="form-control" placeholder="Question..." value="${q.replace(/"/g,'&quot;')}" data-faq-q style="margin-bottom:8px;">
    <textarea class="form-control" placeholder="Answer..." rows="2" data-faq-a>${a}</textarea>`;
  document.getElementById('faqs_container').appendChild(d);
}

// Catalogue PDF
function setCatalogueUI(url){
  document.getElementById('prod_catalogue_url').value=url||'';
  document.getElementById('catalogue_name').textContent=url?url.split('/').pop():'No file';
  document.getElementById('catalogue_clear').style.display=url?'':'none';
}
function clearCatalogue(){ setCatalogueUI(''); }
async function uploadCatalogue(file){
  if(!file)return;
  const fd=new FormData();fd.append('catalogue_pdf',file);
  document.getElementById('catalogue_name').textContent='Uploading…';
  const res=await fetch('products.php',{method:'POST',body:fd});
  const r=await res.json();
  if(r.success){ setCatalogueUI(r.url); showToast('Catalogue uploaded','success'); }
  else { setCatalogueUI(''); showToast(r.message||'Upload failed','danger'); }
}

// Images
let uploadedImages=[];
async function uploadImages(files){
  for(const file of files){
    if(uploadedImages.length>=10){showToast('Max 10 images','warning');break;}
    const fd=new FormData();fd.append('product_image',file);
    try{
      const res=await fetch('products.php',{method:'POST',body:fd});
      const data=await res.json();
      if(data.success){uploadedImages.push(data.url);renderImgs();}
      else showToast(data.message,'danger');
    }catch(e){showToast('Upload error','danger');}
  }
}
function renderImgs(){
  const grid=document.getElementById('imgPreviewGrid');grid.innerHTML='';
  uploadedImages.forEach((url,i)=>{
    const d=document.createElement('div');d.className='img-thumb';
    d.innerHTML=`<img src="${url}" loading="lazy"><button class="del-img" onclick="uploadedImages.splice(${i},1);renderImgs()"><i class="fa-solid fa-xmark"></i></button>`;
    grid.appendChild(d);
  });
  document.getElementById('prod_images_json').value=JSON.stringify(uploadedImages);
}
const dz=document.getElementById('imgDropZone');
if(dz){
  dz.addEventListener('dragover',e=>{e.preventDefault();dz.style.borderColor='var(--gold-primary)';});
  dz.addEventListener('dragleave',()=>dz.style.borderColor='var(--border-active)');
  dz.addEventListener('drop',e=>{e.preventDefault();dz.style.borderColor='var(--border-active)';uploadImages(e.dataTransfer.files);});
}

// Hover image (single)
async function uploadHoverImage(files){
  const file=files[0];if(!file)return;
  const fd=new FormData();fd.append('product_image',file);
  try{
    const res=await fetch('products.php',{method:'POST',body:fd});
    const data=await res.json();
    if(data.success){document.getElementById('prod_hover_image').value=data.url;renderHover();}
    else showToast(data.message,'danger');
  }catch(e){showToast('Upload error','danger');}
}
function renderHover(){
  const url=document.getElementById('prod_hover_image').value;
  const box=document.getElementById('hoverPreview');
  box.innerHTML=url?`<div class="img-thumb" style="width:90px;height:90px;"><img src="${url}" loading="lazy" style="background:#fff;"><button class="del-img" onclick="document.getElementById('prod_hover_image').value='';renderHover()"><i class="fa-solid fa-xmark"></i></button></div>`:'';
}

// ---- Frequently Bought Together (per-product related products) ----
let fbtIds = [];
function addFbt(id){
  id=parseInt(id); if(!id||fbtIds.includes(id))return;
  if(String(id)===document.getElementById('prod_id').value)return;  // not itself
  fbtIds.push(id); renderFbt();
}
function removeFbt(id){ fbtIds=fbtIds.filter(x=>x!==parseInt(id)); renderFbt(); }
function renderFbt(){
  const sel=document.getElementById('fbt_picker');
  const list=document.getElementById('fbt_list');
  if(!fbtIds.length){list.innerHTML='<div class="text-muted" style="font-size:.8rem;padding:6px;">No related products yet.</div>';return;}
  list.innerHTML=fbtIds.map(id=>{
    const opt=[...sel.options].find(o=>o.value==id);
    const name=opt?opt.dataset.name:('#'+id);
    const price=opt?Number(opt.dataset.price).toLocaleString('en-IN'):'';
    return `<div style="display:flex;align-items:center;justify-content:space-between;border:1px solid var(--border-color);border-radius:8px;padding:8px 10px;">
      <span style="font-size:.85rem;">${name}${price?` <span class="text-muted">— ₹${price}</span>`:''}</span>
      <button type="button" class="btn btn-ghost btn-sm" onclick="removeFbt(${id})"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
    </div>`;
  }).join('');
}

// ---- Free Gift (per-product gift products) ----
let giftIds = [];
function addGift(id){
  id=parseInt(id); if(!id||giftIds.includes(id))return;
  if(String(id)===document.getElementById('prod_id').value)return;  // not itself
  giftIds.push(id); renderGift();
}
function removeGift(id){ giftIds=giftIds.filter(x=>x!==parseInt(id)); renderGift(); }
function renderGift(){
  const sel=document.getElementById('gift_picker');
  const list=document.getElementById('gift_list');
  if(!giftIds.length){list.innerHTML='<div class="text-muted" style="font-size:.8rem;padding:6px;">No free gifts yet.</div>';return;}
  list.innerHTML=giftIds.map(id=>{
    const opt=[...sel.options].find(o=>o.value==id);
    const name=opt?opt.dataset.name:('#'+id);
    const price=opt?Number(opt.dataset.price).toLocaleString('en-IN'):'';
    return `<div style="display:flex;align-items:center;justify-content:space-between;border:1px solid var(--border-color);border-radius:8px;padding:8px 10px;">
      <span style="font-size:.85rem;">🎁 ${name}${price?` <span class="text-muted">— worth ₹${price}, FREE</span>`:''}</span>
      <button type="button" class="btn btn-ghost btn-sm" onclick="removeGift(${id})"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
    </div>`;
  }).join('');
}

// Weight is only meaningful for the Weight-Based method. Enable the field only when the
// selected shipping method is of type "weight"; otherwise grey it out (and clear it).
function toggleWeightField(){
  const sel = document.getElementById('prod_ship_method');
  const type = sel.options[sel.selectedIndex]?.dataset.type || '';
  const isWeight = type === 'weight';
  const wt = document.getElementById('prod_weight');
  const hint = document.getElementById('prod_weight_hint');
  wt.disabled = !isWeight;
  wt.style.opacity = isWeight ? '1' : '0.5';
  if (!isWeight) wt.value = '';
  if (hint) hint.style.color = isWeight ? 'var(--gold-primary)' : '';
}

// Open modal
function openProductModal(p=null){
  document.getElementById('prod_id').value=p?.id||'';
  document.getElementById('prod_name').value=p?.name||'';
  document.getElementById('prod_category').value=p?.category_id||'';
  document.getElementById('prod_short_desc').value=p?.short_description||'';
  document.getElementById('prod_full_desc').value=p?.full_description||'';
  document.getElementById('prod_price').value=p?.price||'';
  document.getElementById('prod_discount').value=p?.discount_price||'';
  document.getElementById('prod_stock').value=p?.stock||'';
  document.getElementById('prod_min_stock').value=p?.min_stock_alert??'';
  document.getElementById('prod_status').value=p?.is_active??1;
  document.getElementById('prod_featured').value=p?.is_featured??0;
  document.getElementById('prod_new').value=p?.is_new??0;
  document.getElementById('prod_weight').value=p?.weight_kg||'';
  document.getElementById('prod_ship_method').value=p?.shipping_method_id||'';
  toggleWeightField();
  // Highlights (per-product). Stored in `features` column as [{title,text}].
  // Back-compat: old rows are plain strings -> convert to {title:'', text}.
  document.getElementById('highlights_container').innerHTML='';
  try{
    const feats=p?.features?JSON.parse(p.features):[];
    if(Array.isArray(feats)){
      feats.forEach(f=>{
        if(f && typeof f==='object') addHighlightRow(f.title||'', f.text||'');
        else addHighlightRow('', String(f||''));
      });
    }
  }catch(e){}
  document.getElementById('prod_directions').value=p?.directions_for_use||'';
  document.getElementById('prod_packing').value=p?.packing_info||'';
  document.getElementById('prod_additional').value=p?.additional_information||'';
  document.getElementById('prod_warranty').value=p?.warranty_info||'';
  document.getElementById('prod_key_features').value=p?.key_features||'';
  document.getElementById('prod_warranty_no').value=p?.warranty_no||'';
  document.getElementById('prod_direction_of_use').value=p?.direction_of_use||'';
  setCatalogueUI(p?.catalogue_url||'');
  // SEO fields
  document.getElementById('prod_slug').value=p?.slug||'';
  document.getElementById('prod_meta_title').value=p?.meta_title||'';
  document.getElementById('prod_meta_desc').value=p?.meta_description||'';
  // Variants
  document.getElementById('variants_container').innerHTML='';
  try{
    const vs=p?.variants?(typeof p.variants==='string'?JSON.parse(p.variants):p.variants):[];
    if(Array.isArray(vs)) vs.forEach(v=>addVariantRow(v.label||'', v.mrp||'', v.price||''));
  }catch(e){}
  document.getElementById('modalTitle').textContent=p?'Edit Product':'Add New Product';
  // Specs
  document.getElementById('specs_container').innerHTML='';
  if(p?.key_specifications){try{const sp=JSON.parse(p.key_specifications);if(Array.isArray(sp))sp.forEach(s=>addSpecRow(s.key,s.value));else Object.entries(sp).forEach(([k,v])=>addSpecRow(k,v));}catch(e){}}
  // FAQs from server
  document.getElementById('faqs_container').innerHTML='';
  if(p?.id){
    fetch('products.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'get_faqs',product_id:p.id})})
    .then(r=>r.json()).then(d=>{if(d.faqs)d.faqs.forEach(f=>addFaqRow(f.question,f.answer));});
  }
  // Frequently Bought Together from server
  fbtIds=[];
  if(p?.id){
    fetch('products.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'get_fbt',product_id:p.id})})
    .then(r=>r.json()).then(d=>{fbtIds=(d.fbt||[]).map(Number);renderFbt();});
  } else { renderFbt(); }
  // Free gifts from server
  giftIds=[];
  if(p?.id){
    fetch('products.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'get_gifts',product_id:p.id})})
    .then(r=>r.json()).then(d=>{giftIds=(d.gifts||[]).map(Number);renderGift();});
  } else { renderGift(); }
  // Images
  uploadedImages=p?.images?(typeof p.images==='string'?JSON.parse(p.images):p.images):[];
  renderImgs();
  // Hover image
  document.getElementById('prod_hover_image').value=p?.hover_image||'';
  renderHover();
  // Reset tabs
  document.querySelectorAll('.tab-btn').forEach((b,i)=>b.classList.toggle('active',i===0));
  document.querySelectorAll('.tab-pane').forEach((p,i)=>p.classList.toggle('active',i===0));
  openModal('productModal');
}

async function saveProduct(){
  const name=document.getElementById('prod_name').value.trim();
  const price=document.getElementById('prod_price').value;
  const stock=document.getElementById('prod_stock').value;
  if(!name||!price||stock===''){showToast('Name, Price and Stock are required','warning');return;}
  const specs=[];
  document.querySelectorAll('#specs_container .spec-row').forEach(row=>{
    const k=row.querySelector('[data-spec-key]').value.trim();
    const v=row.querySelector('[data-spec-val]').value.trim();
    if(k)specs.push({key:k,value:v});
  });
  const faqs=[];
  document.querySelectorAll('#faqs_container .faq-item').forEach(item=>{
    const q=item.querySelector('[data-faq-q]')?.value.trim();
    const a=item.querySelector('[data-faq-a]')?.value.trim();
    if(q&&a)faqs.push({question:q,answer:a});
  });
  // Highlights -> stored in `features` column as [{title,text}]
  const features=[];
  document.querySelectorAll('#highlights_container .highlight-row').forEach(row=>{
    const t=row.querySelector('[data-hl-title]').value.trim();
    const x=row.querySelector('[data-hl-text]').value.trim();
    if(t||x)features.push({title:t,text:x});
  });
  // Variants -> [{label, mrp, price}] (server computes discount, validates, stores JSON)
  const variants=[];
  document.querySelectorAll('#variants_container .variant-row').forEach(row=>{
    const label=row.querySelector('[data-var-label]').value.trim();
    const mrp=parseFloat(row.querySelector('[data-var-mrp]').value)||0;
    const price=parseFloat(row.querySelector('[data-var-price]').value)||0;
    if(label && price>0) variants.push({label, mrp: mrp||price, price});
  });
  let images=[];try{images=JSON.parse(document.getElementById('prod_images_json').value);}catch(e){}
  const hover_image=document.getElementById('prod_hover_image').value;
  const payload={action:'save',id:document.getElementById('prod_id').value,name,price,stock,hover_image,
    min_stock_alert:document.getElementById('prod_min_stock').value,
    slug:document.getElementById('prod_slug').value,
    meta_title:document.getElementById('prod_meta_title').value,
    meta_description:document.getElementById('prod_meta_desc').value,
    variants,
    category_id:document.getElementById('prod_category').value,
    short_description:document.getElementById('prod_short_desc').value,
    full_description:document.getElementById('prod_full_desc').value,
    features,packing_info:document.getElementById('prod_packing').value,
    key_specifications:specs,directions_for_use:document.getElementById('prod_directions').value,
    additional_information:document.getElementById('prod_additional').value,
    warranty_info:document.getElementById('prod_warranty').value,
    key_features:document.getElementById('prod_key_features').value,
    warranty_no:document.getElementById('prod_warranty_no').value,
    direction_of_use:document.getElementById('prod_direction_of_use').value,
    catalogue_url:document.getElementById('prod_catalogue_url').value,
    discount_price:document.getElementById('prod_discount').value,
    weight_kg:document.getElementById('prod_weight').value,
    shipping_method_id:document.getElementById('prod_ship_method').value,
    is_active:document.getElementById('prod_status').value,
    is_featured:document.getElementById('prod_featured').value,
    is_new:document.getElementById('prod_new').value,
    images,faqs,fbt:fbtIds,gifts:giftIds};
  const res=await fetch('products.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(payload)});
  const result=await res.json();
  if(result.success){showToast(result.message,'success');closeModal('productModal');setTimeout(()=>location.reload(),800);}
  else showToast(result.message||'Save failed','danger');
}

async function openFaqModal(id){
  const res=await fetch('products.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'get_faqs',product_id:id})});
  const data=await res.json();
  const body=document.getElementById('faqModalBody');
  if(!data.faqs||!data.faqs.length){body.innerHTML='<div class="empty-state"><i class="fa-regular fa-circle-question"></i><p>No FAQs yet</p></div>';}
  else body.innerHTML=data.faqs.map((f,i)=>`<div class="faq-item"><div style="font-weight:600;color:var(--gold-primary);margin-bottom:6px;">${i+1}. ${escapeHtml(f.question)}</div><div style="color:var(--text-secondary);font-size:.85rem;">${escapeHtml(f.answer)}</div></div>`).join('');
  openModal('faqModal');
}

async function openReviewsModal(id,name){
  document.getElementById('reviewsTitle').textContent='Reviews — '+name;
  const res=await fetch('products.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'get_reviews',product_id:id})});
  const data=await res.json();
  const body=document.getElementById('reviewsBody');
  if(!data.reviews||!data.reviews.length){body.innerHTML='<div class="empty-state"><i class="fa-regular fa-star"></i><p>No reviews yet</p></div>';}
  else body.innerHTML=data.reviews.map(r=>`
    <div class="review-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;">
        <div><div style="font-weight:600;">${escapeHtml(r.reviewer_name)}</div><div style="color:#F0D080;">${'★'.repeat(r.rating)}${'☆'.repeat(5-r.rating)}</div></div>
        <div style="display:flex;gap:6px;">
          <span class="badge badge-${r.is_approved?'success':'warning'}">${r.is_approved?'Approved':'Pending'}</span>
          ${r.is_verified?'<span class="badge badge-info">Verified</span>':''}
          <button class="btn btn-ghost btn-sm btn-icon" onclick="approveReview(${r.id},${r.is_approved?0:1})"><i class="fa-solid fa-${r.is_approved?'ban':'check'}" style="color:${r.is_approved?'var(--danger)':'var(--success)'}"></i></button>
          <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteReview(${r.id})"><i class="fa-solid fa-trash" style="color:var(--danger)"></i></button>
        </div>
      </div>
      ${r.title?`<div style="font-weight:500;margin-top:8px;">${escapeHtml(r.title)}</div>`:''}
      <div style="color:var(--text-secondary);font-size:.85rem;margin-top:6px;">${escapeHtml(r.review)}</div>
      <div style="color:var(--text-muted);font-size:.72rem;margin-top:8px;">${r.created_at}</div>
    </div>`).join('');
  openModal('reviewsModal');
}
async function approveReview(id,approved){
  await fetch('products.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'approve_review',id,approved})});
  showToast('Review updated','success');closeModal('reviewsModal');
}
async function deleteReview(id){
  if(!confirm('Delete this review?'))return;
  await fetch('products.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete_review',id})});
  showToast('Review deleted','success');closeModal('reviewsModal');
}
function toggleProduct(id){
  fetch('products.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'toggle',id})})
  .then(r=>r.json()).then(d=>{if(d.success){showToast('Status updated','success');setTimeout(()=>location.reload(),600);}});
}
function deleteProduct(id){
  showConfirm('Delete Product','This hides the product from the store and storefront. Order history is kept, and you can restore it anytime from the "Deleted" filter. Continue?',async()=>{
    const res=await fetch('products.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
    const result=await res.json();
    if(result.success){showToast('Product deleted','success');const row=document.getElementById('product-row-'+id);if(row){row.style.opacity='0';row.style.transition='.3s';setTimeout(()=>row.remove(),300);}}
  });
}
function restoreProduct(id){
  fetch('products.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'restore',id})})
  .then(r=>r.json()).then(d=>{if(d.success){showToast('Product restored','success');setTimeout(()=>location.reload(),600);}});
}

// Voice Search
let recognition=null;
function startVoiceSearch(){
  const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  if(!SR){showToast('Voice search not supported in this browser','warning');return;}
  if(recognition){recognition.abort();recognition=null;resetVoiceBtn();return;}
  recognition=new SR();recognition.lang='en-IN';recognition.interimResults=false;
  document.getElementById('voiceBtn').classList.add('listening');
  document.getElementById('voiceBtn').innerHTML='<i class="fa-solid fa-microphone-slash"></i> Listening...';
  recognition.onresult=e=>{
    const text=e.results[0][0].transcript;
    document.getElementById('searchInput').value=text;
    document.getElementById('voiceBanner').style.display='block';
    document.getElementById('voiceBannerText').textContent='Voice search: "'+text+'"';
    resetVoiceBtn();recognition=null;applyFilters();
  };
  recognition.onerror=()=>{showToast('Voice recognition failed','warning');resetVoiceBtn();recognition=null;};
  recognition.onend=()=>{resetVoiceBtn();recognition=null;};
  recognition.start();
}
function resetVoiceBtn(){document.getElementById('voiceBtn').classList.remove('listening');document.getElementById('voiceBtn').innerHTML='<i class="fa-solid fa-microphone"></i> Voice';}

// Image Search
function doImageSearch(input){
  const file=input.files[0];if(!file)return;
  showToast('Analyzing image...','info');
  const reader=new FileReader();
  reader.onload=e=>{
    // Extract keyword hint from filename
    const hint=file.name.replace(/\.[^/.]+$/,'').replace(/[-_]/g,' ').replace(/\d+/g,' ').trim();
    if(hint.length>2){
      document.getElementById('searchInput').value=hint;
      document.getElementById('voiceBanner').style.display='block';
      document.getElementById('voiceBannerText').textContent='Image search: scanning for "'+hint+'"';
      applyFilters();
    } else {
      showToast('Could not detect product from image. Try a named file.','warning');
    }
  };
  reader.readAsDataURL(file);
  input.value='';
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
