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

// Handle delivery actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $sale_id = intval($_POST['sale_id']);
                $delivery_date = $_POST['delivery_date'];
                $vehicle_number = $db->escapeString($_POST['vehicle_number']);
                $driver_name = $db->escapeString($_POST['driver_name']);
                $driver_phone = $db->escapeString($_POST['driver_phone']);
                $quantity = intval($_POST['quantity']);
                $destination = $db->escapeString($_POST['destination']);
                $delivery_charges = floatval($_POST['delivery_charges'] ?? 0);
                $notes = $db->escapeString($_POST['notes']);
                
                // Get sale details
                $sale_query = "SELECT s.*, pr.product_name, c.customer_name 
                              FROM blockfactory_sales s
                              JOIN blockfactory_products pr ON s.product_id = pr.product_id
                              LEFT JOIN blockfactory_customers c ON s.customer_id = c.customer_id
                              WHERE s.sale_id = ?";
                $sale_stmt = $db->prepare($sale_query);
                $sale_stmt->bind_param("i", $sale_id);
                $sale_stmt->execute();
                $sale = $sale_stmt->get_result()->fetch_assoc();
                
                if (!$sale) {
                    $_SESSION['error'] = 'Sale not found';
                } else {
                    // Check if quantity exceeds sale quantity
                    $delivered_query = "SELECT COALESCE(SUM(quantity), 0) as total_delivered FROM blockfactory_deliveries WHERE sale_id = ?";
                    $delivered_stmt = $db->prepare($delivered_query);
                    $delivered_stmt->bind_param("i", $sale_id);
                    $delivered_stmt->execute();
                    $delivered = $delivered_stmt->get_result()->fetch_assoc();
                    
                    $remaining = $sale['quantity'] - $delivered['total_delivered'];
                    
                    if ($quantity > $remaining) {
                        $_SESSION['error'] = "Cannot deliver more than remaining quantity. Remaining: $remaining";
                    } else {
                        // Generate delivery note
                        $delivery_note = 'DN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        
                        $sql = "INSERT INTO blockfactory_deliveries (delivery_note, sale_id, delivery_date, vehicle_number, 
                                driver_name, driver_phone, quantity, destination, delivery_charges, notes, status, created_by) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?)";
                        $stmt = $db->prepare($sql);
                        $stmt->bind_param("sisssssdssi", $delivery_note, $sale_id, $delivery_date, $vehicle_number,
                                         $driver_name, $driver_phone, $quantity, $destination, $delivery_charges,
                                         $notes, $current_user['user_id']);
                        
                        if ($stmt->execute()) {
                            // Update sale delivery status if all delivered
                            $new_total = $delivered['total_delivered'] + $quantity;
                            $delivery_status = 'Partial';
                            if ($new_total >= $sale['quantity']) {
                                $delivery_status = 'Delivered';
                            }
                            
                            $update_sale = "UPDATE blockfactory_sales SET delivery_status = ? WHERE sale_id = ?";
                            $update_stmt = $db->prepare($update_sale);
                            $update_stmt->bind_param("si", $delivery_status, $sale_id);
                            $update_stmt->execute();
                            
                            $_SESSION['success'] = 'Delivery scheduled successfully. Note: ' . $delivery_note;
                            log_activity($current_user['user_id'], 'Schedule Delivery', "Scheduled delivery for sale ID: $sale_id");
                        } else {
                            $_SESSION['error'] = 'Error scheduling delivery: ' . $db->error();
                        }
                    }
                }
                break;
                
            case 'update_status':
                $delivery_id = intval($_POST['delivery_id']);
                $status = $db->escapeString($_POST['status']);
                
                $sql = "UPDATE blockfactory_deliveries SET status = ? WHERE delivery_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("si", $status, $delivery_id);
                
                if ($stmt->execute()) {
                    // If delivered, update sale delivery status
                    if ($status == 'Delivered') {
                        $get_sql = "SELECT sale_id FROM blockfactory_deliveries WHERE delivery_id = ?";
                        $get_stmt = $db->prepare($get_sql);
                        $get_stmt->bind_param("i", $delivery_id);
                        $get_stmt->execute();
                        $delivery = $get_stmt->get_result()->fetch_assoc();
                        
                        // Check if all deliveries for this sale are completed
                        $check_sql = "SELECT SUM(quantity) as total_delivered, s.quantity as sale_quantity 
                                     FROM blockfactory_deliveries d
                                     JOIN blockfactory_sales s ON d.sale_id = s.sale_id
                                     WHERE d.sale_id = ? AND d.status = 'Delivered'";
                        $check_stmt = $db->prepare($check_sql);
                        $check_stmt->bind_param("i", $delivery['sale_id']);
                        $check_stmt->execute();
                        $result = $check_stmt->get_result()->fetch_assoc();
                        
                        if ($result['total_delivered'] >= $result['sale_quantity']) {
                            $update_sale = "UPDATE blockfactory_sales SET delivery_status = 'Delivered' WHERE sale_id = ?";
                            $update_stmt = $db->prepare($update_sale);
                            $update_stmt->bind_param("i", $delivery['sale_id']);
                            $update_stmt->execute();
                        }
                    }
                    
                    $_SESSION['success'] = 'Delivery status updated successfully';
                    log_activity($current_user['user_id'], 'Update Delivery', "Updated delivery ID: $delivery_id to $status");
                } else {
                    $_SESSION['error'] = 'Error updating delivery status';
                }
                break;
                
            case 'delete':
                $delivery_id = intval($_POST['delivery_id']);
                
                // Get delivery details before deleting
                $get_sql = "SELECT sale_id, quantity FROM blockfactory_deliveries WHERE delivery_id = ?";
                $get_stmt = $db->prepare($get_sql);
                $get_stmt->bind_param("i", $delivery_id);
                $get_stmt->execute();
                $delivery = $get_stmt->get_result()->fetch_assoc();
                
                if ($delivery) {
                    // Delete delivery
                    $sql = "DELETE FROM blockfactory_deliveries WHERE delivery_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("i", $delivery_id);
                    
                    if ($stmt->execute()) {
                        // Update sale delivery status
                        $remaining_sql = "SELECT COALESCE(SUM(quantity), 0) as total_delivered FROM blockfactory_deliveries WHERE sale_id = ?";
                        $remaining_stmt = $db->prepare($remaining_sql);
                        $remaining_stmt->bind_param("i", $delivery['sale_id']);
                        $remaining_stmt->execute();
                        $remaining = $remaining_stmt->get_result()->fetch_assoc();
                        
                        $delivery_status = 'Pending';
                        if ($remaining['total_delivered'] > 0) {
                            $delivery_status = 'Partial';
                        }
                        
                        $update_sale = "UPDATE blockfactory_sales SET delivery_status = ? WHERE sale_id = ?";
                        $update_stmt = $db->prepare($update_sale);
                        $update_stmt->bind_param("si", $delivery_status, $delivery['sale_id']);
                        $update_stmt->execute();
                        
                        $_SESSION['success'] = 'Delivery deleted successfully';
                        log_activity($current_user['user_id'], 'Delete Delivery', "Deleted delivery ID: $delivery_id");
                    } else {
                        $_SESSION['error'] = 'Error deleting delivery';
                    }
                }
                break;
        }
        header('Location: deliveries.php');
        exit();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $db->escapeString($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Build query for deliveries
$query = "SELECT d.*, s.invoice_number, s.quantity as sale_quantity, pr.product_name, pr.product_code,
          c.customer_name, c.phone as customer_phone, u.full_name as created_by_name
          FROM blockfactory_deliveries d
          JOIN blockfactory_sales s ON d.sale_id = s.sale_id
          JOIN blockfactory_products pr ON s.product_id = pr.product_id
          LEFT JOIN blockfactory_customers c ON s.customer_id = c.customer_id
          LEFT JOIN users u ON d.created_by = u.user_id
          WHERE pr.company_id = ?";
$params = [$company_id];
$types = "i";

if (!empty($status_filter)) {
    $query .= " AND d.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if (!empty($date_from)) {
    $query .= " AND d.delivery_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if (!empty($date_to)) {
    $query .= " AND d.delivery_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}
$query .= " ORDER BY d.delivery_date DESC, d.created_at DESC";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$deliveries = $stmt->get_result();

// Get statistics - FIXED: Added table alias 'd.' to all status references
$stats_query = "SELECT 
                COUNT(*) as total_deliveries,
                COUNT(CASE WHEN d.status = 'Scheduled' THEN 1 END) as scheduled,
                COUNT(CASE WHEN d.status = 'In Transit' THEN 1 END) as in_transit,
                COUNT(CASE WHEN d.status = 'Delivered' THEN 1 END) as delivered,
                COUNT(CASE WHEN d.status = 'Cancelled' THEN 1 END) as cancelled,
                COALESCE(SUM(d.delivery_charges), 0) as total_charges
                FROM blockfactory_deliveries d
                JOIN blockfactory_sales s ON d.sale_id = s.sale_id
                JOIN blockfactory_products pr ON s.product_id = pr.product_id
                WHERE pr.company_id = ? AND d.delivery_date BETWEEN ? AND ?";
$stmt = $db->prepare($stats_query);
$stmt->bind_param("iss", $company_id, $date_from, $date_to);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get pending sales for dropdown - FIXED: Syntax error and improved customer name handling
$pending_sales_query = "SELECT s.sale_id, s.invoice_number, 
                        COALESCE(c.customer_name, s.customer_name) as customer_name, 
                        s.quantity, s.delivery_address,
                        (s.quantity - COALESCE((SELECT SUM(quantity) FROM blockfactory_deliveries WHERE sale_id = s.sale_id), 0)) as remaining
                        FROM blockfactory_sales s
                        LEFT JOIN blockfactory_customers c ON s.customer_id = c.customer_id
                        JOIN blockfactory_products pr ON s.product_id = pr.product_id
                        WHERE pr.company_id = ? 
                        AND s.delivery_status != 'Delivered'
                        HAVING remaining > 0
                        ORDER BY s.sale_date ASC";

$pending_stmt = $db->prepare($pending_sales_query);
$pending_stmt->bind_param("i", $company_id);
$pending_stmt->execute();
$pending_sales = $pending_stmt->get_result();

$page_title = 'Deliveries';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliveries - Block Factory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #43e97b;
            --secondary-color: #38f9d7;
        }
        
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }
        
        .stats-card h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        
        .filter-section {
            background: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-scheduled { background: #cce5ff; color: #004085; }
        .status-transit { background: #fff3cd; color: #856404; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .delivery-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
            transition: transform 0.3s;
        }
        .delivery-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
                <?php include dirname(__DIR__, 2) . '/includes/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col">
                <div class="main-content">
                    <!-- Page Header -->
                    <div class="page-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">Delivery Management</h4>
                            <p class="text-muted mb-0">Schedule and track deliveries</p>
                        </div>
                        <div>
                        <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                            <i class="fas fa-bars"></i>
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeliveryModal">
                            <i class="fas fa-truck me-2"></i>Schedule New Delivery
                        </button>
                        </div>
                    </div>
                    
                    <!-- Alert Messages -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Stats Cards -->
                    <div class="row">
                        <div class="col-md-2">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Total</p>
                                <h3><?php echo intval($stats['total_deliveries'] ?? 0); ?></h3>
                                <small>All deliveries</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Scheduled</p>
                                <h3><?php echo intval($stats['scheduled'] ?? 0); ?></h3>
                                <small>Pending</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <p class="text-muted mb-1">In Transit</p>
                                <h3><?php echo intval($stats['in_transit'] ?? 0); ?></h3>
                                <small>On the way</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Delivered</p>
                                <h3><?php echo intval($stats['delivered'] ?? 0); ?></h3>
                                <small>Completed</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Charges</p>
                                <h3><?php echo formatMoney($stats['total_charges'] ?? 0); ?></h3>
                                <small>Delivery fees</small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Cancelled</p>
                                <h3><?php echo intval($stats['cancelled'] ?? 0); ?></h3>
                                <small>Failed</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="Scheduled" <?php echo $status_filter == 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="In Transit" <?php echo $status_filter == 'In Transit' ? 'selected' : ''; ?>>In Transit</option>
                                    <option value="Delivered" <?php echo $status_filter == 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="Cancelled" <?php echo $status_filter == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <a href="deliveries.php" class="btn btn-secondary">Clear Filters</a>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-success" onclick="exportDeliveries()">
                                    <i class="fas fa-download me-2"></i>Export
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Deliveries List -->
                    <div class="row">
                        <?php if ($deliveries && $deliveries->num_rows > 0): ?>
                            <?php while($delivery = $deliveries->fetch_assoc()): ?>
                            <div class="col-md-6">
                                <div class="delivery-card">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">
                                                <strong>Delivery #<?php echo $delivery['delivery_note']; ?></strong>
                                            </h6>
                                            <p class="mb-1">
                                                <i class="fas fa-file-invoice me-1"></i> Invoice: <?php echo $delivery['invoice_number']; ?>
                                            </p>
                                        </div>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $delivery['status'])); ?>">
                                            <?php echo $delivery['status']; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="row mb-2">
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">
                                                <i class="fas fa-calendar me-1"></i> Date: <?php echo date('d/m/Y', strtotime($delivery['delivery_date'])); ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-box me-1"></i> Product: <?php echo $delivery['product_name']; ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-hashtag me-1"></i> Quantity: <?php echo number_format($delivery['quantity']); ?> units
                                            </small>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted d-block">
                                                <i class="fas fa-user me-1"></i> Customer: <?php echo htmlspecialchars($delivery['customer_name'] ?? 'N/A'); ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-phone me-1"></i> Phone: <?php echo htmlspecialchars($delivery['customer_phone'] ?? 'N/A'); ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-truck me-1"></i> Vehicle: <?php echo $delivery['vehicle_number']; ?>
                                            </small>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-user-tie me-1"></i> Driver: <?php echo $delivery['driver_name']; ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted d-block">
                                            <i class="fas fa-map-marker-alt me-1"></i> Destination: <?php echo htmlspecialchars($delivery['destination']); ?>
                                        </small>
                                        <?php if ($delivery['delivery_charges'] > 0): ?>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-money-bill me-1"></i> Charges: <?php echo formatMoney($delivery['delivery_charges']); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="d-flex justify-content-end gap-2">
                                        <button class="btn btn-sm btn-info" onclick="viewDelivery(<?php echo $delivery['delivery_id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if ($delivery['status'] == 'Scheduled'): ?>
                                            <button class="btn btn-sm btn-warning" onclick="updateStatus(<?php echo $delivery['delivery_id']; ?>, 'In Transit')" title="Start Delivery">
                                                <i class="fas fa-play"></i> Start
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($delivery['status'] == 'In Transit'): ?>
                                            <button class="btn btn-sm btn-success" onclick="updateStatus(<?php echo $delivery['delivery_id']; ?>, 'Delivered')" title="Mark Delivered">
                                                <i class="fas fa-check"></i> Deliver
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($delivery['status'] != 'Delivered'): ?>
                                            <button class="btn btn-sm btn-danger" onclick="updateStatus(<?php echo $delivery['delivery_id']; ?>, 'Cancelled')" title="Cancel Delivery">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($role == 'SuperAdmin'): ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteDelivery(<?php echo $delivery['delivery_id']; ?>, '<?php echo $delivery['delivery_note']; ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <!-- <button class="btn btn-sm btn-primary" onclick="printDeliveryNote(<?php echo $delivery['delivery_id']; ?>)" title="Print Delivery Note">
                                            <i class="fas fa-print"></i>
                                        </button> -->
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="text-center py-5">
                                    <i class="fas fa-truck fa-4x text-muted mb-3"></i>
                                    <h5>No Deliveries Found</h5>
                                    <p class="text-muted">Click "Schedule New Delivery" to create a delivery.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Delivery Modal -->
    <div class="modal fade" id="addDeliveryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule New Delivery</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Sale</label>
                            <select class="form-control" name="sale_id" id="saleSelect" required>
                                <option value="">Choose Invoice</option>
                                <?php if ($pending_sales && $pending_sales->num_rows > 0): ?>
                                    <?php while($sale = $pending_sales->fetch_assoc()): ?>
                                    <option value="<?php echo $sale['sale_id']; ?>" 
                                            data-remaining="<?php echo $sale['remaining']; ?>"
                                            data-address="<?php echo htmlspecialchars($sale['delivery_address']); ?>">
                                        <?php echo $sale['invoice_number']; ?> - 
                                        <?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?> 
                                        (Remaining: <?php echo $sale['remaining']; ?> units)
                                    </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Delivery Date</label>
                                <input type="date" class="form-control" name="delivery_date" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="quantity" id="deliveryQuantity" required min="1">
                                <small class="text-muted" id="remainingDisplay"></small>
                            </div>
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
                            <label class="form-label">Destination Address</label>
                            <textarea class="form-control" name="destination" id="destinationAddress" rows="2" required></textarea>
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
    
    <!-- Update Status Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Delivery Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="statusForm">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="delivery_id" id="status_delivery_id">
                    <input type="hidden" name="status" id="status_value">
                    <div class="modal-body">
                        <p>Are you sure you want to mark this delivery as <strong id="status_display"></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="status_confirm">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete delivery <strong id="deleteDeliveryNote"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="delivery_id" id="deleteDeliveryId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Delivery</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>
    
    <script>
        $(document).ready(function() {
            // Update quantity max and destination when sale selected
            $('#saleSelect').change(function() {
                var selected = $(this).find(':selected');
                var remaining = selected.data('remaining');
                var address = selected.data('address');
                
                $('#deliveryQuantity').attr('max', remaining);
                $('#remainingDisplay').text('Maximum available: ' + remaining);
                
                if (address) {
                    $('#destinationAddress').val(address);
                }
            });
        });
        
        function viewDelivery(id) {
            window.location.href = '../../api/view-delivery.php?id=' + id;
        }
        
        function updateStatus(id, status) {
            $('#status_delivery_id').val(id);
            $('#status_value').val(status);
            
            var display = status;
            if (status == 'In Transit') display = 'In Transit';
            $('#status_display').text(display);
            
            var btnClass = 'btn-primary';
            if (status == 'Delivered') btnClass = 'btn-success';
            if (status == 'Cancelled') btnClass = 'btn-danger';
            $('#status_confirm').removeClass('btn-primary btn-success btn-danger').addClass(btnClass);
            
            $('#statusModal').modal('show');
            // TODO
        }
        
        function printDeliveryNote(id) {
            window.open('print-delivery-note.php?id=' + id, '_blank', 'width=800,height=600');
        }
        
        function deleteDelivery(id, note) {
            $('#deleteDeliveryId').val(id);
            $('#deleteDeliveryNote').text(note);
            $('#deleteModal').modal('show');
        }
        
        function exportDeliveries() {
            var status = '<?php echo $status_filter; ?>';
            var from = '<?php echo $date_from; ?>';
            var to = '<?php echo $date_to; ?>';
            window.location.href = 'export-deliveries.php?status=' + status + '&from=' + from + '&to=' + to;
        }
    </script>
</body>
</html>