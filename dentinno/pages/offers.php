<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Offers';
requireView('offers');

// Image upload (shared products folder)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['offer_image'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    requireAction('offers', 'edit');
    $upload_dir = __DIR__ . '/../assets/images/products/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $file = $_FILES['offer_image'];
    // Check PHP's upload status first (a file over upload_max_filesize arrives with error=1,
    // empty tmp_name and size=0, otherwise failing later as a misleading "Upload failed").
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
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) { echo json_encode(['success'=>false,'message'=>'Invalid file type']); exit; }
    // Verify the actual file CONTENT, not just the extension (a .jpg could be a PHP script).
    $fi = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mime = $fi ? finfo_file($fi, $file['tmp_name']) : '';
    if (!$mime || !in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true)) {
        echo json_encode(['success'=>false,'message'=>'The file is not a valid image (content check failed)']); exit;
    }
    if (!imageDimsOk($file['tmp_name'])) { echo json_encode(['success'=>false,'message'=>'Image must be a valid file no larger than 6000×6000 px']); exit; }
    if ($file['size'] > 5*1024*1024) { echo json_encode(['success'=>false,'message'=>'File too large (max 5MB)']); exit; }
    $fname = 'offer_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $fname)) {
        echo json_encode(['success'=>true,'url'=> APP_URL.'/assets/images/products/'.$fname]);
    } else { echo json_encode(['success'=>false,'message'=>'Could not save the file. Check write permissions on assets/images/products/.']); }
    exit;
}

// AJAX JSON actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    // Never let a PHP warning/exception leak HTML into the JSON response (that breaks res.json()
    // on the client with "Unexpected token '<'"). Any DB error is returned as a JSON message.
    try {
    $d = json_decode(file_get_contents('php://input'), true);
    $action = $d['action'] ?? '';
    requireAction('offers', rbacCrudVerb($action, $d));

    if ($action === 'save') {
        $mainProduct = $d['main_product'] ?? null;   // {productId,name,variant,image,price,mrp}
        $freeItems   = is_array($d['free_items'] ?? null) ? $d['free_items'] : []; // [{productId,name,mrp,image,qty?}]

        // ---- Server-side validation (authoritative; do not trust client math) ----
        $title   = trim($d['title'] ?? '');
        $special = (float)($d['special_price'] ?? 0);
        if ($title === '')                         { echo json_encode(['success'=>false,'message'=>'Title is required']); exit; }
        if (!$mainProduct || empty($mainProduct['productId'])) { echo json_encode(['success'=>false,'message'=>'Select a main product']); exit; }
        if ($special <= 0)                         { echo json_encode(['success'=>false,'message'=>'Special price must be greater than 0']); exit; }

        // Resolve the main product slug -> products.id (must be an active catalog product).
        // products.price is the MRP; discount_price is the normal selling price.
        $mainRow = db()->fetchOne("SELECT id, price, discount_price FROM products WHERE slug=? AND is_active=1", [$mainProduct['productId']]);
        if (!$mainRow) { echo json_encode(['success'=>false,'message'=>'Main product not found or inactive']); exit; }
        $productId = (int)$mainRow['id'];
        // Selling price = discount_price if set, else MRP. Special offer must beat this.
        $sellingPrice = ($mainRow['discount_price'] !== null && (float)$mainRow['discount_price'] > 0)
            ? (float)$mainRow['discount_price']
            : (float)$mainRow['price'];

        // ---- Authoritative calculations (recompute MRPs from live products, never trust client) ----
        // Build the relational gift list, resolving each gift slug -> product + live MRP.
        $giftRows = [];
        $totalMrp = (float)$mainRow['price'];
        foreach ($freeItems as $fi) {
            $gpid = null;
            $gmrp = (float)($fi['mrp'] ?? 0);
            if (!empty($fi['productId'])) {
                $gp = db()->fetchOne("SELECT id, price FROM products WHERE slug=?", [$fi['productId']]);
                if ($gp) { $gpid = (int)$gp['id']; $gmrp = (float)$gp['price']; }
            }
            $gqty = max(1, (int)($fi['qty'] ?? 1));
            $giftRows[] = [
                'product_id' => $gpid,
                'name'       => $fi['name'] ?? '',
                'variant'    => ($fi['variant'] ?? null) ?: null,
                'image'      => ($fi['image'] ?? null) ?: null,
                'mrp'        => $gmrp,
                'qty'        => $gqty,
            ];
            $totalMrp += $gmrp * $gqty;
        }
        // Special price must be below the main product's selling (discount) price, not the MRP.
        if ($special >= $sellingPrice) { echo json_encode(['success'=>false,'message'=>'Special price (₹'.$special.') must be less than the product price (₹'.$sellingPrice.')']); exit; }
        $youSave = max(0, $totalMrp - $special);

        // valid_till: past dates allowed only when EDITING (so admins can fix/extend old offers);
        // new offers must be today or future. Stored as end-of-day DATETIME so "valid through
        // that day" matches the storefront countdown.
        $validDate = $d['valid_till'] ?: null;
        if (!$validDate) { echo json_encode(['success'=>false,'message'=>'Valid Till date is required']); exit; }
        if (empty($d['id']) && $validDate < date('Y-m-d')) {
            echo json_encode(['success'=>false,'message'=>'Valid Till must be today or a future date']); exit;
        }
        $validTill = $validDate . ' 23:59:59';

        $sortOrder  = (int)($d['sort_order'] ?? 0);
        $socialMode = ($d['social_mode'] ?? 'live') === 'manual' ? 'manual' : 'live';
        $socialCount= max(0, (int)($d['social_count'] ?? 0));
        $isTopDeal  = !empty($d['is_top_deal']) ? 1 : 0;

        // Authoritative main-product snapshot: overwrite client-sent price/mrp with the live
        // DB values so totalMrp / youSave / discount can never be tampered or go stale.
        $mainProduct['mrp']   = (float)$mainRow['price'];
        $mainProduct['price'] = $sellingPrice;
        $mainJson = json_encode($mainProduct);   // kept for back-compat; relational is source of truth
        $freeJson = json_encode($freeItems);

        // Theme -> card colours (accent = price/border, gradient = card bg, cta = badges/button).
        // The storefront card reads gradient/accent/cta directly, so derive them from the theme
        // here. Keeps admin simple (one dropdown) while the card renders the chosen palette.
        $theme = $d['theme'] ?? 'orange';
        $themePalette = [
            'orange' => ['accent' => '#ea580c', 'cta' => '#f97316', 'gradient' => 'linear-gradient(135deg,#fff7ed 0%,#ffffff 100%)'],
            'blue'   => ['accent' => '#2563eb', 'cta' => '#3b82f6', 'gradient' => 'linear-gradient(135deg,#eff6ff 0%,#ffffff 100%)'],
            'green'  => ['accent' => '#16a34a', 'cta' => '#22c55e', 'gradient' => 'linear-gradient(135deg,#f0fdf4 0%,#ffffff 100%)'],
            'pink'   => ['accent' => '#db2777', 'cta' => '#ec4899', 'gradient' => 'linear-gradient(135deg,#fdf2f8 0%,#ffffff 100%)'],
            'purple' => ['accent' => '#7c3aed', 'cta' => '#8b5cf6', 'gradient' => 'linear-gradient(135deg,#f5f3ff 0%,#ffffff 100%)'],
            'yellow' => ['accent' => '#ca8a04', 'cta' => '#eab308', 'gradient' => 'linear-gradient(135deg,#fefce8 0%,#ffffff 100%)'],
            'maroon' => ['accent' => '#9f1239', 'cta' => '#be123c', 'gradient' => 'linear-gradient(135deg,#fff1f2 0%,#ffffff 100%)'],
        ];
        $pal      = $themePalette[$theme] ?? $themePalette['orange'];
        $accent   = $pal['accent'];
        $gradient = $pal['gradient'];
        $cta      = $pal['cta'];

        // Write the offer + its gift rows atomically.
        $pdo = db()->getConnection();
        $pdo->beginTransaction();
        try {
            $auditBefore = !empty($d['id']) ? auditRow('offers', (int)$d['id']) : null;
            if (!empty($d['id'])) {
                $offerId = (int)$d['id'];
                db()->execute(
                    "UPDATE offers SET product_id=?,title=?,subtitle=?,theme=?,accent=?,gradient=?,cta=?,main_product=?,free_items=?,special_price=?,total_mrp=?,you_save=?,save_extra=?,valid_till=?,is_active=?,sort_order=?,social_mode=?,social_count=?,is_top_deal=? WHERE id=?",
                    [$productId,$title,$d['subtitle']??'',$theme,$accent,$gradient,$cta,$mainJson,$freeJson,$special,$totalMrp,$youSave,($d['save_extra'] ?? null) ?: null,$validTill,$d['is_active']??1,$sortOrder,$socialMode,$socialCount,$isTopDeal,$offerId]
                );
                $msg = 'Offer updated';
            } else {
                $slug = 'offer-' . substr((string)time(), -6);
                $offerId = (int) db()->insert(
                    "INSERT INTO offers (slug,product_id,title,subtitle,theme,accent,gradient,cta,main_product,free_items,special_price,total_mrp,you_save,save_extra,valid_till,is_active,sort_order,social_mode,social_count,is_top_deal) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [$slug,$productId,$title,$d['subtitle']??'',$theme,$accent,$gradient,$cta,$mainJson,$freeJson,$special,$totalMrp,$youSave,($d['save_extra'] ?? null) ?: null,$validTill,$d['is_active']??1,$sortOrder,$socialMode,$socialCount,$isTopDeal]
                );
                $msg = 'Offer added';
            }

            // Rewrite gift rows (relational source of truth).
            db()->execute("DELETE FROM offer_items WHERE offer_id=?", [$offerId]);
            $i = 0;
            foreach ($giftRows as $g) {
                db()->execute(
                    "INSERT INTO offer_items (offer_id,product_id,name,variant,image,mrp,qty,sort_order) VALUES (?,?,?,?,?,?,?,?)",
                    [$offerId,$g['product_id'],$g['name'],$g['variant'],$g['image'],$g['mrp'],$g['qty'],$i++]
                );
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;   // bubble to the outer handler -> JSON error
        }
        logActivity($auditBefore ? 'updated' : 'created', 'offer', $offerId, $title,
                    auditDiff($auditBefore, auditRow('offers', $offerId)));
        echo json_encode(['success'=>true,'message'=>$msg,'you_save'=>$youSave,'total_mrp'=>$totalMrp]);
    } elseif ($action === 'delete') {
        // Soft-delete: keep the row + its gift rows so an accidental delete can be restored.
        $id = (int)($d['id'] ?? 0); $b = auditRow('offers', $id);
        db()->execute("UPDATE offers SET is_deleted=1, is_active=0 WHERE id=?", [$id]);
        logActivity('deleted', 'offer', $id, $b['title'] ?? null, auditDiff($b, null));
        echo json_encode(['success'=>true,'message'=>'Offer deleted']);
    } elseif ($action === 'restore') {
        $id = (int)($d['id'] ?? 0); $b = auditRow('offers', $id);
        db()->execute("UPDATE offers SET is_deleted=0 WHERE id=?", [$id]);
        logActivity('restored', 'offer', $id, $b['title'] ?? null, auditDiff($b, auditRow('offers', $id)));
        echo json_encode(['success'=>true,'message'=>'Offer restored']);
    } elseif ($action === 'toggle') {
        $id = (int)($d['id'] ?? 0); $b = auditRow('offers', $id);
        db()->execute("UPDATE offers SET is_active = NOT is_active WHERE id=? AND is_deleted=0", [$id]);
        logActivity('toggled', 'offer', $id, $b['title'] ?? null, auditDiff($b, auditRow('offers', $id)));
        echo json_encode(['success'=>true,'message'=>'Status updated']);
    } elseif ($action === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array)($d['ids'] ?? []))));
        $op  = (string)($d['op'] ?? '');
        if (!$ids) { echo json_encode(['success'=>false,'message'=>'No offers selected']); exit; }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($op === 'activate')       { db()->execute("UPDATE offers SET is_active=1 WHERE id IN ($ph) AND is_deleted=0", $ids); $msg = count($ids).' offer(s) activated'; }
        elseif ($op === 'deactivate') { db()->execute("UPDATE offers SET is_active=0 WHERE id IN ($ph) AND is_deleted=0", $ids); $msg = count($ids).' offer(s) deactivated'; }
        elseif ($op === 'delete')     { db()->execute("UPDATE offers SET is_deleted=1, is_active=0 WHERE id IN ($ph)", $ids); $msg = count($ids).' offer(s) deleted'; }
        elseif ($op === 'restore')    { db()->execute("UPDATE offers SET is_deleted=0 WHERE id IN ($ph)", $ids); $msg = count($ids).' offer(s) restored'; }
        else { echo json_encode(['success'=>false,'message'=>'Unknown bulk action']); exit; }
        echo json_encode(['success'=>true,'message'=>$msg]);
    }
    } catch (Throwable $e) {
        echo json_encode(['success'=>false,'message'=>'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// Filter + paginate the offers grid (was: fetch ALL rows).
$search   = sanitize($_GET['search'] ?? '');
$status   = sanitize($_GET['status'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;
$where = ["1=1"]; $params = [];
if ($search) { $where[] = "title LIKE ?"; $params[] = "%$search%"; }
// Soft-delete: hide deleted offers unless the "Deleted" filter is chosen.
if ($status === 'deleted') {
    $where[] = "is_deleted = 1";
} else {
    $where[] = "is_deleted = 0";
    // Status: active = live & not past valid_till; expired = past valid_till; inactive = toggled off.
    if ($status === 'active')       { $where[] = "is_active=1 AND (valid_till IS NULL OR valid_till >= NOW())"; }
    elseif ($status === 'inactive') { $where[] = "is_active=0"; }
    elseif ($status === 'expired')  { $where[] = "valid_till IS NOT NULL AND valid_till < NOW()"; }
}
$whereStr = implode(' AND ', $where);
$total    = (int)(db()->fetchOne("SELECT COUNT(*) c FROM offers WHERE $whereStr", $params)['c'] ?? 0);
$pages    = (int)ceil($total / $per_page);
$offers = db()->fetchAll("SELECT * FROM offers WHERE $whereStr ORDER BY sort_order, id LIMIT $per_page OFFSET $offset", $params);
// Real "bought today" count per product slug (same source as storefront API).
$soldRows = db()->fetchAll(
    "SELECT oi.product_slug AS slug, COUNT(DISTINCT oi.order_id) AS cnt
     FROM order_items oi JOIN orders o ON o.id = oi.order_id
     WHERE DATE(o.created_at) = CURDATE() AND oi.product_slug IS NOT NULL
     GROUP BY oi.product_slug"
);
$soldToday = [];
foreach ($soldRows as $r) $soldToday[$r['slug']] = (int)$r['cnt'];
// Product list for dropdowns (auto-fill name/image/mrp/price)
$prodList = db()->fetchAll("SELECT slug, name, price, discount_price, JSON_EXTRACT(images,'$[0]') AS img FROM products WHERE is_active=1 ORDER BY name");
foreach ($prodList as &$pl) { $pl['img'] = trim((string)$pl['img'], '"'); } unset($pl);
$prodJson = json_encode($prodList);
include __DIR__ . '/../includes/header.php';
?>

<style>
.field-err { color: var(--danger); font-size: .74rem; margin-top: 4px; display: none; }
.field-err.show { display: block; }
.form-control.input-invalid { border-color: var(--danger) !important; box-shadow: 0 0 0 2px rgba(231,76,60,.12); }
</style>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Offers</h1>
        <p>Offer Zone deals shown on the storefront. Total MRP &amp; You-Save are calculated automatically.</p>
    </div>
    <?php if (can('offers','create')): ?><button class="btn btn-gold" onclick="openOfferModal()"><i class="fa-solid fa-plus"></i> Add Offer</button><?php endif; ?>
</div>

<div class="filter-bar fade-in" style="flex-wrap:wrap;gap:8px;">
    <div class="search-wrapper" style="flex:1;min-width:180px;max-width:300px;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search offers by title..." value="<?= htmlspecialchars($search) ?>" onkeydown="if(event.key==='Enter')applyFilters()">
    </div>
    <select class="form-control" id="statusFilter" style="max-width:150px;">
        <option value="">All Status</option>
        <option value="active"   <?= $status==='active'?'selected':'' ?>>Active</option>
        <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
        <option value="expired"  <?= $status==='expired'?'selected':'' ?>>Expired</option>
        <option value="deleted"  <?= $status==='deleted'?'selected':'' ?>>🗑 Deleted</option>
    </select>
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="offers.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
    <label style="display:flex;align-items:center;gap:6px;font-size:.8rem;color:var(--text-secondary);cursor:pointer;margin-left:auto;">
        <input type="checkbox" id="selectAllOffers" onchange="toggleAllOffers(this)" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;"> Select all
    </label>
</div>

<div id="bulkBar" style="display:none;padding:10px 16px;margin-bottom:12px;border:1px solid var(--border-color);border-radius:10px;gap:10px;align-items:center;background:var(--bg-elevated);flex-wrap:wrap;">
    <span id="bulkCount" style="font-size:.82rem;font-weight:600;"></span>
    <?php if($status === 'deleted'): ?>
    <button class="btn btn-ghost btn-sm" onclick="bulkAction('restore')"><i class="fa-solid fa-trash-arrow-up" style="color:var(--success);"></i> Restore</button>
    <?php else: ?>
    <button class="btn btn-ghost btn-sm" onclick="bulkAction('activate')"><i class="fa-solid fa-circle-check" style="color:var(--success);"></i> Activate</button>
    <button class="btn btn-ghost btn-sm" onclick="bulkAction('deactivate')"><i class="fa-solid fa-ban" style="color:var(--warning);"></i> Deactivate</button>
    <button class="btn btn-ghost btn-sm" onclick="bulkAction('delete')" style="color:var(--danger);"><i class="fa-solid fa-trash"></i> Delete</button>
    <?php endif; ?>
    <button class="btn btn-ghost btn-sm" onclick="clearBulk()" style="margin-left:auto;">Clear</button>
</div>

<div class="grid-3 fade-in">
    <?php foreach($offers as $o):
        $mp = json_decode($o['main_product'] ?? 'null', true);
        $fi = json_decode($o['free_items'] ?? '[]', true) ?: [];
        // Compare full datetimes (valid_till is stored as '... 23:59:59'); using a bare date here
        // would disagree with the SQL `valid_till < NOW()` filter for same-day expiries.
        $expired = $o['valid_till'] && strtotime($o['valid_till']) < time();
        // Recompute totals from parts (same as API mapOffer) so the card never shows stale stored values.
        $cardMrp = (float)($mp['mrp'] ?? 0);
        foreach ($fi as $f) $cardMrp += (float)($f['mrp'] ?? 0);
        if ($cardMrp <= 0) $cardMrp = (float)$o['total_mrp'];
        $cardSave = max(0, $cardMrp - (float)$o['special_price']);
        $socialMode = $o['social_mode'] ?? 'live';
        $boughtToday = $socialMode === 'manual' ? (int)($o['social_count'] ?? 0) : ($soldToday[$mp['productId'] ?? ''] ?? 0);
    ?>
    <div class="card" id="offer-card-<?= $o['id'] ?>" style="transition:all .2s;">
        <div class="card-body">
            <div style="display:flex;gap:12px;">
                <input type="checkbox" class="offer-check" value="<?= $o['id'] ?>" onchange="updateBulkBar()" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;flex-shrink:0;margin-top:2px;">
                <img src="<?= htmlspecialchars($mp['image'] ?? '') ?>" style="width:60px;height:60px;object-fit:cover;border-radius:8px;background:#fff;border:1px solid var(--border-color);flex-shrink:0;" onerror="this.style.opacity=.2">
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                        <div class="font-bold" style="font-size:.95rem;"><?= htmlspecialchars($o['title']) ?></div>
                        <span class="badge badge-<?= $o['is_active']?'success':'secondary' ?>" style="flex-shrink:0;"><?= $o['is_active']?'Active':'Inactive' ?></span>
                    </div>
                    <div class="text-muted" style="font-size:.74rem;"><?= htmlspecialchars($o['subtitle'] ?? '') ?></div>
                    <div style="margin-top:4px;">
                        <span class="text-gold font-bold">₹<?= number_format($o['special_price'],0) ?></span>
                        <span class="text-muted" style="text-decoration:line-through;font-size:.74rem;margin-left:5px;">₹<?= number_format($cardMrp,0) ?></span>
                        <span style="color:var(--success);font-size:.72rem;margin-left:4px;">save ₹<?= number_format($cardSave,0) ?></span>
                    </div>
                    <div class="text-muted" style="font-size:.7rem;margin-top:3px;">
                        <?= count($fi) ?> free item(s) · till <?= $o['valid_till'] ? date('d M Y', strtotime($o['valid_till'])) : '—' ?>
                        <?php if($expired): ?><span class="badge badge-warning" style="margin-left:4px;">Expired</span><?php endif; ?>
                    </div>
                    <div style="font-size:.7rem;margin-top:3px;color:var(--text-secondary);">
                        <i class="fa-solid fa-users text-gold"></i> <b><?= $boughtToday ?></b> bought today
                        <span style="opacity:.6;">(<?= $socialMode === 'manual' ? 'custom' : 'live' ?>)</span>
                    </div>
                </div>
            </div>
            <div style="margin-top:12px;display:flex;justify-content:flex-end;gap:6px;">
                <?php if(!empty($o['is_deleted'])): ?>
                <button class="btn btn-ghost btn-sm" onclick="restoreOffer(<?= $o['id'] ?>)" title="Restore offer"><i class="fa-solid fa-trash-arrow-up" style="color:var(--success);"></i> Restore</button>
                <?php else: ?>
                <?php if (can('offers','edit')): ?>
                <button class="btn btn-ghost btn-sm btn-icon" title="Activate/Deactivate" onclick="toggleOffer(<?= $o['id'] ?>)"><i class="fa-solid fa-power-off" style="color:<?= $o['is_active']?'var(--success)':'var(--text-muted)' ?>;"></i></button>
                <button class="btn btn-ghost btn-sm btn-icon" title="Edit" onclick="openOfferModal(<?= htmlspecialchars(json_encode($o), ENT_QUOTES) ?>)"><i class="fa-solid fa-pen"></i></button>
                <?php endif; ?>
                <?php if (can('offers','delete')): ?>
                <button class="btn btn-ghost btn-sm btn-icon" title="Delete" onclick="deleteOffer(<?= $o['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php if(empty($offers)): ?>
<div class="card fade-in" style="text-align:center;padding:48px 20px;">
    <i class="fa-solid fa-tags" style="font-size:2.6rem;color:var(--border-active);margin-bottom:12px;"></i>
    <?php if($search !== '' || $status !== ''): ?>
    <h3 style="font-size:1rem;margin-bottom:4px;">No offers match your filters</h3>
    <p class="text-muted" style="font-size:.85rem;margin-bottom:14px;">Try a different search or status — or reset to see all offers.</p>
    <a href="offers.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset filters</a>
    <?php else: ?>
    <h3 style="font-size:1rem;margin-bottom:4px;">No offers yet</h3>
    <p class="text-muted" style="font-size:.85rem;margin-bottom:14px;">Create a special-price deal with free gift items to feature in the storefront Offer Zone.</p>
    <button class="btn btn-gold btn-sm" onclick="openOfferModal()"><i class="fa-solid fa-plus"></i> Add your first offer</button>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if($pages > 1): ?>
<div class="pagination" style="margin-top:16px;">
    <?php
    // Compact pagination: first, last, and a window around the current page (… for gaps).
    $range = 2; $shown = [];
    for ($i = 1; $i <= $pages; $i++) {
        if ($i == 1 || $i == $pages || ($i >= $page - $range && $i <= $page + $range)) $shown[] = $i;
    }
    if ($page > 1): ?><div class="page-item" onclick="goOffersPage(<?= $page-1 ?>)">‹</div><?php endif;
    $prev = 0;
    foreach ($shown as $i):
        if ($prev && $i - $prev > 1): ?><div class="page-item" style="pointer-events:none;opacity:.5;">…</div><?php endif; ?>
        <div class="page-item <?= $i==$page?'active':'' ?>" onclick="goOffersPage(<?= $i ?>)"><?= $i ?></div>
        <?php $prev = $i;
    endforeach;
    if ($page < $pages): ?><div class="page-item" onclick="goOffersPage(<?= $page+1 ?>)">›</div><?php endif; ?>
</div>
<script>function goOffersPage(p){const q=new URLSearchParams(window.location.search);q.set('page',p);window.location.href='offers.php?'+q.toString();}</script>
<?php endif; ?>

<!-- Modal -->
<div class="modal-overlay" id="offerModal" style="display:none;" onclick="if(event.target===this)closeModal('offerModal')">
    <div class="modal-box" style="max-width:600px;text-align:left;padding:0;">
        <div class="modal-head" style="padding:18px 22px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
            <h2 id="offerModalTitle" style="font-family:'Playfair Display',serif;font-size:1.05rem;background:var(--gold-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Add Offer</h2>
            <button class="close-btn" onclick="closeModal('offerModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:22px;max-height:74vh;overflow:auto;">
            <input type="hidden" id="offer_id">
            <div class="form-group"><label class="form-label">Title *</label><input type="text" class="form-control" id="offer_title" placeholder="e.g. Youni X File Combo" oninput="clearErr('offer_title')"><div class="field-err" id="err_offer_title"></div></div>
            <div class="form-group"><label class="form-label">Subtitle</label><input type="text" class="form-control" id="offer_subtitle" placeholder="(MOQ 20 Pack)"></div>

            <hr style="border:none;border-top:1px solid var(--border-color);margin:14px 0;">
            <label class="form-label" style="font-weight:700;">Main Product *</label>
            <div class="text-muted" style="font-size:.72rem;margin-bottom:6px;">Pick a product — name, image &amp; MRP fill in automatically.</div>
            <div class="form-group">
                <select class="form-control" id="offer_mp_select" onchange="onPickMain(this.value)">
                    <option value="">— Select product —</option>
                    <?php foreach($prodList as $p): ?>
                        <option value="<?= htmlspecialchars($p['slug']) ?>"><?= htmlspecialchars($p['name']) ?> (MRP ₹<?= number_format($p['price'],0) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="field-err" id="err_offer_mp_select"></div>
            </div>
            <div id="offer_mp_preview" style="display:none;align-items:center;gap:10px;background:var(--bg-elevated);border-radius:8px;padding:8px;margin-bottom:10px;">
                <img id="offer_mp_img_el" src="" style="width:48px;height:48px;object-fit:cover;border-radius:6px;background:#fff;">
                <div style="flex:1;font-size:.82rem;"><span id="offer_mp_name_el" class="font-bold"></span><br><span class="text-muted">MRP ₹<span id="offer_mp_mrp_el"></span></span></div>
            </div>
            <input type="hidden" id="offer_mp_id"><input type="hidden" id="offer_mp_name"><input type="hidden" id="offer_mp_image"><input type="hidden" id="offer_mp_mrp"><input type="hidden" id="offer_mp_price">

            <hr style="border:none;border-top:1px solid var(--border-color);margin:14px 0;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <label class="form-label" style="font-weight:700;margin:0;">Free Items (gifts)</label>
                <button type="button" class="btn btn-ghost btn-sm" onclick="addFreeItemRow()"><i class="fa-solid fa-plus"></i> Add</button>
            </div>
            <div class="text-muted" style="font-size:.72rem;margin-bottom:6px;">Pick gift products. Their MRP adds to Total MRP automatically.</div>
            <div id="freeItemsContainer"></div>

            <hr style="border:none;border-top:1px solid var(--border-color);margin:14px 0;">
            <div class="form-row" style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><label class="form-label">Special Price (₹) *</label><input type="number" class="form-control" id="offer_special" oninput="clearErr('offer_special');recalc()"><div class="field-err" id="err_offer_special"></div></div>
                <div class="form-group" style="flex:1;"><label class="form-label">Save Extra (note)</label><input type="text" class="form-control" id="offer_saveextra" placeholder="e.g. free shipping"></div>
            </div>
            <!-- Auto-calculated summary -->
            <div style="background:var(--bg-elevated);border:1px solid var(--border-color);border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:.85rem;">
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Total MRP (auto)</span><span class="font-bold">₹<span id="calc_totalmrp">0</span></span></div>
                <div style="display:flex;justify-content:space-between;margin-top:4px;"><span class="text-muted">You Save (auto)</span><span class="font-bold" style="color:var(--success);">₹<span id="calc_yousave">0</span> (<span id="calc_pct">0</span>%)</span></div>
                <div id="calc_warn" style="color:var(--danger);font-size:.76rem;margin-top:6px;display:none;"><i class="fa-solid fa-triangle-exclamation"></i> Special price must be less than the product's selling price.</div>
            </div>

            <div class="form-row" style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><label class="form-label">Valid Till *</label><input type="date" class="form-control" id="offer_validtill" oninput="clearErr('offer_validtill')"><div class="field-err" id="err_offer_validtill"></div></div>
                <div class="form-group" style="flex:1;"><label class="form-label">Sort Order</label><input type="number" class="form-control" id="offer_sortorder" value="0"></div>
            </div>
            <div class="form-row" style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><label class="form-label">Theme color</label>
                    <select class="form-control" id="offer_theme">
                        <option value="orange">Orange</option><option value="blue">Blue</option><option value="green">Green</option><option value="pink">Pink</option>
                        <option value="purple">Purple</option><option value="yellow">Yellow</option><option value="maroon">Maroon</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1;"><label class="form-label">Status</label>
                    <select class="form-control" id="offer_status"><option value="1">Active</option><option value="0">Inactive</option></select>
                </div>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.85rem;"><input type="checkbox" id="offer_topdeal"> <i class="fa-solid fa-star text-gold"></i> Mark as <b>Top Deal</b> (gold badge on card)</label>
            </div>

            <hr style="border:none;border-top:1px solid var(--border-color);margin:14px 0;">
            <label class="form-label" style="font-weight:700;">"X clinics bought today" badge</label>
            <div class="form-row" style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><label class="form-label">Source</label>
                    <select class="form-control" id="offer_social_mode" onchange="toggleSocial()">
                        <option value="live">Live (real orders today)</option>
                        <option value="manual">Custom number</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1;" id="social_count_wrap"><label class="form-label">Custom count</label><input type="number" min="0" class="form-control" id="offer_social_count" value="0"></div>
            </div>
            <div class="text-muted" style="font-size:.72rem;margin-top:-6px;">Live = auto-counts real orders placed today. Custom = show a fixed number you set.</div>
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:10px;">
            <button class="btn btn-ghost" onclick="closeModal('offerModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveOffer()"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        </div>
    </div>
</div>

<script>
const PRODUCTS = <?= $prodJson ?: '[]' ?>;
const prodBySlug = Object.fromEntries(PRODUCTS.map(p => [p.slug, p]));

function toggleSocial() {
    const manual = document.getElementById('offer_social_mode').value === 'manual';
    document.getElementById('social_count_wrap').style.display = manual ? '' : 'none';
}

function onPickMain(slug) {
    const p = prodBySlug[slug];
    if (!p) { document.getElementById('offer_mp_preview').style.display='none'; document.getElementById('offer_mp_id').value=''; recalc(); return; }
    document.getElementById('offer_mp_id').value    = p.slug;
    document.getElementById('offer_mp_name').value  = p.name;
    document.getElementById('offer_mp_image').value = p.img || '';
    document.getElementById('offer_mp_mrp').value   = p.price;
    document.getElementById('offer_mp_price').value = p.discount_price ?? p.price;
    document.getElementById('offer_mp_name_el').textContent = p.name;
    document.getElementById('offer_mp_mrp_el').textContent  = Number(p.price).toLocaleString('en-IN');
    document.getElementById('offer_mp_img_el').src = p.img || '';
    document.getElementById('offer_mp_preview').style.display = 'flex';
    recalc();
}

function freeItemRowHtml(it={}) {
    let opts = PRODUCTS.map(p => `<option value="${p.slug}" ${it.productId===p.slug?'selected':''}>${p.name.replace(/</g,'&lt;')} (MRP ₹${Number(p.price).toLocaleString('en-IN')})</option>`).join('');
    // If the saved gift's product was deleted, keep it visible so it isn't silently dropped on save.
    if (it.productId && !prodBySlug[it.productId]) {
        opts += `<option value="${it.productId}" selected>${(it.name||'?').replace(/</g,'&lt;')} [removed]</option>`;
    }
    return `<div class="free-item-row" style="display:flex;gap:6px;margin-bottom:6px;align-items:center;">
        <select class="form-control" data-fi-select onchange="onPickFree(this)" style="flex:1;"><option value="">— Select gift —</option>${opts}</select>
        <input class="form-control" data-fi-name type="hidden" value="${(it.name||'').replace(/"/g,'&quot;')}">
        <input class="form-control" data-fi-mrp type="hidden" value="${it.mrp||0}">
        <input class="form-control" data-fi-image type="hidden" value="${(it.image||'').replace(/"/g,'&quot;')}">
        <button type="button" class="btn btn-ghost btn-sm" onclick="this.parentElement.remove();recalc()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
    </div>`;
}
function addFreeItemRow(it) { document.getElementById('freeItemsContainer').insertAdjacentHTML('beforeend', freeItemRowHtml(it||{})); recalc(); }
function onPickFree(sel) {
    const row = sel.closest('.free-item-row'); const p = prodBySlug[sel.value];
    row.querySelector('[data-fi-name]').value  = p ? p.name : '';
    row.querySelector('[data-fi-mrp]').value   = p ? p.price : 0;
    row.querySelector('[data-fi-image]').value = p ? (p.img||'') : '';
    recalc();
}

function collectFree() {
    const out = [];
    document.querySelectorAll('#freeItemsContainer .free-item-row').forEach(row => {
        const id = row.querySelector('[data-fi-select]').value;
        if (id) out.push({ productId:id, name:row.querySelector('[data-fi-name]').value, mrp:parseFloat(row.querySelector('[data-fi-mrp]').value)||0, image:row.querySelector('[data-fi-image]').value });
    });
    return out;
}
function recalc() {
    const mainMrp = parseFloat(document.getElementById('offer_mp_mrp').value)||0;
    const free = collectFree();
    const totalMrp = mainMrp + free.reduce((s,f)=>s+(f.mrp||0),0);
    const special = parseFloat(document.getElementById('offer_special').value)||0;
    const youSave = Math.max(0, totalMrp - special);
    const pct = totalMrp>0 ? Math.round((youSave/totalMrp)*100) : 0;
    document.getElementById('calc_totalmrp').textContent = totalMrp.toLocaleString('en-IN');
    document.getElementById('calc_yousave').textContent  = youSave.toLocaleString('en-IN');
    document.getElementById('calc_pct').textContent      = pct;
    // Warn when special price is not below the product's selling (discount) price.
    const sellingPrice = (parseFloat(document.getElementById('offer_mp_price').value)||0) || mainMrp;
    const warn = document.getElementById('calc_warn');
    const bad = special>=sellingPrice && sellingPrice>0;
    warn.style.display = bad ? 'block' : 'none';
    if (bad) warn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Special price must be less than the product price (₹' + sellingPrice.toLocaleString('en-IN') + ')';
}

function openOfferModal(o = null) {
    const mp = o ? (typeof o.main_product==='string'?JSON.parse(o.main_product||'null'):o.main_product) : null;
    const fi = o ? (typeof o.free_items==='string'?JSON.parse(o.free_items||'[]'):o.free_items) || [] : [];
    document.getElementById('offer_id').value       = o?.id || '';
    document.getElementById('offer_title').value    = o?.title || '';
    document.getElementById('offer_subtitle').value = o?.subtitle || '';
    document.getElementById('offer_special').value  = o?.special_price || '';
    document.getElementById('offer_saveextra').value= o?.save_extra || '';
    // Extract YYYY-MM-DD from any stored format ("2026-06-09 23:59:59", ISO, etc.).
    // type=date only accepts a bare YYYY-MM-DD, so a datetime would silently render blank.
    const vtMatch = o?.valid_till ? String(o.valid_till).match(/\d{4}-\d{2}-\d{2}/) : null;
    document.getElementById('offer_validtill').value = vtMatch ? vtMatch[0] : '';
    document.getElementById('offer_sortorder').value= o?.sort_order ?? 0;
    document.getElementById('offer_theme').value    = o?.theme || 'orange';
    document.getElementById('offer_status').value   = o?.is_active ?? 1;
    document.getElementById('offer_social_mode').value  = o?.social_mode || 'live';
    document.getElementById('offer_social_count').value = o?.social_count ?? 0;
    document.getElementById('offer_topdeal').checked    = !!(o && Number(o.is_top_deal));
    toggleSocial();
    // main product — preserve the offer's SAVED values (do not refetch from current product,
    // so editing never silently changes the offer's stored price/image).
    document.getElementById('offer_mp_select').value = mp?.productId || '';
    if (mp?.productId) {
        const dead = !prodBySlug[mp.productId];
        document.getElementById('offer_mp_id').value    = mp.productId;
        document.getElementById('offer_mp_name').value  = mp.name || '';
        document.getElementById('offer_mp_image').value = mp.image || '';
        document.getElementById('offer_mp_mrp').value   = mp.mrp || 0;
        document.getElementById('offer_mp_price').value = mp.price || 0;
        document.getElementById('offer_mp_name_el').textContent = (mp.name || '') + (dead ? ' [product removed]' : '');
        document.getElementById('offer_mp_mrp_el').textContent  = Number(mp.mrp || 0).toLocaleString('en-IN');
        document.getElementById('offer_mp_img_el').src = mp.image || '';
        document.getElementById('offer_mp_preview').style.display = 'flex';
        // ensure dropdown shows the saved product even if it was deleted
        const selEl = document.getElementById('offer_mp_select');
        if (dead && !selEl.querySelector(`option[value="${mp.productId}"]`)) {
            selEl.insertAdjacentHTML('beforeend', `<option value="${mp.productId}" selected>${(mp.name||'?')} [removed]</option>`);
        }
    } else {
        ['offer_mp_id','offer_mp_name','offer_mp_image','offer_mp_mrp','offer_mp_price'].forEach(i=>document.getElementById(i).value='');
        document.getElementById('offer_mp_preview').style.display='none';
    }
    // free items
    const cont = document.getElementById('freeItemsContainer'); cont.innerHTML='';
    fi.forEach(addFreeItemRow);
    recalc();
    document.getElementById('offerModalTitle').textContent = o ? 'Edit Offer' : 'Add Offer';
    clearAllErrs();
    openModal('offerModal');
}

// Inline field errors (shown under the field + red border) — friendlier than a corner toast.
function setErr(id, msg) {
    const el = document.getElementById(id);
    const box = document.getElementById('err_' + id);
    if (el) el.classList.add('input-invalid');
    if (box) { box.textContent = msg; box.classList.add('show'); }
}
function clearErr(id) {
    const el = document.getElementById(id);
    const box = document.getElementById('err_' + id);
    if (el) el.classList.remove('input-invalid');
    if (box) { box.textContent = ''; box.classList.remove('show'); }
}
function clearAllErrs() {
    ['offer_title','offer_mp_select','offer_special','offer_validtill'].forEach(clearErr);
}

async function saveOffer() {
    clearAllErrs();
    const title = document.getElementById('offer_title').value.trim();
    const special = parseFloat(document.getElementById('offer_special').value)||0;
    const mpId = document.getElementById('offer_mp_id').value;
    const main_product = {
        productId: mpId,
        name:  document.getElementById('offer_mp_name').value,
        image: document.getElementById('offer_mp_image').value,
        mrp:   parseFloat(document.getElementById('offer_mp_mrp').value)||0,
        price: parseFloat(document.getElementById('offer_mp_price').value)||0,
        variant: 'Any Size',
    };
    const free_items = collectFree();
    const sellingPrice = main_product.price > 0 ? main_product.price : main_product.mrp;
    const validTill = document.getElementById('offer_validtill').value;

    // Collect all problems, highlight every bad field, focus the first one.
    let firstBad = null;
    const fail = (id, msg) => { setErr(id, msg); if (!firstBad) firstBad = id; };
    if (!title)                    fail('offer_title', 'Title is required');
    if (!mpId)                     fail('offer_mp_select', 'Select a main product');
    if (special <= 0)              fail('offer_special', 'Special price must be greater than 0');
    else if (special >= sellingPrice) fail('offer_special', 'Must be less than the product price (₹' + sellingPrice.toLocaleString('en-IN') + ')');
    if (!validTill)                fail('offer_validtill', 'Valid Till date is required');
    if (firstBad) { document.getElementById(firstBad)?.focus(); return; }

    const data = { action:'save', id:document.getElementById('offer_id').value, title,
        subtitle:document.getElementById('offer_subtitle').value,
        special_price:special,
        save_extra:document.getElementById('offer_saveextra').value,
        valid_till:validTill,
        sort_order:document.getElementById('offer_sortorder').value,
        theme:document.getElementById('offer_theme').value,
        is_active:document.getElementById('offer_status').value,
        social_mode:document.getElementById('offer_social_mode').value,
        social_count:document.getElementById('offer_social_count').value,
        is_top_deal:document.getElementById('offer_topdeal').checked ? 1 : 0,
        main_product, free_items };
    const res = await fetch('offers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});
    const r = await res.json();
    if(r.success){ showToast(r.message,'success'); closeModal('offerModal'); setTimeout(()=>location.reload(),800); }
    else showToast(r.message,'danger');
}
function applyFilters(){
    const p=new URLSearchParams({search:document.getElementById('searchInput')?.value||'',status:document.getElementById('statusFilter')?.value||''});
    [...p.entries()].forEach(([k,v])=>{if(!v)p.delete(k);});
    window.location.href='offers.php?'+p.toString();
}
// ---- Bulk selection ----
function selectedOfferIds(){return [...document.querySelectorAll('.offer-check:checked')].map(c=>parseInt(c.value));}
function updateBulkBar(){
    const n=selectedOfferIds().length;
    const bar=document.getElementById('bulkBar');
    bar.style.display=n?'flex':'none';
    if(n)document.getElementById('bulkCount').textContent=n+' selected';
    const all=document.getElementById('selectAllOffers'); const total=document.querySelectorAll('.offer-check').length;
    if(all)all.checked=n>0&&n===total;
}
function toggleAllOffers(cb){document.querySelectorAll('.offer-check').forEach(c=>c.checked=cb.checked);updateBulkBar();}
function clearBulk(){document.querySelectorAll('.offer-check').forEach(c=>c.checked=false);const a=document.getElementById('selectAllOffers');if(a)a.checked=false;updateBulkBar();}
async function bulkAction(op){
    const ids=selectedOfferIds(); if(!ids.length)return;
    if(!confirm(`${op.charAt(0).toUpperCase()+op.slice(1)} ${ids.length} offer(s)?`))return;
    const res=await fetch('offers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'bulk',op,ids})});
    const r=await res.json();
    showToast(r.message||(r.success?'Done':'Failed'), r.success?'success':'danger');
    if(r.success)setTimeout(()=>location.reload(),800);
}
function toggleOffer(id){
    fetch('offers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'toggle',id})})
    .then(r=>r.json()).then(d=>{if(d.success){showToast('Status updated','success');setTimeout(()=>location.reload(),600);}});
}
function restoreOffer(id){
    fetch('offers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'restore',id})})
    .then(r=>r.json()).then(d=>{if(d.success){showToast('Offer restored','success');setTimeout(()=>location.reload(),600);}});
}
function deleteOffer(id) {
    showConfirm('Delete Offer','This hides the offer from the storefront. You can restore it from the "Deleted" filter. Continue?', async () => {
        const res = await fetch('offers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
        const r = await res.json();
        if(r.success){ showToast('Offer deleted','success'); const el=document.getElementById(`offer-card-${id}`); if(el){el.style.opacity='0';setTimeout(()=>el.remove(),300);} }
        else showToast(r.message,'danger');
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
