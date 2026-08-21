<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Inventory Ledger';
requireView('inventory');   // RBAC page guard, same as other admin pages

// --- Filters ---
$search = sanitize($_GET['search'] ?? '');
$type   = sanitize($_GET['type'] ?? '');
$from   = sanitize($_GET['from'] ?? '');
$to     = sanitize($_GET['to'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per_page = 30; $offset = ($page - 1) * $per_page;

$where = ["1=1"]; $params = [];
if ($search) { $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR im.reference LIKE ? OR im.reason LIKE ?)"; $params = array_merge($params, array_fill(0,4,"%$search%")); }
$validTypes = ['sale','refund','manual','edit','initial'];
if ($type !== '' && in_array($type, $validTypes, true)) { $where[] = "im.type = ?"; $params[] = $type; }
if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = "im.created_at >= ?"; $params[] = $from.' 00:00:00'; }
if ($to   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = "im.created_at <= ?"; $params[] = $to.' 23:59:59'; }
$whereStr = implode(' AND ', $where);

$sel   = "im.*, p.name AS product_name, p.sku, a.name AS admin_name";
$joins = "FROM inventory_movements im LEFT JOIN products p ON p.id=im.product_id LEFT JOIN admin_users a ON a.id=im.admin_id";

// --- CSV export (before any HTML) ---
if (isset($_GET['export'])) {
    try { $rows = db()->fetchAll("SELECT $sel $joins WHERE $whereStr ORDER BY im.created_at DESC", $params); }
    catch (Throwable $e) { $rows = []; }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory-ledger-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','Product','SKU','Type','Change','Balance After','Reason','Reference','By']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['created_at'],$r['product_name'],$r['sku'],$r['type'],($r['delta']>0?'+':'').$r['delta'],$r['balance_after'],$r['reason'],$r['reference'],$r['admin_name']]);
    }
    fclose($out); exit;
}

$tableMissing = false;
try {
    $total = (int)(db()->fetchOne("SELECT COUNT(*) c $joins WHERE $whereStr", $params)['c'] ?? 0);
    $rows  = db()->fetchAll("SELECT $sel $joins WHERE $whereStr ORDER BY im.created_at DESC LIMIT $per_page OFFSET $offset", $params);
} catch (Throwable $e) { $total = 0; $rows = []; $tableMissing = true; }
$pages = (int)ceil($total / $per_page);

include __DIR__ . '/../includes/header.php';

function imTypeBadge(string $t): array {
    // [badge-class, label]
    return [
        'sale'    => ['danger',   'Sale'],
        'refund'  => ['success',  'Refund'],
        'manual'  => ['info',     'Manual'],
        'edit'    => ['secondary','Edit'],
        'initial' => ['warning',  'Initial'],
    ][$t] ?? ['secondary', ucfirst($t)];
}
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1><i class="fa-solid fa-warehouse" style="color:var(--gold-primary);margin-right:10px;"></i>Inventory Ledger</h1>
        <p>Every stock change — sale, refund, manual adjustment, edit — <?= number_format($total) ?> movements</p>
    </div>
    <?php if(!empty($rows)): ?>
    <button class="btn btn-ghost btn-sm" onclick="exportCsv()"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
    <?php endif; ?>
</div>

<?php if($tableMissing): ?>
<div class="card fade-in" style="padding:24px;text-align:center;color:var(--text-muted);">
    Inventory ledger table not found. Run <code>php migrate.php</code> to create it.
</div>
<?php else: ?>

<div class="filter-bar fade-in" style="flex-wrap:wrap;gap:8px;">
    <div class="search-wrapper" style="flex:1;min-width:180px;max-width:280px;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search product / reason / order #..." value="<?= htmlspecialchars($search) ?>" onkeydown="if(event.key==='Enter')applyFilters()">
    </div>
    <select class="form-control" id="typeFilter" style="max-width:150px;">
        <option value="">All Types</option>
        <?php foreach(['sale','refund','manual','edit','initial'] as $tv): ?>
        <option value="<?= $tv ?>" <?= $type===$tv?'selected':'' ?>><?= ucfirst($tv) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" class="form-control" id="fromDate" value="<?= htmlspecialchars($from) ?>" style="max-width:150px;" title="From">
    <input type="date" class="form-control" id="toDate" value="<?= htmlspecialchars($to) ?>" style="max-width:150px;" title="To">
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="inventory.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<div class="card fade-in">
    <div class="table-responsive">
        <table>
            <thead><tr><th>Date</th><th>Product</th><th>Type</th><th style="text-align:right;">Change</th><th style="text-align:right;">Balance</th><th>Reason</th><th>By</th></tr></thead>
            <tbody>
                <?php foreach($rows as $r): [$bc,$bl] = imTypeBadge($r['type']); ?>
                <tr>
                    <td class="text-muted" style="font-size:.78rem;white-space:nowrap;"><?= date('d M Y, h:i A', strtotime($r['created_at'])) ?></td>
                    <td>
                        <div class="font-bold" style="font-size:.84rem;"><?= htmlspecialchars($r['product_name'] ?? ('#'.(int)$r['product_id'])) ?></div>
                        <?php if($r['sku'] || $r['reference']): ?><div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($r['sku'] ?? '') ?><?= $r['reference'] ? ' · '.htmlspecialchars($r['reference']) : '' ?></div><?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= $bc ?>"><?= $bl ?></span></td>
                    <td style="text-align:right;font-weight:700;color:<?= $r['delta']>0 ? 'var(--success)' : 'var(--danger)' ?>;"><?= ($r['delta']>0?'+':'').(int)$r['delta'] ?></td>
                    <td style="text-align:right;" class="text-muted"><?= $r['balance_after']!==null ? (int)$r['balance_after'] : '—' ?></td>
                    <td style="font-size:.82rem;max-width:240px;"><?= htmlspecialchars($r['reason'] ?? '—') ?></td>
                    <td class="text-muted" style="font-size:.8rem;"><?= htmlspecialchars($r['admin_name'] ?: 'system') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($rows)): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-warehouse"></i><p><?= ($search||$type||$from||$to) ? 'No movements match your filters' : 'No stock movements recorded yet' ?></p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($pages > 1): ?>
    <div style="padding:16px 20px;border-top:1px solid var(--border-color);">
      <div class="pagination">
        <?php
        $range = 2; $shown = [];
        for ($i = 1; $i <= $pages; $i++) { if ($i == 1 || $i == $pages || ($i >= $page - $range && $i <= $page + $range)) $shown[] = $i; }
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
<?php endif; ?>

<script>
function buildInvQuery(extra){
    const p=new URLSearchParams();
    const s=document.getElementById('searchInput')?.value; if(s)p.set('search',s);
    const t=document.getElementById('typeFilter')?.value; if(t)p.set('type',t);
    const f=document.getElementById('fromDate')?.value; if(f)p.set('from',f);
    const td=document.getElementById('toDate')?.value; if(td)p.set('to',td);
    if(extra)Object.entries(extra).forEach(([k,v])=>p.set(k,v));
    return p.toString();
}
function applyFilters(){ window.location.href='inventory.php?'+buildInvQuery(); }
function exportCsv(){ window.location.href='inventory.php?'+buildInvQuery({export:'csv'}); }
function goPage(p){const q=new URLSearchParams(window.location.search);q.set('page',p);window.location.href='inventory.php?'+q.toString();}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
