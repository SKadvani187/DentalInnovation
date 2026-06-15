<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validate.php';
$page_title = 'Customers';
requireView('customers');

// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    requireAction('customers', rbacCrudVerb($action, $data));

    if ($action === 'save') {
        $d = $data;
        // Server-side validation — don't trust the client form.
        $v = new Validator($d);
        $v->required('name')->maxLen('name', 120)
          ->emailOpt('email')->phoneOpt('phone')->pincodeOpt('pincode')
          ->maxLen('city', 80)->maxLen('state', 80)->maxLen('address', 255)
          ->maxLen('clinic_name', 150)->maxLen('notes', 2000)
          ->inOpt('customer_type', ['individual','clinic','hospital','distributor']);
        if ($v->fails()) { echo json_encode(['success'=>false,'message'=>$v->firstError()]); exit; }

        $cid = (int)($d['id'] ?? 0);
        // Normalize email: store NULL (not '') when blank so the UNIQUE index allows many
        // phone-only customers; otherwise an empty string would collide on the 2nd one.
        $email = trim((string)($d['email'] ?? ''));
        $email = $email !== '' ? $email : null;

        // Normalize phone to digits ONCE and store that canonical form, so the dupe check
        // (which normalizes) and the stored value never diverge — '98765 43210' and
        // '9876543210' must be treated as the same number.
        $phone = !empty($d['phone']) ? preg_replace('/\D/', '', (string)$d['phone']) : null;

        // Uniqueness: phone must not collide with another customer.
        if ($phone !== null && $phone !== '') {
            $dupe = db()->fetchOne(
                "SELECT id FROM customers WHERE phone=? AND id<>?",
                [$phone, $cid]
            );
            if ($dupe) { echo json_encode(['success'=>false,'message'=>'Another customer already uses this phone number.']); exit; }
        }
        // Uniqueness: email (when provided) must not collide either — avoids an unhandled
        // duplicate-key 500 from the UNIQUE index.
        if ($email !== null) {
            $dupe = db()->fetchOne("SELECT id FROM customers WHERE email=? AND id<>?", [$email, $cid]);
            if ($dupe) { echo json_encode(['success'=>false,'message'=>'Another customer already uses this email.']); exit; }
        }

        // Guard every optional field so a partial payload binds NULL/'' cleanly (no notices),
        // and cap each to its column size server-side (don't rely on the client's maxlength).
        $vals = [
            clip($d['name'] ?? '', 150), $email,
            (($d['phone'] ?? '') !== '' ? clip($d['phone'], 30) : null),
            (($d['city'] ?? '') !== '' ? clip($d['city'], 100) : null),
            (($d['state'] ?? '') !== '' ? clip($d['state'], 100) : null),
            (($d['address'] ?? '') !== '' ? clip($d['address'], 500) : null),
            (($d['pincode'] ?? '') !== '' ? clip($d['pincode'], 12) : null),
            (($d['clinic_name'] ?? '') !== '' ? clip($d['clinic_name'], 200) : null),
            (($d['customer_type'] ?? '') !== '' ? clip($d['customer_type'], 30) : null),
            (($d['notes'] ?? '') !== '' ? clip($d['notes'], 5000) : null),
        ];
        if ($cid > 0) {
            db()->execute("UPDATE customers SET name=?,email=?,phone=?,city=?,state=?,address=?,pincode=?,clinic_name=?,customer_type=?,notes=? WHERE id=?",
                array_merge($vals, [$cid]));
            logActivity('updated', 'customer', $cid, (string)($d['name'] ?? ''));
            echo json_encode(['success'=>true,'message'=>'Customer updated']);
        } else {
            db()->insert("INSERT INTO customers (name,email,phone,city,state,address,pincode,clinic_name,customer_type,notes) VALUES (?,?,?,?,?,?,?,?,?,?)",
                $vals);
            logActivity('created', 'customer', null, (string)($d['name'] ?? ''));
            echo json_encode(['success'=>true,'message'=>'Customer added']);
        }
    } elseif ($action === 'delete') {
        // Soft-delete: keep the row so order history stays intact; block storefront access.
        db()->execute("UPDATE customers SET is_deleted=1, is_active=0 WHERE id=?", [(int)($data['id'] ?? 0)]);
        logActivity('deleted', 'customer', (int)($data['id'] ?? 0));
        echo json_encode(['success'=>true,'message'=>'Customer deleted']);
    } elseif ($action === 'restore') {
        db()->execute("UPDATE customers SET is_deleted=0, is_active=1 WHERE id=?", [(int)($data['id'] ?? 0)]);
        echo json_encode(['success'=>true,'message'=>'Customer restored']);
    } elseif ($action === 'anonymize') {
        // GDPR right-to-erasure: scrub all PII but KEEP the row + order history (legal/accounting).
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid customer']); exit; }
        db()->execute(
            "UPDATE customers
                SET name='Deleted Customer', email=CONCAT('deleted+',id,'@removed.local'),
                    phone=NULL, address=NULL, pincode=NULL, city=NULL, state=NULL,
                    clinic_name=NULL, notes=NULL, addresses=NULL, cart=NULL, api_token=NULL,
                    is_active=0, is_deleted=1
              WHERE id=?",
            [$id]
        );
        logActivity('anonymized', 'customer', $id, 'GDPR erase — PII scrubbed, orders kept');
        echo json_encode(['success'=>true,'message'=>'Customer data anonymized (orders kept for records)']);
    } elseif ($action === 'toggle') {
        db()->execute("UPDATE customers SET is_active = NOT is_active WHERE id=? AND is_deleted=0", [(int)($data['id'] ?? 0)]);
        echo json_encode(['success'=>true,'message'=>'Status updated']);
    } elseif ($action === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array)($data['ids'] ?? []))));
        $op  = (string)($data['op'] ?? '');
        if (!$ids) { echo json_encode(['success'=>false,'message'=>'No customers selected']); exit; }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($op === 'activate')       { db()->execute("UPDATE customers SET is_active=1 WHERE id IN ($ph) AND is_deleted=0", $ids); $msg = count($ids).' customer(s) activated'; }
        elseif ($op === 'deactivate') { db()->execute("UPDATE customers SET is_active=0 WHERE id IN ($ph) AND is_deleted=0", $ids); $msg = count($ids).' customer(s) deactivated'; }
        elseif ($op === 'delete')     { db()->execute("UPDATE customers SET is_deleted=1, is_active=0 WHERE id IN ($ph)", $ids); $msg = count($ids).' customer(s) deleted'; }
        elseif ($op === 'restore')    { db()->execute("UPDATE customers SET is_deleted=0, is_active=1 WHERE id IN ($ph)", $ids); $msg = count($ids).' customer(s) restored'; }
        else { echo json_encode(['success'=>false,'message'=>'Unknown bulk action']); exit; }
        echo json_encode(['success'=>true,'message'=>$msg]);
    }
    exit;
}

// Bulk CSV import — upsert customers by Phone (then Email). Required column: Name.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['customers_csv'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token. Reload the page.']); exit; }
    requireAction('customers', 'create');
    $f = $_FILES['customers_csv'];
    if ($f['error'] !== UPLOAD_ERR_OK) { echo json_encode(['success'=>false,'message'=>'Upload error (code '.$f['error'].')']); exit; }
    if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'csv') { echo json_encode(['success'=>false,'message'=>'Please upload a .csv file']); exit; }
    $fh = fopen($f['tmp_name'], 'r');
    if (!$fh) { echo json_encode(['success'=>false,'message'=>'Could not read the file']); exit; }
    $header = fgetcsv($fh);
    if (!$header) { echo json_encode(['success'=>false,'message'=>'The CSV is empty']); exit; }
    $idx = [];
    foreach ($header as $i => $hcol) $idx[strtolower(trim((string)$hcol))] = $i;
    if (!isset($idx['name'])) { echo json_encode(['success'=>false,'message'=>'CSV is missing a required column: name']); exit; }
    $allowedTypes = ['individual','clinic','hospital','distributor'];
    $get = function(array $row, ?int $i): string { return ($i !== null && isset($row[$i])) ? trim((string)$row[$i]) : ''; };
    $created = 0; $updated = 0; $skipped = 0;
    while (($row = fgetcsv($fh)) !== false) {
        try {
            $name = $get($row, $idx['name'] ?? null);
            if ($name === '') { $skipped++; continue; }
            $phone = preg_replace('/\D/', '', $get($row, $idx['phone'] ?? null));
            $email = $get($row, $idx['email'] ?? null); $email = $email !== '' ? $email : null;
            $type  = strtolower($get($row, $idx['type'] ?? ($idx['customer_type'] ?? null)));
            $type  = in_array($type, $allowedTypes, true) ? $type : 'individual';
            $city  = $get($row, $idx['city'] ?? null) ?: null;
            $state = $get($row, $idx['state'] ?? null) ?: null;
            $pin   = $get($row, $idx['pincode'] ?? null) ?: null;
            $clinic= $get($row, $idx['clinic'] ?? ($idx['clinic name'] ?? null)) ?: null;
            // Find existing by phone, then email.
            $existing = null;
            if ($phone !== '') $existing = db()->fetchOne("SELECT id FROM customers WHERE phone=?", [$phone]);
            if (!$existing && $email !== null) $existing = db()->fetchOne("SELECT id FROM customers WHERE email=?", [$email]);
            if ($existing) {
                db()->execute("UPDATE customers SET name=?,email=COALESCE(?,email),phone=COALESCE(NULLIF(?,''),phone),customer_type=?,city=?,state=?,pincode=?,clinic_name=? WHERE id=?",
                    [$name, $email, $phone, $type, $city, $state, $pin, $clinic, $existing['id']]);
                $updated++;
            } else {
                db()->insert("INSERT INTO customers (name,email,phone,customer_type,city,state,pincode,clinic_name) VALUES (?,?,?,?,?,?,?,?)",
                    [$name, $email, ($phone !== '' ? $phone : null), $type, $city, $state, $pin, $clinic]);
                $created++;
            }
        } catch (Throwable $e) { error_log('customer CSV import: '.$e->getMessage()); $skipped++; }
    }
    fclose($fh);
    echo json_encode(['success'=>true, 'message'=>"Import complete — $created created, $updated updated, $skipped skipped"]);
    exit;
}

// Filters
$search  = sanitize($_GET['search'] ?? '');
$type    = sanitize($_GET['type'] ?? '');
$cstatus = sanitize($_GET['cstatus'] ?? '');   // active / inactive / deleted
$ordersF = sanitize($_GET['orders'] ?? '');    // with / without orders
$segment = sanitize($_GET['segment'] ?? '');   // vip / repeat / lead / atrisk
$sort    = sanitize($_GET['sort'] ?? '');

// VIP spend threshold (₹). A customer who has spent this much is flagged VIP.
const VIP_SPEND = 50000;
// Derive a single CRM segment + colour for a customer from their orders/spend/recency.
function customerSegment(int $orders, float $spent, $lastOrderAt): array {
    if ($orders === 0)      return ['Lead', '#7F8C8D'];        // registered, no purchase yet
    if ($spent >= VIP_SPEND) return ['VIP', '#C9A84C'];
    $days = $lastOrderAt ? (int)((time() - strtotime($lastOrderAt)) / 86400) : null;
    if ($days !== null && $days > 90) return ['At Risk', '#E74C3C']; // lapsed
    if ($orders >= 2)       return ['Repeat', '#27AE60'];
    return ['Active', '#3498DB'];
}
$page    = max(1,(int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page-1)*$per_page;

$where = ["1=1"]; $params = [];
if ($search) { $where[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.clinic_name LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]); }
if ($type)   { $where[] = "c.customer_type = ?"; $params[] = $type; }
// Soft-delete: hide deleted customers unless the "Deleted" filter is chosen.
if ($cstatus === 'deleted') {
    $where[] = "c.is_deleted = 1";
} else {
    $where[] = "c.is_deleted = 0";
    if ($cstatus === 'active')        $where[] = "c.is_active = 1";
    elseif ($cstatus === 'inactive')  $where[] = "c.is_active = 0";
}
if ($ordersF === 'with')      $where[] = "c.total_orders > 0";
elseif ($ordersF === 'without') $where[] = "c.total_orders = 0";
// CRM segment filter (derived from spend / orders / recency).
if ($segment === 'vip')        $where[] = "c.total_spent >= " . (int)VIP_SPEND;
elseif ($segment === 'repeat') $where[] = "c.total_orders >= 2";
elseif ($segment === 'lead')   $where[] = "c.total_orders = 0";
elseif ($segment === 'atrisk') $where[] = "c.total_orders > 0 AND (SELECT MAX(created_at) FROM orders o2 WHERE o2.customer_id=c.id) < DATE_SUB(NOW(), INTERVAL 90 DAY)";
$whereStr = implode(' AND ', $where);

// CSV export of the current (filtered) customer list.
if (($_GET['export'] ?? '') === 'csv') {
    $rows = db()->fetchAll("SELECT name, phone, email, customer_type, city, state, pincode, clinic_name, total_orders, total_spent, is_active FROM customers c WHERE $whereStr ORDER BY c.created_at DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customers-'.date('Ymd-His').'.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name','Phone','Email','Type','City','State','Pincode','Clinic','Orders','Total Spent','Active']);
    foreach ($rows as $r) {
        $em = (!empty($r['email']) && !str_ends_with($r['email'], '@storefront.local')) ? $r['email'] : '';
        fputcsv($out, [$r['name'],$r['phone'],$em,$r['customer_type'],$r['city'],$r['state'],$r['pincode'],$r['clinic_name'],$r['total_orders'],$r['total_spent'],$r['is_active']]);
    }
    fclose($out); exit;
}

// Sort — whitelisted ORDER BY (never interpolate raw input).
$orderMap = [
    'newest'      => 'c.created_at DESC',
    'oldest'      => 'c.created_at ASC',
    'name'        => 'c.name ASC',
    'spent_desc'  => 'c.total_spent DESC',
    'spent_asc'   => 'c.total_spent ASC',
    'orders_desc' => 'c.total_orders DESC',
];
$order = $orderMap[$sort] ?? 'c.created_at DESC';

$total     = db()->fetchOne("SELECT COUNT(*) as cnt FROM customers c WHERE $whereStr", $params)['cnt'];
$pages     = ceil($total/$per_page);
$customers = db()->fetchAll("SELECT c.*, (SELECT MAX(created_at) FROM orders o WHERE o.customer_id=c.id) AS last_order_at FROM customers c WHERE $whereStr ORDER BY $order LIMIT $per_page OFFSET $offset", $params);

// View single customer
$view_id = (int)($_GET['view'] ?? 0);
$cust_detail = null;
if ($view_id) {
    $cust_detail = db()->fetchOne("SELECT * FROM customers WHERE id=?", [$view_id]);
    if ($cust_detail) {
        $cust_detail['orders'] = db()->fetchAll("SELECT * FROM orders WHERE customer_id=? ORDER BY created_at DESC", [$view_id]);
        $cust_detail['wishlist'] = db()->fetchAll("SELECT w.*, p.name as product_name, p.price FROM wishlists w JOIN products p ON w.product_id=p.id WHERE w.customer_id=?", [$view_id]);
    }
}

// --- GDPR: export ALL of one customer's data as JSON (right of access) ---
if (isset($_GET['gdpr_export'])) {
    $cid = (int)$_GET['gdpr_export'];
    $c   = db()->fetchOne("SELECT * FROM customers WHERE id=?", [$cid]);
    if (!$c) { http_response_code(404); exit('Customer not found'); }
    $grab = function(string $sql) use ($cid) { try { return db()->fetchAll($sql, [$cid]); } catch (Throwable $e) { return []; } };
    $export = [
        'exported_at'     => date('c'),
        'profile'         => $c,
        'orders'          => $grab("SELECT * FROM orders WHERE customer_id=? ORDER BY created_at DESC"),
        'order_items'     => $grab("SELECT oi.* FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.customer_id=?"),
        'wishlist'        => $grab("SELECT w.*, p.name AS product_name FROM wishlists w JOIN products p ON p.id=w.product_id WHERE w.customer_id=?"),
        'refund_requests' => $grab("SELECT * FROM refund_requests WHERE customer_id=?"),
        'reviews'         => $grab("SELECT * FROM product_reviews WHERE customer_id=?"),
    ];
    logActivity('exported', 'customer', $cid, 'GDPR data export');
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="customer-' . $cid . '-data.json"');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header fade-in">
    <div class="page-header-left">
        <h1>Customers</h1>
        <p>Manage your customer base — <?= $total ?> customers</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <button class="btn btn-outline btn-sm" onclick="exportCsv()" title="Export the filtered list to CSV"><i class="fa-solid fa-file-csv"></i> Export</button>
        <button class="btn btn-outline btn-sm" onclick="document.getElementById('csvImportInput').click()" title="Import/update customers from a CSV"><i class="fa-solid fa-file-import"></i> Import</button>
        <input type="file" id="csvImportInput" accept=".csv" style="display:none" onchange="importCsv(this)">
        <?php if (can('customers','create')): ?><button class="btn btn-gold" onclick="openCustModal()"><i class="fa-solid fa-user-plus"></i> Add Customer</button><?php endif; ?>
    </div>
</div>

<!-- Customer Detail -->
<?php if ($cust_detail): ?>
<?php
$lastOrderAt = $cust_detail['orders'][0]['created_at'] ?? null;  // orders are sorted DESC
$aov         = $cust_detail['total_orders'] > 0 ? $cust_detail['total_spent'] / $cust_detail['total_orders'] : 0;
[$segName, $segColor] = customerSegment((int)$cust_detail['total_orders'], (float)$cust_detail['total_spent'], $lastOrderAt);
$daysSince   = $lastOrderAt ? (int)((time() - strtotime($lastOrderAt)) / 86400) : null;
$addrBook    = !empty($cust_detail['addresses']) ? json_decode($cust_detail['addresses'], true) : [];
if (!is_array($addrBook)) $addrBook = [];
$waPhone     = preg_replace('/\D/', '', (string)($cust_detail['phone'] ?? ''));
?>
<div class="card fade-in" style="margin-bottom:24px;">
    <div class="card-header">
        <div style="display:flex;align-items:center;gap:14px;">
            <div class="admin-avatar" style="width:48px;height:48px;font-size:1.2rem;"><?= strtoupper(substr($cust_detail['name'],0,1)) ?></div>
            <div>
                <div class="card-title"><?= htmlspecialchars($cust_detail['name']) ?></div>
                <?php if($cust_detail['clinic_name']): ?><div class="text-muted" style="font-size:0.82rem;"><?= htmlspecialchars($cust_detail['clinic_name']) ?></div><?php endif; ?>
            </div>
            <span class="badge badge-primary" style="margin-left:8px;"><?= ucfirst($cust_detail['customer_type']) ?></span>
            <span class="seg-pill" style="color:<?= $segColor ?>;background:<?= $segColor ?>14;border-color:<?= $segColor ?>40;font-size:.72rem;padding:4px 11px;"><span class="seg-dot" style="background:<?= $segColor ?>;"></span><?= $segName ?></span>
        </div>
        <div style="display:flex;gap:8px;">
            <?php if($waPhone): ?>
            <a href="https://wa.me/91<?= $waPhone ?>" target="_blank" class="btn btn-ghost btn-sm" title="Chat on WhatsApp"><i class="fa-brands fa-whatsapp" style="color:#25D366;"></i> WhatsApp</a>
            <a href="tel:+91<?= $waPhone ?>" class="btn btn-ghost btn-sm" title="Call"><i class="fa-solid fa-phone" style="color:var(--gold-primary);"></i> Call</a>
            <?php endif; ?>
            <a href="customers.php?gdpr_export=<?= (int)$cust_detail['id'] ?>" class="btn btn-ghost btn-sm" title="Download all of this customer's data (GDPR)"><i class="fa-solid fa-download"></i> Data</a>
            <button class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="anonymizeCustomer(<?= (int)$cust_detail['id'] ?>)" title="GDPR erase — scrub personal data, keep orders"><i class="fa-solid fa-user-slash"></i> Erase</button>
            <a href="customers.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="grid-2" style="margin-bottom:20px;">
            <div>
                <h4 style="font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:12px;">Contact Details</h4>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php $dEmail = (!empty($cust_detail['email']) && !str_ends_with($cust_detail['email'], '@storefront.local')) ? $cust_detail['email'] : '—'; ?>
                    <div><i class="fa-solid fa-envelope text-gold" style="width:20px;"></i> <?= htmlspecialchars($dEmail) ?></div>
                    <div><i class="fa-solid fa-phone text-gold" style="width:20px;"></i> <?= htmlspecialchars($cust_detail['phone'] ?? '') ?></div>
                    <div><i class="fa-solid fa-location-dot text-gold" style="width:20px;"></i> <?= htmlspecialchars($cust_detail['city'] ?? '') ?>, <?= htmlspecialchars($cust_detail['state'] ?? '') ?></div>
                    <?php if($cust_detail['pincode']): ?><div><i class="fa-solid fa-map-pin text-gold" style="width:20px;"></i> <?= htmlspecialchars($cust_detail['pincode']) ?></div><?php endif; ?>
                </div>
            </div>
            <div>
                <h4 style="font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:12px;">Business Stats</h4>
                <div class="stats-grid" style="grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="stat-card" style="padding:14px;">
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-value" style="font-size:1.4rem;"><?= $cust_detail['total_orders'] ?></div>
                    </div>
                    <div class="stat-card" style="padding:14px;">
                        <div class="stat-label">Total Spent</div>
                        <div class="stat-value text-gold" style="font-size:1.2rem;"><?= formatCurrency($cust_detail['total_spent']) ?></div>
                    </div>
                    <div class="stat-card" style="padding:14px;">
                        <div class="stat-label">Avg Order Value</div>
                        <div class="stat-value" style="font-size:1.2rem;"><?= formatCurrency($aov) ?></div>
                    </div>
                    <div class="stat-card" style="padding:14px;">
                        <div class="stat-label">Last Order</div>
                        <div class="stat-value" style="font-size:1rem;"><?= $lastOrderAt ? formatDate($lastOrderAt, 'd M Y') : '—' ?></div>
                        <?php if($daysSince !== null): ?><div class="text-muted" style="font-size:.7rem;"><?= $daysSince === 0 ? 'today' : $daysSince.'d ago' ?></div><?php endif; ?>
                    </div>
                </div>
                <?php if($cust_detail['notes']): ?>
                <div style="margin-top:12px;padding:12px;background:var(--bg-elevated);border-radius:8px;font-size:0.82rem;color:var(--text-secondary);">
                    <strong>Notes:</strong> <?= htmlspecialchars($cust_detail['notes']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order History -->
        <h4 style="font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:10px;">Order History (<?= count($cust_detail['orders']) ?>)</h4>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Order #</th><th>Date</th><th>Amount</th><th>Status</th><th>Payment</th></tr></thead>
                <tbody>
                    <?php foreach($cust_detail['orders'] as $o): ?>
                    <tr>
                        <td><a href="orders.php?view=<?= $o['id'] ?>" class="text-gold font-bold"><?= $o['order_number'] ?></a></td>
                        <td><?= formatDate($o['created_at']) ?></td>
                        <td class="font-bold"><?= formatCurrency($o['total']) ?></td>
                        <td><span class="badge badge-<?= statusBadge($o['status']) ?>"><?= $o['status'] ?></span></td>
                        <td><span class="badge badge-<?= statusBadge($o['payment_status']) ?>"><?= $o['payment_status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($cust_detail['orders'])): ?><tr><td colspan="5" class="text-center text-muted">No orders yet</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Saved Address Book (from the storefront) -->
        <?php if($addrBook): ?>
        <h4 style="font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;margin:20px 0 10px;">Saved Addresses (<?= count($addrBook) ?>)</h4>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;margin-bottom:8px;">
            <?php foreach($addrBook as $a): ?>
            <div style="border:1px solid var(--border-color);border-radius:8px;padding:12px;font-size:.82rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <strong><?= htmlspecialchars($a['name'] ?? '') ?></strong>
                    <?php if(!empty($a['isDefault'])): ?><span class="badge badge-success" style="font-size:.6rem;">Default</span><?php endif; ?>
                </div>
                <?php if(!empty($a['type'])): ?><div class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars($a['type']) ?></div><?php endif; ?>
                <?php $line = trim(implode(', ', array_filter([$a['building']??'',$a['line1']??'',$a['line2']??'',$a['landmark']??'',$a['city']??'',$a['state']??'',$a['pincode']??'']))); ?>
                <div style="margin-top:4px;color:var(--text-secondary);"><?= htmlspecialchars($line) ?></div>
                <?php if(!empty($a['mobile'])): ?><div class="text-muted" style="margin-top:3px;"><i class="fa-solid fa-phone" style="font-size:.7rem;"></i> <?= htmlspecialchars($a['mobile']) ?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Wishlist -->
        <?php if(!empty($cust_detail['wishlist'])): ?>
        <h4 style="font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;margin:20px 0 10px;">Wishlist (<?= count($cust_detail['wishlist']) ?>)</h4>
        <div class="table-responsive">
            <table>
                <thead><tr><th>Product</th><th>Price</th></tr></thead>
                <tbody>
                    <?php foreach($cust_detail['wishlist'] as $w): ?>
                    <tr>
                        <td><?= htmlspecialchars($w['product_name'] ?? '') ?></td>
                        <td class="font-bold"><?= formatCurrency($w['price']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="filter-bar fade-in">
    <div class="search-wrapper">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" class="search-input" id="searchInput" placeholder="Search name, email, phone..." value="<?= $search ?>">
    </div>
    <select class="form-control" id="typeFilter" style="max-width:150px;">
        <option value="">All Types</option>
        <?php foreach(['individual','clinic','hospital','distributor'] as $t): ?>
        <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-control" id="statusFilter" style="max-width:130px;">
        <option value="">All Status</option>
        <option value="active"   <?= $cstatus==='active'?'selected':'' ?>>Active</option>
        <option value="inactive" <?= $cstatus==='inactive'?'selected':'' ?>>Inactive</option>
        <option value="deleted"  <?= $cstatus==='deleted'?'selected':'' ?>>🗑 Deleted</option>
    </select>
    <select class="form-control" id="ordersFilter" style="max-width:150px;">
        <option value="">All Customers</option>
        <option value="with"    <?= $ordersF==='with'?'selected':'' ?>>With Orders</option>
        <option value="without" <?= $ordersF==='without'?'selected':'' ?>>No Orders</option>
    </select>
    <select class="form-control" id="segmentFilter" style="max-width:150px;">
        <option value="">All Segments</option>
        <option value="vip"    <?= $segment==='vip'?'selected':'' ?>>⭐ VIP</option>
        <option value="repeat" <?= $segment==='repeat'?'selected':'' ?>>Repeat</option>
        <option value="lead"   <?= $segment==='lead'?'selected':'' ?>>Lead (no order)</option>
        <option value="atrisk" <?= $segment==='atrisk'?'selected':'' ?>>At Risk</option>
    </select>
    <select class="form-control" id="sortBy" style="max-width:170px;" onchange="applyFilters()">
        <?php foreach(['newest'=>'Newest','oldest'=>'Oldest','name'=>'Name: A → Z','spent_desc'=>'Top Spenders','spent_asc'=>'Lowest Spent','orders_desc'=>'Most Orders'] as $sv=>$sl): ?>
        <option value="<?= $sv ?>" <?= $sort===$sv?'selected':'' ?>>Sort: <?= $sl ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-ghost btn-sm" onclick="applyFilters()"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="customers.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-rotate-left"></i> Reset</a>
</div>

<!-- Table -->
<style>
    .seg-pill{ display:inline-flex; align-items:center; gap:5px; font-size:.66rem; font-weight:700; padding:3px 9px; border-radius:20px; border:1px solid; white-space:nowrap; line-height:1; }
    .seg-pill .seg-dot{ width:6px; height:6px; border-radius:50%; flex-shrink:0; }
    .cust-table tbody tr{ transition:background .15s; }
    .cust-table tbody tr:hover{ background:var(--bg-elevated); }
    .cust-table tbody td{ vertical-align:middle; }
</style>
<div class="card fade-in">
    <div id="bulkBar" style="display:none;padding:12px 16px;border-bottom:1px solid var(--border-color);gap:10px;align-items:center;background:var(--bg-elevated);">
        <span id="bulkCount" style="font-size:.82rem;font-weight:600;"></span>
        <?php if($cstatus==='deleted'): ?>
        <button class="btn btn-ghost btn-sm" onclick="bulkAction('restore')"><i class="fa-solid fa-trash-arrow-up" style="color:var(--success);"></i> Restore</button>
        <?php else: ?>
        <button class="btn btn-ghost btn-sm" onclick="bulkAction('activate')"><i class="fa-solid fa-circle-check" style="color:var(--success);"></i> Activate</button>
        <button class="btn btn-ghost btn-sm" onclick="bulkAction('deactivate')"><i class="fa-solid fa-ban" style="color:var(--warning);"></i> Deactivate</button>
        <button class="btn btn-ghost btn-sm" onclick="bulkAction('delete')" style="color:var(--danger);"><i class="fa-solid fa-trash"></i> Delete</button>
        <?php endif; ?>
        <button class="btn btn-ghost btn-sm" onclick="clearBulk()">Clear</button>
    </div>
    <div class="table-responsive">
        <table class="cust-table">
            <thead>
                <tr>
                    <th style="width:34px;"><input type="checkbox" id="selectAllCust" onchange="toggleAllCust(this)" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;"></th>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Orders</th>
                    <th>Total Spent</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($customers as $i => $c): ?>
                <tr id="cust-row-<?= $c['id'] ?>">
                    <td><input type="checkbox" class="cust-check" value="<?= $c['id'] ?>" onchange="updateBulkBar()" style="width:15px;height:15px;accent-color:var(--gold-primary);cursor:pointer;"></td>
                    <td class="text-muted"><?= $offset+$i+1 ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="admin-avatar" style="width:34px;height:34px;font-size:0.85rem;"><?= strtoupper(substr($c['name'],0,1)) ?></div>
                            <div>
                                <div class="font-bold" style="font-size:0.84rem;"><?= htmlspecialchars($c['name']) ?></div>
                                <?php if($c['clinic_name']): ?><div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($c['clinic_name']) ?></div><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div><?= htmlspecialchars($c['phone'] ?? '') ?></div>
                        <?php $showEmail = (!empty($c['email']) && !str_ends_with($c['email'], '@storefront.local')) ? $c['email'] : ''; ?>
                        <div class="text-muted" style="font-size:0.73rem;"><?= $showEmail ? htmlspecialchars($showEmail) : '<span style="opacity:.5;">—</span>' ?></div>
                    </td>
                    <td>
                        <span class="badge badge-info"><?= htmlspecialchars(ucfirst($c['customer_type'] ?? '')) ?></span>
                        <?php [$seg,$segColor] = customerSegment((int)$c['total_orders'], (float)$c['total_spent'], $c['last_order_at'] ?? null); ?>
                        <div style="margin-top:5px;"><span class="seg-pill" style="color:<?= $segColor ?>;background:<?= $segColor ?>14;border-color:<?= $segColor ?>40;"><span class="seg-dot" style="background:<?= $segColor ?>;"></span><?= $seg ?></span></div>
                    </td>
                    <td>
                        <div><?= htmlspecialchars($c['city'] ?? '') ?></div>
                        <div class="text-muted" style="font-size:0.72rem;"><?= htmlspecialchars($c['state'] ?? '') ?></div>
                    </td>
                    <td class="text-center font-bold"><?= $c['total_orders'] ?></td>
                    <td class="font-bold text-gold"><?= formatCurrency($c['total_spent']) ?></td>
                    <td><?= formatDate($c['created_at'], 'd M Y') ?></td>
                    <td><span class="badge badge-<?= ($c['is_active']??1)?'success':'secondary' ?>"><?= ($c['is_active']??1)?'Active':'Inactive' ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <?php if(!empty($c['is_deleted'])): ?>
                            <a href="?view=<?= $c['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="View"><i class="fa-solid fa-eye"></i></a>
                            <?php if (can('customers','edit')): ?><button class="btn btn-ghost btn-sm" onclick="restoreCust(<?= $c['id'] ?>)" title="Restore customer"><i class="fa-solid fa-trash-arrow-up" style="color:var(--success);"></i> Restore</button><?php endif; ?>
                            <?php else: ?>
                            <a href="?view=<?= $c['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="View"><i class="fa-solid fa-eye"></i></a>
                            <?php if (can('customers','edit')): ?>
                            <button class="btn btn-ghost btn-sm btn-icon" title="Edit" onclick='openCustModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, "UTF-8") ?>)'><i class="fa-solid fa-pen"></i></button>
                            <button class="btn btn-ghost btn-sm btn-icon" title="Activate/Deactivate" onclick="toggleCust(<?= $c['id'] ?>)"><i class="fa-solid fa-power-off" style="color:<?= ($c['is_active']??1)?'var(--success)':'var(--text-muted)' ?>;"></i></button>
                            <?php endif; ?>
                            <?php if (can('customers','delete')): ?><button class="btn btn-ghost btn-sm btn-icon" title="Delete" onclick="deleteCust(<?= $c['id'] ?>)"><i class="fa-solid fa-trash" style="color:var(--danger);"></i></button><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($customers)): ?>
                <tr><td colspan="11"><div class="empty-state"><i class="fa-solid fa-user-group"></i><p>No customers found</p></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($pages > 1): ?>
    <div style="padding:16px 20px;border-top:1px solid var(--border-color);">
        <div class="pagination">
            <?php
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

<!-- Add/Edit Customer Modal -->
<div class="modal-overlay" id="custModal" style="display:none;" onclick="if(event.target===this)closeModal('custModal')">
    <div class="modal-box modal-lg">
        <div class="modal-head">
            <h2 id="custModalTitle">Add New Customer</h2>
            <button class="close-btn" onclick="closeModal('custModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="cust_id">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" class="form-control" id="cust_name" placeholder="Dr. Rajesh Sharma">
                </div>
                <div class="form-group">
                    <label class="form-label">Customer Type</label>
                    <select class="form-control" id="cust_type">
                        <?php foreach(['individual','clinic','hospital','distributor'] as $t): ?>
                        <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Email <small class="text-muted">(optional)</small></label>
                    <input type="email" class="form-control" id="cust_email" placeholder="doctor@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" class="form-control" id="cust_phone" placeholder="9876543210">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Clinic / Hospital Name</label>
                <input type="text" class="form-control" id="cust_clinic" placeholder="Sharma Dental Clinic">
            </div>
            <div class="form-row-3">
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" class="form-control" id="cust_city" placeholder="Mumbai">
                </div>
                <div class="form-group">
                    <label class="form-label">State</label>
                    <input type="text" class="form-control" id="cust_state" placeholder="Maharashtra">
                </div>
                <div class="form-group">
                    <label class="form-label">Pincode</label>
                    <input type="text" class="form-control" id="cust_pincode" placeholder="400001">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea class="form-control" id="cust_address" rows="2" placeholder="Full address..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea class="form-control" id="cust_notes" rows="2" placeholder="Internal notes about this customer..."></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closeModal('custModal')">Cancel</button>
            <button class="btn btn-gold" onclick="saveCustomer()"><i class="fa-solid fa-floppy-disk"></i> Save Customer</button>
        </div>
    </div>
</div>

<script>
function buildCustQuery(extra={}){
    const p=new URLSearchParams({
        search:document.getElementById('searchInput')?.value||'',
        type:document.getElementById('typeFilter')?.value||'',
        cstatus:document.getElementById('statusFilter')?.value||'',
        orders:document.getElementById('ordersFilter')?.value||'',
        segment:document.getElementById('segmentFilter')?.value||'',
        sort:document.getElementById('sortBy')?.value||'',
    });
    Object.entries(extra).forEach(([k,v])=>p.set(k,v));
    [...p.entries()].forEach(([k,v])=>{if(!v)p.delete(k);});
    return p.toString();
}
function applyFilters(){ window.location.href='customers.php?'+buildCustQuery(); }
function exportCsv(){ window.location.href='customers.php?'+buildCustQuery({export:'csv'}); }
function anonymizeCustomer(id){
  showConfirm('GDPR Erase','This permanently SCRUBS the customer\'s personal data (name, email, phone, address, notes). Order history is kept (anonymized) for accounting. This cannot be undone. Continue?', async()=>{
    const res=await fetch('customers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'anonymize',id})});
    const r=await res.json();
    showToast(r.message, r.success?'success':'danger');
    if(r.success) setTimeout(()=>location.href='customers.php', 1300);
  });
}
function goPage(p){ const q=new URLSearchParams(window.location.search); q.set('page',p); window.location.href='customers.php?'+q.toString(); }

async function importCsv(input){
    const file=input.files[0]; input.value='';
    if(!file)return;
    if(!confirm(`Import "${file.name}"? Existing customers (matched by phone, then email) are updated; new ones created.`))return;
    const fd=new FormData(); fd.append('customers_csv',file);
    showToast('Importing…','info');
    try{
        const res=await fetch('customers.php',{method:'POST',body:fd});
        const r=await res.json();
        showToast(r.message||(r.success?'Imported':'Import failed'), r.success?'success':'danger');
        if(r.success) setTimeout(()=>location.reload(),1200);
    }catch(e){ showToast('Import failed','danger'); }
}

function toggleCust(id){
    fetch('customers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'toggle',id})})
    .then(r=>r.json()).then(d=>{if(d.success){showToast('Status updated','success');setTimeout(()=>location.reload(),600);}});
}

// ---- Bulk selection ----
function selectedCustIds(){return [...document.querySelectorAll('.cust-check:checked')].map(c=>parseInt(c.value));}
function updateBulkBar(){
    const n=selectedCustIds().length;
    const bar=document.getElementById('bulkBar');
    bar.style.display=n?'flex':'none';
    if(n)document.getElementById('bulkCount').textContent=n+' selected';
    const all=document.getElementById('selectAllCust');
    const total=document.querySelectorAll('.cust-check').length;
    if(all)all.checked=n>0&&n===total;
}
function toggleAllCust(cb){document.querySelectorAll('.cust-check').forEach(c=>c.checked=cb.checked);updateBulkBar();}
function clearBulk(){document.querySelectorAll('.cust-check').forEach(c=>c.checked=false);const a=document.getElementById('selectAllCust');if(a)a.checked=false;updateBulkBar();}
async function bulkAction(op){
    const ids=selectedCustIds(); if(!ids.length)return;
    if(!confirm(`${op==='activate'?'Activate':'Deactivate'} ${ids.length} customer(s)?`))return;
    const res=await fetch('customers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'bulk',op,ids})});
    const r=await res.json();
    showToast(r.message||(r.success?'Done':'Failed'), r.success?'success':'danger');
    if(r.success)setTimeout(()=>location.reload(),800);
}

function openCustModal(c = null) {
    document.getElementById('cust_id').value      = c?.id || '';
    document.getElementById('cust_name').value    = c?.name || '';
    document.getElementById('cust_type').value    = c?.customer_type || 'individual';
    document.getElementById('cust_email').value   = c?.email || '';
    document.getElementById('cust_phone').value   = c?.phone || '';
    document.getElementById('cust_clinic').value  = c?.clinic_name || '';
    document.getElementById('cust_city').value    = c?.city || '';
    document.getElementById('cust_state').value   = c?.state || '';
    document.getElementById('cust_pincode').value = c?.pincode || '';
    document.getElementById('cust_address').value = c?.address || '';
    document.getElementById('cust_notes').value   = c?.notes || '';
    document.getElementById('custModalTitle').textContent = c ? 'Edit Customer' : 'Add New Customer';
    openModal('custModal');
}

async function saveCustomer() {
    const name  = document.getElementById('cust_name').value.trim();
    const email = document.getElementById('cust_email').value.trim();
    if (!name) { showToast('Name is required', 'warning'); return; }
    const data = {
        action: 'save',
        id: document.getElementById('cust_id').value,
        name, email,
        phone:         document.getElementById('cust_phone').value,
        clinic_name:   document.getElementById('cust_clinic').value,
        customer_type: document.getElementById('cust_type').value,
        city:          document.getElementById('cust_city').value,
        state:         document.getElementById('cust_state').value,
        pincode:       document.getElementById('cust_pincode').value,
        address:       document.getElementById('cust_address').value,
        notes:         document.getElementById('cust_notes').value,
    };
    const res = await fetch('customers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify(data)});
    const r = await res.json();
    if (r.success) { showToast(r.message,'success'); closeModal('custModal'); setTimeout(()=>location.reload(),800); }
    else showToast(r.message,'danger');
}

function deleteCust(id) {
    showConfirm('Delete Customer','This hides the customer and blocks their storefront login. Order history is kept, and you can restore them anytime from the "Deleted" filter. Continue?', async () => {
        const res = await fetch('customers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'delete',id})});
        const r = await res.json();
        if(r.success){showToast('Customer deleted','success');const row=document.getElementById(`cust-row-${id}`);if(row){row.style.opacity='0';row.style.transition='opacity 0.3s';setTimeout(()=>row.remove(),300);}}
    });
}
function restoreCust(id){
    fetch('customers.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({action:'restore',id})})
    .then(r=>r.json()).then(d=>{if(d.success){showToast('Customer restored','success');setTimeout(()=>location.reload(),600);}});
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
