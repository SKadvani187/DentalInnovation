<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Combos';
requireView('combos');

// Image upload (multipart) — reuse products image folder.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['combo_image'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    requireAction('combos', 'edit');
    $upload_dir = __DIR__ . '/../assets/images/products/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $file = $_FILES['combo_image'];
    // Check PHP's upload status first (a file over upload_max_filesize arrives with error=1,
    // empty tmp_name and size=0 — would otherwise fail later as a misleading "Upload failed").
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit (upload_max_filesize).',
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
    $fname = 'combo_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $fname)) {
        echo json_encode(['success'=>true,'url'=> APP_URL.'/assets/images/products/'.$fname]);
    } else { echo json_encode(['success'=>false,'message'=>'Upload failed']); }
    exit;
}

// AJAX JSON actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    // Never let a PHP warning/exception leak HTML into the JSON response (breaks res.json()).
    try {
    $d = json_decode(file_get_contents('php://input'), true);
    $action = $d['action'] ?? '';
    requireAction('combos', rbacCrudVerb($action, $d));

    if ($action === 'save') {
        $name  = trim($d['name'] ?? '');
        $items = is_array($d['items'] ?? null) ? $d['items'] : [];
        $price = (float)($d['price'] ?? 0);
        if ($name === '')       { echo json_encode(['success'=>false,'message'=>'Name is required']); exit; }
        // A combo must bundle at least 2 products (a 1-item combo is just that product's listing).
        if (count($items) < 2)  { echo json_encode(['success'=>false,'message'=>'A combo needs at least 2 products']); exit; }

        // Authoritative recompute from LIVE products (never trust client price/mrp).
        // mrp = Σ product MRP (strike-through anchor); sellTotal = Σ product selling price.
        $mrp = 0; $sellTotal = 0; $cleanItems = [];
        foreach ($items as $it) {
            $slug = $it['productId'] ?? '';
            if ($slug === '') continue;
            $p = db()->fetchOne("SELECT name, price, discount_price, JSON_EXTRACT(images,'$[0]') AS img FROM products WHERE slug=?", [$slug]);
            if (!$p) continue;
            $pMrp  = (float)$p['price'];
            $pSell = ($p['discount_price'] !== null && (float)$p['discount_price'] > 0) ? (float)$p['discount_price'] : $pMrp;
            $qty   = max(1, (int)($it['qty'] ?? 1));
            $mrp       += $pMrp  * $qty;
            $sellTotal += $pSell * $qty;
            $cleanItems[] = [
                'productId' => $slug,
                'name'      => $p['name'],
                'mrp'       => $pMrp,
                'sell'      => $pSell,
                'image'     => trim((string)$p['img'], '"') ?: ($it['image'] ?? null),
                'qty'       => $qty,
            ];
        }
        if (count($cleanItems) < 2) { echo json_encode(['success'=>false,'message'=>'A combo needs at least 2 valid products']); exit; }
        if ($mrp <= 0)          { echo json_encode(['success'=>false,'message'=>'Selected products have no MRP']); exit; }
        if ($price <= 0)        { echo json_encode(['success'=>false,'message'=>'Combo price must be greater than 0']); exit; }
        if ($price > $mrp)      { echo json_encode(['success'=>false,'message'=>'Combo price cannot exceed total MRP (₹'.$mrp.')']); exit; }
        // Real-deal check: combo must beat buying each item separately at its normal selling price.
        if ($price >= $sellTotal) { echo json_encode(['success'=>false,'message'=>'Combo price must be less than buying separately (₹'.$sellTotal.')']); exit; }
        $disc  = ($mrp > 0 && $price < $mrp) ? round((($mrp - $price) / $mrp) * 100, 0) : 0;
        $images_json = !empty($d['images']) ? json_encode($d['images']) : null;
        $items_json  = json_encode($cleanItems);
        $stock     = max(0, (int)($d['stock'] ?? 0));
        $inStock   = $stock > 0 ? 1 : 0;
        $metaTitle = trim((string)($d['meta_title'] ?? '')) ?: null;
        $metaDesc  = trim((string)($d['meta_description'] ?? '')) ?: null;
        $selfId    = (int)($d['id'] ?? 0);
        // Slug: admin-editable, falls back to the name; kept UNIQUE (auto-suffixed on collision).
        $slug = generateSlug(trim((string)($d['slug'] ?? '')) !== '' ? $d['slug'] : $name) ?: 'combo';
        $slugBase = $slug; $n = 1;
        while (db()->fetchOne("SELECT id FROM combos WHERE slug=? AND id<>?", [$slug, $selfId])) { $slug = $slugBase . '-' . (++$n); }
        if (!empty($d['id'])) {
            db()->execute(
                "UPDATE combos SET slug=?,name=?,description=?,meta_title=?,meta_description=?,mrp=?,price=?,discount_percent=?,image=?,images=?,items=?,stock=?,in_stock=?,is_active=? WHERE id=?",
                [$slug,$name,$d['description']??'',$metaTitle,$metaDesc,$mrp,$price,$disc,($d['image'] ?? null)?:null,$images_json,$items_json,$stock,$inStock,$d['is_active']??1,$d['id']]
            );
            echo json_encode(['success'=>true,'message'=>'Combo updated']);
        } else {
            db()->insert(
                "INSERT INTO combos (slug,name,description,meta_title,meta_description,mrp,price,discount_percent,image,images,items,stock,in_stock,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$slug,$name,$d['description']??'',$metaTitle,$metaDesc,$mrp,$price,$disc,($d['image'] ?? null)?:null,$images_json,$items_json,$stock,$inStock,$d['is_active']??1]
            );
            echo json_encode(['success'=>true,'message'=>'Combo added']);
        }
    } elseif ($action === 'delete') {
        // Soft-delete: keep the row so order history referencing the combo stays intact.
        db()->execute("UPDATE combos SET is_deleted=1, is_active=0 WHERE id=?", [(int)($d['id'] ?? 0)]);
        echo json_encode(['success'=>true,'message'=>'Combo deleted']);
    } elseif ($action === 'restore') {
        db()->execute("UPDATE combos SET is_deleted=0 WHERE id=?", [(int)($d['id'] ?? 0)]);
        echo json_encode(['success'=>true,'message'=>'Combo restored']);
    } elseif ($action === 'toggle') {
        db()->execute("UPDATE combos SET is_active = NOT is_active WHERE id=? AND is_deleted=0", [(int)($d['id'] ?? 0)]);
        echo json_encode(['success'=>true,'message'=>'Status updated']);
    } elseif ($action === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array)($d['ids'] ?? []))));
        $op  = (string)($d['op'] ?? '');
        if (!$ids) { echo json_encode(['success'=>false,'message'=>'No combos selected']); exit; }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($op === 'activate')       { db()->execute("UPDATE combos SET is_active=1 WHERE id IN ($ph) AND is_deleted=0", $ids); $msg = count($ids).' combo(s) activated'; }
        elseif ($op === 'deactivate') { db()->execute("UPDATE combos SET is_active=0 WHERE id IN ($ph) AND is_deleted=0", $ids); $msg = count($ids).' combo(s) deactivated'; }
        elseif ($op === 'delete')     { db()->execute("UPDATE combos SET is_deleted=1, is_active=0 WHERE id IN ($ph)", $ids); $msg = count($ids).' combo(s) deleted'; }
        elseif ($op === 'restore')    { db()->execute("UPDATE combos SET is_deleted=0 WHERE id IN ($ph)", $ids); $msg = count($ids).' combo(s) restored'; }
        else { echo json_encode(['success'=>false,'message'=>'Unknown bulk action']); exit; }
        echo json_encode(['success'=>true,'message'=>$msg]);
    }
    } catch (Throwable $e) {
        // Log the real reason; only expose internals (column names, SQL) on local dev.
        error_log('Combos handler error: ' . $e->getMessage());
        $msg = (defined('APP_DEBUG') && APP_DEBUG) ? ('Server error: ' . $e->getMessage()) : 'Server error. Please try again.';
        echo json_encode(['success'=>false,'message'=>$msg]);
    }
    exit;
}

// Filters + paginate the combos grid.
$search   = sanitize($_GET['search'] ?? '');
$status   = sanitize($_GET['status'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;
$where = ["1=1"]; $params = [];
if ($search) { $where[] = "name LIKE ?"; $params[] = "%$search%"; }
// Soft-delete: hide deleted combos unless the "Deleted" filter is chosen.
if ($status === 'deleted') {
    $where[] = "is_deleted = 1";
} else {
    $where[] = "is_deleted = 0";
    if ($status !== '') { $where[] = "is_active = ?"; $params[] = (int)$status; }
}
$whereStr = implode(' AND ', $where);
$total    = (int)(db()->fetchOne("SELECT COUNT(*) c FROM combos WHERE $whereStr", $params)['c'] ?? 0);
$pages    = (int)ceil($total / $per_page);
$combos = db()->fetchAll("SELECT * FROM combos WHERE $whereStr ORDER BY sort_order, id LIMIT $per_page OFFSET $offset", $params);
// Product list for the "what's inside" item picker (auto-fill name/image/mrp).
$prodList = db()->fetchAll("SELECT slug, name, price, discount_price, JSON_EXTRACT(images,'$[0]') AS img FROM products WHERE is_active=1 ORDER BY name");
foreach ($prodList as &$pl) { $pl['img'] = trim((string)$pl['img'], '"'); } unset($pl);
$prodJson = json_encode($prodList);
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Combos</h1>
        <p>Bundle deals shown on the storefront combos page</p>
    </div>
    <?php if (can('combos','create')): ?><button class="btn btn-gold" onclick="openComboModal()"><i class="fa-solid fa-plus"></i> Add Combo</button><?php endif; ?>
</div>

<div class="filter-bar fade-in" style="flex-wrap:wrap;gap:8px;">
    <div class="search-wrapper" style="flex:1;min-width:180px;max-width:300px;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search combos..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select class="form-control" id="statusFilter" style="max-width:140px;">
        <option value="">All Status</option>
        <option value="1" <?= $status==='1'?'selected':'' ?>>Active</option>
        <option value="0" <?= $status==='0'?'selected':'' ?>>Inactive</option>
        <option value="deleted" <?= $status==='deleted'?'selected':'' ?>>🗑 Deleted</option>
    </select>
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="combos.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
    <label style="display:flex;align-items:center;gap:6px;font-size:.8rem;color:var(--text-secondary);cursor:pointer;margin-left:auto;">
        <input type="checkbox" id="selectAllCombos" onchange="toggleAllCombos(this)" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;"> Select all
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
    <?php foreach($combos as $c): ?>
    <div class="card" id="combo-card-<?= $c['id'] ?>" style="transition:all .2s;">
        <div class="card-body">
            <div style="display:flex;gap:12px;">
                <input type="checkbox" class="combo-check" value="<?= $c['id'] ?>" onchange="updateBulkBar()" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;flex-shrink:0;margin-top:2px;">
                <img src="<?= htmlspecialchars($c['image'] ?: '') ?>" style="width:64px;height:64px;object-fit:cover;border-radius:8px;background:#fff;border:1px solid var(--border-color);flex-shrink:0;" onerror="this.style.opacity=.2">
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                        <div class="font-bold" style="font-size:.95rem;line-height:1.3;"><?= htmlspecialchars($c['name']) ?></div>
                        <span class="badge badge-<?= $c['is_active']?'success':'secondary' ?>" style="flex-shrink:0;"><?= $c['is_active']?'Active':'Inactive' ?></span>
                    </div>
                    <div style="margin-top:4px;">
                        <span class="text-muted" style="text-decoration:line-through;font-size:.78rem;">₹<?= number_format($c['mrp'],0) ?></span>
                        <span class="text-gold font-bold" style="margin-left:6px;">₹<?= number_format($c['price'],0) ?></span>
                        <span style="color:var(--success);font-size:.72rem;margin-left:4px;"><?= (int)$c['discount_percent'] ?>% OFF</span>
                    </div>
                    <?php $cSave = max(0, (float)$c['mrp'] - (float)$c['price']); $cStock = (int)($c['stock'] ?? 0); ?>
                    <div style="margin-top:5px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:.72rem;">
                        <span style="color:var(--success);font-weight:700;"><i class="fa-solid fa-tag"></i> Save ₹<?= number_format($cSave,0) ?></span>
                        <span style="color:<?= $cStock===0 ? 'var(--danger)' : ($cStock<=10 ? '#ea580c' : 'var(--text-secondary)') ?>;">
                            <i class="fa-solid fa-box"></i> Stock: <?= $cStock ?><?= $cStock===0 ? ' (Out)' : ($cStock<=10 ? ' (Low)' : '') ?>
                        </span>
                    </div>
                </div>
            </div>
            <div style="margin-top:14px;display:flex;justify-content:flex-end;gap:6px;">
                <?php if(!empty($c['is_deleted'])): ?>
                <button class="btn btn-ghost btn-sm" onclick="restoreCombo(<?= $c['id'] ?>)" title="Restore combo"><i class="fa-solid fa-trash-arrow-up" style="color:var(--success);"></i> Restore</button>
                <?php else: ?>
                <?php if (can('combos','edit')): ?>
                <button class="btn btn-ghost btn-sm btn-icon" title="Activate/Deactivate" onclick="toggleCombo(<?= $c['id'] ?>)"><i class="fa-solid fa-power-off" style="color:<?= $c['is_active']?'var(--success)':'var(--text-muted)' ?>;"></i></button>
                <button class="btn btn-ghost btn-sm btn-icon" title="Edit" onclick="openComboModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)"><i class="fa-solid fa-pen"></i></button>
                <?php endif; ?>
                <?php if (can('combos','delete')): ?>
                <button class="btn btn-ghost btn-sm btn-icon" title="Delete" onclick="deleteCombo(<?= $c['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php if(empty($combos)): ?>
<div class="card fade-in" style="text-align:center;padding:48px 20px;">
    <i class="fa-solid fa-layer-group" style="font-size:2.6rem;color:var(--border-active);margin-bottom:12px;"></i>
    <?php if($search !== '' || $status !== ''): ?>
    <h3 style="font-size:1rem;margin-bottom:4px;">No combos match your filters</h3>
    <p class="text-muted" style="font-size:.85rem;margin-bottom:14px;">Try a different search or status — or reset to see all combos.</p>
    <a href="combos.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset filters</a>
    <?php else: ?>
    <h3 style="font-size:1rem;margin-bottom:4px;">No combos yet</h3>
    <p class="text-muted" style="font-size:.85rem;margin-bottom:14px;">Bundle 2+ products into a deal that beats buying them separately.</p>
    <button class="btn btn-gold btn-sm" onclick="openComboModal()"><i class="fa-solid fa-plus"></i> Add your first combo</button>
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
    if ($page > 1): ?><div class="page-item" onclick="goCombosPage(<?= $page-1 ?>)">‹</div><?php endif;
    $prev = 0;
    foreach ($shown as $i):
        if ($prev && $i - $prev > 1): ?><div class="page-item" style="pointer-events:none;opacity:.5;">…</div><?php endif; ?>
        <div class="page-item <?= $i==$page?'active':'' ?>" onclick="goCombosPage(<?= $i ?>)"><?= $i ?></div>
        <?php $prev = $i;
    endforeach;
    if ($page < $pages): ?><div class="page-item" onclick="goCombosPage(<?= $page+1 ?>)">›</div><?php endif; ?>
</div>
<script>function goCombosPage(p){const q=new URLSearchParams(window.location.search);q.set('page',p);window.location.href='combos.php?'+q.toString();}</script>
<?php endif; ?>

<!-- Modal -->
<div class="modal-overlay" id="comboModal" style="display:none;" onclick="if(event.target===this)closeModal('comboModal')">
    <div class="modal-box" style="max-width:480px;text-align:left;padding:0;">
        <div class="modal-head" style="padding:18px 22px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
            <h2 id="comboModalTitle" style="font-family:'Playfair Display',serif;font-size:1.05rem;background:var(--gold-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Add Combo</h2>
            <button class="close-btn" onclick="closeModal('comboModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:22px;max-height:74vh;overflow:auto;">
            <input type="hidden" id="combo_id">

            <!-- STEP 1: pick products (drives name + MRP automatically) -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                <label class="form-label" style="font-weight:700;margin:0;">1. Products in this combo *</label>
                <button type="button" class="btn btn-ghost btn-sm" onclick="addComboItem()"><i class="fa-solid fa-plus"></i> Add Product</button>
            </div>
            <div class="text-muted" style="font-size:.72rem;margin-bottom:8px;">Pick your products. Combo <b>name</b> &amp; <b>MRP</b> fill in automatically from them.</div>
            <div id="comboItemsContainer"></div>

            <!-- STEP 2: name (auto-suggested, editable) -->
            <div class="form-group" style="margin-top:10px;">
                <label class="form-label">2. Combo Name * <small class="text-muted">(auto from products — edit if you like)</small></label>
                <input type="text" class="form-control" id="combo_name" placeholder="auto-generated from selected products" oninput="comboNameTouched=true">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="combo_desc" rows="2" placeholder="What's in this combo..."></textarea>
            </div>

            <!-- STEP 3: pricing — MRP auto-summed, admin sets the deal price -->
            <div class="form-row" style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><label class="form-label">MRP (₹) <small class="text-muted">(auto sum)</small></label><input type="number" class="form-control" id="combo_mrp" readonly style="background:var(--bg-elevated);"></div>
                <div class="form-group" style="flex:1;"><label class="form-label">Combo Price (₹) *</label><input type="number" class="form-control" id="combo_price" placeholder="deal price" oninput="comboCalc()"></div>
            </div>
            <div id="combo_calc" style="background:var(--bg-elevated);border:1px solid var(--border-color);border-radius:8px;padding:8px 12px;margin-bottom:8px;font-size:.82rem;">
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Total MRP (auto)</span><span class="font-bold">₹<span id="combo_mrp_disp">0</span></span></div>
                <div style="display:flex;justify-content:space-between;margin-top:3px;"><span class="text-muted">If bought separately</span><span class="font-bold">₹<span id="combo_selltotal">0</span></span></div>
                <div style="display:flex;justify-content:space-between;margin-top:3px;"><span class="text-muted">Discount off MRP</span><span class="font-bold" style="color:var(--success);">₹<span id="combo_save">0</span> (<span id="combo_disc">0</span>%)</span></div>
                <div style="display:flex;justify-content:space-between;margin-top:3px;"><span class="text-muted">Real save vs separate</span><span class="font-bold" style="color:var(--success);">₹<span id="combo_realsave">0</span></span></div>
            </div>
            <div id="combo_warn" style="color:var(--danger);font-size:.76rem;margin-top:-4px;margin-bottom:10px;display:none;"><i class="fa-solid fa-triangle-exclamation"></i> Combo price is higher than total MRP.</div>

            <!-- Optional combo cover image (defaults to first product) -->
            <div class="form-group">
                <label class="form-label">Combo Image <small class="text-muted">(optional — defaults to 1st product)</small></label>
                <div class="drop-zone" id="comboDrop" onclick="document.getElementById('comboImgInput').click()" style="border:2px dashed var(--border-active);border-radius:10px;padding:14px;text-align:center;cursor:pointer;">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;color:var(--gold-primary);display:block;margin-bottom:5px;"></i>
                    <span style="color:var(--text-secondary);font-size:.8rem;">Click to upload (optional)</span>
                </div>
                <input type="file" id="comboImgInput" accept="image/*" style="display:none" onchange="uploadComboImg(this.files)">
                <div id="comboImgPreview" style="margin-top:10px;"></div>
                <input type="hidden" id="combo_image">
            </div>

            <div class="form-row" style="display:flex;gap:10px;align-items:flex-end;">
                <div class="form-group" style="flex:1;"><label class="form-label">Stock Qty <small class="text-muted">(0 = out, low → urgency ribbon)</small></label><input type="number" min="0" class="form-control" id="combo_stock" value="50"></div>
                <div class="form-group" style="flex:1;"><label class="form-label">Status</label>
                    <select class="form-control" id="combo_status"><option value="1">Active</option><option value="0">Inactive</option></select>
                </div>
            </div>

            <!-- SEO (optional) — collapsed by default to keep the form short -->
            <details style="margin-top:6px;border-top:1px dashed var(--border-color);padding-top:10px;">
                <summary style="cursor:pointer;font-weight:700;font-size:.85rem;color:var(--text-secondary);"><i class="fa-solid fa-magnifying-glass-chart"></i> SEO &amp; URL <small class="text-muted">(optional)</small></summary>
                <div class="form-group" style="margin-top:10px;">
                    <label class="form-label">URL Slug <small class="text-muted">(auto from name if blank)</small></label>
                    <input type="text" class="form-control" id="combo_slug" placeholder="e.g. scaler-mask-combo">
                </div>
                <div class="form-group">
                    <label class="form-label">Meta Title <small class="text-muted">(Google/tab title — keep under ~60 chars)</small></label>
                    <input type="text" class="form-control" id="combo_meta_title" maxlength="255" placeholder="Defaults to the combo name">
                </div>
                <div class="form-group">
                    <label class="form-label">Meta Description <small class="text-muted">(search snippet — ~155 chars)</small></label>
                    <textarea class="form-control" id="combo_meta_desc" rows="2" maxlength="500" placeholder="Short, benefit-led summary of the deal"></textarea>
                </div>
            </details>
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:10px;">
            <button class="btn btn-ghost" onclick="closeModal('comboModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveCombo()"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        </div>
    </div>
</div>

<script>
const CB_PRODUCTS = <?= $prodJson ?: '[]' ?>;
const cbProdBySlug = Object.fromEntries(CB_PRODUCTS.map(p => [p.slug, p]));
function comboItemRowHtml(it={}) {
    let opts = CB_PRODUCTS.map(p => `<option value="${p.slug}" ${it.productId===p.slug?'selected':''}>${p.name.replace(/</g,'&lt;')}</option>`).join('');
    if (it.productId && !cbProdBySlug[it.productId]) opts += `<option value="${it.productId}" selected>${(it.name||'?').replace(/</g,'&lt;')} [removed]</option>`;
    return `<div class="combo-item-row" style="display:flex;gap:6px;margin-bottom:6px;align-items:center;">
        <select class="form-control" data-ci-select onchange="onPickComboItem(this)" style="flex:1;"><option value="">— Select product —</option>${opts}</select>
        <input type="hidden" data-ci-name value="${(it.name||'').replace(/"/g,'&quot;')}">
        <input type="hidden" data-ci-mrp value="${it.mrp||0}">
        <input type="hidden" data-ci-sell value="${it.sell||it.mrp||0}">
        <input type="hidden" data-ci-image value="${(it.image||'').replace(/"/g,'&quot;')}">
        <button type="button" class="btn btn-ghost btn-sm" onclick="this.parentElement.remove();comboAutoFill()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
    </div>`;
}
function addComboItem(it){ document.getElementById('comboItemsContainer').insertAdjacentHTML('beforeend', comboItemRowHtml(it||{})); comboAutoFill(); }
function onPickComboItem(sel){
    const row=sel.closest('.combo-item-row'); const p=cbProdBySlug[sel.value];
    row.querySelector('[data-ci-name]').value=p?p.name:'';
    row.querySelector('[data-ci-mrp]').value=p?p.price:0;
    row.querySelector('[data-ci-sell]').value=p?((p.discount_price!=null&&p.discount_price>0)?p.discount_price:p.price):0;
    row.querySelector('[data-ci-image]').value=p?(p.img||''):'';
    comboAutoFill();
}
function collectComboItems(){
    const out=[];
    document.querySelectorAll('#comboItemsContainer .combo-item-row').forEach(row=>{
        const id=row.querySelector('[data-ci-select]').value;
        if(id) out.push({productId:id, name:row.querySelector('[data-ci-name]').value, mrp:parseFloat(row.querySelector('[data-ci-mrp]').value)||0, sell:parseFloat(row.querySelector('[data-ci-sell]').value)||0, image:row.querySelector('[data-ci-image]').value});
    });
    return out;
}
// Auto-build name + MRP from picked products. Name only auto-fills if admin hasn't typed a custom one.
let comboNameTouched = false;
function comboAutoFill(){
    const items = collectComboItems();
    // MRP = sum of product MRPs
    const mrp = items.reduce((s,it)=>s+(it.mrp||0),0);
    document.getElementById('combo_mrp').value = mrp || '';
    // auto name from product names (unless admin edited)
    const nameEl = document.getElementById('combo_name');
    if(!comboNameTouched){
        nameEl.value = items.length ? items.map(it=>it.name).filter(Boolean).join(' + ') : '';
    }
    comboCalc();
}
function openComboModal(c = null) {
    comboNameTouched = !!(c && c.name);   // editing keeps the saved name; new combo auto-names
    document.getElementById('combo_id').value     = c?.id || '';
    document.getElementById('combo_name').value   = c?.name || '';
    document.getElementById('combo_desc').value   = c?.description || '';
    document.getElementById('combo_price').value  = c?.price || '';
    document.getElementById('combo_image').value  = c?.image || '';
    document.getElementById('combo_stock').value  = (c && c.stock != null) ? c.stock : 50;
    document.getElementById('combo_status').value = c?.is_active ?? 1;
    document.getElementById('combo_slug').value       = c?.slug || '';
    document.getElementById('combo_meta_title').value = c?.meta_title || '';
    document.getElementById('combo_meta_desc').value  = c?.meta_description || '';
    document.getElementById('comboModalTitle').textContent = c ? 'Edit Combo' : 'Add Combo';
    renderComboImg();
    // items first (drives MRP + name)
    const cont = document.getElementById('comboItemsContainer'); cont.innerHTML='';
    const its = c ? (typeof c.items==='string'?JSON.parse(c.items||'[]'):c.items) || [] : [];
    its.forEach((it)=>document.getElementById('comboItemsContainer').insertAdjacentHTML('beforeend', comboItemRowHtml(it)));
    comboAutoFill();
    openModal('comboModal');
}
function comboCalc(){
    const items = collectComboItems();
    const mrp = parseFloat(document.getElementById('combo_mrp').value)||0;
    const sellTotal = items.reduce((s,it)=>s+(it.sell||it.mrp||0),0);
    const price = parseFloat(document.getElementById('combo_price').value)||0;
    const save = (mrp>price) ? mrp-price : 0;
    const disc = (mrp>0 && price<mrp) ? Math.round((save/mrp)*100) : 0;
    // Real saving vs buying each item separately at its normal selling price.
    const realSave = (sellTotal>price) ? sellTotal-price : 0;
    document.getElementById('combo_mrp_disp').textContent = mrp.toLocaleString('en-IN');
    document.getElementById('combo_save').textContent = save.toLocaleString('en-IN');
    document.getElementById('combo_disc').textContent = disc;
    document.getElementById('combo_selltotal').textContent = sellTotal.toLocaleString('en-IN');
    document.getElementById('combo_realsave').textContent = realSave.toLocaleString('en-IN');
    // Warn: combo price must beat the separate-purchase total (real deal), and not exceed MRP.
    const warn = document.getElementById('combo_warn');
    let msg = '';
    if (price>mrp && mrp>0) msg = 'Combo price is higher than total MRP.';
    else if (price>0 && sellTotal>0 && price>=sellTotal) msg = 'Combo price must be less than buying separately (₹'+sellTotal.toLocaleString('en-IN')+').';
    warn.style.display = msg ? 'block' : 'none';
    if (msg) warn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + msg;
}
async function uploadComboImg(files) {
    const file = files[0]; if(!file) return;
    const fd = new FormData(); fd.append('combo_image', file);
    try {
        const res = await fetch('combos.php',{method:'POST',body:fd});
        const data = await res.json();
        if(data.success){ document.getElementById('combo_image').value=data.url; renderComboImg(); }
        else showToast(data.message,'danger');
    } catch(e){ showToast('Upload error','danger'); }
}
function renderComboImg() {
    const url = document.getElementById('combo_image').value;
    document.getElementById('comboImgPreview').innerHTML = url
        ? `<div style="position:relative;width:90px;height:90px;"><img src="${url}" style="width:100%;height:100%;object-fit:cover;border-radius:8px;background:#fff;border:1px solid var(--border-color);"><button onclick="document.getElementById('combo_image').value='';renderComboImg()" style="position:absolute;top:3px;right:3px;width:20px;height:20px;border:none;border-radius:50%;background:rgba(231,76,60,.9);color:#fff;cursor:pointer;">×</button></div>`
        : '';
}
async function saveCombo() {
    const items = collectComboItems();
    const name = document.getElementById('combo_name').value.trim();
    const mrp = parseFloat(document.getElementById('combo_mrp').value)||0;
    const price = parseFloat(document.getElementById('combo_price').value)||0;
    const sellTotal = items.reduce((s,it)=>s+(it.sell||it.mrp||0),0);
    // A combo must bundle ≥ 2 products (a 1-item combo is just that product's listing).
    if(items.length < 2){ showToast('A combo needs at least 2 products','warning'); return; }
    if(!name){ showToast('Combo name is required','warning'); return; }
    if(!price){ showToast('Combo price is required','warning'); return; }
    if(price > mrp){ showToast('Combo price cannot exceed total MRP','warning'); return; }
    // Real-deal check: combo must be cheaper than buying each item separately.
    if(price >= sellTotal){ showToast('Combo price must be less than buying separately (₹'+sellTotal.toLocaleString('en-IN')+')','warning'); return; }
    // Only the admin-uploaded combo cover (empty if none). Storefront falls back to the
    // bundled-product thumbnails when there's no custom cover.
    const image = document.getElementById('combo_image').value || '';
    const data = { action:'save', id:document.getElementById('combo_id').value, name,
        description:document.getElementById('combo_desc').value, mrp, price,
        image, items,
        stock:document.getElementById('combo_stock').value,
        is_active:document.getElementById('combo_status').value,
        slug:document.getElementById('combo_slug').value,
        meta_title:document.getElementById('combo_meta_title').value,
        meta_description:document.getElementById('combo_meta_desc').value };
    const res = await fetch('combos.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});
    const r = await res.json();
    if(r.success){ showToast(r.message,'success'); closeModal('comboModal'); setTimeout(()=>location.reload(),800); }
    else showToast(r.message,'danger');
}
function applyFilters(){
    const p=new URLSearchParams({search:document.getElementById('searchInput')?.value||'',status:document.getElementById('statusFilter')?.value||''});
    [...p.entries()].forEach(([k,v])=>{if(!v)p.delete(k);});
    window.location.href='combos.php?'+p.toString();
}
// ---- Bulk selection ----
function selectedComboIds(){return [...document.querySelectorAll('.combo-check:checked')].map(c=>parseInt(c.value));}
function updateBulkBar(){
    const n=selectedComboIds().length;
    const bar=document.getElementById('bulkBar');
    bar.style.display=n?'flex':'none';
    if(n)document.getElementById('bulkCount').textContent=n+' selected';
    const all=document.getElementById('selectAllCombos'); const total=document.querySelectorAll('.combo-check').length;
    if(all)all.checked=n>0&&n===total;
}
function toggleAllCombos(cb){document.querySelectorAll('.combo-check').forEach(c=>c.checked=cb.checked);updateBulkBar();}
function clearBulk(){document.querySelectorAll('.combo-check').forEach(c=>c.checked=false);const a=document.getElementById('selectAllCombos');if(a)a.checked=false;updateBulkBar();}
async function bulkAction(op){
    const ids=selectedComboIds(); if(!ids.length)return;
    if(!confirm(`${op.charAt(0).toUpperCase()+op.slice(1)} ${ids.length} combo(s)?`))return;
    const res=await fetch('combos.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'bulk',op,ids})});
    const r=await res.json();
    showToast(r.message||(r.success?'Done':'Failed'), r.success?'success':'danger');
    if(r.success)setTimeout(()=>location.reload(),800);
}
function toggleCombo(id){
    fetch('combos.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'toggle',id})})
    .then(r=>r.json()).then(d=>{if(d.success){showToast('Status updated','success');setTimeout(()=>location.reload(),600);}});
}
function restoreCombo(id){
    fetch('combos.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'restore',id})})
    .then(r=>r.json()).then(d=>{if(d.success){showToast('Combo restored','success');setTimeout(()=>location.reload(),600);}});
}
function deleteCombo(id) {
    showConfirm('Delete Combo','This hides the combo from the storefront. Order history is kept, and you can restore it from the "Deleted" filter. Continue?', async () => {
        const res = await fetch('combos.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
        const r = await res.json();
        if(r.success){ showToast('Combo deleted','success'); const el=document.getElementById(`combo-card-${id}`); if(el){el.style.opacity='0';setTimeout(()=>el.remove(),300);} }
        else showToast(r.message,'danger');
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
