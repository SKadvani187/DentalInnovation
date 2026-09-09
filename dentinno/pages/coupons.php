<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validate.php';
$page_title = 'Coupons';
requireView('coupons');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    requireAction('coupons', rbacCrudVerb($action, $data));

    if ($action === 'save') {
        $d = $data;
        $code = strtoupper(trim((string)($d['code'] ?? '')));
        $v = new Validator(['code'=>$code] + $d);
        $v->required('code')->maxLen('code', 40)
          ->numericOpt('value', 0)->numericOpt('min_order', 0)->numericOpt('max_discount', 0)
          ->numericOpt('uses_limit', 0)->dateOpt('start_date')->dateOpt('expires_at');
        if ($v->fails()) { echo json_encode(['success'=>false,'message'=>$v->firstError()]); exit; }
        // Per-customer redemption cap (blank = unlimited per customer); scheduling window.
        $perUser   = (isset($d['per_user_limit']) && $d['per_user_limit'] !== '') ? max(1, (int)$d['per_user_limit']) : null;
        $startDate = !empty($d['start_date']) ? $d['start_date'] : null;
        $expires   = !empty($d['expires_at']) ? $d['expires_at'] : null;
        if ($startDate && $expires && strtotime($startDate) > strtotime($expires)) {
            echo json_encode(['success'=>false,'message'=>'Start date cannot be after the expiry date.']); exit;
        }
        // type/value are required and must match the schema ENUM ('percent','fixed').
        $type = $d['type'] ?? '';
        if (!in_array($type, ['percent','fixed'], true)) {
            echo json_encode(['success'=>false,'message'=>'Coupon type must be percent or fixed.']); exit;
        }
        if (!is_numeric($d['value'] ?? null) || (float)$d['value'] <= 0) {
            echo json_encode(['success'=>false,'message'=>'Coupon value must be greater than 0.']); exit;
        }
        if ($type === 'percent' && (float)$d['value'] > 100) {
            echo json_encode(['success'=>false,'message'=>'Percentage discount cannot exceed 100.']); exit;
        }
        // Code uniqueness (case-insensitive, excluding self).
        $dupe = db()->fetchOne("SELECT id FROM coupons WHERE UPPER(code)=? AND id<>?", [$code, (int)($d['id'] ?? 0)]);
        if ($dupe) { echo json_encode(['success'=>false,'message'=>'A coupon with this code already exists.']); exit; }

        if (!empty($d['id'])) {
            $before = db()->fetchOne("SELECT * FROM coupons WHERE id=?", [(int)$d['id']]);
            db()->execute("UPDATE coupons SET code=?,type=?,value=?,min_order=?,max_discount=?,uses_limit=?,per_user_limit=?,is_active=?,start_date=?,expires_at=? WHERE id=?",
                [$code,$d['type'],$d['value'],($d['min_order'] ?? 0),(($d['max_discount'] ?? '')?:null),(($d['uses_limit'] ?? '')?:null),$perUser,($d['is_active'] ?? 1),$startDate,$expires,$d['id']]);
            $after = db()->fetchOne("SELECT * FROM coupons WHERE id=?", [(int)$d['id']]);
            logActivity('updated', 'coupon', (int)$d['id'], $code.' · '.$d['type'].' '.$d['value'], auditDiff($before, $after));
            echo json_encode(['success'=>true,'message'=>'Coupon updated']);
        } else {
            $newId = db()->insert("INSERT INTO coupons (code,type,value,min_order,max_discount,uses_limit,per_user_limit,is_active,start_date,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$code,$d['type'],$d['value'],($d['min_order'] ?? 0),(($d['max_discount'] ?? '')?:null),(($d['uses_limit'] ?? '')?:null),$perUser,($d['is_active'] ?? 1),$startDate,$expires]);
            $after = db()->fetchOne("SELECT * FROM coupons WHERE id=?", [(int)$newId]);
            logActivity('created', 'coupon', (int)$newId, $code.' · '.$d['type'].' '.$d['value'], auditDiff(null, $after));
            echo json_encode(['success'=>true,'message'=>'Coupon created']);
        }
    } elseif ($action === 'delete') {
        // Soft-delete: keep the row so order history / redemptions / analytics stay intact.
        $cid    = (int)($data['id'] ?? 0);
        $before = db()->fetchOne("SELECT * FROM coupons WHERE id=?", [$cid]);
        db()->execute("UPDATE coupons SET is_deleted=1, is_active=0 WHERE id=?", [$cid]);
        logActivity('deleted', 'coupon', $cid, $before['code'] ?? null, auditDiff($before, null));
        echo json_encode(['success'=>true,'message'=>'Coupon deleted']);
    } elseif ($action === 'restore') {
        db()->execute("UPDATE coupons SET is_deleted=0 WHERE id=?", [(int)($data['id'] ?? 0)]);
        echo json_encode(['success'=>true,'message'=>'Coupon restored']);
    } elseif ($action === 'toggle') {
        db()->execute("UPDATE coupons SET is_active = NOT is_active WHERE id=? AND is_deleted=0", [(int)($data['id'] ?? 0)]);
        echo json_encode(['success'=>true,'message'=>'Status toggled']);
    } elseif ($action === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array)($data['ids'] ?? []))));
        $op  = (string)($data['op'] ?? '');
        if (!$ids) { echo json_encode(['success'=>false,'message'=>'No coupons selected']); exit; }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($op === 'activate')       { db()->execute("UPDATE coupons SET is_active=1 WHERE id IN ($ph) AND is_deleted=0", $ids); $msg = count($ids).' coupon(s) activated'; }
        elseif ($op === 'deactivate') { db()->execute("UPDATE coupons SET is_active=0 WHERE id IN ($ph) AND is_deleted=0", $ids); $msg = count($ids).' coupon(s) deactivated'; }
        elseif ($op === 'delete')     { db()->execute("UPDATE coupons SET is_deleted=1, is_active=0 WHERE id IN ($ph)", $ids); $msg = count($ids).' coupon(s) deleted'; }
        elseif ($op === 'restore')    { db()->execute("UPDATE coupons SET is_deleted=0 WHERE id IN ($ph)", $ids); $msg = count($ids).' coupon(s) restored'; }
        else { echo json_encode(['success'=>false,'message'=>'Unknown bulk action']); exit; }
        echo json_encode(['success'=>true,'message'=>$msg]);
    } elseif ($action === 'generate') {
        // Bulk code generation — N unique codes sharing the same settings (campaign codes).
        $count  = max(1, min(500, (int)($data['count'] ?? 0)));
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)($data['prefix'] ?? '')));
        $type   = ($data['type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
        $value  = (float)($data['value'] ?? 0);
        if ($value <= 0) { echo json_encode(['success'=>false,'message'=>'Discount value must be greater than 0']); exit; }
        if ($type === 'percent' && $value > 100) { echo json_encode(['success'=>false,'message'=>'Percentage cannot exceed 100']); exit; }
        // Clamp money/limit fields — this path bypasses the Validator the 'save' path uses.
        $minOrder = max(0, (float)($data['min_order'] ?? 0));
        $maxDisc  = (!empty($data['max_discount']) && (float)$data['max_discount'] > 0) ? (float)$data['max_discount'] : null;
        // A non-positive uses_limit (0 / negative) would make a coupon unusable; treat as unlimited.
        $usesLimit= (isset($data['uses_limit']) && (int)$data['uses_limit'] > 0) ? (int)$data['uses_limit'] : null;
        $perUser  = (isset($data['per_user_limit']) && $data['per_user_limit'] !== '') ? max(1,(int)$data['per_user_limit']) : null;
        $startD   = !empty($data['start_date']) ? $data['start_date'] : null;
        $expD     = !empty($data['expires_at']) ? $data['expires_at'] : null;
        $created = 0; $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $tries = 0;
            do {
                $code = ($prefix !== '' ? $prefix.'-' : '') . strtoupper(bin2hex(random_bytes(3)));
                $exists = db()->fetchOne("SELECT id FROM coupons WHERE UPPER(code)=?", [$code]);
            } while ($exists && ++$tries < 5);
            if ($exists) continue;
            db()->insert("INSERT INTO coupons (code,type,value,min_order,max_discount,uses_limit,per_user_limit,is_active,start_date,expires_at) VALUES (?,?,?,?,?,?,?,1,?,?)",
                [$code,$type,$value,$minOrder,$maxDisc,$usesLimit,$perUser,$startD,$expD]);
            $created++; $codes[] = $code;
        }
        echo json_encode(['success'=>true,'message'=>"Generated $created coupon code(s)", 'codes'=>$codes]);
    }
    exit;
}

$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$today  = date('Y-m-d');
$where = ["1=1"]; $params = [];
if ($search) { $where[] = "code LIKE ?"; $params[] = "%".strtoupper($search)."%"; }
// Soft-delete: hide deleted coupons unless the "Deleted" filter is chosen.
if ($status === 'deleted') {
    $where[] = "is_deleted = 1";
} else {
    $where[] = "is_deleted = 0";
    if ($status === 'active')        { $where[] = "is_active=1 AND (expires_at IS NULL OR expires_at >= ?) AND (start_date IS NULL OR start_date <= ?)"; $params[]=$today; $params[]=$today; }
    elseif ($status === 'inactive')  { $where[] = "is_active=0"; }
    elseif ($status === 'expired')   { $where[] = "expires_at IS NOT NULL AND expires_at < ?"; $params[]=$today; }
    elseif ($status === 'scheduled') { $where[] = "start_date IS NOT NULL AND start_date > ?"; $params[]=$today; }
}
$whereStr = implode(' AND ', $where);
$coupons = db()->fetchAll("SELECT c.*, (SELECT COALESCE(SUM(o.discount),0) FROM orders o WHERE o.coupon_id=c.id) AS discount_given FROM coupons c WHERE $whereStr ORDER BY c.created_at DESC", $params);
// Top-line usage analytics.
$stats     = db()->fetchOne("SELECT COUNT(*) total, COALESCE(SUM(uses_count),0) redemptions FROM coupons WHERE is_deleted=0");
$activeCnt = (int)(db()->fetchOne("SELECT COUNT(*) c FROM coupons WHERE is_deleted=0 AND is_active=1 AND (expires_at IS NULL OR expires_at >= ?) AND (start_date IS NULL OR start_date <= ?)", [$today, $today])['c'] ?? 0);
$discountGiven = (float)(db()->fetchOne("SELECT COALESCE(SUM(discount),0) g FROM orders WHERE coupon_id IS NOT NULL")['g'] ?? 0);
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Coupons</h1>
        <p>Manage discount coupons and promotional codes</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <?php if (can('coupons','create')): ?>
        <button class="btn btn-outline btn-sm" onclick="openGenModal()" title="Generate many campaign codes"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate Codes</button>
        <button class="btn btn-gold" onclick="openCouponModal()"><i class="fa-solid fa-plus"></i> Add Coupon</button>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;" class="fade-in">
    <?php
    $sc = [
        ['Total Coupons', number_format($stats['total'] ?? 0), 'fa-tag', '#C9A84C'],
        ['Active Now', number_format($activeCnt), 'fa-circle-check', '#2ECC71'],
        ['Redemptions', number_format($stats['redemptions'] ?? 0), 'fa-ticket', '#3498DB'],
        ['Discount Given', formatCurrency($discountGiven), 'fa-indian-rupee-sign', '#E67E22'],
    ];
    foreach($sc as [$label,$val,$icon,$color]): ?>
    <div class="card" style="padding:16px 20px;display:flex;align-items:center;gap:14px;">
        <div style="width:40px;height:40px;border-radius:10px;background:<?= $color ?>1a;display:grid;place-items:center;flex-shrink:0;">
            <i class="fa-solid <?= $icon ?>" style="color:<?= $color ?>;font-size:1rem;"></i>
        </div>
        <div>
            <div class="stat-value" style="font-size:1.3rem;"><?= $val ?></div>
            <div class="stat-label"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="filter-bar fade-in" style="flex-wrap:wrap;gap:8px;">
    <div class="search-wrapper" style="flex:1;min-width:180px;max-width:300px;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search by code..." value="<?= htmlspecialchars($search) ?>" style="text-transform:uppercase;">
    </div>
    <select class="form-control" id="statusFilter" style="max-width:150px;">
        <option value="">All Status</option>
        <option value="active"    <?= $status==='active'?'selected':'' ?>>Active</option>
        <option value="scheduled" <?= $status==='scheduled'?'selected':'' ?>>Scheduled</option>
        <option value="expired"   <?= $status==='expired'?'selected':'' ?>>Expired</option>
        <option value="inactive"  <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
        <option value="deleted"   <?= $status==='deleted'?'selected':'' ?>>🗑 Deleted</option>
    </select>
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="coupons.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<div class="card fade-in">
    <div id="bulkBar" style="display:none;padding:12px 16px;border-bottom:1px solid var(--border-color);gap:10px;align-items:center;background:var(--bg-elevated);">
        <span id="bulkCount" style="font-size:.82rem;font-weight:600;"></span>
        <?php if($status==='deleted'): ?>
        <button class="btn btn-ghost btn-sm" onclick="bulkAction('restore')"><i class="fa-solid fa-trash-arrow-up" style="color:var(--success);"></i> Restore</button>
        <?php else: ?>
        <button class="btn btn-ghost btn-sm" onclick="bulkAction('activate')"><i class="fa-solid fa-circle-check" style="color:var(--success);"></i> Activate</button>
        <button class="btn btn-ghost btn-sm" onclick="bulkAction('deactivate')"><i class="fa-solid fa-ban" style="color:var(--warning);"></i> Deactivate</button>
        <button class="btn btn-ghost btn-sm" onclick="bulkAction('delete')" style="color:var(--danger);"><i class="fa-solid fa-trash"></i> Delete</button>
        <?php endif; ?>
        <button class="btn btn-ghost btn-sm" onclick="clearBulk()">Clear</button>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width:34px;"><input type="checkbox" id="selectAllCoup" onchange="toggleAllCoup(this)" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;"></th>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Value</th>
                    <th>Min Order</th>
                    <th>Max Discount</th>
                    <th>Used / Limit</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($coupons as $c):
                    $isExpired   = !empty($c['expires_at']) && strtotime($c['expires_at']) < strtotime($today);
                    $isScheduled = !empty($c['start_date']) && strtotime($c['start_date']) > strtotime($today);
                    if (!$c['is_active'])    { $stLabel='Inactive';  $stClass='secondary'; }
                    elseif ($isExpired)      { $stLabel='Expired';   $stClass='danger'; }
                    elseif ($isScheduled)    { $stLabel='Scheduled'; $stClass='warning'; }
                    else                     { $stLabel='Active';    $stClass='success'; }
                ?>
                <tr id="coupon-row-<?= $c['id'] ?>">
                    <td><input type="checkbox" class="coup-check" value="<?= $c['id'] ?>" onchange="updateBulkBar()" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;"></td>
                    <td><span class="font-bold text-gold" style="font-family:monospace;font-size:1rem;"><?= htmlspecialchars($c['code']) ?></span></td>
                    <td><span class="badge badge-info"><?= ucfirst($c['type']) ?></span></td>
                    <td class="font-bold">
                        <?= $c['type']==='percent' ? $c['value'].'%' : formatCurrency($c['value']) ?>
                    </td>
                    <td><?= formatCurrency($c['min_order']) ?></td>
                    <td><?= $c['max_discount'] ? formatCurrency($c['max_discount']) : '<span class="text-muted">—</span>' ?></td>
                    <td>
                        <?= $c['uses_count'] ?> / <?= $c['uses_limit'] ?: '∞' ?>
                        <?php if($c['uses_limit']): ?>
                        <div style="margin-top:4px;background:var(--bg-elevated);border-radius:99px;height:4px;overflow:hidden;">
                            <div style="height:100%;width:<?= min(100, ($c['uses_count']/$c['uses_limit'])*100) ?>%;background:var(--gold-primary);border-radius:99px;"></div>
                        </div>
                        <?php endif; ?>
                        <div class="text-muted" style="font-size:.66rem;margin-top:3px;"><?= !empty($c['per_user_limit']) ? (int)$c['per_user_limit'].' / customer' : '∞ / customer' ?></div>
                        <?php if((float)($c['discount_given'] ?? 0) > 0): ?><div style="font-size:.66rem;margin-top:2px;color:var(--success);"><?= formatCurrency($c['discount_given']) ?> given</div><?php endif; ?>
                    </td>
                    <td>
                        <?php if(!empty($c['start_date'])): ?><div class="text-muted" style="font-size:.66rem;">from <?= formatDate($c['start_date'],'d M Y') ?></div><?php endif; ?>
                        <?= $c['expires_at'] ? formatDate($c['expires_at'],'d M Y') : '<span class="text-muted">Never</span>' ?>
                    </td>
                    <td><span class="badge badge-<?= $stClass ?>"><?= $stLabel ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <?php if(!empty($c['is_deleted'])): ?>
                            <?php if (can('coupons','edit')): ?><button class="btn btn-ghost btn-sm" onclick="restoreCoupon(<?= $c['id'] ?>)" title="Restore coupon"><i class="fa-solid fa-trash-arrow-up" style="color:var(--success);"></i> Restore</button><?php endif; ?>
                            <?php else: ?>
                            <?php if (can('coupons','edit')): ?>
                            <button class="btn btn-ghost btn-sm btn-icon" title="Edit" onclick='openCouponModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8") ?>)'><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-ghost btn-sm btn-icon" title="Activate/Deactivate" onclick="toggleCoupon(<?= $c['id'] ?>)"><i class="fa-solid fa-power-off" style="color:<?= $c['is_active']?'var(--success)':'var(--text-muted)' ?>;"></i></button>
                            <?php endif; ?>
                            <?php if (can('coupons','delete')): ?><button class="btn btn-ghost btn-sm btn-icon" title="Delete" onclick="deleteCoupon(<?= $c['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($coupons)): ?>
                <tr><td colspan="10"><div class="empty-state"><i class="fa-solid fa-tag"></i><p>No coupons yet</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="couponModal" style="display:none;" onclick="if(event.target===this)closeModal('couponModal')">
    <div class="modal-box modal-lg">
        <div class="modal-head">
            <h2 id="couponModalTitle">Add New Coupon</h2>
            <button class="close-btn" onclick="closeModal('couponModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="coup_id">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Coupon Code *</label>
                    <input type="text" class="form-control" id="coup_code" placeholder="e.g. DENT20" style="text-transform:uppercase;font-weight:700;">
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Type *</label>
                    <select class="form-control" id="coup_type">
                        <option value="percent">Percent (%)</option>
                        <option value="fixed">Fixed Amount (₹)</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Discount Value *</label>
                    <input type="number" class="form-control" id="coup_value" placeholder="e.g. 10 for 10%">
                </div>
                <div class="form-group">
                    <label class="form-label">Minimum Order (₹)</label>
                    <input type="number" class="form-control" id="coup_min" placeholder="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Max Discount (₹)</label>
                    <input type="number" class="form-control" id="coup_max" placeholder="Optional cap">
                </div>
                <div class="form-group">
                    <label class="form-label">Usage Limit</label>
                    <input type="number" class="form-control" id="coup_limit" placeholder="Leave blank for unlimited">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Starts On <small class="text-muted">(blank = immediately)</small></label>
                    <input type="date" class="form-control" id="coup_start">
                </div>
                <div class="form-group">
                    <label class="form-label">Per-Customer Limit <small class="text-muted">(blank = unlimited)</small></label>
                    <input type="number" min="1" class="form-control" id="coup_peruser" placeholder="e.g. 1">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Expires On <small class="text-muted">(blank = never)</small></label>
                    <input type="date" class="form-control" id="coup_expires">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control" id="coup_status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closeModal('couponModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveCoupon()"><i class="fa-solid fa-floppy-disk"></i> Save Coupon</button>
        </div>
    </div>
</div>

<!-- Generate Codes Modal -->
<div class="modal-overlay" id="genModal" style="display:none;" onclick="if(event.target===this)closeModal('genModal')">
    <div class="modal-box modal-lg">
        <div class="modal-head"><h2>Generate Coupon Codes</h2><button class="close-btn" onclick="closeModal('genModal')"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body">
            <p class="text-muted" style="font-size:.8rem;margin-bottom:14px;">Create many unique codes that share the same discount (great for campaigns / one-time codes). Each gets a random suffix.</p>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Code Prefix <small class="text-muted">(optional)</small></label><input type="text" class="form-control" id="gen_prefix" placeholder="e.g. DIWALI" style="text-transform:uppercase;"></div>
                <div class="form-group"><label class="form-label">How Many *</label><input type="number" class="form-control" id="gen_count" min="1" max="500" placeholder="e.g. 100"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Discount Type</label><select class="form-control" id="gen_type"><option value="percent">Percent (%)</option><option value="fixed">Fixed Amount (₹)</option></select></div>
                <div class="form-group"><label class="form-label">Discount Value *</label><input type="number" class="form-control" id="gen_value" placeholder="e.g. 10"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Minimum Order (₹)</label><input type="number" class="form-control" id="gen_min" placeholder="0"></div>
                <div class="form-group"><label class="form-label">Uses Per Code <small class="text-muted">(blank = unlimited)</small></label><input type="number" class="form-control" id="gen_uses" min="1" placeholder="e.g. 1"></div>
            </div>
            <div class="form-group"><label class="form-label">Expires On <small class="text-muted">(blank = never)</small></label><input type="date" class="form-control" id="gen_expires"></div>
            <div id="gen_result" style="display:none;margin-top:8px;padding:12px;background:var(--bg-elevated);border-radius:8px;font-size:.76rem;max-height:160px;overflow:auto;font-family:monospace;"></div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closeModal('genModal')">Close</button>
            <button class="btn btn-gold" onclick="generateCodes()"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate</button>
        </div>
    </div>
</div>

<script>
function openGenModal(){ document.getElementById('gen_result').style.display='none'; openModal('genModal'); }
async function generateCodes(){
    const count=parseInt(document.getElementById('gen_count').value)||0;
    const value=parseFloat(document.getElementById('gen_value').value)||0;
    if(count<1){showToast('Enter how many codes to generate','warning');return;}
    if(value<=0){showToast('Enter a discount value','warning');return;}
    const data={action:'generate',prefix:document.getElementById('gen_prefix').value,count,
        type:document.getElementById('gen_type').value,value,
        min_order:document.getElementById('gen_min').value||0,
        uses_limit:document.getElementById('gen_uses').value,
        expires_at:document.getElementById('gen_expires').value};
    const res=await fetch('coupons.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});
    const r=await res.json();
    if(r.success){
        showToast(r.message,'success');
        const box=document.getElementById('gen_result'); box.style.display='block';
        box.innerHTML='<strong>'+(r.codes||[]).length+' codes created:</strong><br>'+(r.codes||[]).join('<br>');
        setTimeout(()=>location.reload(),3500);
    } else showToast(r.message,'danger');
}
// ---- Bulk selection ----
function selectedCoupIds(){return [...document.querySelectorAll('.coup-check:checked')].map(c=>parseInt(c.value));}
function updateBulkBar(){
    const n=selectedCoupIds().length;
    const bar=document.getElementById('bulkBar');
    bar.style.display=n?'flex':'none';
    if(n)document.getElementById('bulkCount').textContent=n+' selected';
    const all=document.getElementById('selectAllCoup'); const total=document.querySelectorAll('.coup-check').length;
    if(all)all.checked=n>0&&n===total;
}
function toggleAllCoup(cb){document.querySelectorAll('.coup-check').forEach(c=>c.checked=cb.checked);updateBulkBar();}
function clearBulk(){document.querySelectorAll('.coup-check').forEach(c=>c.checked=false);const a=document.getElementById('selectAllCoup');if(a)a.checked=false;updateBulkBar();}
async function bulkAction(op){
    const ids=selectedCoupIds(); if(!ids.length)return;
    if(!confirm(`${op.charAt(0).toUpperCase()+op.slice(1)} ${ids.length} coupon(s)?`))return;
    const res=await fetch('coupons.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'bulk',op,ids})});
    const r=await res.json();
    showToast(r.message||(r.success?'Done':'Failed'), r.success?'success':'danger');
    if(r.success)setTimeout(()=>location.reload(),800);
}
function openCouponModal(c = null) {
    document.getElementById('coup_id').value      = c?.id || '';
    document.getElementById('coup_code').value    = c?.code || '';
    document.getElementById('coup_type').value    = c?.type || 'percent';
    document.getElementById('coup_value').value   = c?.value || '';
    document.getElementById('coup_min').value     = c?.min_order || '0';
    document.getElementById('coup_max').value     = c?.max_discount || '';
    document.getElementById('coup_limit').value   = c?.uses_limit || '';
    document.getElementById('coup_peruser').value = c?.per_user_limit || '';
    document.getElementById('coup_start').value   = c?.start_date || '';
    document.getElementById('coup_expires').value = c?.expires_at || '';
    document.getElementById('coup_status').value  = c?.is_active ?? 1;
    document.getElementById('couponModalTitle').textContent = c ? 'Edit Coupon' : 'Add New Coupon';
    openModal('couponModal');
}
function applyFilters(){
    const p=new URLSearchParams({search:document.getElementById('searchInput')?.value||'',status:document.getElementById('statusFilter')?.value||''});
    [...p.entries()].forEach(([k,v])=>{if(!v)p.delete(k);});
    window.location.href='coupons.php?'+p.toString();
}

async function saveCoupon() {
    const code  = document.getElementById('coup_code').value.trim().toUpperCase();
    const value = document.getElementById('coup_value').value;
    if (!code || !value) { showToast('Code and value are required', 'warning'); return; }
    const data = {
        action:'save', id:document.getElementById('coup_id').value,
        code, type:document.getElementById('coup_type').value, value,
        min_order:document.getElementById('coup_min').value||0,
        max_discount:document.getElementById('coup_max').value,
        uses_limit:document.getElementById('coup_limit').value,
        per_user_limit:document.getElementById('coup_peruser').value,
        start_date:document.getElementById('coup_start').value,
        expires_at:document.getElementById('coup_expires').value,
        is_active:document.getElementById('coup_status').value,
    };
    const res = await fetch('coupons.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});
    const r = await res.json();
    if(r.success){showToast(r.message,'success');closeModal('couponModal');setTimeout(()=>location.reload(),800);}
    else showToast(r.message,'danger');
}

async function toggleCoupon(id) {
    const res = await fetch('coupons.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'toggle',id})});
    const r = await res.json().catch(()=>({success:false,message:'Request failed'}));
    showToast(r.message || (r.success ? 'Status updated' : 'Failed'), r.success ? 'success' : 'error');
    if (r.success) setTimeout(()=>location.reload(),600);
}
function deleteCoupon(id) {
    showConfirm('Delete Coupon','This hides the coupon and stops it working at checkout. Order history and usage stats are kept, and you can restore it from the "Deleted" filter. Continue?', async () => {
        await fetch('coupons.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
        showToast('Coupon deleted','success');
        const row=document.getElementById(`coupon-row-${id}`);if(row){row.style.opacity='0';row.style.transition='0.3s';setTimeout(()=>row.remove(),300);}
    });
}
function restoreCoupon(id){
    fetch('coupons.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'restore',id})})
    .then(r=>r.json()).then(d=>{if(d.success){showToast('Coupon restored','success');setTimeout(()=>location.reload(),600);}});
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
