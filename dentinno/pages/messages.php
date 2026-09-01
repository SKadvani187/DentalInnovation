<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Messages';
requireView('messages');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    // Never let a PHP warning/exception leak HTML into the JSON response (breaks res.json()).
    try {
    $d = json_decode(file_get_contents('php://input'), true);
    $action = $d['action'] ?? '';
    requireAction('messages', rbacCrudVerb($action, $d));
    if ($action === 'read')   { db()->execute("UPDATE contact_messages SET is_read=1 WHERE id=?", [$d['id']]); echo json_encode(['success'=>true]); }
    elseif ($action === 'delete') {
        // Hard delete — a customer enquiry vanishes for good, so the audit keeps it.
        $id = (int)($d['id'] ?? 0); $b = auditRow('contact_messages', $id);
        db()->execute("DELETE FROM contact_messages WHERE id=?", [$id]);
        logActivity('deleted', 'message', $id, ($b['name'] ?? '') . ' · ' . ($b['department'] ?? ''), auditDiff($b, null));
        echo json_encode(['success'=>true]);
    }
    else echo json_encode(['success'=>false]);
    } catch (Throwable $e) {
        echo json_encode(['success'=>false,'message'=>'Server error: ' . $e->getMessage()]);
    }
    exit;
}

$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');   // '' all, 'unread', 'read', 'deleted'
$page   = max(1,(int)($_GET['page'] ?? 1));
$per_page = 20; $offset = ($page-1)*$per_page;

$where = ["1=1"]; $params = [];
if ($search) { $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR message LIKE ?)"; $params = array_merge($params, array_fill(0,4,"%$search%")); }
// Soft-delete: hide deleted unless the "Deleted" filter is chosen.
if ($status === 'deleted') { $where[] = "is_deleted=1"; }
else {
    $where[] = "is_deleted=0";
    if ($status === 'unread')   $where[] = "is_read=0";
    elseif ($status === 'read') $where[] = "is_read=1";
}
$whereStr = implode(' AND ', $where);

// --- CSV export — full filtered set, before any HTML output ---
if (isset($_GET['export'])) {
    $rows = db()->fetchAll("SELECT name,phone,email,department,message,is_read,created_at FROM contact_messages WHERE $whereStr ORDER BY created_at DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="messages-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name','Phone','Email','Department','Message','Read','Received']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['name'],$r['phone'],$r['email'],$r['department'],$r['message'],$r['is_read']?'Yes':'No',$r['created_at']]);
    }
    fclose($out);
    exit;
}

$total = (int)(db()->fetchOne("SELECT COUNT(*) c FROM contact_messages WHERE $whereStr", $params)['c'] ?? 0);
$pages = (int)ceil($total/$per_page);
$messages = db()->fetchAll("SELECT * FROM contact_messages WHERE $whereStr ORDER BY created_at DESC LIMIT $per_page OFFSET $offset", $params);
$unread = db()->fetchOne("SELECT COUNT(*) c FROM contact_messages WHERE is_read=0 AND is_deleted=0")['c'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Messages</h1>
        <p>Contact form inquiries from the storefront — <?= (int)$unread ?> unread</p>
    </div>
</div>

<div class="filter-bar fade-in" style="flex-wrap:wrap;gap:8px;">
    <div class="search-wrapper" style="flex:1;min-width:180px;max-width:300px;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search name / email / message..." value="<?= htmlspecialchars($search) ?>" onkeydown="if(event.key==='Enter')applyFilters()">
    </div>
    <select class="form-control" id="statusFilter" style="max-width:150px;">
        <option value="">All</option>
        <option value="unread"  <?= $status==='unread'?'selected':'' ?>>Unread</option>
        <option value="read"    <?= $status==='read'?'selected':'' ?>>Read</option>
        <option value="deleted" <?= $status==='deleted'?'selected':'' ?>>🗑 Deleted</option>
    </select>
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="messages.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
    <button class="btn btn-ghost btn-sm" onclick="exportCsv()"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
</div>

<div class="card fade-in">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>#</th><th>Name</th><th>Contact</th><th>Department</th><th>Message</th><th>Received</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($messages as $i => $m): ?>
            <tr id="msg-<?= $m['id'] ?>" style="<?= $m['is_read'] ? '' : 'background:rgba(201,168,76,0.06);' ?>">
                <td><?= $i+1 ?></td>
                <td>
                    <div class="font-bold"><?= htmlspecialchars($m['name']) ?> <?= $m['is_read'] ? '' : '<span class="badge badge-warning" style="margin-left:6px;">New</span>' ?></div>
                </td>
                <td style="font-size:.8rem;">
                    <div><?= htmlspecialchars($m['phone'] ?: '—') ?></div>
                    <div class="text-muted"><?= htmlspecialchars($m['email'] ?: '—') ?></div>
                </td>
                <td><span class="badge badge-secondary"><?= htmlspecialchars(ucfirst($m['department'] ?: 'general')) ?></span></td>
                <td style="max-width:340px;font-size:.83rem;"><?= nl2br(htmlspecialchars($m['message'])) ?></td>
                <td style="font-size:.78rem;" class="text-muted"><?= timeAgo($m['created_at']) ?></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <?php if (!empty($m['is_deleted'])): ?>
                        <button class="btn btn-ghost btn-sm btn-icon" onclick="restoreMsg(<?= $m['id'] ?>)" title="Restore"><i class="fa-solid fa-trash-arrow-up" style="color:var(--success);"></i></button>
                        <?php else: ?>
                        <?php if (!$m['is_read']): ?><button class="btn btn-ghost btn-sm btn-icon" onclick="markRead(<?= $m['id'] ?>)" title="Mark read"><i class="fa-solid fa-check text-gold"></i></button><?php endif; ?>
                        <?php if (can('messages','delete')): ?><button class="btn btn-ghost btn-sm btn-icon" onclick="delMsg(<?= $m['id'] ?>)" title="Delete"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($messages)): ?><tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-envelope-open"></i><p>No messages yet</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($pages > 1): ?>
    <div style="padding:16px 20px;border-top:1px solid var(--border-color);">
      <div class="pagination">
        <?php
        // Compact pagination: first, last, and a window around the current page (… for gaps).
        $range = 2; $shown = [];
        for ($i = 1; $i <= $pages; $i++) {
            if ($i == 1 || $i == $pages || ($i >= $page - $range && $i <= $page + $range)) $shown[] = $i;
        }
        if ($page > 1): ?><div class="page-item" onclick="goPage(<?= $page-1 ?>)">‹</div><?php endif;
        $prev = 0;
        foreach ($shown as $i):
            if ($prev && $i - $prev > 1): ?><div class="page-item" style="pointer-events:none;opacity:.5;">…</div><?php endif; ?>
            <div class="page-item <?= $i==$page?'active':'' ?>" onclick="goPage(<?= $i ?>)"><?= $i ?></div>
            <?php $prev = $i;
        endforeach;
        if ($page < $pages): ?><div class="page-item" onclick="goPage(<?= $page+1 ?>)">›</div><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
</div>

<script>
function buildMsgQuery(extra){
  const p=new URLSearchParams();
  const s=document.getElementById('searchInput').value; if(s)p.set('search',s);
  const st=document.getElementById('statusFilter').value; if(st)p.set('status',st);
  if(extra)Object.entries(extra).forEach(([k,v])=>p.set(k,v));
  return p.toString();
}
function applyFilters(){ window.location.href='messages.php?'+buildMsgQuery(); }
function exportCsv(){ window.location.href='messages.php?'+buildMsgQuery({export:'csv'}); }
function goPage(p){const q=new URLSearchParams(window.location.search);q.set('page',p);window.location.href='messages.php?'+q.toString();}
async function markRead(id){
  const res = await fetch('messages.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'read',id})});
  const r = await res.json().catch(()=>({success:false,message:'Request failed'}));
  if (r.success) location.reload();
  else showToast(r.message || 'Failed', 'error');
}
function delMsg(id){
  showConfirm('Delete Message','This hides the inquiry. You can restore it from the "Deleted" filter. Continue?', async () => {
    await fetch('messages.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
    const el=document.getElementById('msg-'+id); if(el){el.style.opacity='0';setTimeout(()=>el.remove(),300);}
  });
}
async function restoreMsg(id){
  await fetch('messages.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'restore',id})});
  const el=document.getElementById('msg-'+id); if(el){el.style.opacity='0';setTimeout(()=>el.remove(),300);}
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
