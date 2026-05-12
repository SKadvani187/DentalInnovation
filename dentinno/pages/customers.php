<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Customers';

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'save') {
        $d = $data;
        if (!empty($d['id'])) {
            db()->execute("UPDATE customers SET name=?,email=?,phone=?,city=?,state=?,address=?,pincode=?,clinic_name=?,customer_type=?,notes=? WHERE id=?",
                [$d['name'],$d['email'],$d['phone'],$d['city'],$d['state'],$d['address'],$d['pincode'],$d['clinic_name'],$d['customer_type'],$d['notes'],$d['id']]);
            echo json_encode(['success'=>true,'message'=>'Customer updated']);
        } else {
            db()->insert("INSERT INTO customers (name,email,phone,city,state,address,pincode,clinic_name,customer_type,notes) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$d['name'],$d['email'],$d['phone'],$d['city'],$d['state'],$d['address'],$d['pincode'],$d['clinic_name'],$d['customer_type'],$d['notes']]);
            echo json_encode(['success'=>true,'message'=>'Customer added']);
        }
    } elseif ($action === 'delete') {
        db()->execute("DELETE FROM customers WHERE id=?", [$data['id']]);
        echo json_encode(['success'=>true,'message'=>'Customer deleted']);
    }
    exit;
}

// Filters
$search = sanitize($_GET['search'] ?? '');
$type   = sanitize($_GET['type'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page-1)*$per_page;

$where = ["1=1"]; $params = [];
if ($search) { $where[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.clinic_name LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]); }
if ($type)   { $where[] = "c.customer_type = ?"; $params[] = $type; }
$whereStr = implode(' AND ', $where);

$total     = db()->fetchOne("SELECT COUNT(*) as cnt FROM customers c WHERE $whereStr", $params)['cnt'];
$pages     = ceil($total/$per_page);
$customers = db()->fetchAll("SELECT c.* FROM customers c WHERE $whereStr ORDER BY c.created_at DESC LIMIT $per_page OFFSET $offset", $params);

// View single customer
$view_id = (int)($_GET['view'] ?? 0);
$cust_detail = null;
if ($view_id) {
    $cust_detail = db()->fetchOne("SELECT * FROM customers WHERE id=?", [$view_id]);
    if ($cust_detail) {
        $cust_detail['orders'] = db()->fetchAll("SELECT * FROM orders WHERE customer_id=? ORDER BY created_at DESC", [$view_id]);
        $cust_detail['wishlist'] = db()->fetchAll("SELECT w.*, p.name as product_name, p.price FROM wishlists w JOIN products p ON w.product_id=p.id WHERE w.customer_id=?", [$view_id]);
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Customers</h1>
        <p>Manage your customer base — <?= $total ?> customers</p>
    </div>
    <button class="btn btn-gold" onclick="openCustModal()">
        <i class="fa-solid fa-user-plus"></i> Add Customer
    </button>
</div>

<!-- Customer Detail -->
<?php if ($cust_detail): ?>
<div class="card fade-in" style="margin-bottom:24px;">
    <div class="card-header">
        <div style="display:flex;align-items:center;gap:14px;">
            <div class="admin-avatar" style="width:48px;height:48px;font-size:1.2rem;"><?= strtoupper(substr($cust_detail['name'],0,1)) ?></div>
            <div>
                <div class="card-title"><?= htmlspecialchars($cust_detail['name']) ?></div>
                <?php if($cust_detail['clinic_name']): ?><div class="text-muted" style="font-size:0.82rem;"><?= $cust_detail['clinic_name'] ?></div><?php endif; ?>
            </div>
            <span class="badge badge-primary" style="margin-left:8px;"><?= ucfirst($cust_detail['customer_type']) ?></span>
        </div>
        <a href="customers.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>
    <div class="card-body">
        <div class="grid-2" style="margin-bottom:20px;">
            <div>
                <h4 style="font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:12px;">Contact Details</h4>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <div><i class="fa-solid fa-envelope text-gold" style="width:20px;"></i> <?= $cust_detail['email'] ?></div>
                    <div><i class="fa-solid fa-phone text-gold" style="width:20px;"></i> <?= $cust_detail['phone'] ?></div>
                    <div><i class="fa-solid fa-location-dot text-gold" style="width:20px;"></i> <?= $cust_detail['city'] ?>, <?= $cust_detail['state'] ?></div>
                    <?php if($cust_detail['pincode']): ?><div><i class="fa-solid fa-map-pin text-gold" style="width:20px;"></i> <?= $cust_detail['pincode'] ?></div><?php endif; ?>
                </div>
            </div>
            <div>
                <h4 style="font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:12px;">Business Stats</h4>
                <div class="stats-grid" style="grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="stat-card" style="padding:14px;">
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-value" style="font-size:1.4rem;"><?= $cust_detail['total_orders'] ?></div>
                    </div>
                    <div class="stat-card" style="padding:14px;">
                        <div class="stat-label">Total Spent</div>
                        <div class="stat-value text-gold" style="font-size:1.2rem;"><?= formatCurrency($cust_detail['total_spent']) ?></div>
                    </div>
                </div>
                <?php if($cust_detail['notes']): ?>
                <div style="margin-top:12px;padding:12px;background:var(--bg-elevated);border-radius:8px;font-size:0.82rem;color:var(--text-secondary);">
                    <strong>Notes:</strong> <?= htmlspecialchars($cust_detail['notes']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order History -->
        <h4 style="font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px;">Order History (<?= count($cust_detail['orders']) ?>)</h4>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Order #</th><th>Date</th><th>Amount</th><th>Status</th><th>Payment</th></tr></thead>
                <tbody>
                    <?php foreach($cust_detail['orders'] as $o): ?>
                    <tr>
                        <td><a href="orders.php?view=<?= $o['id'] ?>" class="text-gold font-bold"><?= $o['order_number'] ?></a></td>
                        <td><?= formatDate($o['created_at']) ?></td>
                        <td class="font-bold"><?= formatCurrency($o['total']) ?></td>
                        <td><span class="badge badge-<?= statusBadge($o['status']) ?>"><?= $o['status'] ?></span></td>
                        <td><span class="badge badge-<?= statusBadge($o['payment_status']) ?>"><?= $o['payment_status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($cust_detail['orders'])): ?><tr><td colspan="5" class="text-center text-muted">No orders yet</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filter-bar fade-in">
    <div class="search-wrapper">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search name, email, phone..." value="<?= $search ?>">
    </div>
    <select class="form-control" id="typeFilter" style="max-width:160px;">
        <option value="">All Types</option>
        <?php foreach(['individual','clinic','hospital','distributor'] as $t): ?>
        <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="customers.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<!-- Table -->
<div class="card fade-in">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Orders</th>
                    <th>Total Spent</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($customers as $i => $c): ?>
                <tr id="cust-row-<?= $c['id'] ?>">
                    <td class="text-muted"><?= $offset+$i+1 ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="admin-avatar" style="width:34px;height:34px;font-size:0.85rem;"><?= strtoupper(substr($c['name'],0,1)) ?></div>
                            <div>
                                <div class="font-bold" style="font-size:0.84rem;"><?= htmlspecialchars($c['name']) ?></div>
                                <?php if($c['clinic_name']): ?><div class="text-muted" style="font-size:0.72rem;"><?= $c['clinic_name'] ?></div><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div><?= $c['phone'] ?></div>
                        <div class="text-muted" style="font-size:0.73rem;"><?= $c['email'] ?></div>
                    </td>
                    <td><span class="badge badge-info"><?= $c['customer_type'] ?></span></td>
                    <td>
                        <div><?= $c['city'] ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= $c['state'] ?></div>
                    </td>
                    <td class="text-center font-bold"><?= $c['total_orders'] ?></td>
                    <td class="font-bold text-gold"><?= formatCurrency($c['total_spent']) ?></td>
                    <td><?= formatDate($c['created_at'], 'd M Y') ?></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="?view=<?= $c['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="View"><i class="fa-solid fa-eye"></i></a>
                            <button class="btn btn-ghost btn-sm btn-icon" title="Edit" onclick='openCustModal(<?= json_encode($c) ?>)'><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-ghost btn-sm btn-icon" title="Delete" onclick="deleteCust(<?= $c['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($customers)): ?>
                <tr><td colspan="9"><div class="empty-state"><i class="fa-solid fa-user-group"></i><p>No customers found</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($pages > 1): ?>
    <div style="padding:16px 20px;border-top:1px solid var(--border-color);">
        <div class="pagination">
            <?php for($i=1;$i<=$pages;$i++): ?>
            <div class="page-item <?= $i==$page?'active':'' ?>" onclick="goPage(<?= $i ?>)"><?= $i ?></div>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Customer Modal -->
<div class="modal-overlay" id="custModal" style="display:none;" onclick="if(event.target===this)closeModal('custModal')">
    <div class="modal-box modal-lg">
        <div class="modal-head">
            <h2 id="custModalTitle">Add New Customer</h2>
            <button class="close-btn" onclick="closeModal('custModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="cust_id">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" id="cust_name" placeholder="Dr. Rajesh Sharma">
                </div>
                <div class="form-group">
                    <label class="form-label">Customer Type</label>
                    <select class="form-control" id="cust_type">
                        <?php foreach(['individual','clinic','hospital','distributor'] as $t): ?>
                        <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-control" id="cust_email" placeholder="doctor@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" id="cust_phone" placeholder="9876543210">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Clinic / Hospital Name</label>
                <input type="text" class="form-control" id="cust_clinic" placeholder="Sharma Dental Clinic">
            </div>
            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" class="form-control" id="cust_city" placeholder="Mumbai">
                </div>
                <div class="form-group">
                    <label class="form-label">State</label>
                    <input type="text" class="form-control" id="cust_state" placeholder="Maharashtra">
                </div>
                <div class="form-group">
                    <label class="form-label">Pincode</label>
                    <input type="text" class="form-control" id="cust_pincode" placeholder="400001">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea class="form-control" id="cust_address" rows="2" placeholder="Full address..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea class="form-control" id="cust_notes" rows="2" placeholder="Internal notes about this customer..."></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closeModal('custModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveCustomer()"><i class="fa-solid fa-floppy-disk"></i> Save Customer</button>
        </div>
    </div>
</div>

<script>
function applyFilters() {
    const s = document.getElementById('searchInput').value;
    const t = document.getElementById('typeFilter').value;
    window.location.href = `customers.php?search=${s}&type=${t}`;
}
function goPage(p) { window.location.href = `customers.php?page=${p}`; }

function openCustModal(c = null) {
    document.getElementById('cust_id').value      = c?.id || '';
    document.getElementById('cust_name').value    = c?.name || '';
    document.getElementById('cust_type').value    = c?.customer_type || 'individual';
    document.getElementById('cust_email').value   = c?.email || '';
    document.getElementById('cust_phone').value   = c?.phone || '';
    document.getElementById('cust_clinic').value  = c?.clinic_name || '';
    document.getElementById('cust_city').value    = c?.city || '';
    document.getElementById('cust_state').value   = c?.state || '';
    document.getElementById('cust_pincode').value = c?.pincode || '';
    document.getElementById('cust_address').value = c?.address || '';
    document.getElementById('cust_notes').value   = c?.notes || '';
    document.getElementById('custModalTitle').textContent = c ? 'Edit Customer' : 'Add New Customer';
    openModal('custModal');
}

async function saveCustomer() {
    const name  = document.getElementById('cust_name').value.trim();
    const email = document.getElementById('cust_email').value.trim();
    if (!name || !email) { showToast('Name and email are required', 'warning'); return; }
    const data = {
        action: 'save',
        id: document.getElementById('cust_id').value,
        name, email,
        phone:         document.getElementById('cust_phone').value,
        clinic_name:   document.getElementById('cust_clinic').value,
        customer_type: document.getElementById('cust_type').value,
        city:          document.getElementById('cust_city').value,
        state:         document.getElementById('cust_state').value,
        pincode:       document.getElementById('cust_pincode').value,
        address:       document.getElementById('cust_address').value,
        notes:         document.getElementById('cust_notes').value,
    };
    const res = await fetch('customers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});
    const r = await res.json();
    if (r.success) { showToast(r.message,'success'); closeModal('custModal'); setTimeout(()=>location.reload(),800); }
    else showToast(r.message,'danger');
}

function deleteCust(id) {
    showConfirm('Delete Customer','This will delete the customer record. Orders will remain.', async () => {
        const res = await fetch('customers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
        const r = await res.json();
        if(r.success){showToast('Customer deleted','success');const row=document.getElementById(`cust-row-${id}`);if(row){row.style.opacity='0';row.style.transition='opacity 0.3s';setTimeout(()=>row.remove(),300);}}
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
