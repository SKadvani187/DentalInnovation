<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/order_effects.php';
$page_title = 'Refunds';
requireView('refunds');
$current_page = 'refunds';

// Razorpay refund call (admin-side, self-contained — mirrors rzpRequest in the storefront
// payment endpoint). Returns the gateway JSON or throws.
function rzpRefund(string $paymentId, float $amount): array {
    $ch = curl_init('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId) . '/refund');
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POSTFIELDS     => json_encode(['amount' => (int) round($amount * 100)], JSON_UNESCAPED_SLASHES),
    ];
    if (defined('OTP_SSL_INSECURE') && OTP_SSL_INSECURE) {
        $opts[CURLOPT_SSL_VERIFYPEER] = false;
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
    } elseif (is_readable(__DIR__ . '/../includes/cacert.pem')) {
        $opts[CURLOPT_CAINFO] = __DIR__ . '/../includes/cacert.pem';
    }
    curl_setopt_array($ch, $opts);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('Gateway unreachable: ' . $err);
    $json = json_decode($raw, true);
    if (!is_array($json)) throw new RuntimeException('Invalid gateway response');
    if ($code >= 400) throw new RuntimeException('Razorpay: ' . ($json['error']['description'] ?? 'refund failed'));
    return $json;
}

// --- AJAX: approve / reject ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    requirePermissionAjax('manage_refunds'); // real-money payouts: super_admin only
    // Never let a PHP warning/exception leak HTML into the JSON response (breaks res.json()).
    try {
    $data    = json_decode(file_get_contents('php://input'), true);
    $action  = $data['action'] ?? '';
    $rid     = (int)($data['id'] ?? 0);
    $note    = trim((string)($data['note'] ?? ''));
    $actedBy = (int)($_SESSION['admin_id'] ?? 0) ?: null;   // audit: who is actioning this payout

    $rr = db()->fetchOne(
        "SELECT rr.*, o.order_number, o.payment_method, o.total
           FROM refund_requests rr JOIN orders o ON o.id = rr.order_id WHERE rr.id = ?",
        [$rid]
    );
    if (!$rr) { echo json_encode(['success' => false, 'message' => 'Request not found']); exit; }

    // RECOVERY CASE: a prior approve already issued the gateway refund (razorpay_refund_id is
    // set) but crashed before the DB transaction could mark the order refunded, leaving the
    // request stuck in 'processing'. Allow a re-approve to FINISH the DB side only — never a
    // second gateway call. Any other non-actionable status is rejected.
    $gatewayAlreadyDone = ($rr['status'] === 'processing' && !empty($rr['razorpay_refund_id']));
    if (!in_array($rr['status'], ['pending', 'approved'], true) && !$gatewayAlreadyDone) {
        echo json_encode(['success' => false, 'message' => 'Request already ' . $rr['status']]); exit;
    }

    if ($action === 'reject') {
        // Cannot reject once a refund is already in flight / paid out.
        if (!in_array($rr['status'], ['pending', 'approved'], true)) {
            echo json_encode(['success' => false, 'message' => 'Cannot reject — refund already in progress or paid out']); exit;
        }
        db()->execute(
            "UPDATE refund_requests SET status='rejected', admin_note=?, actioned_by=?, actioned_at=NOW() WHERE id=?",
            [$note, $actedBy, $rid]
        );
        echo json_encode(['success' => true, 'message' => 'Refund rejected']); exit;
    }

    if ($action === 'approve') {
        $orderId    = (int)$rr['order_id'];
        $orderTotal = (float)$rr['total'];
        // Admin may adjust the payout (partial refund) — defaults to the requested amount.
        $amount = (isset($data['amount']) && $data['amount'] !== '') ? (float)$data['amount'] : (float)$rr['refund_amount'];
        $rzpRefId   = null;
        $isFull     = false;
        if ($amount <= 0) { echo json_encode(['success'=>false,'message'=>'Refund amount must be greater than 0']); exit; }
        // Cumulative refunds on this order can never exceed the order total.
        $alreadyRefunded = (float)(db()->fetchOne(
            "SELECT COALESCE(SUM(refund_amount),0) v FROM refund_requests WHERE order_id=? AND status='completed' AND id<>?",
            [$orderId, $rid])['v'] ?? 0);
        $balance = $orderTotal - $alreadyRefunded;
        if ($amount > $balance + 0.01) {
            echo json_encode(['success'=>false,'message'=>'Amount exceeds the refundable balance (₹'.number_format($balance,2).' left of ₹'.number_format($orderTotal,2).')']); exit;
        }

        if (!$gatewayAlreadyDone) {
            // --- Amount validation (applies to EVERY method, incl. COD/manual) ---
            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Refund amount must be greater than zero']); exit;
            }
            // Cumulative cap: this refund + everything already refunded on the order must not
            // exceed the order total. Prevents two separate requests each paying out a slice
            // that together exceed 100% (real money out twice).
            $already = (float) db()->fetchOne(
                "SELECT COALESCE(SUM(refund_amount),0) v FROM refund_requests WHERE order_id=? AND status='completed'",
                [$orderId]
            )['v'];
            if ($amount > ($orderTotal - $already) + 0.001) {
                echo json_encode(['success' => false, 'message' =>
                    'Refund exceeds the order total. Already refunded ' . formatCurrency($already) .
                    ' of ' . formatCurrency($orderTotal) . '; this request is ' . formatCurrency($amount) . '.']); exit;
            }

            // SECURITY (double-refund prevention): atomically claim this request BEFORE calling the
            // gateway. Only the row-update that transitions pending/approved -> processing proceeds;
            // a concurrent second click / re-submit gets rowCount()===0 and bails, so rzpRefund()
            // fires at most once per request.
            $claimed = db()->execute(
                "UPDATE refund_requests SET status='processing' WHERE id=? AND status IN ('pending','approved') AND (razorpay_refund_id IS NULL OR razorpay_refund_id='')",
                [$rid]
            );
            if ($claimed < 1) {
                echo json_encode(['success' => false, 'message' => 'Refund already being processed or completed']); exit;
            }

            // For an online order with a captured Razorpay payment, fire a real gateway refund.
            $pay = db()->fetchOne(
                "SELECT * FROM payments WHERE order_id=? AND status='completed' AND transaction_id LIKE 'pay\_%' ESCAPE '\\\\' ORDER BY id DESC LIMIT 1",
                [$orderId]
            );
            if ($pay && ($rr['payment_method'] ?? '') !== 'cod') {
                // Guard: refund amount must be within the captured payment amount.
                $captured = (float)$pay['amount'];
                if ($amount > $captured + 0.001) {
                    db()->execute("UPDATE refund_requests SET status='approved' WHERE id=?", [$rid]); // release claim
                    echo json_encode(['success' => false, 'message' => 'Invalid refund amount (exceeds captured payment)']); exit;
                }
                try {
                    $res = rzpRefund($pay['transaction_id'], $amount);
                    $rzpRefId = $res['id'] ?? null;
                } catch (Throwable $e) {
                    db()->execute("UPDATE refund_requests SET status='approved' WHERE id=?", [$rid]); // release claim on gateway failure
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]); exit;
                }
                // Persist the gateway refund id IMMEDIATELY so a crash before the txn below can't
                // cause a second real refund (the claim guard above also blocks re-entry).
                db()->execute("UPDATE refund_requests SET razorpay_refund_id=? WHERE id=?", [$rzpRefId, $rid]);
            }
            // COD / no gateway payment → manual refund (recorded, money returned out-of-band).
        }
        // else: $gatewayAlreadyDone — skip validation/claim/gateway; the prior txn rolled back
        // so no order effects were applied yet. Just finish the DB side below with $rzpRefId.

        $pdo = db()->getConnection();
        $pdo->beginTransaction();
        try {
            // Persist the (possibly adjusted) amount so the cumulative total stays accurate.
            db()->execute(
                "UPDATE refund_requests SET status='completed', refund_amount=?, admin_note=?, razorpay_refund_id=?, actioned_by=?, actioned_at=NOW(), completed_at=NOW() WHERE id=?",
                [$amount, $note, $rzpRefId, $actedBy, $rid]
            );
            // Is the order now FULLY refunded (all completed refunds cover the order total)?
            $totRefunded = (float)(db()->fetchOne("SELECT COALESCE(SUM(refund_amount),0) v FROM refund_requests WHERE order_id=? AND status='completed'", [$orderId])['v'] ?? 0);
            $isFull = $totRefunded >= ($orderTotal - 0.01);
            if ($isFull) {
                db()->execute("UPDATE orders SET status='refunded', payment_status='refunded' WHERE id=?", [$orderId]);
                db()->execute("UPDATE payments SET status='refunded' WHERE order_id=? AND status='completed'", [$orderId]);
                // Full restock + aggregate reversal — ONLY on a full refund (atomic with this txn).
                reverseOrderEffects($orderId);
            } else {
                // Partial refund: mark the payment partial. No auto-restock — an amount-based partial
                // refund doesn't tell us which items came back; adjust stock manually if goods returned.
                db()->execute("UPDATE orders SET payment_status='partial' WHERE id=?", [$orderId]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Refund DB error (request #' . $rid . '): ' . $e->getMessage());
            if ($rzpRefId) {
                // Gateway money already went out but the order couldn't be updated. The request
                // stays in 'processing' WITH its razorpay_refund_id — click "Approve" again to
                // re-run ONLY the DB updates (no second gateway refund). Surfaced clearly so it's
                // not silently lost.
                echo json_encode(['success' => false, 'message' =>
                    'Gateway refund succeeded (' . htmlspecialchars($rzpRefId) . ') but saving the order failed. '
                    . 'The request is marked "processing" — re-click Approve to finish (it will NOT refund again).']); exit;
            }
            echo json_encode(['success' => false, 'message' => 'Could not save the refund. No money was sent. Please try again.']); exit;
        }

        $kind = $isFull ? 'Full refund' : 'Partial refund';
        echo json_encode(['success' => true, 'message' => $kind . ' of ₹' . number_format($amount,2) . ($rzpRefId ? ' via gateway (' . $rzpRefId . ')' : ' (manual)')]); exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']); exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]); exit;
    }
}

// --- Listing: filters ---
$statusF = sanitize($_GET['status'] ?? '');
// Only allow known statuses through to the WHERE clause (an unknown value shows everything).
if ($statusF !== '' && !in_array($statusF, ['pending','approved','completed','rejected'], true)) $statusF = '';
$search = sanitize($_GET['search'] ?? '');
$from   = sanitize($_GET['from'] ?? '');
$to     = sanitize($_GET['to'] ?? '');

$where = ["1=1"]; $params = [];
if ($statusF) { $where[] = "rr.status = ?"; $params[] = $statusF; }
if ($search)  { $where[] = "(o.order_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
// Date range on the request date (whole 'to' day included).
if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[] = "rr.requested_at >= ?"; $params[] = $from . ' 00:00:00'; }
if ($to   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $where[] = "rr.requested_at <= ?"; $params[] = $to   . ' 23:59:59'; }
$whereStr = implode(' AND ', $where);

$selectCols = "rr.*, o.order_number, o.total AS order_total, o.payment_method, o.status AS order_status,
               c.name AS customer_name, c.phone AS customer_phone, au.name AS actioned_by_name";
$fromJoin = "FROM refund_requests rr
             JOIN orders o ON o.id = rr.order_id
             JOIN customers c ON c.id = rr.customer_id
             LEFT JOIN admin_users au ON au.id = rr.actioned_by";

// --- CSV export (finance reconciliation) — full filtered set, before any HTML output ---
if (isset($_GET['export'])) {
    $rows = db()->fetchAll("SELECT $selectCols $fromJoin WHERE $whereStr ORDER BY rr.requested_at DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="refunds-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Order #','Customer','Phone','Reason','Amount','Mode','Status','Gateway Refund ID','Admin Note','Actioned By','Requested At','Actioned At','Completed At']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['order_number'], $r['customer_name'], $r['customer_phone'], $r['reason'],
            $r['refund_amount'], ($r['payment_method']==='cod' ? 'COD (manual)' : 'Online'), $r['status'],
            $r['razorpay_refund_id'], $r['admin_note'],
            ($r['actioned_by_name'] ?: ($r['actioned_by'] ?: '')),
            $r['requested_at'], $r['actioned_at'], $r['completed_at'],
        ]);
    }
    fclose($out);
    exit;
}

// --- Listing: paginate ---
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset   = ($page - 1) * $per_page;
$total    = (int)(db()->fetchOne("SELECT COUNT(*) c $fromJoin WHERE $whereStr", $params)['c'] ?? 0);
$pages    = (int)ceil($total / $per_page);
$refunds = db()->fetchAll(
    "SELECT $selectCols $fromJoin WHERE $whereStr
      ORDER BY FIELD(rr.status,'pending','approved','completed','rejected'), rr.requested_at DESC
      LIMIT $per_page OFFSET $offset",
    $params
);
$pendingCount = db()->fetchOne("SELECT COUNT(*) c FROM refund_requests WHERE status='pending'")['c'];

include __DIR__ . '/../includes/header.php';

function refundBadge($s) {
    return ['pending'=>'warning','approved'=>'info','completed'=>'success','rejected'=>'danger'][$s] ?? 'secondary';
}
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Refunds</h1>
        <p>Customer refund &amp; return requests — <?= $pendingCount ?> pending</p>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="exportCsv()"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
</div>

<div class="filter-bar fade-in" style="flex-wrap:wrap;gap:8px;">
    <div class="search-wrapper" style="flex:1;min-width:180px;max-width:280px;">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search order # / customer..." value="<?= htmlspecialchars($search) ?>" onkeydown="if(event.key==='Enter')applyFilters()">
    </div>
    <select class="form-control" id="statusFilter" style="max-width:150px;">
        <option value="">All Status</option>
        <?php foreach(['pending','approved','completed','rejected'] as $sv): ?>
        <option value="<?= $sv ?>" <?= $statusF===$sv?'selected':'' ?>><?= ucfirst($sv) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" class="form-control" id="fromDate" value="<?= htmlspecialchars($from) ?>" style="max-width:150px;" title="From (request date)">
    <input type="date" class="form-control" id="toDate" value="<?= htmlspecialchars($to) ?>" style="max-width:150px;" title="To (request date)">
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="refunds.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<div class="card fade-in">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Order #</th><th>Customer</th><th>Reason</th><th>Amount</th>
                    <th>Mode</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($refunds as $r): ?>
                <tr id="refund-row-<?= $r['id'] ?>">
                    <td><a href="orders.php?view=<?= $r['order_id'] ?>" class="text-gold font-bold"><?= htmlspecialchars($r['order_number']) ?></a></td>
                    <td>
                        <div class="font-bold" style="font-size:0.84rem;"><?= htmlspecialchars($r['customer_name']) ?></div>
                        <div class="text-muted" style="font-size:0.73rem;"><?= htmlspecialchars($r['customer_phone'] ?? '') ?></div>
                    </td>
                    <td style="max-width:260px;"><?= htmlspecialchars($r['reason'] ?? '') ?>
                        <?php if($r['admin_note']): ?><div class="text-muted" style="font-size:0.72rem;">Note: <?= htmlspecialchars($r['admin_note']) ?></div><?php endif; ?>
                    </td>
                    <td class="font-bold"><?= formatCurrency($r['refund_amount']) ?></td>
                    <td><span class="badge badge-secondary"><?= ($r['payment_method']==='cod') ? 'COD (manual)' : 'Online' ?></span></td>
                    <td>
                        <span class="badge badge-<?= refundBadge($r['status']) ?>"><?= ucfirst($r['status']) ?></span>
                        <?php $ts = $r['completed_at'] ?: $r['actioned_at']; if ($r['actioned_by_name'] || $ts): ?>
                        <div class="text-muted" style="font-size:.68rem;margin-top:3px;">
                            <?php if($r['actioned_by_name']): ?><i class="fa-solid fa-user-shield"></i> <?= htmlspecialchars($r['actioned_by_name']) ?><?php endif; ?>
                            <?php if($ts): ?><?= $r['actioned_by_name']?' · ':'' ?><?= date('d M Y', strtotime($ts)) ?><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (in_array($r['status'], ['pending','approved'], true)): ?>
                        <button class="btn btn-gold btn-sm" onclick="openApprove(<?= $r['id'] ?>, <?= (float)$r['refund_amount'] ?>, <?= (float)$r['order_total'] ?>)"><i class="fa-solid fa-check"></i> Approve &amp; Refund</button>
                        <button class="btn btn-ghost btn-sm" onclick="actOnRefund(<?= $r['id'] ?>, 'reject')"><i class="fa-solid fa-xmark"></i> Reject</button>
                        <?php elseif ($r['status'] === 'processing' && !empty($r['razorpay_refund_id'])): ?>
                        <button class="btn btn-warning btn-sm" onclick="actOnRefund(<?= $r['id'] ?>, 'approve')" title="Gateway already refunded — re-run the DB update only"><i class="fa-solid fa-rotate"></i> Finish refund</button>
                        <div class="text-muted" style="font-size:0.7rem;">Gateway ref: <?= htmlspecialchars($r['razorpay_refund_id']) ?></div>
                        <?php else: ?>
                        <span class="text-muted">—<?= $r['razorpay_refund_id'] ? ' ' . htmlspecialchars($r['razorpay_refund_id']) : '' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($refunds)): ?>
                <tr><td colspan="7" style="padding:0;">
                    <div style="text-align:center;padding:48px 20px;">
                        <i class="fa-solid fa-receipt" style="font-size:2.4rem;color:var(--border-active);margin-bottom:12px;"></i>
                        <?php if($statusF || $search || $from || $to): ?>
                        <h3 style="font-size:1rem;margin-bottom:4px;">No refunds match your filters</h3>
                        <p class="text-muted" style="font-size:.85rem;margin-bottom:14px;">Try different filters — or reset to see all requests.</p>
                        <a href="refunds.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset filters</a>
                        <?php else: ?>
                        <h3 style="font-size:1rem;margin-bottom:4px;">No refund requests yet</h3>
                        <p class="text-muted" style="font-size:.85rem;">Customer refund &amp; return requests will appear here.</p>
                        <?php endif; ?>
                    </div>
                </td></tr>
                <?php endif; ?>
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

<!-- Reject Refund Modal (styled replacement for the native prompt) -->
<div class="modal-overlay" id="rejectModal" style="display:none;" onclick="if(event.target===this)closeModal('rejectModal')">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-head"><h2>Reject Refund</h2><button class="close-btn" onclick="closeModal('rejectModal')"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body">
            <input type="hidden" id="reject_id">
            <div class="form-group">
                <label class="form-label">Reason for rejection <small class="text-muted">(shown to the customer)</small></label>
                <textarea class="form-control" id="reject_note" rows="3" placeholder="e.g. Return window has passed"></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closeModal('rejectModal')">Cancel</button>
            <button class="btn btn-gold" style="background:var(--danger);" onclick="confirmReject()"><i class="fa-solid fa-xmark"></i> Reject Refund</button>
        </div>
    </div>
</div>

<!-- Approve / Refund Modal — supports partial (amount-editable) refunds -->
<div class="modal-overlay" id="approveModal" style="display:none;" onclick="if(event.target===this)closeModal('approveModal')">
    <div class="modal-box" style="max-width:460px;">
        <div class="modal-head"><h2>Approve &amp; Refund</h2><button class="close-btn" onclick="closeModal('approveModal')"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body">
            <input type="hidden" id="approve_id">
            <div class="form-group">
                <label class="form-label">Refund Amount (₹) *</label>
                <input type="number" step="0.01" min="0" class="form-control" id="approve_amount">
                <small class="text-muted" style="font-size:.72rem;" id="approve_hint"></small>
            </div>
            <div class="form-group">
                <label class="form-label">Admin note <small class="text-muted">(optional, internal)</small></label>
                <input type="text" class="form-control" id="approve_note" placeholder="e.g. Item returned — partial restock done">
            </div>
            <div style="background:rgba(231,76,60,.08);border:1px solid rgba(231,76,60,.3);color:var(--danger);padding:9px 12px;border-radius:8px;font-size:.78rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> Online orders fire a <b>REAL Razorpay refund</b> (cannot be undone). Refunding <b>less than the order total = partial refund</b> — order stays open, no auto-restock.
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closeModal('approveModal')">Cancel</button>
            <button class="btn btn-gold" onclick="confirmApprove()"><i class="fa-solid fa-check"></i> Process Refund</button>
        </div>
    </div>
</div>

<script>
function buildRefundQuery(extra){
    const p=new URLSearchParams();
    const s=document.getElementById('searchInput').value; if(s)p.set('search',s);
    const st=document.getElementById('statusFilter').value; if(st)p.set('status',st);
    const f=document.getElementById('fromDate').value; if(f)p.set('from',f);
    const t=document.getElementById('toDate').value; if(t)p.set('to',t);
    if(extra)Object.entries(extra).forEach(([k,v])=>p.set(k,v));
    return p.toString();
}
function applyFilters(){window.location.href='refunds.php?'+buildRefundQuery();}
function exportCsv(){window.location.href='refunds.php?'+buildRefundQuery({export:'csv'});}
function goPage(p){const q=new URLSearchParams(window.location.search);q.set('page',p);window.location.href='refunds.php?'+q.toString();}
function actOnRefund(id, action) {
    // Reject needs a reason — open the styled modal. (Approve uses openApprove.)
    document.getElementById('reject_id').value = id;
    document.getElementById('reject_note').value = '';
    openModal('rejectModal');
}
function openApprove(id, requested, orderTotal) {
    document.getElementById('approve_id').value = id;
    const amt = document.getElementById('approve_amount');
    amt.value = requested;
    amt.max = orderTotal;
    document.getElementById('approve_note').value = '';
    document.getElementById('approve_hint').textContent =
        'Order total ₹' + Number(orderTotal).toLocaleString('en-IN') + '. Edit to refund a partial amount.';
    openModal('approveModal');
}
function confirmApprove() {
    const id = document.getElementById('approve_id').value;
    const amount = parseFloat(document.getElementById('approve_amount').value);
    const note = document.getElementById('approve_note').value.trim();
    if (!(amount > 0)) { showToast('Enter a refund amount greater than 0', 'warning'); return; }
    closeModal('approveModal');
    submitRefund(id, 'approve', note, amount);
}
function confirmReject() {
    const id = document.getElementById('reject_id').value;
    const note = document.getElementById('reject_note').value.trim();
    closeModal('rejectModal');
    submitRefund(id, 'reject', note);
}
async function submitRefund(id, action, note, amount) {
    const body = { action, id, note };
    if (amount !== undefined) body.amount = amount;
    const res = await fetch('refunds.php', {
        method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify(body)
    });
    const r = await res.json();
    showToast(r.message || (r.success ? 'Done' : 'Failed'), r.success ? 'success' : 'danger');
    if (r.success) setTimeout(() => location.reload(), 900);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
