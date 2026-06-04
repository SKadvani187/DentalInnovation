<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Offers';

// Image upload (shared products folder)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['offer_image'])) {
    header('Content-Type: application/json');
    $upload_dir = __DIR__ . '/../assets/images/products/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $file = $_FILES['offer_image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) { echo json_encode(['success'=>false,'message'=>'Invalid file type']); exit; }
    if ($file['size'] > 5*1024*1024) { echo json_encode(['success'=>false,'message'=>'File too large']); exit; }
    $fname = 'offer_' . time() . '_' . rand(1000,9999) . '.' . $ext;
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
        $special = (float)($d['special_price'] ?? 0);
        $totalMrp = (float)($d['total_mrp'] ?? 0);
        $youSave = (float)($d['you_save'] ?? max(0, $totalMrp - $special));
        $mainProduct = $d['main_product'] ?? null;   // {productId,name,variant,image,price,mrp}
        $freeItems   = $d['free_items'] ?? [];        // [{name,mrp,image,variant?}]
        $mainJson = $mainProduct ? json_encode($mainProduct) : null;
        $freeJson = json_encode(is_array($freeItems) ? $freeItems : []);

        if (!empty($d['id'])) {
            db()->execute(
                "UPDATE offers SET title=?,subtitle=?,theme=?,accent=?,gradient=?,cta=?,main_product=?,free_items=?,special_price=?,total_mrp=?,you_save=?,save_extra=?,valid_till=?,is_active=? WHERE id=?",
                [$d['title'],$d['subtitle']??'',$d['theme']??'orange',$d['accent']??null,$d['gradient']??null,$d['cta']??null,$mainJson,$freeJson,$special,$totalMrp,$youSave,$d['save_extra']??null,$d['valid_till']?:null,$d['is_active']??1,$d['id']]
            );
            echo json_encode(['success'=>true,'message'=>'Offer updated']);
        } else {
            $slug = 'offer-' . substr((string)time(), -6);
            db()->insert(
                "INSERT INTO offers (slug,title,subtitle,theme,accent,gradient,cta,main_product,free_items,special_price,total_mrp,you_save,save_extra,valid_till,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$slug,$d['title'],$d['subtitle']??'',$d['theme']??'orange',$d['accent']??null,$d['gradient']??null,$d['cta']??null,$mainJson,$freeJson,$special,$totalMrp,$youSave,$d['save_extra']??null,$d['valid_till']?:null,$d['is_active']??1]
            );
            echo json_encode(['success'=>true,'message'=>'Offer added']);
        }
    } elseif ($action === 'delete') {
        db()->execute("DELETE FROM offers WHERE id=?", [$d['id']]);
        echo json_encode(['success'=>true,'message'=>'Offer deleted']);
    }
    exit;
}

$offers = db()->fetchAll("SELECT * FROM offers ORDER BY sort_order, id");
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Offers</h1>
        <p>Offer Zone deals shown on the storefront</p>
    </div>
    <button class="btn btn-gold" onclick="openOfferModal()"><i class="fa-solid fa-plus"></i> Add Offer</button>
</div>

<div class="grid-3 fade-in">
    <?php foreach($offers as $o):
        $mp = json_decode($o['main_product'] ?? 'null', true);
        $fi = json_decode($o['free_items'] ?? '[]', true) ?: [];
    ?>
    <div class="card" id="offer-card-<?= $o['id'] ?>" style="transition:all .2s;">
        <div class="card-body">
            <div style="display:flex;gap:12px;">
                <img src="<?= htmlspecialchars($mp['image'] ?? '') ?>" style="width:60px;height:60px;object-fit:cover;border-radius:8px;background:#fff;border:1px solid var(--border-color);flex-shrink:0;" onerror="this.style.opacity=.2">
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                        <div class="font-bold" style="font-size:.95rem;"><?= htmlspecialchars($o['title']) ?></div>
                        <span class="badge badge-<?= $o['is_active']?'success':'secondary' ?>" style="flex-shrink:0;"><?= $o['is_active']?'Active':'Inactive' ?></span>
                    </div>
                    <div class="text-muted" style="font-size:.74rem;"><?= htmlspecialchars($o['subtitle'] ?? '') ?></div>
                    <div style="margin-top:4px;">
                        <span class="text-gold font-bold">₹<?= number_format($o['special_price'],0) ?></span>
                        <span class="text-muted" style="text-decoration:line-through;font-size:.74rem;margin-left:5px;">₹<?= number_format($o['total_mrp'],0) ?></span>
                        <span style="color:var(--success);font-size:.72rem;margin-left:4px;">save ₹<?= number_format($o['you_save'],0) ?></span>
                    </div>
                    <div class="text-muted" style="font-size:.7rem;margin-top:3px;"><?= count($fi) ?> free item(s) · till <?= $o['valid_till'] ?: '—' ?></div>
                </div>
            </div>
            <div style="margin-top:12px;display:flex;justify-content:flex-end;gap:6px;">
                <button class="btn btn-ghost btn-sm btn-icon" onclick='openOfferModal(<?= json_encode($o) ?>)'><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteOffer(<?= $o['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($offers)): ?><p class="text-muted">No offers yet. Click "Add Offer".</p><?php endif; ?>
</div>

<!-- Modal -->
<div class="modal-overlay" id="offerModal" style="display:none;" onclick="if(event.target===this)closeModal('offerModal')">
    <div class="modal-box" style="max-width:560px;text-align:left;padding:0;">
        <div class="modal-head" style="padding:18px 22px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
            <h2 id="offerModalTitle" style="font-family:'Playfair Display',serif;font-size:1.05rem;background:var(--gold-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Add Offer</h2>
            <button class="close-btn" onclick="closeModal('offerModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:22px;max-height:72vh;overflow:auto;">
            <input type="hidden" id="offer_id">
            <div class="form-group"><label class="form-label">Title *</label><input type="text" class="form-control" id="offer_title" placeholder="e.g. Youni X File"></div>
            <div class="form-group"><label class="form-label">Subtitle</label><input type="text" class="form-control" id="offer_subtitle" placeholder="(MOQ 20 Pack)"></div>
            <div class="form-row" style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><label class="form-label">Special Price (₹) *</label><input type="number" class="form-control" id="offer_special"></div>
                <div class="form-group" style="flex:1;"><label class="form-label">Total MRP (₹)</label><input type="number" class="form-control" id="offer_totalmrp"></div>
            </div>
            <div class="form-row" style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><label class="form-label">You Save (₹)</label><input type="number" class="form-control" id="offer_yousave" placeholder="auto if blank"></div>
                <div class="form-group" style="flex:1;"><label class="form-label">Valid Till</label><input type="date" class="form-control" id="offer_validtill"></div>
            </div>
            <div class="form-row" style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><label class="form-label">Theme</label>
                    <select class="form-control" id="offer_theme">
                        <option value="orange">Orange</option><option value="blue">Blue</option><option value="pink">Pink</option>
                        <option value="purple">Purple</option><option value="yellow">Yellow</option><option value="maroon">Maroon</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1;"><label class="form-label">Status</label>
                    <select class="form-control" id="offer_status"><option value="1">Active</option><option value="0">Inactive</option></select>
                </div>
            </div>

            <hr style="border:none;border-top:1px solid var(--border-color);margin:14px 0;">
            <label class="form-label" style="font-weight:700;">Main Product</label>
            <div class="form-group"><input type="text" class="form-control" id="offer_mp_name" placeholder="Product name"></div>
            <div class="form-row" style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><input type="number" class="form-control" id="offer_mp_price" placeholder="Price ₹"></div>
                <div class="form-group" style="flex:1;"><input type="number" class="form-control" id="offer_mp_mrp" placeholder="MRP ₹"></div>
            </div>
            <div class="form-group">
                <div class="drop-zone" id="offerDrop" onclick="document.getElementById('offerImgInput').click()" style="border:2px dashed var(--border-active);border-radius:10px;padding:16px;text-align:center;cursor:pointer;">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;color:var(--gold-primary);display:block;margin-bottom:5px;"></i>
                    <span style="color:var(--text-secondary);font-size:.8rem;">Upload main product image</span>
                </div>
                <input type="file" id="offerImgInput" accept="image/*" style="display:none" onchange="uploadOfferImg(this.files)">
                <div id="offerImgPreview" style="margin-top:8px;"></div>
                <input type="hidden" id="offer_mp_image">
            </div>

            <hr style="border:none;border-top:1px solid var(--border-color);margin:14px 0;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <label class="form-label" style="font-weight:700;margin:0;">Free Items</label>
                <button type="button" class="btn btn-ghost btn-sm" onclick="addFreeItemRow()"><i class="fa-solid fa-plus"></i> Add</button>
            </div>
            <div id="freeItemsContainer"></div>
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:10px;">
            <button class="btn btn-ghost" onclick="closeModal('offerModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveOffer()"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        </div>
    </div>
</div>

<script>
function freeItemRowHtml(it={}) {
    return `<div class="free-item-row" style="display:flex;gap:6px;margin-bottom:6px;align-items:center;">
        <input class="form-control" data-fi-name placeholder="Free item name" value="${(it.name||'').replace(/"/g,'&quot;')}" style="flex:2;">
        <input class="form-control" data-fi-mrp type="number" placeholder="MRP" value="${it.mrp||''}" style="flex:1;">
        <input class="form-control" data-fi-image placeholder="Image URL" value="${(it.image||'').replace(/"/g,'&quot;')}" style="flex:2;">
        <button type="button" class="btn btn-ghost btn-sm" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark" style="color:var(--danger);"></i></button>
    </div>`;
}
function addFreeItemRow(it) { document.getElementById('freeItemsContainer').insertAdjacentHTML('beforeend', freeItemRowHtml(it||{})); }

function openOfferModal(o = null) {
    const mp = o ? (typeof o.main_product==='string'?JSON.parse(o.main_product||'null'):o.main_product) : null;
    const fi = o ? (typeof o.free_items==='string'?JSON.parse(o.free_items||'[]'):o.free_items) || [] : [];
    document.getElementById('offer_id').value       = o?.id || '';
    document.getElementById('offer_title').value    = o?.title || '';
    document.getElementById('offer_subtitle').value = o?.subtitle || '';
    document.getElementById('offer_special').value  = o?.special_price || '';
    document.getElementById('offer_totalmrp').value = o?.total_mrp || '';
    document.getElementById('offer_yousave').value  = o?.you_save || '';
    document.getElementById('offer_validtill').value= o?.valid_till || '';
    document.getElementById('offer_theme').value    = o?.theme || 'orange';
    document.getElementById('offer_status').value   = o?.is_active ?? 1;
    document.getElementById('offer_mp_name').value  = mp?.name || '';
    document.getElementById('offer_mp_price').value = mp?.price || '';
    document.getElementById('offer_mp_mrp').value   = mp?.mrp || '';
    document.getElementById('offer_mp_image').value = mp?.image || '';
    renderOfferImg();
    const cont = document.getElementById('freeItemsContainer'); cont.innerHTML='';
    fi.forEach(addFreeItemRow);
    document.getElementById('offerModalTitle').textContent = o ? 'Edit Offer' : 'Add Offer';
    openModal('offerModal');
}
async function uploadOfferImg(files) {
    const file = files[0]; if(!file) return;
    const fd = new FormData(); fd.append('offer_image', file);
    try {
        const res = await fetch('offers.php',{method:'POST',body:fd});
        const data = await res.json();
        if(data.success){ document.getElementById('offer_mp_image').value=data.url; renderOfferImg(); }
        else showToast(data.message,'danger');
    } catch(e){ showToast('Upload error','danger'); }
}
function renderOfferImg() {
    const url = document.getElementById('offer_mp_image').value;
    document.getElementById('offerImgPreview').innerHTML = url
        ? `<div style="position:relative;width:80px;height:80px;"><img src="${url}" style="width:100%;height:100%;object-fit:cover;border-radius:8px;background:#fff;border:1px solid var(--border-color);"><button onclick="document.getElementById('offer_mp_image').value='';renderOfferImg()" style="position:absolute;top:3px;right:3px;width:18px;height:18px;border:none;border-radius:50%;background:rgba(231,76,60,.9);color:#fff;cursor:pointer;">×</button></div>`
        : '';
}
async function saveOffer() {
    const title = document.getElementById('offer_title').value.trim();
    const special = document.getElementById('offer_special').value;
    if(!title||!special){ showToast('Title and Special Price are required','warning'); return; }
    const freeItems = [];
    document.querySelectorAll('#freeItemsContainer .free-item-row').forEach(row=>{
        const name=row.querySelector('[data-fi-name]').value.trim();
        if(name) freeItems.push({ name, mrp:parseFloat(row.querySelector('[data-fi-mrp]').value)||0, image:row.querySelector('[data-fi-image]').value.trim() });
    });
    const main_product = {
        name:  document.getElementById('offer_mp_name').value.trim(),
        price: parseFloat(document.getElementById('offer_mp_price').value)||0,
        mrp:   parseFloat(document.getElementById('offer_mp_mrp').value)||0,
        image: document.getElementById('offer_mp_image').value,
        variant: 'Any Size',
    };
    const data = { action:'save', id:document.getElementById('offer_id').value, title,
        subtitle:document.getElementById('offer_subtitle').value,
        special_price:special, total_mrp:document.getElementById('offer_totalmrp').value,
        you_save:document.getElementById('offer_yousave').value,
        valid_till:document.getElementById('offer_validtill').value,
        theme:document.getElementById('offer_theme').value,
        is_active:document.getElementById('offer_status').value,
        main_product, free_items:freeItems };
    const res = await fetch('offers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});
    const r = await res.json();
    if(r.success){ showToast(r.message,'success'); closeModal('offerModal'); setTimeout(()=>location.reload(),800); }
    else showToast(r.message,'danger');
}
function deleteOffer(id) {
    showConfirm('Delete Offer','This offer will be removed from the storefront.', async () => {
        const res = await fetch('offers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
        const r = await res.json();
        if(r.success){ showToast('Offer deleted','success'); const el=document.getElementById(`offer-card-${id}`); if(el){el.style.opacity='0';setTimeout(()=>el.remove(),300);} }
        else showToast(r.message,'danger');
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
