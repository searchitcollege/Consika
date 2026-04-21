<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('blockfactory', 'create')) {
    $_SESSION['error'] = 'You do not have permission to add materials.';
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
$material_code  = trim($_POST['material_code']  ?? '');
$material_name  = trim($_POST['material_name']  ?? '');
$material_type  = trim($_POST['material_type']  ?? '');
$supplier       = trim($_POST['supplier']       ?? '') ?: null;
$unit           = trim($_POST['unit']           ?? '');
$stock_quantity = (float)($_POST['current_stock']  ?? 0);   // form field named current_stock
$minimum_stock  = (float)($_POST['minimum_stock']  ?? 0);
$maximum_stock  = (float)($_POST['maximum_stock']  ?? 10000);
$reorder_level  = (float)($_POST['reorder_level']  ?? 100);
$unit_cost      = (float)($_POST['unit_cost']      ?? 0);

// ── Validation ───────────────────────────────────────────────────────────────
$allowed_types = ['Cement', 'Sand', 'Aggregate', 'Water', 'Additive', 'Other'];
$allowed_units = ['kg', 'bags', 'tons', 'liters'];

if (empty($material_code) || empty($material_name) || empty($material_type) ||
    empty($unit) || $unit_cost <= 0) {
    $_SESSION['error'] = 'Material code, name, type, unit, and unit cost are required.';
    header('Location: ../index.php');
    exit();
}

if (!in_array($material_type, $allowed_types)) {
    $_SESSION['error'] = 'Invalid material type selected.';
    header('Location: ../index.php');
    exit();
}

if (!in_array($unit, $allowed_units)) {
    $_SESSION['error'] = 'Invalid unit selected.';
    header('Location: ../index.php');
    exit();
}

// ── Duplicate code check ─────────────────────────────────────────────────────
$dup = $db->prepare("SELECT material_id FROM blockfactory_raw_materials WHERE material_code = ?");
$dup->bind_param("s", $material_code);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) {
    $_SESSION['error'] = "Material code '{$material_code}' already exists.";
    header('Location: ../index.php');
    exit();
}
$dup->close();

// ── Determine initial status ─────────────────────────────────────────────────
if ($stock_quantity <= 0) {
    $status = 'Out of Stock';
} elseif ($stock_quantity <= $minimum_stock) {
    $status = 'Low Stock';
} else {
    $status = 'Available';
}

// ── Insert ───────────────────────────────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO blockfactory_raw_materials
        (material_code, material_name, material_type, supplier, unit,
         stock_quantity, minimum_stock, maximum_stock, reorder_level,
         unit_cost, status, admin_approvals)
    VALUES
        (?, ?, ?, ?, ?,
         ?, ?, ?, ?,
         ?, ?, 'Pending')
");
$stmt->bind_param(
    "sssssddddds",
    $material_code,
    $material_name,
    $material_type,
    $supplier,
    $unit,
    $stock_quantity,
    $minimum_stock,
    $maximum_stock,
    $reorder_level,
    $unit_cost,
    $status
);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to add material. Please try again.';
    header('Location: ./index.php');
    exit();
}
$stmt->close();

$id_result   = $db->query("SELECT LAST_INSERT_ID() AS new_id");
$material_id = (int)$id_result->fetch_assoc()['new_id'];

// ── Activity log ─────────────────────────────────────────────────────────────
$log_desc = "Added raw material: {$material_name} ({$material_code}), Type: {$material_type}, Stock: {$stock_quantity} {$unit}";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Add Material', ?, ?, 'blockfactory', ?)
");
$log->bind_param("issi", $created_by, $log_desc, $log_ip, $material_id);
$log->execute();
$log->close();

$_SESSION['success'] = "Material '{$material_name}' added successfully.";
header('Location: ./index.php');
exit();