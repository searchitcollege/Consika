<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/session.php';

$session->requireLogin();

// Check permission
if (!hasPermission('procurement', 'view')) {
    $_SESSION['error'] = 'You do not have permission to access this module.';
    header('Location: ../../admin/dashboard.php');
    exit();
}

$current_user = currentUser();
$company_id = $session->getCompanyId();

global $db;

if (empty($company_id) || $company_id == null) {
    $result = $db->query("SELECT company_id FROM companies WHERE company_type = 'Procurement' LIMIT 1");
    $row = $result->fetch_assoc();
    $company_id = (int)($row['company_id'] ?? 0);
}

if (empty($company_id)) {
    $_SESSION['error'] = 'Procurement company not found.';
    header('Location: ../../admin/dashboard.php');
    exit();
}


// Handle purchase orders createion 
// PO draft until everything is approved and ready, then redirect to edit page to add items and finalize the order
// Handle purchase order creation

// ============================================================
// CREATE PURCHASE ORDER
// ============================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'add' &&
    ($_POST['form']   ?? '') === 'po'
) {

    $supplier_id       = (int)($_POST['supplier_id']       ?? 0);
    $order_date        = trim($_POST['order_date']         ?? '');
    $expected_delivery = trim($_POST['expected_delivery']  ?? '') ?: null;
    $notes             = trim($_POST['notes']              ?? '') ?: null;
    $items             = $_POST['items'] ?? [];
    $created_by        = (int)$current_user['user_id'];

    if (!$supplier_id || empty($order_date) || empty($items)) {
        $_SESSION['error'] = 'Please fill required fields and add at least one item.';
        header('Location: index.php');
        exit();
    }

    // ── Validate supplier belongs to this company ────────────────────────────
    $sup_check = $db->prepare("SELECT supplier_id FROM procurement_suppliers WHERE supplier_id = ? AND company_id = ?");
    $sup_check->bind_param("ii", $supplier_id, $company_id);
    $sup_check->execute();
    $sup_check->store_result();

    if ($sup_check->num_rows === 0) {
        $_SESSION['error'] = 'Invalid supplier selected.';
        header('Location: index.php');
        exit();
    }
    $sup_check->close();

    // ── Calculate totals ─────────────────────────────────────────────────────
    $subtotal = 0;
    $valid_items = [];

    foreach ($items as $item) {
        if (empty($item['product_id'])) continue;

        $quantity = (int)($item['quantity']   ?? 0);
        $price    = (float)($item['unit_price'] ?? 0);
        $discount = (float)($item['discount']   ?? 0);

        if ($quantity <= 0 || $price <= 0) continue;

        $line_total  = ($quantity * $price) - $discount;
        $subtotal   += $line_total;

        $valid_items[] = [
            'product_id' => (int)$item['product_id'],
            'quantity'   => $quantity,
            'price'      => $price,
            'discount'   => $discount,
            'line_total' => $line_total,
        ];
    }

    if (empty($valid_items)) {
        $_SESSION['error'] = 'Please add at least one valid item.';
        header('Location: index.php');
        exit();
    }

    $tax_amount   = $subtotal * 0.16;
    $total_amount = $subtotal + $tax_amount;

    // ── Generate unique PO number ─────────────────────────────────────────────
    $po_number = 'PO-' . date('Y') . '-' . rand(1000, 9999);

    $po_check = $db->prepare("SELECT po_id FROM procurement_purchase_orders WHERE po_number = ?");
    $po_check->bind_param("s", $po_number);
    $po_check->execute();
    $po_check->store_result();
    if ($po_check->num_rows > 0) {
        $po_number = 'PO-' . date('Y') . '-' . rand(1000, 9999) . rand(0, 9);
    }
    $po_check->close();

    $delivery_status = 'Pending';
    $payment_status  = 'Unpaid';
    $approval_status = 'Pending';

    // ── Insert PO header ──────────────────────────────────────────────────────
    $stmt = $db->prepare("
        INSERT INTO procurement_purchase_orders
            (po_number, supplier_id, order_date, expected_delivery, notes,
             delivery_status, payment_status, total_amount, approval_status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sisssssdsi",
        $po_number,
        $supplier_id,
        $order_date,
        $expected_delivery,
        $notes,
        $delivery_status,
        $payment_status,
        $total_amount,
        $approval_status,
        $created_by
    );

    if (!$stmt->execute()) {
        $_SESSION['error'] = 'Failed to create purchase order.';
        header('Location: index.php');
        exit();
    }
    $stmt->close();

    // ── Retrieve new po_id ────────────────────────────────────────────────────
    $id_result = $db->query("SELECT LAST_INSERT_ID() AS new_id");
    $po_id     = (int)$id_result->fetch_assoc()['new_id'];

    // ── Insert PO items ───────────────────────────────────────────────────────
    foreach ($valid_items as $item) {
        $stmt_item = $db->prepare("
            INSERT INTO procurement_po_items
                (po_id, product_id, quantity, unit_price, discount, total_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_item->bind_param(
            "iiiddd",
            $po_id,
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $item['discount'],
            $item['line_total']
        );
        $stmt_item->execute();
        $stmt_item->close();
    }

    // ── Activity log ──────────────────────────────────────────────────────────
    $log_desc = "Created purchase order {$po_number} for supplier ID {$supplier_id}";
    $log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

    $log = $db->prepare("
        INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
        VALUES (?, 'Create PO', ?, ?, 'procurement', ?)
    ");
    $log->bind_param("issi", $created_by, $log_desc, $log_ip, $po_id);
    $log->execute();
    $log->close();

    $_SESSION['success'] = "Purchase Order {$po_number} created successfully.";
    header('Location: index.php');
    exit();
}


// ============================================================
// ADD SUPPLIER
// ============================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'add' &&
    ($_POST['form']   ?? '') === 'supplier'
) {

    $supplier_code  = trim($_POST['supplier_code']  ?? '');
    $supplier_name  = trim($_POST['supplier_name']  ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '') ?: null;
    $phone          = trim($_POST['phone']          ?? '');
    $email          = trim($_POST['email']          ?? '') ?: null;
    $website        = trim($_POST['website']        ?? '') ?: null;
    $address        = trim($_POST['address']        ?? '') ?: null;
    $category       = trim($_POST['category']       ?? '') ?: null;
    $tax_number     = trim($_POST['tax_number']     ?? '') ?: null;
    $payment_terms  = trim($_POST['payment_terms']  ?? '') ?: null;
    $credit_limit   = isset($_POST['credit_limit']) && $_POST['credit_limit'] !== '' ? (float)$_POST['credit_limit'] : null;
    $created_by     = (int)$current_user['user_id'];

    if (empty($supplier_code) || empty($supplier_name) || empty($phone)) {
        $_SESSION['error'] = 'Supplier code, name, and phone are required.';
        header('Location: index.php');
        exit();
    }

    if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email address.';
        header('Location: index.php');
        exit();
    }

    // Duplicate code check
    $dup = $db->prepare("SELECT supplier_id FROM procurement_suppliers WHERE supplier_code = ?");
    $dup->bind_param("s", $supplier_code);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        $_SESSION['error'] = "Supplier code '{$supplier_code}' already exists.";
        header('Location: index.php');
        exit();
    }
    $dup->close();

    $stmt = $db->prepare("
        INSERT INTO procurement_suppliers
            (company_id, supplier_code, supplier_name, contact_person, phone,
             email, website, address, category, tax_number, payment_terms, credit_limit, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "issssssssssdi",
        $company_id,
        $supplier_code,
        $supplier_name,
        $contact_person,
        $phone,
        $email,
        $website,
        $address,
        $category,
        $tax_number,
        $payment_terms,
        $credit_limit,
        $created_by
    );

    if ($stmt->execute()) {
        $stmt->close();

        $id_result   = $db->query("SELECT LAST_INSERT_ID() AS new_id");
        $supplier_id = (int)$id_result->fetch_assoc()['new_id'];

        $log_desc = "Added supplier: {$supplier_name} ({$supplier_code})";
        $log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

        $log = $db->prepare("
            INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
            VALUES (?, 'Add Supplier', ?, ?, 'procurement', ?)
        ");
        $log->bind_param("issi", $created_by, $log_desc, $log_ip, $supplier_id);
        $log->execute();
        $log->close();

        $_SESSION['success'] = "Supplier '{$supplier_name}' added successfully.";
    } else {
        $_SESSION['error'] = 'Error adding supplier. Please try again.';
    }

    header('Location: index.php');
    exit();
}


// ============================================================
// PRODUCT ACTIONS (add / edit / delete / reduce_stock)
// ============================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['form'] ?? '') === 'product' &&
    isset($_POST['action'])
) {

    $created_by = (int)$current_user['user_id'];

    switch ($_POST['action']) {

        // ── ADD PRODUCT ───────────────────────────────────────────────────────
        case 'add':
            $product_code  = trim($_POST['product_code']  ?? '');
            $product_name  = trim($_POST['product_name']  ?? '');
            $category      = trim($_POST['category']      ?? '') ?: null;
            $sub_category  = trim($_POST['sub_category']  ?? '') ?: null;
            $description   = trim($_POST['description']   ?? '') ?: null;
            $unit          = trim($_POST['unit']          ?? '');
            $minimum_stock = (int)($_POST['minimum_stock'] ?? 0);
            $maximum_stock = (int)($_POST['maximum_stock'] ?? 1000);
            $current_stock = (int)($_POST['current_stock'] ?? 0);
            $reorder_level = (int)($_POST['reorder_level'] ?? 10);
            $unit_price    = (float)($_POST['unit_price']   ?? 0);
            $selling_price = isset($_POST['selling_price']) && $_POST['selling_price'] !== '' ? (float)$_POST['selling_price'] : null;
            $tax_rate      = isset($_POST['tax_rate'])      && $_POST['tax_rate']      !== '' ? (float)$_POST['tax_rate']      : 16.00;
            $location      = trim($_POST['location']  ?? '') ?: null;
            $barcode       = trim($_POST['barcode']   ?? '') ?: null;
            $status        = trim($_POST['status']    ?? 'Active');

            if (empty($product_code) || empty($product_name) || empty($unit) || $unit_price <= 0) {
                $_SESSION['error'] = 'Product code, name, unit, and unit price are required.';
                break;
            }

            $dup = $db->prepare("SELECT product_id FROM procurement_products WHERE product_code = ?");
            $dup->bind_param("s", $product_code);
            $dup->execute();
            $dup->store_result();
            if ($dup->num_rows > 0) {
                $_SESSION['error'] = "Product code '{$product_code}' already exists.";
                $dup->close();
                break;
            }
            $dup->close();

            $stmt = $db->prepare("
                INSERT INTO procurement_products
                    (product_code, product_name, category, sub_category, description, unit,
                     minimum_stock, maximum_stock, current_stock, reorder_level,
                     unit_price, selling_price, tax_rate, location, barcode, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "ssssssiiiidddsss",
                $product_code,
                $product_name,
                $category,
                $sub_category,
                $description,
                $unit,
                $minimum_stock,
                $maximum_stock,
                $current_stock,
                $reorder_level,
                $unit_price,
                $selling_price,
                $tax_rate,
                $location,
                $barcode,
                $status
            );

            if ($stmt->execute()) {
                $stmt->close();

                $id_result  = $db->query("SELECT LAST_INSERT_ID() AS new_id");
                $product_id = (int)$id_result->fetch_assoc()['new_id'];

                $log_desc = "Added product: {$product_name} ({$product_code})";
                $log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

                $log = $db->prepare("
                    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
                    VALUES (?, 'Add Product', ?, ?, 'procurement', ?)
                ");
                $log->bind_param("issi", $created_by, $log_desc, $log_ip, $product_id);
                $log->execute();
                $log->close();

                $_SESSION['success'] = "Product '{$product_name}' added successfully.";
            } else {
                $_SESSION['error'] = 'Error adding product. Please try again.';
            }
            break;

        // ── EDIT PRODUCT ──────────────────────────────────────────────────────
        case 'edit':
            $product_id    = (int)($_POST['product_id']   ?? 0);
            $product_code  = trim($_POST['product_code']  ?? '');
            $product_name  = trim($_POST['product_name']  ?? '');
            $category      = trim($_POST['category']      ?? '') ?: null;
            $sub_category  = trim($_POST['sub_category']  ?? '') ?: null;
            $description   = trim($_POST['description']   ?? '') ?: null;
            $unit          = trim($_POST['unit']          ?? '');
            $minimum_stock = (int)($_POST['minimum_stock'] ?? 0);
            $maximum_stock = (int)($_POST['maximum_stock'] ?? 1000);
            $unit_price    = (float)($_POST['unit_price']   ?? 0);
            $selling_price = isset($_POST['selling_price']) && $_POST['selling_price'] !== '' ? (float)$_POST['selling_price'] : null;
            $status        = trim($_POST['status'] ?? 'Active');

            if (!$product_id || empty($product_code) || empty($product_name) || empty($unit)) {
                $_SESSION['error'] = 'Product ID, code, name, and unit are required.';
                break;
            }

            $stmt = $db->prepare("
                UPDATE procurement_products SET
                    product_code  = ?,
                    product_name  = ?,
                    category      = ?,
                    sub_category  = ?,
                    description   = ?,
                    unit          = ?,
                    minimum_stock = ?,
                    maximum_stock = ?,
                    unit_price    = ?,
                    selling_price = ?,
                    status        = ?
                WHERE product_id = ?
            ");
            $stmt->bind_param(
                "ssssssiiddsi",
                $product_code,
                $product_name,
                $category,
                $sub_category,
                $description,
                $unit,
                $minimum_stock,
                $maximum_stock,
                $unit_price,
                $selling_price,
                $status,
                $product_id
            );

            if ($stmt->execute()) {
                $log_desc = "Updated product ID {$product_id}: {$product_name}";
                $log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

                $log = $db->prepare("
                    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
                    VALUES (?, 'Edit Product', ?, ?, 'procurement', ?)
                ");
                $log->bind_param("issi", $created_by, $log_desc, $log_ip, $product_id);
                $log->execute();
                $log->close();

                $_SESSION['success'] = 'Product updated successfully.';
            } else {
                $_SESSION['error'] = 'Error updating product. Please try again.';
            }
            $stmt->close();
            break;

        // ── DELETE PRODUCT ────────────────────────────────────────────────────
        case 'delete':
            $product_id = (int)($_POST['product_id'] ?? 0);

            if (!$product_id) {
                $_SESSION['error'] = 'Invalid product ID.';
                break;
            }

            // Check for inventory history before deleting
            $check = $db->prepare("SELECT COUNT(*) AS cnt FROM procurement_inventory WHERE product_id = ?");
            $check->bind_param("i", $product_id);
            $check->execute();
            $count = (int)$check->get_result()->fetch_assoc()['cnt'];
            $check->close();

            if ($count > 0) {
                $_SESSION['error'] = 'Cannot delete product with inventory history. Deactivate it instead.';
                break;
            }

            // Also block if used in any PO items
            $po_check = $db->prepare("SELECT COUNT(*) AS cnt FROM procurement_po_items WHERE product_id = ?");
            $po_check->bind_param("i", $product_id);
            $po_check->execute();
            $po_count = (int)$po_check->get_result()->fetch_assoc()['cnt'];
            $po_check->close();

            if ($po_count > 0) {
                $_SESSION['error'] = 'Cannot delete product that is referenced in purchase orders.';
                break;
            }

            $stmt = $db->prepare("DELETE FROM procurement_products WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);

            if ($stmt->execute()) {
                $log_desc = "Deleted product ID {$product_id}";
                $log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

                $log = $db->prepare("
                    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
                    VALUES (?, 'Delete Product', ?, ?, 'procurement', ?)
                ");
                $log->bind_param("issi", $created_by, $log_desc, $log_ip, $product_id);
                $log->execute();
                $log->close();

                $_SESSION['success'] = 'Product deleted successfully.';
            } else {
                $_SESSION['error'] = 'Error deleting product.';
            }
            $stmt->close();
            break;

        // ── REDUCE STOCK ──────────────────────────────────────────────────────
        case 'reduce_stock':
            $product_id = (int)($_POST['product_id'] ?? 0);
            $quantity   = (float)($_POST['quantity']   ?? 0);

            if (!$product_id || $quantity <= 0) {
                http_response_code(400);
                echo 'Invalid product or quantity.';
                exit();
            }

            $check = $db->prepare("SELECT current_stock, product_name FROM procurement_products WHERE product_id = ?");
            $check->bind_param("i", $product_id);
            $check->execute();
            $product = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$product) {
                http_response_code(404);
                echo 'Product not found.';
                exit();
            }

            $current_stock = (float)$product['current_stock'];

            if ($quantity > $current_stock) {
                http_response_code(400);
                echo 'Cannot reduce more than available stock.';
                exit();
            }

            $new_balance = $current_stock - $quantity;

            $update = $db->prepare("
                UPDATE procurement_products
                SET current_stock = current_stock - ?
                WHERE product_id = ?
            ");
            $update->bind_param("di", $quantity, $product_id);

            if (!$update->execute()) {
                http_response_code(500);
                echo 'Failed to update stock.';
                exit();
            }
            $update->close();

            // Log inventory movement with full required columns
            $movement = $db->prepare("
                INSERT INTO procurement_inventory
                    (product_id, transaction_type, quantity, previous_balance, new_balance, transaction_date, created_by)
                VALUES (?, 'Sale', ?, ?, ?, NOW(), ?)
            ");
            $movement->bind_param("idddi", $product_id, $quantity, $current_stock, $new_balance, $created_by);
            $movement->execute();
            $movement->close();

            echo 'Stock reduced successfully.';
            exit();
    }

    header('Location: index.php');
    exit();
}

// Get statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM procurement_suppliers WHERE company_id = ? AND status = 'Active') as total_suppliers,
                (SELECT COUNT(*) FROM procurement_products WHERE status = 'Active') as total_products,
                (SELECT COUNT(*) FROM procurement_purchase_orders WHERE delivery_status = 'Pending') as pending_orders,
                (SELECT COUNT(*) FROM procurement_products WHERE current_stock <= minimum_stock) as low_stock_items,
                (SELECT COALESCE(SUM(total_amount), 0) FROM procurement_purchase_orders WHERE MONTH(order_date) = MONTH(CURRENT_DATE())) as monthly_purchases,
                (SELECT COALESCE(SUM(total_amount), 0) FROM procurement_purchase_orders WHERE order_date = CURRENT_DATE()) as today_purchases";
$stmt = $db->prepare($stats_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get recent purchase orders
$po_query = "SELECT po.*, s.supplier_name 
             FROM procurement_purchase_orders po
             JOIN procurement_suppliers s ON po.supplier_id = s.supplier_id
             WHERE s.company_id = ?
             ORDER BY po.created_at DESC LIMIT 10";
$stmt = $db->prepare($po_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$recent_pos = $stmt->get_result();

// Get low stock products
$low_stock_query = "SELECT * FROM procurement_products 
                   WHERE current_stock <= minimum_stock 
                   AND status = 'Active'
                   ORDER BY (current_stock - minimum_stock) ASC LIMIT 10";
$low_stock = $db->query($low_stock_query);

// Get recent deliveries
$deliveries_query = "SELECT po.po_number, po.delivery_status, s.supplier_name, po.expected_delivery
                    FROM procurement_purchase_orders po
                    JOIN procurement_suppliers s ON po.supplier_id = s.supplier_id
                    WHERE s.company_id = ? AND po.delivery_status != 'Completed'
                    ORDER BY po.expected_delivery ASC LIMIT 10";
$stmt = $db->prepare($deliveries_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$pending_deliveries = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procurement Management - <?php echo APP_NAME; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Custom CSS -->
    <link href="../../assets/css/style.css" rel="stylesheet">

    <!-- Custom CSS For jus Procuremnt Pages-->
    <link href="../../assets/css/procurement/style.css" rel="stylesheet">

</head>

<body class="module-procurement">
    <div class="wrapper">
        <!-- Include sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <?php include '../../includes/top-nav.php'; ?>

            <!-- Module Header -->
            <div class="module-header">
                <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="h3 mb-2">Procurement Management</h1>
                        <p class="mb-0 opacity-75">Manage suppliers, purchase orders, and inventory</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-light me-2" data-bs-toggle="modal" data-bs-target="#createPOModal">
                            <i class="fas fa-plus-circle me-2"></i>Create PO
                        </button>
                        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                            <i class="fas fa-truck me-2"></i>Add Supplier
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-action-grid mb-4">
                <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#createPOModal">
                    <i class="fas fa-file-invoice"></i>
                    <span>New Purchase Order</span>
                </div>
                <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#receiveStockModal">
                    <i class="fas fa-boxes"></i>
                    <span>Receive Stock</span>
                </div>
                <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fas fa-box"></i>
                    <span>Add Product</span>
                </div>
                <div class="quick-action-btn" onclick="window.location.href='inventory-check.php'">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Inventory Check</span>
                </div>
                <div class="quick-action-btn" onclick="window.location.href='supplier-performance.php'">
                    <i class="fas fa-chart-line"></i>
                    <span>Supplier Performance</span>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['total_suppliers']; ?></h3>
                        <p class="stat-label">Active Suppliers</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f72585, #b5179e);">
                            <i class="fas fa-box"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['total_products']; ?></h3>
                        <p class="stat-label">Products</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f8961e, #f3722c);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['pending_orders']; ?></h3>
                        <p class="stat-label">Pending Orders</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #b02a37);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['low_stock_items']; ?></h3>
                        <p class="stat-label">Low Stock Items</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="procurementTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                        <i class="fas fa-home me-2"></i>Overview
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="purchase-orders-tab" data-bs-toggle="tab" data-bs-target="#purchase-orders" type="button" role="tab">
                        <i class="fas fa-file-invoice me-2"></i>Purchase Orders
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="suppliers-tab" data-bs-toggle="tab" data-bs-target="#suppliers" type="button" role="tab">
                        <i class="fas fa-truck me-2"></i>Suppliers
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">
                        <i class="fas fa-box me-2"></i>Products
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="procurementTabContent">
                <!-- Overview Tab -->
                <div class="tab-pane fade show active" id="overview" role="tabpanel">
                    <div class="row">
                        <!-- Recent Purchase Orders -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Purchase Orders</h5>
                                    <a href="purchase-orders.php" class="btn btn-sm btn-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if ($recent_pos->num_rows > 0): ?>
                                        <?php while ($po = $recent_pos->fetch_assoc()): ?>
                                            <div class="po-card">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="mb-1">PO #<?php echo $po['po_number']; ?></h6>
                                                        <p class="mb-1 text-muted small"><?php echo htmlspecialchars($po['supplier_name']); ?></p>
                                                    </div>
                                                    <span class="status-badge bg-<?php
                                                                                    echo $po['delivery_status'] == 'Completed' ? 'success' : ($po['delivery_status'] == 'Partial' ? 'warning' : ($po['delivery_status'] == 'Pending' ? 'info' : 'secondary'));
                                                                                    ?> text-white">
                                                        <?php echo $po['delivery_status']; ?>
                                                    </span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="far fa-calendar me-1"></i> <?php echo format_date($po['order_date']); ?>
                                                    </small>
                                                    <strong><?php echo format_money($po['total_amount']); ?></strong>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-4">No purchase orders found</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Pending Deliveries -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Pending Deliveries</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($pending_deliveries->num_rows > 0): ?>
                                        <?php while ($delivery = $pending_deliveries->fetch_assoc()): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div>
                                                    <strong>PO #<?php echo $delivery['po_number']; ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo $delivery['supplier_name']; ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-<?php
                                                                            echo strtotime($delivery['expected_delivery']) < time() ? 'danger' : 'warning';
                                                                            ?>">
                                                        <?php echo format_date($delivery['expected_delivery']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-4">No pending deliveries</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Low Stock Alert -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Low Stock Alert</h5>
                                    <span class="badge bg-danger"><?php echo $stats['low_stock_items']; ?> Items</span>
                                </div>
                                <div class="card-body">
                                    <?php if ($low_stock->num_rows > 0): ?>
                                        <?php while ($product = $low_stock->fetch_assoc()): ?>
                                            <div class="low-stock-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                                                        <p class="mb-1 text-muted small">Code: <?php echo $product['product_code']; ?></p>
                                                    </div>
                                                    <button class="btn btn-sm btn-primary" onclick="reorderProduct(<?php echo $product['product_id']; ?>)">
                                                        <i class="fas fa-shopping-cart"></i> Reorder
                                                    </button>
                                                </div>
                                                <div class="progress mt-2" style="height: 8px;">
                                                    <?php
                                                    $percentage = ($product['current_stock'] / $product['maximum_stock']) * 100;
                                                    $percentage = min($percentage, 100);
                                                    ?>
                                                    <div class="progress-bar bg-<?php echo $percentage < 20 ? 'danger' : 'warning'; ?>"
                                                        style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <div class="d-flex justify-content-between mt-1">
                                                    <small>Current: <?php echo $product['current_stock']; ?> <?php echo $product['unit']; ?></small>
                                                    <small>Min: <?php echo $product['minimum_stock']; ?> <?php echo $product['unit']; ?></small>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-4">No low stock items</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Monthly Summary -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Monthly Summary</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <h4><?php echo format_money($stats['monthly_purchases']); ?></h4>
                                            <p class="text-muted small">This Month</p>
                                        </div>
                                        <div class="col-6">
                                            <h4><?php echo format_money($stats['today_purchases']); ?></h4>
                                            <p class="text-muted small">Today</p>
                                        </div>
                                    </div>
                                    <div style="position: relative; height: 300px;">
                                        <canvas id="monthlyChart" height="200"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchase Orders Tab -->
                <div class="tab-pane fade" id="purchase-orders" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">All Purchase Orders</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                <i class="fas fa-plus-circle me-2"></i>Create PO
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="poTable">
                                    <thead>
                                        <tr>
                                            <th>PO Number</th>
                                            <th>Supplier</th>
                                            <th>Order Date</th>
                                            <th>Expected Delivery</th>
                                            <th>Total Amount</th>
                                            <th>Delivery Status</th>
                                            <th>Payment Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $all_pos_query = "SELECT po.*, s.supplier_name FROM procurement_purchase_orders po 
                                                            JOIN procurement_suppliers s ON po.supplier_id = s.supplier_id 
                                                            ORDER BY po.order_date ";
                                        $stmt = $db->prepare($all_pos_query);
                                        $stmt->execute();
                                        $all_pos = $stmt->get_result();
                                        while ($po = $all_pos->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $po['po_number']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                                                <td><?php echo format_date($po['order_date']); ?></td>
                                                <td><?php echo $po['expected_delivery'] ? format_date($po['expected_delivery']) : '-'; ?></td>
                                                <td><?php echo format_money($po['total_amount']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo $po['delivery_status'] == 'Completed' ? 'success' : ($po['delivery_status'] == 'Partial' ? 'warning' : ($po['delivery_status'] == 'Pending' ? 'info' : 'secondary'));
                                                                            ?>">
                                                        <?php echo $po['delivery_status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo $po['payment_status'] == 'Paid' ? 'success' : ($po['payment_status'] == 'Partial' ? 'warning' : 'danger');
                                                                            ?>">
                                                        <?php echo $po['payment_status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewEntity('../../api/get_purchase_order.php',<?php echo $po['po_id']; ?>,'Purchsase Order')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($po['delivery_status'] != 'Completed'): ?>
                                                        <button class="btn btn-sm btn-success" onclick="receivePO(<?php echo $po['po_id']; ?>)">
                                                            <i class="fas fa-truck-loading"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Suppliers Tab -->
                <div class="tab-pane fade" id="suppliers" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Suppliers</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                <i class="fas fa-plus-circle me-2"></i>Add Supplier
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="suppliersTable">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Supplier Name</th>
                                            <th>Contact Person</th>
                                            <th>Phone</th>
                                            <th>Email</th>
                                            <th>Category</th>
                                            <th>Rating</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $suppliers_query = "SELECT * FROM procurement_suppliers 
                                                           ORDER BY supplier_name ASC";
                                        $stmt = $db->prepare($suppliers_query);
                                        $stmt->execute();
                                        $suppliers = $stmt->get_result();
                                        while ($supplier = $suppliers->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><?php echo $supplier['supplier_code']; ?></td>
                                                <td><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                                                <td><?php echo htmlspecialchars($supplier['contact_person']); ?></td>
                                                <td><?php echo $supplier['phone']; ?></td>
                                                <td><?php echo $supplier['email']; ?></td>
                                                <td><?php echo $supplier['category']; ?></td>
                                                <td>
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $supplier['rating'] ? 'text-warning' : 'text-secondary'; ?>"></i>
                                                    <?php endfor; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $supplier['status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo $supplier['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewSupplier(<?php echo $supplier['supplier_id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="editSupplier(<?php echo $supplier['supplier_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products Tab -->
                <div class="tab-pane fade" id="products" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Products</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="fas fa-plus-circle me-2"></i>Add Product
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="productsTable">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Unit</th>
                                            <th>Stock</th>
                                            <th>Unit Price</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $products_query = "SELECT * FROM procurement_products 
                                                          ORDER BY product_name ASC";
                                        $products = $db->query($products_query);
                                        while ($product = $products->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><?php echo $product['product_code']; ?></td>
                                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                <td><?php echo $product['category']; ?></td>
                                                <td><?php echo $product['unit']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="<?php echo $product['current_stock'] <= $product['minimum_stock'] ? 'text-danger fw-bold' : ''; ?>">
                                                            <?php echo $product['current_stock']; ?>
                                                        </span>
                                                        <?php if ($product['current_stock'] <= $product['minimum_stock']): ?>
                                                            <i class="fas fa-exclamation-circle text-danger ms-2" title="Low Stock"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><?php echo format_money($product['unit_price']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $product['status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo $product['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewProduct(<?php echo $product['product_id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo $product['product_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Purchase Order Modal -->
    <div class="modal fade" id="createPOModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Purchase Order</h5>
                    <button type="button" class="btn-close"></button>
                </div>
                <form action="index.php" method="POST" id="poForm">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="form" value="po">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Supplier</label>
                                <select class="form-control select2" name="supplier_id" required>
                                    <option value="">Select Supplier</option>
                                    <?php
                                    $supplierss = $db->query("SELECT supplier_id, supplier_name FROM procurement_suppliers WHERE status = 'Active'");
                                    while ($sup = $supplierss->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $sup['supplier_id']; ?>"><?php echo $sup['supplier_name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Order Date</label>
                                <input type="date" class="form-control" name="order_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Expected Delivery</label>
                                <input type="date" class="form-control" name="expected_delivery">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Shipping Address</label>
                            <textarea class="form-control" name="shipping_address" rows="2">Same as company address</textarea>
                        </div>

                        <h6 class="mt-4 mb-3">Order Items</h6>
                        <table class="table" id="poItemsTable">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Discount</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="poItems">
                                <tr class="po-item">
                                    <td>
                                        <select class="form-control product-select" name="items[0][product_id]" required>
                                            <option value="">Select Product</option>
                                            <?php
                                            $products = $db->query("SELECT product_id, product_name, unit_price FROM procurement_products WHERE status = 'Active'");
                                            while ($prod = $products->fetch_assoc()):
                                            ?>
                                                <option value="<?php echo $prod['product_id']; ?>" data-price="<?php echo $prod['unit_price']; ?>">
                                                    <?php echo $prod['product_name']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control quantity" name="items[0][quantity]" required min="1">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control unit-price" name="items[0][unit_price]" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control discount" name="items[0][discount]" value="0">
                                    </td>
                                    <td class="item-total">0.00</td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm remove-item" disabled>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6">
                                        <button type="button" class="btn btn-sm btn-primary" id="addItem">
                                            <i class="fas fa-plus"></i> Add Item
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                    <td><strong id="subtotal">0.00</strong></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end">Tax (16%):</td>
                                    <td id="tax">0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                    <td><strong id="grand-total">0.00</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Purchase Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View entityb modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Supplier Modal -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="form" value="supplier">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier Code</label>
                                <input type="text" class="form-control" name="supplier_code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier Name</label>
                                <input type="text" class="form-control" name="supplier_name" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Person</label>
                                <input type="text" class="form-control" name="contact_person">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Website</label>
                                <input type="url" class="form-control" name="website">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <input type="text" class="form-control" name="category" placeholder="e.g., Building Materials, Electronics">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tax Number</label>
                                <input type="text" class="form-control" name="tax_number">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Terms</label>
                                <select class="form-control" name="payment_terms">
                                    <option value="Cash on Delivery">Cash on Delivery</option>
                                    <option value="Net 15">Net 15</option>
                                    <option value="Net 30">Net 30</option>
                                    <option value="Net 60">Net 60</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Credit Limit</label>
                                <input type="number" step="0.01" class="form-control" name="credit_limit">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Supplier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addProductForm" action="products.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="form" value="product">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product Code</label>
                            <input id="product_code" type="text" class="form-control" name="product_code" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input id="product_name" type="text" class="form-control" name="product_name" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <input id="category" type="text" class="form-control" name="category">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sub-Category</label>
                                <input id="sub_category" type="text" class="form-control" name="sub_category">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unit</label>
                                <select id="unit" class="form-control" name="unit" required>
                                    <option value="pcs">Pieces (pcs)</option>
                                    <option value="kg">Kilograms (kg)</option>
                                    <option value="liters">Liters</option>
                                    <option value="meters">Meters</option>
                                    <option value="boxes">Boxes</option>
                                    <option value="bags">Bags</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Min Stock</label>
                                <input id="minimum_stock" type="number" class="form-control" name="minimum_stock" value="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max Stock</label>
                                <input id="maximum_stock" type="number" class="form-control" name="maximum_stock" value="1000">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Reorder Level</label>
                                <input id="reorder_level" type="number" class="form-control" name="reorder_level" value="10">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unit Price</label>
                                <input id="unit_price" type="number" step="0.01" class="form-control" name="unit_price" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Selling Price</label>
                                <input id="selling_price" type="number" step="0.01" class="form-control" name="selling_price">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea id="description" class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tax Rate (%)</label>
                            <input id="tax_rate" type="number" step="0.01" class="form-control" name="tax_rate" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input id="location" type="text" class="form-control" name="location">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="number" class="form-control" name="current_stock" id="current_stock" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Barcode</label>
                            <input type="text" class="form-control" name="barcode" id="barcode" value="">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Product Image</label>
                            <input type="file" class="form-control" id="image_file" name="image_file" accept="image/*">
                            <small class="text-muted">Upload a product image (JPG, PNG, GIF)</small>
                        </div>
                        <input type="hidden" name="image_path" id="image_path" value="222222">
                        <input type="hidden" name="status" id="status" value="Active">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Receive Stock Modal -->
    <div class="modal fade" id="receiveStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Receive Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/receive-stock.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Purchase Order</label>
                            <select class="form-control" name="po_id" required>
                                <option value="">Select PO</option>
                                <?php
                                $pos = $db->query("SELECT po_id, po_number FROM procurement_purchase_orders WHERE delivery_status != 'Completed'");
                                while ($po = $pos->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $po['po_id']; ?>"><?php echo $po['po_number']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div id="poItemsList"></div>

                        <div class="mb-3">
                            <label class="form-label">Receiving Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Receive Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>

    <script>
        let viewModalInstance;

        $(document).ready(function() {
            // Initialize DataTables
            $('#poTable').DataTable({
                order: [
                    [2, 'desc']
                ]
            });
            $('#suppliersTable').DataTable();
            $('#productsTable').DataTable();
            $('#inventoryTable').DataTable({
                order: [
                    [0, 'desc']
                ]
            });

            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#createPOModal')
            });

            // Initialize monthly chart
            const ctx = document.getElementById('monthlyChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Purchases',
                        data: [12000, 19000, 15000, 25000, 22000, 30000, 28000, 32000, 29000, 35000, 40000, 45000],
                        borderColor: '#4cc9f0',
                        backgroundColor: 'rgba(76, 201, 240, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            // Add PO item
            let itemCount = 1;
            $('#addItem').click(function() {
                let newRow = `
                    <tr class="po-item">
                        <td>
                            <select class="form-control product-select" name="items[${itemCount}][product_id]" required>
                                <option value="">Select Product</option>
                                <?php
                                $products = $db->query("SELECT product_id, product_name, unit_price FROM procurement_products WHERE status = 'Active'");
                                while ($prod = $products->fetch_assoc()):
                                ?>
                                <option value="<?php echo $prod['product_id']; ?>" data-price="<?php echo $prod['unit_price']; ?>">
                                    <?php echo $prod['product_name']; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </td>
                        <td>
                            <input type="number" class="form-control quantity" name="items[${itemCount}][quantity]" required min="1">
                        </td>
                        <td>
                            <input type="number" step="0.01" class="form-control unit-price" name="items[${itemCount}][unit_price]" required>
                        </td>
                        <td>
                            <input type="number" step="0.01" class="form-control discount" name="items[${itemCount}][discount]" value="0">
                        </td>
                        <td class="item-total">0.00</td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm remove-item">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    </tr>
                `;
                $('#poItems').append(newRow);
                itemCount++;
            });

            // Remove PO item
            $(document).on('click', '.remove-item', function() {
                if ($('.po-item').length > 1) {
                    $(this).closest('tr').remove();
                    calculateTotal();
                }
            });

            // Calculate item total
            $(document).on('change keyup', '.quantity, .unit-price, .discount, .product-select', function() {
                let row = $(this).closest('tr');
                let quantity = parseFloat(row.find('.quantity').val()) || 0;
                let price = parseFloat(row.find('.unit-price').val()) || 0;
                let discount = parseFloat(row.find('.discount').val()) || 0;

                let subtotal = quantity * price;
                let total = subtotal - discount;

                row.find('.item-total').text(formatMoney(total));
                calculateTotal();
            });

            // Set unit price when product selected
            $(document).on('change', '.product-select', function() {
                let price = $(this).find(':selected').data('price');
                $(this).closest('tr').find('.unit-price').val(price).trigger('change');
            });

            function calculateTotal() {
                let subtotal = 0;
                $('.po-item').each(function() {
                    let totalText = $(this).find('.item-total').text();
                    subtotal += parseFloat(totalText.replace(/[^0-9.-]+/g, '')) || 0;
                });

                let tax = subtotal * 0.16;
                let grandTotal = subtotal + tax;

                $('#subtotal').text(formatMoney(subtotal));
                $('#tax').text(formatMoney(tax));
                $('#grand-total').text(formatMoney(grandTotal));
            }

            function formatMoney(amount) {
                return 'GHS ' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            }

            // Load PO items for receiving
            $('select[name="po_id"]').change(function() {
                let poId = $(this).val();
                if (poId) {
                    $.get('ajax/get-po-items.php', {
                        po_id: poId
                    }, function(data) {
                        $('#poItemsList').html(data);
                    });
                }
            });
        });

        function viewPO(id) {
            window.location.href = 'view-po.php?id=' + id;
        }

        function receivePO(id) {
            window.location.href = 'receive-po.php?id=' + id;
        }

        function reorderProduct(id) {
            $('#createPOModal').modal('show');
            // Pre-select product and set quantity
        }

        function showModal(title, data) {
            let html = '<div class="container-fluid">';
            for (const key in data) {
                if (Array.isArray(data[key])) {
                    // For nested arrays (like PO line items)
                    html += `<h6 class="mt-3">${key.replace(/_/g,' ')}</h6><ul>`;
                    data[key].forEach(item => {
                        html += '<li>' + Object.entries(item)
                            .map(([k, v]) => `${k}: ${v}`)
                            .join(', ') + '</li>';
                    });
                    html += '</ul>';
                } else {
                    html += `<p><strong>${key.replace(/_/g,' ')}</strong>: ${data[key]}</p>`;
                }
            }
            html += '</div>';

            document.getElementById('modalBody').innerHTML = html;
            document.querySelector('#viewModal .modal-title').innerText = title;

            if (!viewModalInstance) {
                viewModalInstance = new bootstrap.Modal(document.getElementById('viewModal'));
            }
            viewModalInstance.show();
        }

        function viewSupplier(id) {
            window.location.href = 'view-supplier.php?id=' + id;
        }

        function editSupplier(id) {
            window.location.href = 'edit-supplier.php?id=' + id;
        }

        function viewProduct(id) {
            window.location.href = 'view-product.php?id=' + id;
        }

        function editProduct(id) {
            window.location.href = 'edit-product.php?id=' + id;
        }

        function viewEntity(endpoint, id, title) {
            fetch(`${endpoint}?id=${id}`)
                .then(res => res.json())
                .then(data => showModal(title, data))
                .catch(err => alert('Error loading data: ' + err));
        }

        function viewPO(id) {
            window.location.href = 'view-po.php?id=' + id;
        }
    </script>
</body>

</html>