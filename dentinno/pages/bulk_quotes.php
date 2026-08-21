<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Bulk Quotes';
requireView('bulk_quotes');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    // Never let a PHP warning/exception leak HTML into the JSON response (breaks res.json()).
    try {
    $d = json_decode(file_get_contents('php://input'), true);
    $action = $d['action'] ?? '';
    requireAction('bulk_quotes', rbacCrudVerb($action, $d));
    if ($action === 'read')   { db()->execute("UPDATE bulk_quotes SET is_read=1 WHERE id=?", [$d['id']]); echo json_encode(['success'=>true]); }
    elseif ($action === 'status') {
        $allowed = ['new','contacted','quoted','closed'];
        $s = in_array($d['status'] ?? '', $allowed, true) ? $d['status'] : 'new';
        db()->execute("UPDATE bulk_quotes SET status=?, is_read=1 WHERE id=?", [$s, (int)($d['id'] ?? 0)]);
        echo json_encode(['success'=>true]);
    }
    elseif ($action === 'delete')  { db()->execute("UPDATE bulk_quotes SET is_deleted=1 WHERE id=?", [(int)($d['id'] ?? 0)]); echo json_encode(['success'=>true]); }
    elseif ($action === 'restore') { db()->execute("UPDATE bulk_quotes SET is_deleted=0 WHERE id=?", [(int)($d['id'] ?? 0)]); echo json_encode(['success'=>true]); }
    else echo json_encode(['success'=>false]);
    } catch (Throwable $e) {
        echo json_encode(['success'=>false,'message'=>'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// --- Filters ---
$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');   // '' all, new/contacted/quoted/closed, 'unread', 'deleted'
$page   = max(1,(int)($_GET['page'] ?? 1));
$per_page = 20; $offset = ($page-1)*$per_page;

$where = ["1=1"]; $params = [];
if ($search) { $where[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ? OR product_name LIKE ? OR product_slug LIKE ?)"; $params = array_merge($params, array_fill(0,5,"%$search%")); }
// Soft-delete: hide deleted unless the "Deleted" filter is chosen.
if ($status === 'deleted') { $where[] = "is_deleted=1"; }
else {
    $where[] = "is_deleted=0";
    if ($status === 'unread') { $where[] = "is_read=0"; }
    elseif (in_array($status, ['new','contacted','quoted','closed'], true)) { $where[] = "status=?"; $params[] = $status; }
}
$whereStr = implode(' AND ', $where);

// --- CSV export (sales follow-up) — full filtered set, before any HTML output ---
if (isset($_GET['export'])) {
    $rows = db()->fetchAll("SELECT name,phone,email,pincode,address,product_name,product_slug,quantity,expected_price,status,created_at FROM bulk_quotes WHERE $whereStr ORDER BY created_at DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bulk-quotes-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name','Phone','Email','Pincode','Address','Product','Slug','Quantity','Expected/pc','Status','Received']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['name'],$r['phone'],$r['email'],$r['pincode'],$r['address'],$r['product_name'],$r['product_slug'],$r['quantity'],$r['expected_price'],$r['status'],$r['created_at']]);
    }
    fclose($out);
    exit;
}

$total  = (int)(db()->fetchOne("SELECT COUNT(*) c FROM bulk_quotes WHERE $whereStr", $params)['c'] ?? 0);
$pages  = (int)ceil($total/$per_page);
$quotes = db()->fetchAll("SELECT * FROM bulk_quotes WHERE $whereStr ORDER BY created_at DESC LIMIT $per_page OFFSET $offset", $params);
$unread = db()->fetchOne("SELECT COUNT(*) c FROM bulk_quotes WHERE is_read=0 AND is_deleted=0")['c'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Bulk Quotes</h1>
        <p>Bulk / wholesale quote requests from product pages — <?= (int)$unread ?> new</p>
    </div>
</div>

<div class="filter-bar fade-in" style="flex-wrap:wrap;gap:8px;">
    <div class="search-wrapper" style="flex:1;min-width:180px;max-width:300px;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search name / phone / product..." value="<?= htmlspecialchars($search) ?>" onkeydown="if(event.key==='Enter')applyFilters()">
    </div>
    <select class="form-control" id="statusFilter" style="max-width:150px;">
        <option value="">All</option>
        <option value="unread" <?= $status==='unread'?'selected':'' ?>>Unread (new)</option>
        <?php foreach(['new','contacted','quoted','closed'] as $sv): ?>
        <option value="<?= $sv ?>" <?= $status===$sv?'selected':'' ?>><?= ucfirst($sv) ?></option>
        <?php endforeach; ?>
        <option value="deleted" <?= $status==='deleted'?'selected':'' ?>>🗑 Deleted</option>
    </select>
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="bulk_quotes.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
    <button class="btn btn-ghost btn-sm" onclick="exportCsv()"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
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
                    <select class="form-control" style="padding:4px 8px;font-size:.78rem;min-width:110px;" onchange="setStatus(<?= $q['id'] ?>, this.value)" <?= !empty($q['is_deleted']) ? 'disabled' : '' ?>>
                        <?php foreach (['new','contacted','quoted','closed'] as $s): ?>
                        <option value="<?= $s ?>" <?= $q['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td style="font-size:.78rem;" class="text-muted"><?= timeAgo($q['created_at']) ?></td>
                <td>
                    <?php if (can('bulk_quotes','delete')): ?><button class="btn btn-ghost btn-sm btn-icon" onclick="delQuote(<?= $q['id'] ?>)" title="Delete"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($quotes)): ?><tr><td colspan="9"><div class="empty-state"><i class="fa-solid fa-file-invoice-dollar"></i><p>No bulk quote requests yet</p></div></td></tr><?php endif; ?>
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
function buildBqQuery(extra){
  const p=new URLSearchParams();
  const s=document.getElementById('searchInput').value; if(s)p.set('search',s);
  const st=document.getElementById('statusFilter').value; if(st)p.set('status',st);
  if(extra)Object.entries(extra).forEach(([k,v])=>p.set(k,v));
  return p.toString();
}
function applyFilters(){ window.location.href='bulk_quotes.php?'+buildBqQuery(); }
function exportCsv(){ window.location.href='bulk_quotes.php?'+buildBqQuery({export:'csv'}); }
function goPage(p){const q=new URLSearchParams(window.location.search);q.set('page',p);window.location.href='bulk_quotes.php?'+q.toString();}
async function setStatus(id, status){
  const res = await fetch('bulk_quotes.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'status',id,status})});
  const r = await res.json().catch(()=>({success:false,message:'Request failed'}));
  showToast && showToast(r.message || (r.success?'Status updated':'Failed'), r.success?'success':'error');
  if (r.success){ const row=document.getElementById('bq-'+id); if(row) row.style.background=''; }
}
function delQuote(id){
  showConfirm('Delete Quote Request','This hides the lead. You can restore it from the "Deleted" filter. Continue?', async () => {
    await fetch('bulk_quotes.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
    const el=document.getElementById('bq-'+id); if(el){el.style.opacity='0';setTimeout(()=>el.remove(),300);}
  });
}
async function restoreQuote(id){
  await fetch('bulk_quotes.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'restore',id})});
  const el=document.getElementById('bq-'+id); if(el){el.style.opacity='0';setTimeout(()=>el.remove(),300);}
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
