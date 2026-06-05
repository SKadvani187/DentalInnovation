<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Messages';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $d = json_decode(file_get_contents('php://input'), true);
    $action = $d['action'] ?? '';
    if ($action === 'read')   { db()->execute("UPDATE contact_messages SET is_read=1 WHERE id=?", [$d['id']]); echo json_encode(['success'=>true]); }
    elseif ($action === 'delete') { db()->execute("DELETE FROM contact_messages WHERE id=?", [$d['id']]); echo json_encode(['success'=>true]); }
    else echo json_encode(['success'=>false]);
    exit;
}

$messages = db()->fetchAll("SELECT * FROM contact_messages ORDER BY created_at DESC");
$unread = db()->fetchOne("SELECT COUNT(*) c FROM contact_messages WHERE is_read=0")['c'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Messages</h1>
        <p>Contact form inquiries from the storefront — <?= (int)$unread ?> unread</p>
    </div>
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
                        <?php if (!$m['is_read']): ?><button class="btn btn-ghost btn-sm btn-icon" onclick="markRead(<?= $m['id'] ?>)" title="Mark read"><i class="fa-solid fa-check text-gold"></i></button><?php endif; ?>
                        <button class="btn btn-ghost btn-sm btn-icon" onclick="delMsg(<?= $m['id'] ?>)" title="Delete"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($messages)): ?><tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-envelope-open"></i><p>No messages yet</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function markRead(id){
  await fetch('messages.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'read',id})});
  location.reload();
}
function delMsg(id){
  showConfirm('Delete Message','This inquiry will be removed.', async () => {
    await fetch('messages.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
    const el=document.getElementById('msg-'+id); if(el){el.style.opacity='0';setTimeout(()=>el.remove(),300);}
  });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
