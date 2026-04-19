<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('works', 'create')) {
    $_SESSION['error'] = 'You do not have permission to add tenants.';
    header('Location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

global $db;

$current_user = currentUser();
$company_id   = $session->getCompanyId();

// Resolve company_id for SuperAdmin
if (empty($company_id)) {
    $result     = $db->query("SELECT company_id FROM companies WHERE company_type = 'Estate' LIMIT 1");
    $row        = $result->fetch_assoc();
    $company_id = (int)($row['company_id'] ?? 0);
}

if (empty($company_id)) {
    $_SESSION['error'] = 'Estate company not found.';
    header('Location: ../index.php');
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


    // ── Activity log ─────────────────────────────────────────────────────────────
    $log_desc = "Added new product: {$product_name}";
    $log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $module   = 'works';
    
    $log = $db->prepare("
        INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
        VALUES (?, 'Add Product', ?, ?, ?, ?)
    ");
    $log->bind_param("isssi", $created_by, $log_desc, $log_ip, $module, $tenant_id);
    $log->execute();
    $log->close();
    
    $_SESSION['success'] = "Product '({$product_name})' added successfully.";
    header('Location: ../index.php');
    exit();
}
