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

// --- check (storefront mobile step): does this number already have a password? ---
// Drives the login UI: a number WITH a password is sent to the password screen (no OTP is
// generated); a new/legacy number falls through to the OTP flow. Returns the real name (when
// known) so the profile step can pre-fill it.
if ($action === 'check') {
    $mobile = trim((string)($body['mobile'] ?? ''));
    if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        jsonErr('Enter a valid 10-digit mobile number.', 422);
    }
    $c = $db->fetchOne("SELECT name, password, is_provisional, is_deleted FROM customers WHERE phone=?", [$mobile]);
    $exists      = $c && empty($c['is_deleted']);
    $hasPassword = $exists && !empty($c['password']);
    $realName    = ($exists && (int)($c['is_provisional'] ?? 0) !== 1) ? (string)$c['name'] : '';
    jsonOut(['success' => true, 'exists' => (bool)$exists, 'hasPassword' => (bool)$hasPassword, 'name' => $realName]);
}

// --- login / register (find-or-create by phone) ---
if ($action === 'login') {
    $mobile = trim((string)($body['mobile'] ?? ''));
    if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        jsonErr('Enter a valid 10-digit mobile number.', 422);
    }

    // PASSWORD LOGIN (returning customer): a password in the body means the buyer is logging
    // in with their saved password — verify it and issue a token WITHOUT any OTP. This is the
    // path for re-login after sign-out, so no OTP is ever generated for a known account.
    if (isset($body['password']) && (string)$body['password'] !== '') {
        $existing = $db->fetchOne("SELECT * FROM customers WHERE phone=?", [$mobile]);
        if (!$existing || !empty($existing['is_deleted'])) {
            jsonErr('No account found for this number. Please sign up first.', 404);
        }
        if (empty($existing['password'])) {
            jsonErr('No password set for this account. Please verify via OTP.', 403);
        }
        if (!password_verify((string)$body['password'], $existing['password'])) {
            jsonErr('Incorrect password. Please try again.', 401);
        }
        $token = makeToken();
        $db->execute("UPDATE customers SET api_token=? WHERE id=?", [$token, $existing['id']]);
        $c = $db->fetchOne("SELECT * FROM customers WHERE id=?", [$existing['id']]);
        jsonOut(['success' => true, 'isNew' => false, 'token' => $token, 'customer' => customerPublic($c)]);
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
        // A real name/email supplied at login (or completeProfile) clears the provisional flag.
        if (!empty($body['name']))  $db->execute("UPDATE customers SET name=?, is_provisional=0 WHERE id=?", [trim($body['name']), $existing['id']]);
        if (!empty($body['email'])) $db->execute("UPDATE customers SET email=? WHERE id=?", [trim($body['email']), $existing['id']]);
        $c = $db->fetchOne("SELECT * FROM customers WHERE id=?", [$existing['id']]);
        jsonOut(['success' => true, 'isNew' => false, 'token' => $token, 'customer' => customerPublic($c)]);
    }

    // create new. With no name yet (the storefront prompts for it right after), the row is
    // PROVISIONAL — a "Customer 1234" placeholder hidden from the admin CRM list until the
    // buyer completes their name (auth.php action=profile clears is_provisional).
    $hasName = trim((string)($body['name'] ?? '')) !== '';
    $name  = $hasName ? trim((string)$body['name']) : ('Customer ' . substr($mobile, -4));
    $email = trim((string)($body['email'] ?? '')) ?: null;  // no fake email; leave blank
    $id = $db->insert(
        "INSERT INTO customers (name, email, phone, customer_type, api_token, is_provisional) VALUES (?,?,?, 'individual', ?, ?)",
        [$name, $email, $mobile, $token, $hasName ? 0 : 1]
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
        if (array_key_exists($in, $body)) {
            $val = $body[$in];
            // email has a UNIQUE index: MySQL allows many NULLs but rejects duplicate ''.
            // Storefront sends "" for a blank email, which collides across customers (1062).
            // Coerce blank email -> NULL so unset emails never collide.
            if ($col === 'email' && trim((string)$val) === '') { $val = null; }
            $fields[] = "$col=?";
            $params[] = $val;
        }
    }
    if (array_key_exists('addresses', $body)) { $fields[] = "addresses=?"; $params[] = json_encode($body['addresses']); }
    // Password (set during registration / reset via OTP). Validate server-side — never trust the
    // client — then store a bcrypt hash, never the plain text. Rule: >= 8 chars with at least one
    // uppercase letter, one digit and one special character.
    if (array_key_exists('password', $body) && trim((string)$body['password']) !== '') {
        $pw = (string)$body['password'];
        if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $pw)) {
            jsonErr('Password must be at least 8 characters and include one uppercase letter, one number and one special character.', 422);
        }
        $fields[] = "password=?";
        $params[] = password_hash($pw, PASSWORD_DEFAULT);
    }
    // Supplying a real name promotes a provisional (placeholder) account to a full customer.
    if (!empty(trim((string)($body['name'] ?? '')))) { $fields[] = "is_provisional=0"; }
    if ($fields) {
        $params[] = $c['id'];
        $db->execute("UPDATE customers SET " . implode(',', $fields) . " WHERE id=?", $params);
    }
    $fresh = $db->fetchOne("SELECT * FROM customers WHERE id=?", [$c['id']]);
    jsonOut(['success' => true, 'customer' => customerPublic($fresh)]);
}

jsonErr('Unknown action', 400);
