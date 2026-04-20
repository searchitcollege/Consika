<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/session.php';

$session->requireLogin();

$current_user = currentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

global $db;

//Access Check
if ($current_user['company_type'] != 'Works' && ($role != 'SuperAdmin' && $role != 'CompanyAdmin' && $role != 'Manager')) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['form_type'] === 'add_material') {
        //    GET FORM DATA
        $product_code   = $_POST['product_code'] ?? '';
        $product_name   = $_POST['product_name'] ?? '';
        $category       = $_POST['category'] ?? '';
        $sub_category   = $_POST['sub_category'] ?? '';
        $unit           = $_POST['unit'] ?? '';
        $unit_price     = $_POST['unit_price'] ?? 0;
        $current_stock  = $_POST['current_stock'] ?? 0;
        $minimum_stock  = $_POST['minimum_stock'] ?? 0;
        $maximum_stock  = $_POST['maximum_stock'] ?? 0;
        $reorder_level  = $_POST['reorder_level'] ?? 0;
        $tax_rate       = $_POST['tax_rate'] ?? 0;
        $description    = $_POST['description'] ?? '';
        $location       = $_POST['location'] ?? '';
        $barcode        = $_POST['barcode'] ?? '';
        $status         = $_POST['status'] ?? 'Active';

        //    INSERT PRODUCT
        $photo_path = null;

        if (!empty($_FILES['photos']['name'][0])) {

            $upload_dir = '../../../uploads/materials/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $ext = strtolower(pathinfo($_FILES['photos']['name'][0], PATHINFO_EXTENSION));
            $filename = 'mat_' . time() . '.' . $ext;

            if (move_uploaded_file($_FILES['photos']['tmp_name'][0], $upload_dir . $filename)) {
                $photo_path = 'uploads/materials/' . $filename;
            }
        }

        $sql = "INSERT INTO procurement_products (
                        product_code,
                        product_name,
                        category,
                        sub_category,
                        unit,
                        unit_price,
                        current_stock,
                        minimum_stock,
                        maximum_stock,
                        reorder_level,
                        tax_rate,
                        description,
                        location,
                        barcode,
                        status,
                        image_path
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $db->prepare($sql);

        $stmt->bind_param(
            "sssssdiiiidsssss",
            $product_code,
            $product_name,
            $category,
            $sub_category,
            $unit,
            $unit_price,
            $current_stock,
            $minimum_stock,
            $maximum_stock,
            $reorder_level,
            $tax_rate,
            $description,
            $location,
            $barcode,
            $status,
            $photo_path
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = "Material added successfully.";
        } else {
            $_SESSION['error'] = "Error adding material.";
        }

        header("Location: materials.php");
        exit();
    }

    if ($_POST['form_type'] === 'create_po') {
        //Handle purchase orders createion 
        //PO draft until everything is approved and ready, then redirect to edit page to add items and finalize the order
        // Handle purchase order creation
        $supplier_id = $_POST['supplier_id'] ?? null;
        $order_date = $_POST['order_date'] ?? null;
        $expected_delivery = $_POST['expected_delivery'] ?? null;
        $notes = $_POST['notes'] ?? null;
        $items = $_POST['items'] ?? [];

        if (!$supplier_id || !$order_date || empty($items)) {
            $_SESSION['error'] = 'Please fill required fields and add at least one item.';
            header("Location: materials.php");
            exit();
        }

        // Draft status
        $delivery_status = "Pending";
        $payment_status = "Unpaid";
        $approval_status = "Pending";
        $created_by = $current_user['user_id'];

        // for total amount
        $total_amount = 0;
        foreach ($items as $item) {
            if (!empty($item['product_id'])) {
                $quantity = $item['quantity'];
                $price = $item['unit_price'];
                $discount = $item['discount'] ?? 0;
                $total_amount += ($quantity * $price) - $discount;
            }
        }

        $tax = $total_amount * 0.16;
        $total_amount += $tax;

        // Generate PO number
        $po_number = "PO-" . date("Y") . "-" . rand(1000, 9999);
        $sql = "INSERT INTO procurement_purchase_orders
                 (po_number, supplier_id, order_date, expected_delivery, notes, delivery_status, payment_status, total_amount, approval_status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);

        $stmt->bind_param(
            "sisssssdss",
            $po_number,
            $supplier_id,
            $order_date,
            $expected_delivery,
            $notes,
            $delivery_status,
            $payment_status,
            $total_amount, // Will be updated later when items are added
            $approval_status,
            $created_by
        );

        if ($stmt->execute()) {

            $po_id = $stmt->insert_id;

            // Insert PO items
            foreach ($items as $item) {

                if (!empty($item['product_id'])) {

                    $product_id = $item['product_id'];
                    $quantity = $item['quantity'];
                    $price = $item['unit_price'];
                    $discount = $item['discount'] ?? 0;
                    $total_price = $total_amount;

                    $sql_item = "INSERT INTO procurement_po_items
                (po_id, product_id, quantity, unit_price, discount, total_price)
                VALUES (?, ?, ?, ?, ?, ?)";

                    $stmt_item = $db->prepare($sql_item);
                    $stmt_item->bind_param("iiiddd", $po_id, $product_id, $quantity, $price, $discount, $total_price);
                    $stmt_item->execute();
                }
            }

            $_SESSION['success'] = "Purchase Order created as draft.";
        } else {

            $_SESSION['error'] = "Failed to create purchase order.";
        }

        header("Location: materials.php");
        exit();
    }
}

// Get statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM procurement_products WHERE category = 'Building Materials' AND current_stock <= minimum_stock) as low_materials";
$stmt = $db->prepare($stats_query);
// $stmt->bind_param("i", $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Works & Construction - <?php echo APP_NAME; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- FullCalendar -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css' rel='stylesheet' />

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Custom CSS -->
    <link href="../../assets/css/style.css" rel="stylesheet">

    <!-- Custom Styles Specific to workd -->
    <link href="../../assets/css/works/style.css" rel="stylesheet">
</head>

<body class="module-works">
    <div class="container-fluid p-0 module-works works">
        <!-- ?Inclusde sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Main content -->
        <div class="main-content">
            <!-- Module Header -->
            <div class="department-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-2">Works & Construction - Materials</h1>
                        <p class="mb-0 opacity-75 text-white">Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>!</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-light text-dark p-2">
                            <i class="far fa-calendar me-2"></i><?php echo date('F j, Y'); ?>
                        </span>
                        <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #b02a37);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['low_materials']; ?></h3>
                        <p class="stat-label">Low Materials</p>
                    </div>
                </div>
            </div>

            <!-- Low Materials List and materials uisage today list repectively -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Low Stock Alert</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $low_stock_query = "SELECT * FROM procurement_products WHERE category = 'Building Materials' AND current_stock <= minimum_stock";
                            $low_stock = $db->query($low_stock_query);
                            if ($low_stock->num_rows > 0):
                                while ($material = $low_stock->fetch_assoc()):
                            ?>
                                    <div class="material-alert">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?php echo htmlspecialchars($material['product_name']); ?></strong>
                                                <br>
                                                <small>Current: <?php echo $material['current_stock']; ?> <?php echo $material['unit']; ?></small>
                                                <br>
                                                <small>Min: <?php echo $material['minimum_stock']; ?> <?php echo $material['unit']; ?></small>
                                            </div>
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                                Order
                                            </button>
                                        </div>
                                    </div>
                                <?php
                                endwhile;
                            else:
                                ?>
                                <p class="text-muted text-center">No low stock items</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Material Usage Today</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $usage_query = "SELECT pm.*, m.product_name, p.project_name, m.unit
                                                   FROM works_project_materials pm
                                                   JOIN procurement_products m ON pm.material_id = m.product_id
                                                   JOIN works_projects p ON pm.project_id = p.project_id
                                                   WHERE DATE(pm.date_used) = CURDATE()
                                                   ORDER BY pm.created_at DESC LIMIT 5";
                            $usage = $db->query($usage_query);
                            if ($usage->num_rows > 0):
                                while ($use = $usage->fetch_assoc()):
                            ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <strong><?php echo htmlspecialchars($use['product_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $use['project_name']; ?></small>
                                        </div>
                                        <span class="badge bg-info">
                                            <?php echo $use['quantity']; ?> <?php echo $use['unit']; ?>
                                        </span>
                                    </div>
                                <?php
                                endwhile;
                            else:
                                ?>
                                <p class="text-muted text-center">No usage recorded today</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Materials Inventory table -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Materials Inventory</h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                            <i class="fas fa-plus-circle me-2"></i>Add Material
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="materialsTable">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Material Name</th>
                                        <th>Category</th>
                                        <th>Unit</th>
                                        <th>Stock</th>
                                        <th>Unit Cost</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $materials_query = "SELECT * FROM procurement_products WHERE category = 'Building Materials' ORDER BY product_name ASC";
                                    $materials = $db->query($materials_query);
                                    while ($mat = $materials->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td><?php echo $mat['product_code']; ?></td>
                                            <td><?php echo htmlspecialchars($mat['product_name']); ?></td>
                                            <td><?php echo $mat['category']; ?></td>
                                            <td><?php echo $mat['unit']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="<?php echo $mat['current_stock'] <= $mat['minimum_stock'] ? 'text-danger fw-bold' : ''; ?>">
                                                        <?php echo $mat['current_stock']; ?>
                                                    </span>
                                                    <?php if ($mat['current_stock'] <= $mat['minimum_stock']): ?>
                                                        <i class="fas fa-exclamation-circle text-danger ms-2"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo format_money($mat['unit_price']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php
                                                                        echo $mat['status'] == 'Available' ? 'success' : ($mat['status'] == 'Low Stock' ? 'warning' : 'danger');
                                                                        ?>">
                                                    <?php echo $mat['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <!-- <button class="btn btn-sm btn-info" onclick="viewMaterial(<?php echo $mat['product_id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button> -->
                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal" onclick="editMaterial(<?php echo $mat['product_id']; ?>)">
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

        <!-- Create Purchase Order Modal -->
        <div class="modal fade" id="createPOModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="materials.php" method="POST" id="poForm">
                        <input type="hidden" name="form_type" value="create_po">
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Supplier</label>
                                    <select class="form-control select2" name="supplier_id" required>
                                        <option value="">Select Supplier</option>
                                        <?php
                                        $suppliers = $db->query("SELECT supplier_id, supplier_name FROM procurement_suppliers WHERE status = 'Active'");
                                        while ($sup = $suppliers->fetch_assoc()):
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
                                                $products = $db->query("SELECT product_id, product_name, unit_price FROM procurement_products WHERE status = 'Active' AND category = 'Building Materials'");
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

        <!--TODO: make sure add material goes to product tabel -->
        <!-- Add Material Modal -->
        <div class="modal fade" id="addMaterialModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Material</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="materials.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="form_type" value="add_material">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Material Code</label>
                                <input type="text" class="form-control" name="product_code" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Material Name</label>
                                <input type="text" class="form-control" name="product_name" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category</label>
                                    <input type="hidden" name="category" value="Building Materials">
                                    <select class="form-control" name="sub_category" required>
                                        <option value="Cement">Cement</option>
                                        <option value="Sand">Sand</option>
                                        <option value="Aggregate">Aggregate</option>
                                        <option value="Steel">Steel</option>
                                        <option value="Timber">Timber</option>
                                        <option value="Electrical">Electrical</option>
                                        <option value="Plumbing">Plumbing</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Unit</label>
                                    <select class="form-control" name="unit" required>
                                        <option value="bags">Bags</option>
                                        <option value="tons">Tons</option>
                                        <option value="kg">Kilograms</option>
                                        <option value="pieces">Pieces</option>
                                        <option value="meters">Meters</option>
                                        <option value="liters">Liters</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Unit Cost</label>
                                    <input type="number" step="0.01" class="form-control" name="unit_price" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Current Stock</label>
                                    <input type="number" step="0.01" class="form-control" name="current_stock" value="0">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Minimum Stock</label>
                                    <input type="number" step="0.01" class="form-control" name="minimum_stock" value="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Maximum Stock</label>
                                    <input type="number" step="0.01" class="form-control" name="maximum_stock" value="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Re-Order Level</label>
                                    <input type="number" step="0.01" class="form-control" name="reorder_level" value="0">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tax-Rate</label>
                                    <input type="number" step="0.01" class="form-control" name="tax_rate" value="0">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Location</label>
                                <textarea class="form-control" name="location" rows="2"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Barcode</label>
                                    <input type="number" step="0.01" class="form-control" name="barcode" value="0">
                                </div>
                                <input type="hidden" name="status" value="Active">
                                <!-- Site Photos -->
                                <div class="mb-1">
                                    <label class="form-label fw-semibold">Site Photos</label>
                                    <input type="file" class="form-control" name="photos[]" id="dailyReportPhotos"
                                        multiple accept="image/*">
                                    <div class="text-muted small mt-1">
                                        <i class="fas fa-info-circle me-1"></i>
                                        You can select multiple photos. Max 5 MB each.
                                    </div>
                                    <!-- Preview thumbnails -->
                                    <div id="photoPreviewRow" class="d-flex flex-wrap gap-2 mt-2"></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Material</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js'></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#createPOModal')
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
                    $.get('../../api/get-po-items.php', {
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
    </script>
</body>

</html>