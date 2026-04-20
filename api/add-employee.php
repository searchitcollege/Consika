<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('works', 'create')) {
    $_SESSION['error'] = 'You do not have permission to add employees.';
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
$employee_code     = trim($_POST['employee_code']     ?? '');
$full_name         = trim($_POST['full_name']         ?? '');
$id_number         = trim($_POST['id_number']         ?? '') ?: null;
$phone             = trim($_POST['phone']             ?? '');
$alternate_phone   = trim($_POST['alternate_phone']   ?? '') ?: null;
$email             = trim($_POST['email']             ?? '') ?: null;
$address           = trim($_POST['address']           ?? '') ?: null;
$position          = trim($_POST['position']          ?? '');
$department        = trim($_POST['department']        ?? '') ?: null;
$specialization    = trim($_POST['specialization']    ?? '') ?: null;
$qualification     = trim($_POST['qualification']     ?? '') ?: null;
$hire_date         = trim($_POST['hire_date']         ?? '');
$contract_type     = trim($_POST['contract_type']     ?? 'Contract');
$hourly_rate       = isset($_POST['hourly_rate'])    && $_POST['hourly_rate']    !== '' ? (float)$_POST['hourly_rate']    : null;
$daily_rate        = isset($_POST['daily_rate'])     && $_POST['daily_rate']     !== '' ? (float)$_POST['daily_rate']     : null;
$monthly_salary    = isset($_POST['monthly_salary']) && $_POST['monthly_salary'] !== '' ? (float)$_POST['monthly_salary'] : null;
$emergency_contact = trim($_POST['emergency_contact'] ?? '') ?: null;
$emergency_phone   = trim($_POST['emergency_phone']   ?? '') ?: null;

// ── Validation ───────────────────────────────────────────────────────────────
$allowed_contracts = ['Permanent', 'Contract', 'Temporary', 'Casual'];

if (empty($employee_code) || empty($full_name) || empty($phone) ||
    empty($position) || empty($hire_date)) {
    $_SESSION['error'] = 'Employee code, name, phone, position, and hire date are required.';
    header('Location: ../index.php');
    exit();
}

if (!in_array($contract_type, $allowed_contracts)) {
    $_SESSION['error'] = 'Invalid contract type selected.';
    header('Location: ../index.php');
    exit();
}

if (!strtotime($hire_date)) {
    $_SESSION['error'] = 'Invalid hire date.';
    header('Location: ../index.php');
    exit();
}

if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = 'Invalid email address.';
    header('Location: ../index.php');
    exit();
}

// ── Duplicate employee_code check ────────────────────────────────────────────
$dup = $db->prepare("SELECT employee_id FROM works_employees WHERE employee_code = ?");
$dup->bind_param("s", $employee_code);
$dup->execute();
$dup->store_result();

if ($dup->num_rows > 0) {
    $_SESSION['error'] = "Employee code '{$employee_code}' already exists.";
    header('Location: ../index.php');
    exit();
}
$dup->close();

// ── Insert ───────────────────────────────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO works_employees
        (employee_code, full_name, id_number, phone, alternate_phone,
         email, address, position, department, specialization,
         qualification, hire_date, contract_type,
         hourly_rate, daily_rate, monthly_salary,
         emergency_contact, emergency_phone, status)
    VALUES
        (?, ?, ?, ?, ?,
         ?, ?, ?, ?, ?,
         ?, ?, ?,
         ?, ?, ?,
         ?, ?, 'Active')
");

$stmt->bind_param(
    "sssssssssssssdddss",
    $employee_code,
    $full_name,
    $id_number,
    $phone,
    $alternate_phone,
    $email,
    $address,
    $position,
    $department,
    $specialization,
    $qualification,
    $hire_date,
    $contract_type,
    $hourly_rate,
    $daily_rate,
    $monthly_salary,
    $emergency_contact,
    $emergency_phone
);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to add employee. Please try again.';
    header('Location: ./index.php');
    exit();
}
$stmt->close();

// ── Retrieve new employee_id ──────────────────────────────────────────────────
$id_result   = $db->query("SELECT LAST_INSERT_ID() AS new_id");
$employee_id = (int)$id_result->fetch_assoc()['new_id'];

// ── Activity log ─────────────────────────────────────────────────────────────
$log_desc = "Added employee: {$full_name} ({$employee_code}), Position: {$position}";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Add Employee', ?, ?, 'works', ?)
");
$log->bind_param("issi", $created_by, $log_desc, $log_ip, $employee_id);
$log->execute();
$log->close();

$_SESSION['success'] = "Employee '{$full_name}' ({$employee_code}) added successfully.";
header('Location: ./index.php');
exit();