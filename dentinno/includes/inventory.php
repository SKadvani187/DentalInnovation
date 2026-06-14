<?php
// Inventory ledger helper. Call AFTER the products.stock UPDATE so $balanceAfter is the new value.
// Best-effort by design — a logging failure must NEVER break the order/refund/edit that triggered it.
if (!function_exists('recordStockMovement')) {
    function recordStockMovement(int $productId, int $delta, string $type, ?string $reason = null, ?string $reference = null, ?int $adminId = null, ?int $balanceAfter = null): void {
        if ($productId <= 0 || $delta === 0) return;
        try {
            if ($balanceAfter === null) {
                $balanceAfter = (int)(db()->fetchOne("SELECT stock FROM products WHERE id=?", [$productId])['stock'] ?? 0);
            }
            db()->insert(
                "INSERT INTO inventory_movements (product_id,delta,type,reason,reference,balance_after,admin_id) VALUES (?,?,?,?,?,?,?)",
                [$productId, $delta, $type, $reason, $reference, $balanceAfter, $adminId]
            );
        } catch (Throwable $e) { /* never break the caller */ }
    }
}
