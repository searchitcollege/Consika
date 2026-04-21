<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('works', 'create')) {
    $_SESSION['error'] = 'You do not have permission to create projects.';
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

$company_id = $session->getCompanyId();
if (empty($company_id)) {
    $result     = $db->query("SELECT company_id FROM companies WHERE company_type = 'Works' LIMIT 1");
    $company_id = (int)($result->fetch_assoc()['company_id'] ?? 0);
}

$project_code    = trim($_POST['project_code']    ?? '');
$project_name    = trim($_POST['project_name']    ?? '');
$project_type    = trim($_POST['project_type']    ?? '');
$client_name     = trim($_POST['client_name']     ?? '') ?: null;
$location        = trim($_POST['location']        ?? '');
$start_date      = trim($_POST['start_date']      ?? '');
$end_date        = trim($_POST['end_date']        ?? '') ?: null;
$budget          = (float)($_POST['budget']       ?? 0);
$contingency     = (float)($_POST['contingency']  ?? 0);
$description     = trim($_POST['description']     ?? '') ?: null;
$project_manager = isset($_POST['project_manager']) && $_POST['project_manager'] !== ''
    ? (int)$_POST['project_manager'] : null;

$allowed_types = ['Construction','Renovation','Maintenance','Infrastructure','Other'];

if (empty($project_code) || empty($project_name) || empty($project_type) ||
    empty($location) || empty($start_date) || $budget <= 0) {
    $_SESSION['error'] = 'Please fill in all required fields.';
    header('Location: ../index.php');
    exit();
}

if (!in_array($project_type, $allowed_types)) {
    $_SESSION['error'] = 'Invalid project type.';
    header('Location: ../index.php');
    exit();
}

// Duplicate code check
$dup = $db->prepare("SELECT project_id FROM works_projects WHERE project_code = ?");
$dup->bind_param("s", $project_code);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) {
    $_SESSION['error'] = "Project code '{$project_code}' already exists.";
    header('Location: ../index.php');
    exit();
}
$dup->close();

$total_budget = $budget + $contingency;

$stmt = $db->prepare("
    INSERT INTO works_projects
        (company_id, project_code, project_name, project_type, client_name,
         location, start_date, end_date, budget, contingency, total_budget,
         description, project_manager, status, progress_percentage, actual_cost, created_by, admin_approvals)
    VALUES
        (?, ?, ?, ?, ?,
         ?, ?, ?, ?, ?, ?,
         ?, ?, 'Planning', 0.00, 0.00, ?, 'Pending')
");
$stmt->bind_param(
    "isssssssdddssi",
    $company_id, $project_code, $project_name, $project_type, $client_name,
    $location, $start_date, $end_date, $budget, $contingency, $total_budget,
    $description, $project_manager, $created_by
);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to create project. Please try again.';
    header('Location: ../index.php');
    exit();
}
$stmt->close();

$id_result  = $db->query("SELECT LAST_INSERT_ID() AS new_id");
$project_id = (int)$id_result->fetch_assoc()['new_id'];

$log_desc = "Created project: {$project_name} ({$project_code})";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Create Project', ?, ?, 'works', ?)
");
$log->bind_param("issi", $created_by, $log_desc, $log_ip, $project_id);
$log->execute();
$log->close();

$_SESSION['success'] = "Project '{$project_name}' created successfully.";
header('Location: ../index.php');
exit();