<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

session_start();

$response = ['success' => false, 'message' => '', 'data' => null];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = isset($_POST['remember']) ? true : false;
    
    if (empty($username) || empty($password)) {
        $response['message'] = 'Please enter both username and password';
    } else {
        // Get user from database
        $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                // Check if account is active
                if ($row['status'] != 'Active') {
                    $response['message'] = 'Your account is not active. Please contact administrator.';
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['full_name'] = $row['full_name'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['company_id'] = $row['company_id'];
                    $_SESSION['login_time'] = time();
                    
                    // Set remember me token if checked
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + (86400 * 30); // 30 days
                        
                        // Store token in database
                        $sql = "INSERT INTO user_tokens (user_id, token, expiry) VALUES (?, ?, FROM_UNIXTIME(?))";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param("isi", $row['user_id'], $token, $expiry);
                        $stmt->execute();
                        
                        setcookie('remember_token', $token, $expiry, '/', '', false, true);
                    }
                    
                    // Update last login
                    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
                    $sql = "UPDATE users SET last_login = NOW(), last_ip = ? WHERE user_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("si", $ip, $row['user_id']);
                    $stmt->execute();
                    
                    // Log activity
                    $sql = "INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, 'Login', 'User logged in successfully via API', ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("is", $row['user_id'], $ip);
                    $stmt->execute();
                    
                    // Get user permissions
                    $perms_sql = "SELECT module_name, can_view, can_create, can_edit, can_delete, can_approve 
                                 FROM user_permissions WHERE user_id = ?";
                    $perms_stmt = $db->prepare($perms_sql);
                    $perms_stmt->bind_param("i", $row['user_id']);
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
                    
                    // Get company info
                    $company_sql = "SELECT company_name, company_type FROM companies WHERE company_id = ?";
                    $company_stmt = $db->prepare($company_sql);
                    $company_stmt->bind_param("i", $row['company_id']);
                    $company_stmt->execute();
                    $company_result = $company_stmt->get_result();
                    $company = $company_result->fetch_assoc();
                    
                    $response['success'] = true;
                    $response['message'] = 'Login successful';
                    $response['data'] = [
                        'user_id' => $row['user_id'],
                        'username' => $row['username'],
                        'full_name' => $row['full_name'],
                        'email' => $row['email'],
                        'role' => $row['role'],
                        'company_id' => $row['company_id'],
                        'company_name' => $company['company_name'] ?? null,
                        'company_type' => $company['company_type'] ?? null,
                        'permissions' => $permissions,
                        'session_id' => session_id()
                    ];
                }
            } else {
                // Log failed attempt
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
                $sql = "INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("ss", $username, $ip);
                $stmt->execute();
                
                $response['message'] = 'Invalid password';
            }
        } else {
            $response['message'] = 'Username not found';
        }
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
?>