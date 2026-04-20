<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!in_array($session->getRole(), ['SuperAdmin', 'CompanyAdmin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

global $db;
header('Content-Type: application/json');

$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit();
}

$stmt = $db->prepare("
    SELECT user_id, username, email, full_name, phone, role, company_id, status
    FROM users
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit();
}

echo json_encode($user);