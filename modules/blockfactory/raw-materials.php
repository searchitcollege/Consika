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

// Handle material actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $material_code = $db->escapeString($_POST['material_code']);
                $material_name = $db->escapeString($_POST['material_name']);
                $material_type = $db->escapeString($_POST['material_type']);
                $supplier = $db->escapeString($_POST['supplier']);
                $unit = $db->escapeString($_POST['unit']);
                $stock_quantity = floatval($_POST['stock_quantity'] ?? 0);
                $minimum_stock = floatval($_POST['minimum_stock'] ?? 0);
                $maximum_stock = floatval($_POST['maximum_stock'] ?? 10000);
                $reorder_level = floatval($_POST['reorder_level'] ?? 100);
                $unit_cost = floatval($_POST['unit_cost']);
                $location = $db->escapeString($_POST['location']);
                $notes = $db->escapeString($_POST['notes']);
                
                $sql = "INSERT INTO blockfactory_raw_materials (material_code, material_name, material_type, supplier, 
                        unit, stock_quantity, minimum_stock, maximum_stock, reorder_level, unit_cost, location, notes, status, admin_approvals) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available', 'Pending)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("sssssddddsss", $material_code, $material_name, $material_type, $supplier,
                                 $unit, $stock_quantity, $minimum_stock, $maximum_stock, $reorder_level,
                                 $unit_cost, $location, $notes);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Raw material added successfully';
                    log_activity($current_user['user_id'], 'Add Material', "Added material: $material_name");
                } else {
                    $_SESSION['error'] = 'Error adding material: ' . $db->error();
                }
                break;
                
            case 'edit':
                $material_id = intval($_POST['material_id']);
                $material_name = $db->escapeString($_POST['material_name']);
                $material_type = $db->escapeString($_POST['material_type']);
                $supplier = $db->escapeString($_POST['supplier']);
                $unit = $db->escapeString($_POST['unit']);
                $minimum_stock = floatval($_POST['minimum_stock']);
                $maximum_stock = floatval($_POST['maximum_stock']);
                $reorder_level = floatval($_POST['reorder_level']);
                $unit_cost = floatval($_POST['unit_cost']);
                $location = $db->escapeString($_POST['location']);
                $notes = $db->escapeString($_POST['notes']);
                $status = $db->escapeString($_POST['status']);
                
                $sql = "UPDATE blockfactory_raw_materials SET 
                        material_name = ?, material_type = ?, supplier = ?, unit = ?, 
                        minimum_stock = ?, maximum_stock = ?, reorder_level = ?, 
                        unit_cost = ?, location = ?, notes = ?, status = ? 
                        WHERE material_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("ssssddddsssi", $material_name, $material_type, $supplier, $unit,
                                 $minimum_stock, $maximum_stock, $reorder_level, $unit_cost,
                                 $location, $notes, $status, $material_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Material updated successfully';
                    log_activity($current_user['user_id'], 'Edit Material', "Edited material ID: $material_id");
                } else {
                    $_SESSION['error'] = 'Error updating material';
                }
                break;
                
            case 'adjust_stock':
                $material_id = intval($_POST['material_id']);
                $adjustment_type = $db->escapeString($_POST['adjustment_type']);
                $quantity = floatval($_POST['quantity']);
                $reason = $db->escapeString($_POST['reason']);
                
                // Get current stock
                $get_sql = "SELECT stock_quantity, material_name FROM blockfactory_raw_materials WHERE material_id = ?";
                $get_stmt = $db->prepare($get_sql);
                $get_stmt->bind_param("i", $material_id);
                $get_stmt->execute();
                $material = $get_stmt->get_result()->fetch_assoc();
                
                $new_quantity = $material['stock_quantity'];
                $adjustment = 0;
                
                switch ($adjustment_type) {
                    case 'add':
                        $adjustment = $quantity;
                        $new_quantity += $quantity;
                        break;
                    case 'remove':
                        $adjustment = -$quantity;
                        $new_quantity -= $quantity;
                        break;
                    case 'set':
                        $adjustment = $quantity - $material['stock_quantity'];
                        $new_quantity = $quantity;
                        break;
                }
                
                if ($new_quantity < 0) {
                    $_SESSION['error'] = 'Stock cannot be negative';
                } else {
                    // Update stock
                    $update_sql = "UPDATE blockfactory_raw_materials SET stock_quantity = ? WHERE material_id = ?";
                    $update_stmt = $db->prepare($update_sql);
                    $update_stmt->bind_param("di", $new_quantity, $material_id);
                    
                    if ($update_stmt->execute()) {
                        // Update status based on stock level
                        $status_sql = "UPDATE blockfactory_raw_materials SET status = 
                                      CASE 
                                          WHEN stock_quantity <= minimum_stock THEN 'Low Stock'
                                          WHEN stock_quantity = 0 THEN 'Out of Stock'
                                          ELSE 'Available'
                                      END
                                      WHERE material_id = ?";
                        $status_stmt = $db->prepare($status_sql);
                        $status_stmt->bind_param("i", $material_id);
                        $status_stmt->execute();
                        
                        $_SESSION['success'] = 'Stock adjusted successfully';
                        log_activity($current_user['user_id'], 'Adjust Stock', 
                                   "Adjusted stock for {$material['material_name']}: {$adjustment} units. Reason: $reason");
                    } else {
                        $_SESSION['error'] = 'Error adjusting stock';
                    }
                }
                break;
                
            case 'delete':
                $material_id = intval($_POST['material_id']);
                
                // Check if material is used in production
                $check = $db->prepare("SELECT COUNT(*) as count FROM blockfactory_production WHERE raw_materials_used LIKE ?");
                $material_name = "%{$material_id}%";
                $check->bind_param("s", $material_name);
                $check->execute();
                $usage_count = $check->get_result()->fetch_assoc()['count'];
                
                if ($usage_count > 0) {
                    $_SESSION['error'] = 'Cannot delete material used in production records';
                } else {
                    $sql = "DELETE FROM blockfactory_raw_materials WHERE material_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("i", $material_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Material deleted successfully';
                        log_activity($current_user['user_id'], 'Delete Material', "Deleted material ID: $material_id");
                    } else {
                        $_SESSION['error'] = 'Error deleting material';
                    }
                }
                break;
        }
        header('Location: raw-materials.php');
        exit();
    }
}

// Get filter parameters
$type_filter = isset($_GET['type']) ? $db->escapeString($_GET['type']) : '';
$status_filter = isset($_GET['status']) ? $db->escapeString($_GET['status']) : '';

// Build query for materials
$query = "SELECT * FROM blockfactory_raw_materials WHERE 1=1";
$params = [];
$types = "";

if (!empty($type_filter)) {
    $query .= " AND material_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}
if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
$query .= " ORDER BY material_name ASC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$materials = $stmt->get_result();

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_materials,
                COALESCE(SUM(stock_quantity * unit_cost), 0) as total_value,
                COUNT(CASE WHEN stock_quantity <= minimum_stock THEN 1 END) as low_stock_count,
                COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock_count,
                COALESCE(SUM(stock_quantity), 0) as total_units
                FROM blockfactory_raw_materials";
$stats = $db->query($stats_query)->fetch_assoc();

$page_title = 'Raw Materials';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raw Materials - Block Factory</title>
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
        
        .stock-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .stock-available { background-color: #28a745; }
        .stock-low { background-color: #ffc107; }
        .stock-out { background-color: #dc3545; }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-Available { background: #d4edda; color: #155724; }
        .status-Low { background: #fff3cd; color: #856404; }
        .status-Out { background: #f8d7da; color: #721c24; }
        
        .type-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e2e3e5;
            color: #383d41;
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
                            <h4 class="mb-1">Raw Materials Inventory</h4>
                            <p class="text-muted mb-0">Manage raw materials and stock levels</p>
                        </div>
                        <div>
                        <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                            <i class="fas fa-bars"></i>
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                            <i class="fas fa-plus-circle me-2"></i>Add New Material
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
                                <p class="text-muted mb-1">Total Materials</p>
                                <h3><?php echo intval($stats['total_materials'] ?? 0); ?></h3>
                                <small>Inventory items</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Total Value</p>
                                <h3><?php echo formatMoney($stats['total_value'] ?? 0); ?></h3>
                                <small>Current stock value</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Low Stock</p>
                                <h3><?php echo intval($stats['low_stock_count'] ?? 0); ?></h3>
                                <small>Need reordering</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Out of Stock</p>
                                <h3><?php echo intval($stats['out_of_stock_count'] ?? 0); ?></h3>
                                <small>Critical items</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Material Type</label>
                                <select class="form-control" name="type" onchange="this.form.submit()">
                                    <option value="">All Types</option>
                                    <option value="Cement" <?php echo $type_filter == 'Cement' ? 'selected' : ''; ?>>Cement</option>
                                    <option value="Sand" <?php echo $type_filter == 'Sand' ? 'selected' : ''; ?>>Sand</option>
                                    <option value="Aggregate" <?php echo $type_filter == 'Aggregate' ? 'selected' : ''; ?>>Aggregate</option>
                                    <option value="Water" <?php echo $type_filter == 'Water' ? 'selected' : ''; ?>>Water</option>
                                    <option value="Additive" <?php echo $type_filter == 'Additive' ? 'selected' : ''; ?>>Additive</option>
                                    <option value="Other" <?php echo $type_filter == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="Available" <?php echo $status_filter == 'Available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="Low Stock" <?php echo $status_filter == 'Low Stock' ? 'selected' : ''; ?>>Low Stock</option>
                                    <option value="Out of Stock" <?php echo $status_filter == 'Out of Stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <a href="raw-materials.php" class="btn btn-secondary">Clear Filters</a>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="button" class="btn btn-success" onclick="exportMaterials()">
                                    <i class="fas fa-download me-2"></i>Export
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Materials Table -->
                    <div class="card">
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
                                            <th>Min Stock</th>
                                            <th>Max Stock</th>
                                            <th>Unit Cost</th>
                                            <th>Total Value</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($materials && $materials->num_rows > 0): ?>
                                            <?php while($material = $materials->fetch_assoc()): 
                                                $stock_class = 'stock-available';
                                                if ($material['status'] == 'Low Stock') {
                                                    $stock_class = 'stock-low';
                                                } elseif ($material['status'] == 'Out of Stock') {
                                                    $stock_class = 'stock-out';
                                                }
                                            ?>
                                            <tr>
                                                <td><strong><?php echo $material['material_code']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($material['material_name']); ?></td>
                                                <td><span class="type-badge"><?php echo $material['material_type']; ?></span></td>
                                                <td><?php echo htmlspecialchars($material['supplier'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="stock-indicator <?php echo $stock_class; ?>"></span>
                                                    <?php echo number_format($material['stock_quantity'], 2); ?>
                                                </td>
                                                <td><?php echo $material['unit']; ?></td>
                                                <td><?php echo number_format($material['minimum_stock'], 2); ?></td>
                                                <td><?php echo number_format($material['maximum_stock'], 2); ?></td>
                                                <td><?php echo formatMoney($material['unit_cost']); ?></td>
                                                <td><?php echo formatMoney($material['stock_quantity'] * $material['unit_cost']); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo str_replace(' ', '', $material['status']); ?>">
                                                        <?php echo $material['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewMaterial(<?php echo $material['material_id']; ?>)" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="editMaterial(<?php echo $material['material_id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <!-- <button class="btn btn-sm btn-success" onclick="adjustStock(<?php echo $material['material_id']; ?>)" title="Adjust Stock">
                                                        <i class="fas fa-balance-scale"></i>
                                                    </button> -->
                                                    <button class="btn btn-sm btn-warning" onclick="orderMaterial(<?php echo $material['material_id']; ?>)" title="Order">
                                                        <i class="fas fa-shopping-cart"></i>
                                                    </button>
                                                    <?php if ($role == 'SuperAdmin'): ?>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteMaterial(<?php echo $material['material_id']; ?>, '<?php echo $material['material_name']; ?>')" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="12" class="text-center py-4">
                                                    <i class="fas fa-boxes fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No raw materials found</p>
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
    
    <!-- Add Material Modal -->
    <div class="modal fade" id="addMaterialModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addProductForm" action="../../api/add-product.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="form" value="product">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Material Code</label>
                            <input id="product_code" type="text" class="form-control" name="product_code" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Material Name</label>
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
    
    <!-- Edit Material Modal -->
    <div class="modal fade" id="editMaterialModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editMaterialForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="material_id" id="edit_material_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Material Code</label>
                                <input type="text" class="form-control" id="edit_material_code" readonly disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Material Name</label>
                                <input type="text" class="form-control" name="material_name" id="edit_material_name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Material Type</label>
                                <select class="form-control" name="material_type" id="edit_material_type" required>
                                    <option value="Cement">Cement</option>
                                    <option value="Sand">Sand</option>
                                    <option value="Aggregate">Aggregate</option>
                                    <option value="Water">Water</option>
                                    <option value="Additive">Additive</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Supplier</label>
                                <input type="text" class="form-control" name="supplier" id="edit_supplier">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Unit</label>
                                <select class="form-control" name="unit" id="edit_unit" required>
                                    <option value="kg">Kilograms (kg)</option>
                                    <option value="bags">Bags</option>
                                    <option value="tons">Tons</option>
                                    <option value="liters">Liters</option>
                                    <option value="pieces">Pieces</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Current Stock</label>
                                <input type="text" class="form-control" id="edit_stock_quantity" readonly disabled>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Minimum Stock</label>
                                <input type="number" step="0.01" class="form-control" name="minimum_stock" id="edit_minimum_stock" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Maximum Stock</label>
                                <input type="number" step="0.01" class="form-control" name="maximum_stock" id="edit_maximum_stock" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Reorder Level</label>
                                <input type="number" step="0.01" class="form-control" name="reorder_level" id="edit_reorder_level" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Unit Cost</label>
                                <input type="number" step="0.01" class="form-control" name="unit_cost" id="edit_unit_cost" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" name="location" id="edit_location">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status" id="edit_status" required>
                                    <option value="Available">Available</option>
                                    <option value="Low Stock">Low Stock</option>
                                    <option value="Out of Stock">Out of Stock</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Material</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Adjust Stock Modal -->
    <div class="modal fade" id="adjustStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adjust Stock Level</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="adjust_stock">
                    <input type="hidden" name="material_id" id="adjust_material_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Material</label>
                            <input type="text" class="form-control" id="adjust_material_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Stock</label>
                            <input type="text" class="form-control" id="adjust_current_stock" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adjustment Type</label>
                            <select class="form-control" name="adjustment_type" required>
                                <option value="add">Add Stock</option>
                                <option value="remove">Remove Stock</option>
                                <option value="set">Set to Exact Value</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" step="0.01" class="form-control" name="quantity" required min="0.01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" name="reason" rows="2" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Adjust Stock</button>
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
                    <p>Are you sure you want to delete <strong id="deleteMaterialName"></strong>?</p>
                    <p class="text-danger">This cannot be undone if material is used in production.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="material_id" id="deleteMaterialId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Material</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="../../assets/js/modules.js"></script>
    <script src="../../assets/js/main.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#materialsTable').DataTable({
                order: [[1, 'asc']],
                pageLength: 25
            });
        });
        
        function viewMaterial(id) {
            window.location.href = '../../api/view-material.php?id=' + id;
        }
        
        function editMaterial(id) {
            window.location.href = '../../api/edit-material.php?id=' + id;
        }
        
        function adjustStock(id) {
            $.get('ajax/get-material.php', {id: id}, function(data) {
                $('#adjust_material_id').val(data.material_id);
                $('#adjust_material_name').val(data.material_name);
                $('#adjust_current_stock').val(data.stock_quantity);
                $('#adjustStockModal').modal('show');
            }, 'json');
        }
        
        function orderMaterial(id) {
            window.location.href = '../procurement/create-po.php?material=' + id + '&source=blockfactory';
            // TODO
        }
        
        function deleteMaterial(id, name) {
            $('#deleteMaterialId').val(id);
            $('#deleteMaterialName').text(name);
            $('#deleteModal').modal('show');
        }
        
        function exportMaterials() {
            var type = '<?php echo $type_filter; ?>';
            var status = '<?php echo $status_filter; ?>';
            window.location.href = 'export-materials.php?type=' + type + '&status=' + status;
        }
    </script>
</body>
</html>