<?php
// Customer orders (storefront). Auth required (Bearer token).
// POST /api/v1/orders.php        {items:[{id,name,price,qty,variant}], address, paymentMethod, subtotal, discount?, shipping?}
//                                 -> creates order + items, returns {order}
// GET  /api/v1/orders.php        -> list this customer's orders
// GET  /api/v1/orders.php?id=ORD-..  -> single order with items
require_once __DIR__ . '/_bootstrap.php';

$db  = db();
$cust = requireCustomer();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Map an order row -> React shape
function mapOrder(array $o, array $items): array {
    return [
        'orderId'       => $o['order_number'],
        'dbId'          => (int)$o['id'],
        'status'        => $o['status'],
        'paymentStatus' => $o['payment_status'],
        'paymentMethod' => $o['payment_method'],
        'subtotal'      => (float)$o['subtotal'],
        'discount'      => (float)$o['discount'],
        'shipping'      => (float)$o['shipping_charge'],
        'total'         => (float)$o['total'],
        'address'       => jcol($o['shipping_address'] ?? null, null),
        'createdAt'     => $o['created_at'],
        'items'         => array_map(fn($it) => [
            'id'      => $it['product_slug'] ?: (string)$it['product_id'],
            'name'    => $it['product_name'],
            'price'   => (float)$it['price'],
            'qty'     => (int)$it['quantity'],
            'variant' => $it['variant'],
            'total'   => (float)$it['total'],
        ], $items),
    ];
}

// --- GET list / single ---
if ($method === 'GET') {
    $oid = qstr('id');
    if ($oid !== '') {
        $o = $db->fetchOne("SELECT * FROM orders WHERE order_number=? AND customer_id=?", [$oid, $cust['id']]);
        if (!$o) jsonErr('Order not found', 404);
        $items = $db->fetchAll("SELECT * FROM order_items WHERE order_id=?", [$o['id']]);
        jsonOut(['success' => true, 'order' => mapOrder($o, $items)]);
    }
    $rows = $db->fetchAll("SELECT * FROM orders WHERE customer_id=? ORDER BY created_at DESC", [$cust['id']]);
    $orders = array_map(function ($o) use ($db) {
        $items = $db->fetchAll("SELECT * FROM order_items WHERE order_id=?", [$o['id']]);
        return mapOrder($o, $items);
    }, $rows);
    jsonOut(['success' => true, 'count' => count($orders), 'orders' => $orders]);
}

// --- POST create order ---
if ($method !== 'POST') jsonErr('Method not allowed', 405);

$body  = jsonBody();
$items = $body['items'] ?? [];
if (!is_array($items) || count($items) === 0) jsonErr('Cart is empty', 422);

$subtotal = 0.0;
foreach ($items as $it) {
    $subtotal += ((float)($it['price'] ?? 0)) * ((int)($it['qty'] ?? 1));
}
$discount = (float)($body['discount'] ?? 0);
$shipping = (float)($body['shipping'] ?? 0);
$total    = max(0, $subtotal - $discount + $shipping);
$payMethod = (string)($body['paymentMethod'] ?? 'cod');
$address   = $body['address'] ?? null;

$orderNumber = 'SDI-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

$pdo = $db->getConnection();
$pdo->beginTransaction();
try {
    $orderId = $db->insert(
        "INSERT INTO orders
         (order_number, customer_id, status, payment_status, payment_method,
          subtotal, discount, shipping_charge, total, shipping_address)
         VALUES (?,?, 'pending', ?, ?, ?,?,?,?,?)",
        [
            $orderNumber, $cust['id'],
            $payMethod === 'cod' ? 'unpaid' : 'paid',
            $payMethod, $subtotal, $discount, $shipping, $total,
            $address ? json_encode($address) : null,
        ]
    );

    $insItem = $pdo->prepare(
        "INSERT INTO order_items (order_id, product_id, product_slug, product_name, variant, quantity, price, total)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    foreach ($items as $it) {
        $slug = (string)($it['id'] ?? '');
        // resolve slug -> products.id (null if combo / not a catalog product)
        $pid = null;
        if ($slug !== '') {
            $row = $db->fetchOne("SELECT id FROM products WHERE slug=?", [$slug]);
            $pid = $row ? (int)$row['id'] : null;
        }
        $qty   = (int)($it['qty'] ?? 1);
        $price = (float)($it['price'] ?? 0);
        $insItem->execute([
            $orderId, $pid, $slug ?: null, $it['name'] ?? '', $it['variant'] ?? null,
            $qty, $price, $price * $qty,
        ]);
        if ($pid) {
            $db->execute("UPDATE products SET total_sales = total_sales + ? WHERE id=?", [$qty, $pid]);
        }
    }

    // bump customer aggregates
    $db->execute(
        "UPDATE customers SET total_orders = total_orders + 1, total_spent = total_spent + ? WHERE id=?",
        [$total, $cust['id']]
    );

    $pdo->commit();
} catch (Throwable $t) {
    $pdo->rollBack();
    jsonErr('Order failed: ' . $t->getMessage(), 500);
}

$o = $db->fetchOne("SELECT * FROM orders WHERE id=?", [$orderId]);
$oi = $db->fetchAll("SELECT * FROM order_items WHERE order_id=?", [$orderId]);
jsonOut(['success' => true, 'order' => mapOrder($o, $oi)], 201);
