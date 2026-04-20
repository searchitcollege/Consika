<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('procurement', 'edit')) {
    $_SESSION['error'] = 'You do not have permission to receive stock.';
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

global $db;
$current_user = currentUser();
$created_by   = (int)$current_user['user_id'];

$po_id  = (int)($_POST['po_id']  ?? 0);
$notes  = trim($_POST['notes']   ?? '') ?: null;
$receive = $_POST['receive'] ?? []; // [po_item_id => qty_received]

if (!$po_id || empty($receive)) {
    $_SESSION['error'] = 'Please select a PO and enter quantities.';
    header('Location: ../index.php');
    exit();
}

// Verify PO exists and is not completed
$po_check = $db->prepare("SELECT po_id, po_number FROM procurement_purchase_orders WHERE po_id = ? AND delivery_status != 'Completed'");
$po_check->bind_param("i", $po_id);
$po_check->execute();
$po = $po_check->get_result()->fetch_assoc();
$po_check->close();

if (!$po) {
    $_SESSION['error'] = 'Purchase order not found or already completed.';
    header('Location: ../index.php');
    exit();
}

$all_received = true;
$any_received = false;

foreach ($receive as $item_id => $qty_now) {
    $item_id = (int)$item_id;
    $qty_now = (float)$qty_now;

    if ($qty_now <= 0) continue;

    // Get item details
    $item_stmt = $db->prepare("
        SELECT pi.quantity, pi.received_quantity, pi.unit_price, pi.product_id
        FROM procurement_po_items pi
        WHERE pi.po_item_id = ? AND pi.po_id = ?
    ");
    $item_stmt->bind_param("ii", $item_id, $po_id);
    $item_stmt->execute();
    $item = $item_stmt->get_result()->fetch_assoc();
    $item_stmt->close();

    if (!$item) continue;

    $remaining = $item['quantity'] - $item['received_quantity'];
    if ($qty_now > $remaining) $qty_now = $remaining;
    if ($qty_now <= 0) continue;

    $new_received = $item['received_quantity'] + $qty_now;
    $new_status   = $new_received >= $item['quantity'] ? 'Received' : 'Partial';

    // Update PO item received quantity
    $upd = $db->prepare("
        UPDATE procurement_po_items
        SET received_quantity = ?, status = ?
        WHERE po_item_id = ?
    ");
    $upd->bind_param("dsi", $new_received, $new_status, $item_id);
    $upd->execute();
    $upd->close();

    // Update product stock
    $product_id = $item['product_id'];
    $stock_check = $db->prepare("SELECT current_stock FROM procurement_products WHERE product_id = ?");
    $stock_check->bind_param("i", $product_id);
    $stock_check->execute();
    $prod = $stock_check->get_result()->fetch_assoc();
    $stock_check->close();

    $prev_balance = (float)($prod['current_stock'] ?? 0);
    $new_balance  = $prev_balance + $qty_now;

    $stock_upd = $db->prepare("
        UPDATE procurement_products
        SET current_stock = current_stock + ?
        WHERE product_id = ?
    ");
    $stock_upd->bind_param("di", $qty_now, $product_id);
    $stock_upd->execute();
    $stock_upd->close();

    // Log inventory movement
    $ref_type = 'PO';
    $inv = $db->prepare("
        INSERT INTO procurement_inventory
            (product_id, transaction_type, reference_type, reference_id,
             quantity, previous_balance, new_balance, unit_cost, transaction_date, created_by)
        VALUES (?, 'Purchase', ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    $inv->bind_param("isiddddi",
        $product_id, $ref_type, $po_id,
        $qty_now, $prev_balance, $new_balance,
        $item['unit_price'], $created_by
    );
    $inv->execute();
    $inv->close();

    $any_received = true;

    if ($new_status !== 'Received') $all_received = false;
}

if (!$any_received) {
    $_SESSION['error'] = 'No valid quantities entered.';
    header('Location: ../index.php');
    exit();
}

// Check if all PO items are now received
$remaining_check = $db->prepare("
    SELECT COUNT(*) AS cnt FROM procurement_po_items
    WHERE po_id = ? AND status != 'Received' AND status != 'Cancelled'
");
$remaining_check->bind_param("i", $po_id);
$remaining_check->execute();
$remaining_count = (int)$remaining_check->get_result()->fetch_assoc()['cnt'];
$remaining_check->close();

$new_delivery_status = $remaining_count === 0 ? 'Completed' : 'Partial';

$po_upd = $db->prepare("
    UPDATE procurement_purchase_orders
    SET delivery_status = ?, delivery_date = CURDATE()
    WHERE po_id = ?
");
$po_upd->bind_param("si", $new_delivery_status, $po_id);
$po_upd->execute();
$po_upd->close();

$log_desc = "Received stock against PO {$po['po_number']}";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Receive Stock', ?, ?, 'procurement', ?)
");
$log->bind_param("issi", $created_by, $log_desc, $log_ip, $po_id);
$log->execute();
$log->close();

$_SESSION['success'] = "Stock received successfully against PO {$po['po_number']}.";
header('Location: ../index.php');
exit();