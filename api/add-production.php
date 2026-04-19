<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('blockfactory', 'create')) {
    $_SESSION['error'] = 'You do not have permission to record production.';
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
$product_id       = (int)($_POST['product_id']       ?? 0);
$production_date  = trim($_POST['production_date']   ?? '');
$shift            = trim($_POST['shift']             ?? '');
$supervisor       = trim($_POST['supervisor']        ?? '');
$machine_used     = trim($_POST['machine_used']      ?? '') ?: null;
$planned_quantity = (int)($_POST['planned_quantity'] ?? 0);
$produced_quantity= (int)($_POST['produced_quantity']?? 0);
$good_quantity    = (int)($_POST['good_quantity']    ?? 0);
$cement_used      = isset($_POST['cement_used'])    && $_POST['cement_used']    !== '' ? (float)$_POST['cement_used']    : null;
$sand_used        = isset($_POST['sand_used'])      && $_POST['sand_used']      !== '' ? (float)$_POST['sand_used']      : null;
$aggregate_used   = isset($_POST['aggregate_used']) && $_POST['aggregate_used'] !== '' ? (float)$_POST['aggregate_used'] : null;
$water_used       = isset($_POST['water_used'])     && $_POST['water_used']     !== '' ? (float)$_POST['water_used']     : null;
$notes            = trim($_POST['notes'] ?? '') ?: null;

// ── Validation ───────────────────────────────────────────────────────────────
$allowed_shifts = ['Morning', 'Afternoon', 'Night'];

if (!$product_id || empty($production_date) || empty($shift) || empty($supervisor) ||
    $planned_quantity <= 0 || $produced_quantity <= 0 || $good_quantity < 0) {
    $_SESSION['error'] = 'Please fill in all required fields.';
    header('Location: ../index.php');
    exit();
}

if (!in_array($shift, $allowed_shifts)) {
    $_SESSION['error'] = 'Invalid shift selected.';
    header('Location: ../index.php');
    exit();
}

if (!strtotime($production_date)) {
    $_SESSION['error'] = 'Invalid production date.';
    header('Location: ../index.php');
    exit();
}

if ($good_quantity > $produced_quantity) {
    $_SESSION['error'] = 'Good quantity cannot exceed produced quantity.';
    header('Location: ../index.php');
    exit();
}

// ── Verify product exists ────────────────────────────────────────────────────
$prod_check = $db->prepare("SELECT product_id, product_name FROM blockfactory_products WHERE product_id = ? AND status = 'Active'");
$prod_check->bind_param("i", $product_id);
$prod_check->execute();
$product = $prod_check->get_result()->fetch_assoc();
$prod_check->close();

if (!$product) {
    $_SESSION['error'] = 'Invalid product selected.';
    header('Location: ../index.php');
    exit();
}

// ── Calculate derived fields ─────────────────────────────────────────────────
$defective_quantity = $produced_quantity - $good_quantity;
$defect_rate        = $produced_quantity > 0
    ? round(($defective_quantity / $produced_quantity) * 100, 2)
    : 0.00;

// ── Auto-generate batch number ───────────────────────────────────────────────
$batch_number = 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

$batch_check = $db->prepare("SELECT production_id FROM blockfactory_production WHERE batch_number = ?");
$batch_check->bind_param("s", $batch_number);
$batch_check->execute();
$batch_check->store_result();
if ($batch_check->num_rows > 0) {
    $batch_number = 'BATCH-' . date('Ymd') . '-' . strtoupper(uniqid());
}
$batch_check->close();

// ── Insert production record ─────────────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO blockfactory_production
        (batch_number, product_id, production_date, shift, supervisor,
         machine_used, planned_quantity, produced_quantity, good_quantity,
         defective_quantity, defect_rate,
         cement_used, sand_used, aggregate_used, water_used,
         notes, created_by)
    VALUES
        (?, ?, ?, ?, ?,
         ?, ?, ?, ?,
         ?, ?,
         ?, ?, ?, ?,
         ?, ?)
");
$stmt->bind_param(
    "sissssiiiidddddss",
    $batch_number,
    $product_id,
    $production_date,
    $shift,
    $supervisor,
    $machine_used,
    $planned_quantity,
    $produced_quantity,
    $good_quantity,
    $defective_quantity,
    $defect_rate,
    $cement_used,
    $sand_used,
    $aggregate_used,
    $water_used,
    $notes,
    $created_by
);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to record production. Please try again.';
    header('Location: ./index.php');
    exit();
}
$stmt->close();

$id_result     = $db->query("SELECT LAST_INSERT_ID() AS new_id");
$production_id = (int)$id_result->fetch_assoc()['new_id'];

// ── Update product stock with good quantity produced ──────────────────────────
$stock_update = $db->prepare("
    UPDATE blockfactory_products
    SET current_stock = current_stock + ?
    WHERE product_id = ?
");
$stock_update->bind_param("ii", $good_quantity, $product_id);
$stock_update->execute();
$stock_update->close();

// ── Activity log ─────────────────────────────────────────────────────────────
$log_desc = "Recorded production batch {$batch_number}: {$produced_quantity} units of {$product['product_name']}, {$good_quantity} good";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Record Production', ?, ?, 'blockfactory', ?)
");
$log->bind_param("issi", $created_by, $log_desc, $log_ip, $production_id);
$log->execute();
$log->close();

$_SESSION['success'] = "Production batch {$batch_number} recorded successfully. {$good_quantity} good units added to stock.";
header('Location: ./index.php');
exit();