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
        $mainProduct = $d['main_product'] ?? null;   // {productId,name,variant,image,price,mrp}
        $freeItems   = is_array($d['free_items'] ?? null) ? $d['free_items'] : []; // [{productId,name,mrp,image}]

        // ---- Server-side validation (authoritative; do not trust client math) ----
        $title   = trim($d['title'] ?? '');
        $special = (float)($d['special_price'] ?? 0);
        if ($title === '')                         { echo json_encode(['success'=>false,'message'=>'Title is required']); exit; }
        if (!$mainProduct || empty($mainProduct['productId'])) { echo json_encode(['success'=>false,'message'=>'Select a main product']); exit; }
        if ($special <= 0)                         { echo json_encode(['success'=>false,'message'=>'Special price must be greater than 0']); exit; }

        // ---- Authoritative calculations (never trust client youSave/totalMrp) ----
        // totalMrp = main product MRP + sum(free item MRPs)
        $totalMrp = (float)($mainProduct['mrp'] ?? 0);
        foreach ($freeItems as $fi) $totalMrp += (float)($fi['mrp'] ?? 0);
        if ($special > $totalMrp) { echo json_encode(['success'=>false,'message'=>'Special price (₹'.$special.') cannot exceed total MRP (₹'.$totalMrp.')']); exit; }
        $youSave = max(0, $totalMrp - $special);

        // valid_till: past dates allowed only when EDITING an existing offer (so admins can fix/extend
        // old offers); new offers must be today or future.
        $validTill = $d['valid_till'] ?: null;
        if ($validTill && empty($d['id']) && $validTill < date('Y-m-d')) {
            echo json_encode(['success'=>false,'message'=>'Valid Till must be today or a future date']); exit;
        }

        $sortOrder  = (int)($d['sort_order'] ?? 0);
        $socialMode = ($d['social_mode'] ?? 'live') === 'manual' ? 'manual' : 'live';
        $socialCount= max(0, (int)($d['social_count'] ?? 0));
        $isTopDeal  = !empty($d['is_top_deal']) ? 1 : 0;
        $mainJson = json_encode($mainProduct);
        $freeJson = json_encode($freeItems);

        if (!empty($d['id'])) {
            db()->execute(
                "UPDATE offers SET title=?,subtitle=?,theme=?,main_product=?,free_items=?,special_price=?,total_mrp=?,you_save=?,save_extra=?,valid_till=?,is_active=?,sort_order=?,social_mode=?,social_count=?,is_top_deal=? WHERE id=?",
                [$title,$d['subtitle']??'',$d['theme']??'orange',$mainJson,$freeJson,$special,$totalMrp,$youSave,($d['save_extra'] ?? null) ?: null,$validTill,$d['is_active']??1,$sortOrder,$socialMode,$socialCount,$isTopDeal,$d['id']]
            );
            echo json_encode(['success'=>true,'message'=>'Offer updated','you_save'=>$youSave,'total_mrp'=>$totalMrp]);
        } else {
            $slug = 'offer-' . substr((string)time(), -6);
            db()->insert(
                "INSERT INTO offers (slug,title,subtitle,theme,main_product,free_items,special_price,total_mrp,you_save,save_extra,valid_till,is_active,sort_order,social_mode,social_count,is_top_deal) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$slug,$title,$d['subtitle']??'',$d['theme']??'orange',$mainJson,$freeJson,$special,$totalMrp,$youSave,($d['save_extra'] ?? null) ?: null,$validTill,$d['is_active']??1,$sortOrder,$socialMode,$socialCount,$isTopDeal]
            );
            echo json_encode(['success'=>true,'message'=>'Offer added','you_save'=>$youSave,'total_mrp'=>$totalMrp]);
        }
    } elseif ($action === 'delete') {
        db()->execute("DELETE FROM offers WHERE id=?", [$d['id']]);
        echo json_encode(['success'=>true,'message'=>'Offer deleted']);
    }
    exit;
}

$offers = db()->fetchAll("SELECT * FROM offers ORDER BY sort_order, id");
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

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Offers</h1>
        <p>Offer Zone deals shown on the storefront. Total MRP &amp; You-Save are calculated automatically.</p>
    </div>
    <button class="btn btn-gold" onclick="openOfferModal()"><i class="fa-solid fa-plus"></i> Add Offer</button>
</div>

<div class="grid-3 fade-in">
    <?php foreach($offers as $o):
        $mp = json_decode($o['main_product'] ?? 'null', true);
        $fi = json_decode($o['free_items'] ?? '[]', true) ?: [];
        $expired = $o['valid_till'] && $o['valid_till'] < date('Y-m-d');
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
                        <?= count($fi) ?> free item(s) · till <?= $o['valid_till'] ?: '—' ?>
                        <?php if($expired): ?><span class="badge badge-warning" style="margin-left:4px;">Expired</span><?php endif; ?>
                    </div>
                    <div style="font-size:.7rem;margin-top:3px;color:var(--text-secondary);">
                        <i class="fa-solid fa-users text-gold"></i> <b><?= $boughtToday ?></b> bought today
                        <span style="opacity:.6;">(<?= $socialMode === 'manual' ? 'custom' : 'live' ?>)</span>
                    </div>
                </div>
            </div>
            <div style="margin-top:12px;display:flex;justify-content:flex-end;gap:6px;">
                <button class="btn btn-ghost btn-sm btn-icon" onclick="openOfferModal(<?= htmlspecialchars(json_encode($o), ENT_QUOTES) ?>)"><i class="fa-solid fa-pen"></i></button>
                <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteOffer(<?= $o['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($offers)): ?><p class="text-muted">No offers yet. Click "Add Offer".</p><?php endif; ?>
</div>

<!-- Modal -->
<div class="modal-overlay" id="offerModal" style="display:none;" onclick="if(event.target===this)closeModal('offerModal')">
    <div class="modal-box" style="max-width:600px;text-align:left;padding:0;">
        <div class="modal-head" style="padding:18px 22px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
            <h2 id="offerModalTitle" style="font-family:'Playfair Display',serif;font-size:1.05rem;background:var(--gold-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Add Offer</h2>
            <button class="close-btn" onclick="closeModal('offerModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:22px;max-height:74vh;overflow:auto;">
            <input type="hidden" id="offer_id">
            <div class="form-group"><label class="form-label">Title *</label><input type="text" class="form-control" id="offer_title" placeholder="e.g. Youni X File Combo"></div>
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
                <div class="form-group" style="flex:1;"><label class="form-label">Special Price (₹) *</label><input type="number" class="form-control" id="offer_special" oninput="recalc()"></div>
                <div class="form-group" style="flex:1;"><label class="form-label">Save Extra (note)</label><input type="text" class="form-control" id="offer_saveextra" placeholder="e.g. free shipping"></div>
            </div>
            <!-- Auto-calculated summary -->
            <div style="background:var(--bg-elevated);border:1px solid var(--border-color);border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:.85rem;">
                <div style="display:flex;justify-content:space-between;"><span class="text-muted">Total MRP (auto)</span><span class="font-bold">₹<span id="calc_totalmrp">0</span></span></div>
                <div style="display:flex;justify-content:space-between;margin-top:4px;"><span class="text-muted">You Save (auto)</span><span class="font-bold" style="color:var(--success);">₹<span id="calc_yousave">0</span> (<span id="calc_pct">0</span>%)</span></div>
                <div id="calc_warn" style="color:var(--danger);font-size:.76rem;margin-top:6px;display:none;"><i class="fa-solid fa-triangle-exclamation"></i> Special price is higher than total MRP.</div>
            </div>

            <div class="form-row" style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><label class="form-label">Valid Till</label><input type="date" class="form-control" id="offer_validtill"></div>
                <div class="form-group" style="flex:1;"><label class="form-label">Sort Order</label><input type="number" class="form-control" id="offer_sortorder" value="0"></div>
            </div>
            <div class="form-row" style="display:flex;gap:10px;">
                <div class="form-group" style="flex:1;"><label class="form-label">Theme color</label>
                    <select class="form-control" id="offer_theme">
                        <option value="orange">Orange</option><option value="blue">Blue</option><option value="pink">Pink</option>
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
    document.getElementById('calc_warn').style.display = (special>totalMrp && totalMrp>0) ? 'block' : 'none';
}

function openOfferModal(o = null) {
    const mp = o ? (typeof o.main_product==='string'?JSON.parse(o.main_product||'null'):o.main_product) : null;
    const fi = o ? (typeof o.free_items==='string'?JSON.parse(o.free_items||'[]'):o.free_items) || [] : [];
    document.getElementById('offer_id').value       = o?.id || '';
    document.getElementById('offer_title').value    = o?.title || '';
    document.getElementById('offer_subtitle').value = o?.subtitle || '';
    document.getElementById('offer_special').value  = o?.special_price || '';
    document.getElementById('offer_saveextra').value= o?.save_extra || '';
    document.getElementById('offer_validtill').value= o?.valid_till || '';
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
    openModal('offerModal');
}

async function saveOffer() {
    const title = document.getElementById('offer_title').value.trim();
    const special = parseFloat(document.getElementById('offer_special').value)||0;
    const mpId = document.getElementById('offer_mp_id').value;
    if(!title){ showToast('Title is required','warning'); return; }
    if(!mpId){ showToast('Select a main product','warning'); return; }
    if(special<=0){ showToast('Special price must be greater than 0','warning'); return; }
    const main_product = {
        productId: mpId,
        name:  document.getElementById('offer_mp_name').value,
        image: document.getElementById('offer_mp_image').value,
        mrp:   parseFloat(document.getElementById('offer_mp_mrp').value)||0,
        price: parseFloat(document.getElementById('offer_mp_price').value)||0,
        variant: 'Any Size',
    };
    const free_items = collectFree();
    const totalMrp = main_product.mrp + free_items.reduce((s,f)=>s+(f.mrp||0),0);
    if(special>totalMrp){ showToast('Special price cannot exceed total MRP (₹'+totalMrp.toLocaleString('en-IN')+')','warning'); return; }
    const validTill = document.getElementById('offer_validtill').value;
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
