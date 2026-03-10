<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/session.php';

$session->requireLogin();
global $db;

$product_id = intval($_POST['product_id'] ?? 0);
$quantity   = intval($_POST['quantity'] ?? 0);

if ($product_id <= 0 || $quantity <= 0) {
    echo "Invalid request";
    exit;
}

// Reduce stock safely
$sql = "UPDATE procurement_products
        SET current_stock = current_stock - ?
        WHERE product_id = ? AND current_stock >= ?";

$stmt = $db->prepare($sql);
$stmt->bind_param("iii", $quantity, $product_id, $quantity);

if ($stmt->execute() && $stmt->affected_rows > 0) {

    // Log inventory movement
    $log = $db->prepare("
        INSERT INTO procurement_inventory
        (product_id, quantity, transaction_type, transaction_date)
        VALUES (?, ?, 'Sale', NOW())
    ");

    $log->bind_param("ii", $product_id, $quantity);
    $log->execute();

    echo "Stock updated successfully";
} else {

    echo "Not enough stock or update failed";
}
