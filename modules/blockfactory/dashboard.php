<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db_connection.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

$session->requireLogin();

$current_user = $session->getCurrentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

// Ensure only Block Factory users can access
if ($current_user['company_type'] != 'Block Factory' && $role != 'SuperAdmin') {
    $_SESSION['error'] = 'Access denied. Block Factory department only.';
    header('Location: ../../login.php');
    exit();
}

global $db;

// Get block factory statistics
$stats = [];

// Total products
$stmt = $db->prepare("SELECT COUNT(*) as count FROM blockfactory_products WHERE company_id = ? AND status = 'Active'");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['total_products'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Total blocks in stock
$stmt = $db->prepare("SELECT COALESCE(SUM(current_stock), 0) as total FROM blockfactory_products WHERE company_id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['total_blocks'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Today's production
$stmt = $db->prepare("SELECT COUNT(*) as count FROM blockfactory_production WHERE production_date = CURDATE()");
$stmt->execute();
$stats['today_production'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Today's blocks produced
$stmt = $db->prepare("SELECT COALESCE(SUM(produced_quantity), 0) as total FROM blockfactory_production WHERE production_date = CURDATE()");
$stmt->execute();
$stats['today_blocks'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Today's sales
$stmt = $db->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM blockfactory_sales WHERE sale_date = CURDATE()");
$stmt->execute();
$stats['today_sales'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Pending deliveries
$result = $db->query("SELECT COUNT(*) as count FROM blockfactory_deliveries WHERE status != 'Delivered'");
$stats['pending_deliveries'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;

// Low raw materials - FIXED: Changed current_stock to stock_quantity
$result = $db->query("SELECT COUNT(*) as count FROM blockfactory_raw_materials WHERE stock_quantity <= minimum_stock");
$stats['low_materials'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;

// Average defect rate
$defect_query = "SELECT COALESCE(AVG(defect_rate), 0) as avg_defect FROM blockfactory_production WHERE production_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
$result = $db->query($defect_query);
$stats['avg_defect_rate'] = round(($result->fetch_assoc()['avg_defect'] ?? 0), 2);

// Recent production batches
$production_query = "SELECT p.*, pr.product_name 
                     FROM blockfactory_production p
                     JOIN blockfactory_products pr ON p.product_id = pr.product_id
                     WHERE pr.company_id = ?
                     ORDER BY p.production_date DESC LIMIT 10";
$stmt = $db->prepare($production_query);
if ($stmt) {
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $recent_production = $stmt->get_result();
} else {
    $recent_production = null;
}

// Recent sales
$sales_query = "SELECT s.*, pr.product_name, c.customer_name
                FROM blockfactory_sales s
                JOIN blockfactory_products pr ON s.product_id = pr.product_id
                LEFT JOIN blockfactory_customers c ON s.customer_id = c.customer_id
                WHERE pr.company_id = ?
                ORDER BY s.sale_date DESC LIMIT 10";
$stmt = $db->prepare($sales_query);
if ($stmt) {
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $recent_sales = $stmt->get_result();
} else {
    $recent_sales = null;
}

// Top products by sales
$top_products_query = "SELECT pr.product_name, COUNT(s.sale_id) as sale_count, COALESCE(SUM(s.total_amount), 0) as total_value
                       FROM blockfactory_products pr
                       LEFT JOIN blockfactory_sales s ON pr.product_id = s.product_id
                       WHERE pr.company_id = ?
                       GROUP BY pr.product_id
                       ORDER BY total_value DESC
                       LIMIT 5";
$stmt = $db->prepare($top_products_query);
if ($stmt) {
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $top_products = $stmt->get_result();
} else {
    $top_products = null;
}

// Low raw materials list - FIXED: Changed current_stock to stock_quantity
$low_materials_query = "SELECT *, stock_quantity as current_stock FROM blockfactory_raw_materials 
                        WHERE stock_quantity <= minimum_stock 
                        ORDER BY (stock_quantity - minimum_stock) ASC";
$low_materials = $db->query($low_materials_query);

// Pending deliveries
$deliveries_query = "SELECT d.*, s.invoice_number, c.customer_name, pr.product_name
                     FROM blockfactory_deliveries d
                     JOIN blockfactory_sales s ON d.sale_id = s.sale_id
                     JOIN blockfactory_products pr ON s.product_id = pr.product_id
                     JOIN blockfactory_customers c ON s.customer_id = c.customer_id
                     WHERE d.status != 'Delivered'
                     ORDER BY d.delivery_date ASC LIMIT 10";
$pending_deliveries = $db->query($deliveries_query);

$page_title = 'Block Factory Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Block Factory Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #43e97b;
            --secondary-color: #38f9d7;
        }
        
        .department-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(67, 233, 123, 0.3);
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .production-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .sale-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid var(--secondary-color);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 25px;
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
            border-color: var(--primary-color);
            background: #f8f9fa;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(67, 233, 123, 0.2);
        }
        
        .quick-action-btn i {
            font-size: 28px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .material-alert {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .delivery-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .delivery-scheduled { background: #cce5ff; color: #004085; }
        .delivery-transit { background: #fff3cd; color: #856404; }
        .delivery-delivered { background: #d4edda; color: #155724; }
        
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #1e1e2f, #2a2a40);
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            padding-left: 30px;
        }
        
        .nav-link.active {
            background: var(--primary-color);
            color: white;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-auto">
                <div class="sidebar p-3">
                    <div class="text-center mb-4">
                        <h4>Block Factory</h4>
                        <p class="small text-muted">Manufacturing & Sales</p>
                    </div>
                    
                    <div class="user-info text-center mb-4 p-3 bg-white bg-opacity-10 rounded">
                        <div class="avatar mx-auto mb-2" style="width: 50px; height: 50px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                            <?php echo getAvatarLetter($current_user['full_name'] ?? 'User'); ?>
                        </div>
                        <strong><?php echo htmlspecialchars($current_user['full_name'] ?? 'User'); ?></strong>
                        <p class="small text-muted"><?php echo $current_user['company_name'] ?? 'Block Factory'; ?></p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link active">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="production.php" class="nav-link">
                                <i class="fas fa-industry me-2"></i>Production
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="products.php" class="nav-link">
                                <i class="fas fa-cubes me-2"></i>Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="sales.php" class="nav-link">
                                <i class="fas fa-shopping-cart me-2"></i>Sales
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="customers.php" class="nav-link">
                                <i class="fas fa-users me-2"></i>Customers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="raw-materials.php" class="nav-link">
                                <i class="fas fa-boxes me-2"></i>Raw Materials
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="deliveries.php" class="nav-link">
                                <i class="fas fa-truck me-2"></i>Deliveries
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="reports.php" class="nav-link">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item mt-5">
                            <a href="../../api/logout.php" class="nav-link text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col">
                <div class="main-content">
                    <!-- Header -->
                    <div class="department-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-2">Block Factory Dashboard</h2>
                                <p class="mb-0 opacity-75">Welcome back, <?php echo htmlspecialchars($current_user['full_name'] ?? 'User'); ?>!</p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-light text-dark p-2">
                                    <i class="far fa-calendar me-2"></i><?php echo date('F j, Y'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stats Row -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-cubes"></i>
                                </div>
                                <h3 class="mb-1"><?php echo number_format($stats['total_blocks'] ?? 0); ?></h3>
                                <p class="text-muted mb-0">Blocks in Stock</p>
                                <small class="text-success">
                                    <i class="fas fa-box me-1"></i><?php echo $stats['total_products'] ?? 0; ?> product types
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #f72585, #b5179e);">
                                    <i class="fas fa-industry"></i>
                                </div>
                                <h3 class="mb-1"><?php echo $stats['today_blocks'] ?? 0; ?></h3>
                                <p class="text-muted mb-0">Produced Today</p>
                                <small class="text-info">
                                    <i class="fas fa-clock me-1"></i><?php echo $stats['today_production'] ?? 0; ?> batches
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #f8961e, #f3722c);">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h3 class="mb-1"><?php echo formatMoney($stats['today_sales'] ?? 0); ?></h3>
                                <p class="text-muted mb-0">Sales Today</p>
                                <small class="text-warning">
                                    <i class="fas fa-truck me-1"></i><?php echo $stats['pending_deliveries'] ?? 0; ?> pending
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #4361ee, #3f37c9);">
                                    <i class="fas fa-flask"></i>
                                </div>
                                <h3 class="mb-1"><?php echo $stats['avg_defect_rate'] ?? 0; ?>%</h3>
                                <p class="text-muted mb-0">Avg Defect Rate</p>
                                <small class="text-danger">
                                    <i class="fas fa-exclamation-triangle me-1"></i><?php echo $stats['low_materials'] ?? 0; ?> low materials
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Second Row -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="stat-card">
                                <h5 class="mb-3">Recent Production</h5>
                                <?php if ($recent_production && $recent_production->num_rows > 0): ?>
                                    <?php while($prod = $recent_production->fetch_assoc()): ?>
                                    <div class="production-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong>Batch #<?php echo $prod['batch_number']; ?></strong>
                                                <br>
                                                <small><?php echo htmlspecialchars($prod['product_name']); ?></small>
                                            </div>
                                            <span class="badge bg-<?php echo ($prod['defect_rate'] ?? 0) <= 2 ? 'success' : (($prod['defect_rate'] ?? 0) <= 5 ? 'warning' : 'danger'); ?>">
                                                <?php echo $prod['defect_rate'] ?? 0; ?>% defects
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <small>
                                                <i class="far fa-calendar me-1"></i><?php echo date('d/m/Y', strtotime($prod['production_date'])); ?>
                                            </small>
                                            <small>
                                                <i class="fas fa-check-circle me-1"></i><?php echo $prod['good_quantity'] ?? 0; ?>/<?php echo $prod['produced_quantity'] ?? 0; ?> good
                                            </small>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No production records found</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="stat-card mt-3">
                                <h5 class="mb-3">Low Raw Materials</h5>
                                <?php if ($low_materials && $low_materials->num_rows > 0): ?>
                                    <?php while($material = $low_materials->fetch_assoc()): ?>
                                    <div class="material-alert d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($material['material_name']); ?></strong>
                                            <br>
                                            <small>Stock: <?php echo $material['stock_quantity'] ?? 0; ?> / Min: <?php echo $material['minimum_stock'] ?? 0; ?> <?php echo $material['unit'] ?? ''; ?></small>
                                        </div>
                                        <button class="btn btn-sm btn-warning" onclick="orderMaterial(<?php echo $material['material_id']; ?>)">
                                            <i class="fas fa-shopping-cart"></i> Order
                                        </button>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">All materials are adequately stocked</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="stat-card">
                                <h5 class="mb-3">Recent Sales</h5>
                                <?php if ($recent_sales && $recent_sales->num_rows > 0): ?>
                                    <?php while($sale = $recent_sales->fetch_assoc()): ?>
                                    <div class="sale-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <strong>Invoice #<?php echo $sale['invoice_number']; ?></strong>
                                                <br>
                                                <small><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></small>
                                            </div>
                                            <span class="badge bg-<?php echo ($sale['payment_status'] ?? '') == 'Paid' ? 'success' : (($sale['payment_status'] ?? '') == 'Partial' ? 'warning' : 'danger'); ?>">
                                                <?php echo $sale['payment_status'] ?? 'Unknown'; ?>
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <small>
                                                <i class="fas fa-cubes me-1"></i><?php echo $sale['quantity'] ?? 0; ?> units
                                            </small>
                                            <strong><?php echo formatMoney($sale['total_amount'] ?? 0); ?></strong>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No sales records found</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="stat-card mt-3">
                                <h5 class="mb-3">Top Products</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Sales</th>
                                                <th>Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($top_products && $top_products->num_rows > 0): ?>
                                                <?php while($product = $top_products->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                    <td><?php echo $product['sale_count'] ?? 0; ?></td>
                                                    <td><?php echo formatMoney($product['total_value'] ?? 0); ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center py-2">No data available</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="stat-card mt-3">
                                <h5 class="mb-3">Pending Deliveries</h5>
                                <?php if ($pending_deliveries && $pending_deliveries->num_rows > 0): ?>
                                    <?php while($delivery = $pending_deliveries->fetch_assoc()): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 border-bottom">
                                        <div>
                                            <strong>Delivery #<?php echo $delivery['delivery_note']; ?></strong>
                                            <br>
                                            <small><?php echo htmlspecialchars($delivery['customer_name']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="delivery-badge delivery-<?php echo strtolower($delivery['status']); ?>">
                                                <?php echo $delivery['status']; ?>
                                            </span>
                                            <br>
                                            <small><?php echo date('d/m', strtotime($delivery['delivery_date'])); ?></small>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No pending deliveries</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="quick-actions mt-4">
                        <div class="quick-action-btn" onclick="window.location.href='record-production.php'">
                            <i class="fas fa-plus-circle"></i>
                            <span>Record Production</span>
                            <small class="text-muted d-block">New batch</small>
                        </div>
                        <div class="quick-action-btn" onclick="window.location.href='add-sale.php'">
                            <i class="fas fa-shopping-cart"></i>
                            <span>New Sale</span>
                            <small class="text-muted d-block">Record sale</small>
                        </div>
                        <div class="quick-action-btn" onclick="window.location.href='add-product.php'">
                            <i class="fas fa-cube"></i>
                            <span>Add Product</span>
                            <small class="text-muted d-block">New product type</small>
                        </div>
                        <div class="quick-action-btn" onclick="window.location.href='schedule-delivery.php'">
                            <i class="fas fa-truck"></i>
                            <span>Schedule Delivery</span>
                            <small class="text-muted d-block">Arrange transport</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function orderMaterial(id) {
            window.location.href = 'order-material.php?id=' + id;
        }
    </script>
</body>
</html>