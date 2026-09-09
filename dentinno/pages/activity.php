<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Activity Log';

// The activity log spans all admins' actions — restrict to super admins.
if (!hasPermission('manage_admins')) { header('Location: ' . APP_URL . '/index.php'); exit; }

// --- Filters ---
$search = sanitize($_GET['search'] ?? '');
$entity = sanitize($_GET['entity'] ?? '');
$action = sanitize($_GET['action'] ?? '');
$from   = sanitize($_GET['from'] ?? '');
$to     = sanitize($_GET['to'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per_page = 40; $offset = ($page - 1) * $per_page;

$where = ["1=1"]; $params = [];
if ($search) { $where[] = "(actor_name LIKE ? OR summary LIKE ? OR entity_id LIKE ?)"; $params = array_merge($params, array_fill(0,3,"%$search%")); }
if ($entity !== '') { $where[] = "entity_type = ?"; $params[] = $entity; }
if ($action !== '') { $where[] = "action = ?"; $params[] = $action; }
if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = "created_at >= ?"; $params[] = $from.' 00:00:00'; }
if ($to   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = "created_at <= ?"; $params[] = $to.' 23:59:59'; }
$whereStr = implode(' AND ', $where);

// --- CSV export ---
if (isset($_GET['export'])) {
    try { $rows = db()->fetchAll("SELECT * FROM activity_log WHERE $whereStr ORDER BY created_at DESC", $params); }
    catch (Throwable $e) { $rows = []; }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity-log-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['When','Actor','Action','Entity','Entity ID','Summary','Changes (field: old -> new)']);
    // "field: old -> new; …" so the export is readable in Excel without unpacking JSON.
    foreach ($rows as $r) {
        $chg = $r['changes'] ? json_decode($r['changes'], true) : null;
        $flat = '';
        if (is_array($chg)) {
            $parts = [];
            foreach ($chg as $f => $v) {
                $parts[] = $f . ': ' . ($v['old'] ?? '—') . ' -> ' . ($v['new'] ?? '—');
            }
            $flat = implode('; ', $parts);
        }
        fputcsv($out, [$r['created_at'],$r['actor_name'],$r['action'],$r['entity_type'],$r['entity_id'],$r['summary'],$flat]);
    }
    fclose($out); exit;
}

$tableMissing = false;
try {
    $total = (int)(db()->fetchOne("SELECT COUNT(*) c FROM activity_log WHERE $whereStr", $params)['c'] ?? 0);
    $rows  = db()->fetchAll("SELECT * FROM activity_log WHERE $whereStr ORDER BY created_at DESC LIMIT $per_page OFFSET $offset", $params);
    // Distinct entity types for the filter dropdown.
    $entityTypes = array_column(db()->fetchAll("SELECT DISTINCT entity_type FROM activity_log ORDER BY entity_type"), 'entity_type');
} catch (Throwable $e) { $total = 0; $rows = []; $entityTypes = []; $tableMissing = true; }
$pages = (int)ceil($total / $per_page);

include __DIR__ . '/../includes/header.php';

function alActionBadge(string $a): string {
    return ['created'=>'success','updated'=>'info','deleted'=>'danger','restored'=>'success','toggled'=>'secondary','adjusted'=>'warning'][$a] ?? 'secondary';
}
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1><i class="fa-solid fa-clock-rotate-left" style="color:var(--gold-primary);margin-right:10px;"></i>Activity Log</h1>
        <p>Who changed which catalog / pricing / CMS / customer — <?= number_format($total) ?> events</p>
    </div>
    <?php if(!empty($rows)): ?>
    <button class="btn btn-ghost btn-sm" onclick="exportCsv()"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
    <?php endif; ?>
</div>

<?php if($tableMissing): ?>
<div class="card fade-in" style="padding:24px;text-align:center;color:var(--text-muted);">Activity log table not found. Run <code>php migrate.php</code>.</div>
<?php else: ?>

<div class="filter-bar fade-in" style="flex-wrap:wrap;gap:8px;">
    <div class="search-wrapper" style="flex:1;min-width:180px;max-width:280px;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search actor / summary / id..." value="<?= htmlspecialchars($search) ?>" onkeydown="if(event.key==='Enter')applyFilters()">
    </div>
    <select class="form-control" id="entityFilter" style="max-width:150px;">
        <option value="">All Entities</option>
        <?php foreach($entityTypes as $et): ?><option value="<?= htmlspecialchars($et) ?>" <?= $entity===$et?'selected':'' ?>><?= htmlspecialchars(ucfirst($et)) ?></option><?php endforeach; ?>
    </select>
    <select class="form-control" id="actionFilter" style="max-width:140px;">
        <option value="">All Actions</option>
        <?php foreach(['created','updated','deleted','restored','toggled','adjusted'] as $av): ?><option value="<?= $av ?>" <?= $action===$av?'selected':'' ?>><?= ucfirst($av) ?></option><?php endforeach; ?>
    </select>
    <input type="date" class="form-control" id="fromDate" value="<?= htmlspecialchars($from) ?>" style="max-width:150px;" title="From">
    <input type="date" class="form-control" id="toDate" value="<?= htmlspecialchars($to) ?>" style="max-width:150px;" title="To">
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="activity.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<div class="card fade-in">
    <div class="table-responsive">
        <table>
            <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Entity</th><th>Summary</th><th style="width:110px;">Changes</th></tr></thead>
            <tbody>
                <?php foreach($rows as $i => $r):
                    $chg = $r['changes'] ? json_decode($r['changes'], true) : null;
                    $chg = is_array($chg) ? $chg : null;
                ?>
                <tr>
                    <td class="text-muted" style="font-size:.78rem;white-space:nowrap;"><?= date('d M Y, h:i A', strtotime($r['created_at'])) ?></td>
                    <td style="font-size:.82rem;"><?= htmlspecialchars($r['actor_name'] ?: ('#'.(int)$r['actor_id'])) ?></td>
                    <td><span class="badge badge-<?= alActionBadge($r['action']) ?>"><?= htmlspecialchars(ucfirst($r['action'])) ?></span></td>
                    <td style="font-size:.82rem;"><?= htmlspecialchars(ucfirst($r['entity_type'])) ?><?= $r['entity_id']!==null ? ' <span class="text-muted">#'.htmlspecialchars($r['entity_id']).'</span>' : '' ?></td>
                    <td style="font-size:.82rem;max-width:320px;"><?= htmlspecialchars($r['summary'] ?? '—') ?></td>
                    <td>
                        <?php if($chg): ?>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="alToggle(<?= (int)$i ?>)" style="white-space:nowrap;">
                            <i class="fa-solid fa-code-compare"></i> <?= count($chg) ?> field<?= count($chg)>1?'s':'' ?>
                        </button>
                        <?php else: ?><span class="text-muted" style="font-size:.78rem;">—</span><?php endif; ?>
                    </td>
                </tr>
                <?php if($chg): ?>
                <tr id="alChg<?= (int)$i ?>" style="display:none;">
                    <td colspan="6" style="background:var(--bg-surface);">
                        <table style="width:100%;font-size:.8rem;">
                            <thead><tr>
                                <th style="width:22%;">Field</th><th style="width:39%;">Old value</th><th style="width:39%;">New value</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach($chg as $field => $v): ?>
                                <tr>
                                    <td style="font-weight:600;"><?= htmlspecialchars((string)$field) ?></td>
                                    <td style="color:var(--danger);word-break:break-word;"><?= $v['old'] === null ? '<span class="text-muted">—</span>' : nl2br(htmlspecialchars((string)$v['old'])) ?></td>
                                    <td style="color:var(--success, #22c55e);word-break:break-word;"><?= $v['new'] === null ? '<span class="text-muted">—</span>' : nl2br(htmlspecialchars((string)$v['new'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </td>
                </tr>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php if(empty($rows)): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i><p><?= ($search||$entity||$action||$from||$to) ? 'No activity matches your filters' : 'No activity recorded yet' ?></p></div></td></tr>
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
function buildActQuery(extra){
    const p=new URLSearchParams();
    const g=(id)=>document.getElementById(id)?.value;
    if(g('searchInput'))p.set('search',g('searchInput'));
    if(g('entityFilter'))p.set('entity',g('entityFilter'));
    if(g('actionFilter'))p.set('action',g('actionFilter'));
    if(g('fromDate'))p.set('from',g('fromDate'));
    if(g('toDate'))p.set('to',g('toDate'));
    if(extra)Object.entries(extra).forEach(([k,v])=>p.set(k,v));
    return p.toString();
}
// Expand/collapse one row's field-level before/after.
function alToggle(i){
  const row = document.getElementById('alChg'+i);
  if (row) row.style.display = (row.style.display === 'none' ? '' : 'none');
}

function applyFilters(){ window.location.href='activity.php?'+buildActQuery(); }
function exportCsv(){ window.location.href='activity.php?'+buildActQuery({export:'csv'}); }
function goPage(p){const q=new URLSearchParams(window.location.search);q.set('page',p);window.location.href='activity.php?'+q.toString();}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
