<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db_connection.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

$session->requireLogin();

$current_user = $session->getCurrentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

// Ensure only Procurement users can access
if ($current_user['company_type'] != 'Procurement' && $role != 'SuperAdmin') {
    $_SESSION['error'] = 'Access denied. Procurement department only.';
    header('Location: ../../login.php');
    exit();
}

global $db;

// Get procurement-specific statistics
$stats = [];

// Total suppliers
$stmt = $db->prepare("SELECT COUNT(*) as count FROM procurement_suppliers WHERE company_id = ? AND status = 'Active'");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['total_suppliers'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Total products
$result = $db->query("SELECT COUNT(*) as count FROM procurement_products WHERE status = 'Active'");
$stats['total_products'] = $result->fetch_assoc()['count'] ?? 0;

// Pending orders
$stmt = $db->prepare("SELECT COUNT(*) as count FROM procurement_purchase_orders WHERE delivery_status = 'Pending' AND supplier_id IN (SELECT supplier_id FROM procurement_suppliers WHERE company_id = ?)");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['pending_orders'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Low stock items
$result = $db->query("SELECT COUNT(*) as count FROM procurement_products WHERE current_stock <= minimum_stock");
$stats['low_stock'] = $result->fetch_assoc()['count'] ?? 0;

// Total inventory value
$value_query = "SELECT COALESCE(SUM(current_stock * unit_price), 0) as total FROM procurement_products";
$result = $db->query($value_query);
$stats['inventory_value'] = $result->fetch_assoc()['total'] ?? 0;

// Monthly purchases
$purchase_query = "SELECT COALESCE(SUM(total_amount), 0) as total FROM procurement_purchase_orders 
                   WHERE MONTH(order_date) = MONTH(CURRENT_DATE()) 
                   AND YEAR(order_date) = YEAR(CURRENT_DATE())
                   AND supplier_id IN (SELECT supplier_id FROM procurement_suppliers WHERE company_id = ?)";
$stmt = $db->prepare($purchase_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['monthly_purchases'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Top suppliers by order value
$top_suppliers = [];
$top_query = "SELECT s.supplier_name, COUNT(po.po_id) as order_count, COALESCE(SUM(po.total_amount), 0) as total_value
              FROM procurement_suppliers s
              LEFT JOIN procurement_purchase_orders po ON s.supplier_id = po.supplier_id
              WHERE s.company_id = ? AND s.status = 'Active'
              GROUP BY s.supplier_id
              ORDER BY total_value DESC
              LIMIT 5";
$stmt = $db->prepare($top_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$top_suppliers = $stmt->get_result();

// Recent orders
$recent_orders = [];
$orders_query = "SELECT po.*, s.supplier_name 
                 FROM procurement_purchase_orders po
                 JOIN procurement_suppliers s ON po.supplier_id = s.supplier_id
                 WHERE s.company_id = ?
                 ORDER BY po.created_at DESC LIMIT 10";
$stmt = $db->prepare($orders_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$recent_orders = $stmt->get_result();

// Low stock alerts
$low_stock_items = [];
$low_query = "SELECT * FROM procurement_products 
              WHERE current_stock <= minimum_stock 
              AND status = 'Active'
              ORDER BY (current_stock - minimum_stock) ASC LIMIT 10";
$low_stock_items = $db->query($low_query);

// Recent activities
$recent_activities = [];
$activity_query = "SELECT a.*, u.full_name 
                  FROM activity_log a 
                  JOIN users u ON a.user_id = u.user_id 
                  WHERE a.module = 'procurement' OR a.module IS NULL
                  ORDER BY a.created_at DESC LIMIT 10";
$result = $db->query($activity_query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}

$page_title = 'Procurement Dashboard';
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
    <style>
        :root {
            --primary-color: #4cc9f0;
            --secondary-color: #4895ef;
        }
        
        .department-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(76, 201, 240, 0.3);
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
            box-shadow: 0 5px 15px rgba(76, 201, 240, 0.2);
        }
        
        .quick-action-btn i {
            font-size: 28px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-delivered { background: #cce5ff; color: #004085; }
        
        .low-stock-alert {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
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
                        <h4>Procurement</h4>
                        <p class="small text-muted">Supply Chain Management</p>
                    </div>
                    
                    <div class="user-info text-center mb-4 p-3 bg-white bg-opacity-10 rounded">
                        <div class="avatar mx-auto mb-2" style="width: 50px; height: 50px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                            <?php echo getAvatarLetter($current_user['full_name']); ?>
                        </div>
                        <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong>
                        <p class="small text-muted"><?php echo $current_user['company_name'] ?? 'Procurement Dept'; ?></p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link active">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="suppliers.php" class="nav-link">
                                <i class="fas fa-truck me-2"></i>Suppliers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="products.php" class="nav-link">
                                <i class="fas fa-box me-2"></i>Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="purchase-orders.php" class="nav-link">
                                <i class="fas fa-file-invoice me-2"></i>Purchase Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="inventory.php" class="nav-link">
                                <i class="fas fa-warehouse me-2"></i>Inventory
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
                                <h2 class="mb-2">Procurement Dashboard</h2>
                                <p class="mb-0 opacity-75">Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>!</p>
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
                                    <i class="fas fa-truck"></i>
                                </div>
                                <h3 class="mb-1"><?php echo $stats['total_suppliers']; ?></h3>
                                <p class="text-muted mb-0">Active Suppliers</p>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up me-1"></i>12% this month
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #f72585, #b5179e);">
                                    <i class="fas fa-box"></i>
                                </div>
                                <h3 class="mb-1"><?php echo $stats['total_products']; ?></h3>
                                <p class="text-muted mb-0">Products</p>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up me-1"></i>5 new this week
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #f8961e, #f3722c);">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h3 class="mb-1"><?php echo $stats['pending_orders']; ?></h3>
                                <p class="text-muted mb-0">Pending Orders</p>
                                <small class="text-warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i>Needs attention
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <h3 class="mb-1"><?php echo formatMoney($stats['monthly_purchases']); ?></h3>
                                <p class="text-muted mb-0">Monthly Purchases</p>
                                <small class="text-info">
                                    <i class="fas fa-chart-line me-1"></i>This month
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Second Row -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="text-muted mb-1">Inventory Value</p>
                                        <h2><?php echo formatMoney($stats['inventory_value']); ?></h2>
                                    </div>
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #4cc9f0, #4895ef);">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                </div>
                                <div class="progress mt-3" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: 75%"></div>
                                </div>
                                <small class="text-muted mt-2 d-block">75% of target inventory value</small>
                            </div>
                            
                            <div class="stat-card mt-3">
                                <h5 class="mb-3">Low Stock Alerts</h5>
                                <?php if ($low_stock_items && $low_stock_items->num_rows > 0): ?>
                                    <?php while($item = $low_stock_items->fetch_assoc()): ?>
                                    <div class="low-stock-alert d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                            <br>
                                            <small>Stock: <?php echo $item['current_stock']; ?> / Min: <?php echo $item['minimum_stock']; ?></small>
                                        </div>
                                        <button class="btn btn-sm btn-warning" onclick="reorderProduct(<?php echo $item['product_id']; ?>)">
                                            <i class="fas fa-shopping-cart"></i> Reorder
                                        </button>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No low stock items</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="stat-card">
                                <h5 class="mb-3">Top Suppliers</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Supplier</th>
                                                <th>Orders</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($supplier = $top_suppliers->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                                                <td><?php echo $supplier['order_count']; ?></td>
                                                <td><?php echo formatMoney($supplier['total_value']); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="stat-card mt-3">
                                <h5 class="mb-3">Recent Purchase Orders</h5>
                                <div class="list-group">
                                    <?php while($order = $recent_orders->fetch_assoc()): ?>
                                    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>PO-<?php echo $order['po_number']; ?></strong>
                                            <br>
                                            <small><?php echo htmlspecialchars($order['supplier_name']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="status-badge status-<?php echo strtolower($order['delivery_status']); ?>">
                                                <?php echo $order['delivery_status']; ?>
                                            </span>
                                            <br>
                                            <small><?php echo formatMoney($order['total_amount']); ?></small>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="quick-actions mt-4">
                        <div class="quick-action-btn" onclick="window.location.href='add-supplier.php'">
                            <i class="fas fa-plus-circle"></i>
                            <span>Add Supplier</span>
                            <small class="text-muted d-block">Register new supplier</small>
                        </div>
                        <div class="quick-action-btn" onclick="window.location.href='add-product.php'">
                            <i class="fas fa-cube"></i>
                            <span>Add Product</span>
                            <small class="text-muted d-block">Create new product</small>
                        </div>
                        <div class="quick-action-btn" onclick="window.location.href='create-po.php'">
                            <i class="fas fa-file-invoice"></i>
                            <span>Create PO</span>
                            <small class="text-muted d-block">New purchase order</small>
                        </div>
                        <div class="quick-action-btn" onclick="window.location.href='receive-stock.php'">
                            <i class="fas fa-truck-loading"></i>
                            <span>Receive Stock</span>
                            <small class="text-muted d-block">Process delivery</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function reorderProduct(id) {
            window.location.href = 'create-po.php?product=' + id;
        }
    </script>
</body>
</html>