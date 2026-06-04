<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Combos';

// Image upload (multipart) — reuse products image folder.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['combo_image'])) {
    header('Content-Type: application/json');
    $upload_dir = __DIR__ . '/../assets/images/products/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $file = $_FILES['combo_image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) { echo json_encode(['success'=>false,'message'=>'Invalid file type']); exit; }
    if ($file['size'] > 5*1024*1024) { echo json_encode(['success'=>false,'message'=>'File too large']); exit; }
    $fname = 'combo_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $fname)) {
        echo json_encode(['success'=>true,'url'=> APP_URL.'/assets/images/products/'.$fname]);
    } else { echo json_encode(['success'=>false,'message'=>'Upload failed']); }
    exit;
}

// AJAX JSON actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $d = json_decode(file_get_contents('php://input'), true);
    $action = $d['action'] ?? '';

    if ($action === 'save') {
        $mrp   = (float)($d['mrp'] ?? 0);
        $price = (float)($d['price'] ?? 0);
        $disc  = ($mrp > 0 && $price < $mrp) ? round((($mrp - $price) / $mrp) * 100, 0) : 0;
        $images_json = !empty($d['images']) ? json_encode($d['images']) : null;
        if (!empty($d['id'])) {
            db()->execute(
                "UPDATE combos SET name=?,description=?,mrp=?,price=?,discount_percent=?,image=?,images=?,in_stock=?,is_active=? WHERE id=?",
                [$d['name'],$d['description']??'',$mrp,$price,$disc,$d['image']?:null,$images_json,$d['in_stock']??1,$d['is_active']??1,$d['id']]
            );
            echo json_encode(['success'=>true,'message'=>'Combo updated']);
        } else {
            $slug = generateSlug($d['name']) . '-' . substr((string)time(), -5);
            db()->insert(
                "INSERT INTO combos (slug,name,description,mrp,price,discount_percent,image,images,in_stock,is_active) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$slug,$d['name'],$d['description']??'',$mrp,$price,$disc,$d['image']?:null,$images_json,$d['in_stock']??1,$d['is_active']??1]
            );
            echo json_encode(['success'=>true,'message'=>'Combo added']);
        }
    } elseif ($action === 'delete') {
        db()->execute("DELETE FROM combos WHERE id=?", [$d['id']]);
        echo json_encode(['success'=>true,'message'=>'Combo deleted']);
    } elseif ($action === 'toggle') {
        db()->execute("UPDATE combos SET is_active=? WHERE id=?", [$d['is_active'],$d['id']]);
        echo json_encode(['success'=>true]);
    }
    exit;
}

$combos = db()->fetchAll("SELECT * FROM combos ORDER BY sort_order, id");
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Combos</h1>
        <p>Bundle deals shown on the storefront combos page</p>
    </div>
    <button class="btn btn-gold" onclick="openComboModal()"><i class="fa-solid fa-plus"></i> Add Combo</button>
</div>

<div class="grid-3 fade-in">
    <?php foreach($combos as $c): ?>
    <div class="card" id="combo-card-<?= $c['id'] ?>" style="transition:all .2s;">
        <div class="card-body">
            <div style="display:flex;gap:12px;">
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
                </div>
            </div>
            <div style="margin-top:14px;display:flex;justify-content:flex-end;gap:6px;">
                <button class="btn btn-ghost btn-sm btn-icon" onclick='openComboModal(<?= json_encode($c) ?>)'><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteCombo(<?= $c['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($combos)): ?><p class="text-muted">No combos yet. Click "Add Combo".</p><?php endif; ?>
</div>

<!-- Modal -->
<div class="modal-overlay" id="comboModal" style="display:none;" onclick="if(event.target===this)closeModal('comboModal')">
    <div class="modal-box" style="max-width:480px;text-align:left;padding:0;">
        <div class="modal-head" style="padding:18px 22px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
            <h2 id="comboModalTitle" style="font-family:'Playfair Display',serif;font-size:1.05rem;background:var(--gold-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Add Combo</h2>
            <button class="close-btn" onclick="closeModal('comboModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:22px;max-height:70vh;overflow:auto;">
            <input type="hidden" id="combo_id">
            <div class="form-group">
                <label class="form-label">Combo Name *</label>
                <input type="text" class="form-control" id="combo_name" placeholder="e.g. Endo Master Combo">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="combo_desc" rows="2" placeholder="What's in this combo..."></textarea>
            </div>
            <div class="form-row" style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><label class="form-label">MRP (₹) *</label><input type="number" class="form-control" id="combo_mrp" placeholder="2100"></div>
                <div class="form-group" style="flex:1;"><label class="form-label">Selling Price (₹) *</label><input type="number" class="form-control" id="combo_price" placeholder="1199"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Image</label>
                <div class="drop-zone" id="comboDrop" onclick="document.getElementById('comboImgInput').click()" style="border:2px dashed var(--border-active);border-radius:10px;padding:18px;text-align:center;cursor:pointer;">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.6rem;color:var(--gold-primary);display:block;margin-bottom:6px;"></i>
                    <span style="color:var(--text-secondary);font-size:.82rem;">Click to upload combo image</span>
                </div>
                <input type="file" id="comboImgInput" accept="image/*" style="display:none" onchange="uploadComboImg(this.files)">
                <div id="comboImgPreview" style="margin-top:10px;"></div>
                <input type="hidden" id="combo_image">
            </div>
            <div class="form-row" style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><label class="form-label">Stock</label>
                    <select class="form-control" id="combo_stock"><option value="1">In Stock</option><option value="0">Out of Stock</option></select>
                </div>
                <div class="form-group" style="flex:1;"><label class="form-label">Status</label>
                    <select class="form-control" id="combo_status"><option value="1">Active</option><option value="0">Inactive</option></select>
                </div>
            </div>
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:10px;">
            <button class="btn btn-ghost" onclick="closeModal('comboModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveCombo()"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        </div>
    </div>
</div>

<script>
function openComboModal(c = null) {
    document.getElementById('combo_id').value     = c?.id || '';
    document.getElementById('combo_name').value   = c?.name || '';
    document.getElementById('combo_desc').value   = c?.description || '';
    document.getElementById('combo_mrp').value    = c?.mrp || '';
    document.getElementById('combo_price').value  = c?.price || '';
    document.getElementById('combo_image').value  = c?.image || '';
    document.getElementById('combo_stock').value  = c?.in_stock ?? 1;
    document.getElementById('combo_status').value = c?.is_active ?? 1;
    document.getElementById('comboModalTitle').textContent = c ? 'Edit Combo' : 'Add Combo';
    renderComboImg();
    openModal('comboModal');
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
    const name = document.getElementById('combo_name').value.trim();
    const mrp = document.getElementById('combo_mrp').value;
    const price = document.getElementById('combo_price').value;
    if(!name||!mrp||!price){ showToast('Name, MRP and Price are required','warning'); return; }
    const data = { action:'save', id:document.getElementById('combo_id').value, name,
        description:document.getElementById('combo_desc').value, mrp, price,
        image:document.getElementById('combo_image').value,
        in_stock:document.getElementById('combo_stock').value,
        is_active:document.getElementById('combo_status').value };
    const res = await fetch('combos.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});
    const r = await res.json();
    if(r.success){ showToast(r.message,'success'); closeModal('comboModal'); setTimeout(()=>location.reload(),800); }
    else showToast(r.message,'danger');
}
function deleteCombo(id) {
    showConfirm('Delete Combo','This combo will be removed from the storefront.', async () => {
        const res = await fetch('combos.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
        const r = await res.json();
        if(r.success){ showToast('Combo deleted','success'); const el=document.getElementById(`combo-card-${id}`); if(el){el.style.opacity='0';setTimeout(()=>el.remove(),300);} }
        else showToast(r.message,'danger');
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
