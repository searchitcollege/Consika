<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('blockfactory', 'edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

global $db;
$current_user = currentUser();
$delivery_id  = (int)($_POST['id'] ?? 0);

if (!$delivery_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit();
}

$stmt = $db->prepare("
    UPDATE blockfactory_deliveries
    SET status = 'Delivered'
    WHERE delivery_id = ?
");
$stmt->bind_param("i", $delivery_id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Update failed']);
    exit();
}
$stmt->close();

// Update parent sale delivery status
$sale_update = $db->prepare("
    UPDATE blockfactory_sales s
    JOIN blockfactory_deliveries d ON d.sale_id = s.sale_id
    SET s.delivery_status = 'Delivered'
    WHERE d.delivery_id = ?
");
$sale_update->bind_param("i", $delivery_id);
$sale_update->execute();
$sale_update->close();

// ──UPDATE STICK
$product_stmt = $db->prepare("
    SELECT s.product_id, s.quantity
    FROM blockfactory_sales s
    JOIN blockfactory_deliveries d ON d.sale_id = s.sale_id
    WHERE d.delivery_id = ?
");
$product_stmt->bind_param("i", $delivery_id);
$product_stmt->execute();

$result = $product_stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Sale not found']);
    exit();
}

$product_id = (int)$row['product_id'];
$quantity   = (int)$row['quantity'];

$product_stmt->close();

// update itself
$stock_update = $db->prepare("
    UPDATE blockfactory_products
    SET current_stock = current_stock - ?
    WHERE product_id = ?
");

$stock_update->bind_param("ii", $quantity, $product_id);
$stock_update->execute();
$stock_update->close();

$log_desc = "Marked delivery ID {$delivery_id} as delivered";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$user_id  = (int)$current_user['user_id'];

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Mark Delivered', ?, ?, 'blockfactory', ?)
");
$log->bind_param("issi", $user_id, $log_desc, $log_ip, $delivery_id);
$log->execute();
$log->close();

echo json_encode(['success' => true]);