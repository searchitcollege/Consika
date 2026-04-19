<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('estate', 'create')) {
    $_SESSION['error'] = 'You do not have permission to add tenants.';
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

global $db;

$current_user = currentUser();
$company_id   = $session->getCompanyId();

// Resolve company_id for SuperAdmin
if (empty($company_id)) {
    $result     = $db->query("SELECT company_id FROM companies WHERE company_type = 'Estate' LIMIT 1");
    $row        = $result->fetch_assoc();
    $company_id = (int)($row['company_id'] ?? 0);
}

if (empty($company_id)) {
    $_SESSION['error'] = 'Estate company not found.';
    header('Location: ../index.php');
    exit();
}

// ── Sanitise inputs ──────────────────────────────────────────────────────────
$property_id             = (int)($_POST['property_id']             ?? 0);
$full_name               = trim($_POST['full_name']               ?? '');
$id_number               = trim($_POST['id_number']               ?? '');
$phone                   = trim($_POST['phone']                   ?? '');
$email                   = trim($_POST['email']                   ?? '');
$lease_start_date        = trim($_POST['lease_start_date']        ?? '');
$lease_end_date          = trim($_POST['lease_end_date']          ?? '');
$monthly_rent            = $_POST['monthly_rent']   !== '' ? (float)$_POST['monthly_rent']   : 0;
$deposit_amount          = $_POST['deposit_amount'] !== '' ? (float)$_POST['deposit_amount'] : null;
$emergency_contact_name  = trim($_POST['emergency_contact_name']  ?? '');
$emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
$created_by              = (int)$current_user['user_id'];

// ── Validation ───────────────────────────────────────────────────────────────
if (!$property_id || empty($full_name) || empty($id_number) || empty($phone) ||
    empty($lease_start_date) || empty($lease_end_date) || $monthly_rent <= 0) {
    $_SESSION['error'] = 'Please fill in all required fields.';
    header('Location: ../index.php');
    exit();
}

// Validate dates
$start_ts = strtotime($lease_start_date);
$end_ts   = strtotime($lease_end_date);

if (!$start_ts || !$end_ts) {
    $_SESSION['error'] = 'Invalid lease dates provided.';
    header('Location: ../index.php');
    exit();
}

if ($end_ts <= $start_ts) {
    $_SESSION['error'] = 'Lease end date must be after the start date.';
    header('Location: ../index.php');
    exit();
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Invalid email address provided.';
    header('Location: ../index.php');
    exit();
}

// ── Verify property belongs to this company ──────────────────────────────────
$prop_check = $db->prepare("SELECT property_id, property_name FROM estate_properties WHERE property_id = ? AND company_id = ?");
$prop_check->bind_param("ii", $property_id, $company_id);
$prop_check->execute();
$prop_check->store_result();

if ($prop_check->num_rows === 0) {
    $_SESSION['error'] = 'Selected property was not found.';
    header('Location: ../index.php');
    exit();
}

$prop_check->bind_result($verified_property_id, $property_name);
$prop_check->fetch();
$prop_check->close();

// ── Duplicate ID number check (within same company properties) ───────────────
$dup_check = $db->prepare("
    SELECT t.tenant_id
    FROM estate_tenants t
    JOIN estate_properties p ON t.property_id = p.property_id
    WHERE t.id_number = ? AND p.company_id = ?
");
$dup_check->bind_param("si", $id_number, $company_id);
$dup_check->execute();
$dup_check->store_result();

if ($dup_check->num_rows > 0) {
    $_SESSION['error'] = "A tenant with ID number '{$id_number}' already exists.";
    header('Location: ../index.php');
    exit();
}
$dup_check->close();

// ── Auto-generate tenant_code ────────────────────────────────────────────────
// Format: TEN001, TEN002 … based on highest existing numeric suffix
$code_result = $db->query("SELECT tenant_code FROM estate_tenants ORDER BY tenant_id DESC LIMIT 1");
$last_code   = $code_result->fetch_assoc()['tenant_code'] ?? 'TEN000';
$last_num    = (int)preg_replace('/\D/', '', $last_code);
$tenant_code = 'TEN' . str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);

// Ensure uniqueness in case of gaps/custom codes
$code_check = $db->prepare("SELECT tenant_id FROM estate_tenants WHERE tenant_code = ?");
$code_check->bind_param("s", $tenant_code);
$code_check->execute();
$code_check->store_result();

if ($code_check->num_rows > 0) {
    // Fallback: use timestamp-based suffix
    $tenant_code = 'TEN' . date('ymdHis');
}
$code_check->close();

// ── Calculate lease duration in months ──────────────────────────────────────
$lease_duration_months = (int)round(($end_ts - $start_ts) / (60 * 60 * 24 * 30));

// ── Nullable helpers ─────────────────────────────────────────────────────────
$email_val                   = !empty($email)                   ? $email                   : null;
$emergency_contact_name_val  = !empty($emergency_contact_name)  ? $emergency_contact_name  : null;
$emergency_contact_phone_val = !empty($emergency_contact_phone) ? $emergency_contact_phone : null;
$deposit_val                 = $deposit_amount !== null          ? $deposit_amount           : null;

// ── Insert ───────────────────────────────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO estate_tenants
        (company_id, property_id, tenant_code, full_name, id_number,
         phone, email, lease_start_date, lease_end_date, lease_duration_months,
         monthly_rent, deposit_amount, emergency_contact_name, emergency_contact_phone,
         status, created_by)
    VALUES
        (?, ?, ?, ?, ?,
         ?, ?, ?, ?, ?,
         ?, ?, ?, ?,
         'Active', ?)
");

$stmt->bind_param(
    "iisssssssiddssi",
    $company_id,
    $property_id,
    $tenant_code,
    $full_name,
    $id_number,
    $phone,
    $email_val,
    $lease_start_date,
    $lease_end_date,
    $lease_duration_months,
    $monthly_rent,
    $deposit_val,
    $emergency_contact_name_val,
    $emergency_contact_phone_val,
    $created_by
);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to add tenant. Please try again.';
    header('Location: ../index.php');
    exit();
}

$stmt->close();

// Retrieve new tenant_id
$id_result = $db->query("SELECT LAST_INSERT_ID() AS new_id");
$id_row    = $id_result->fetch_assoc();
$tenant_id = (int)$id_row['new_id'];

// ── Mark property as Occupied if it was Available ────────────────────────────
$update_prop = $db->prepare("
    UPDATE estate_properties
    SET status = 'Occupied'
    WHERE property_id = ? AND status = 'Available'
");
$update_prop->bind_param("i", $property_id);
$update_prop->execute();
$update_prop->close();

// ── Activity log ─────────────────────────────────────────────────────────────
$log_desc = "Added new tenant: {$full_name} ({$tenant_code}) to property: {$property_name}";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$module   = 'estate';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Add Tenant', ?, ?, ?, ?)
");
$log->bind_param("isssi", $created_by, $log_desc, $log_ip, $module, $tenant_id);
$log->execute();
$log->close();

$_SESSION['success'] = "Tenant '{$full_name}' ({$tenant_code}) added successfully.";
header('Location: ../index.php');
exit();