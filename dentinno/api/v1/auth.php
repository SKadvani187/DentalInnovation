<?php
// Customer auth (storefront). Phone-based, find-or-create. Issues an api_token.
// POST /api/v1/auth.php?action=login    {mobile, name?, email?}  -> {token, customer}
// POST /api/v1/auth.php?action=profile  (Bearer token) {name,email,...} -> {customer}
// GET  /api/v1/auth.php?action=me       (Bearer token)              -> {customer}
require_once __DIR__ . '/_bootstrap.php';

$db = db();
$action = qstr('action');

// --- GET me ---
if ($action === 'me') {
    $c = requireCustomer();
    jsonOut(['success' => true, 'customer' => customerPublic($c)]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonErr('POST required', 405);
}
$body = jsonBody();

// --- login / register (find-or-create by phone) ---
if ($action === 'login') {
    $mobile = trim((string)($body['mobile'] ?? ''));
    if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        jsonErr('Enter a valid 10-digit mobile number.', 422);
    }

    // SECURITY: require a server-verified, unexpired OTP for THIS mobile before issuing a
    // token. otp.php?action=verify sets otp_codes.verified=1 on a correct OTP. We consume it
    // here (verified -> 0) so the same verification cannot be replayed for another login.
    $otpRow = $db->fetchOne("SELECT * FROM otp_codes WHERE identifier=?", [$mobile]);
    $otpOk = $otpRow
        && (int)$otpRow['verified'] === 1
        && !empty($otpRow['expires_at'])
        && strtotime($otpRow['expires_at']) >= time();
    if (!$otpOk) {
        jsonErr('OTP verification required. Please verify the OTP sent to your mobile.', 401);
    }
    // Consume the verification (single-use) to block replay.
    $db->execute("UPDATE otp_codes SET verified=0 WHERE identifier=?", [$mobile]);

    $existing = $db->fetchOne("SELECT * FROM customers WHERE phone=?", [$mobile]);
    $token = makeToken();

    // A soft-deleted account can't log back in.
    if ($existing && !empty($existing['is_deleted'])) {
        jsonErr('This account is no longer available. Please contact support.', 403);
    }

    if ($existing) {
        $db->execute("UPDATE customers SET api_token=? WHERE id=?", [$token, $existing['id']]);
        // optional profile refresh
        if (!empty($body['name']))  $db->execute("UPDATE customers SET name=? WHERE id=?", [trim($body['name']), $existing['id']]);
        if (!empty($body['email'])) $db->execute("UPDATE customers SET email=? WHERE id=?", [trim($body['email']), $existing['id']]);
        $c = $db->fetchOne("SELECT * FROM customers WHERE id=?", [$existing['id']]);
        jsonOut(['success' => true, 'isNew' => false, 'token' => $token, 'customer' => customerPublic($c)]);
    }

    // create new
    $name  = trim((string)($body['name'] ?? '')) ?: ('Customer ' . substr($mobile, -4));
    $email = trim((string)($body['email'] ?? '')) ?: null;  // no fake email; leave blank
    $id = $db->insert(
        "INSERT INTO customers (name, email, phone, customer_type, api_token) VALUES (?,?,?, 'individual', ?)",
        [$name, $email, $mobile, $token]
    );
    $c = $db->fetchOne("SELECT * FROM customers WHERE id=?", [$id]);
    jsonOut(['success' => true, 'isNew' => true, 'token' => $token, 'customer' => customerPublic($c)]);
}

// --- update profile (auth) ---
if ($action === 'profile') {
    $c = requireCustomer();
    $fields = [];
    $params = [];
    $map = ['name'=>'name','email'=>'email','city'=>'city','state'=>'state','address'=>'address','pincode'=>'pincode','clinicName'=>'clinic_name'];
    foreach ($map as $in => $col) {
        if (array_key_exists($in, $body)) { $fields[] = "$col=?"; $params[] = $body[$in]; }
    }
    if (array_key_exists('addresses', $body)) { $fields[] = "addresses=?"; $params[] = json_encode($body['addresses']); }
    if ($fields) {
        $params[] = $c['id'];
        $db->execute("UPDATE customers SET " . implode(',', $fields) . " WHERE id=?", $params);
    }
    $fresh = $db->fetchOne("SELECT * FROM customers WHERE id=?", [$c['id']]);
    jsonOut(['success' => true, 'customer' => customerPublic($fresh)]);
}

jsonErr('Unknown action', 400);
