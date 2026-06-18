<?php
// Abandoned-order cleanup (CLI-only). Online orders are created as status=pending /
// payment_status=pending and DECREMENT stock at creation. If the customer never completes
// payment (closed the Razorpay sheet, card failed), the order sits pending forever and its
// stock stays locked + total_sales/LTV/coupon stay inflated.
//
// This script cancels such orders once they're older than a grace window and reverses their
// effects (restock + counter decrement) via the shared, idempotent reverseOrderEffects().
//
// Run from cron, e.g. every 30 min:
//   */30 * * * * php /path/to/dentinno/cleanup_abandoned_orders.php
//
// Optional first arg = grace window in MINUTES (default 120). COD orders are left alone
// (they're legitimately unpaid until delivery).

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/order_effects.php';

// Default grace 30 min — the standard online-payment retry window (Amazon/Flipkart-style).
// A paid-via-webhook order is already 'confirmed' by then, so only genuinely-abandoned
// pending orders remain to cancel. Override with the first CLI arg.
$graceMinutes = isset($argv[1]) ? max(5, (int)$argv[1]) : 30;
$db = db();

// Online, never-paid, still-pending orders past the grace window. payment_method<>'cod'
// excludes Cash-on-Delivery; payment_status='pending' is the online-unpaid marker
// (COD orders use 'unpaid'); status='pending' means it never progressed.
$rows = $db->fetchAll(
    "SELECT id, order_number FROM orders
      WHERE status = 'pending'
        AND payment_status = 'pending'
        AND payment_method <> 'cod'
        AND effects_reversed = 0
        AND created_at < (NOW() - INTERVAL ? MINUTE)",
    [$graceMinutes]
);

echo "Abandoned-order cleanup — grace {$graceMinutes}m — " . count($rows) . " candidate(s)\n";

$done = 0;
foreach ($rows as $o) {
    $oid = (int)$o['id'];
    $pdo = $db->getConnection();
    $pdo->beginTransaction();
    try {
        // Cancel AND clear the 'pending' payment marker -> 'unpaid'. Otherwise the admin sees
        // a cancelled order still showing payment "Pending" (looks like a live order awaiting
        // capture). 'unpaid' = payment never completed, which is the truth for an abandoned order.
        $db->execute("UPDATE orders SET status = 'cancelled', payment_status = 'unpaid' WHERE id = ? AND status = 'pending'", [$oid]);
        reverseOrderEffects($oid);   // joins this open transaction
        $pdo->commit();
        echo "  cancelled + restocked {$o['order_number']}\n";
        $done++;
    } catch (Throwable $t) {
        $pdo->rollBack();
        echo "  ERROR on {$o['order_number']}: " . $t->getMessage() . "\n";
    }
}

echo "Done. Cancelled {$done} abandoned order(s).\n";
