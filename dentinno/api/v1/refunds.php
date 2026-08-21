<?php
// Customer refund/return requests (storefront). Auth required (Bearer token).
//
// GET  /api/v1/refunds.php            -> { refunds: [...] }   list this customer's requests
// GET  /api/v1/refunds.php?orderId=.. -> { refund } | { refund:null }  request for one order
// POST /api/v1/refunds.php  {orderId, reason}
//      -> creates a 'pending' refund request for an order the customer owns. The actual
//         refund is processed by admin (pages/refunds.php), which calls the gateway.
require_once __DIR__ . '/_bootstrap.php';

$cust   = requireCustomer();
$db     = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Shape a refund row for the client (joins order number + total for display).
function mapRefund(array $r): array {
    return [
        'id'          => (int)$r['id'],
        'orderId'     => $r['order_number'] ?? null,
        'orderTotal'  => isset($r['order_total']) ? (float)$r['order_total'] : null,
        'reason'      => $r['reason'],
        'status'      => $r['status'],
        'amount'      => (float)$r['refund_amount'],
        'adminNote'   => $r['admin_note'],
        'requestedAt' => $r['requested_at'],
        'completedAt' => $r['completed_at'],
    ];
}

if ($method === 'GET') {
    $orderNumber = qstr('orderId');
    if ($orderNumber !== '') {
        $r = $db->fetchOne(
            "SELECT rr.*, o.order_number, o.total AS order_total
               FROM refund_requests rr JOIN orders o ON o.id = rr.order_id
              WHERE o.order_number = ? AND rr.customer_id = ?",
            [$orderNumber, $cust['id']]
        );
        jsonOut(['success' => true, 'refund' => $r ? mapRefund($r) : null]);
    }
    $rows = $db->fetchAll(
        "SELECT rr.*, o.order_number, o.total AS order_total
           FROM refund_requests rr JOIN orders o ON o.id = rr.order_id
          WHERE rr.customer_id = ? ORDER BY rr.requested_at DESC",
        [$cust['id']]
    );
    jsonOut(['success' => true, 'refunds' => array_map('mapRefund', $rows)]);
}

if ($method !== 'POST') jsonErr('Method not allowed', 405);

// --- create a refund request ---
$body        = jsonBody();
$orderNumber = trim((string)($body['orderId'] ?? ''));
$reason      = trim((string)($body['reason'] ?? ''));
if ($orderNumber === '') jsonErr('orderId is required', 422);
if ($reason === '')      jsonErr('Please tell us why you want a refund.', 422);

// Order must exist and belong to this customer.
$order = $db->fetchOne(
    "SELECT * FROM orders WHERE order_number = ? AND customer_id = ?",
    [$orderNumber, $cust['id']]
);
if (!$order) jsonErr('Order not found', 404);

// A refund/return needs real money collected AND a non-terminal order. Mirror the storefront
// gate (OrderDetailPage refundEligible) on the SERVER so a crafted API call can't create a
// refund for an order that was never paid or is already dead/terminal.
//   * cancelled / rejected / returned / refunded -> never fulfilled or already closed
//   * payment must be collected: online 'paid', or COD that reached 'delivered'
if (in_array($order['status'], ['cancelled', 'rejected', 'returned', 'refunded'], true)) {
    jsonErr('This order is ' . $order['status'] . ' and cannot be refunded.', 409);
}
$paymentCollected = ($order['payment_status'] === 'paid')
    || ((string)$order['payment_method'] === 'cod' && $order['status'] === 'delivered');
if (!$paymentCollected) {
    jsonErr('This order has no completed payment to refund.', 409);
}

// One active request per order.
$existing = $db->fetchOne("SELECT * FROM refund_requests WHERE order_id = ?", [$order['id']]);
if ($existing) {
    if (in_array($existing['status'], ['pending', 'approved'], true)) {
        jsonErr('A refund request for this order is already ' . $existing['status'] . '.', 409);
    }
    if ($existing['status'] === 'completed') {
        jsonErr('This order has already been refunded.', 409);
    }
    // A previously rejected request can be re-raised: reset it.
    $db->execute(
        "UPDATE refund_requests
            SET reason = ?, status = 'pending', refund_amount = ?, admin_note = NULL,
                razorpay_refund_id = NULL, requested_at = NOW(), actioned_at = NULL, completed_at = NULL
          WHERE id = ?",
        [$reason, (float)$order['total'], $existing['id']]
    );
    jsonOut(['success' => true, 'refundId' => (int)$existing['id'], 'status' => 'pending'], 201);
}

$id = $db->insert(
    "INSERT INTO refund_requests (order_id, customer_id, reason, status, refund_amount)
     VALUES (?,?,?, 'pending', ?)",
    [$order['id'], $cust['id'], $reason, (float)$order['total']]
);

// Notify admins of the new refund request.
pushNotification('payment', 'Refund request — ' . ($order['order_number'] ?? ('#'.$order['id'])), $cust['name'] . ' requested a refund of ₹' . number_format((float)$order['total'], 2), '/pages/refunds.php?status=pending');

jsonOut(['success' => true, 'refundId' => (int)$id, 'status' => 'pending'], 201);
