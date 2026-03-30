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

// Today's purchases
$purchase_query = "SELECT COALESCE(SUM(total_amount), 0) as total FROM procurement_purchase_orders 
                   WHERE DATE(order_date) = CURDATE()
                   AND supplier_id IN (SELECT supplier_id FROM procurement_suppliers WHERE company_id = ?)";
$stmt = $db->prepare($purchase_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['today_purchases'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$chart_query = "
                SELECT 
                    MONTH(order_date) AS month,
                    COALESCE(SUM(total_amount),0) AS total
                FROM procurement_purchase_orders
                WHERE YEAR(order_date) = YEAR(CURDATE())
                AND supplier_id IN (
                    SELECT supplier_id 
                    FROM procurement_suppliers 
                    WHERE company_id = ?
                )
                GROUP BY MONTH(order_date)
                ORDER BY MONTH(order_date)
            ";

$stmt = $db->prepare($chart_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$chart_result = $stmt->get_result();

$monthly_data = array_fill(1, 12, 0);

while ($row = $chart_result->fetch_assoc()) {
    $monthly_data[$row['month']] = (float)$row['total'];
}

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

    <!-- Custom CSS For jus Procuremnt Pages-->
    <link href="../../assets/css/procurement/dashboard.css" rel="stylesheet">

</head>

<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Include sidebar -->
            <?php include dirname(__DIR__, 2) . '/includes/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col">
                <div class="main-content">
                    <!-- Header -->
                    <div class="department-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-2">Procurement Dashboard</h2>
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
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Low Stock Alert</h5>
                                    <span class="badge bg-danger"><?php echo $low_stock_items->num_rows; ?> Items</span>
                                </div>
                                <div class="card-body">
                                    <?php if ($low_stock_items->num_rows > 0): ?>
                                        <?php while ($product = $low_stock_items->fetch_assoc()): ?>
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
                                        <canvas id="monthlyChart" height="120"></canvas>
                                    </div>
                                </div>
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
                                            <?php while ($supplier = $top_suppliers->fetch_assoc()): ?>
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
                                    <?php while ($order = $recent_orders->fetch_assoc()): ?>
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
                        <div class="quick-action-btn" onclick="window.location.href='suppliers.php'">
                            <i class="fas fa-plus-circle"></i>
                            <span>Add Supplier</span>
                            <small class="text-muted d-block">Register new supplier</small>
                        </div>
                        <div class="quick-action-btn" onclick="window.location.href='products.php'">
                            <i class="fas fa-cube"></i>
                            <span>Add Product</span>
                            <small class="text-muted d-block">Create new product</small>
                        </div>
                        <div class="quick-action-btn" onclick="window.location.href='purchase.php'">
                            <i class="fas fa-file-invoice"></i>
                            <span>Create PO</span>
                            <small class="text-muted d-block">New purchase order</small>
                        </div>
                        <div class="quick-action-btn" onclick="window.location.href='approvals.php'">
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
        <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>

    <script>
        const monthlyPurchases = <?php echo json_encode(array_values($monthly_data)); ?>;
        // Monthly Chart Initialization
        const ctx = document.getElementById('monthlyChart').getContext('2d');

        const monthlyChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [
                    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
                ],
                datasets: [{
                    label: 'Monthly Purchases',
                    data: monthlyPurchases,
                    fill: true,
                    borderColor: '#4cc9f0',
                    backgroundColor: 'rgba(76, 201, 240, 0.1)',
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return "GHS " + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return "GHS " + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        function reorderProduct(id) {
            window.location.href = 'purchase.php?product=' + id;
        }
    </script>
</body>

</html>