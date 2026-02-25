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

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $product_code = $db->escapeString($_POST['product_code']);
                $product_name = $db->escapeString($_POST['product_name']);
                $product_type = $db->escapeString($_POST['product_type']);
                $dimensions = $db->escapeString($_POST['dimensions']);
                $weight_kg = floatval($_POST['weight_kg']);
                $strength_mpa = $db->escapeString($_POST['strength_mpa']);
                $price_per_unit = floatval($_POST['price_per_unit']);
                $cost_per_unit = floatval($_POST['cost_per_unit']);
                $minimum_stock = intval($_POST['minimum_stock']);
                $maximum_stock = intval($_POST['maximum_stock']);
                $reorder_level = intval($_POST['reorder_level']);
                $description = $db->escapeString($_POST['description']);
                
                $sql = "INSERT INTO blockfactory_products (company_id, product_code, product_name, product_type, 
                        dimensions, weight_kg, strength_mpa, price_per_unit, cost_per_unit, 
                        minimum_stock, maximum_stock, reorder_level, description, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("issssdsddiiiis", $company_id, $product_code, $product_name, $product_type,
                                 $dimensions, $weight_kg, $strength_mpa, $price_per_unit, $cost_per_unit,
                                 $minimum_stock, $maximum_stock, $reorder_level, $description);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Product added successfully';
                    log_activity($current_user['user_id'], 'Add Product', "Added product: $product_name");
                } else {
                    $_SESSION['error'] = 'Error adding product: ' . $db->error();
                }
                break;
                
            case 'edit':
                $product_id = intval($_POST['product_id']);
                $product_name = $db->escapeString($_POST['product_name']);
                $product_type = $db->escapeString($_POST['product_type']);
                $dimensions = $db->escapeString($_POST['dimensions']);
                $weight_kg = floatval($_POST['weight_kg']);
                $strength_mpa = $db->escapeString($_POST['strength_mpa']);
                $price_per_unit = floatval($_POST['price_per_unit']);
                $cost_per_unit = floatval($_POST['cost_per_unit']);
                $minimum_stock = intval($_POST['minimum_stock']);
                $maximum_stock = intval($_POST['maximum_stock']);
                $reorder_level = intval($_POST['reorder_level']);
                $description = $db->escapeString($_POST['description']);
                $status = $db->escapeString($_POST['status']);
                
                $sql = "UPDATE blockfactory_products SET 
                        product_name = ?, product_type = ?, dimensions = ?, weight_kg = ?, 
                        strength_mpa = ?, price_per_unit = ?, cost_per_unit = ?, 
                        minimum_stock = ?, maximum_stock = ?, reorder_level = ?, 
                        description = ?, status = ? 
                        WHERE product_id = ? AND company_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("sssdssdiiissii", $product_name, $product_type, $dimensions, $weight_kg,
                                 $strength_mpa, $price_per_unit, $cost_per_unit, $minimum_stock,
                                 $maximum_stock, $reorder_level, $description, $status, $product_id, $company_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Product updated successfully';
                    log_activity($current_user['user_id'], 'Edit Product', "Edited product ID: $product_id");
                } else {
                    $_SESSION['error'] = 'Error updating product';
                }
                break;
                
            case 'delete':
                $product_id = intval($_POST['product_id']);
                
                // Check if product has production or sales
                $check = $db->prepare("SELECT COUNT(*) as count FROM blockfactory_production WHERE product_id = ?");
                $check->bind_param("i", $product_id);
                $check->execute();
                $prod_count = $check->get_result()->fetch_assoc()['count'];
                
                $check2 = $db->prepare("SELECT COUNT(*) as count FROM blockfactory_sales WHERE product_id = ?");
                $check2->bind_param("i", $product_id);
                $check2->execute();
                $sales_count = $check2->get_result()->fetch_assoc()['count'];
                
                if ($prod_count > 0 || $sales_count > 0) {
                    $_SESSION['error'] = 'Cannot delete product with existing production or sales records';
                } else {
                    $sql = "DELETE FROM blockfactory_products WHERE product_id = ? AND company_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("ii", $product_id, $company_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Product deleted successfully';
                        log_activity($current_user['user_id'], 'Delete Product', "Deleted product ID: $product_id");
                    } else {
                        $_SESSION['error'] = 'Error deleting product';
                    }
                }
                break;
        }
        header('Location: products.php');
        exit();
    }
}

// Get all products
$products_query = "SELECT * FROM blockfactory_products WHERE company_id = ? ORDER BY product_name";
$stmt = $db->prepare($products_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$products = $stmt->get_result();

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_products,
                COALESCE(SUM(current_stock), 0) as total_blocks,
                COALESCE(SUM(current_stock * price_per_unit), 0) as inventory_value,
                COUNT(CASE WHEN current_stock <= reorder_level THEN 1 END) as low_stock_count
                FROM blockfactory_products
                WHERE company_id = ? AND status = 'Active'";
$stmt = $db->prepare($stats_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$page_title = 'Products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Block Factory</title>
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
        
        .stock-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .stock-good { background-color: #28a745; }
        .stock-low { background-color: #ffc107; }
        .stock-critical { background-color: #dc3545; }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
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
                            <h4 class="mb-1">Products Management</h4>
                            <p class="text-muted mb-0">Manage block products and inventory</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus-circle me-2"></i>Add New Product
                        </button>
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
                                <p class="text-muted mb-1">Total Products</p>
                                <h3><?php echo intval($stats['total_products'] ?? 0); ?></h3>
                                <small>Active products</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Total Blocks</p>
                                <h3><?php echo number_format($stats['total_blocks'] ?? 0); ?></h3>
                                <small>In stock</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Inventory Value</p>
                                <h3><?php echo formatMoney($stats['inventory_value'] ?? 0); ?></h3>
                                <small>Current stock value</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Low Stock Items</p>
                                <h3><?php echo intval($stats['low_stock_count'] ?? 0); ?></h3>
                                <small>Need reordering</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Products Table -->
                    <div class="card">
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
                                            <th>Cost</th>
                                            <th>Stock</th>
                                            <th>Value</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($products && $products->num_rows > 0): ?>
                                            <?php while($product = $products->fetch_assoc()): 
                                                $stock_class = 'stock-good';
                                                if ($product['current_stock'] <= $product['reorder_level']) {
                                                    $stock_class = 'stock-critical';
                                                } elseif ($product['current_stock'] <= $product['reorder_level'] * 2) {
                                                    $stock_class = 'stock-low';
                                                }
                                            ?>
                                            <tr>
                                                <td><strong><?php echo $product['product_code']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                <td><?php echo $product['product_type']; ?></td>
                                                <td><?php echo $product['dimensions']; ?></td>
                                                <td><?php echo formatMoney($product['price_per_unit']); ?></td>
                                                <td><?php echo formatMoney($product['cost_per_unit']); ?></td>
                                                <td>
                                                    <span class="stock-indicator <?php echo $stock_class; ?>"></span>
                                                    <?php echo number_format($product['current_stock']); ?>
                                                    <?php if ($product['current_stock'] <= $product['reorder_level']): ?>
                                                        <i class="fas fa-exclamation-triangle text-danger ms-1" title="Low Stock"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatMoney($product['current_stock'] * $product['price_per_unit']); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo strtolower($product['status']); ?>">
                                                        <?php echo $product['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewProduct(<?php echo $product['product_id']; ?>)" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo $product['product_id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="adjustStock(<?php echo $product['product_id']; ?>)" title="Adjust Stock">
                                                        <i class="fas fa-balance-scale"></i>
                                                    </button>
                                                    <?php if ($role == 'SuperAdmin'): ?>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteProduct(<?php echo $product['product_id']; ?>, '<?php echo $product['product_name']; ?>')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="10" class="text-center py-4">
                                                    <i class="fas fa-cubes fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No products found</p>
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
    
    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Code</label>
                                <input type="text" class="form-control" name="product_code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Name</label>
                                <input type="text" class="form-control" name="product_name" required>
                            </div>
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
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" step="0.01" class="form-control" name="weight_kg">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Strength (MPa)</label>
                                <input type="text" class="form-control" name="strength_mpa">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Initial Stock</label>
                                <input type="number" class="form-control" name="current_stock" value="0" readonly disabled>
                                <small class="text-muted">Use production to add stock</small>
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
                                <label class="form-label">Min Stock Level</label>
                                <input type="number" class="form-control" name="minimum_stock" value="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max Stock Level</label>
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
    
    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editProductForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Code</label>
                                <input type="text" class="form-control" id="edit_product_code" readonly disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Name</label>
                                <input type="text" class="form-control" name="product_name" id="edit_product_name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Type</label>
                                <select class="form-control" name="product_type" id="edit_product_type" required>
                                    <option value="Solid Block">Solid Block</option>
                                    <option value="Hollow Block">Hollow Block</option>
                                    <option value="Interlocking Block">Interlocking Block</option>
                                    <option value="Paving Block">Paving Block</option>
                                    <option value="Kerbstones">Kerbstones</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Dimensions</label>
                                <input type="text" class="form-control" name="dimensions" id="edit_dimensions" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" step="0.01" class="form-control" name="weight_kg" id="edit_weight_kg">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Strength (MPa)</label>
                                <input type="text" class="form-control" name="strength_mpa" id="edit_strength_mpa">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Current Stock</label>
                                <input type="text" class="form-control" id="edit_current_stock" readonly disabled>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price per Unit</label>
                                <input type="number" step="0.01" class="form-control" name="price_per_unit" id="edit_price" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cost per Unit</label>
                                <input type="number" step="0.01" class="form-control" name="cost_per_unit" id="edit_cost">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Min Stock</label>
                                <input type="number" class="form-control" name="minimum_stock" id="edit_min_stock">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Max Stock</label>
                                <input type="number" class="form-control" name="maximum_stock" id="edit_max_stock">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Reorder Level</label>
                                <input type="number" class="form-control" name="reorder_level" id="edit_reorder">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status" id="edit_status" required>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Product</button>
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
                <form method="POST" action="adjust-stock.php">
                    <input type="hidden" name="product_id" id="adjust_product_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" class="form-control" id="adjust_product_name" readonly>
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
                            <input type="number" class="form-control" name="quantity" required min="1">
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
                    <p>Are you sure you want to delete <strong id="deleteProductName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone if there are no production/sales records.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="product_id" id="deleteProductId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Product</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#productsTable').DataTable({
                order: [[1, 'asc']],
                pageLength: 25
            });
        });
        
        function viewProduct(id) {
            window.location.href = 'product-details.php?id=' + id;
        }
        
        function editProduct(id) {
            $.get('ajax/get-product.php', {id: id}, function(data) {
                $('#edit_product_id').val(data.product_id);
                $('#edit_product_code').val(data.product_code);
                $('#edit_product_name').val(data.product_name);
                $('#edit_product_type').val(data.product_type);
                $('#edit_dimensions').val(data.dimensions);
                $('#edit_weight_kg').val(data.weight_kg);
                $('#edit_strength_mpa').val(data.strength_mpa);
                $('#edit_current_stock').val(data.current_stock);
                $('#edit_price').val(data.price_per_unit);
                $('#edit_cost').val(data.cost_per_unit);
                $('#edit_min_stock').val(data.minimum_stock);
                $('#edit_max_stock').val(data.maximum_stock);
                $('#edit_reorder').val(data.reorder_level);
                $('#edit_description').val(data.description);
                $('#edit_status').val(data.status);
                $('#editProductModal').modal('show');
            }, 'json');
        }
        
        function adjustStock(id) {
            $.get('ajax/get-product.php', {id: id}, function(data) {
                $('#adjust_product_id').val(data.product_id);
                $('#adjust_product_name').val(data.product_name);
                $('#adjust_current_stock').val(data.current_stock);
                $('#adjustStockModal').modal('show');
            }, 'json');
        }
        
        function deleteProduct(id, name) {
            $('#deleteProductId').val(id);
            $('#deleteProductName').text(name);
            $('#deleteModal').modal('show');
        }
    </script>
</body>
</html>