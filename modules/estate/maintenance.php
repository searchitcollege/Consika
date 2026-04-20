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
if ($current_user['company_type'] != 'Estate' && $role != 'SuperAdmin') {
    $_SESSION['error'] = 'Access denied. Estate department only.';
    header('Location: ../../login.php');
    exit();
}

global $db;

// Handle maintenance actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $property_id = intval($_POST['property_id']);
                $tenant_id = !empty($_POST['tenant_id']) ? intval($_POST['tenant_id']) : null;
                $category = $db->escapeString($_POST['category']);
                $priority = $db->escapeString($_POST['priority']);
                $description = $db->escapeString($_POST['description']);
                
                $sql = "INSERT INTO estate_maintenance (property_id, tenant_id, issue_category, priority, description, status, created_by) 
                        VALUES (?, ?, ?, ?, ?, 'Pending', ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("iisssi", $property_id, $tenant_id, $category, $priority, $description, $current_user['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Maintenance request submitted successfully';
                    log_activity($current_user['user_id'], 'Add Maintenance', "Added maintenance request for property ID: $property_id");
                } else {
                    $_SESSION['error'] = 'Error submitting request: ' . $db->error();
                }
                break;
                
            case 'assign':
                $maintenance_id = intval($_POST['maintenance_id']);
                $assigned_to = $db->escapeString($_POST['assigned_to']);
                $scheduled_date = $_POST['scheduled_date'];
                
                $sql = "UPDATE estate_maintenance SET assigned_to = ?, scheduled_date = ?, status = 'In Progress' WHERE maintenance_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("ssi", $assigned_to, $scheduled_date, $maintenance_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Maintenance request assigned successfully';
                } else {
                    $_SESSION['error'] = 'Error assigning request';
                }
                break;
                
            case 'complete':
                $maintenance_id = intval($_POST['maintenance_id']);
                $actual_cost = floatval($_POST['actual_cost']);
                $completion_notes = $db->escapeString($_POST['completion_notes']);
                
                $sql = "UPDATE estate_maintenance SET actual_cost = ?, completion_notes = ?, status = 'Completed', completion_date = NOW() WHERE maintenance_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("dsi", $actual_cost, $completion_notes, $maintenance_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Maintenance request marked as completed';
                } else {
                    $_SESSION['error'] = 'Error completing request';
                }
                break;
                
            case 'cancel':
                $maintenance_id = intval($_POST['maintenance_id']);
                
                $sql = "UPDATE estate_maintenance SET status = 'Cancelled' WHERE maintenance_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $maintenance_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Maintenance request cancelled';
                } else {
                    $_SESSION['error'] = 'Error cancelling request';
                }
                break;
        }
        header('Location: maintenance.php');
        exit();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $db->escapeString($_GET['status']) : '';
$priority_filter = isset($_GET['priority']) ? $db->escapeString($_GET['priority']) : '';

// Build query for maintenance requests
$query = "SELECT m.*, p.property_name, p.property_code, t.full_name as tenant_name
          FROM estate_maintenance m
          JOIN estate_properties p ON m.property_id = p.property_id
          LEFT JOIN estate_tenants t ON m.tenant_id = t.tenant_id
          WHERE p.company_id = ?";
$params = [$company_id];
$types = "i";

if (!empty($status_filter)) {
    $query .= " AND m.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if (!empty($priority_filter)) {
    $query .= " AND m.priority = ?";
    $params[] = $priority_filter;
    $types .= "s";
}
$query .= " ORDER BY 
            CASE m.priority 
                WHEN 'Emergency' THEN 1
                WHEN 'High' THEN 2
                WHEN 'Medium' THEN 3
                WHEN 'Low' THEN 4
            END, m.request_date DESC";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$maintenance_requests = $stmt->get_result();

// Get statistics - FIXED: Added table aliases to all column references
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN m.status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN m.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN m.status = 'Completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN m.priority = 'Emergency' THEN 1 ELSE 0 END) as emergency,
                COALESCE(AVG(CASE WHEN m.status = 'Completed' THEN DATEDIFF(m.completion_date, m.request_date) ELSE NULL END), 0) as avg_completion_days
                FROM estate_maintenance m
                JOIN estate_properties p ON m.property_id = p.property_id
                WHERE p.company_id = ?";
$stmt = $db->prepare($stats_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get properties for dropdown
$properties = $db->query("SELECT property_id, property_name FROM estate_properties WHERE company_id = $company_id ORDER BY property_name");

$page_title = 'Maintenance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Estate Management</title>
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
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        .stats-card.emergency { border-left-color: #dc3545; }
        .stats-card.pending { border-left-color: #ffc107; }
        .stats-card.progress { border-left-color: #17a2b8;  display: block; height: fit-content; font-size: 1rem;}
        .stats-card.completed { border-left-color: #28a745; }
        
        .priority-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .priority-emergency { background: #dc3545; color: white; }
        .priority-high { background: #fd7e14; color: white; }
        .priority-medium { background: #ffc107; color: black; }
        .priority-low { background: #6c757d; color: white; }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-progress { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .filter-section {
            background: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .maintenance-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #dee2e6;
            transition: transform 0.3s;
        }
        .maintenance-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
                        <h4 class="mb-1">Maintenance Management</h4>
                        <p class="text-muted mb-0">Track and manage maintenance requests</p>
                    </div>
                <div>
                        <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                            <i class="fas fa-bars"></i>
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                            <i class="fas fa-plus-circle me-2"></i>New Request
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
                        <div class="stats-card emergency">
                            <p class="text-muted mb-1">Emergency</p>
                            <h3><?php echo intval($stats['emergency'] ?? 0); ?></h3>
                            <small>Require immediate action</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card pending">
                            <p class="text-muted mb-1">Pending</p>
                            <h3><?php echo intval($stats['pending'] ?? 0); ?></h3>
                            <small>Awaiting assignment</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card progress">
                            <p class="text-muted mb-1">In Progress</p>
                            <h3><?php echo intval($stats['in_progress'] ?? 0); ?></h3>
                            <small>Currently being worked on</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card completed">
                            <p class="text-muted mb-1">Avg Completion</p>
                            <h3><?php echo round($stats['avg_completion_days'] ?? 0); ?> days</h3>
                            <small>Average time to complete</small>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Filter by Status</label>
                            <select class="form-control" name="status" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo $status_filter == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filter by Priority</label>
                            <select class="form-control" name="priority" onchange="this.form.submit()">
                                <option value="">All Priorities</option>
                                <option value="Emergency" <?php echo $priority_filter == 'Emergency' ? 'selected' : ''; ?>>Emergency</option>
                                <option value="High" <?php echo $priority_filter == 'High' ? 'selected' : ''; ?>>High</option>
                                <option value="Medium" <?php echo $priority_filter == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="Low" <?php echo $priority_filter == 'Low' ? 'selected' : ''; ?>>Low</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <a href="maintenance.php" class="btn btn-secondary">Clear Filters</a>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-success" onclick="exportMaintenance()">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Maintenance Requests List -->
                <div class="row">
                    <?php if ($maintenance_requests && $maintenance_requests->num_rows > 0): ?>
                        <?php while($request = $maintenance_requests->fetch_assoc()): ?>
                        <div class="col-md-6">
                            <div class="maintenance-card">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="priority-badge priority-<?php echo strtolower($request['priority']); ?>">
                                            <?php echo $request['priority']; ?>
                                        </span>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '', $request['status'])); ?> ms-2">
                                            <?php echo $request['status']; ?>
                                        </span>
                                    </div>
                                    <small class="text-muted"><?php echo timeAgo($request['request_date']); ?></small>
                                </div>
                                
                                <h6 class="mb-1"><?php echo htmlspecialchars($request['description']); ?></h6>
                                
                                <p class="mb-2">
                                    <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($request['property_name']); ?>
                                    <?php if ($request['tenant_name']): ?>
                                        | <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($request['tenant_name']); ?>
                                    <?php endif; ?>
                                </p>
                                
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <small class="text-muted d-block">
                                            <i class="fas fa-tag me-1"></i> <?php echo $request['issue_category']; ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($request['assigned_to']): ?>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-user-cog me-1"></i> Assigned to: <?php echo $request['assigned_to']; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-end gap-2">
                                    <?php if ($request['status'] == 'Pending'): ?>
                                        <button class="btn btn-sm btn-primary" onclick="assignRequest(<?php echo $request['maintenance_id']; ?>)">
                                            <i class="fas fa-user-check me-1"></i>Assign
                                        </button>
                                    <?php elseif ($request['status'] == 'In Progress'): ?>
                                        <button class="btn btn-sm btn-success" onclick="completeRequest(<?php echo $request['maintenance_id']; ?>)">
                                            <i class="fas fa-check-circle me-1"></i>Complete
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($request['status'] == 'Pending' || $request['status'] == 'In Progress'): ?>
                                        <button class="btn btn-sm btn-danger" onclick="cancelRequest(<?php echo $request['maintenance_id']; ?>)">
                                            <i class="fas fa-times me-1"></i>Cancel
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-sm btn-info" onclick="viewRequest(<?php echo $request['maintenance_id']; ?>)">
                                        <i class="fas fa-eye me-1"></i>View
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-tools fa-4x text-muted mb-3"></i>
                                <h5>No Maintenance Requests</h5>
                                <p class="text-muted">Click "New Request" to create a maintenance request.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Maintenance Modal -->
    <div class="modal fade" id="addMaintenanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Maintenance Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Property</label>
                            <select class="form-control" name="property_id" required>
                                <option value="">Select Property</option>
                                <?php 
                                // Reset properties pointer
                                $properties->data_seek(0);
                                while($prop = $properties->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $prop['property_id']; ?>">
                                    <?php echo htmlspecialchars($prop['property_name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reported by Tenant (Optional)</label>
                            <select class="form-control" name="tenant_id" id="tenantSelect">
                                <option value="">Not reported by tenant</option>
                                <?php 
                                $tenants = $db->query("SELECT tenant_id, full_name FROM estate_tenants WHERE status = 'Active'");
                                while($tenant = $tenants->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $tenant['tenant_id']; ?>">
                                    <?php echo htmlspecialchars($tenant['full_name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-control" name="category" required>
                                <option value="Plumbing">Plumbing</option>
                                <option value="Electrical">Electrical</option>
                                <option value="Structural">Structural</option>
                                <option value="Appliance">Appliance</option>
                                <option value="Pest Control">Pest Control</option>
                                <option value="Cleaning">Cleaning</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-control" name="priority" required>
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Emergency">Emergency</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Assign Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Maintenance Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="assignForm">
                    <input type="hidden" name="action" value="assign">
                    <input type="hidden" name="maintenance_id" id="assign_maintenance_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Assign To</label>
                            <input type="text" class="form-control" name="assigned_to" placeholder="Contractor/Staff Name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Scheduled Date</label>
                            <input type="date" class="form-control" name="scheduled_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Complete Modal -->
    <div class="modal fade" id="completeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Maintenance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="completeForm">
                    <input type="hidden" name="action" value="complete">
                    <input type="hidden" name="maintenance_id" id="complete_maintenance_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Actual Cost</label>
                            <input type="number" step="0.01" class="form-control" name="actual_cost" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Completion Notes</label>
                            <textarea class="form-control" name="completion_notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Mark Completed</button>
                    </div>
                </form>
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
        function assignRequest(id) {
            $('#assign_maintenance_id').val(id);
            $('#assignModal').modal('show');
        }
        
        function completeRequest(id) {
            $('#complete_maintenance_id').val(id);
            $('#completeModal').modal('show');
        }
        
        function cancelRequest(id) {
            if (confirm('Are you sure you want to cancel this maintenance request?')) {
                $('<form method="POST">')
                    .append($('<input>', {type: 'hidden', name: 'action', value: 'cancel'}))
                    .append($('<input>', {type: 'hidden', name: 'maintenance_id', value: id}))
                    .appendTo('body').submit();
            }
        }
        
        function viewRequest(id) {
            window.location.href = 'maintenance-details.php?id=' + id;
        }
        
        function exportMaintenance() {
            var status = '<?php echo $status_filter; ?>';
            var priority = '<?php echo $priority_filter; ?>';
            window.location.href = 'export-maintenance.php?status=' + status + '&priority=' + priority;
        }
    </script>
</body>
</html>