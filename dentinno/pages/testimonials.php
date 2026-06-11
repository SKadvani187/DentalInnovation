<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Testimonials';

// Image upload (shared products folder)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['t_image'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    $upload_dir = __DIR__ . '/../assets/images/products/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $file = $_FILES['t_image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) { echo json_encode(['success'=>false,'message'=>'Invalid file type']); exit; }
    if ($file['size'] > 5*1024*1024) { echo json_encode(['success'=>false,'message'=>'File too large']); exit; }
    $fname = 'tst_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $fname)) {
        echo json_encode(['success'=>true,'url'=> APP_URL.'/assets/images/products/'.$fname]);
    } else { echo json_encode(['success'=>false,'message'=>'Upload failed']); }
    exit;
}

// AJAX JSON actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    $d = json_decode(file_get_contents('php://input'), true);
    $action = $d['action'] ?? '';

    if ($action === 'save') {
        $rating = max(1, min(5, (int)($d['rating'] ?? 5)));
        if (!empty($d['id'])) {
            db()->execute(
                "UPDATE testimonials SET name=?,avatar=?,product_image=?,product_name=?,rating=?,text=?,is_active=? WHERE id=?",
                [$d['name'],($d['avatar']??'')?:null,($d['product_image']??'')?:null,($d['product_name']??'')?:null,$rating,$d['text'],$d['is_active']??1,$d['id']]
            );
            echo json_encode(['success'=>true,'message'=>'Testimonial updated']);
        } else {
            $slug = 't-' . substr((string)time(), -6);
            db()->insert(
                "INSERT INTO testimonials (slug,name,avatar,product_image,product_name,rating,text,is_active) VALUES (?,?,?,?,?,?,?,?)",
                [$slug,$d['name'],($d['avatar']??'')?:null,($d['product_image']??'')?:null,($d['product_name']??'')?:null,$rating,$d['text'],$d['is_active']??1]
            );
            echo json_encode(['success'=>true,'message'=>'Testimonial added']);
        }
    } elseif ($action === 'delete') {
        db()->execute("DELETE FROM testimonials WHERE id=?", [$d['id']]);
        echo json_encode(['success'=>true,'message'=>'Testimonial deleted']);
    }
    exit;
}

$testimonials = db()->fetchAll("SELECT * FROM testimonials ORDER BY sort_order, id");
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Testimonials</h1>
        <p>Customer reviews shown on the storefront home page</p>
    </div>
    <button class="btn btn-gold" onclick="openTstModal()"><i class="fa-solid fa-plus"></i> Add Testimonial</button>
</div>

<div class="grid-3 fade-in">
    <?php foreach($testimonials as $t): ?>
    <div class="card" id="tst-card-<?= $t['id'] ?>" style="transition:all .2s;">
        <div class="card-body">
            <div style="display:flex;gap:12px;">
                <img src="<?= htmlspecialchars($t['avatar'] ?: '') ?>" style="width:46px;height:46px;border-radius:50%;object-fit:cover;background:#eee;border:1px solid var(--border-color);flex-shrink:0;" onerror="this.style.opacity=.2">
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                        <div class="font-bold" style="font-size:.92rem;"><?= htmlspecialchars($t['name']) ?></div>
                        <span class="badge badge-<?= $t['is_active']?'success':'secondary' ?>" style="flex-shrink:0;"><?= $t['is_active']?'Active':'Inactive' ?></span>
                    </div>
                    <div class="text-muted" style="font-size:.78rem;margin-top:5px;line-height:1.45;"><?= htmlspecialchars(mb_strimwidth($t['text'],0,120,'…')) ?></div>
                </div>
            </div>
            <div style="margin-top:12px;display:flex;justify-content:flex-end;gap:6px;">
                <button class="btn btn-ghost btn-sm btn-icon" onclick='openTstModal(<?= json_encode($t) ?>)'><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteTst(<?= $t['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($testimonials)): ?><p class="text-muted">No testimonials yet. Click "Add Testimonial".</p><?php endif; ?>
</div>

<!-- Modal -->
<div class="modal-overlay" id="tstModal" style="display:none;" onclick="if(event.target===this)closeModal('tstModal')">
    <div class="modal-box" style="max-width:460px;text-align:left;padding:0;">
        <div class="modal-head" style="padding:18px 22px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
            <h2 id="tstModalTitle" style="font-family:'Playfair Display',serif;font-size:1.05rem;background:var(--gold-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Add Testimonial</h2>
            <button class="close-btn" onclick="closeModal('tstModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:22px;max-height:70vh;overflow:auto;">
            <input type="hidden" id="tst_id">
            <div class="form-group"><label class="form-label">Customer Name *</label><input type="text" class="form-control" id="tst_name" placeholder="e.g. Dr. Patel"></div>
            <div class="form-group">
                <label class="form-label">Avatar Image</label>
                <div class="drop-zone" id="tstDrop" onclick="document.getElementById('tstImgInput').click()" style="border:2px dashed var(--border-active);border-radius:10px;padding:16px;text-align:center;cursor:pointer;">
                    <i class="fa-solid fa-user-circle" style="font-size:1.5rem;color:var(--gold-primary);display:block;margin-bottom:5px;"></i>
                    <span style="color:var(--text-secondary);font-size:.8rem;">Upload avatar (or paste URL below)</span>
                </div>
                <input type="file" id="tstImgInput" accept="image/*" style="display:none" onchange="uploadTstImg(this.files)">
                <div id="tstImgPreview" style="margin-top:8px;"></div>
                <input type="text" class="form-control" id="tst_avatar" placeholder="Avatar URL" style="margin-top:8px;" oninput="renderTstImg()">
            </div>
            <div class="form-group"><label class="form-label">Product Name</label><input type="text" class="form-control" id="tst_product_name" placeholder="e.g. Implant Hex Driver"></div>
            <div class="form-group"><label class="form-label">Product Image URL</label><input type="text" class="form-control" id="tst_product_image" placeholder="Optional product image URL"></div>
            <div class="form-group"><label class="form-label">Rating</label>
                <select class="form-control" id="tst_rating">
                    <option value="5">★★★★★ (5)</option><option value="4">★★★★ (4)</option><option value="3">★★★ (3)</option><option value="2">★★ (2)</option><option value="1">★ (1)</option>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Review Text *</label><textarea class="form-control" id="tst_text" rows="4" placeholder="What the customer said..."></textarea></div>
            <div class="form-group"><label class="form-label">Status</label>
                <select class="form-control" id="tst_status"><option value="1">Active</option><option value="0">Inactive</option></select>
            </div>
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:10px;">
            <button class="btn btn-ghost" onclick="closeModal('tstModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveTst()"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        </div>
    </div>
</div>

<script>
function openTstModal(t = null) {
    document.getElementById('tst_id').value            = t?.id || '';
    document.getElementById('tst_name').value          = t?.name || '';
    document.getElementById('tst_avatar').value        = t?.avatar || '';
    document.getElementById('tst_product_name').value  = t?.product_name || '';
    document.getElementById('tst_product_image').value = t?.product_image || '';
    document.getElementById('tst_rating').value        = t?.rating ?? 5;
    document.getElementById('tst_text').value          = t?.text || '';
    document.getElementById('tst_status').value        = t?.is_active ?? 1;
    document.getElementById('tstModalTitle').textContent = t ? 'Edit Testimonial' : 'Add Testimonial';
    renderTstImg();
    openModal('tstModal');
}
async function uploadTstImg(files) {
    const file = files[0]; if(!file) return;
    const fd = new FormData(); fd.append('t_image', file);
    try {
        const res = await fetch('testimonials.php',{method:'POST',body:fd});
        const data = await res.json();
        if(data.success){ document.getElementById('tst_avatar').value=data.url; renderTstImg(); }
        else showToast(data.message,'danger');
    } catch(e){ showToast('Upload error','danger'); }
}
function renderTstImg() {
    const url = document.getElementById('tst_avatar').value;
    document.getElementById('tstImgPreview').innerHTML = url
        ? `<img src="${url}" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:1px solid var(--border-color);">`
        : '';
}
async function saveTst() {
    const name = document.getElementById('tst_name').value.trim();
    const text = document.getElementById('tst_text').value.trim();
    if(!name||!text){ showToast('Name and Review text are required','warning'); return; }
    const data = { action:'save', id:document.getElementById('tst_id').value, name, text,
        avatar:document.getElementById('tst_avatar').value,
        product_name:document.getElementById('tst_product_name').value,
        product_image:document.getElementById('tst_product_image').value,
        rating:document.getElementById('tst_rating').value,
        is_active:document.getElementById('tst_status').value };
    const res = await fetch('testimonials.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});
    const r = await res.json();
    if(r.success){ showToast(r.message,'success'); closeModal('tstModal'); setTimeout(()=>location.reload(),800); }
    else showToast(r.message,'danger');
}
function deleteTst(id) {
    showConfirm('Delete Testimonial','This review will be removed from the storefront.', async () => {
        const res = await fetch('testimonials.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
        const r = await res.json();
        if(r.success){ showToast('Testimonial deleted','success'); const el=document.getElementById(`tst-card-${id}`); if(el){el.style.opacity='0';setTimeout(()=>el.remove(),300);} }
        else showToast(r.message,'danger');
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
