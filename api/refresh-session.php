<?php
require_once '../includes/session.php';
$session->refreshSession();
echo json_encode(['success' => true]);
?>