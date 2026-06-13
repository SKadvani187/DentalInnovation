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
    // Never let a PHP warning/exception leak HTML into the JSON response (breaks res.json()).
    try {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    $selfId = (int)($_SESSION['admin_id'] ?? 0);

    // Allowlist roles — never trust the client-supplied role string verbatim (mass-assignment).
    $ALLOWED_ROLES = ['super_admin','admin','staff'];
    $role = in_array($data['role'] ?? '', $ALLOWED_ROLES, true) ? $data['role'] : 'staff';

    // Append-only audit of admin-account changes. Wrapped so a logging failure never blocks the action.
    $audit = function(string $act, ?int $targetId, ?string $targetEmail, string $details) {
        try {
            db()->insert("INSERT INTO admin_audit_log (actor_id,actor_name,action,target_id,target_email,details) VALUES (?,?,?,?,?,?)",
                [(int)($_SESSION['admin_id'] ?? 0) ?: null, $_SESSION['admin_name'] ?? null, $act, $targetId, $targetEmail, $details]);
        } catch (Throwable $e) { /* audit must never break the operation */ }
    };

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
            $targetId = (int)$data['id'];
            $target = db()->fetchOne("SELECT name,email,role,is_active FROM admin_users WHERE id=?", [$targetId]);
            if (!$target) { echo json_encode(['success'=>false,'message'=>'Admin not found']); exit; }
            // Self-lockout prevention: you cannot change your OWN role or deactivate yourself
            // (another super admin must do it) — stops an accidental instant loss of access.
            if ($targetId === $selfId) {
                if ($role !== $target['role']) { echo json_encode(['success'=>false,'message'=>'You cannot change your own role. Ask another super admin.']); exit; }
                if ($isActive === 0)           { echo json_encode(['success'=>false,'message'=>'You cannot deactivate your own account.']); exit; }
            }
            // Lockout guard: never demote/deactivate the LAST active super admin.
            if ($target['role'] === 'super_admin' && ($role !== 'super_admin' || $isActive === 0)) {
                $others = (int)(db()->fetchOne("SELECT COUNT(*) c FROM admin_users WHERE role='super_admin' AND is_active=1 AND id<>?", [$targetId])['c'] ?? 0);
                if ($others < 1) { echo json_encode(['success'=>false,'message'=>'Cannot demote or deactivate the last active super admin']); exit; }
            }
            $dupe = db()->fetchOne("SELECT id FROM admin_users WHERE email=? AND id<>?", [$email, $targetId]);
            if ($dupe) { echo json_encode(['success'=>false,'message'=>'Email already in use']); exit; }
            $extra = !empty($data['password']) ? ", password=?" : "";
            $params = [$name,$email,$role,$isActive];
            if (!empty($data['password'])) $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            $params[] = $targetId;
            db()->execute("UPDATE admin_users SET name=?,email=?,role=?,is_active=?$extra WHERE id=?", $params);
            // Audit: summarise what actually changed.
            $changes = [];
            if ($target['name']  !== $name)  $changes[] = 'name';
            if ($target['email'] !== $email) $changes[] = 'email';
            if ($target['role']  !== $role)  $changes[] = "role {$target['role']}→{$role}";
            if ((int)$target['is_active'] !== $isActive) $changes[] = $isActive ? 'activated' : 'deactivated';
            if (!empty($data['password'])) $changes[] = 'password reset';
            $audit('updated', $targetId, $email, $changes ? implode(', ', $changes) : 'no changes');
            echo json_encode(['success'=>true,'message'=>'Admin updated']);
        } else {
            if (empty($data['password'])) { echo json_encode(['success'=>false,'message'=>'Password is required']); exit; }
            $exists = db()->fetchOne("SELECT id FROM admin_users WHERE email=?",[$email]);
            if ($exists) { echo json_encode(['success'=>false,'message'=>'Email already exists']); exit; }
            $newId = (int) db()->insert("INSERT INTO admin_users (name,email,password,role,is_active) VALUES (?,?,?,?,?)",
                [$name,$email,password_hash($data['password'],PASSWORD_DEFAULT),$role,$isActive]);
            $audit('created', $newId, $email, "role: $role" . ($isActive ? '' : ' (inactive)'));
            echo json_encode(['success'=>true,'message'=>'Admin user created']);
        }
    } elseif ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        if ($id === $selfId) { echo json_encode(['success'=>false,'message'=>'Cannot delete yourself']); exit; }
        // Lockout guard: never delete the last active super admin.
        $target = db()->fetchOne("SELECT role,email FROM admin_users WHERE id=?", [$id]);
        if (!$target) { echo json_encode(['success'=>false,'message'=>'Admin not found']); exit; }
        if ($target['role'] === 'super_admin') {
            $others = (int)(db()->fetchOne("SELECT COUNT(*) c FROM admin_users WHERE role='super_admin' AND is_active=1 AND id<>?", [$id])['c'] ?? 0);
            if ($others < 1) { echo json_encode(['success'=>false,'message'=>'Cannot delete the last active super admin']); exit; }
        }
        db()->execute("DELETE FROM admin_users WHERE id=?",[$id]);
        $audit('deleted', $id, $target['email'] ?? null, 'role: ' . ($target['role'] ?? '?'));
        echo json_encode(['success'=>true,'message'=>'Admin deleted']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Unknown action']);
    }
    } catch (Throwable $e) {
        echo json_encode(['success'=>false,'message'=>'Server error: ' . $e->getMessage()]);
    }
    exit;
}

$admins = db()->fetchAll("SELECT * FROM admin_users ORDER BY created_at DESC");
// Recent admin-account activity (audit trail). Table may not exist on an un-migrated DB — guard it.
try { $auditLog = db()->fetchAll("SELECT * FROM admin_audit_log ORDER BY created_at DESC LIMIT 20"); }
catch (Throwable $e) { $auditLog = []; }
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
                    <td><?= $a['last_login'] ? formatDate($a['last_login'],'d M Y, h:i A').' <span class="text-muted" style="font-size:.7rem;">('.timeAgo($a['last_login']).')</span>' : '<span class="text-muted">Never</span>' ?></td>
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

<!-- Audit trail: recent admin-account changes (who did what, when) -->
<div class="card fade-in" style="margin-top:20px;">
    <div style="padding:14px 18px;border-bottom:1px solid var(--border-color);display:flex;align-items:center;gap:10px;">
        <i class="fa-solid fa-clock-rotate-left text-gold"></i>
        <span class="font-bold" style="font-size:.92rem;">Recent admin activity</span>
        <span class="text-muted" style="font-size:.75rem;">last 20 changes</span>
    </div>
    <div class="table-responsive">
        <table>
            <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>Details</th></tr></thead>
            <tbody>
                <?php foreach($auditLog as $log): ?>
                <tr>
                    <td class="text-muted" style="font-size:.78rem;white-space:nowrap;"><?= formatDate($log['created_at'],'d M Y, h:i A') ?></td>
                    <td style="font-size:.82rem;"><?= htmlspecialchars($log['actor_name'] ?: ('#'.(int)$log['actor_id'])) ?></td>
                    <td><span class="badge badge-<?= ['created'=>'success','updated'=>'info','deleted'=>'danger'][$log['action']] ?? 'secondary' ?>"><?= htmlspecialchars(ucfirst($log['action'])) ?></span></td>
                    <td style="font-size:.82rem;" class="text-muted"><?= htmlspecialchars($log['target_email'] ?: ('#'.(int)$log['target_id'])) ?></td>
                    <td style="font-size:.8rem;"><?= htmlspecialchars($log['details'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($auditLog)): ?><tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i><p>No admin changes recorded yet</p></div></td></tr><?php endif; ?>
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
                    <div style="position:relative;">
                        <input type="password" class="form-control" id="adm_password" placeholder="Min 8 characters" style="padding-right:38px;">
                        <button type="button" onclick="togglePw()" title="Show / hide password" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;"><i class="fa-solid fa-eye" id="pwEye"></i></button>
                    </div>
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
            <div id="selfEditNote" style="display:none;background:rgba(201,168,76,.1);border:1px solid rgba(201,168,76,.3);color:var(--gold-primary);padding:9px 12px;border-radius:8px;font-size:.78rem;">
                <i class="fa-solid fa-circle-info"></i> This is your own account — you can't change your own role or status (another super admin must).
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closeModal('adminModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveAdmin()"><i class="fa-solid fa-floppy-disk"></i> Save Admin</button>
        </div>
    </div>
</div>

<script>
const CURRENT_ADMIN_ID = <?= (int)$_SESSION['admin_id'] ?>;
function togglePw(){
    const i=document.getElementById('adm_password'), e=document.getElementById('pwEye');
    if(i.type==='password'){ i.type='text'; e.className='fa-solid fa-eye-slash'; }
    else { i.type='password'; e.className='fa-solid fa-eye'; }
}
function openAdminModal(a = null) {
    document.getElementById('adm_id').value       = a?.id || '';
    document.getElementById('adm_name').value     = a?.name || '';
    document.getElementById('adm_email').value    = a?.email || '';
    document.getElementById('adm_role').value     = a?.role || 'staff';
    document.getElementById('adm_status').value   = a?.is_active ?? 1;
    document.getElementById('adm_password').value = '';
    document.getElementById('passNote').style.display = a ? 'inline' : 'none';
    // Self-edit: lock your own role/status (matches the server-side self-lockout guard).
    const isSelf = a && String(a.id) === String(CURRENT_ADMIN_ID);
    document.getElementById('adm_role').disabled   = !!isSelf;
    document.getElementById('adm_status').disabled = !!isSelf;
    document.getElementById('selfEditNote').style.display = isSelf ? 'block' : 'none';
    // reset password visibility each open
    document.getElementById('adm_password').type = 'password';
    document.getElementById('pwEye').className = 'fa-solid fa-eye';
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
    showConfirm('Delete Admin','This permanently deletes the admin account and revokes all CRM access — it cannot be undone. (Tip: deactivate instead to keep the record.)', async () => {
        const res = await fetch('admins.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
        const r = await res.json();
        if(r.success){showToast('Admin deleted','success');const row=document.getElementById(`admin-row-${id}`);if(row){row.style.opacity='0';setTimeout(()=>row.remove(),300);}}
        else showToast(r.message,'danger');
    });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
