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
    SELECT module_name, can_view, can_create, can_edit, can_delete, can_approve
    FROM user_permissions
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$permissions = [];
while ($row = $result->fetch_assoc()) {
    $permissions[$row['module_name']] = [
        'view'    => (int)$row['can_view'],
        'create'  => (int)$row['can_create'],
        'edit'    => (int)$row['can_edit'],
        'delete'  => (int)$row['can_delete'],
        'approve' => (int)$row['can_approve'],
    ];
}

echo json_encode($permissions);