<?php
require_once 'includes/config.php';
require_once 'includes/session.php';

// If logged in, redirect to dashboard
if ($session->isLoggedIn()) {
    header('Location: admin/dashboard.php');
    exit();
}

// Otherwise, show landing page or redirect to login
header('Location: login.php');
exit();
?>