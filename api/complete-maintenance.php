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

$maintenance_id = (int)($_POST['id'] ?? 0);

if (!$maintenance_id) {
    http_response_code(400);
    echo "Invalid ID";
    exit();
}

// Verify ownership
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
    echo "Maintenance not found";
    exit();
}
$check->close();

$completed_by = (int)$current_user['user_id'];

$stmt = $db->prepare("
    UPDATE estate_maintenance
    SET status          = 'Completed',
        completion_date = NOW(),
        completed_by    = ?
    WHERE maintenance_id = ?
");
$stmt->bind_param("ii", $completed_by, $maintenance_id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo "Error: " . $stmt->error;
    exit();
}
$stmt->close();

// Activity log
$log_desc = "Completed maintenance ID {$maintenance_id}";
$ip       = $_SERVER['REMOTE_ADDR'] ?? '';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Complete Maintenance', ?, ?, 'estate', ?)
");
$log->bind_param("issi", $completed_by, $log_desc, $ip, $maintenance_id);
$log->execute();
$log->close();

echo "Success";