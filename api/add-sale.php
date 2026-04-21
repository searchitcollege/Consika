<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('blockfactory', 'create')) {
    $_SESSION['error'] = 'You do not have permission to record sales.';
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

global $db;

$current_user = currentUser();
$sales_person = (int)$current_user['user_id'];

// ── Sanitise inputs ──────────────────────────────────────────────────────────
$customer_id      = (int)($_POST['customer_id']   ?? 0);
$new_customer_name= trim($_POST['new_customer_name'] ?? '');
$sale_date        = trim($_POST['sale_date']       ?? '');
$product_id       = (int)($_POST['product_id']    ?? 0);
$quantity         = (int)($_POST['quantity']       ?? 0);
$unit_price       = (float)($_POST['unit_price']  ?? 0);
$discount         = (float)($_POST['discount']    ?? 0);
$payment_method   = trim($_POST['payment_method'] ?? '');
$amount_paid      = (float)($_POST['amount_paid'] ?? 0);
$delivery_address = trim($_POST['delivery_address'] ?? '') ?: null;
$notes            = trim($_POST['notes']           ?? '') ?: null;

// ── Resolve customer name ────────────────────────────────────────────────────
// customer_id = 0 means walk-in / new customer name typed
if ($customer_id > 0) {
    $cust_check = $db->prepare("SELECT customer_name, phone FROM blockfactory_customers WHERE customer_id = ? AND status = 'Active'");
    $cust_check->bind_param("i", $customer_id);
    $cust_check->execute();
    $cust_row = $cust_check->get_result()->fetch_assoc();
    $cust_check->close();

    if (!$cust_row) {
        $_SESSION['error'] = 'Selected customer not found.';
        header('Location: ../index.php');
        exit();
    }
    $customer_name  = $cust_row['customer_name'];
    $customer_phone = $cust_row['phone'] ?? null;
    $customer_id_val = $customer_id;
} else {
    // Walk-in — customer_id stored as NULL
    if (empty($new_customer_name)) {
        $_SESSION['error'] = 'Please select a customer or enter a customer name.';
        header('Location: ../index.php');
        exit();
    }
    $customer_name   = $new_customer_name;
    $customer_phone  = null;
    $customer_id_val = null;
}

// ── Validation ───────────────────────────────────────────────────────────────
$allowed_methods = ['Cash', 'Bank Transfer', 'Cheque', 'Mobile Money', 'Credit'];

if (!$product_id || $quantity <= 0 || $unit_price <= 0 ||
    empty($sale_date) || empty($payment_method)) {
    $_SESSION['error'] = 'Please fill in all required fields.';
    header('Location: ../index.php');
    exit();
}

if (!in_array($payment_method, $allowed_methods)) {
    $_SESSION['error'] = 'Invalid payment method selected.';
    header('Location: ../index.php');
    exit();
}

if (!strtotime($sale_date)) {
    $_SESSION['error'] = 'Invalid sale date.';
    header('Location: ../index.php');
    exit();
}

// ── Verify product and check stock ───────────────────────────────────────────
$prod_check = $db->prepare("SELECT product_id, product_name, current_stock FROM blockfactory_products WHERE product_id = ? AND status = 'Active'");
$prod_check->bind_param("i", $product_id);
$prod_check->execute();
$product = $prod_check->get_result()->fetch_assoc();
$prod_check->close();

if (!$product) {
    $_SESSION['error'] = 'Invalid product selected.';
    header('Location: ../index.php');
    exit();
}

if ($quantity > (int)$product['current_stock']) {
    $_SESSION['error'] = "Insufficient stock. Available: {$product['current_stock']} units.";
    header('Location: ../index.php');
    exit();
}

// ── Calculations ─────────────────────────────────────────────────────────────
$subtotal     = $quantity * $unit_price;
$total_amount = $subtotal - $discount;
$balance      = $total_amount - $amount_paid;

if ($amount_paid >= $total_amount) {
    $payment_status = 'Paid';
} elseif ($amount_paid > 0) {
    $payment_status = 'Partial';
} else {
    $payment_status = 'Unpaid';
}

$delivery_status = 'Pending';

// ── Generate invoice number ───────────────────────────────────────────────────
$invoice_number = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

$inv_check = $db->prepare("SELECT sale_id FROM blockfactory_sales WHERE invoice_number = ?");
$inv_check->bind_param("s", $invoice_number);
$inv_check->execute();
$inv_check->store_result();
if ($inv_check->num_rows > 0) {
    $invoice_number = 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());
}
$inv_check->close();

// ── Insert sale ──────────────────────────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO blockfactory_sales
        (invoice_number, customer_id, customer_name, customer_phone,
         sale_date, product_id, quantity, unit_price, discount,
         subtotal, total_amount, amount_paid, balance,
         payment_method, payment_status, delivery_status,
         delivery_address, notes, sales_person, admin_approvals)
    VALUES
        (?, ?, ?, ?,
         ?, ?, ?, ?, ?,
         ?, ?, ?, ?,
         ?, ?, ?,
         ?, ?, ?, 'Pending')
");
$stmt->bind_param(
    "sisssiiddddddsssssi",
    $invoice_number,
    $customer_id_val,
    $customer_name,
    $customer_phone,
    $sale_date,
    $product_id,
    $quantity,
    $unit_price,
    $discount,
    $subtotal,
    $total_amount,
    $amount_paid,
    $balance,
    $payment_method,
    $payment_status,
    $delivery_status,
    $delivery_address,
    $notes,
    $sales_person
);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to record sale. Please try again.';
    header('Location: ../modules/blockfactory/index.php');
    exit();
}
$stmt->close();

$id_result = $db->query("SELECT LAST_INSERT_ID() AS new_id");
$sale_id   = (int)$id_result->fetch_assoc()['new_id'];

// // ── Reduce product stock ──────────────────────────────────────────────────────
// $stock_update = $db->prepare("
//     UPDATE blockfactory_products
//     SET current_stock = current_stock - ?
//     WHERE product_id = ?
// ");
// $stock_update->bind_param("ii", $quantity, $product_id);
// $stock_update->execute();
// $stock_update->close();

// ── Activity log ─────────────────────────────────────────────────────────────
$log_desc = "Recorded sale {$invoice_number}: {$quantity} units of {$product['product_name']} to {$customer_name}. Total: {$total_amount}";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Record Sale', ?, ?, 'blockfactory', ?)
");
$log->bind_param("issi", $sales_person, $log_desc, $log_ip, $sale_id);
$log->execute();
$log->close();

$_SESSION['success'] = "Sale {$invoice_number} recorded successfully.";
header('Location: ../modules/blockfactory/index.php');
exit();