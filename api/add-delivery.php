<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('blockfactory', 'create')) {
    $_SESSION['error'] = 'You do not have permission to schedule deliveries.';
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
$sale_id          = (int)($_POST['sale_id']          ?? 0);
$delivery_date    = trim($_POST['delivery_date']     ?? '');
$vehicle_number   = trim($_POST['vehicle_number']    ?? '');
$driver_name      = trim($_POST['driver_name']       ?? '');
$driver_phone     = trim($_POST['driver_phone']      ?? '') ?: null;
$delivery_charges = (float)($_POST['delivery_charges'] ?? 0);
$destination      = trim($_POST['destination']       ?? '');
$notes            = trim($_POST['notes']             ?? '') ?: null;

// ── Validation ───────────────────────────────────────────────────────────────
if (!$sale_id || empty($delivery_date) || empty($vehicle_number) ||
    empty($driver_name) || empty($destination)) {
    $_SESSION['error'] = 'Please fill in all required fields.';
    header('Location: ../index.php');
    exit();
}

if (!strtotime($delivery_date)) {
    $_SESSION['error'] = 'Invalid delivery date.';
    header('Location: ../index.php');
    exit();
}

// ── Verify sale exists and is not fully delivered ────────────────────────────
$sale_check = $db->prepare("
    SELECT s.sale_id, s.quantity, s.invoice_number, s.delivery_status,
           c.customer_name
    FROM blockfactory_sales s
    LEFT JOIN blockfactory_customers c ON s.customer_id = c.customer_id
    WHERE s.sale_id = ?
");
$sale_check->bind_param("i", $sale_id);
$sale_check->execute();
$sale = $sale_check->get_result()->fetch_assoc();
$sale_check->close();

if (!$sale) {
    $_SESSION['error'] = 'Sale not found.';
    header('Location: ../index.php');
    exit();
}

if ($sale['delivery_status'] === 'Delivered') {
    $_SESSION['error'] = 'This sale has already been fully delivered.';
    header('Location: ../index.php');
    exit();
}

$quantity     = (int)$sale['quantity'];
$customer_name = $sale['customer_name'] ?? 'Unknown';

// ── Generate unique delivery note ────────────────────────────────────────────
$delivery_note = 'DN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

$dn_check = $db->prepare("SELECT delivery_id FROM blockfactory_deliveries WHERE delivery_note = ?");
$dn_check->bind_param("s", $delivery_note);
$dn_check->execute();
$dn_check->store_result();
if ($dn_check->num_rows > 0) {
    $delivery_note = 'DN-' . date('Ymd') . '-' . strtoupper(uniqid());
}
$dn_check->close();

// ── Insert delivery ───────────────────────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO blockfactory_deliveries
        (sale_id, delivery_note, delivery_date, vehicle_number, driver_name,
         driver_phone, quantity, destination, delivery_charges,
         status, notes, created_by)
    VALUES
        (?, ?, ?, ?, ?,
         ?, ?, ?, ?,
         'Scheduled', ?, ?)
");
$stmt->bind_param(
    "isssssisdss",
    $sale_id,
    $delivery_note,
    $delivery_date,
    $vehicle_number,
    $driver_name,
    $driver_phone,
    $quantity,
    $destination,
    $delivery_charges,
    $notes,
    $created_by
);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to schedule delivery. Please try again.';
    header('Location: ./index.php');
    exit();
}
$stmt->close();

$id_result   = $db->query("SELECT LAST_INSERT_ID() AS new_id");
$delivery_id = (int)$id_result->fetch_assoc()['new_id'];

// ── Activity log ─────────────────────────────────────────────────────────────
$log_desc = "Scheduled delivery {$delivery_note} for invoice {$sale['invoice_number']} to {$customer_name} on {$delivery_date}";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Schedule Delivery', ?, ?, 'blockfactory', ?)
");
$log->bind_param("issi", $created_by, $log_desc, $log_ip, $delivery_id);
$log->execute();
$log->close();

$_SESSION['success'] = "Delivery {$delivery_note} scheduled successfully for {$delivery_date}.";
header('Location: ../modules/blockfactory/index.php');
exit();