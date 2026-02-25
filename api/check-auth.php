<?php
header('Content-Type: application/json');
require_once '../includes/session.php';

$response = ['authenticated' => false, 'user' => null];

if ($session->isLoggedIn()) {
    $user = $session->getCurrentUser();
    
    // Get permissions
    global $db;
    $perms_sql = "SELECT module_name, can_view, can_create, can_edit, can_delete, can_approve 
                 FROM user_permissions WHERE user_id = ?";
    $perms_stmt = $db->prepare($perms_sql);
    $perms_stmt->bind_param("i", $user['user_id']);
    $perms_stmt->execute();
    $perms_result = $perms_stmt->get_result();
    
    $permissions = [];
    while ($perm = $perms_result->fetch_assoc()) {
        $permissions[$perm['module_name']] = [
            'view' => (bool)$perm['can_view'],
            'create' => (bool)$perm['can_create'],
            'edit' => (bool)$perm['can_edit'],
            'delete' => (bool)$perm['can_delete'],
            'approve' => (bool)$perm['can_approve']
        ];
    }
    
    $response['authenticated'] = true;
    $response['user'] = [
        'user_id' => $user['user_id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'company_id' => $user['company_id'],
        'company_name' => $user['company_name'],
        'company_type' => $user['company_type'],
        'permissions' => $permissions
    ];
}

echo json_encode($response);
?>