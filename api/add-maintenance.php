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
$allowed_categories = ['Plumbing','Electrical','Structural','Appliance','Pest Control','Cleaning','Other'];
$allowed_priorities = ['Low','Medium','High','Emergency'];

if (!$property_id || empty($request_date) || empty($issue_category) || empty($priority) || empty($description)) {
    $_SESSION['error'] = 'Please fill in all required fields.';
    header('Location: ../modules/estate/index.php');
    exit();
}

if (!in_array($issue_category, $allowed_categories)) {
    $_SESSION['error'] = 'Invalid category selected.';
    header('Location: ../modules/estate/index.php');
    exit();
}

if (!in_array($priority, $allowed_priorities)) {
    $_SESSION['error'] = 'Invalid priority selected.';
    header('Location: ../modules/estate/index.php');
    exit();
}

// tenant_id is optional — treat 0 as NULL
$tenant_id_val = $tenant_id > 0 ? $tenant_id : null;
$request_date_val = !empty($request_date) ? $request_date : date('Y-m-d H:i:s');

$stmt = $db->prepare("
    INSERT INTO estate_maintenance
        (property_id, tenant_id, request_date, issue_category, priority, description, created_by, status, admin_approvals)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, 'Pending', 'Pending')
");
$stmt->bind_param(
    "iissssi",
    $property_id,
    $tenant_id_val,
    $request_date_val,
    $issue_category,
    $priority,
    $description,
    $created_by
);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to record maintenance request. Please try again.';
    header('Location: ../modules/estate/index.php');
    exit();
}
$stmt->close();

$id_result      = $db->query("SELECT LAST_INSERT_ID() AS new_id");
$maintenance_id = (int)$id_result->fetch_assoc()['new_id'];

// Activity log
$log_desc = "New maintenance request: {$issue_category} ({$priority}) for property ID {$property_id}";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Add Maintenance', ?, ?, 'estate', ?)
");
$log->bind_param("issi", $created_by, $log_desc, $log_ip, $maintenance_id);
$log->execute();
$log->close();

$_SESSION['success'] = 'Maintenance request submitted successfully.';
header('Location: ../modules/estate/index.php');
exit();