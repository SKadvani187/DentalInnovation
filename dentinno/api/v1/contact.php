<?php
// POST /api/v1/contact.php  {name, phone, email, department, message}
// Saves a contact-form inquiry; admin sees it under Messages.
require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jsonErr('POST required', 405);
$b = jsonBody();

$name  = trim((string)($b['name'] ?? ''));
$phone = trim((string)($b['phone'] ?? ''));
$email = trim((string)($b['email'] ?? ''));
$dept  = trim((string)($b['department'] ?? ''));
$msg   = trim((string)($b['message'] ?? ''));

if ($name === '' || $msg === '') jsonErr('Name and message are required', 422);
if ($phone !== '' && !preg_match('/^[6-9]\d{9}$/', $phone)) jsonErr('Invalid phone', 422);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonErr('Invalid email', 422);

db()->insert(
    "INSERT INTO contact_messages (name, phone, email, department, message) VALUES (?,?,?,?,?)",
    [$name, $phone ?: null, $email ?: null, $dept ?: null, $msg]
);

jsonOut(['success' => true, 'message' => 'Message received']);
