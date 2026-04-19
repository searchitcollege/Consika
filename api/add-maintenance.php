<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('estate', 'create')) {
    $_SESSION['error'] = 'You do not have permission to record payments.';
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

// Resolve company_id for SuperAdmin
if (empty($company_id)) {
    $result     = $db->query("SELECT company_id FROM companies WHERE company_type = 'Estate' LIMIT 1");
    $row        = $result->fetch_assoc();
    $company_id = (int)($row['company_id'] ?? 0);
}

if (empty($company_id)) {
    $_SESSION['error'] = 'Estate company not found.';
    header('Location: ../admin/dashboard.php');
    exit();
}

// ── Sanitise inputs ──────────────────────────────────────────────────────────
$tenant_id             = (int)($_POST['tenant_id']             ?? 0);
$property_id           = (int)($_POST['property_id']           ?? 0);
$request_date          = trim($_POST['request_date']           ?? '');
$issue_category        = trim($_POST['category']               ?? '');
$priority              = trim($_POST['priority']               ?? '');
$description           = trim($_POST['description']            ?? '');
$created_by           = (int)$current_user['user_id'];

// ── Validation ───────────────────────────────────────────────────────────────
if (!$tenant_id || !$property_id || empty($request_date) || empty($priority) 
    || empty($issue_category)) {
    $_SESSION['error'] = 'Please fill in all required payment fields.';
    header('Location: ../admin/dashboard.php');
    exit();
}

// ── Insert payment ───────────────────────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO estate_maintenance
        (tenant_id, property_id, request_date, issue_category,
         priority, description, created_by)
    VALUES
        (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "iissss",
    $tenant_id,
    $property_id,
    $request_date,
    $issue_category,
    $priority,
    $description,
    $created_by
);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to record maintenance request. Please try again.';
    header('Location: ../admin/dashboard.php');
    exit();
}

$stmt->close();

// ── Retrieve new payment_id ──────────────────────────────────────────────────
$id_result  = $db->query("SELECT LAST_INSERT_ID() AS new_id");
$payment_id = (int)$id_result->fetch_assoc()['new_id'];

// ── Activity log ─────────────────────────────────────────────────────────────
$log_desc = "Recorded maintenance: {$description} ON: {$request_date}) for tenant: {$tenant_id} in {$property_id} ";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$module   = 'estate';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Record Maintenance', ?, ?, ?, ?)
");
$log->bind_param("isssi", $recorded_by, $log_desc, $log_ip, $module, $payment_id);
$log->execute();
$log->close();

$_SESSION['success'] = "Payment recorded successfully. Receipt No: {$receipt_number}";
header('Location: ../modules/estate/index.php');
exit();