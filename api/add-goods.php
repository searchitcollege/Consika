<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('blockfactory', 'create')) {
    $_SESSION['error'] = 'You do not have permission to add products.';
    header('Location: ../admin/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/dashboard.php');
    exit();
}

global $db;

$current_user = currentUser();
$created_by   = (int)$current_user['user_id'];

// Resolve company_id
$company_id = $session->getCompanyId();
if (empty($company_id)) {
    $result     = $db->query("SELECT company_id FROM companies WHERE company_type = 'Block Factory' LIMIT 1");
    $row        = $result->fetch_assoc();
    $company_id = (int)($row['company_id'] ?? 0);
}

// ── Sanitise inputs ──────────────────────────────────────────────────────────
$product_code   = trim($_POST['product_code']   ?? '');
$product_name   = trim($_POST['product_name']   ?? '');
$product_type   = trim($_POST['product_type']   ?? '');
$dimensions     = trim($_POST['dimensions']     ?? '');
$weight_kg      = isset($_POST['weight_kg'])     && $_POST['weight_kg']     !== '' ? (float)$_POST['weight_kg']     : null;
$strength_mpa   = trim($_POST['strength_mpa']   ?? '') ?: null;
$color          = trim($_POST['color']          ?? 'Grey') ?: 'Grey';
$price_per_unit = (float)($_POST['price_per_unit'] ?? 0);
$cost_per_unit  = isset($_POST['cost_per_unit']) && $_POST['cost_per_unit'] !== '' ? (float)$_POST['cost_per_unit'] : null;
$minimum_stock  = (int)($_POST['minimum_stock']  ?? 100);
$reorder_level  = (int)($_POST['reorder_level']  ?? 200);
$description    = trim($_POST['description']    ?? '') ?: null;

// ── Validation ───────────────────────────────────────────────────────────────
$allowed_types = ['Solid Block', 'Hollow Block', 'Interlocking Block', 'Paving Block', 'Kerbstones'];

if (empty($product_code) || empty($product_name) || empty($product_type) ||
    empty($dimensions) || $price_per_unit <= 0) {
    $_SESSION['error'] = 'Product code, name, type, dimensions, and price are required.';
    header('Location: ../modules/blockfactory/index.php');
    exit();
}

if (!in_array($product_type, $allowed_types)) {
    $_SESSION['error'] = 'Invalid product type selected.';
    header('Location: ../modules/blockfactory/index.php');
    exit();
}

// ── Duplicate code check ─────────────────────────────────────────────────────
$dup = $db->prepare("SELECT product_id FROM blockfactory_products WHERE product_code = ?");
$dup->bind_param("s", $product_code);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) {
    $_SESSION['error'] = "Product code '{$product_code}' already exists.";
    header('Location: ../modules/blockfactory/index.php');
    exit();
}
$dup->close();

// ── Insert ───────────────────────────────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO blockfactory_products
        (company_id, product_code, product_name, product_type, dimensions,
         weight_kg, strength_mpa, color, price_per_unit, cost_per_unit,
         minimum_stock, reorder_level, description, status)
    VALUES
        (?, ?, ?, ?, ?,
         ?, ?, ?, ?, ?,
         ?, ?, ?, 'Active')
");
$stmt->bind_param(
    "issssdssddiis",
    $company_id,
    $product_code,
    $product_name,
    $product_type,
    $dimensions,
    $weight_kg,
    $strength_mpa,
    $color,
    $price_per_unit,
    $cost_per_unit,
    $minimum_stock,
    $reorder_level,
    $description
);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to add product. Please try again.';
    header('Location: ../modules/blockfactory/index.php');
    exit();
}
$stmt->close();

$id_result  = $db->query("SELECT LAST_INSERT_ID() AS new_id");
$product_id = (int)$id_result->fetch_assoc()['new_id'];

// ── Activity log ─────────────────────────────────────────────────────────────
$log_desc = "Added block factory product: {$product_name} ({$product_code}), Type: {$product_type}";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Add Product', ?, ?, 'blockfactory', ?)
");
$log->bind_param("issi", $created_by, $log_desc, $log_ip, $product_id);
$log->execute();
$log->close();

$_SESSION['success'] = "Product '{$product_name}' added successfully.";
header('Location: ../modules/blockfactory/index.php');
exit();