<?php
require_once '../../includes/session.php';
$session->requireLogin();

// Check permission
if (!hasPermission('blockfactory', 'view')) {
    $_SESSION['error'] = 'You do not have permission to access this module.';
    header('Location: ../../admin/dashboard.php');
    exit();
}

$current_user = currentUser();
$company_id = $session->getCompanyId();

global $db;

// Get statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM blockfactory_products WHERE company_id = ? AND status = 'Active') as total_products,
                (SELECT COALESCE(SUM(current_stock), 0) FROM blockfactory_products WHERE company_id = ?) as total_blocks,
                (SELECT COUNT(*) FROM blockfactory_production WHERE production_date = CURDATE()) as today_production,
                (SELECT COALESCE(SUM(produced_quantity), 0) FROM blockfactory_production WHERE production_date = CURDATE()) as today_blocks,
                (SELECT COALESCE(SUM(total_amount), 0) FROM blockfactory_sales WHERE sale_date = CURDATE()) as today_sales,
                (SELECT COUNT(*) FROM blockfactory_sales WHERE delivery_status = 'Pending') as pending_deliveries,
                (SELECT COALESCE(SUM(current_stock), 0) FROM blockfactory_raw_materials WHERE current_stock <= minimum_stock) as low_materials";
$stmt = $db->prepare($stats_query);
$stmt->bind_param("ii", $company_id, $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get recent production batches
$production_query = "SELECT p.*, pr.product_name 
                     FROM blockfactory_production p
                     JOIN blockfactory_products pr ON p.product_id = pr.product_id
                     WHERE pr.company_id = ?
                     ORDER BY p.production_date DESC LIMIT 10";
$stmt = $db->prepare($production_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$recent_production = $stmt->get_result();

// Get recent sales
$sales_query = "SELECT s.*, pr.product_name 
                FROM blockfactory_sales s
                JOIN blockfactory_products pr ON s.product_id = pr.product_id
                WHERE pr.company_id = ?
                ORDER BY s.sale_date DESC LIMIT 10";
$stmt = $db->prepare($sales_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$recent_sales = $stmt->get_result();

// Get low stock raw materials
$low_materials_query = "SELECT * FROM blockfactory_raw_materials 
                       WHERE current_stock <= minimum_stock 
                       ORDER BY (current_stock - minimum_stock) ASC";
$low_materials = $db->query($low_materials_query);

// Get pending deliveries
$deliveries_query = "SELECT d.*, s.invoice_number, c.customer_name, pr.product_name
                     FROM blockfactory_deliveries d
                     JOIN blockfactory_sales s ON d.sale_id = s.sale_id
                     JOIN blockfactory_products pr ON s.product_id = pr.product_id
                     JOIN blockfactory_customers c ON s.customer_id = c.customer_id
                     WHERE d.status != 'Delivered'
                     ORDER BY d.delivery_date ASC LIMIT 10";
$deliveries = $db->query($deliveries_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Block Factory - <?php echo APP_NAME; ?></title>

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

    <style>
        .module-header {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, #43e97b, #38f9d7);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .production-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #43e97b;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .sale-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #38f9d7;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .material-alert {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .quick-action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .quick-action-btn {
            background: white;
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .quick-action-btn:hover {
            border-color: #43e97b;
            background: #f8f9fa;
            transform: translateY(-3px);
        }

        .quick-action-btn i {
            font-size: 28px;
            color: #43e97b;
            margin-bottom: 10px;
        }

        .quick-action-btn span {
            display: block;
            font-weight: 600;
            color: #333;
        }

        .nav-tabs .nav-link {
            color: #666;
            font-weight: 500;
            border: none;
            padding: 10px 20px;
        }

        .nav-tabs .nav-link.active {
            color: #43e97b;
            background: none;
            border-bottom: 3px solid #43e97b;
        }

        .quality-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .defect-rate {
            font-size: 13px;
            color: #dc3545;
        }

        .stock-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .stock-good {
            background-color: #28a745;
        }

        .stock-low {
            background-color: #ffc107;
        }

        .stock-critical {
            background-color: #dc3545;
        }
    </style>
</head>

<body class="module-blockfactory">
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
                        <h1 class="h3 mb-2">Block Factory Management</h1>
                        <p class="mb-0 opacity-75">Manage production, sales, and inventory</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-light me-2" data-bs-toggle="modal" data-bs-target="#productionModal">
                            <i class="fas fa-plus-circle me-2"></i>Record Production
                        </button>
                        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#saleModal">
                            <i class="fas fa-shopping-cart me-2"></i>New Sale
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-action-grid mb-4">
                <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#productionModal">
                    <i class="fas fa-industry"></i>
                    <span>Record Production</span>
                </div>
                <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#saleModal">
                    <i class="fas fa-shopping-cart"></i>
                    <span>New Sale</span>
                </div>
                <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fas fa-box"></i>
                    <span>Add Product</span>
                </div>
                <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Customer</span>
                </div>
                <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#deliveryModal">
                    <i class="fas fa-truck"></i>
                    <span>Schedule Delivery</span>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-cubes"></i>
                        </div>
                        <h3 class="stat-value"><?php echo number_format($stats['total_blocks']); ?></h3>
                        <p class="stat-label">Blocks in Stock</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f72585, #b5179e);">
                            <i class="fas fa-industry"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['today_blocks']; ?></h3>
                        <p class="stat-label">Produced Today</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f8961e, #f3722c);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="stat-value"><?php echo format_money($stats['today_sales']); ?></h3>
                        <p class="stat-label">Sales Today</p>
                    </div>
                </div>

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

            <!-- Second Row Stats -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Total Products</h6>
                            <h3><?php echo $stats['total_products']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Production Batches Today</h6>
                            <h3><?php echo $stats['today_production']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Pending Deliveries</h6>
                            <h3><?php echo $stats['pending_deliveries']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="blockTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="production-tab" data-bs-toggle="tab" data-bs-target="#production" type="button" role="tab">
                        <i class="fas fa-industry me-2"></i>Production
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">
                        <i class="fas fa-cubes me-2"></i>Products
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button" role="tab">
                        <i class="fas fa-shopping-cart me-2"></i>Sales
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="customers-tab" data-bs-toggle="tab" data-bs-target="#customers" type="button" role="tab">
                        <i class="fas fa-users me-2"></i>Customers
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab">
                        <i class="fas fa-boxes me-2"></i>Raw Materials
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="deliveries-tab" data-bs-toggle="tab" data-bs-target="#deliveries" type="button" role="tab">
                        <i class="fas fa-truck me-2"></i>Deliveries
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="blockTabContent">
                <!-- Dashboard Tab -->
                <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
                    <div class="row">
                        <!-- Recent Production -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Production</h5>
                                    <a href="#" onclick="$('#production-tab').click()" class="btn btn-sm btn-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if ($recent_production->num_rows > 0): ?>
                                        <?php while ($prod = $recent_production->fetch_assoc()): ?>
                                            <div class="production-card">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="mb-1">Batch #<?php echo $prod['batch_number']; ?></h6>
                                                        <p class="mb-1"><?php echo htmlspecialchars($prod['product_name']); ?></p>
                                                    </div>
                                                    <span class="quality-badge bg-<?php echo $prod['defect_rate'] <= 2 ? 'success' : 'warning'; ?>">
                                                        <?php echo $prod['good_quantity']; ?>/<?php echo $prod['produced_quantity']; ?> good
                                                    </span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="far fa-calendar me-1"></i> <?php echo format_date($prod['production_date']); ?>
                                                        <i class="fas fa-user ms-2 me-1"></i> <?php echo $prod['supervisor']; ?>
                                                    </small>
                                                    <?php if ($prod['defect_rate'] > 5): ?>
                                                        <span class="defect-rate">Defect rate: <?php echo $prod['defect_rate']; ?>%</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-4">No production records found</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Pending Deliveries -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Pending Deliveries</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($deliveries->num_rows > 0): ?>
                                        <?php while ($delivery = $deliveries->fetch_assoc()): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-3 p-2 border-bottom">
                                                <div>
                                                    <strong>Delivery #<?php echo $delivery['delivery_note']; ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo $delivery['customer_name']; ?> - <?php echo $delivery['product_name']; ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-<?php
                                                                            echo $delivery['status'] == 'Scheduled' ? 'info' : ($delivery['status'] == 'In Transit' ? 'warning' : 'secondary');
                                                                            ?>">
                                                        <?php echo $delivery['status']; ?>
                                                    </span>
                                                    <br>
                                                    <small><?php echo format_date($delivery['delivery_date']); ?></small>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No pending deliveries</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Sales & Low Materials -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Recent Sales</h5>
                                    <a href="#" onclick="$('#sales-tab').click()" class="btn btn-sm btn-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if ($recent_sales->num_rows > 0): ?>
                                        <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                            <div class="sale-card">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="mb-1">Invoice #<?php echo $sale['invoice_number']; ?></h6>
                                                        <p class="mb-1"><?php echo htmlspecialchars($sale['customer_name']); ?></p>
                                                    </div>
                                                    <strong><?php echo format_money($sale['total_amount']); ?></strong>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="far fa-calendar me-1"></i> <?php echo format_date($sale['sale_date']); ?>
                                                        <i class="fas fa-cubes ms-2 me-1"></i> <?php echo $sale['quantity']; ?> units
                                                    </small>
                                                    <span class="badge bg-<?php
                                                                            echo $sale['payment_status'] == 'Paid' ? 'success' : ($sale['payment_status'] == 'Partial' ? 'warning' : 'danger');
                                                                            ?>">
                                                        <?php echo $sale['payment_status']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-4">No sales records found</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Low Raw Materials Alert -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Low Raw Materials</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($low_materials->num_rows > 0): ?>
                                        <?php while ($material = $low_materials->fetch_assoc()): ?>
                                            <div class="material-alert">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($material['material_name']); ?></strong>
                                                        <br>
                                                        <small>Current: <?php echo $material['current_stock']; ?> <?php echo $material['unit']; ?></small>
                                                        <br>
                                                        <small>Min: <?php echo $material['minimum_stock']; ?> <?php echo $material['unit']; ?></small>
                                                    </div>
                                                    <button class="btn btn-sm btn-warning" onclick="orderMaterial(<?php echo $material['material_id']; ?>)">
                                                        Order
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">All materials are adequately stocked</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Production Tab -->
                <div class="tab-pane fade" id="production" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Production Batches</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#productionModal">
                                <i class="fas fa-plus-circle me-2"></i>Record Production
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="productionTable">
                                    <thead>
                                        <tr>
                                            <th>Batch #</th>
                                            <th>Product</th>
                                            <th>Date</th>
                                            <th>Shift</th>
                                            <th>Supervisor</th>
                                            <th>Produced</th>
                                            <th>Good</th>
                                            <th>Defects</th>
                                            <th>Defect Rate</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $all_production_query = "SELECT p.*, pr.product_name 
                                                                FROM blockfactory_production p
                                                                JOIN blockfactory_products pr ON p.product_id = pr.product_id
                                                                WHERE pr.company_id = ?
                                                                ORDER BY p.production_date DESC";
                                        $stmt = $db->prepare($all_production_query);
                                        $stmt->bind_param("i", $company_id);
                                        $stmt->execute();
                                        $all_production = $stmt->get_result();
                                        while ($prod = $all_production->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $prod['batch_number']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($prod['product_name']); ?></td>
                                                <td><?php echo format_date($prod['production_date']); ?></td>
                                                <td><?php echo $prod['shift']; ?></td>
                                                <td><?php echo $prod['supervisor']; ?></td>
                                                <td><?php echo $prod['produced_quantity']; ?></td>
                                                <td><?php echo $prod['good_quantity']; ?></td>
                                                <td><?php echo $prod['defective_quantity']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo $prod['defect_rate'] <= 2 ? 'success' : ($prod['defect_rate'] <= 5 ? 'warning' : 'danger');
                                                                            ?>">
                                                        <?php echo $prod['defect_rate']; ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewBatch(<?php echo $prod['production_id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" onclick="recordQuality(<?php echo $prod['production_id']; ?>)">
                                                        <i class="fas fa-clipboard-check"></i>
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
                                            <th>Type</th>
                                            <th>Dimensions</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $products_query = "SELECT * FROM blockfactory_products WHERE company_id = ? ORDER BY product_name ASC";
                                        $stmt = $db->prepare($products_query);
                                        $stmt->bind_param("i", $company_id);
                                        $stmt->execute();
                                        $products = $stmt->get_result();
                                        while ($product = $products->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><?php echo $product['product_code']; ?></td>
                                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                <td><?php echo $product['product_type']; ?></td>
                                                <td><?php echo $product['dimensions']; ?></td>
                                                <td><?php echo format_money($product['price_per_unit']); ?></td>
                                                <td>
                                                    <span class="stock-indicator stock-<?php
                                                                                        echo $product['current_stock'] <= $product['reorder_level'] ? 'critical' : ($product['current_stock'] <= $product['reorder_level'] * 2 ? 'low' : 'good');
                                                                                        ?>"></span>
                                                    <?php echo $product['current_stock']; ?>
                                                </td>
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

                <!-- Sales Tab -->
                <div class="tab-pane fade" id="sales" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Sales</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#saleModal">
                                <i class="fas fa-plus-circle me-2"></i>New Sale
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="salesTable">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Date</th>
                                            <th>Customer</th>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Total</th>
                                            <th>Payment</th>
                                            <th>Delivery</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $all_sales_query = "SELECT s.*, pr.product_name, c.customer_name
                                                           FROM blockfactory_sales s
                                                           JOIN blockfactory_products pr ON s.product_id = pr.product_id
                                                           LEFT JOIN blockfactory_customers c ON s.customer_id = c.customer_id
                                                           WHERE pr.company_id = ?
                                                           ORDER BY s.sale_date DESC";
                                        $stmt = $db->prepare($all_sales_query);
                                        $stmt->bind_param("i", $company_id);
                                        $stmt->execute();
                                        $all_sales = $stmt->get_result();
                                        while ($sale = $all_sales->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $sale['invoice_number']; ?></strong></td>
                                                <td><?php echo format_date($sale['sale_date']); ?></td>
                                                <td><?php echo htmlspecialchars($sale['customer_name'] ?: $sale['customer_name_custom']); ?></td>
                                                <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                                <td><?php echo $sale['quantity']; ?></td>
                                                <td><?php echo format_money($sale['total_amount']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo $sale['payment_status'] == 'Paid' ? 'success' : ($sale['payment_status'] == 'Partial' ? 'warning' : 'danger');
                                                                            ?>">
                                                        <?php echo $sale['payment_status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo $sale['delivery_status'] == 'Delivered' ? 'success' : ($sale['delivery_status'] == 'Partial' ? 'warning' : 'info');
                                                                            ?>">
                                                        <?php echo $sale['delivery_status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewSale(<?php echo $sale['sale_id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="printInvoice(<?php echo $sale['sale_id']; ?>)">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                    <?php if ($sale['delivery_status'] != 'Delivered'): ?>
                                                        <button class="btn btn-sm btn-warning" onclick="scheduleDelivery(<?php echo $sale['sale_id']; ?>)">
                                                            <i class="fas fa-truck"></i>
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

                <!-- Customers Tab -->
                <div class="tab-pane fade" id="customers" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Customers</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                                <i class="fas fa-plus-circle me-2"></i>Add Customer
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="customersTable">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Customer Name</th>
                                            <th>Contact Person</th>
                                            <th>Phone</th>
                                            <th>Email</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $customers_query = "SELECT * FROM blockfactory_customers ORDER BY customer_name ASC";
                                        $customers = $db->query($customers_query);
                                        while ($customer = $customers->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><?php echo $customer['customer_code']; ?></td>
                                                <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                                <td><?php echo htmlspecialchars($customer['contact_person']); ?></td>
                                                <td><?php echo $customer['phone']; ?></td>
                                                <td><?php echo $customer['email']; ?></td>
                                                <td><?php echo $customer['customer_type']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $customer['status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo $customer['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewCustomer(<?php echo $customer['customer_id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="editCustomer(<?php echo $customer['customer_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="newSale(<?php echo $customer['customer_id']; ?>)">
                                                        <i class="fas fa-shopping-cart"></i>
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

                <!-- Raw Materials Tab -->
                <div class="tab-pane fade" id="materials" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Raw Materials</h5>
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
                                            <th>Material</th>
                                            <th>Type</th>
                                            <th>Supplier</th>
                                            <th>Stock</th>
                                            <th>Unit</th>
                                            <th>Unit Cost</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $materials_query = "SELECT * FROM blockfactory_raw_materials ORDER BY material_name ASC";
                                        $materials = $db->query($materials_query);
                                        while ($material = $materials->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><?php echo $material['material_code']; ?></td>
                                                <td><?php echo htmlspecialchars($material['material_name']); ?></td>
                                                <td><?php echo $material['material_type']; ?></td>
                                                <td><?php echo $material['supplier']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="stock-indicator stock-<?php
                                                                                            echo $material['current_stock'] <= $material['minimum_stock'] ? 'critical' : ($material['current_stock'] <= $material['reorder_level'] ? 'low' : 'good');
                                                                                            ?>"></span>
                                                        <?php echo $material['current_stock']; ?>
                                                    </div>
                                                </td>
                                                <td><?php echo $material['unit']; ?></td>
                                                <td><?php echo format_money($material['unit_cost']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo $material['status'] == 'Available' ? 'success' : ($material['status'] == 'Low Stock' ? 'warning' : 'danger');
                                                                            ?>">
                                                        <?php echo $material['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewMaterial(<?php echo $material['material_id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="editMaterial(<?php echo $material['material_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="receiveMaterial(<?php echo $material['material_id']; ?>)">
                                                        <i class="fas fa-arrow-down"></i>
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

                <!-- Deliveries Tab -->
                <div class="tab-pane fade" id="deliveries" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Deliveries</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#deliveryModal">
                                <i class="fas fa-plus-circle me-2"></i>Schedule Delivery
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="deliveriesTable">
                                    <thead>
                                        <tr>
                                            <th>Delivery Note</th>
                                            <th>Invoice #</th>
                                            <th>Customer</th>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Delivery Date</th>
                                            <th>Vehicle</th>
                                            <th>Driver</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $all_deliveries_query = "SELECT d.*, s.invoice_number, s.quantity as sale_qty, 
                                                                pr.product_name, c.customer_name
                                                                FROM blockfactory_deliveries d
                                                                JOIN blockfactory_sales s ON d.sale_id = s.sale_id
                                                                JOIN blockfactory_products pr ON s.product_id = pr.product_id
                                                                JOIN blockfactory_customers c ON s.customer_id = c.customer_id
                                                                ORDER BY d.delivery_date DESC";
                                        $all_deliveries = $db->query($all_deliveries_query);
                                        while ($delivery = $all_deliveries->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $delivery['delivery_note']; ?></strong></td>
                                                <td><?php echo $delivery['invoice_number']; ?></td>
                                                <td><?php echo htmlspecialchars($delivery['customer_name']); ?></td>
                                                <td><?php echo htmlspecialchars($delivery['product_name']); ?></td>
                                                <td><?php echo $delivery['quantity']; ?></td>
                                                <td><?php echo format_date($delivery['delivery_date']); ?></td>
                                                <td><?php echo $delivery['vehicle_number']; ?></td>
                                                <td><?php echo $delivery['driver_name']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo $delivery['status'] == 'Delivered' ? 'success' : ($delivery['status'] == 'In Transit' ? 'warning' : 'info');
                                                                            ?>">
                                                        <?php echo $delivery['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewDelivery(<?php echo $delivery['delivery_id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($delivery['status'] != 'Delivered'): ?>
                                                        <button class="btn btn-sm btn-success" onclick="markDelivered(<?php echo $delivery['delivery_id']; ?>)">
                                                            <i class="fas fa-check"></i>
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
        </div>
    </div>

    <!-- Production Modal -->
    <div class="modal fade" id="productionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record Production Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/add-production.php" method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product</label>
                                <select class="form-control" name="product_id" required>
                                    <option value="">Select Product</option>
                                    <?php
                                    $products = $db->query("SELECT product_id, product_name FROM blockfactory_products WHERE company_id = $company_id AND status = 'Active'");
                                    while ($prod = $products->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $prod['product_id']; ?>"><?php echo $prod['product_name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Production Date</label>
                                <input type="date" class="form-control" name="production_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Shift</label>
                                <select class="form-control" name="shift" required>
                                    <option value="Morning">Morning</option>
                                    <option value="Afternoon">Afternoon</option>
                                    <option value="Night">Night</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supervisor</label>
                                <input type="text" class="form-control" name="supervisor" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Planned Quantity</label>
                                <input type="number" class="form-control" name="planned_quantity" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Produced Quantity</label>
                                <input type="number" class="form-control" name="produced_quantity" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Good Quantity</label>
                                <input type="number" class="form-control" name="good_quantity" required>
                            </div>
                        </div>

                        <h6 class="mt-3">Raw Materials Used</h6>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Cement (kg)</label>
                                <input type="number" step="0.01" class="form-control" name="cement_used">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Sand (kg)</label>
                                <input type="number" step="0.01" class="form-control" name="sand_used">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Aggregate (kg)</label>
                                <input type="number" step="0.01" class="form-control" name="aggregate_used">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Water (L)</label>
                                <input type="number" step="0.01" class="form-control" name="water_used">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Production</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sale Modal -->
    <div class="modal fade" id="saleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Sale</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/add-sale.php" method="POST" id="saleForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer</label>
                                <select class="form-control select2" name="customer_id" id="customerSelect">
                                    <option value="">Select Customer</option>
                                    <?php
                                    $customers = $db->query("SELECT customer_id, customer_name FROM blockfactory_customers WHERE status = 'Active'");
                                    while ($cust = $customers->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $cust['customer_id']; ?>"><?php echo $cust['customer_name']; ?></option>
                                    <?php endwhile; ?>
                                    <option value="new">+ Add New Customer</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" id="newCustomerFields" style="display: none;">
                                <input type="text" class="form-control" name="new_customer_name" placeholder="Customer Name">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sale Date</label>
                                <input type="date" class="form-control" name="sale_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Product</label>
                                <select class="form-control" name="product_id" id="productSelect" required>
                                    <option value="">Select Product</option>
                                    <?php
                                    $products = $db->query("SELECT product_id, product_name, price_per_unit, current_stock FROM blockfactory_products WHERE company_id = $company_id AND status = 'Active'");
                                    while ($prod = $products->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $prod['product_id']; ?>"
                                            data-price="<?php echo $prod['price_per_unit']; ?>"
                                            data-stock="<?php echo $prod['current_stock']; ?>">
                                            <?php echo $prod['product_name']; ?> (Stock: <?php echo $prod['current_stock']; ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="quantity" id="quantity" required min="1">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Unit Price</label>
                                <input type="number" step="0.01" class="form-control" name="unit_price" id="unitPrice" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Discount</label>
                                <input type="number" step="0.01" class="form-control" name="discount" id="discount" value="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Total Amount</label>
                                <input type="text" class="form-control" id="totalAmount" readonly>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method</label>
                                <select class="form-control" name="payment_method" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Mobile Money">Mobile Money</option>
                                    <option value="Credit">Credit</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount Paid</label>
                                <input type="number" step="0.01" class="form-control" name="amount_paid" id="amountPaid" value="0">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Delivery Address</label>
                            <textarea class="form-control" name="delivery_address" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Process Sale</button>
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
                <form action="process/add-product.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product Code</label>
                            <input type="text" class="form-control" name="product_code" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-control" name="product_name" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Type</label>
                                <select class="form-control" name="product_type" required>
                                    <option value="Solid Block">Solid Block</option>
                                    <option value="Hollow Block">Hollow Block</option>
                                    <option value="Interlocking Block">Interlocking Block</option>
                                    <option value="Paving Block">Paving Block</option>
                                    <option value="Kerbstones">Kerbstones</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dimensions</label>
                                <input type="text" class="form-control" name="dimensions" placeholder="e.g., 400x200x200" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" step="0.01" class="form-control" name="weight_kg">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Strength (MPa)</label>
                                <input type="text" class="form-control" name="strength_mpa">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price per Unit</label>
                                <input type="number" step="0.01" class="form-control" name="price_per_unit" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cost per Unit</label>
                                <input type="number" step="0.01" class="form-control" name="cost_per_unit">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Min Stock</label>
                                <input type="number" class="form-control" name="minimum_stock" value="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max Stock</label>
                                <input type="number" class="form-control" name="maximum_stock" value="10000">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Reorder Level</label>
                                <input type="number" class="form-control" name="reorder_level" value="200">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/add-customer.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Customer Code</label>
                            <input type="text" class="form-control" name="customer_code" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Customer Name</label>
                            <input type="text" class="form-control" name="customer_name" required>
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
                                <label class="form-label">Customer Type</label>
                                <select class="form-control" name="customer_type">
                                    <option value="Individual">Individual</option>
                                    <option value="Company">Company</option>
                                    <option value="Contractor">Contractor</option>
                                    <option value="Government">Government</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tax Number</label>
                                <input type="text" class="form-control" name="tax_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Credit Limit</label>
                                <input type="number" step="0.01" class="form-control" name="credit_limit">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Material Modal -->
    <div class="modal fade" id="addMaterialModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Raw Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/add-material.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Material Code</label>
                            <input type="text" class="form-control" name="material_code" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Material Name</label>
                            <input type="text" class="form-control" name="material_name" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Material Type</label>
                                <select class="form-control" name="material_type" required>
                                    <option value="Cement">Cement</option>
                                    <option value="Sand">Sand</option>
                                    <option value="Aggregate">Aggregate</option>
                                    <option value="Water">Water</option>
                                    <option value="Additive">Additive</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unit</label>
                                <select class="form-control" name="unit" required>
                                    <option value="kg">Kilograms (kg)</option>
                                    <option value="bags">Bags</option>
                                    <option value="tons">Tons</option>
                                    <option value="liters">Liters</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unit Cost</label>
                                <input type="number" step="0.01" class="form-control" name="unit_cost" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Current Stock</label>
                                <input type="number" step="0.01" class="form-control" name="current_stock" value="0">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Min Stock</label>
                                <input type="number" step="0.01" class="form-control" name="minimum_stock" value="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max Stock</label>
                                <input type="number" step="0.01" class="form-control" name="maximum_stock" value="10000">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Reorder Level</label>
                                <input type="number" step="0.01" class="form-control" name="reorder_level" value="100">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Supplier</label>
                            <input type="text" class="form-control" name="supplier">
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

    <!-- Delivery Modal -->
    <div class="modal fade" id="deliveryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Delivery</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/add-delivery.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Sale</label>
                            <select class="form-control" name="sale_id" required>
                                <option value="">Select Invoice</option>
                                <?php
                                $pending_sales = $db->query("SELECT s.sale_id, s.invoice_number, c.customer_name, s.delivery_address
                                                            FROM blockfactory_sales s
                                                            JOIN blockfactory_customers c ON s.customer_id = c.customer_id
                                                            WHERE s.delivery_status != 'Delivered'");
                                while ($sale = $pending_sales->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $sale['sale_id']; ?>" data-address="<?php echo $sale['delivery_address']; ?>">
                                        <?php echo $sale['invoice_number']; ?> - <?php echo $sale['customer_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Delivery Date</label>
                            <input type="date" class="form-control" name="delivery_date" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vehicle Number</label>
                                <input type="text" class="form-control" name="vehicle_number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Driver Name</label>
                                <input type="text" class="form-control" name="driver_name" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Driver Phone</label>
                                <input type="text" class="form-control" name="driver_phone">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Delivery Charges</label>
                                <input type="number" step="0.01" class="form-control" name="delivery_charges" value="0">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Destination</label>
                            <textarea class="form-control" name="destination" rows="2" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Schedule Delivery</button>
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
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#productionTable').DataTable({
                order: [
                    [2, 'desc']
                ]
            });
            $('#productsTable').DataTable();
            $('#salesTable').DataTable({
                order: [
                    [1, 'desc']
                ]
            });
            $('#customersTable').DataTable();
            $('#materialsTable').DataTable();
            $('#deliveriesTable').DataTable({
                order: [
                    [5, 'asc']
                ]
            });

            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#saleModal')
            });

            // Handle customer select
            $('#customerSelect').change(function() {
                if ($(this).val() == 'new') {
                    $('#newCustomerFields').show();
                    $('#newCustomerFields input').prop('required', true);
                } else {
                    $('#newCustomerFields').hide();
                    $('#newCustomerFields input').prop('required', false);
                }
            });

            // Calculate total amount
            function calculateTotal() {
                let quantity = parseFloat($('#quantity').val()) || 0;
                let price = parseFloat($('#unitPrice').val()) || 0;
                let discount = parseFloat($('#discount').val()) || 0;

                let subtotal = quantity * price;
                let total = subtotal - discount;

                $('#totalAmount').val(formatMoney(total));
            }

            $('#quantity, #unitPrice, #discount').on('input', calculateTotal);

            // Set unit price when product selected
            $('#productSelect').change(function() {
                let price = $(this).find(':selected').data('price');
                let stock = $(this).find(':selected').data('stock');
                $('#unitPrice').val(price);

                // Validate quantity against stock
                $('#quantity').attr('max', stock);
                $('#quantity').next('.invalid-feedback').remove();
                if (stock < 1) {
                    $('#quantity').addClass('is-invalid');
                    $('#quantity').after('<div class="invalid-feedback">Out of stock</div>');
                }
            });

            // Update delivery address when sale selected
            $('select[name="sale_id"]').change(function() {
                let address = $(this).find(':selected').data('address');
                $('textarea[name="destination"]').val(address);
            });

            function formatMoney(amount) {
                return 'GHS ' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            }
        });

        function viewBatch(id) {
            window.location.href = 'view-batch.php?id=' + id;
        }

        function recordQuality(id) {
            window.location.href = 'quality-check.php?id=' + id;
        }

        function viewProduct(id) {
            window.location.href = 'view-product.php?id=' + id;
        }

        function editProduct(id) {
            window.location.href = 'edit-product.php?id=' + id;
        }

        function viewSale(id) {
            window.location.href = 'view-sale.php?id=' + id;
        }

        function printInvoice(id) {
            window.open('print-invoice.php?id=' + id, '_blank');
        }

        function scheduleDelivery(saleId) {
            $('#deliveryModal select[name="sale_id"]').val(saleId).trigger('change');
            $('#deliveryModal').modal('show');
        }

        function viewCustomer(id) {
            window.location.href = 'view-customer.php?id=' + id;
        }

        function editCustomer(id) {
            window.location.href = 'edit-customer.php?id=' + id;
        }

        function newSale(customerId) {
            $('#saleModal').modal('show');
            $('#customerSelect').val(customerId).trigger('change');
        }

        function viewMaterial(id) {
            window.location.href = 'view-material.php?id=' + id;
        }

        function editMaterial(id) {
            window.location.href = 'edit-material.php?id=' + id;
        }

        function receiveMaterial(id) {
            window.location.href = 'receive-material.php?id=' + id;
        }

        function orderMaterial(id) {
            window.location.href = '../procurement/create-po.php?material=' + id + '&source=blockfactory';
        }

        function viewDelivery(id) {
            window.location.href = 'view-delivery.php?id=' + id;
        }

        function markDelivered(id) {
            if (confirm('Mark this delivery as delivered?')) {
                $.post('ajax/mark-delivered.php', {
                    id: id
                }, function() {
                    location.reload();
                });
            }
        }
    </script>
</body>

</html>