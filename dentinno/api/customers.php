<?php
/**
 * Customers API - Insert into customers table
 * POST JSON body matching the customers table columns
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

$name  = trim($data['name']  ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');

if ($name === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'name is required']);
    exit;
}

if ($email === '' && $phone === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'email or phone is required']);
    exit;
}

if ($email === '' && $phone !== '') {
    $email = $phone . '@guest.dentinno.local';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

$allowedTypes  = ['individual','clinic','hospital','distributor'];
$customer_type = $data['customer_type'] ?? 'individual';
if (!in_array($customer_type, $allowedTypes, true)) {
    $customer_type = 'individual';
}

$city        = $data['city']        ?? null;
$state       = $data['state']       ?? null;
$address     = $data['address']     ?? null;
$pincode     = $data['pincode']     ?? null;
$clinic_name = $data['clinic_name'] ?? null;
$notes       = $data['notes']       ?? null;

try {
    $exists = db()->fetchOne("SELECT id FROM customers WHERE email = ?", [$email]);
    if (!$exists && $phone !== '') {
        $exists = db()->fetchOne("SELECT id FROM customers WHERE phone = ?", [$phone]);
    }
    if ($exists) {
        // Customer already registered (same email or phone). Update the real name
        // they just submitted instead of silently keeping a stale/placeholder name.
        db()->execute(
            "UPDATE customers SET name = ?, customer_type = ? WHERE id = ?",
            [$name, $customer_type, (int)$exists['id']]
        );
        http_response_code(200);
        echo json_encode([
            'success'  => true,
            'message'  => 'Customer updated',
            'id'       => (int)$exists['id'],
            'existing' => true,
        ]);
        exit;
    }

    $id = db()->insert(
        "INSERT INTO customers (name, email, phone, city, state, address, pincode, clinic_name, customer_type, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$name, $email, $phone, $city, $state, $address, $pincode, $clinic_name, $customer_type, $notes]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Customer added',
        'id'      => (int)$id,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}