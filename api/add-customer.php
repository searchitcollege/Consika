<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('blockfactory', 'create')) {
    $_SESSION['error'] = 'You do not have permission to add customers.';
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

global $db;

$current_user = currentUser();
$created_by   = (int)$current_user['user_id'];

// ── Sanitise inputs ──────────────────────────────────────────────────────────
$customer_code  = trim($_POST['customer_code']  ?? '');
$customer_name  = trim($_POST['customer_name']  ?? '');
$contact_person = trim($_POST['contact_person'] ?? '') ?: null;
$phone          = trim($_POST['phone']          ?? '');
$email          = trim($_POST['email']          ?? '') ?: null;
$address        = trim($_POST['address']        ?? '') ?: null;
$customer_type  = trim($_POST['customer_type']  ?? 'Individual');
$tax_number     = trim($_POST['tax_number']     ?? '') ?: null;
$credit_limit   = isset($_POST['credit_limit']) && $_POST['credit_limit'] !== '' ? (float)$_POST['credit_limit'] : null;

// ── Validation ───────────────────────────────────────────────────────────────
$allowed_types = ['Individual', 'Company', 'Contractor', 'Government'];

if (empty($customer_code) || empty($customer_name) || empty($phone)) {
    $_SESSION['error'] = 'Customer code, name, and phone are required.';
    header('Location: ../index.php');
    exit();
}

if (!in_array($customer_type, $allowed_types)) {
    $_SESSION['error'] = 'Invalid customer type selected.';
    header('Location: ../index.php');
    exit();
}

if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Invalid email address.';
    header('Location: ../index.php');
    exit();
}

// ── Duplicate code check ─────────────────────────────────────────────────────
$dup = $db->prepare("SELECT customer_id FROM blockfactory_customers WHERE customer_code = ?");
$dup->bind_param("s", $customer_code);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) {
    $_SESSION['error'] = "Customer code '{$customer_code}' already exists.";
    header('Location: ../index.php');
    exit();
}
$dup->close();

// ── Insert ───────────────────────────────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO blockfactory_customers
        (customer_code, customer_name, contact_person, phone,
         email, address, customer_type, tax_number, credit_limit, status)
    VALUES
        (?, ?, ?, ?,
         ?, ?, ?, ?, ?, 'Active')
");
$stmt->bind_param(
    "ssssssssd",
    $customer_code,
    $customer_name,
    $contact_person,
    $phone,
    $email,
    $address,
    $customer_type,
    $tax_number,
    $credit_limit
);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to add customer. Please try again.';
    header('Location: ./index.php');
    exit();
}
$stmt->close();

$id_result   = $db->query("SELECT LAST_INSERT_ID() AS new_id");
$customer_id = (int)$id_result->fetch_assoc()['new_id'];

// ── Activity log ─────────────────────────────────────────────────────────────
$log_desc = "Added customer: {$customer_name} ({$customer_code}), Type: {$customer_type}";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Add Customer', ?, ?, 'blockfactory', ?)
");
$log->bind_param("issi", $created_by, $log_desc, $log_ip, $customer_id);
$log->execute();
$log->close();

$_SESSION['success'] = "Customer '{$customer_name}' added successfully.";
header('Location: ./index.php');
exit();