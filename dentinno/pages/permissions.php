<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Permissions';
requireView('permissions');   // super-admin only

// ---- AJAX: save a role's matrix ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    requireAction('permissions', 'edit');
    $d = json_decode(file_get_contents('php://input'), true);
    if (($d['action'] ?? '') !== 'save') { echo json_encode(['success'=>false,'message'=>'Unknown action']); exit; }

    $roleId = (int)($d['role_id'] ?? 0);
    $role = db()->fetchOne("SELECT * FROM roles WHERE id=? AND is_super=0", [$roleId]);
    if (!$role) { echo json_encode(['success'=>false,'message'=>'Invalid role (the super admin role is not editable).']); exit; }

    $in    = is_array($d['perms'] ?? null) ? $d['perms'] : [];
    $pages = db()->fetchAll("SELECT * FROM page_registry WHERE is_super_only=0");   // configurable pages only

    // One grant string per page ("orders:view/edit"), so the audit diff reads as a permission
    // matrix rather than a wall of 0/1 columns. Captured before AND after the write.
    $permSnapshot = function (int $rid): array {
        $rows = db()->fetchAll(
            "SELECT p.page_key, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete
               FROM page_registry p LEFT JOIN role_permissions rp ON rp.page_id = p.id AND rp.role_id = ?
              WHERE p.is_super_only = 0 ORDER BY p.page_key", [$rid]);
        $out = [];
        foreach ($rows as $r) {
            $v = array_keys(array_filter(['view'=>$r['can_view'],'create'=>$r['can_create'],'edit'=>$r['can_edit'],'delete'=>$r['can_delete']]));
            $out[$r['page_key']] = $v ? implode('/', $v) : 'no access';
        }
        return $out;
    };
    $permsBefore = $permSnapshot($roleId);

    $pdo = db()->getConnection();
    $pdo->beginTransaction();
    try {
        foreach ($pages as $p) {
            $row = $in[$p['page_key']] ?? [];
            // Clamp each verb to what the page actually supports.
            $c = (!empty($row['c']) && $p['supports_create']) ? 1 : 0;
            $e = (!empty($row['e']) && $p['supports_edit'])   ? 1 : 0;
            $del = (!empty($row['d']) && $p['supports_delete']) ? 1 : 0;
            // View is auto-required whenever any write verb is granted.
            $v = (!empty($row['v']) || $c || $e || $del) ? 1 : 0;
            db()->execute(
                "INSERT INTO role_permissions (role_id, page_id, can_view, can_create, can_edit, can_delete)
                 VALUES (?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_create=VALUES(can_create), can_edit=VALUES(can_edit), can_delete=VALUES(can_delete)",
                [$roleId, $p['id'], $v, $c, $e, $del]
            );
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('Permissions save error: ' . $e->getMessage());
        echo json_encode(['success'=>false,'message'=>(defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : 'Server error. Please try again.']);
        exit;
    }
    logActivity('updated', 'permissions', $roleId, 'Role: ' . $role['name'],
                auditDiff($permsBefore, $permSnapshot($roleId)));
    rbacBumpVersion();   // active sessions of this role pick up the change on their next request
    echo json_encode(['success'=>true,'message'=>'Permissions saved for ' . $role['name']]);
    exit;
}

// ---- GET ----
$roles = db()->fetchAll("SELECT id, name FROM roles WHERE is_super=0 AND is_active=1 ORDER BY is_system DESC, name");
$pages = db()->fetchAll("SELECT * FROM page_registry WHERE is_super_only=0 AND is_active=1 ORDER BY group_order, sort_order");

$selRoleId = (int)($_GET['role'] ?? 0);
if (!$selRoleId && $roles) $selRoleId = (int)$roles[0]['id'];
$selRole = $selRoleId ? db()->fetchOne("SELECT * FROM roles WHERE id=? AND is_super=0", [$selRoleId]) : null;

// Current grants for the selected role, keyed by page_key.
$cur = [];
if ($selRole) {
    foreach (db()->fetchAll(
        "SELECT pr.page_key, rp.can_view v, rp.can_create c, rp.can_edit e, rp.can_delete d
           FROM role_permissions rp JOIN page_registry pr ON pr.id = rp.page_id
          WHERE rp.role_id=?", [$selRoleId]) as $row) {
        $cur[$row['page_key']] = $row;
    }
}

// Group pages for display.
$grouped = [];
foreach ($pages as $p) $grouped[$p['nav_group'] ?: 'OTHER'][] = $p;

include __DIR__ . '/../includes/header.php';
?>
<style>
.perm-table{width:100%;border-collapse:collapse;}
.perm-table th,.perm-table td{padding:9px 12px;border-bottom:1px solid var(--border-color);text-align:left;font-size:.86rem;}
.perm-table th.act,.perm-table td.act{text-align:center;width:74px;}
.perm-group-row td{background:var(--bg-elevated);font-weight:700;font-size:.74rem;letter-spacing:.5px;color:var(--text-secondary);text-transform:uppercase;}
.perm-table input[type=checkbox]{width:17px;height:17px;accent-color:var(--gold-primary);cursor:pointer;}
.perm-table input[type=checkbox]:disabled{opacity:.25;cursor:not-allowed;}
</style>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Permissions</h1>
        <p>Tick what each role can do on each page. Ticking Create/Edit/Delete auto-grants View. Manage roles on the <a href="<?= APP_URL ?>/pages/roles.php" class="text-gold">Roles</a> page.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <select class="form-control" id="roleSelect" style="min-width:200px;" onchange="location.href='permissions.php?role='+this.value">
            <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $r['id']==$selRoleId?'selected':'' ?>><?= htmlspecialchars($r['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($selRole): ?>
        <button class="btn btn-gold" onclick="savePermissions()"><i class="fa-solid fa-floppy-disk"></i> Save</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$selRole): ?>
<div class="card fade-in"><div class="card-body"><p class="text-muted">No editable roles yet. Create one on the <a href="<?= APP_URL ?>/pages/roles.php" class="text-gold">Roles</a> page (the super admin role always has full access and isn't listed here).</p></div></div>
<?php else: ?>
<div class="card fade-in">
    <div class="table-responsive">
        <table class="perm-table" id="permTable" data-role="<?= $selRoleId ?>">
            <thead>
                <tr>
                    <th>Page</th>
                    <th class="act">View</th><th class="act">Create</th><th class="act">Edit</th><th class="act">Delete</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grouped as $group => $glist): ?>
                <tr class="perm-group-row"><td colspan="5"><?= htmlspecialchars($group) ?></td></tr>
                <?php foreach ($glist as $p):
                    $k = $p['page_key'];
                    $cv = !empty($cur[$k]['v']); $cc = !empty($cur[$k]['c']); $ce = !empty($cur[$k]['e']); $cd = !empty($cur[$k]['d']);
                    $anyWrite = $cc || $ce || $cd;
                ?>
                <tr data-page="<?= htmlspecialchars($k) ?>">
                    <td>
                        <span class="font-bold"><?= htmlspecialchars($p['label']) ?></span>
                        <?php if ($p['description']): ?><div class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars($p['description']) ?></div><?php endif; ?>
                    </td>
                    <td class="act"><input type="checkbox" data-act="v" <?= $cv?'checked':'' ?> <?= $anyWrite?'disabled':'' ?>></td>
                    <td class="act"><input type="checkbox" data-act="c" <?= $cc?'checked':'' ?> <?= $p['supports_create']?'':'disabled' ?>></td>
                    <td class="act"><input type="checkbox" data-act="e" <?= $ce?'checked':'' ?> <?= $p['supports_edit']?'':'disabled' ?>></td>
                    <td class="act"><input type="checkbox" data-act="d" <?= $cd?'checked':'' ?> <?= $p['supports_delete']?'':'disabled' ?>></td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
// View is a prerequisite for any write: keep the View box checked+locked whenever Create/Edit/Delete is on.
function syncRow(row) {
    const v = row.querySelector('input[data-act="v"]');
    const writes = ['c','e','d'].map(a => row.querySelector('input[data-act="'+a+'"]'));
    const anyWrite = writes.some(cb => cb && cb.checked);
    if (anyWrite) { v.checked = true; v.disabled = true; }
    else { v.disabled = false; }
}
document.querySelectorAll('#permTable tr[data-page]').forEach(row => {
    row.querySelectorAll('input[data-act]').forEach(cb => cb.addEventListener('change', () => syncRow(row)));
    syncRow(row);
});
async function savePermissions() {
    const table = document.getElementById('permTable');
    const roleId = table.getAttribute('data-role');
    const perms = {};
    table.querySelectorAll('tr[data-page]').forEach(row => {
        const key = row.getAttribute('data-page');
        const get = a => { const cb = row.querySelector('input[data-act="'+a+'"]'); return cb && cb.checked ? 1 : 0; };
        perms[key] = { v: get('v'), c: get('c'), e: get('e'), d: get('d') };
    });
    const res = await fetch('permissions.php', {method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify({action:'save', role_id: roleId, perms})});
    const r = await res.json().catch(() => ({success:false, message:'Request failed'}));
    showToast(r.message || (r.success ? 'Saved' : 'Failed'), r.success ? 'success' : 'error');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
