<?php
// Customer orders (storefront). Auth required (Bearer token).
// POST /api/v1/orders.php        {items:[{id,name,price,qty,variant,type,offerId}], address, paymentMethod, couponCode?}
//                                 -> creates order + items (server-priced), returns {order}
// GET  /api/v1/orders.php        -> list this customer's orders
// GET  /api/v1/orders.php?id=ORD-..  -> single order with items
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_pricing.php';   // server-authoritative coupon + order totals

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
        'tax'           => (float)($o['tax'] ?? 0),
        'total'         => (float)$o['total'],
        'address'       => jcol($o['shipping_address'] ?? null, null),
        'createdAt'     => $o['created_at'],
        // Shipment tracking — set by admin once dispatched, so the customer can follow the order.
        'trackingId'    => $o['tracking_number'] ?? null,
        'courier'       => $o['courier_name'] ?? null,
        'shippedAt'     => $o['shipped_at'] ?? null,
        'deliveredAt'   => $o['delivered_at'] ?? null,
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

// "now" in the app timezone (config sets Asia/Kolkata) for offer-expiry checks.
$now = date('Y-m-d H:i:s');

// ---- Pass 1: resolve AUTHORITATIVE prices and validate. Client prices are ignored. ----
// Cart payload lines carry an optional `type`: "product" (default), "offer", "gift".
//   { id, name, qty, variant, type:"product" }
//   { id, name, qty, variant, type:"offer", offerId }
//   { id, name, qty, variant, type:"gift",  offerId, parentId }

// Index the offer lines present in the cart so gift lines can be validated against them,
// and so an offer is loaded/expiry-checked exactly once.
$offerLines = [];   // offerSlug => ['row'=>offerRow, 'qty'=>int]
foreach ($items as $it) {
    if (($it['type'] ?? 'product') !== 'offer') continue;
    $oslug = (string)($it['offerId'] ?? $it['id'] ?? '');
    if ($oslug === '') jsonErr('Invalid offer in cart', 422);
    $orow = $db->fetchOne("SELECT * FROM offers WHERE slug=? AND is_active=1", [$oslug]);
    if (!$orow) jsonErr('An offer in your cart is no longer available', 409);
    if (!empty($orow['valid_till']) && strtotime($orow['valid_till']) < strtotime($now)) jsonErr('An offer in your cart has expired', 409);
    $offerLines[$oslug] = ['row' => $orow, 'qty' => max(1, (int)($it['qty'] ?? 1))];
}

$resolved  = [];   // normalized order_items rows to write
$stockNeed = [];   // products.id => total qty needed (friendly pre-check)

foreach ($items as $it) {
    $type = $it['type'] ?? 'product';
    $qty  = max(1, (int)($it['qty'] ?? 1));
    $slug = (string)($it['id'] ?? '');

    if ($type === 'offer') {
        $oslug = (string)($it['offerId'] ?? $it['id'] ?? '');
        $orow  = $offerLines[$oslug]['row'] ?? null;
        if (!$orow) jsonErr('Invalid offer in cart', 422);
        $pid = (int)($orow['product_id'] ?? 0);
        if ($pid <= 0) jsonErr('An offer in your cart is misconfigured', 409);
        $prod = $db->fetchOne("SELECT id, slug, name, stock, is_active FROM products WHERE id=?", [$pid]);
        if (!$prod || !$prod['is_active']) jsonErr('A product in an offer is unavailable', 409);
        if ($slug !== '' && $slug !== $prod['slug']) jsonErr('Offer / product mismatch', 409);  // anti-tamper
        $resolved[] = ['product_id'=>$pid,'slug'=>$prod['slug'],'name'=>$prod['name'],'variant'=>$it['variant']??null,
                       'qty'=>$qty,'price'=>(float)$orow['special_price'],'line_type'=>'offer','offer_id'=>(int)$orow['id']];
        $stockNeed[$pid] = ($stockNeed[$pid] ?? 0) + $qty;
    }
    elseif ($type === 'gift' && !empty($it['autoGift'])) {
        // Per-product free gift (product_gifts). Verified server-side: the gift product must
        // exist + be active, AND its parent product must be in this cart AND actually grant
        // it (anti-tamper). Price is forced to 0 — never trusted from the client.
        $gprod = $db->fetchOne("SELECT id, slug, name, stock, is_active FROM products WHERE slug=?", [$slug]);
        if (!$gprod || !$gprod['is_active']) jsonErr('A free gift in your cart is unavailable', 409);
        $parentSlug = (string)($it['parentSlug'] ?? '');
        $valid = $db->fetchOne(
            "SELECT 1 FROM product_gifts pg
             JOIN products pp ON pp.id=pg.product_id
             JOIN products gp ON gp.id=pg.gift_product_id
             WHERE pp.slug=? AND gp.slug=?",
            [$parentSlug, $slug]
        );
        if (!$valid) jsonErr('An invalid free gift was found in your cart', 409);
        // Ensure the parent is genuinely in the cart (else the gift shouldn't exist).
        $parentInCart = false;
        foreach ($items as $chk) { if (($chk['type'] ?? 'product') === 'product' && (string)($chk['id'] ?? '') === $parentSlug) { $parentInCart = true; break; } }
        if (!$parentInCart) jsonErr('A free gift has no qualifying product in the cart', 409);
        $gpid = (int)$gprod['id'];
        $resolved[] = ['product_id'=>$gpid,'slug'=>$gprod['slug'],'name'=>$gprod['name'],'variant'=>null,
                       'qty'=>$qty,'price'=>0.0,'line_type'=>'gift','offer_id'=>null];
        $stockNeed[$gpid] = ($stockNeed[$gpid] ?? 0) + $qty;
    }
    elseif ($type === 'gift') {
        $oslug = (string)($it['offerId'] ?? '');
        if (!isset($offerLines[$oslug])) jsonErr('A free gift in your cart has no matching offer', 409);
        $orow      = $offerLines[$oslug]['row'];
        $parentQty = $offerLines[$oslug]['qty'];
        // The gift MUST belong to this offer (by product slug, else by name snapshot).
        $gift = null;
        if ($slug !== '') {
            $gift = $db->fetchOne(
                "SELECT oi.*, p.slug AS pslug FROM offer_items oi LEFT JOIN products p ON p.id=oi.product_id
                 WHERE oi.offer_id=? AND p.slug=?", [(int)$orow['id'], $slug]);
        }
        if (!$gift) {
            $gift = $db->fetchOne(
                "SELECT oi.*, p.slug AS pslug FROM offer_items oi LEFT JOIN products p ON p.id=oi.product_id
                 WHERE oi.offer_id=? AND oi.name=?", [(int)$orow['id'], (string)($it['name'] ?? '')]);
        }
        if (!$gift) jsonErr('An invalid free gift was found in your cart', 409);
        $giftQty = max(1, (int)$gift['qty']) * $parentQty;   // gifts scale 1:1 with the offer line
        $gpid    = $gift['product_id'] !== null ? (int)$gift['product_id'] : null;
        // Free gifts are always ₹0, never trusted from the client.
        $resolved[] = ['product_id'=>$gpid,'slug'=>($gift['pslug'] ?? ($slug ?: null)),'name'=>$gift['name'],
                       'variant'=>$gift['variant'],'qty'=>$giftQty,'price'=>0.0,'line_type'=>'gift','offer_id'=>(int)$orow['id']];
        if ($gpid) $stockNeed[$gpid] = ($stockNeed[$gpid] ?? 0) + $giftQty;
    }
    else {  // normal catalog product (or a combo from its own table)
        if ($slug === '') jsonErr('Invalid item in cart', 422);
        $prod = $db->fetchOne("SELECT id, slug, name, price, discount_price, stock, is_active FROM products WHERE slug=?", [$slug]);
        if ($prod) {
            if (!$prod['is_active']) jsonErr('A product in your cart is unavailable', 409);
            $price = $prod['discount_price'] !== null ? (float)$prod['discount_price'] : (float)$prod['price'];
            $pid   = (int)$prod['id'];
            $resolved[] = ['product_id'=>$pid,'slug'=>$prod['slug'],'name'=>$prod['name'],'variant'=>$it['variant']??null,
                           'qty'=>$qty,'price'=>$price,'line_type'=>'product','offer_id'=>null];
            $stockNeed[$pid] = ($stockNeed[$pid] ?? 0) + $qty;
        } else {
            // Not a catalog product — resolve a combo authoritatively (combos have their own
            // stock; per-combo stock enforcement is out of scope for this change).
            $combo = $db->fetchOne("SELECT id, slug, name, price, is_active FROM combos WHERE slug=?", [$slug]);
            if (!$combo || !$combo['is_active']) jsonErr('An item in your cart is unavailable', 409);
            $resolved[] = ['product_id'=>null,'slug'=>$combo['slug'],'name'=>$combo['name'],'variant'=>$it['variant']??null,
                           'qty'=>$qty,'price'=>(float)$combo['price'],'line_type'=>'product','offer_id'=>null];
        }
    }
}

if (count($resolved) === 0) jsonErr('Cart is empty', 422);

// Friendly pre-check (the authoritative guard is the atomic UPDATE inside the transaction).
foreach ($stockNeed as $pid => $need) {
    $row = $db->fetchOne("SELECT name, stock FROM products WHERE id=?", [$pid]);
    if (!$row || (int)$row['stock'] < $need) {
        jsonErr('Insufficient stock for "' . ($row['name'] ?? 'an item') . '"', 409);
    }
}

// Authoritative line subtotal (gift lines contribute 0; client subtotal is ignored).
$subtotal = 0.0;
foreach ($resolved as $l) $subtotal += $l['price'] * $l['qty'];

// Order-level money is recomputed SERVER-SIDE from the cart lines + coupon code +
// admin settings (bulk rule, shipping, tax). The client never dictates discount/
// shipping/total — only which coupon code to try. See _pricing.php.
$couponCode = isset($body['couponCode']) ? (string)$body['couponCode'] : null;
$address   = $body['address'] ?? null;
// Delivery pincode drives zone-based shipping (accepts pincode/pin/zip/postalCode keys).
$pincode = is_array($address)
    ? (string)($address['pincode'] ?? $address['pin'] ?? $address['zip'] ?? $address['postalCode'] ?? '')
    : '';
$pricing  = computeOrderTotals($subtotal, $resolved, $couponCode, $pincode);
$subtotal = $pricing['subtotal'];
$discount = $pricing['discount'];
$shipping = $pricing['shipping'];
$tax      = $pricing['tax'];
$total    = $pricing['total'];
$payMethod = (string)($body['paymentMethod'] ?? 'cod');

// Per-customer coupon cap: block a customer redeeming a code more times than per_user_limit
// allows (NULL = unlimited). The global cap is enforced atomically at increment time below.
if ($pricing['couponRow']) {
    $perUser = $pricing['couponRow']['per_user_limit'] ?? 1;
    if ($perUser !== null && $perUser !== '') {
        $usedByCust = (int)($db->fetchOne(
            "SELECT COUNT(*) c FROM coupon_redemptions WHERE coupon_id=? AND customer_id=?",
            [(int)$pricing['couponRow']['id'], $cust['id']]
        )['c'] ?? 0);
        if ($usedByCust >= (int)$perUser) jsonErr('You have already used this coupon.', 422);
    }
}

// COD guard: only allow Cash-on-Delivery where the delivery pincode is serviceable AND
// has cod_available=1 (admin → Shipping → Pincode ETA). Longest-prefix match, same as
// delivery.php. If no pincode rows exist at all, COD is left open (don't block on empty config).
if ($payMethod === 'cod') {
    $pin = preg_replace('/\D/', '', $pincode);
    $pinRows = $db->fetchAll("SELECT pincode_prefix, cod_available FROM delivery_pincodes WHERE is_active=1 ORDER BY CHAR_LENGTH(pincode_prefix) DESC");
    if (count($pinRows) > 0) {
        if (strlen($pin) !== 6) jsonErr('A valid 6-digit delivery pincode is required for Cash on Delivery.', 422);
        $match = null;
        foreach ($pinRows as $r) {
            $pfx = (string)$r['pincode_prefix'];
            if ($pfx !== '' && strpos($pin, $pfx) === 0) { $match = $r; break; }
        }
        if (!$match)               jsonErr('We do not deliver to this pincode yet.', 422);
        if (!$match['cod_available']) jsonErr('Cash on Delivery is not available for this pincode. Please choose online payment.', 422);
    }
}

$orderNumber = 'SDI-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

$pdo = $db->getConnection();
$pdo->beginTransaction();
try {
    // Coupon link so the usage increment below can be reversed if the order is later
    // cancelled/refunded (see includes/order_effects.php::reverseOrderEffects).
    $couponId = $pricing['couponRow'] ? (int)$pricing['couponRow']['id'] : null;
    $orderId = $db->insert(
        "INSERT INTO orders
         (order_number, customer_id, status, payment_status, payment_method,
          subtotal, discount, shipping_charge, tax, total, coupon_id, shipping_address)
         VALUES (?,?, 'pending', ?, ?, ?,?,?,?,?,?,?)",
        [
            $orderNumber, $cust['id'],
            // COD is collected on delivery (unpaid); online orders stay 'pending'
            // until the payment gateway confirms capture (see payment_razorpay.php).
            $payMethod === 'cod' ? 'unpaid' : 'pending',
            $payMethod, $subtotal, $discount, $shipping, $tax, $total, $couponId,
            $address ? json_encode($address) : null,
        ]
    );

    $insItem = $pdo->prepare(
        "INSERT INTO order_items (order_id, product_id, product_slug, product_name, variant, quantity, price, total, line_type, offer_id, hsn_code)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    );
    // Snapshot each product's HSN code onto its order line (so a later product edit can't
    // change a past tax invoice). Looked up once per product id.
    $hsnStmt = $pdo->prepare("SELECT hsn_code FROM products WHERE id=?");
    $hsnFor = function ($pid) use ($hsnStmt) {
        if (!$pid) return null;
        $hsnStmt->execute([$pid]);
        $r = $hsnStmt->fetch(PDO::FETCH_ASSOC);
        return $r ? ($r['hsn_code'] ?: null) : null;
    };
    // Atomic stock decrement: the WHERE stock>=? + affected-row check prevents overselling
    // the last unit under concurrent orders.
    $decStock = $pdo->prepare("UPDATE products SET stock = stock - ?, total_sales = total_sales + ? WHERE id=? AND stock >= ?");
    $decCombo = $pdo->prepare("UPDATE combos SET stock = stock - ? WHERE slug=? AND stock >= ?");

    foreach ($resolved as $l) {
        $insItem->execute([
            $orderId, $l['product_id'], $l['slug'] ?: null, $l['name'], $l['variant'],
            $l['qty'], $l['price'], $l['price'] * $l['qty'], $l['line_type'], $l['offer_id'],
            $hsnFor($l['product_id']),
        ]);
        if ($l['product_id']) {
            $decStock->execute([$l['qty'], $l['qty'], $l['product_id'], $l['qty']]);
            if ($decStock->rowCount() !== 1) {
                throw new RuntimeException('"' . $l['name'] . '" just went out of stock');
            }
            // Ledger: stock out for this sale (best-effort; inside the order txn).
            recordStockMovement((int)$l['product_id'], -(int)$l['qty'], 'sale', null, $orderNumber);
            // Low-stock notification — fire once, only when this sale CROSSES the alert threshold.
            $pr = $db->fetchOne("SELECT name, stock, min_stock_alert FROM products WHERE id=?", [$l['product_id']]);
            if ($pr && (int)$pr['stock'] <= (int)$pr['min_stock_alert'] && ((int)$pr['stock'] + (int)$l['qty']) > (int)$pr['min_stock_alert']) {
                pushNotification('stock', 'Low stock: ' . $pr['name'], $pr['name'] . ' is down to ' . (int)$pr['stock'] . ' (alert ≤ ' . (int)$pr['min_stock_alert'] . ')', '/pages/products.php?stock=restock');
            }
        } elseif (!empty($l['slug'])) {
            // Non-catalog line backed by a combo: decrement combo stock the same way.
            $combo = $db->fetchOne("SELECT id FROM combos WHERE slug=?", [$l['slug']]);
            if ($combo) {
                $decCombo->execute([$l['qty'], $l['slug'], $l['qty']]);
                if ($decCombo->rowCount() !== 1) {
                    throw new RuntimeException('Combo "' . $l['name'] . '" just went out of stock');
                }
            }
        }
    }

    // Record coupon usage atomically: the conditional UPDATE only succeeds while the global
    // uses_limit hasn't been hit, closing the concurrent over-redemption race. Then log the
    // per-customer redemption (drives the per_user_limit check above).
    if ($pricing['couponRow']) {
        $cpId = (int)$pricing['couponRow']['id'];
        $bumped = $db->execute(
            "UPDATE coupons SET uses_count = uses_count + 1
              WHERE id=? AND (uses_limit IS NULL OR uses_count < uses_limit)",
            [$cpId]
        );
        if ($bumped < 1) throw new RuntimeException('This coupon has reached its usage limit.');
        $db->insert(
            "INSERT INTO coupon_redemptions (coupon_id, customer_id, order_id) VALUES (?,?,?)",
            [$cpId, $cust['id'], $orderId]
        );
    }

    // bump customer aggregates
    $db->execute(
        "UPDATE customers SET total_orders = total_orders + 1, total_spent = total_spent + ? WHERE id=?",
        [$total, $cust['id']]
    );

    // Audit trail: order placed (changed_by NULL = customer/storefront, not an admin).
    try { $db->execute("INSERT INTO order_status_history (order_id, status, note) VALUES (?, 'pending', 'order placed')", [$orderId]); } catch (Throwable $e) {}

    $pdo->commit();
} catch (Throwable $t) {
    $pdo->rollBack();
    jsonErr('Order failed: ' . $t->getMessage(), 409);
}

// New-order notification for admins (after commit, so a rolled-back order never notifies).
pushNotification('order', 'New order ' . $orderNumber, $cust['name'] . ' placed an order of ₹' . number_format($total, 2), '/pages/orders.php?view=' . $orderId);

$o = $db->fetchOne("SELECT * FROM orders WHERE id=?", [$orderId]);
$oi = $db->fetchAll("SELECT * FROM order_items WHERE order_id=?", [$orderId]);

// Best-effort WhatsApp order-confirmation (never blocks the response).
try {
    require_once __DIR__ . '/../../includes/whatsapp_sender.php';
    if (!empty($cust['phone'])) waOrderPlaced($cust, $o, $oi);
} catch (Throwable $e) { error_log('WA orderPlaced: ' . $e->getMessage()); }

// Best-effort order-placed emails. COD only here — online orders are emailed once the payment
// is captured (see payment_razorpay.php). Never blocks the response.
//   * admin notification, and
//   * customer confirmation + PDF invoice (COD is confirmed at placement).
try {
    require_once __DIR__ . '/../../includes/order_mailer.php';
    if ($payMethod === 'cod') {
        sendOrderAdminMail($o, $oi, $cust, 'placed');
        sendOrderCustomerMail($o, $oi, $cust);
    }
} catch (Throwable $e) { error_log('orderMail placed: ' . $e->getMessage()); }

jsonOut(['success' => true, 'order' => mapOrder($o, $oi)], 201);
