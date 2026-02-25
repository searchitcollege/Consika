<?php
require_once '../includes/session.php';

// Log activity if user is logged in
if ($session->isLoggedIn()) {
    $user = $session->getCurrentUser();
    log_activity($user['user_id'], 'Logout', 'User logged out');
}

// Clear remember me cookie if exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
    
    // Delete token from database
    global $db;
    $token = $_COOKIE['remember_token'];
    $sql = "DELETE FROM user_tokens WHERE token = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
}

// Destroy session
$session->logout();

// Redirect to login
header('Location: ../login.php?loggedout=1');
exit();
?>