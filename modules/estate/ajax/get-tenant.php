<?php
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__, 3) . '/includes/db_connection.php';
require_once dirname(__DIR__, 3) . '/includes/functions.php';
require_once dirname(__DIR__, 3) . '/includes/session.php';

$session->requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No ID provided']);
    exit();
}

$tenant_id = intval($_GET['id']);
$company_id = $session->getCompanyId();

$query = "SELECT t.*, p.property_name 
          FROM estate_tenants t
          JOIN estate_properties p ON t.property_id = p.property_id
          WHERE t.tenant_id = ? AND p.company_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("ii", $tenant_id, $company_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    echo json_encode(['error' => 'Tenant not found']);
}
?>