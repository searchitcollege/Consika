<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

$supplier_id = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM procurement_suppliers WHERE supplier_id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();

header('Content-Type: application/json');
echo json_encode($supplier);