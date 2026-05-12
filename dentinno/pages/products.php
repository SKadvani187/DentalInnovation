<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Products';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'delete') {
        db()->execute("DELETE FROM products WHERE id = ?", [$data['id']]);
        echo json_encode(['success' => true, 'message' => 'Product deleted']);
    } elseif ($action === 'toggle') {
        db()->execute("UPDATE products SET is_active = NOT is_active WHERE id = ?", [$data['id']]);
        echo json_encode(['success' => true, 'message' => 'Status updated']);
    } elseif ($action === 'save') {
        $d = $data;
        $disc_price = !empty($d['discount_price']) ? $d['discount_price'] : null;
        $disc_pct   = ($disc_price && $d['price'] > 0) ? round((($d['price'] - $disc_price) / $d['price']) * 100, 2) : 0;
        $features   = !empty($d['features']) ? json_encode($d['features']) : null;
        $key_specs  = !empty($d['key_specifications']) ? json_encode($d['key_specifications']) : null;
        $images_json = !empty($d['images']) ? json_encode($d['images']) : null;
        if (!empty($d['id'])) {
            db()->execute("UPDATE products SET name=?,category_id=?,price=?,discount_price=?,discount_percent=?,stock=?,short_description=?,full_description=?,features=?,packing_info=?,key_specifications=?,directions_for_use=?,additional_information=?,warranty_info=?,images=?,weight_kg=?,is_active=?,is_featured=? WHERE id=?",
                [$d['name'],$d['category_id']?:null,$d['price'],$disc_price,$disc_pct,$d['stock'],$d['short_description'],$d['full_description'],$features,$d['packing_info'],$key_specs,$d['directions_for_use'],$d['additional_information'],$d['warranty_info'],$images_json,$d['weight_kg']?:null,$d['is_active']??1,$d['is_featured']??0,$d['id']]);
            $pid = $d['id'];
            echo json_encode(['success' => true, 'message' => 'Product updated', 'id' => $pid]);
        } else {
            $slug = generateSlug($d['name']) . '-' . time();
            $sku  = 'SKU-' . strtoupper(substr(md5($d['name']), 0, 6));
            $pid = db()->insert("INSERT INTO products (name,slug,sku,category_id,price,discount_price,discount_percent,stock,short_description,full_description,features,packing_info,key_specifications,directions_for_use,additional_information,warranty_info,images,weight_kg,is_active,is_featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$d['name'],$slug,$sku,$d['category_id']?:null,$d['price'],$disc_price,$disc_pct,$d['stock'],$d['short_description'],$d['full_description'],$features,$d['packing_info'],$key_specs,$d['directions_for_use'],$d['additional_information'],$d['warranty_info'],$images_json,$d['weight_kg']?:null,$d['is_active']??1,$d['is_featured']??0]);
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
    } elseif ($action === 'get_faqs') {
        $faqs = db()->fetchAll("SELECT * FROM product_faqs WHERE product_id=? ORDER BY sort_order", [$data['product_id']]);
        echo json_encode(['success' => true, 'faqs' => $faqs]);
    } elseif ($action === 'get_reviews') {
        $reviews = db()->fetchAll("SELECT * FROM product_reviews WHERE product_id=? ORDER BY created_at DESC", [$data['product_id']]);
        echo json_encode(['success' => true, 'reviews' => $reviews]);
    } elseif ($action === 'approve_review') {
        db()->execute("UPDATE product_reviews SET is_approved=? WHERE id=?", [$data['approved'],$data['id']]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'delete_review') {
        db()->execute("DELETE FROM product_reviews WHERE id=?", [$data['id']]);
        echo json_encode(['success' => true]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['product_image'])) {
    header('Content-Type: application/json');
    $upload_dir = __DIR__ . '/../assets/images/products/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $file = $_FILES['product_image'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) { echo json_encode(['success'=>false,'message'=>'Invalid file type']); exit; }
    if ($file['size'] > 5*1024*1024) { echo json_encode(['success'=>false,'message'=>'File too large']); exit; }
    $fname = 'prod_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $fname)) {
        echo json_encode(['success'=>true,'url'=> APP_URL.'/assets/images/products/'.$fname]);
    } else { echo json_encode(['success'=>false,'message'=>'Upload failed']); }
    exit;
}

$search  = sanitize($_GET['search'] ?? '');
$cat_id  = (int)($_GET['cat'] ?? 0);
$status  = sanitize($_GET['status'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));
$per_page = 15; $offset = ($page-1)*$per_page;
$where = ["1=1"]; $params = [];
if ($search) { $where[] = "p.name LIKE ?"; $params[] = "%$search%"; }
if ($cat_id)  { $where[] = "p.category_id = ?"; $params[] = $cat_id; }
if ($status !== '') { $where[] = "p.is_active = ?"; $params[] = (int)$status; }
$whereStr = implode(' AND ', $where);
$total = db()->fetchOne("SELECT COUNT(*) as cnt FROM products p WHERE $whereStr", $params)['cnt'];
$pages = ceil($total/$per_page);
$products = db()->fetchAll("SELECT p.*,c.name as category FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE $whereStr ORDER BY p.created_at DESC LIMIT $per_page OFFSET $offset", $params);
$categories = db()->fetchAll("SELECT * FROM categories WHERE is_active=1 ORDER BY name");
include __DIR__ . '/../includes/header.php';
?>
<style>
.tab-nav{display:flex;gap:2px;border-bottom:1px solid var(--border-color);margin-bottom:0;padding:0 20px;overflow-x:auto;}
.tab-btn{padding:11px 16px;background:none;border:none;color:var(--text-secondary);font-size:.82rem;font-weight:500;cursor:pointer;border-bottom:2px solid transparent;transition:all .2s;white-space:nowrap;}
.tab-btn.active{color:var(--gold-primary);border-bottom-color:var(--gold-primary);}
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
  <button class="btn btn-gold" onclick="openProductModal()"><i class="fa-solid fa-plus"></i> Add Product</button>
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
  </select>
  <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
  <a href="products.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<div class="voice-banner" id="voiceBanner"><i class="fa-solid fa-microphone" style="color:#9B59B6;margin-right:6px;"></i><span id="voiceBannerText"></span><button onclick="document.getElementById('voiceBanner').style.display='none'" style="float:right;background:none;border:none;color:var(--text-muted);cursor:pointer;">✕</button></div>

<div class="card fade-in">
  <div class="table-responsive">
    <table>
      <thead><tr><th>#</th><th>Product</th><th>Category</th><th>Price</th><th>Discount</th><th>Stock</th><th>Weight</th><th>Status</th><th>★</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($products as $i => $p):
          $imgs = $p['images'] ? json_decode($p['images'],true) : null;
          $thumb = $imgs && !empty($imgs[0]) ? $imgs[0] : null;
        ?>
        <tr id="product-row-<?= $p['id'] ?>">
          <td class="text-muted"><?= $offset+$i+1 ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px;">
              <div style="background:var(--bg-elevated);border-radius:8px;width:40px;height:40px;overflow:hidden;flex-shrink:0;">
                <?php if($thumb): ?><img src="<?= htmlspecialchars($thumb) ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?><div style="width:100%;height:100%;display:grid;place-items:center;"><i class="fa-solid fa-tooth" style="color:var(--gold-primary);"></i></div><?php endif; ?>
              </div>
              <div>
                <div class="font-bold" style="font-size:.85rem;"><?= htmlspecialchars($p['name']) ?></div>
                <div class="text-muted" style="font-size:.72rem;">SKU: <?= $p['sku'] ?></div>
              </div>
            </div>
          </td>
          <td><?= $p['category'] ?? '<span class="text-muted">—</span>' ?></td>
          <td class="font-bold"><?= formatCurrency($p['price']) ?></td>
          <td><?php if($p['discount_price']): ?><div><?= formatCurrency($p['discount_price']) ?></div><div class="badge badge-success"><?= $p['discount_percent'] ?>% off</div><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
          <td><span class="<?= $p['stock']<=$p['min_stock_alert']?'stock-low':($p['stock']<=10?'stock-warn':'stock-ok') ?>"><?= $p['stock'] ?> units</span></td>
          <td><?= $p['weight_kg'] ? $p['weight_kg'].' kg' : '<span class="text-muted">—</span>' ?></td>
          <td><span class="badge badge-<?= $p['is_active']?'success':'secondary' ?>"><?= $p['is_active']?'Active':'Inactive' ?></span></td>
          <td><?= $p['is_featured']?'<i class="fa-solid fa-star text-gold"></i>':'<i class="fa-regular fa-star text-muted"></i>' ?></td>
          <td>
            <div style="display:flex;gap:4px;">
              <button class="btn btn-ghost btn-sm btn-icon" title="Edit" onclick='openProductModal(<?= json_encode($p) ?>)'><i class="fa-solid fa-pen"></i></button>
              <button class="btn btn-ghost btn-sm btn-icon" title="FAQs" onclick="openFaqModal(<?= $p['id'] ?>)"><i class="fa-regular fa-circle-question"></i></button>
              <button class="btn btn-ghost btn-sm btn-icon" title="Reviews" onclick="openReviewsModal(<?= $p['id'] ?>,'<?= addslashes(htmlspecialchars($p['name'])) ?>')"><i class="fa-regular fa-star"></i></button>
              <button class="btn btn-ghost btn-sm btn-icon" title="Toggle" onclick="toggleProduct(<?= $p['id'] ?>)"><i class="fa-solid fa-power-off" style="color:<?= $p['is_active']?'var(--success)':'var(--text-muted)' ?>;"></i></button>
              <button class="btn btn-ghost btn-sm btn-icon" title="Delete" onclick="deleteProduct(<?= $p['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($products)): ?><tr><td colspan="10"><div class="empty-state"><i class="fa-solid fa-boxes-stacked"></i><p>No products found</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if($pages > 1): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border-color);">
    <div class="pagination">
      <?php for($i=1;$i<=$pages;$i++): ?><div class="page-item <?= $i==$page?'active':'' ?>" onclick="goPage(<?= $i ?>)"><?= $i ?></div><?php endfor; ?>
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
        <button class="tab-btn" onclick="switchTab('content',this)"><i class="fa-solid fa-align-left" style="margin-right:5px;"></i>Content</button>
        <button class="tab-btn" onclick="switchTab('specs',this)"><i class="fa-solid fa-list" style="margin-right:5px;"></i>Specs</button>
        <button class="tab-btn" onclick="switchTab('images',this)"><i class="fa-solid fa-images" style="margin-right:5px;"></i>Images</button>
        <button class="tab-btn" onclick="switchTab('faqs_tab',this)"><i class="fa-regular fa-circle-question" style="margin-right:5px;"></i>FAQs</button>
        <button class="tab-btn" onclick="switchTab('ship_tab',this)"><i class="fa-solid fa-truck" style="margin-right:5px;"></i>Shipping</button>
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
          <div class="form-row">
            <div class="form-group"><label class="form-label">Status</label><select class="form-control" id="prod_status"><option value="1">Active</option><option value="0">Inactive</option></select></div>
            <div class="form-group"><label class="form-label">Featured</label><select class="form-control" id="prod_featured"><option value="0">No</option><option value="1">Yes — Homepage</option></select></div>
          </div>
        </div>

        <!-- CONTENT -->
        <div id="tab-content" class="tab-pane">
          <div class="form-group"><label class="form-label">Full Description</label><textarea class="form-control" id="prod_full_desc" rows="4" placeholder="Detailed product description..."></textarea></div>
          <div class="form-group"><label class="form-label">Features <small class="text-muted">(one per line)</small></label><textarea class="form-control" id="prod_features" rows="4" placeholder="High precision RF technology&#10;Digital display panel&#10;Autoclavable tips&#10;CE & ISO certified"></textarea></div>
          <div class="form-group"><label class="form-label">Directions for Use</label><textarea class="form-control" id="prod_directions" rows="3" placeholder="Step-by-step usage instructions..."></textarea></div>
          <div class="form-group"><label class="form-label">Packing Information</label><textarea class="form-control" id="prod_packing" rows="2" placeholder="e.g. 1 Unit, Accessory Kit, Power Adapter, User Manual"></textarea></div>
          <div class="form-group"><label class="form-label">Additional Information</label><textarea class="form-control" id="prod_additional" rows="3" placeholder="Regulatory compliance, certifications, legal disclaimers..."></textarea></div>
          <div class="form-group"><label class="form-label">Warranty</label><textarea class="form-control" id="prod_warranty" rows="2" placeholder="e.g. 2 Year Manufacturer Warranty on unit, 6 months on accessories"></textarea></div>
        </div>

        <!-- SPECS -->
        <div id="tab-specs" class="tab-pane">
          <label class="form-label" style="margin-bottom:10px;">Key Specifications</label>
          <div id="specs_container"></div>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addSpecRow()" style="margin-top:8px;"><i class="fa-solid fa-plus"></i> Add Row</button>
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
            <div class="form-group"><label class="form-label">Product Weight (kg)</label><input type="number" step="0.001" class="form-control" id="prod_weight" placeholder="e.g. 2.500"></div>
            <div class="form-group"><label class="form-label">Shipping Class</label>
              <select class="form-control" id="prod_ship_class">
                <option value="standard">Standard</option><option value="bulky">Bulky / Heavy</option>
                <option value="fragile">Fragile</option><option value="express_only">Express Only</option><option value="free">Free Shipping</option>
              </select>
            </div>
          </div>
          <div class="form-row-3">
            <div class="form-group"><label class="form-label">Length (cm)</label><input type="number" class="form-control" id="prod_length" placeholder="0"></div>
            <div class="form-group"><label class="form-label">Width (cm)</label><input type="number" class="form-control" id="prod_width" placeholder="0"></div>
            <div class="form-group"><label class="form-label">Height (cm)</label><input type="number" class="form-control" id="prod_height" placeholder="0"></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Override Shipping Cost (₹)</label><input type="number" class="form-control" id="prod_ship_cost" placeholder="Leave blank to use global rules"></div>
            <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px;">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" id="prod_free_ship" style="width:16px;height:16px;accent-color:var(--gold-primary);"><span class="form-label" style="margin:0;">Free Shipping for this product</span></label>
            </div>
          </div>
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
function applyFilters(){window.location.href=`products.php?search=${encodeURIComponent(document.getElementById('searchInput').value)}&cat=${document.getElementById('catFilter').value}&status=${document.getElementById('statusFilter').value}`;}
function goPage(p){window.location.href=`products.php?page=${p}`;}

// Spec rows
function addSpecRow(k='',v=''){
  const id='s'+Date.now()+Math.random().toString(36).slice(2,6);
  const d=document.createElement('div');d.className='spec-row';d.id=id;
  d.innerHTML=`<input type="text" class="form-control" placeholder="Specification" value="${k.replace(/"/g,'&quot;')}" data-spec-key>
    <input type="text" class="form-control" placeholder="Value" value="${v.replace(/"/g,'&quot;')}" data-spec-val>
    <button type="button" class="btn btn-ghost btn-sm btn-icon" onclick="this.closest('.spec-row').remove()"><i class="fa-solid fa-minus" style="color:var(--danger);"></i></button>`;
  document.getElementById('specs_container').appendChild(d);
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
  document.getElementById('prod_status').value=p?.is_active??1;
  document.getElementById('prod_featured').value=p?.is_featured??0;
  document.getElementById('prod_weight').value=p?.weight_kg||'';
  try{
    const feats=p?.features?JSON.parse(p.features):[];
    document.getElementById('prod_features').value=Array.isArray(feats)?feats.join('\n'):'';
  }catch(e){document.getElementById('prod_features').value='';}
  document.getElementById('prod_directions').value=p?.directions_for_use||'';
  document.getElementById('prod_packing').value=p?.packing_info||'';
  document.getElementById('prod_additional').value=p?.additional_information||'';
  document.getElementById('prod_warranty').value=p?.warranty_info||'';
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
  // Images
  uploadedImages=p?.images?(typeof p.images==='string'?JSON.parse(p.images):p.images):[];
  renderImgs();
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
  const featText=document.getElementById('prod_features').value;
  const features=featText.split('\n').map(l=>l.replace(/^[•\-*]\s*/,'')).filter(l=>l.trim());
  let images=[];try{images=JSON.parse(document.getElementById('prod_images_json').value);}catch(e){}
  const payload={action:'save',id:document.getElementById('prod_id').value,name,price,stock,
    category_id:document.getElementById('prod_category').value,
    short_description:document.getElementById('prod_short_desc').value,
    full_description:document.getElementById('prod_full_desc').value,
    features,packing_info:document.getElementById('prod_packing').value,
    key_specifications:specs,directions_for_use:document.getElementById('prod_directions').value,
    additional_information:document.getElementById('prod_additional').value,
    warranty_info:document.getElementById('prod_warranty').value,
    discount_price:document.getElementById('prod_discount').value,
    weight_kg:document.getElementById('prod_weight').value,
    is_active:document.getElementById('prod_status').value,
    is_featured:document.getElementById('prod_featured').value,
    images,faqs};
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
  else body.innerHTML=data.faqs.map((f,i)=>`<div class="faq-item"><div style="font-weight:600;color:var(--gold-primary);margin-bottom:6px;">${i+1}. ${f.question}</div><div style="color:var(--text-secondary);font-size:.85rem;">${f.answer}</div></div>`).join('');
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
        <div><div style="font-weight:600;">${r.reviewer_name}</div><div style="color:#F0D080;">${'★'.repeat(r.rating)}${'☆'.repeat(5-r.rating)}</div></div>
        <div style="display:flex;gap:6px;">
          <span class="badge badge-${r.is_approved?'success':'warning'}">${r.is_approved?'Approved':'Pending'}</span>
          ${r.is_verified?'<span class="badge badge-info">Verified</span>':''}
          <button class="btn btn-ghost btn-sm btn-icon" onclick="approveReview(${r.id},${r.is_approved?0:1})"><i class="fa-solid fa-${r.is_approved?'ban':'check'}" style="color:${r.is_approved?'var(--danger)':'var(--success)'}"></i></button>
          <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteReview(${r.id})"><i class="fa-solid fa-trash" style="color:var(--danger)"></i></button>
        </div>
      </div>
      ${r.title?`<div style="font-weight:500;margin-top:8px;">${r.title}</div>`:''}
      <div style="color:var(--text-secondary);font-size:.85rem;margin-top:6px;">${r.review}</div>
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
  showConfirm('Delete Product','This will permanently delete the product. Continue?',async()=>{
    const res=await fetch('products.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
    const result=await res.json();
    if(result.success){showToast('Product deleted','success');const row=document.getElementById('product-row-'+id);if(row){row.style.opacity='0';row.style.transition='.3s';setTimeout(()=>row.remove(),300);}}
  });
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
