<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db_connection.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

$session->requireLogin();

$current_user = $session->getCurrentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

// Ensure only Estate users can access
if ($current_user['company_type'] != 'Estate') {
    $_SESSION['error'] = 'Access denied. Estate department only.';
    header('Location: ../../api/logout.php');
    exit();
}

global $db;

// Handle property actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $property_code = $db->escapeString($_POST['property_code']);
                $property_name = $db->escapeString($_POST['property_name']);
                $property_type = $db->escapeString($_POST['property_type']);
                $address = $db->escapeString($_POST['address']);
                $city = $db->escapeString($_POST['city']);
                $total_area = floatval($_POST['total_area']);
                $units = intval($_POST['units']);
                $purchase_price = floatval($_POST['purchase_price']);
                $current_value = floatval($_POST['current_value']);
                $status = $db->escapeString($_POST['status']);
                $description = $db->escapeString($_POST['description']);
                
                $sql = "INSERT INTO estate_properties (company_id, property_code, property_name, property_type, 
                        address, city, total_area, units, purchase_price, current_value, status, description, created_by) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("issssssiddssi", $company_id, $property_code, $property_name, $property_type, 
                                 $address, $city, $total_area, $units, $purchase_price, $current_value, $status, $description, $current_user['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Property added successfully';
                    log_activity($current_user['user_id'], 'Add Property', "Added property: $property_name");
                } else {
                    $_SESSION['error'] = 'Error adding property: ' . $db->error();
                }
                break;
                
            case 'edit':
                $property_id = intval($_POST['property_id']);
                $property_name = $db->escapeString($_POST['property_name']);
                $property_type = $db->escapeString($_POST['property_type']);
                $address = $db->escapeString($_POST['address']);
                $city = $db->escapeString($_POST['city']);
                $total_area = floatval($_POST['total_area']);
                $units = intval($_POST['units']);
                $current_value = floatval($_POST['current_value']);
                $status = $db->escapeString($_POST['status']);
                $description = $db->escapeString($_POST['description']);
                
                $sql = "UPDATE estate_properties SET property_name=?, property_type=?, address=?, city=?, 
                        total_area=?, units=?, current_value=?, status=?, description=? WHERE property_id=? AND company_id=?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("sssssiddsii", $property_name, $property_type, $address, $city, 
                                 $total_area, $units, $current_value, $status, $description, $property_id, $company_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Property updated successfully';
                    log_activity($current_user['user_id'], 'Edit Property', "Edited property ID: $property_id");
                } else {
                    $_SESSION['error'] = 'Error updating property';
                }
                break;
                
            case 'delete':
                $property_id = intval($_POST['property_id']);
                
                // Check if property has tenants
                $check = $db->prepare("SELECT COUNT(*) as count FROM estate_tenants WHERE property_id = ? AND status = 'Active'");
                $check->bind_param("i", $property_id);
                $check->execute();
                $result = $check->get_result();
                $tenant_count = $result->fetch_assoc()['count'];
                
                if ($tenant_count > 0) {
                    $_SESSION['error'] = 'Cannot delete property with active tenants';
                } else {
                    $sql = "DELETE FROM estate_properties WHERE property_id = ? AND company_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("ii", $property_id, $company_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Property deleted successfully';
                        log_activity($current_user['user_id'], 'Delete Property', "Deleted property ID: $property_id");
                    } else {
                        $_SESSION['error'] = 'Error deleting property';
                    }
                }
                break;
        }
        header('Location: properties.php');
        exit();
    }
}

// Get all properties
$properties_query = "SELECT p.*, 
                    (SELECT COUNT(*) FROM estate_tenants WHERE property_id = p.property_id AND status = 'Active') as active_tenants,
                    (SELECT COALESCE(SUM(amount), 0) FROM estate_payments WHERE property_id = p.property_id) as total_revenue
                    FROM estate_properties p 
                    WHERE p.company_id = ? 
                    ORDER BY p.created_at DESC";
$stmt = $db->prepare($properties_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$properties = $stmt->get_result();

$page_title = 'Properties';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Properties - Estate Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .property-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #4361ee;
            transition: transform 0.3s;
        }
        .property-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-available { background: #d4edda; color: #155724; }
        .status-occupied { background: #cce5ff; color: #004085; }
        .status-maintenance { background: #fff3cd; color: #856404; }
        .btn-action {
            margin: 0 2px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0">
                <?php include dirname(__DIR__, 2) . '/includes/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">Properties Management</h4>
                        <p class="text-muted mb-0">Manage all estate properties</p>
                    </div>
                    <div>
                        <button id="sidebarToggle" class="btn btn-dark d-md-none m-2 align-self-end">
                            <i class="fas fa-bars"></i>
                        </button>
                        <!-- <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPropertyModal">
                            <i class="fas fa-plus-circle me-2"></i>Add New Property
                        </button> -->
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
                
                <!-- Properties Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="propertiesTable">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Property Name</th>
                                        <th>Type</th>
                                        <th>Location</th>
                                        <th>Units</th>
                                        <th>Tenants</th>
                                        <th>Value</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($prop = $properties->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo $prop['property_code']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($prop['property_name']); ?></td>
                                        <td><?php echo $prop['property_type']; ?></td>
                                        <td><?php echo htmlspecialchars($prop['city'] ?: 'N/A'); ?></td>
                                        <td><?php echo $prop['units']; ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $prop['active_tenants']; ?> active</span>
                                        </td>
                                        <td><?php echo formatMoney($prop['current_value']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $prop['status'])); ?>">
                                                <?php echo $prop['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info btn-action" onclick="viewProperty(<?php echo $prop['property_id']; ?>)" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <!-- <button class="btn btn-sm btn-primary btn-action" onclick="editProperty(<?php echo $prop['property_id']; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button> -->
                                            <button class="btn btn-sm btn-success btn-action" onclick="viewTenants(<?php echo $prop['property_id']; ?>)" title="Tenants">
                                                <i class="fas fa-users"></i>
                                            </button>
                                            <?php if ($prop['active_tenants'] == 0): ?>
                                            <!-- <button class="btn btn-sm btn-danger btn-action" onclick="deleteProperty(<?php echo $prop['property_id']; ?>, '<?php echo $prop['property_name']; ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button> -->
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
    
    <!-- Add Property Modal -->
    <div class="modal fade" id="addPropertyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Property</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Property Code</label>
                                <input type="text" class="form-control" name="property_code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Property Name</label>
                                <input type="text" class="form-control" name="property_name" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Property Type</label>
                                <select class="form-control" name="property_type" required>
                                    <option value="Residential">Residential</option>
                                    <option value="Commercial">Commercial</option>
                                    <option value="Land">Land</option>
                                    <option value="Industrial">Industrial</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status" required>
                                    <option value="Available">Available</option>
                                    <option value="Occupied">Occupied</option>
                                    <option value="Under Maintenance">Under Maintenance</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Total Area (sqm)</label>
                                <input type="number" step="0.01" class="form-control" name="total_area">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Units</label>
                                <input type="number" class="form-control" name="units" value="1">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Price</label>
                                <input type="number" step="0.01" class="form-control" name="purchase_price">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Current Value</label>
                                <input type="number" step="0.01" class="form-control" name="current_value">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Property</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Property Modal -->
    <div class="modal fade" id="editPropertyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Property</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editPropertyForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="property_id" id="edit_property_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Property Name</label>
                                <input type="text" class="form-control" name="property_name" id="edit_property_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Property Type</label>
                                <select class="form-control" name="property_type" id="edit_property_type" required>
                                    <option value="Residential">Residential</option>
                                    <option value="Commercial">Commercial</option>
                                    <option value="Land">Land</option>
                                    <option value="Industrial">Industrial</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status" id="edit_status" required>
                                    <option value="Available">Available</option>
                                    <option value="Occupied">Occupied</option>
                                    <option value="Under Maintenance">Under Maintenance</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" id="edit_city">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" id="edit_address" rows="2" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Total Area (sqm)</label>
                                <input type="number" step="0.01" class="form-control" name="total_area" id="edit_total_area">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Units</label>
                                <input type="number" class="form-control" name="units" id="edit_units">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Current Value</label>
                                <input type="number" step="0.01" class="form-control" name="current_value" id="edit_current_value">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Property</button>
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
                    <p>Are you sure you want to delete <strong id="deletePropertyName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="property_id" id="deletePropertyId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Property</button>
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
            $('#propertiesTable').DataTable({
                order: [[0, 'asc']]
            });
        });
        
        function viewProperty(id) {
            window.location.href = '../../api/property-details.php?id=' + id;
        }
        
        function editProperty(id) {
            $.get('ajax/get-property.php', {id: id}, function(data) {
                $('#edit_property_id').val(data.property_id);
                $('#edit_property_name').val(data.property_name);
                $('#edit_property_type').val(data.property_type);
                $('#edit_status').val(data.status);
                $('#edit_address').val(data.address);
                $('#edit_city').val(data.city);
                $('#edit_total_area').val(data.total_area);
                $('#edit_units').val(data.units);
                $('#edit_current_value').val(data.current_value);
                $('#edit_description').val(data.description);
                $('#editPropertyModal').modal('show');
            }, 'json');
        }
        
        function viewTenants(id) {
            window.location.href = 'tenants.php?property_id=' + id;
        }
        
        function deleteProperty(id, name) {
            $('#deletePropertyId').val(id);
            $('#deletePropertyName').text(name);
            $('#deleteModal').modal('show');
        }
    </script>
</body>
</html>