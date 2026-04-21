<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('estate', 'create')) {
    $_SESSION['error'] = 'You do not have permission to add properties.';
    header('Location: ./logout.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/dashboard.php');
    exit();
}

global $db;

$current_user = currentUser();
$company_id   = $session->getCompanyId();

// Resolve company_id for SuperAdmin (no company bound)
if (empty($company_id)) {
    $result     = $db->query("SELECT company_id FROM companies WHERE company_type = 'Estate' LIMIT 1");
    $row        = $result->fetch_assoc();
    $company_id = (int)($row['company_id'] ?? 0);
}

if (empty($company_id)) {
    $_SESSION['error'] = 'Estate company not found.';
    header('Location: ../modules/estate/index.php');
    exit();
}

// ── Sanitise inputs ──────────────────────────────────────────────────────────
$property_code  = trim($_POST['property_code']  ?? '');
$property_name  = trim($_POST['property_name']  ?? '');
$property_type  = trim($_POST['property_type']  ?? '');
$status         = trim($_POST['status']         ?? 'Available');
$address        = trim($_POST['address']        ?? '');
$city           = trim($_POST['city']           ?? '');
$total_area     = $_POST['total_area']     !== '' ? (float)$_POST['total_area']     : null;
$units          = isset($_POST['units'])   && $_POST['units'] !== '' ? (int)$_POST['units'] : 1;
$purchase_price = $_POST['purchase_price'] !== '' ? (float)$_POST['purchase_price'] : null;
$current_value  = $_POST['current_value']  !== '' ? (float)$_POST['current_value']  : null;
$description    = trim($_POST['description'] ?? '');
$created_by     = (int)$current_user['user_id'];

// ── Validation ───────────────────────────────────────────────────────────────
$allowed_types   = ['Residential', 'Commercial', 'Land', 'Industrial'];
$allowed_statuses = ['Available', 'Under Maintenance', 'Under Construction'];

if (empty($property_code) || empty($property_name) || empty($address) || empty($property_type)) {
    $_SESSION['error'] = 'Please fill in all required fields.';
    header('Location: ../modules/estate/index.php');
    exit();
}

if (!in_array($property_type, $allowed_types)) {
    $_SESSION['error'] = 'Invalid property type selected.';
    header('Location: ../modules/estate/index.php');
    exit();
}

if (!in_array($status, $allowed_statuses)) {
    $_SESSION['error'] = 'Invalid status selected.';
    header('Location: ../modules/estate/index.php');
    exit();
}

// ── Duplicate property_code check ────────────────────────────────────────────
$check = $db->prepare("SELECT property_id FROM estate_properties WHERE property_code = ?");
$check->bind_param("s", $property_code);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $_SESSION['error'] = "Property code '{$property_code}' already exists. Please use a unique code.";
    header('Location: ../modules/estate/index.php');
    exit();
}
$check->close();

// ── Image upload ─────────────────────────────────────────────────────────────
$image_paths    = [];
$allowed_exts   = ['jpg', 'jpeg', 'png', 'webp'];
$max_size_bytes = 5 * 1024 * 1024; // 5 MB
$upload_dir     = '../../../uploads/estate/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (!empty($_FILES['images']['name'][0])) {
    foreach ($_FILES['images']['tmp_name'] as $index => $tmp_name) {
        if ($_FILES['images']['error'][$index] !== UPLOAD_ERR_OK) {
            continue;
        }

        $original_name = $_FILES['images']['name'][$index];
        $ext           = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $file_size     = $_FILES['images']['size'][$index];

        if (!in_array($ext, $allowed_exts)) {
            $_SESSION['error'] = "Invalid file type for '{$original_name}'. Allowed: JPG, JPEG, PNG, WEBP.";
            header('Location: ../modules/estate/index.php');
            exit();
        }

        if ($file_size > $max_size_bytes) {
            $_SESSION['error'] = "File '{$original_name}' exceeds the 5 MB limit.";
            header('Location: ../modules/estate/index.php');
            exit();
        }

        $new_filename = 'prop_' . uniqid() . '.' . $ext;
        $destination  = $upload_dir . $new_filename;

        if (move_uploaded_file($tmp_name, $destination)) {
            $image_paths[] = $new_filename;
        }
    }
}

$images_json = !empty($image_paths) ? json_encode($image_paths) : null;

// ── Insert ───────────────────────────────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO estate_properties
        (company_id, property_code, property_name, property_type, address,
         city, total_area, units, purchase_price, current_value,
         description, images, status, created_by, admin_approvals)
    VALUES
        (?, ?, ?, ?, ?,
         ?, ?, ?, ?, ?,
         ?, ?, ?, ?, 'Pending')
");

$stmt->bind_param(
    "isssssididsssi",
    $company_id,
    $property_code,
    $property_name,
    $property_type,
    $address,
    $city,
    $total_area,
    $units,
    $purchase_price,
    $current_value,
    $description,
    $images_json,
    $status,
    $created_by
);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to add property. Please try again.';
    header('Location: ../modules/estate/index.php');
    exit();
}

$stmt->close();

// Retrieve the new property_id
$id_result   = $db->query("SELECT LAST_INSERT_ID() AS new_id");
$id_row      = $id_result->fetch_assoc();
$property_id = (int)$id_row['new_id'];

// ── Activity log ─────────────────────────────────────────────────────────────
$log_desc = "Added new property: {$property_name} ({$property_code})";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$module   = 'estate';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Add Property', ?, ?, ?, ?)
");
$log->bind_param("isssi", $created_by, $log_desc, $log_ip, $module, $property_id);
$log->execute();
$log->close();

$_SESSION['success'] = "Property '{$property_name}' added successfully.";
header('Location: ../modules/estate/index.php');
exit();