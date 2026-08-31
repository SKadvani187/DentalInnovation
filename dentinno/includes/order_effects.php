<?php
// Order side-effect reversal — shared by the admin order/refund pages.
//
// Order creation (api/v1/orders.php) applies: stock-, total_sales+, total_orders+,
// total_spent+, coupons.uses_count+. When an order reaches a TERMINAL state
// (cancelled / rejected / returned / refunded) those effects must be undone exactly
// once. reverseOrderEffects() is the single authoritative place that does it.
//
// Guard: an atomic `effects_reversed 0 -> 1` claim means only the first caller runs
// the compensation; every later/concurrent call is a no-op. Safe to call from any
// terminal transition without tracking which one fired first.

if (!function_exists('reverseOrderEffects')) {

/**
 * Reverse the inventory + aggregate effects of an order, exactly once.
 *
 * @return bool true if this call performed the reversal, false if it was already done
 *              (or the order doesn't exist). Caller can ignore the return.
 */
function reverseOrderEffects(int $orderId): bool {
    $db  = db();
    $pdo = $db->getConnection();

    // Atomic one-shot claim. If 0 rows changed, another path already reversed it.
    $claimed = $db->execute(
        "UPDATE orders SET effects_reversed = 1 WHERE id = ? AND effects_reversed = 0",
        [$orderId]
    );
    if ($claimed < 1) return false;

    // Pull the order (for coupon_id + customer aggregates) and its line items.
    $order = $db->fetchOne("SELECT id, customer_id, total, coupon_id FROM orders WHERE id = ?", [$orderId]);
    if (!$order) return false;
    $items = $db->fetchAll(
        "SELECT product_id, product_slug, variant, quantity FROM order_items WHERE order_id = ?",
        [$orderId]
    );

    $ownTxn = !$pdo->inTransaction();
    if ($ownTxn) $pdo->beginTransaction();
    try {
        $restockProd = $pdo->prepare(
            "UPDATE products SET stock = stock + ?, total_sales = GREATEST(0, total_sales - ?) WHERE id = ?"
        );
        $restockCombo = $pdo->prepare("UPDATE combos SET stock = stock + ? WHERE slug = ?");
        // Mirror of the per-variant decrement in orders.php: put the units back into the exact
        // option that was sold, matched by the label snapshotted on the order line.
        $restockVariant = $pdo->prepare(
            "UPDATE products
                SET variants = JSON_SET(variants, CONCAT('$[', ?, '].qty'),
                        CAST(JSON_UNQUOTE(JSON_EXTRACT(variants, CONCAT('$[', ?, '].qty'))) AS SIGNED) + ?)
              WHERE id = ?"
        );

        foreach ($items as $it) {
            $qty = (int)$it['quantity'];
            if ($qty <= 0) continue;
            if (!empty($it['product_id'])) {
                // Gift lines (price 0) still consumed stock + bumped total_sales at creation,
                // so they are restocked here too — symmetric with orders.php.
                $restockProd->execute([$qty, $qty, (int)$it['product_id']]);
                // Tracked variant: give the units back to that option as well. A label that no
                // longer exists (variant since renamed/removed) is skipped — products.stock above
                // has already absorbed the units, so nothing is lost.
                if (!empty($it['variant'])) {
                    $prow = $db->fetchOne("SELECT variants FROM products WHERE id = ?", [(int)$it['product_id']]);
                    $list = ($prow && !empty($prow['variants'])) ? json_decode($prow['variants'], true) : null;
                    if (is_array($list)) {
                        foreach ($list as $i => $v) {
                            if (is_array($v) && (string)($v['label'] ?? '') === (string)$it['variant']
                                && isset($v['qty']) && $v['qty'] !== null) {
                                $restockVariant->execute([(int)$i, (int)$i, $qty, (int)$it['product_id']]);
                                break;
                            }
                        }
                    }
                }
                // Ledger: stock back in from a refund (best-effort).
                recordStockMovement((int)$it['product_id'], $qty, 'refund', null, $order['order_number'] ?? null);
            } elseif (!empty($it['product_slug'])) {
                // Non-catalog line backed by a combo (product_id NULL): restock the combo.
                $restockCombo->execute([$qty, $it['product_slug']]);
            }
        }

        // Reverse the customer aggregates (floored so they can't go negative on bad data).
        $db->execute(
            "UPDATE customers
                SET total_orders = GREATEST(0, total_orders - 1),
                    total_spent  = GREATEST(0, total_spent - ?)
              WHERE id = ?",
            [(float)$order['total'], (int)$order['customer_id']]
        );

        // Reverse coupon usage if one was applied.
        if (!empty($order['coupon_id'])) {
            $db->execute(
                "UPDATE coupons SET uses_count = GREATEST(0, uses_count - 1) WHERE id = ?",
                [(int)$order['coupon_id']]
            );
            // Free the customer's per-customer redemption slot so they can use it again.
            $db->execute("DELETE FROM coupon_redemptions WHERE order_id = ?", [(int)$order['id']]);
        }

        if ($ownTxn) $pdo->commit();
    } catch (Throwable $t) {
        if ($ownTxn) $pdo->rollBack();
        // Re-open the guard so a later retry can attempt the reversal again.
        $db->execute("UPDATE orders SET effects_reversed = 0 WHERE id = ?", [$orderId]);
        throw $t;
    }
    return true;
}

}
