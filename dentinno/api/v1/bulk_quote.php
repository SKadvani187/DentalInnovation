<?php
// POST /api/v1/bulk_quote.php
//   {name, phone, email, pincode, address, productSlug, productName, quantity, expectedPrice}
// Saves a bulk-quote request from the product page form; admin sees it under Bulk Quotes.
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jsonErr('POST required', 405);
$b = jsonBody();

$name    = trim((string)($b['name'] ?? ''));
$phone   = trim((string)($b['phone'] ?? ''));
$email   = trim((string)($b['email'] ?? ''));
$pincode = preg_replace('/\D/', '', (string)($b['pincode'] ?? ''));
$address = trim((string)($b['address'] ?? ''));
$pslug   = trim((string)($b['productSlug'] ?? $b['productId'] ?? ''));
$pname   = trim((string)($b['productName'] ?? ''));
$qty     = (int)($b['quantity'] ?? 0);
$exp     = (float)($b['expectedPrice'] ?? 0);

// Server-side validation (mirrors the storefront form — never trust the client).
$phone = cleanPhone($phone);
if ($name === '' || strlen($name) < 2)      jsonErr('Please enter a valid name', 422);
if (!validPhone($phone))                    jsonErr('Enter a valid 10-digit mobile number', 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonErr('Enter a valid email address', 422);
if (!preg_match('/^[1-9]\d{5}$/', $pincode)) jsonErr('Enter a valid 6-digit pincode', 422);
if (strlen($address) < 6)                   jsonErr('Please enter a valid address', 422);
if ($qty < 1)                               jsonErr('Enter a valid quantity', 422);
if ($exp <= 0)                              jsonErr('Enter a valid expected price', 422);

db()->insert(
    "INSERT INTO bulk_quotes
       (name, phone, email, pincode, address, product_slug, product_name, quantity, expected_price)
     VALUES (?,?,?,?,?,?,?,?,?)",
    [$name, $phone ?: null, $email ?: null, $pincode ?: null, $address ?: null,
     $pslug ?: null, $pname ?: null, $qty, $exp ?: null]
);

jsonOut(['success' => true, 'message' => 'Bulk quote request received']);
