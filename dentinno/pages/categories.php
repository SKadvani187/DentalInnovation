<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    // Never let a PHP warning/exception leak HTML into the JSON response (breaks res.json()).
    try {
    if ($action === 'save') {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') { echo json_encode(['success'=>false,'message'=>'Category name is required']); exit; }
        $selfId = (int)($data['id'] ?? 0);
        // Slug — admin-editable, falls back to the name; unique (avoids a duplicate-key exception).
        $slugInput = trim((string)($data['slug'] ?? ''));
        $slug = generateSlug($slugInput !== '' ? $slugInput : $name) ?: 'category';
        $base = $slug; $n = 1;
        while (db()->fetchOne("SELECT id FROM categories WHERE slug=? AND id<>?", [$slug, $selfId])) { $slug = $base.'-'.(++$n); }
        $metaTitle = trim((string)($data['meta_title'] ?? '')) ?: null;
        $metaDesc  = trim((string)($data['meta_description'] ?? '')) ?: null;
        $parentId  = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        // Cycle guard: the chosen parent must not be this category or one of its descendants.
        if ($parentId && $selfId) {
            $anc = $parentId; $seen = [];
            while ($anc) {
                if ($anc === $selfId) { echo json_encode(['success'=>false,'message'=>'That would create a category loop (parent is a sub-category of this one).']); exit; }
                if (isset($seen[$anc])) break;     // guard against pre-existing bad data
                $seen[$anc] = true;
                $row = db()->fetchOne("SELECT parent_id FROM categories WHERE id=?", [$anc]);
                $anc = $row ? (int)($row['parent_id'] ?? 0) : 0;
            }
        }
        $image     = !empty($data['image']) ? $data['image'] : null;
        $sortOrder = (int)($data['sort_order'] ?? 0);
        $isActive  = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        $desc      = $data['description'] ?? null;
        if (!empty($data['id'])) {
            db()->execute("UPDATE categories SET name=?,slug=?,meta_title=?,meta_description=?,description=?,parent_id=?,image=?,sort_order=?,is_active=? WHERE id=?",
                [$name,$slug,$metaTitle,$metaDesc,$desc,$parentId,$image,$sortOrder,$isActive,$selfId]);
            logActivity('updated', 'category', (int)$selfId, $name);
            echo json_encode(['success'=>true,'message'=>'Category updated']);
        } else {
            db()->insert("INSERT INTO categories (name,slug,meta_title,meta_description,description,parent_id,image,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?,?)",
                [$name,$slug,$metaTitle,$metaDesc,$desc,$parentId,$image,$sortOrder,$isActive]);
            logActivity('created', 'category', null, $name);
            echo json_encode(['success'=>true,'message'=>'Category added']);
        }
    } elseif ($action === 'toggle') {
        db()->execute("UPDATE categories SET is_active = NOT is_active WHERE id=?", [(int)($data['id'] ?? 0)]);
        echo json_encode(['success'=>true,'message'=>'Status updated']);
    } elseif ($action === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array)($data['ids'] ?? []))));
        $op  = (string)($data['op'] ?? '');
        if (!$ids) { echo json_encode(['success'=>false,'message'=>'No categories selected']); exit; }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($op === 'activate')       { db()->execute("UPDATE categories SET is_active=1 WHERE id IN ($ph)", $ids); $msg = count($ids).' categor(ies) activated'; }
        elseif ($op === 'deactivate') { db()->execute("UPDATE categories SET is_active=0 WHERE id IN ($ph)", $ids); $msg = count($ids).' categor(ies) deactivated'; }
        elseif ($op === 'delete') {
            // Only delete categories with NO products and NO sub-categories; report the rest skipped.
            $deleted = 0; $skipped = 0;
            foreach ($ids as $cid) {
                $p   = (int)(db()->fetchOne("SELECT COUNT(*) c FROM products WHERE category_id=?", [$cid])['c'] ?? 0);
                $sub = (int)(db()->fetchOne("SELECT COUNT(*) c FROM categories WHERE parent_id=?", [$cid])['c'] ?? 0);
                if ($p > 0 || $sub > 0) { $skipped++; continue; }
                db()->execute("DELETE FROM categories WHERE id=?", [$cid]); $deleted++;
            }
            $msg = "$deleted deleted" . ($skipped ? ", $skipped skipped (has products/sub-categories)" : "");
        } else { echo json_encode(['success'=>false,'message'=>'Unknown bulk action']); exit; }
        echo json_encode(['success'=>true,'message'=>$msg]);
    } elseif ($action === 'delete') {
        $id   = (int)($data['id'] ?? 0);
        $pCnt = (int)(db()->fetchOne("SELECT COUNT(*) as c FROM products WHERE category_id=?", [$id])['c'] ?? 0);
        $sCnt = (int)(db()->fetchOne("SELECT COUNT(*) as c FROM categories WHERE parent_id=?", [$id])['c'] ?? 0);
        if ($pCnt > 0)      { echo json_encode(['success'=>false,'message'=>"Cannot delete — $pCnt product(s) use this category. Reassign them first."]); }
        elseif ($sCnt > 0)  { echo json_encode(['success'=>false,'message'=>"Cannot delete — $sCnt sub-categor(ies) belong to this one. Move or delete them first."]); }
        else { db()->execute("DELETE FROM categories WHERE id=?", [$id]); logActivity('deleted', 'category', $id); echo json_encode(['success'=>true,'message'=>'Category deleted']); }
    }
    } catch (Throwable $e) {
        echo json_encode(['success'=>false, 'message'=>'Save failed: ' . $e->getMessage()]);
    }
    exit;
}

// Category image upload (stored under assets/images/categories/).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['category_image'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    $dir = __DIR__ . '/../assets/images/categories/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $file = $_FILES['category_image'];
    if ($file['error'] !== UPLOAD_ERR_OK) { echo json_encode(['success'=>false,'message'=>'Upload error (code '.$file['error'].')']); exit; }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) { echo json_encode(['success'=>false,'message'=>'Invalid file type']); exit; }
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($mime && !in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'], true)) {
        echo json_encode(['success'=>false,'message'=>'The file is not a valid image (content check failed)']); exit;
    }
    if (!imageDimsOk($file['tmp_name'])) { echo json_encode(['success'=>false,'message'=>'Image must be a valid file no larger than 6000×6000 px']); exit; }
    if ($file['size'] > 5*1024*1024) { echo json_encode(['success'=>false,'message'=>'File too large (max 5MB)']); exit; }
    $fname = 'cat_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
        echo json_encode(['success'=>true,'url'=> APP_URL.'/assets/images/categories/'.$fname]);
    } else { echo json_encode(['success'=>false,'message'=>'Could not save the image']); }
    exit;
}

// Filters + paginate the categories grid.
$search   = sanitize($_GET['search'] ?? '');
$status   = sanitize($_GET['status'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;
$where = ["1=1"]; $params = [];
if ($search)        { $where[] = "(c.name LIKE ? OR c.slug LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status !== '') { $where[] = "c.is_active = ?"; $params[] = (int)$status; }
$whereStr = implode(' AND ', $where);
$total    = (int)(db()->fetchOne("SELECT COUNT(*) c FROM categories c WHERE $whereStr", $params)['c'] ?? 0);
$pages    = (int)ceil($total / $per_page);
$categories = db()->fetchAll("SELECT c.*, pc.name AS parent_name, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) as product_count FROM categories c LEFT JOIN categories pc ON pc.id=c.parent_id WHERE $whereStr ORDER BY c.sort_order, c.name LIMIT $per_page OFFSET $offset", $params);
// All categories for the parent-category dropdown in the modal.
$allCats = db()->fetchAll("SELECT id, name FROM categories ORDER BY name");
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Categories</h1>
        <p>Organize your products by category</p>
    </div>
    <button class="btn btn-gold" onclick="openCatModal()"><i class="fa-solid fa-plus"></i> Add Category</button>
</div>

<div class="filter-bar fade-in" style="flex-wrap:wrap;gap:8px;">
    <div class="search-wrapper" style="flex:1;min-width:180px;max-width:300px;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search categories..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select class="form-control" id="statusFilter" style="max-width:140px;">
        <option value="">All Status</option>
        <option value="1" <?= $status==='1'?'selected':'' ?>>Active</option>
        <option value="0" <?= $status==='0'?'selected':'' ?>>Inactive</option>
    </select>
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="categories.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<div id="bulkBar" style="display:none;margin-bottom:14px;padding:12px 16px;border:1px solid var(--border-color);border-radius:10px;gap:10px;align-items:center;background:var(--bg-elevated);">
    <span id="bulkCount" style="font-size:.82rem;font-weight:600;"></span>
    <button class="btn btn-ghost btn-sm" onclick="bulkAction('activate')"><i class="fa-solid fa-circle-check" style="color:var(--success);"></i> Activate</button>
    <button class="btn btn-ghost btn-sm" onclick="bulkAction('deactivate')"><i class="fa-solid fa-ban" style="color:var(--warning);"></i> Deactivate</button>
    <button class="btn btn-ghost btn-sm" onclick="bulkAction('delete')" style="color:var(--danger);"><i class="fa-solid fa-trash"></i> Delete</button>
    <button class="btn btn-ghost btn-sm" onclick="clearBulk()">Clear</button>
</div>

<div class="grid-3 fade-in">
    <?php foreach($categories as $c): ?>
    <div class="card" id="cat-card-<?= $c['id'] ?>" style="transition:all 0.2s;">
        <div class="card-body">
            <div style="display:flex;gap:10px;align-items:flex-start;">
                <input type="checkbox" class="cat-check" value="<?= $c['id'] ?>" onchange="updateBulkBar()" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;margin-top:5px;flex-shrink:0;">
                <div style="width:52px;height:52px;border-radius:10px;background:var(--bg-elevated);flex-shrink:0;overflow:hidden;display:grid;place-items:center;">
                    <?php if(!empty($c['image'])): ?><img src="<?= htmlspecialchars($c['image']) ?>" style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
                    <?php else: ?><i class="fa-solid fa-layer-group" style="color:var(--gold-primary);"></i><?php endif; ?>
                </div>
                <div style="flex:1;min-width:0;display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                    <div style="min-width:0;">
                        <div class="font-bold" style="font-size:1rem;margin-bottom:2px;"><?= htmlspecialchars($c['name']) ?></div>
                        <?php if(!empty($c['parent_name'])): ?><div style="font-size:.68rem;color:var(--gold-primary);margin-bottom:2px;"><i class="fa-solid fa-arrow-turn-up fa-rotate-90" style="font-size:.6rem;"></i> under <?= htmlspecialchars($c['parent_name']) ?></div><?php endif; ?>
                        <div class="text-muted" style="font-size:0.76rem;"><?= htmlspecialchars($c['description'] ?: 'No description') ?></div>
                    </div>
                    <span class="badge badge-<?= $c['is_active']?'success':'secondary' ?>" style="flex-shrink:0;"><?= $c['is_active']?'Active':'Inactive' ?></span>
                </div>
            </div>
            <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <a href="products.php?cat=<?= $c['id'] ?>" title="View products in this category" style="text-decoration:none;">
                        <span class="text-gold font-bold" style="font-size:1.3rem;"><?= $c['product_count'] ?></span>
                        <span class="text-muted" style="font-size:0.78rem;margin-left:4px;">products</span>
                    </a>
                    <?php if((int)($c['sort_order']??0) !== 0): ?><span class="text-muted" style="font-size:.7rem;margin-left:8px;">· order <?= (int)$c['sort_order'] ?></span><?php endif; ?>
                </div>
                <div style="display:flex;gap:6px;">
                    <button class="btn btn-ghost btn-sm btn-icon" title="Activate/Deactivate" onclick="toggleCat(<?= $c['id'] ?>)"><i class="fa-solid fa-power-off" style="color:<?= $c['is_active']?'var(--success)':'var(--text-muted)' ?>;"></i></button>
                    <button class="btn btn-ghost btn-sm btn-icon" title="Edit" onclick='openCatModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8") ?>)'><i class="fa-solid fa-pen"></i></button>
                    <button class="btn btn-ghost btn-sm btn-icon" title="Delete" onclick="deleteCat(<?= $c['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if($pages > 1): ?>
<div class="pagination" style="margin-top:16px;">
    <?php for($i=1;$i<=$pages;$i++): ?><div class="page-item <?= $i==$page?'active':'' ?>" onclick="goCatsPage(<?= $i ?>)"><?= $i ?></div><?php endfor; ?>
</div>
<script>function goCatsPage(p){window.location.href=`categories.php?page=${p}`;}</script>
<?php endif; ?>

<!-- Modal -->
<div class="modal-overlay" id="catModal" style="display:none;" onclick="if(event.target===this)closeModal('catModal')">
    <div class="modal-box" style="max-width:440px;text-align:left;padding:0;">
        <div class="modal-head" style="padding:18px 22px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
            <h2 id="catModalTitle" style="font-family:'Playfair Display',serif;font-size:1.05rem;background:var(--gold-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Add Category</h2>
            <button class="close-btn" onclick="closeModal('catModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:22px;max-height:65vh;overflow-y:auto;">
            <input type="hidden" id="cat_id">
            <input type="hidden" id="cat_image">
            <div class="form-group">
                <label class="form-label">Category Name *</label>
                <input type="text" class="form-control" id="cat_name" placeholder="e.g. Implantology">
            </div>
            <div class="form-group">
                <label class="form-label">Parent Category <small class="text-muted">(optional — makes this a sub-category)</small></label>
                <select class="form-control" id="cat_parent">
                    <option value="">— None (top level) —</option>
                    <?php foreach($allCats as $pc): ?><option value="<?= $pc['id'] ?>"><?= htmlspecialchars($pc['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="cat_desc" rows="2" placeholder="Brief description..."></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control" id="cat_status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Sort Order <small class="text-muted">(lower = first)</small></label>
                    <input type="number" class="form-control" id="cat_sort" placeholder="0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Category Image <small class="text-muted">(shown on the storefront)</small></label>
                <div onclick="document.getElementById('catImgInput').click()" style="border:2px dashed var(--border-active);border-radius:10px;padding:14px;text-align:center;cursor:pointer;">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.3rem;color:var(--gold-primary);display:block;margin-bottom:4px;"></i>
                    <span style="color:var(--text-secondary);font-size:.8rem;">Click to upload (JPG/PNG/WebP, max 5MB)</span>
                </div>
                <input type="file" id="catImgInput" accept="image/*" style="display:none" onchange="uploadCatImage(this.files)">
                <div id="catImgPreview" style="margin-top:8px;"></div>
            </div>
            <div style="border-top:1px solid var(--border-color);padding-top:14px;margin-top:4px;">
                <label class="form-label" style="margin-bottom:10px;display:block;"><i class="fa-solid fa-magnifying-glass-chart" style="color:var(--gold-primary);margin-right:5px;"></i>SEO <small class="text-muted">(search engines)</small></label>
                <div class="form-group"><label class="form-label">URL Slug <small class="text-muted">(auto from name if blank)</small></label><input type="text" class="form-control" id="cat_slug" placeholder="e.g. implantology"></div>
                <div class="form-group"><label class="form-label">Meta Title <small class="text-muted">(~60 chars)</small></label><input type="text" class="form-control" id="cat_meta_title" maxlength="255" placeholder="Defaults to the category name"></div>
                <div class="form-group"><label class="form-label">Meta Description <small class="text-muted">(~155 chars)</small></label><textarea class="form-control" id="cat_meta_desc" rows="2" maxlength="320" placeholder="Short search-result description"></textarea></div>
            </div>
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:10px;">
            <button class="btn btn-ghost" onclick="closeModal('catModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveCat()"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        </div>
    </div>
</div>

<script>
function applyFilters(){
    const p=new URLSearchParams({search:document.getElementById('searchInput')?.value||'',status:document.getElementById('statusFilter')?.value||''});
    [...p.entries()].forEach(([k,v])=>{if(!v)p.delete(k);});
    window.location.href='categories.php?'+p.toString();
}
function renderCatImg(){
    const url=document.getElementById('cat_image').value;
    document.getElementById('catImgPreview').innerHTML = url
        ? `<div style="position:relative;width:80px;height:80px;border-radius:8px;overflow:hidden;border:1px solid var(--border-color);"><img src="${url}" style="width:100%;height:100%;object-fit:cover;"><button onclick="document.getElementById('cat_image').value='';renderCatImg()" style="position:absolute;top:2px;right:2px;width:20px;height:20px;background:rgba(231,76,60,.9);color:#fff;border:none;border-radius:50%;cursor:pointer;font-size:.6rem;">✕</button></div>`
        : '';
}
async function uploadCatImage(files){
    const file=files[0]; if(!file)return;
    const fd=new FormData(); fd.append('category_image',file);
    showToast('Uploading…','info');
    try{
        const res=await fetch('categories.php',{method:'POST',body:fd});
        const r=await res.json();
        if(r.success){document.getElementById('cat_image').value=r.url;renderCatImg();showToast('Image uploaded','success');}
        else showToast(r.message||'Upload failed','danger');
    }catch(e){ showToast('Upload error','danger'); }
}
function openCatModal(c = null) {
    document.getElementById('cat_id').value    = c?.id || '';
    document.getElementById('cat_name').value  = c?.name || '';
    document.getElementById('cat_parent').value= c?.parent_id || '';
    document.getElementById('cat_desc').value  = c?.description || '';
    document.getElementById('cat_status').value= c?.is_active ?? 1;
    document.getElementById('cat_sort').value  = c?.sort_order ?? 0;
    document.getElementById('cat_image').value = c?.image || '';
    document.getElementById('cat_slug').value       = c?.slug || '';
    document.getElementById('cat_meta_title').value = c?.meta_title || '';
    document.getElementById('cat_meta_desc').value  = c?.meta_description || '';
    renderCatImg();
    document.getElementById('catModalTitle').textContent = c ? 'Edit Category' : 'Add Category';
    openModal('catModal');
}
async function saveCat() {
    const name = document.getElementById('cat_name').value.trim();
    if (!name) { showToast('Name is required','warning'); return; }
    const data = { action:'save', id:document.getElementById('cat_id').value, name,
        parent_id:document.getElementById('cat_parent').value,
        description:document.getElementById('cat_desc').value,
        is_active:document.getElementById('cat_status').value,
        sort_order:document.getElementById('cat_sort').value,
        image:document.getElementById('cat_image').value,
        slug:document.getElementById('cat_slug').value,
        meta_title:document.getElementById('cat_meta_title').value,
        meta_description:document.getElementById('cat_meta_desc').value };
    const res = await fetch('categories.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});
    const r = await res.json();
    if(r.success){showToast(r.message,'success');closeModal('catModal');setTimeout(()=>location.reload(),800);}
    else showToast(r.message,'danger');
}
function toggleCat(id){
    fetch('categories.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'toggle',id})})
    .then(r=>r.json()).then(d=>{if(d.success){showToast('Status updated','success');setTimeout(()=>location.reload(),600);}});
}
// ---- Bulk selection ----
function selectedCatIds(){return [...document.querySelectorAll('.cat-check:checked')].map(c=>parseInt(c.value));}
function updateBulkBar(){
    const n=selectedCatIds().length;
    const bar=document.getElementById('bulkBar');
    bar.style.display=n?'flex':'none';
    if(n)document.getElementById('bulkCount').textContent=n+' selected';
}
function clearBulk(){document.querySelectorAll('.cat-check').forEach(c=>c.checked=false);updateBulkBar();}
async function bulkAction(op){
    const ids=selectedCatIds(); if(!ids.length)return;
    const verb={activate:'activate',deactivate:'deactivate',delete:'delete'}[op];
    if(!confirm(`${verb.charAt(0).toUpperCase()+verb.slice(1)} ${ids.length} categor(ies)?`))return;
    const res=await fetch('categories.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'bulk',op,ids})});
    const r=await res.json();
    showToast(r.message||(r.success?'Done':'Failed'), r.success?'success':'danger');
    if(r.success)setTimeout(()=>location.reload(),800);
}
function deleteCat(id) {
    showConfirm('Delete Category','Categories with products or sub-categories cannot be deleted — reassign those first. Continue?', async () => {
        const res = await fetch('categories.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
        const r = await res.json();
        if(r.success){showToast('Category deleted','success');const el=document.getElementById(`cat-card-${id}`);if(el){el.style.opacity='0';setTimeout(()=>el.remove(),300);}}
        else showToast(r.message,'danger');
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
