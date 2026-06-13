<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Admin Users';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    // SECURITY: managing admin accounts (and assigning roles) is a super_admin-only action.
    // Without this gate any logged-in admin could escalate themselves to super_admin.
    if (!hasPermission('manage_admins')) {
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'Forbidden: super admin only']); exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    // Allowlist roles — never trust the client-supplied role string verbatim (mass-assignment).
    $ALLOWED_ROLES = ['super_admin','admin','staff'];
    $role = in_array($data['role'] ?? '', $ALLOWED_ROLES, true) ? $data['role'] : 'staff';

    if ($action === 'save') {
        $name     = trim((string)($data['name'] ?? ''));
        $email    = trim((string)($data['email'] ?? ''));
        $isActive = (int)($data['is_active'] ?? 1);
        if ($name === '' || $email === '') { echo json_encode(['success'=>false,'message'=>'Name and email are required']); exit; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'Enter a valid email address']); exit; }
        // Enforce a minimum password length whenever a password is being set.
        if (!empty($data['password']) && strlen((string)$data['password']) < ADMIN_MIN_PASSWORD) {
            echo json_encode(['success'=>false,'message'=>'Password must be at least '.ADMIN_MIN_PASSWORD.' characters']); exit;
        }
        if (!empty($data['id'])) {
            // Lockout guard: never demote/deactivate the LAST active super admin.
            $target = db()->fetchOne("SELECT role FROM admin_users WHERE id=?", [$data['id']]);
            if ($target && $target['role'] === 'super_admin' && ($role !== 'super_admin' || $isActive === 0)) {
                $others = (int)(db()->fetchOne("SELECT COUNT(*) c FROM admin_users WHERE role='super_admin' AND is_active=1 AND id<>?", [$data['id']])['c'] ?? 0);
                if ($others < 1) { echo json_encode(['success'=>false,'message'=>'Cannot demote or deactivate the last active super admin']); exit; }
            }
            $dupe = db()->fetchOne("SELECT id FROM admin_users WHERE email=? AND id<>?", [$email, $data['id']]);
            if ($dupe) { echo json_encode(['success'=>false,'message'=>'Email already in use']); exit; }
            $extra = !empty($data['password']) ? ", password=?" : "";
            $params = [$name,$email,$role,$isActive];
            if (!empty($data['password'])) $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            $params[] = $data['id'];
            db()->execute("UPDATE admin_users SET name=?,email=?,role=?,is_active=?$extra WHERE id=?", $params);
            echo json_encode(['success'=>true,'message'=>'Admin updated']);
        } else {
            if (empty($data['password'])) { echo json_encode(['success'=>false,'message'=>'Password is required']); exit; }
            $exists = db()->fetchOne("SELECT id FROM admin_users WHERE email=?",[$email]);
            if ($exists) { echo json_encode(['success'=>false,'message'=>'Email already exists']); exit; }
            db()->insert("INSERT INTO admin_users (name,email,password,role,is_active) VALUES (?,?,?,?,?)",
                [$name,$email,password_hash($data['password'],PASSWORD_DEFAULT),$role,$isActive]);
            echo json_encode(['success'=>true,'message'=>'Admin user created']);
        }
    } elseif ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        if ($id === (int)$_SESSION['admin_id']) { echo json_encode(['success'=>false,'message'=>'Cannot delete yourself']); exit; }
        // Lockout guard: never delete the last active super admin.
        $target = db()->fetchOne("SELECT role FROM admin_users WHERE id=?", [$id]);
        if ($target && $target['role'] === 'super_admin') {
            $others = (int)(db()->fetchOne("SELECT COUNT(*) c FROM admin_users WHERE role='super_admin' AND is_active=1 AND id<>?", [$id])['c'] ?? 0);
            if ($others < 1) { echo json_encode(['success'=>false,'message'=>'Cannot delete the last active super admin']); exit; }
        }
        db()->execute("DELETE FROM admin_users WHERE id=?",[$id]);
        echo json_encode(['success'=>true,'message'=>'Admin deleted']);
    }
    exit;
}

$admins = db()->fetchAll("SELECT * FROM admin_users ORDER BY created_at DESC");
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Admin Users</h1>
        <p>Manage CRM access and staff permissions</p>
    </div>
    <button class="btn btn-gold" onclick="openAdminModal()"><i class="fa-solid fa-user-plus"></i> Add Admin</button>
</div>

<div class="card fade-in">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Admin</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Last Login</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($admins as $a): ?>
                <tr id="admin-row-<?= $a['id'] ?>">
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="admin-avatar"><?= strtoupper(substr($a['name'],0,1)) ?></div>
                            <span class="font-bold"><?= htmlspecialchars($a['name']) ?></span>
                            <?php if($a['id'] == $_SESSION['admin_id']): ?>
                            <span class="badge badge-primary" style="font-size:0.65rem;">You</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-muted"><?= htmlspecialchars($a['email']) ?></td>
                    <td>
                        <span class="badge badge-<?= $a['role']==='super_admin'?'warning':($a['role']==='admin'?'info':'secondary') ?>">
                            <?= ucfirst(str_replace('_',' ',$a['role'])) ?>
                        </span>
                    </td>
                    <td><?= $a['last_login'] ? formatDate($a['last_login'],'d M Y, h:i A') : '<span class="text-muted">Never</span>' ?></td>
                    <td><span class="badge badge-<?= $a['is_active']?'success':'secondary' ?>"><?= $a['is_active']?'Active':'Inactive' ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button class="btn btn-ghost btn-sm btn-icon" onclick='openAdminModal(<?= json_encode(['id'=>$a['id'],'name'=>$a['name'],'email'=>$a['email'],'role'=>$a['role'],'is_active'=>$a['is_active']]) ?>)'><i class="fa-solid fa-pen"></i></button>
                            <?php if($a['id'] != $_SESSION['admin_id']): ?>
                            <button class="btn btn-ghost btn-sm btn-icon" onclick="deleteAdmin(<?= $a['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="adminModal" style="display:none;" onclick="if(event.target===this)closeModal('adminModal')">
    <div class="modal-box modal-lg">
        <div class="modal-head">
            <h2 id="adminModalTitle">Add Admin User</h2>
            <button class="close-btn" onclick="closeModal('adminModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="adm_id">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" id="adm_name" placeholder="Staff Name">
                </div>
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-control" id="adm_email" placeholder="staff@dentinno.com">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password <span id="passNote" class="text-muted" style="font-size:0.7rem;">(leave blank to keep current)</span></label>
                    <input type="password" class="form-control" id="adm_password" placeholder="Min 8 characters">
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select class="form-control" id="adm_role">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-control" id="adm_status">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closeModal('adminModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveAdmin()"><i class="fa-solid fa-floppy-disk"></i> Save Admin</button>
        </div>
    </div>
</div>

<script>
function openAdminModal(a = null) {
    document.getElementById('adm_id').value       = a?.id || '';
    document.getElementById('adm_name').value     = a?.name || '';
    document.getElementById('adm_email').value    = a?.email || '';
    document.getElementById('adm_role').value     = a?.role || 'staff';
    document.getElementById('adm_status').value   = a?.is_active ?? 1;
    document.getElementById('adm_password').value = '';
    document.getElementById('passNote').style.display = a ? 'inline' : 'none';
    document.getElementById('adminModalTitle').textContent = a ? 'Edit Admin User' : 'Add Admin User';
    openModal('adminModal');
}
async function saveAdmin() {
    const name  = document.getElementById('adm_name').value.trim();
    const email = document.getElementById('adm_email').value.trim();
    if (!name || !email) { showToast('Name and email required','warning'); return; }
    const data = {
        action:'save', id:document.getElementById('adm_id').value,
        name, email, password:document.getElementById('adm_password').value,
        role:document.getElementById('adm_role').value,
        is_active:document.getElementById('adm_status').value,
    };
    const res = await fetch('admins.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});
    const r = await res.json();
    if(r.success){showToast(r.message,'success');closeModal('adminModal');setTimeout(()=>location.reload(),800);}
    else showToast(r.message,'danger');
}
function deleteAdmin(id) {
    showConfirm('Delete Admin','This admin will lose all CRM access.', async () => {
        const res = await fetch('admins.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
        const r = await res.json();
        if(r.success){showToast('Admin deleted','success');const row=document.getElementById(`admin-row-${id}`);if(row){row.style.opacity='0';setTimeout(()=>row.remove(),300);}}
        else showToast(r.message,'danger');
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
