<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/session.php';

$session->requireLogin();

$current_user = currentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

// Access check
if ($current_user['company_type'] != 'Procurement' && ($role != 'SuperAdmin' && $role != 'CompanyAdmin' && $role != 'Manager')) {
    $_SESSION['error'] = 'Access denied. Procurement department only.';
    header('Location: ../../login.php');
    exit();
}

global $db;

//Handle purchase orders createion 
//PO draft until everything is approved and ready, then redirect to edit page to add items and finalize the order
// Handle purchase order creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'add') {

    $supplier_id = $_POST['supplier_id'] ?? null;
    $order_date = $_POST['order_date'] ?? null;
    $expected_delivery = $_POST['expected_delivery'] ?? null;
    $notes = $_POST['notes'] ?? null;
    $items = $_POST['items'] ?? [];

    if (!$supplier_id || !$order_date || empty($items)) {
        $_SESSION['error'] = 'Please fill required fields and add at least one item.';
        header("Location: purchase.php");
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

    header("Location: purchase.php");
    exit();
}

//for pending orders and monthly purchase statistics 
$pending_orders_sql = "SELECT po.*, s.supplier_name FROM procurement_purchase_orders po 
                       JOIN procurement_suppliers s ON po.supplier_id = s.supplier_id 
                       WHERE po.delivery_status = 'Pending'";
$stmt = $db->prepare($pending_orders_sql);
// $stmt->bind_param("i", $company_id);
$stmt->execute();
$pending_orders = $stmt->get_result();
$pending_count = $pending_orders->num_rows;

$monthly_stats_sql = "SELECT MONTH(order_date) AS month, YEAR(order_date) AS year, SUM(total_amount) AS total 
                      FROM procurement_purchase_orders 
                      WHERE YEAR(order_date) = YEAR(CURDATE()) 
                      GROUP BY MONTH(order_date), YEAR(order_date)";
$stmt = $db->prepare($monthly_stats_sql);
// $stmt->bind_param("i", $company_id);
$stmt->execute();
$monthly_stats = $stmt->get_result();
$total_monthly = 0;
while ($row = $monthly_stats->fetch_assoc()) {
    $total_monthly += $row['total'];
}

// Recent purchase ordedrs and recent deliveries
$recent_orders_sql = "SELECT po.*, s.supplier_name FROM procurement_purchase_orders po 
                       JOIN procurement_suppliers s ON po.supplier_id = s.supplier_id 
                     ORDER BY po.order_date DESC LIMIT 5";
$stmt = $db->prepare($recent_orders_sql);
// $stmt->bind_param("i", $company_id);
$stmt->execute();
$recent_orders = $stmt->get_result();

$recent_deliveries_sql = "SELECT po.*, s.supplier_name FROM procurement_purchase_orders po 
                           JOIN procurement_suppliers s ON po.supplier_id = s.supplier_id 
                           WHERE po.delivery_status = 'Completed' AND po.expected_delivery IS NOT NULL AND po.expected_delivery >= CURDATE()
                           ORDER BY po.expected_delivery DESC LIMIT 5";
$stmt = $db->prepare($recent_deliveries_sql);
// $stmt->bind_param("i", $company_id);
$stmt->execute();
$recent_deliveries = $stmt->get_result();

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

<body>
    <div class="container-fluid p-0 module-procurement products">
        <!-- Include sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Main content -->
        <div class="main-content">
            <!-- Header -->
            <div class="department-header">
                <div class="d-flex justify-content-between align-items-center" style="margin-bottom: 10px;">
                    <div>
                        <h2 class="mb-2">Purchase Orders</h2>
                        <p class="mb-0 opacity-75">Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>!</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-light text-dark p-2">
                            <i class="far fa-calendar me-2"></i><?php echo date('F j, Y'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- stats row -->
            <div class="stat-card">
                <div>
                    <div class="stat-icon" style="background: linear-gradient(135deg, #f72585, #b5179e);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div style="display: block; margin-left: 20px;">
                        <h3 class="stat-value"><?php echo $pending_count; ?></h3>
                        <p class="stat-label">Pending Orders</p>
                    </div>
                </div>
                <div>
                    <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #b02a37);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div style="display: block; margin-left: 20px;">
                        <h3 class="stat-value"><?php echo format_money($total_monthly); ?></h3>
                        <p class="stat-label">Monthly Purchases</p>
                    </div>
                </div>
            </div>

            <!-- Overview Tab -->
            <div class="row mb-4">
                <!-- Recent Purchase Orders -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Purchase Orders</h5>
                            <!-- <a href="purchase-orders.php" class="btn btn-sm btn-primary">View All</a> -->
                        </div>
                        <div class="card-body">
                            <?php if ($recent_orders->num_rows > 0): ?>
                                <?php while ($po = $recent_orders->fetch_assoc()): ?>
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
                </div>

                <!-- Low Stock Alert -->
                <div class="col-md-6">
                    <!-- Recent Deliveries -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Deliveries</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($recent_deliveries->num_rows > 0): ?>
                                <?php while ($delivery = $recent_deliveries->fetch_assoc()): ?>
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

                    <!-- Monthly Summary -->
                    <!-- <div class="card mt-4">
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
                    </div> -->
                </div>
            </div>

            <!-- Purchase orders table -->
            <div class="card mb-4">
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
                                $all_pos_query = "SELECT po.*, s.supplier_name 
                                                         FROM procurement_purchase_orders po
                                                         JOIN procurement_suppliers s ON po.supplier_id = s.supplier_id
                                                         WHERE s.company_id = ?
                                                         ORDER BY po.created_at DESC";
                                $stmt = $db->prepare($all_pos_query);
                                $stmt->bind_param("i", $company_id);
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
                                                <button class="btn btn-sm btn-success" onclick="window.location.href='approvals.php?id=<?php echo $po['po_id']; ?>'">
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
    </div>
    <!-- Create Purchase Order Modal -->
    <div class="modal fade" id="createPOModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Purchase Order</h5>
                    <button type="button" class="btn-close"></button>
                </div>
                <form action="purchase.php" method="POST" id="poForm">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Supplier</label>
                                <select class="form-control select2" name="supplier_id" required>
                                    <option value="">Select Supplier</option>
                                    <?php
                                    $suppliers = $db->query("SELECT supplier_id, supplier_name FROM procurement_suppliers WHERE company_id = $company_id AND status = 'Active'");
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
            // Initialize DataTable
            $('#poTable').DataTable({
                order: [
                    [2, 'desc']
                ]
            });

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

        function viewEntity(endpoint, id, title) {
            fetch(`${endpoint}?id=${id}`)
                .then(res => res.json())
                .then(data => showModal(title, data))
                .catch(err => alert('Error loading data: ' + err));
        }
    </script>
</body>