<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Roles';
requireView('roles');   // super-admin only (page_registry.is_super_only)

// ---- AJAX ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    $d = json_decode(file_get_contents('php://input'), true);
    $action = $d['action'] ?? '';
    // save(new)=create, save(existing)/clone-name-edit=edit, delete=delete, clone=create
    $verb = ($action === 'delete') ? 'delete' : (($action === 'save' && !empty($d['id'])) ? 'edit' : 'create');
    requireAction('roles', $verb);

    try {
    if ($action === 'save') {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') { echo json_encode(['success'=>false,'message'=>'Role name is required']); exit; }
        $desc   = trim((string)($d['description'] ?? ''));
        $active = !empty($d['is_active']) ? 1 : 0;

        if (!empty($d['id'])) {
            $role = db()->fetchOne("SELECT * FROM roles WHERE id=?", [(int)$d['id']]);
            if (!$role) { echo json_encode(['success'=>false,'message'=>'Role not found']); exit; }
            if ($role['is_super']) { echo json_encode(['success'=>false,'message'=>'The super admin role cannot be edited.']); exit; }
            db()->execute("UPDATE roles SET name=?, description=?, is_active=? WHERE id=?", [$name, $desc, $active, (int)$d['id']]);
            rbacBumpVersion();
            echo json_encode(['success'=>true,'message'=>'Role updated']);
        } else {
            // unique slug from the name
            $base = preg_replace('/-+/', '-', trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-')) ?: 'role';
            $slug = $base; $i = 1;
            while (db()->fetchOne("SELECT id FROM roles WHERE slug=?", [$slug])) { $slug = $base . '-' . (++$i); }
            $newId = db()->insert("INSERT INTO roles (name, slug, description, is_super, is_system, is_active) VALUES (?,?,?,0,0,?)", [$name, $slug, $desc, $active]);
            // Optional: copy the matrix from an existing role.
            if (!empty($d['clone_from'])) {
                db()->execute(
                    "INSERT INTO role_permissions (role_id, page_id, can_view, can_create, can_edit, can_delete)
                     SELECT ?, page_id, can_view, can_create, can_edit, can_delete FROM role_permissions WHERE role_id=?",
                    [$newId, (int)$d['clone_from']]
                );
            }
            rbacBumpVersion();
            echo json_encode(['success'=>true,'message'=>'Role created','id'=>$newId]);
        }
    } elseif ($action === 'delete') {
        $id = (int)($d['id'] ?? 0);
        $role = db()->fetchOne("SELECT * FROM roles WHERE id=?", [$id]);
        if (!$role) { echo json_encode(['success'=>false,'message'=>'Role not found']); exit; }
        if ($role['is_super'] || $role['is_system']) { echo json_encode(['success'=>false,'message'=>'Built-in roles cannot be deleted.']); exit; }
        $users = (int)(db()->fetchOne("SELECT COUNT(*) c FROM admin_users WHERE role_id=?", [$id])['c'] ?? 0);
        if ($users > 0) { echo json_encode(['success'=>false,'message'=>"This role is assigned to $users user(s). Reassign them first."]); exit; }
        db()->execute("DELETE FROM roles WHERE id=?", [$id]);   // cascades role_permissions
        rbacBumpVersion();
        echo json_encode(['success'=>true,'message'=>'Role deleted']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Unknown action']);
    }
    } catch (Throwable $e) {
        error_log('Roles handler error: ' . $e->getMessage());
        echo json_encode(['success'=>false, 'message'=>(defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : 'Server error. Please try again.']);
    }
    exit;
}

// ---- Listing ----
$roles = db()->fetchAll(
    "SELECT r.*,
            (SELECT COUNT(*) FROM admin_users au WHERE au.role_id = r.id) AS user_count,
            (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id AND (rp.can_view|rp.can_create|rp.can_edit|rp.can_delete)=1) AS page_count
       FROM roles r
      ORDER BY r.is_super DESC, r.is_system DESC, r.name"
);
$canCreate = can('roles','create');
$canEdit   = can('roles','edit');
$canDelete = can('roles','delete');
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Roles</h1>
        <p>Define roles and what each can access. Assign roles to admins on the <a href="<?= APP_URL ?>/pages/admins.php" class="text-gold">Admin Users</a> page; set page access on the <a href="<?= APP_URL ?>/pages/permissions.php" class="text-gold">Permissions</a> page.</p>
    </div>
    <?php if ($canCreate): ?>
    <button class="btn btn-gold" onclick="openRoleModal()"><i class="fa-solid fa-plus"></i> Add Role</button>
    <?php endif; ?>
</div>

<div class="card fade-in">
    <div class="table-responsive">
        <table>
            <thead><tr><th>Role</th><th>Description</th><th>Users</th><th>Pages</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($roles as $r): ?>
                <tr id="role-row-<?= $r['id'] ?>">
                    <td>
                        <span class="font-bold"><?= htmlspecialchars($r['name']) ?></span>
                        <?php if ($r['is_super']): ?><span class="badge badge-warning" style="margin-left:6px;">Super</span>
                        <?php elseif ($r['is_system']): ?><span class="badge badge-secondary" style="margin-left:6px;">Built-in</span><?php endif; ?>
                        <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($r['slug']) ?></div>
                    </td>
                    <td class="text-muted" style="font-size:.84rem;max-width:320px;"><?= htmlspecialchars($r['description'] ?: '—') ?></td>
                    <td><?= (int)$r['user_count'] ?></td>
                    <td><?= $r['is_super'] ? '<span class="text-gold">All</span>' : (int)$r['page_count'] ?></td>
                    <td><span class="badge badge-<?= $r['is_active'] ? 'success' : 'secondary' ?>"><?= $r['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td style="text-align:right;white-space:nowrap;">
                        <?php if (!$r['is_super']): ?>
                            <?php if ($canEdit): ?>
                            <a href="<?= APP_URL ?>/pages/permissions.php?role=<?= $r['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Edit permissions"><i class="fa-solid fa-table-cells"></i></a>
                            <button class="btn btn-ghost btn-sm btn-icon" title="Edit role" onclick='openRoleModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES) ?>)'><i class="fa-solid fa-pen"></i></button>
                            <?php endif; ?>
                            <?php if ($canDelete && !$r['is_system']): ?>
                            <button class="btn btn-ghost btn-sm btn-icon" title="Delete role" onclick="deleteRole(<?= $r['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Role Modal -->
<div class="modal-overlay" id="roleModal" style="display:none;" onclick="if(event.target===this)closeModal('roleModal')">
    <div class="modal-box">
        <div class="modal-head">
            <h2 id="roleModalTitle">Add Role</h2>
            <button class="close-btn" onclick="closeModal('roleModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="role_id">
            <div class="form-group">
                <label class="form-label">Role Name *</label>
                <input type="text" class="form-control" id="role_name" placeholder="e.g. Catalog Manager">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="role_desc" rows="2" placeholder="What this role is for…"></textarea>
            </div>
            <div class="form-group" id="cloneWrap">
                <label class="form-label">Copy permissions from <small class="text-muted">(optional, new roles only)</small></label>
                <select class="form-control" id="role_clone">
                    <option value="">— Start with no access —</option>
                    <?php foreach ($roles as $r): if ($r['is_super']) continue; ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="checkbox-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" id="role_active" checked> Active
                </label>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closeModal('roleModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveRole()"><i class="fa-solid fa-floppy-disk"></i> Save Role</button>
        </div>
    </div>
</div>

<script>
function openRoleModal(r) {
    document.getElementById('role_id').value    = r ? r.id : '';
    document.getElementById('role_name').value  = r ? r.name : '';
    document.getElementById('role_desc').value  = r ? (r.description || '') : '';
    document.getElementById('role_active').checked = r ? (r.is_active == 1) : true;
    document.getElementById('roleModalTitle').textContent = r ? 'Edit Role' : 'Add Role';
    // Clone option only makes sense for a brand-new role.
    document.getElementById('cloneWrap').style.display = r ? 'none' : 'block';
    document.getElementById('role_clone').value = '';
    openModal('roleModal');
}
async function saveRole() {
    const payload = {
        action: 'save',
        id: document.getElementById('role_id').value || '',
        name: document.getElementById('role_name').value.trim(),
        description: document.getElementById('role_desc').value.trim(),
        is_active: document.getElementById('role_active').checked ? 1 : 0,
        clone_from: document.getElementById('role_clone').value || ''
    };
    if (!payload.name) { showToast('Role name is required', 'error'); return; }
    const res = await fetch('roles.php', {method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify(payload)});
    const r = await res.json().catch(() => ({success:false, message:'Request failed'}));
    showToast(r.message || (r.success ? 'Saved' : 'Failed'), r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 700);
}
function deleteRole(id) {
    showConfirm('Delete Role', 'This permanently removes the role and its permission matrix. Users must be reassigned first.', async () => {
        const res = await fetch('roles.php', {method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify({action:'delete', id})});
        const r = await res.json().catch(() => ({success:false, message:'Request failed'}));
        showToast(r.message || (r.success ? 'Deleted' : 'Failed'), r.success ? 'success' : 'error');
        if (r.success) { const row = document.getElementById('role-row-' + id); if (row) row.remove(); }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
