<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
$page_title = 'Refunds';
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
    $data   = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    $rid    = (int)($data['id'] ?? 0);
    $note   = trim((string)($data['note'] ?? ''));

    $rr = db()->fetchOne(
        "SELECT rr.*, o.order_number, o.payment_method, o.total
           FROM refund_requests rr JOIN orders o ON o.id = rr.order_id WHERE rr.id = ?",
        [$rid]
    );
    if (!$rr) { echo json_encode(['success' => false, 'message' => 'Request not found']); exit; }
    if (!in_array($rr['status'], ['pending', 'approved'], true)) {
        echo json_encode(['success' => false, 'message' => 'Request already ' . $rr['status']]); exit;
    }

    if ($action === 'reject') {
        db()->execute(
            "UPDATE refund_requests SET status='rejected', admin_note=?, actioned_at=NOW() WHERE id=?",
            [$note, $rid]
        );
        echo json_encode(['success' => true, 'message' => 'Refund rejected']); exit;
    }

    if ($action === 'approve') {
        $orderId   = (int)$rr['order_id'];
        $amount    = (float)$rr['refund_amount'];
        $rzpRefId  = null;

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
            if ($amount <= 0 || $amount > $captured + 0.001) {
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

        $pdo = db()->getConnection();
        $pdo->beginTransaction();
        try {
            db()->execute(
                "UPDATE refund_requests SET status='completed', admin_note=?, razorpay_refund_id=?, actioned_at=NOW(), completed_at=NOW() WHERE id=?",
                [$note, $rzpRefId, $rid]
            );
            db()->execute("UPDATE orders SET status='refunded', payment_status='refunded' WHERE id=?", [$orderId]);
            db()->execute("UPDATE payments SET status='refunded' WHERE order_id=? AND status='completed'", [$orderId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]); exit;
        }

        echo json_encode(['success' => true, 'message' => $rzpRefId ? 'Refunded via gateway (' . $rzpRefId . ')' : 'Marked refunded (manual)']); exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']); exit;
}

// --- Listing ---
$statusF = sanitize($_GET['status'] ?? '');
$where = $statusF ? "WHERE rr.status = ?" : "";
$params = $statusF ? [$statusF] : [];
$refunds = db()->fetchAll(
    "SELECT rr.*, o.order_number, o.total AS order_total, o.payment_method, o.status AS order_status,
            c.name AS customer_name, c.phone AS customer_phone
       FROM refund_requests rr
       JOIN orders o ON o.id = rr.order_id
       JOIN customers c ON c.id = rr.customer_id
       $where
      ORDER BY FIELD(rr.status,'pending','approved','completed','rejected'), rr.requested_at DESC",
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
    <div style="display:flex;gap:10px;">
        <a href="refunds.php" class="btn btn-outline btn-sm">All</a>
        <a href="?status=pending" class="btn btn-outline btn-sm">Pending (<?= $pendingCount ?>)</a>
    </div>
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
                        <div class="text-muted" style="font-size:0.73rem;"><?= htmlspecialchars($r['customer_phone']) ?></div>
                    </td>
                    <td style="max-width:260px;"><?= htmlspecialchars($r['reason']) ?>
                        <?php if($r['admin_note']): ?><div class="text-muted" style="font-size:0.72rem;">Note: <?= htmlspecialchars($r['admin_note']) ?></div><?php endif; ?>
                    </td>
                    <td class="font-bold"><?= formatCurrency($r['refund_amount']) ?></td>
                    <td><span class="badge badge-secondary"><?= ($r['payment_method']==='cod') ? 'COD (manual)' : 'Online' ?></span></td>
                    <td><span class="badge badge-<?= refundBadge($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
                    <td>
                        <?php if (in_array($r['status'], ['pending','approved'], true)): ?>
                        <button class="btn btn-gold btn-sm" onclick="actOnRefund(<?= $r['id'] ?>, 'approve')"><i class="fa-solid fa-check"></i> Approve &amp; Refund</button>
                        <button class="btn btn-ghost btn-sm" onclick="actOnRefund(<?= $r['id'] ?>, 'reject')"><i class="fa-solid fa-xmark"></i> Reject</button>
                        <?php else: ?>
                        <span class="text-muted">—<?= $r['razorpay_refund_id'] ? ' ' . htmlspecialchars($r['razorpay_refund_id']) : '' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($refunds)): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:30px;">No refund requests.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function actOnRefund(id, action) {
    let note = '';
    if (action === 'reject') { note = prompt('Reason for rejection (shown to customer):') || ''; }
    else if (!confirm('Approve and refund this order? For online orders this triggers a real Razorpay refund.')) { return; }
    const res = await fetch('refunds.php', {
        method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({ action, id, note })
    });
    const r = await res.json();
    showToast(r.message || (r.success ? 'Done' : 'Failed'), r.success ? 'success' : 'error');
    if (r.success) setTimeout(() => location.reload(), 900);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
