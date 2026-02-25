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

$property_id = intval($_GET['id']);
$company_id = $session->getCompanyId();

$query = "SELECT * FROM estate_properties WHERE property_id = ? AND company_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("ii", $property_id, $company_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    echo json_encode(['error' => 'Property not found']);
}
?>