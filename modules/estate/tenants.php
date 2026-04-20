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
if ($current_user['company_type'] != 'Estate' ) {
    $_SESSION['error'] = 'Access denied. Estate department only.';
    header('Location: ../../api/logout.php');
    exit();
}

global $db;

// Handle tenant actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $property_id = intval($_POST['property_id']);
                $tenant_code = $db->escapeString($_POST['tenant_code']);
                $full_name = $db->escapeString($_POST['full_name']);
                $id_number = $db->escapeString($_POST['id_number']);
                $phone = $db->escapeString($_POST['phone']);
                $email = $db->escapeString($_POST['email']);
                $lease_start = $_POST['lease_start'];
                $lease_end = $_POST['lease_end'];
                $monthly_rent = floatval($_POST['monthly_rent']);
                $deposit = floatval($_POST['deposit']);
                $emergency_contact = $db->escapeString($_POST['emergency_contact']);
                $emergency_phone = $db->escapeString($_POST['emergency_phone']);
                
                // Check if property exists and get its details
                $prop_check = $db->prepare("SELECT property_name FROM estate_properties WHERE property_id = ? AND company_id = ?");
                $prop_check->bind_param("ii", $property_id, $company_id);
                $prop_check->execute();
                if ($prop_check->get_result()->num_rows == 0) {
                    $_SESSION['error'] = 'Invalid property selected';
                } else {
                    $sql = "INSERT INTO estate_tenants (property_id, tenant_code, full_name, id_number, phone, email, 
                            lease_start_date, lease_end_date, monthly_rent, deposit_amount, emergency_contact_name, emergency_contact_phone, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("isssssssddss", $property_id, $tenant_code, $full_name, $id_number, $phone, $email, 
                                     $lease_start, $lease_end, $monthly_rent, $deposit, $emergency_contact, $emergency_phone);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Tenant added successfully';
                        log_activity($current_user['user_id'], 'Add Tenant', "Added tenant: $full_name");
                    } else {
                        $_SESSION['error'] = 'Error adding tenant: ' . $db->error();
                    }
                }
                break;
                
            case 'edit':
                $tenant_id = intval($_POST['tenant_id']);
                $full_name = $db->escapeString($_POST['full_name']);
                $phone = $db->escapeString($_POST['phone']);
                $email = $db->escapeString($_POST['email']);
                $lease_end = $_POST['lease_end'];
                $monthly_rent = floatval($_POST['monthly_rent']);
                $status = $db->escapeString($_POST['status']);
                $emergency_contact = $db->escapeString($_POST['emergency_contact']);
                $emergency_phone = $db->escapeString($_POST['emergency_phone']);
                
                $sql = "UPDATE estate_tenants SET full_name=?, phone=?, email=?, lease_end_date=?, 
                        monthly_rent=?, status=?, emergency_contact_name=?, emergency_contact_phone=? 
                        WHERE tenant_id=?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("ssssdsssi", $full_name, $phone, $email, $lease_end, $monthly_rent, 
                                 $status, $emergency_contact, $emergency_phone, $tenant_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Tenant updated successfully';
                    log_activity($current_user['user_id'], 'Edit Tenant', "Edited tenant ID: $tenant_id");
                } else {
                    $_SESSION['error'] = 'Error updating tenant';
                }
                break;
                
            case 'terminate':
                $tenant_id = intval($_POST['tenant_id']);
                
                $sql = "UPDATE estate_tenants SET status='Terminated', lease_end_date=CURDATE() WHERE tenant_id=?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $tenant_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Tenant terminated successfully';
                    log_activity($current_user['user_id'], 'Terminate Tenant', "Terminated tenant ID: $tenant_id");
                } else {
                    $_SESSION['error'] = 'Error terminating tenant';
                }
                break;
        }
        header('Location: tenants.php');
        exit();
    }
}

// Get filter parameters
$property_filter = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
$status_filter = isset($_GET['status']) ? $db->escapeString($_GET['status']) : '';

// Build query
$query = "SELECT t.*, p.property_name, p.property_code 
          FROM estate_tenants t
          JOIN estate_properties p ON t.property_id = p.property_id
          WHERE p.company_id = ?";
$params = [$company_id];
$types = "i";

if ($property_filter > 0) {
    $query .= " AND t.property_id = ?";
    $params[] = $property_filter;
    $types .= "i";
}
if (!empty($status_filter)) {
    $query .= " AND t.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
$query .= " ORDER BY t.created_at DESC";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tenants = $stmt->get_result();

// Get properties for dropdown
$properties = $db->query("SELECT property_id, property_name, property_code FROM estate_properties WHERE company_id = $company_id ORDER BY property_name");

$page_title = 'Tenants';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenants - Estate Management</title>
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
        .tenant-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4361ee, #3f37c9);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-notice { background: #fff3cd; color: #856404; }
        .status-terminated { background: #f8d7da; color: #721c24; }
        .filter-section {
            background: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
                        <h4 class="mb-1">Tenants Management</h4>
                        <p class="text-muted mb-0">Manage all tenants and leases</p>
                    </div>
                <div>
                                            <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                            <i class="fas fa-bars"></i>
                        </button>
                        <!-- <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTenantModal">
                            <i class="fas fa-user-plus me-2"></i>Add New Tenant
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
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Filter by Property</label>
                            <select class="form-control" name="property_id" onchange="this.form.submit()">
                                <option value="">All Properties</option>
                                <?php while($prop = $properties->fetch_assoc()): ?>
                                <option value="<?php echo $prop['property_id']; ?>" <?php echo $property_filter == $prop['property_id'] ? 'selected' : ''; ?>>
                                    <?php echo $prop['property_name']; ?>
                                </option>
                                <?php endwhile; ?>
                                <?php $properties->data_seek(0); // Reset pointer ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Filter by Status</label>
                            <select class="form-control" name="status" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="Active" <?php echo $status_filter == 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Notice" <?php echo $status_filter == 'Notice' ? 'selected' : ''; ?>>Notice</option>
                                <option value="Terminated" <?php echo $status_filter == 'Terminated' ? 'selected' : ''; ?>>Terminated</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <a href="tenants.php" class="btn btn-secondary">Clear Filters</a>
                        </div>
                    </form>
                </div>
                
                <!-- Tenants Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="tenantsTable">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Property</th>
                                        <th>Contact</th>
                                        <th>Lease Period</th>
                                        <th>Monthly Rent</th>
                                        <th>Deposit</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($tenant = $tenants->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="tenant-avatar me-2">
                                                    <?php echo getAvatarLetter($tenant['full_name']); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($tenant['full_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo $tenant['tenant_code']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($tenant['property_name']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo $tenant['property_code']; ?></small>
                                        </td>
                                        <td>
                                            <i class="fas fa-phone me-1"></i><?php echo $tenant['phone']; ?><br>
                                            <i class="fas fa-envelope me-1"></i><?php echo $tenant['email']; ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($tenant['lease_start_date'])); ?> - 
                                            <?php echo date('d/m/Y', strtotime($tenant['lease_end_date'])); ?>
                                            <br>
                                            <?php
                                            $days_left = (strtotime($tenant['lease_end_date']) - time()) / 86400;
                                            if ($days_left > 0 && $days_left < 30) {
                                                echo "<span class='badge bg-warning'>$days_left days left</span>";
                                            }
                                            ?>
                                        </td>
                                        <td><strong><?php echo formatMoney($tenant['monthly_rent']); ?></strong></td>
                                        <td><?php echo formatMoney($tenant['deposit_amount']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($tenant['status']); ?>">
                                                <?php echo $tenant['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewTenant(<?php echo $tenant['tenant_id']; ?>)" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <!-- <button class="btn btn-sm btn-primary" onclick="editTenant(<?php echo $tenant['tenant_id']; ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-success" onclick="recordPayment(<?php echo $tenant['tenant_id']; ?>)" title="Record Payment">
                                                <i class="fas fa-money-bill"></i>
                                            </button>
                                            <?php if ($tenant['status'] == 'Active'): ?>
                                            <button class="btn btn-sm btn-warning" onclick="terminateTenant(<?php echo $tenant['tenant_id']; ?>, '<?php echo $tenant['full_name']; ?>')" title="Terminate">
                                                <i class="fas fa-ban"></i>
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
    
    <!-- Add Tenant Modal -->
    <div class="modal fade" id="addTenantModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Tenant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Property</label>
                                <select class="form-control" name="property_id" required>
                                    <option value="">Select Property</option>
                                    <?php $properties->data_seek(0); while($prop = $properties->fetch_assoc()): ?>
                                    <option value="<?php echo $prop['property_id']; ?>">
                                        <?php echo $prop['property_name']; ?> (<?php echo $prop['property_code']; ?>)
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tenant Code</label>
                                <input type="text" class="form-control" name="tenant_code" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ID Number</label>
                                <input type="text" class="form-control" name="id_number" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lease Start Date</label>
                                <input type="date" class="form-control" name="lease_start" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lease End Date</label>
                                <input type="date" class="form-control" name="lease_end" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Monthly Rent</label>
                                <input type="number" step="0.01" class="form-control" name="monthly_rent" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Deposit Amount</label>
                                <input type="number" step="0.01" class="form-control" name="deposit" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="text" class="form-control" name="emergency_phone">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Tenant</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Tenant Modal -->
    <div class="modal fade" id="editTenantModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Tenant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editTenantForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="tenant_id" id="edit_tenant_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" id="edit_phone" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lease End Date</label>
                                <input type="date" class="form-control" name="lease_end" id="edit_lease_end" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Monthly Rent</label>
                                <input type="number" step="0.01" class="form-control" name="monthly_rent" id="edit_monthly_rent" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status" id="edit_status" required>
                                    <option value="Active">Active</option>
                                    <option value="Notice">Notice</option>
                                    <option value="Terminated">Terminated</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact" id="edit_emergency_contact">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="text" class="form-control" name="emergency_phone" id="edit_emergency_phone">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Tenant</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Terminate Confirmation Modal -->
    <div class="modal fade" id="terminateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Termination</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to terminate lease for <strong id="terminateTenantName"></strong>?</p>
                    <p class="text-warning">This will mark the tenant as terminated and end their lease today.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="terminate">
                        <input type="hidden" name="tenant_id" id="terminateTenantId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Terminate Lease</button>
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
            $('#tenantsTable').DataTable({
                order: [[0, 'asc']]
            });
        });
        
        function viewTenant(id) {
            window.location.href = '../../api/tenant-details.php?id=' + id;
        }
        
        function editTenant(id) {
            $.get('ajax/get-tenant.php', {id: id}, function(data) {
                $('#edit_tenant_id').val(data.tenant_id);
                $('#edit_full_name').val(data.full_name);
                $('#edit_phone').val(data.phone);
                $('#edit_email').val(data.email);
                $('#edit_lease_end').val(data.lease_end_date);
                $('#edit_monthly_rent').val(data.monthly_rent);
                $('#edit_status').val(data.status);
                $('#edit_emergency_contact').val(data.emergency_contact_name);
                $('#edit_emergency_phone').val(data.emergency_contact_phone);
                $('#editTenantModal').modal('show');
            }, 'json');
        }
        
        function recordPayment(id) {
            window.location.href = 'payments.php?tenant_id=' + id;
        }
        
        function terminateTenant(id, name) {
            $('#terminateTenantId').val(id);
            $('#terminateTenantName').text(name);
            $('#terminateModal').modal('show');
        }
    </script>
</body>
</html>