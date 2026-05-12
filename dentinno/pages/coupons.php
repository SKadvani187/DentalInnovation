<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Coupons';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'save') {
        $d = $data;
        if (!empty($d['id'])) {
            db()->execute("UPDATE coupons SET code=?,type=?,value=?,min_order=?,max_discount=?,uses_limit=?,is_active=?,expires_at=? WHERE id=?",
                [$d['code'],$d['type'],$d['value'],$d['min_order'],$d['max_discount']?:null,$d['uses_limit']?:null,$d['is_active'],$d['expires_at']?:null,$d['id']]);
            echo json_encode(['success'=>true,'message'=>'Coupon updated']);
        } else {
            db()->insert("INSERT INTO coupons (code,type,value,min_order,max_discount,uses_limit,is_active,expires_at) VALUES (?,?,?,?,?,?,?,?)",
                [strtoupper($d['code']),$d['type'],$d['value'],$d['min_order'],$d['max_discount']?:null,$d['uses_limit']?:null,$d['is_active'],$d['expires_at']?:null]);
            echo json_encode(['success'=>true,'message'=>'Coupon created']);
        }
    } elseif ($action === 'delete') {
        db()->execute("DELETE FROM coupons WHERE id=?",[$data['id']]);
        echo json_encode(['success'=>true,'message'=>'Coupon deleted']);
    } elseif ($action === 'toggle') {
        db()->execute("UPDATE coupons SET is_active = NOT is_active WHERE id=?",[$data['id']]);
        echo json_encode(['success'=>true,'message'=>'Status toggled']);
    }
    exit;
}

$coupons = db()->fetchAll("SELECT * FROM coupons ORDER BY created_at DESC");
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Coupons</h1>
        <p>Manage discount coupons and promotional codes</p>
    </div>
    <button class="btn btn-gold" onclick="openCouponModal()">
        <i class="fa-solid fa-plus"></i> Add Coupon
    </button>
</div>

<div class="card fade-in">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Value</th>
                    <th>Min Order</th>
                    <th>Max Discount</th>
                    <th>Used / Limit</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($coupons as $c): ?>
                <tr id="coupon-row-<?= $c['id'] ?>">
                    <td><span class="font-bold text-gold" style="font-family:monospace;font-size:1rem;"><?= $c['code'] ?></span></td>
                    <td><span class="badge badge-info"><?= ucfirst($c['type']) ?></span></td>
                    <td class="font-bold">
                        <?= $c['type']==='percent' ? $c['value'].'%' : formatCurrency($c['value']) ?>
                    </td>
                    <td><?= formatCurrency($c['min_order']) ?></td>
                    <td><?= $c['max_discount'] ? formatCurrency($c['max_discount']) : '<span class="text-muted">—</span>' ?></td>
                    <td>
                        <?= $c['uses_count'] ?> / <?= $c['uses_limit'] ?: '∞' ?>
                        <?php if($c['uses_limit']): ?>
                        <div style="margin-top:4px;background:var(--bg-elevated);border-radius:99px;height:4px;overflow:hidden;">
                            <div style="height:100%;width:<?= min(100, ($c['uses_count']/$c['uses_limit'])*100) ?>%;background:var(--gold-primary);border-radius:99px;"></div>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td><?= $c['expires_at'] ? formatDate($c['expires_at']) : '<span class="text-muted">Never</span>' ?></td>
                    <td><span class="badge badge-<?= $c['is_active']?'success':'secondary' ?>"><?= $c['is_active']?'Active':'Inactive' ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button class="btn btn-ghost btn-sm btn-icon" onclick='openCouponModal(<?= json_encode($c) ?>)'><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-ghost btn-sm btn-icon" onclick="toggleCoupon(<?= $c['id'] ?>)"><i class="fa-solid fa-power-off" style="color:<?= $c['is_active']?'var(--success)':'var(--text-muted)' ?>;"></i></button>
                            <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteCoupon(<?= $c['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($coupons)): ?>
                <tr><td colspan="9"><div class="empty-state"><i class="fa-solid fa-tag"></i><p>No coupons yet</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="couponModal" style="display:none;" onclick="if(event.target===this)closeModal('couponModal')">
    <div class="modal-box modal-lg">
        <div class="modal-head">
            <h2 id="couponModalTitle">Add New Coupon</h2>
            <button class="close-btn" onclick="closeModal('couponModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="coup_id">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Coupon Code *</label>
                    <input type="text" class="form-control" id="coup_code" placeholder="e.g. DENT20" style="text-transform:uppercase;font-weight:700;">
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Type *</label>
                    <select class="form-control" id="coup_type">
                        <option value="percent">Percent (%)</option>
                        <option value="fixed">Fixed Amount (₹)</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Discount Value *</label>
                    <input type="number" class="form-control" id="coup_value" placeholder="e.g. 10 for 10%">
                </div>
                <div class="form-group">
                    <label class="form-label">Minimum Order (₹)</label>
                    <input type="number" class="form-control" id="coup_min" placeholder="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Max Discount (₹)</label>
                    <input type="number" class="form-control" id="coup_max" placeholder="Optional cap">
                </div>
                <div class="form-group">
                    <label class="form-label">Usage Limit</label>
                    <input type="number" class="form-control" id="coup_limit" placeholder="Leave blank for unlimited">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Expires On</label>
                    <input type="date" class="form-control" id="coup_expires">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control" id="coup_status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closeModal('couponModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveCoupon()"><i class="fa-solid fa-floppy-disk"></i> Save Coupon</button>
        </div>
    </div>
</div>

<script>
function openCouponModal(c = null) {
    document.getElementById('coup_id').value      = c?.id || '';
    document.getElementById('coup_code').value    = c?.code || '';
    document.getElementById('coup_type').value    = c?.type || 'percent';
    document.getElementById('coup_value').value   = c?.value || '';
    document.getElementById('coup_min').value     = c?.min_order || '0';
    document.getElementById('coup_max').value     = c?.max_discount || '';
    document.getElementById('coup_limit').value   = c?.uses_limit || '';
    document.getElementById('coup_expires').value = c?.expires_at || '';
    document.getElementById('coup_status').value  = c?.is_active ?? 1;
    document.getElementById('couponModalTitle').textContent = c ? 'Edit Coupon' : 'Add New Coupon';
    openModal('couponModal');
}

async function saveCoupon() {
    const code  = document.getElementById('coup_code').value.trim().toUpperCase();
    const value = document.getElementById('coup_value').value;
    if (!code || !value) { showToast('Code and value are required', 'warning'); return; }
    const data = {
        action:'save', id:document.getElementById('coup_id').value,
        code, type:document.getElementById('coup_type').value, value,
        min_order:document.getElementById('coup_min').value||0,
        max_discount:document.getElementById('coup_max').value,
        uses_limit:document.getElementById('coup_limit').value,
        expires_at:document.getElementById('coup_expires').value,
        is_active:document.getElementById('coup_status').value,
    };
    const res = await fetch('coupons.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});
    const r = await res.json();
    if(r.success){showToast(r.message,'success');closeModal('couponModal');setTimeout(()=>location.reload(),800);}
    else showToast(r.message,'danger');
}

async function toggleCoupon(id) {
    const res = await fetch('coupons.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'toggle',id})});
    showToast('Status updated','success'); setTimeout(()=>location.reload(),600);
}
function deleteCoupon(id) {
    showConfirm('Delete Coupon','This coupon will be permanently removed.', async () => {
        await fetch('coupons.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
        showToast('Coupon deleted','success');
        const row=document.getElementById(`coupon-row-${id}`);if(row){row.style.opacity='0';row.style.transition='0.3s';setTimeout(()=>row.remove(),300);}
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
