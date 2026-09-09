-- Abandoned online orders cancelled by cleanup_abandoned_orders.php used to keep
-- payment_status='pending', so the admin saw a CANCELLED order still showing payment "Pending"
-- (looks like a live order awaiting gateway capture). The cleanup now sets payment_status to
-- 'unpaid' on cancel; this backfills the orders cancelled before that change.
--
-- 'unpaid' = payment never completed, which is the truth for an abandoned order. A genuinely
-- paid-then-cancelled order keeps 'paid' (the refund flow owns that money), so it is excluded.
-- Idempotent: matches nothing on re-run.


UPDATE orders
   SET payment_status = 'unpaid'
 WHERE status = 'cancelled'
   AND payment_status = 'pending'
   AND payment_method <> 'cod';
