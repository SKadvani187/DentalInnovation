<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    if ($action === 'save') {
        $slug = generateSlug($data['name']);
        if (!empty($data['id'])) {
            db()->execute("UPDATE categories SET name=?,slug=?,description=?,is_active=? WHERE id=?",
                [$data['name'],$slug,$data['description'],$data['is_active'],$data['id']]);
            echo json_encode(['success'=>true,'message'=>'Category updated']);
        } else {
            db()->insert("INSERT INTO categories (name,slug,description,is_active) VALUES (?,?,?,?)",
                [$data['name'],$slug,$data['description'],$data['is_active']??1]);
            echo json_encode(['success'=>true,'message'=>'Category added']);
        }
    } elseif ($action === 'delete') {
        $count = db()->fetchOne("SELECT COUNT(*) as c FROM products WHERE category_id=?",[$data['id']])['c'];
        if ($count > 0) { echo json_encode(['success'=>false,'message'=>"Cannot delete — $count products use this category"]); }
        else { db()->execute("DELETE FROM categories WHERE id=?",[$data['id']]); echo json_encode(['success'=>true,'message'=>'Category deleted']); }
    }
    exit;
}

$categories = db()->fetchAll("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) as product_count FROM categories c ORDER BY c.sort_order, c.name");
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Categories</h1>
        <p>Organize your products by category</p>
    </div>
    <button class="btn btn-gold" onclick="openCatModal()"><i class="fa-solid fa-plus"></i> Add Category</button>
</div>

<div class="grid-3 fade-in">
    <?php foreach($categories as $c): ?>
    <div class="card" id="cat-card-<?= $c['id'] ?>" style="transition:all 0.2s;">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <div class="font-bold" style="font-size:1rem;margin-bottom:4px;"><?= htmlspecialchars($c['name']) ?></div>
                    <div class="text-muted" style="font-size:0.78rem;"><?= $c['description'] ?: 'No description' ?></div>
                </div>
                <span class="badge badge-<?= $c['is_active']?'success':'secondary' ?>"><?= $c['is_active']?'Active':'Inactive' ?></span>
            </div>
            <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <span class="text-gold font-bold" style="font-size:1.3rem;"><?= $c['product_count'] ?></span>
                    <span class="text-muted" style="font-size:0.78rem;margin-left:4px;">products</span>
                </div>
                <div style="display:flex;gap:6px;">
                    <button class="btn btn-ghost btn-sm btn-icon" onclick='openCatModal(<?= json_encode($c) ?>)'><i class="fa-solid fa-pen"></i></button>
                    <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteCat(<?= $c['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modal -->
<div class="modal-overlay" id="catModal" style="display:none;" onclick="if(event.target===this)closeModal('catModal')">
    <div class="modal-box" style="max-width:440px;text-align:left;padding:0;">
        <div class="modal-head" style="padding:18px 22px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
            <h2 id="catModalTitle" style="font-family:'Playfair Display',serif;font-size:1.05rem;background:var(--gold-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Add Category</h2>
            <button class="close-btn" onclick="closeModal('catModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:22px;">
            <input type="hidden" id="cat_id">
            <div class="form-group">
                <label class="form-label">Category Name *</label>
                <input type="text" class="form-control" id="cat_name" placeholder="e.g. Implantology">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="cat_desc" rows="2" placeholder="Brief description..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-control" id="cat_status">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:10px;">
            <button class="btn btn-ghost" onclick="closeModal('catModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveCat()"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        </div>
    </div>
</div>

<script>
function openCatModal(c = null) {
    document.getElementById('cat_id').value    = c?.id || '';
    document.getElementById('cat_name').value  = c?.name || '';
    document.getElementById('cat_desc').value  = c?.description || '';
    document.getElementById('cat_status').value= c?.is_active ?? 1;
    document.getElementById('catModalTitle').textContent = c ? 'Edit Category' : 'Add Category';
    openModal('catModal');
}
async function saveCat() {
    const name = document.getElementById('cat_name').value.trim();
    if (!name) { showToast('Name is required','warning'); return; }
    const data = { action:'save', id:document.getElementById('cat_id').value, name, description:document.getElementById('cat_desc').value, is_active:document.getElementById('cat_status').value };
    const res = await fetch('categories.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});
    const r = await res.json();
    if(r.success){showToast(r.message,'success');closeModal('catModal');setTimeout(()=>location.reload(),800);}
    else showToast(r.message,'danger');
}
function deleteCat(id) {
    showConfirm('Delete Category','Products in this category will become uncategorized.', async () => {
        const res = await fetch('categories.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
        const r = await res.json();
        if(r.success){showToast('Category deleted','success');const el=document.getElementById(`cat-card-${id}`);if(el){el.style.opacity='0';setTimeout(()=>el.remove(),300);}}
        else showToast(r.message,'danger');
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
