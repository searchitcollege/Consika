<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db_connection.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

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

// Handle product actions (add/edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            /*
            ========================================
            ADD PRODUCT
            ========================================
            */
            case 'add':
                $product_code   = $db->escapeString($_POST['product_code']);
                $product_name   = $db->escapeString($_POST['product_name']);
                $category       = $db->escapeString($_POST['category']);
                $sub_category   = $db->escapeString($_POST['sub_category']);
                $description    = $db->escapeString($_POST['description']);
                $unit           = $db->escapeString($_POST['unit']);
                $minimum_stock  = intval($_POST['minimum_stock']);
                $maximum_stock  = intval($_POST['maximum_stock']);
                $current_stock  = intval($_POST['current_stock']);
                $reorder_level  = intval($_POST['reorder_level']);
                $unit_price     = floatval($_POST['unit_price']);
                $selling_price  = floatval($_POST['selling_price']);
                $tax_rate  = floatval($_POST['tax_rate']);
                $location = $db->escapeString($_POST['location']);
                $barcode = $db->escapeString($_POST['barcode']);
                $image_path = $db->escapeString($_POST['image_path']);
                $status = $db->escapeString($_POST['status']);
                $sql = "INSERT INTO procurement_products 
                        (product_code, product_name, category, sub_category, description, unit, minimum_stock, maximum_stock, current_stock, reorder_level, unit_price, selling_price, tax_rate, location, barcode, image_path, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param(
                    "ssssssiiiidddssss",
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
                    $image_path,
                    $status
                );

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Product added successfully";
                    log_activity(
                        $current_user['user_id'],
                        'Add Product',
                        "Added product: $product_name"
                    );
                } else {
                    $_SESSION['error'] = "Error adding product";
                }
                break;

            /*
            ========================================
            EDIT PRODUCT
            ========================================
            */
            case 'edit':
                $product_id     = intval($_POST['product_id']);
                $product_code   = $db->escapeString($_POST['product_code']);
                $product_name   = $db->escapeString($_POST['product_name']);
                $category       = $db->escapeString($_POST['category']);
                $sub_category   = $db->escapeString($_POST['sub_category']);
                $description    = $db->escapeString($_POST['description']);
                $unit           = $db->escapeString($_POST['unit']);
                $minimum_stock  = intval($_POST['minimum_stock']);
                $maximum_stock  = intval($_POST['maximum_stock']);
                $cost_price     = floatval($_POST['cost_price']);
                $selling_price  = floatval($_POST['selling_price']);
                $status         = $db->escapeString($_POST['status']);
                $sql = "UPDATE procurement_products SET
                        product_code = ?,
                        product_name = ?,
                        category = ?,
                        sub_category = ?,
                        description = ?,
                        unit = ?,
                        minimum_stock = ?,
                        maximum_stock = ?,
                        cost_price = ?,
                        selling_price = ?,
                        status = ?
                        WHERE product_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param(
                    "ssssssiidssi",
                    $product_code,
                    $product_name,
                    $category,
                    $sub_category,
                    $description,
                    $unit,
                    $minimum_stock,
                    $maximum_stock,
                    $cost_price,
                    $selling_price,
                    $status,
                    $product_id
                );
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Product updated successfully";
                    log_activity(
                        $current_user['user_id'],
                        'Edit Product',
                        "Edited product ID: $product_id"
                    );
                } else {
                    $_SESSION['error'] = "Error updating product";
                }
                break;

            /*
            ========================================
            DELETE PRODUCT
            ========================================
            */
            case 'delete':
                $product_id = intval($_POST['product_id']);
                /*
                Check if product has stock movement
                */
                $check = $db->prepare(
                    "SELECT COUNT(*) as count FROM stock_movements WHERE product_id = ?"
                );
                $check->bind_param("i", $product_id);
                $check->execute();
                $result = $check->get_result();
                $count  = $result->fetch_assoc()['count'];
                if ($count > 0) {
                    $_SESSION['error'] = "Cannot delete product with stock history";
                } else {
                    $stmt = $db->prepare(
                        "DELETE FROM products WHERE product_id = ?"
                    );
                    $stmt->bind_param("i", $product_id);
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Product deleted successfully";
                        log_activity(
                            $current_user['user_id'],
                            'Delete Product',
                            "Deleted product ID: $product_id"
                        );
                    } else {
                        $_SESSION['error'] = "Error deleting product";
                    }
                }
                break;

            /*
            ========================================
            REDUCE STOCK
            ========================================
            */
            case 'reduce_stock':

                $product_id = intval($_POST['product_id']);
                $quantity   = floatval($_POST['quantity']);

                // Check current stock
                $check = $db->prepare("SELECT current_stock, product_name FROM procurement_products WHERE product_id=?");
                $check->bind_param("i", $product_id);
                $check->execute();
                $result = $check->get_result();
                $product = $result->fetch_assoc();

                if (!$product) {
                    echo "Product not found";
                    exit;
                }

                if ($quantity > $product['current_stock']) {
                    echo "Cannot reduce more than available stock";
                    exit;
                }

                // Reduce stock
                $update = $db->prepare("
                    UPDATE procurement_products
                    SET current_stock = current_stock - ?
                    WHERE product_id = ?
                ");
                $update->bind_param("di", $quantity, $product_id);
                $update->execute();

                // Log inventory movement
                $movement = $db->prepare("
                INSERT INTO procurement_inventory
                (product_id, quantity, transaction_type, transaction_date)
                VALUES (?, ?, 'Sale', NOW())
                ");
                $movement->bind_param("id", $product_id, $quantity);
                $movement->execute();

                echo "Stock reduced successfully";
                exit;
        }
        header("Location: products.php");
        exit();
    }
}

// Get statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM procurement_products WHERE current_stock <= minimum_stock) as low_stock_items,
                (SELECT COUNT(*) FROM procurement_products WHERE status = 'Active') as total_products";
$stmt = $db->prepare($stats_query);
// $stmt->bind_param("i", $company_id); 
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

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


// Get products for dropdown for stoc k movemtnt chart
$products_dropdown = $db->query("
    SELECT product_id, product_name 
    FROM procurement_products 
    WHERE status='Active'
    ORDER BY product_name ASC
");

// stock movement trend for charts
if (isset($_GET['ajax']) && $_GET['ajax'] == 'stock_trend') {

    $product_id = $_GET['product_id'] ?? 'all';

    $where_po = "";
    $where_inventory = "";

    if ($product_id != 'all') {
        $product_id = intval($product_id);
        $where_po = "AND pi.product_id = $product_id";
        $where_inventory = "AND product_id = $product_id";
    }

    $query = "
    SELECT 
        m.month,
        COALESCE(received.total_received,0) AS received,
        COALESCE(consumed.total_consumed,0) AS consumed,
        COALESCE(received.total_received,0) - COALESCE(consumed.total_consumed,0) AS net_movement
    FROM
    (
        SELECT DATE_FORMAT(order_date,'%Y-%m') AS month
        FROM procurement_purchase_orders
        WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month
    ) m

    LEFT JOIN
    (
        SELECT 
            DATE_FORMAT(po.order_date,'%Y-%m') AS month,
            SUM(pi.quantity) AS total_received
        FROM procurement_purchase_orders po
        JOIN procurement_po_items pi ON po.po_id = pi.po_id
        WHERE po.delivery_status='Completed'
        $where_po
        GROUP BY month
    ) received ON m.month = received.month

    LEFT JOIN
    (
        SELECT 
            DATE_FORMAT(transaction_date,'%Y-%m') AS month,
            SUM(quantity) AS total_consumed
        FROM procurement_inventory
        WHERE transaction_type='Sale'
        $where_inventory
        GROUP BY month
    ) consumed ON m.month = consumed.month

    ORDER BY m.month ASC
    ";

    $result = $db->query($query);

    $data = [
        "labels" => [],
        "received" => [],
        "consumed" => [],
        "net" => []
    ];

    while ($row = $result->fetch_assoc()) {
        $data["labels"][] = $row['month'];
        $data["received"][] = $row['received'];
        $data["consumed"][] = $row['consumed'];
        $data["net"][] = $row['net_movement'];
    }

    echo json_encode($data);
    exit;
}

// Reorder prediction using procurement_inventory 
$reorder_query = "
    SELECT 
    p.product_id,
    p.product_name,
    p.current_stock,
    p.minimum_stock,

    COALESCE(usage_stats.total_used/60,0) AS avg_daily_usage,

    CASE 
    WHEN usage_stats.total_used IS NULL OR usage_stats.total_used=0
    THEN 60  -- If no usage data, assume 60 days of stock
    ELSE (p.current_stock - p.minimum_stock)/(usage_stats.total_used/90)
    END AS days_remaining

    FROM procurement_products p

    LEFT JOIN
    (
    SELECT 
    product_id,
    SUM(quantity) AS total_used
    FROM procurement_inventory
    WHERE transaction_type='Sale'
    AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    GROUP BY product_id
    ) usage_stats

    ON p.product_id = usage_stats.product_id

    ORDER BY days_remaining ASC
    LIMIT 10";

$reorder = $db->query($reorder_query);

$prod = [];
$days = [];

while ($row = $reorder->fetch_assoc()) {
    $prod[] = $row['product_name'];
    $days[] = round($row['days_remaining']);
}

$page_title = 'Products';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procurement Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="../../assets/css/style.css" rel="stylesheet">

    <!-- Custom CSS For jus Procuremnt Pages-->
    <link href="../../assets/css/procurement/style.css" rel="stylesheet">

</head>

<body>
    <div class="container-fluid p-0 module-procurement products">
        <!-- Include sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Mainn Content -->
        <div class="col">
            <div class="main-content">
                <div class="col-lg" style="padding-top: 20px;">
                    <!-- Header -->
                    <div class="module-header">
                        <div class="d-flex justify-content-between align-items-center" style="margin-bottom: 10px;">
                            <div>
                                <h2 class="mb-2">Procurement - Products</h2>
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

                    <!-- stats row -->
                    <div class="stat-card">
                        <div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #f72585, #b5179e);">
                                <i class="fas fa-box"></i>
                            </div>
                            <div style="display: block; margin-left: 20px;">
                                <h3 class="stat-value"><?php echo $stats['total_products']; ?></h3>
                                <p class="stat-label">Products</p>
                            </div>
                        </div>
                        <div>
                            <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #b02a37);">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div style="display: block; margin-left: 20px;">
                                <h3 class="stat-value"><?php echo $stats['low_stock_items']; ?></h3>
                                <p class="stat-label">Low Stock Items</p>
                            </div>
                        </div>
                    </div>

                    <!-- Products Tab -->
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
                                                    <button class="btn btn-sm btn-danger"
                                                        onclick="reduceStock(<?php echo $product['product_id']; ?>, parseFloat(<?php echo $product['current_stock']; ?>))">
                                                        <i class="fas fa-minus-circle"></i> Reduce
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Section -->
                    <div class="row g-4 mt-2 mb-4">

                        <!-- Stock Trend Chart -->
                        <div class="col-lg-7">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">

                                    <div>
                                        <h5 class="mb-0">
                                            <i class="fas fa-chart-line me-2 text-primary"></i>
                                            Stock Movement Trend
                                        </h5>
                                        <small class="text-muted">Net stock in/out over the last 6 months</small>
                                    </div>

                                    <div style="width:220px;">
                                        <select id="productFilter" class="form-select form-select-sm">
                                            <option value="all">All Products</option>

                                            <?php while ($p = $products_dropdown->fetch_assoc()): ?>
                                                <option value="<?php echo $p['product_id']; ?>">
                                                    <?php echo htmlspecialchars($p['product_name']); ?>
                                                </option>
                                            <?php endwhile; ?>

                                        </select>
                                    </div>

                                </div>
                                <div class="card-body">
                                    <canvas id="stockMovementChart"></canvas>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex gap-4 text-muted small">
                                        <span><i class="fas fa-circle text-primary me-1"></i>Net Stock Movement</span>
                                        <span><i class="fas fa-info-circle me-1"></i>Positive = more stock in than out</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reorder Prediction Chart -->
                        <div class="col-lg-5">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-clock me-2 text-warning"></i>Reorder Urgency</h5>
                                    <small class="text-muted">Estimated days before hitting minimum stock</small>
                                </div>
                                <div class="card-body">
                                    <canvas id="reorderUrgencyChart" height="220"></canvas>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex gap-4 text-muted small">
                                        <span><i class="fas fa-circle text-danger me-1"></i>Critical (&lt;7 days)</span>
                                        <span><i class="fas fa-circle text-warning me-1"></i>Soon (&lt;30 days)</span>
                                        <span><i class="fas fa-circle text-success me-1"></i>OK</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Chart Descriptions -->
                        <div class="col-12">
                            <div class="card border-0" style="background: linear-gradient(135deg, #f8f9ff, #eef1ff); border-left: 4px solid #4361ee !important; border-left-width: 4px;">
                                <div class="card-body py-3">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="d-flex gap-3 align-items-start">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                    style="width:40px;height:40px;background:linear-gradient(135deg,#4361ee,#3a0ca3);">
                                                    <i class="fas fa-chart-line text-white small"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1 fw-semibold">Stock Movement Trend</h6>
                                                    <p class="mb-0 text-muted small">
                                                        Tracks net stock change each month (items received minus items consumed).
                                                        A consistently declining trend signals you're depleting stock faster than restocking —
                                                        consider increasing order frequency or quantities.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex gap-3 align-items-start">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                                    style="width:40px;height:40px;background:linear-gradient(135deg,#f72585,#b5179e);">
                                                    <i class="fas fa-clock text-white small"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1 fw-semibold">Reorder Urgency Prediction</h6>
                                                    <p class="mb-0 text-muted small">
                                                        Estimates days remaining before each product hits its minimum stock threshold,
                                                        based on the last 3 months of average consumption.
                                                        Items shown in red should be reordered <strong>immediately</strong>.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
            let stockChart;

            // Load chart data
            function loadChart(productId = "all") {
                const reorderLabels = <?php echo json_encode($prod); ?>;
                const daysLeft = <?php echo json_encode($days); ?>;


                // Assign colors based on urgency
                const barColors = daysLeft.map(d =>
                    d < 7 ? 'rgba(220,53,69,0.85)' :
                    d < 30 ? 'rgba(255,193,7,0.85)' :
                    'rgba(25,135,84,0.75)'
                );

                fetch("products.php?ajax=stock_trend&product_id=" + productId)
                    .then(res => res.json())
                    .then(data => {

                        if (stockChart) {
                            stockChart.destroy();
                        }

                        stockChart = new Chart(document.getElementById("stockMovementChart"), {
                            type: "line",
                            data: {
                                labels: data.labels,
                                datasets: [{
                                        label: "Received",
                                        data: data.received,
                                        borderColor: "#4361ee",
                                        backgroundColor: "rgba(67,97,238,0.2)",
                                        tension: .3
                                    },
                                    {
                                        label: "Consumed",
                                        data: data.consumed,
                                        borderColor: "#f72585",
                                        backgroundColor: "rgba(247,37,133,0.2)",
                                        tension: .3
                                    },
                                    {
                                        label: "Net Movement",
                                        data: data.net,
                                        borderColor: "#2a9d8f",
                                        borderDash: [5, 5],
                                        tension: .3
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: {
                                        position: "bottom"
                                    }
                                }
                            }
                        });

                    });

                const reorderChart = new Chart(
                    document.getElementById("reorderUrgencyChart"), {
                        type: "bar",
                        data: {
                            labels: reorderLabels,
                            datasets: [{
                                label: "Days Remaining",
                                data: daysLeft,
                                backgroundColor: barColors
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.raw + " days remaining";
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: "Days Before Reorder Needed"
                                    }
                                }
                            }
                        }
                    }
                );
            }

            // Load default chart
            loadChart();

            // Filter by product
            document.getElementById("productFilter").addEventListener("change", function() {
                loadChart(this.value);
            });


            // Reduce Stock
            function reduceStock(productId, currentStock) {
                let qty = prompt("Enter quantity to reduce from stock:");

                if (qty === null) return;
                qty = parseFloat(qty);

                if (isNaN(qty) || qty <= 0) {
                    alert("Invalid quantity.");
                    return;
                }

                if (qty > currentStock) {
                    alert("Cannot reduce more than current stock (" + currentStock + ")");
                    return;
                }

                if (confirm("Reduce stock by " + qty + " units?")) {
                    fetch("products.php", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded"
                            },
                            body: "action=reduce_stock&product_id=" + productId + "&quantity=" + qty
                        })
                        .then(res => res.text())
                        .then(data => {
                            alert(data);
                            location.reload();
                        });

                }
            }
        </script>
</body>