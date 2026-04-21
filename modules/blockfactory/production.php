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

// Handle production actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $product_id = intval($_POST['product_id']);
                $production_date = $_POST['production_date'];
                $shift = $db->escapeString($_POST['shift']);
                $supervisor = $db->escapeString($_POST['supervisor']);
                $planned_quantity = intval($_POST['planned_quantity']);
                $produced_quantity = intval($_POST['produced_quantity']);
                $good_quantity = intval($_POST['good_quantity']);
                $defective_quantity = intval($_POST['defective_quantity']);
                $notes = $db->escapeString($_POST['notes']);
                
                // Calculate defect rate
                $defect_rate = $produced_quantity > 0 ? round(($defective_quantity / $produced_quantity) * 100, 2) : 0;
                
                // Generate batch number
                $batch_number = 'BATCH-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                
                $sql = "INSERT INTO blockfactory_production (batch_number, product_id, production_date, shift, supervisor, 
                        planned_quantity, produced_quantity, good_quantity, defective_quantity, defect_rate, notes, created_by, admin_approvals) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("sisssiiiidssi", $batch_number, $product_id, $production_date, $shift, $supervisor,
                                 $planned_quantity, $produced_quantity, $good_quantity, $defective_quantity, $defect_rate, $notes, $current_user['user_id']);
                
                if ($stmt->execute()) {
                    // Update product stock
                    $update_sql = "UPDATE blockfactory_products SET current_stock = current_stock + ? WHERE product_id = ?";
                    $update_stmt = $db->prepare($update_sql);
                    $update_stmt->bind_param("ii", $good_quantity, $product_id);
                    $update_stmt->execute();
                    
                    $_SESSION['success'] = 'Production recorded successfully. Batch: ' . $batch_number;
                    log_activity($current_user['user_id'], 'Add Production', "Added production batch: $batch_number");
                } else {
                    $_SESSION['error'] = 'Error recording production: ' . $db->error();
                }
                break;
                
            case 'edit':
                $production_id = intval($_POST['production_id']);
                $good_quantity = intval($_POST['good_quantity']);
                $defective_quantity = intval($_POST['defective_quantity']);
                $notes = $db->escapeString($_POST['notes']);
                
                // Get current production data
                $get_sql = "SELECT product_id, produced_quantity, good_quantity FROM blockfactory_production WHERE production_id = ?";
                $get_stmt = $db->prepare($get_sql);
                $get_stmt->bind_param("i", $production_id);
                $get_stmt->execute();
                $current = $get_stmt->get_result()->fetch_assoc();
                
                // Calculate new defect rate
                $produced_quantity = $current['produced_quantity'];
                $defect_rate = $produced_quantity > 0 ? round(($defective_quantity / $produced_quantity) * 100, 2) : 0;
                
                // Update stock (adjust difference)
                $old_good = $current['good_quantity'];
                $difference = $good_quantity - $old_good;
                
                if ($difference != 0) {
                    $update_stock = "UPDATE blockfactory_products SET current_stock = current_stock + ? WHERE product_id = ?";
                    $stock_stmt = $db->prepare($update_stock);
                    $stock_stmt->bind_param("ii", $difference, $current['product_id']);
                    $stock_stmt->execute();
                }
                
                $sql = "UPDATE blockfactory_production SET good_quantity = ?, defective_quantity = ?, defect_rate = ?, notes = ? WHERE production_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("iidssi", $good_quantity, $defective_quantity, $defect_rate, $notes, $production_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Production updated successfully';
                    log_activity($current_user['user_id'], 'Edit Production', "Edited production ID: $production_id");
                } else {
                    $_SESSION['error'] = 'Error updating production';
                }
                break;
                
            case 'delete':
                $production_id = intval($_POST['production_id']);
                
                // Get production data before deleting
                $get_sql = "SELECT product_id, good_quantity FROM blockfactory_production WHERE production_id = ?";
                $get_stmt = $db->prepare($get_sql);
                $get_stmt->bind_param("i", $production_id);
                $get_stmt->execute();
                $prod = $get_stmt->get_result()->fetch_assoc();
                
                if ($prod) {
                    // Remove from stock
                    $update_stock = "UPDATE blockfactory_products SET current_stock = current_stock - ? WHERE product_id = ?";
                    $stock_stmt = $db->prepare($update_stock);
                    $stock_stmt->bind_param("ii", $prod['good_quantity'], $prod['product_id']);
                    $stock_stmt->execute();
                    
                    // Delete production record
                    $sql = "DELETE FROM blockfactory_production WHERE production_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("i", $production_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Production deleted successfully';
                        log_activity($current_user['user_id'], 'Delete Production', "Deleted production ID: $production_id");
                    } else {
                        $_SESSION['error'] = 'Error deleting production';
                    }
                }
                break;
        }
        header('Location: production.php');
        exit();
    }
}

// Get filter parameters
$product_filter = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Build query for production batches
$query = "SELECT p.*, pr.product_name, pr.product_code, u.full_name as created_by_name
          FROM blockfactory_production p
          JOIN blockfactory_products pr ON p.product_id = pr.product_id
          LEFT JOIN users u ON p.created_by = u.user_id
          WHERE pr.company_id = ?";
$params = [$company_id];
$types = "i";

if ($product_filter > 0) {
    $query .= " AND p.product_id = ?";
    $params[] = $product_filter;
    $types .= "i";
}
if (!empty($date_from)) {
    $query .= " AND p.production_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if (!empty($date_to)) {
    $query .= " AND p.production_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}
$query .= " ORDER BY p.production_date DESC, p.created_at DESC";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$production_batches = $stmt->get_result();

// Get products for dropdown
$products = $db->query("SELECT product_id, product_name, product_code FROM blockfactory_products WHERE company_id = $company_id AND status = 'Active' ORDER BY product_name");

// Get summary statistics
$stats_query = "SELECT 
                COUNT(*) as total_batches,
                COALESCE(SUM(produced_quantity), 0) as total_produced,
                COALESCE(SUM(good_quantity), 0) as total_good,
                COALESCE(SUM(defective_quantity), 0) as total_defective,
                COALESCE(AVG(defect_rate), 0) as avg_defect_rate
                FROM blockfactory_production p
                JOIN blockfactory_products pr ON p.product_id = pr.product_id
                WHERE pr.company_id = ? AND p.production_date BETWEEN ? AND ?";
$stmt = $db->prepare($stats_query);
$stmt->bind_param("iss", $company_id, $date_from, $date_to);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$page_title = 'Production';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production - Block Factory</title>
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
        
        .defect-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .defect-low { background: #d4edda; color: #155724; }
        .defect-medium { background: #fff3cd; color: #856404; }
        .defect-high { background: #f8d7da; color: #721c24; }
        
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
                            <h4 class="mb-1">Production Management</h4>
                            <p class="text-muted mb-0">Manage production batches and quality control</p>
                        </div>
                        <div class="column align-items-left">
                        <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                            <i class="fas fa-bars"></i>
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductionModal">
                            <i class="fas fa-plus-circle me-2"></i>Record New Production
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
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Total Batches</p>
                                <h3><?php echo intval($stats['total_batches'] ?? 0); ?></h3>
                                <small>Selected period</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Total Produced</p>
                                <h3><?php echo number_format($stats['total_produced'] ?? 0); ?></h3>
                                <small>Units</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Good Units</p>
                                <h3><?php echo number_format($stats['total_good'] ?? 0); ?></h3>
                                <small><?php echo $stats['total_produced'] > 0 ? round(($stats['total_good'] / $stats['total_produced']) * 100, 2) : 0; ?>% yield</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Avg Defect Rate</p>
                                <h3><?php echo round($stats['avg_defect_rate'] ?? 0, 2); ?>%</h3>
                                <small>Quality metric</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Filter by Product</label>
                                <select class="form-control" name="product_id" onchange="this.form.submit()">
                                    <option value="0">All Products</option>
                                    <?php 
                                    $products->data_seek(0);
                                    while($product = $products->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $product['product_id']; ?>" <?php echo $product_filter == $product['product_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
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
                                <a href="production.php" class="btn btn-secondary">Clear Filters</a>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-success" onclick="exportProduction()">
                                    <i class="fas fa-download me-2"></i>Export
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Production Table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="productionTable">
                                    <thead>
                                        <tr>
                                            <th>Batch #</th>
                                            <th>Date</th>
                                            <th>Product</th>
                                            <th>Shift</th>
                                            <th>Supervisor</th>
                                            <th>Planned</th>
                                            <th>Produced</th>
                                            <th>Good</th>
                                            <th>Defects</th>
                                            <th>Defect Rate</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($production_batches && $production_batches->num_rows > 0): ?>
                                            <?php while($batch = $production_batches->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?php echo $batch['batch_number']; ?></strong></td>
                                                <td><?php echo date('d/m/Y', strtotime($batch['production_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($batch['product_name']); ?></td>
                                                <td><?php echo $batch['shift']; ?></td>
                                                <td><?php echo htmlspecialchars($batch['supervisor']); ?></td>
                                                <td><?php echo number_format($batch['planned_quantity']); ?></td>
                                                <td><?php echo number_format($batch['produced_quantity']); ?></td>
                                                <td><?php echo number_format($batch['good_quantity']); ?></td>
                                                <td><?php echo number_format($batch['defective_quantity']); ?></td>
                                                <td>
                                                    <span class="defect-badge defect-<?php echo $batch['defect_rate'] <= 2 ? 'low' : ($batch['defect_rate'] <= 5 ? 'medium' : 'high'); ?>">
                                                        <?php echo $batch['defect_rate']; ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewBatch(<?php echo $batch['production_id']; ?>)" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="editBatch(<?php echo $batch['production_id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($role == 'SuperAdmin'): ?>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteBatch(<?php echo $batch['production_id']; ?>, '<?php echo $batch['batch_number']; ?>')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="11" class="text-center py-4">
                                                    <i class="fas fa-industry fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No production records found</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Production Modal -->
    <div class="modal fade" id="addProductionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record New Production Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product</label>
                                <select class="form-control" name="product_id" required>
                                    <option value="">Select Product</option>
                                    <?php 
                                    $products->data_seek(0);
                                    while($product = $products->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $product['product_id']; ?>">
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Production Date</label>
                                <input type="date" class="form-control" name="production_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Shift</label>
                                <select class="form-control" name="shift" required>
                                    <option value="Morning">Morning</option>
                                    <option value="Afternoon">Afternoon</option>
                                    <option value="Night">Night</option>
                                </select>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Supervisor</label>
                                <input type="text" class="form-control" name="supervisor" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Planned Quantity</label>
                                <input type="number" class="form-control" name="planned_quantity" required min="1">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Produced Quantity</label>
                                <input type="number" class="form-control" name="produced_quantity" id="produced_quantity" required min="1">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Good Quantity</label>
                                <input type="number" class="form-control" name="good_quantity" id="good_quantity" required min="0">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Defective Quantity</label>
                                <input type="number" class="form-control" name="defective_quantity" id="defective_quantity" required min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Defect Rate</label>
                                <input type="text" class="form-control" id="defect_rate_display" readonly>
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
    
    <!-- Edit Production Modal -->
    <div class="modal fade" id="editProductionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Production Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editProductionForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="production_id" id="edit_production_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Batch Number</label>
                                <input type="text" class="form-control" id="edit_batch_number" readonly disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product</label>
                                <input type="text" class="form-control" id="edit_product_name" readonly disabled>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Production Date</label>
                                <input type="text" class="form-control" id="edit_production_date" readonly disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Shift</label>
                                <input type="text" class="form-control" id="edit_shift" readonly disabled>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Produced Quantity</label>
                                <input type="text" class="form-control" id="edit_produced_quantity" readonly disabled>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Good Quantity</label>
                                <input type="number" class="form-control" name="good_quantity" id="edit_good_quantity" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Defective Quantity</label>
                                <input type="number" class="form-control" name="defective_quantity" id="edit_defective_quantity" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Production</button>
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
                    <p>Are you sure you want to delete batch <strong id="deleteBatchNumber"></strong>?</p>
                    <p class="text-danger">This will also adjust product stock levels.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="production_id" id="deleteProductionId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Batch</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#productionTable').DataTable({
                order: [[1, 'desc']],
                pageLength: 25
            });
            
            // Calculate defect rate in add modal
            $('#produced_quantity, #good_quantity, #defective_quantity').on('input', function() {
                var produced = parseInt($('#produced_quantity').val()) || 0;
                var defective = parseInt($('#defective_quantity').val()) || 0;
                var rate = produced > 0 ? ((defective / produced) * 100).toFixed(2) : 0;
                $('#defect_rate_display').val(rate + '%');
            });
        });
        
        function viewBatch(id) {
            window.location.href = '../../api/view-batch.php?id=' + id;
        }
        
        function editBatch(id) {
            window.location.href = '../../api/edit-good.php?id=' + id;            
        }
        
        function deleteBatch(id, batchNumber) {
            $('#deleteProductionId').val(id);
            $('#deleteBatchNumber').text(batchNumber);
            $('#deleteModal').modal('show');
        }
        
        function exportProduction() {
            var product = '<?php echo $product_filter; ?>';
            var from = '<?php echo $date_from; ?>';
            var to = '<?php echo $date_to; ?>';
            window.location.href = 'export-production.php?product=' + product + '&from=' + from + '&to=' + to;
        }
    </script>
</body>
</html>