<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('estate', 'edit')) {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Invalid request";
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

$maintenance_id   = (int)($_POST['id']         ?? 0);
$contractor_name  = trim($_POST['contractor']  ?? '');
$contractor_phone = trim($_POST['phone']       ?? '');

if (!$maintenance_id || empty($contractor_name)) {
    http_response_code(400);
    echo "Invalid input";
    exit();
}

// Verify maintenance belongs to this company
$check = $db->prepare("
    SELECT m.maintenance_id
    FROM estate_maintenance m
    JOIN estate_properties p ON m.property_id = p.property_id
    WHERE m.maintenance_id = ?
      AND p.company_id = ?
");
$check->bind_param("ii", $maintenance_id, $company_id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    http_response_code(404);
    echo "Maintenance request not found";
    exit();
}
$check->close();

// Update maintenance record
$stmt = $db->prepare("
    UPDATE estate_maintenance
    SET contractor_name  = ?,
        contractor_phone = ?,
        status           = 'In Progress'
    WHERE maintenance_id = ?
");
$stmt->bind_param("ssi", $contractor_name, $contractor_phone, $maintenance_id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo "Error: " . $stmt->error;
    exit();
}
$stmt->close();

// Activity log
$log_desc = "Assigned maintenance ID {$maintenance_id} to contractor: {$contractor_name}";
$ip       = $_SERVER['REMOTE_ADDR'] ?? '';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Assign Maintenance', ?, ?, 'estate', ?)
");
$log->bind_param("issi", $current_user['user_id'], $log_desc, $ip, $maintenance_id);
$log->execute();
$log->close();

echo "Success";