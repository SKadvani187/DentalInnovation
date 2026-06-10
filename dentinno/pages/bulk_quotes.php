<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Bulk Quotes';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $d = json_decode(file_get_contents('php://input'), true);
    $action = $d['action'] ?? '';
    if ($action === 'read')   { db()->execute("UPDATE bulk_quotes SET is_read=1 WHERE id=?", [$d['id']]); echo json_encode(['success'=>true]); }
    elseif ($action === 'status') {
        $allowed = ['new','contacted','quoted','closed'];
        $s = in_array($d['status'] ?? '', $allowed, true) ? $d['status'] : 'new';
        db()->execute("UPDATE bulk_quotes SET status=?, is_read=1 WHERE id=?", [$s, $d['id']]);
        echo json_encode(['success'=>true]);
    }
    elseif ($action === 'delete') { db()->execute("DELETE FROM bulk_quotes WHERE id=?", [$d['id']]); echo json_encode(['success'=>true]); }
    else echo json_encode(['success'=>false]);
    exit;
}

$quotes = db()->fetchAll("SELECT * FROM bulk_quotes ORDER BY created_at DESC");
$unread = db()->fetchOne("SELECT COUNT(*) c FROM bulk_quotes WHERE is_read=0")['c'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Bulk Quotes</h1>
        <p>Bulk / wholesale quote requests from product pages — <?= (int)$unread ?> new</p>
    </div>
</div>

<div class="card fade-in">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>#</th><th>Customer</th><th>Contact</th><th>Product</th><th>Qty</th><th>Expected ₹/pc</th><th>Status</th><th>Received</th><th>Actions</th></tr></thead>
            <tbody>
            <?php
            $statusColors = ['new'=>'badge-warning','contacted'=>'badge-secondary','quoted'=>'badge-info','closed'=>'badge-success'];
            foreach ($quotes as $i => $q): ?>
            <tr id="bq-<?= $q['id'] ?>" style="<?= $q['is_read'] ? '' : 'background:rgba(201,168,76,0.06);' ?>">
                <td><?= $i+1 ?></td>
                <td>
                    <div class="font-bold"><?= htmlspecialchars($q['name']) ?> <?= $q['is_read'] ? '' : '<span class="badge badge-warning" style="margin-left:6px;">New</span>' ?></div>
                    <?php if ($q['address'] || $q['pincode']): ?><div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars(trim(($q['address'] ?: '').' '.($q['pincode'] ? '— '.$q['pincode'] : ''))) ?></div><?php endif; ?>
                </td>
                <td style="font-size:.8rem;">
                    <div><?= htmlspecialchars($q['phone'] ?: '—') ?></div>
                    <div class="text-muted"><?= htmlspecialchars($q['email'] ?: '—') ?></div>
                </td>
                <td style="font-size:.83rem;"><?= htmlspecialchars($q['product_name'] ?: ($q['product_slug'] ?: '—')) ?></td>
                <td class="font-bold"><?= (int)$q['quantity'] ?></td>
                <td><?= $q['expected_price'] !== null ? '₹'.number_format((float)$q['expected_price'], 0) : '—' ?></td>
                <td>
                    <select class="form-control" style="padding:4px 8px;font-size:.78rem;min-width:110px;" onchange="setStatus(<?= $q['id'] ?>, this.value)">
                        <?php foreach (['new','contacted','quoted','closed'] as $s): ?>
                        <option value="<?= $s ?>" <?= $q['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td style="font-size:.78rem;" class="text-muted"><?= timeAgo($q['created_at']) ?></td>
                <td>
                    <button class="btn btn-ghost btn-sm btn-icon" onclick="delQuote(<?= $q['id'] ?>)" title="Delete"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($quotes)): ?><tr><td colspan="9"><div class="empty-state"><i class="fa-solid fa-file-invoice-dollar"></i><p>No bulk quote requests yet</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function setStatus(id, status){
  await fetch('bulk_quotes.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'status',id,status})});
  showToast && showToast('Status updated','success');
  const row=document.getElementById('bq-'+id); if(row) row.style.background='';
}
function delQuote(id){
  showConfirm('Delete Quote Request','This bulk quote request will be removed.', async () => {
    await fetch('bulk_quotes.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
    const el=document.getElementById('bq-'+id); if(el){el.style.opacity='0';setTimeout(()=>el.remove(),300);}
  });
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
