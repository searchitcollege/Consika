<?php
require_once '../../includes/session.php';
$session->requireLogin();

// Check permission
if (!hasPermission('estate', 'view')) {
    $_SESSION['error'] = 'You do not have permission to access this module.';
    header('Location: ../../admin/dashboard.php');
    exit();
}

$current_user = currentUser();
$company_id = $session->getCompanyId();
$role = $_SESSION['role'] ?? '';


if ($role !== 'SuperAdmin') {
    // $_SESSION['error'] = 'You do not have permission to create projects.';
    header('Location: ./dashboard.php');
    exit();
}

global $db;

if (empty($company_id) || $company_id == null) {
    $result = $db->query("SELECT company_id FROM companies WHERE company_type = 'Estate' LIMIT 1");
    $row = $result->fetch_assoc();
    $company_id = (int)($row['company_id'] ?? 0);
}

if (empty($company_id)) {
    $_SESSION['error'] = 'Estate company not found.';
    header('Location: ../../admin/dashboard.php');
    exit();
}

// Get properties
$properties_query = "SELECT p.*, 
                    (SELECT COUNT(*) FROM estate_tenants WHERE property_id = p.property_id AND status = 'Active') as active_tenants,
                    (SELECT COALESCE(SUM(amount), 0) FROM estate_payments WHERE property_id = p.property_id AND MONTH(payment_date) = MONTH(CURRENT_DATE())) as monthly_revenue
                    FROM estate_properties p 
                    WHERE p.company_id = ? 
                    ORDER BY p.created_at DESC";
$stmt = $db->prepare($properties_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$properties = $stmt->get_result();

// Get recent tenants
$tenants_query = "SELECT t.*, p.property_name 
                 FROM estate_tenants t
                 JOIN estate_properties p ON t.property_id = p.property_id
                 WHERE p.company_id = ?
                 ORDER BY t.created_at DESC LIMIT 10";
$stmt = $db->prepare($tenants_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$recent_tenants = $stmt->get_result();

// Get pending maintenance
$maintenance_query = "SELECT m.*, p.property_name, t.full_name as tenant_name
                     FROM estate_maintenance m
                     LEFT JOIN estate_properties p ON m.property_id = p.property_id
                     LEFT JOIN estate_tenants t ON m.tenant_id = t.tenant_id
                     WHERE p.company_id = ? AND m.status = 'Pending'
                     ORDER BY m.priority DESC, m.request_date ASC";
$stmt = $db->prepare($maintenance_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$pending_maintenance = $stmt->get_result();

// Get upcoming rent payments
$upcoming_rent_query = "SELECT t.*, p.property_name, 
                       DATEDIFF(t.lease_end_date, CURRENT_DATE()) as days_remaining
                       FROM estate_tenants t
                       JOIN estate_properties p ON t.property_id = p.property_id
                       WHERE p.company_id = ? AND t.status = 'Active'
                       ORDER BY days_remaining ASC LIMIT 10";
$stmt = $db->prepare($upcoming_rent_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$upcoming_rent = $stmt->get_result();

// Get monthly statistics
$stats_query = "SELECT 
                COUNT(DISTINCT p.property_id) as total_properties,
                COUNT(DISTINCT t.tenant_id) as total_tenants,
                COALESCE(SUM(CASE WHEN t.status = 'Active' THEN t.monthly_rent ELSE 0 END), 0) as potential_monthly_rent,
                COALESCE(SUM(py.amount), 0) as collected_this_month
                FROM estate_properties p
                LEFT JOIN estate_tenants t ON p.property_id = t.property_id AND t.status = 'Active'
                LEFT JOIN estate_payments py ON p.property_id = py.property_id AND MONTH(py.payment_date) = MONTH(CURRENT_DATE())
                WHERE p.company_id = ?";
$stmt = $db->prepare($stats_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts Management - <?php echo APP_NAME; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <!-- Custom CSS -->
    <link href="../../assets/css/style.css" rel="stylesheet">

    <style>
        .module-header {
            background: linear-gradient(135deg, #4361ee, #3f37c9);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, #4361ee, #3f37c9);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin: 0;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .property-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            margin-bottom: 20px;
            cursor: pointer;
        }

        .property-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .property-image {
            height: 200px;
            background-size: cover;
            background-position: center;
            position: relative;
        }

        .property-status {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .property-details {
            padding: 20px;
        }

        .property-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .property-address {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .property-meta {
            display: flex;
            gap: 20px;
            font-size: 13px;
            color: #666;
        }

        .quick-action-btn {
            background: white;
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .quick-action-btn:hover {
            border-color: #4361ee;
            background: #f8f9fa;
        }

        .quick-action-btn i {
            font-size: 32px;
            color: #4361ee;
            margin-bottom: 10px;
        }

        .quick-action-btn span {
            display: block;
            font-weight: 600;
            color: #333;
        }

        .priority-high {
            border-left: 4px solid #dc3545;
        }

        .priority-medium {
            border-left: 4px solid #ffc107;
        }

        .priority-low {
            border-left: 4px solid #28a745;
        }

        .nav-tabs .nav-link {
            color: #666;
            font-weight: 500;
            border: none;
            padding: 10px 20px;
        }

        .nav-tabs .nav-link.active {
            color: #4361ee;
            background: none;
            border-bottom: 3px solid #4361ee;
        }
    </style>
</head>

<body class="module-estate">
    <div class="wrapper">
        <!-- Include sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <?php include '../../includes/top-nav.php'; ?>

            <!-- Module Header -->
            <div class="module-header">
                <!-- <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                    <i class="fas fa-bars"></i>
                </button> -->
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="h3 mb-2">Accounts Management</h1>
                        <p class="mb-0 opacity-75">Manage properties, tenants, and maintenance requests</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-light " data-bs-toggle="modal" data-bs-target="#addPropertyModal">
                            <i class="fas fa-plus-circle me-2"></i>Add Property
                        </button>
                        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addTenantModal">
                            <i class="fas fa-user-plus me-2"></i>Add Tenant
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['total_properties']; ?></h3>
                        <p class="stat-label">Total Properties</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4cc9f0, #4895ef);">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['total_tenants']; ?></h3>
                        <p class="stat-label">Active Tenants</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f8961e, #f3722c);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h3 class="stat-value"><?php echo format_money($stats['potential_monthly_rent']); ?></h3>
                        <p class="stat-label">Potential Monthly Rent</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f72585, #b5179e);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="stat-value"><?php echo format_money($stats['collected_this_month']); ?></h3>
                        <p class="stat-label">Collected This Month</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="estateTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="properties-tab" data-bs-toggle="tab" data-bs-target="#properties" type="button" role="tab">
                        <i class="fas fa-building me-2"></i>Properties
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tenants-tab" data-bs-toggle="tab" data-bs-target="#tenants" type="button" role="tab">
                        <i class="fas fa-users me-2"></i>Tenants
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab">
                        <i class="fas fa-credit-card me-2"></i>Payments
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab">
                        <i class="fas fa-tools me-2"></i>Maintenance
                        <?php if ($pending_maintenance->num_rows > 0): ?>
                            <span class="badge bg-danger ms-2"><?php echo $pending_maintenance->num_rows; ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                        <i class="fas fa-chart-bar me-2"></i>Reports
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="estateTabContent">
                <!-- Properties Tab -->
                <div class="tab-pane fade show active" id="properties" role="tabpanel">
                    <div class="row">
                        <?php while ($property = $properties->fetch_assoc()): ?>
                            <div class="col-md-4">
                                <div class="property-card" onclick="window.location.href='../../api/property-details.php?id=<?php echo $property['property_id']; ?>'">
                                    <div class="property-image" style="background-image: url('<?php echo $property['images'] ? '../../uploads/estate/' . $property['images'] : '../../assets/images/default-property.jpg'; ?>')">
                                        <span class="property-status badge bg-<?php
                                                                                echo $property['status'] == 'Available' ? 'success' : ($property['status'] == 'Occupied' ? 'primary' : ($property['status'] == 'Under Maintenance' ? 'warning' : 'secondary'));
                                                                                ?>">
                                            <?php echo $property['status']; ?>
                                        </span>
                                    </div>
                                    <div class="property-details">
                                        <h5 class="property-name"><?php echo htmlspecialchars($property['property_name']); ?></h5>
                                        <p class="property-address">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($property['address']); ?>
                                        </p>
                                        <div class="property-meta">
                                            <span><i class="fas fa-door-open me-1"></i> <?php echo $property['units']; ?> Units</span>
                                            <span><i class="fas fa-users me-1"></i> <?php echo $property['active_tenants']; ?> Tenants</span>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-primary fw-bold"><?php echo format_money($property['monthly_revenue']); ?>/this Month</span>
                                            <div>
                                                <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); window.location.href='../../api/edit-property.php?id=<?php echo $property['property_id']; ?>'">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>

                        <!-- Quick Add Property Card -->
                        <div class="col-md-4">
                            <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#addPropertyModal">
                                <i class="fas fa-plus-circle"></i>
                                <span>Add New Property</span>
                                <small class="text-muted">Click to add a new property</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tenants Tab -->
                <div class="tab-pane fade" id="tenants" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Current Tenants</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTenantModal">
                                <i class="fas fa-plus-circle me-2"></i>Add Tenant
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="tenantsTable">
                                    <thead>
                                        <tr>
                                            <th>Tenant Name</th>
                                            <th>Property</th>
                                            <th>Phone</th>
                                            <th>Lease Period</th>
                                            <th>Monthly Rent</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $tenants_query = "SELECT t.*, p.property_name 
                                                         FROM estate_tenants t
                                                         JOIN estate_properties p ON t.property_id = p.property_id
                                                         ORDER BY t.created_at DESC";
                                        $stmt = $db->prepare($tenants_query);
                                        $stmt->execute();
                                        $all_tenants = $stmt->get_result();
                                        while ($tenant = $all_tenants->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-circle bg-primary text-white me-2">
                                                            <?php echo get_avatar_letter($tenant['full_name']); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($tenant['full_name']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo $tenant['tenant_code']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($tenant['property_name']); ?></td>
                                                <td><?php echo $tenant['phone']; ?></td>
                                                <td>
                                                    <?php echo format_date($tenant['lease_start_date']); ?> -
                                                    <?php echo format_date($tenant['lease_end_date']); ?>
                                                </td>
                                                <td><?php echo format_money($tenant['monthly_rent']); ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = $tenant['status'] == 'Active' ? 'success' : ($tenant['status'] == 'Notice' ? 'warning' : 'secondary');
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                        <?php echo $tenant['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="window.location.href='../../api/tenant-details.php?id=<?php echo $tenant['tenant_id']; ?>'">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="window.location.href='../../api/edit-tenant.php?id=<?php echo $tenant['tenant_id']; ?>'">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="recordPayment(<?php echo $tenant['tenant_id']; ?>, '<?php echo $tenant['full_name']; ?>', <?php echo $tenant['monthly_rent']; ?>)">
                                                        <i class="fas fa-money-bill"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payments Tab -->
                <div class="tab-pane fade" id="payments" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Payment History</h5>
                            <button class="btn btn-primary btn-sm" onclick="recordPayment()">
                                <i class="fas fa-plus-circle me-2"></i>Record Payment
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="paymentsTable">
                                    <thead>
                                        <tr>
                                            <th>Receipt No.</th>
                                            <th>Tenant</th>
                                            <th>Property</th>
                                            <th>Payment Date</th>
                                            <th>Period</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $payments_query = "SELECT p.*, t.full_name as tenant_name, pr.property_name 
                                                          FROM estate_payments p
                                                          JOIN estate_tenants t ON p.tenant_id = t.tenant_id
                                                          JOIN estate_properties pr ON p.property_id = pr.property_id
                                                          ORDER BY p.payment_date DESC LIMIT 50";
                                        $stmt = $db->prepare($payments_query);
                                        $stmt->execute();
                                        $payments = $stmt->get_result();
                                        while ($payment = $payments->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><strong><?php echo $payment['receipt_number']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($payment['tenant_name']); ?></td>
                                                <td><?php echo htmlspecialchars($payment['property_name']); ?></td>
                                                <td><?php echo format_date($payment['payment_date']); ?></td>
                                                <td>
                                                    <?php echo format_date($payment['payment_period_start'], 'd/m'); ?> -
                                                    <?php echo format_date($payment['payment_period_end'], 'd/m/y'); ?>
                                                </td>
                                                <td><strong><?php echo format_money($payment['amount']); ?></strong></td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $payment['payment_method']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo $payment['status']; ?></span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="window.open('../../api/receipt.php?id=<?php echo $payment['payment_id']; ?>', '_blank')">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Tab -->
                <div class="tab-pane fade" id="maintenance" role="tabpanel">
                    <div class="row">
                        <!-- Priority Maintenance -->
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Maintenance Requests</h5>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                                        <i class="fas fa-plus-circle me-2"></i>New Request
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $maintenance_query = "SELECT m.*, p.property_name, t.full_name as tenant_name
                                                         FROM estate_maintenance m
                                                         LEFT JOIN estate_properties p ON m.property_id = p.property_id
                                                         LEFT JOIN estate_tenants t ON m.tenant_id = t.tenant_id
                                                         ORDER BY 
                                                         CASE m.priority 
                                                             WHEN 'Emergency' THEN 1
                                                             WHEN 'High' THEN 2
                                                             WHEN 'Medium' THEN 3
                                                             WHEN 'Low' THEN 4
                                                         END, m.request_date DESC";
                                    $stmt = $db->prepare($maintenance_query);
                                    $stmt->execute();
                                    $maintenance_requests = $stmt->get_result();

                                    if ($maintenance_requests->num_rows > 0):
                                        while ($request = $maintenance_requests->fetch_assoc()):
                                            $priority_class = $request['priority'] == 'Emergency' ? 'danger' : ($request['priority'] == 'High' ? 'warning' : ($request['priority'] == 'Medium' ? 'info' : 'secondary'));
                                    ?>
                                            <div class="maintenance-item <?php echo 'priority-' . strtolower($request['priority']); ?> p-3 mb-2 border rounded">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <span class="badge bg-<?php echo $priority_class; ?> mb-2"><?php echo $request['priority']; ?></span>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($request['description']); ?></h6>
                                                        <p class="mb-2 text-muted small">
                                                            <i class="fas fa-building me-1"></i> <?php echo $request['property_name']; ?>
                                                            <?php if ($request['tenant_name']): ?>
                                                                | <i class="fas fa-user me-1"></i> <?php echo $request['tenant_name']; ?>
                                                            <?php endif; ?>
                                                            | <i class="fas fa-clock me-1"></i> <?php echo timeAgo($request['request_date']); ?>
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <?php if ($request['status'] == 'Pending'): ?>
                                                            <button class="btn btn-sm btn-success" onclick="assignMaintenance(<?php echo $request['maintenance_id']; ?>)">
                                                                <i class="fas fa-check"></i> Assign
                                                            </button>
                                                        <?php elseif ($request['status'] == 'In Progress'): ?>
                                                            <button class="btn btn-sm btn-primary" onclick="completeMaintenance(<?php echo $request['maintenance_id']; ?>)">
                                                                <i class="fas fa-check-circle"></i> Complete
                                                            </button>
                                                        <?php endif; ?>
                                                        <span class="badge bg-<?php
                                                                                echo $request['status'] == 'Completed' ? 'success' : ($request['status'] == 'In Progress' ? 'primary' : ($request['status'] == 'Pending' ? 'warning' : 'secondary'));
                                                                                ?> ms-2">
                                                            <?php echo $request['status']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php
                                        endwhile;
                                    else:
                                        ?>
                                        <p class="text-muted text-center py-4">No maintenance requests found</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Maintenance Stats -->
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Quick Stats</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $stats_query = "SELECT 
                                                    COUNT(*) as total,
                                                    SUM(CASE WHEN m.priority = 'Emergency' THEN 1 ELSE 0 END) as emergency,
                                                    SUM(CASE WHEN m.status = 'Pending' THEN 1 ELSE 0 END) as pending,
                                                    SUM(CASE WHEN m.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
                                                    AVG(CASE WHEN m.status = 'Completed' THEN DATEDIFF(m.completion_date, m.request_date) ELSE NULL END) as avg_completion_days
                                                    FROM estate_maintenance m
                                                    JOIN estate_properties p ON m.property_id = p.property_id
                                                    WHERE p.company_id = ?";
                                    $stmt = $db->prepare($stats_query);
                                    $stmt->bind_param("i", $company_id);
                                    $stmt->execute();
                                    $maintenance_stats = $stmt->get_result()->fetch_assoc();
                                    ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Emergency</span>
                                            <span class="badge bg-danger"><?php echo $maintenance_stats['emergency']; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Pending</span>
                                            <span class="badge bg-warning"><?php echo $maintenance_stats['pending']; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>In Progress</span>
                                            <span class="badge bg-primary"><?php echo $maintenance_stats['in_progress']; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>Avg. Completion</span>
                                            <span class="badge bg-info"><?php echo round($maintenance_stats['avg_completion_days'] ?? 0, 1); ?> days</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Upcoming Lease Expiries</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $expiring_query = "SELECT t.full_name, p.property_name, t.lease_end_date,
                                                       DATEDIFF(t.lease_end_date, CURRENT_DATE()) as days_left
                                                       FROM estate_tenants t
                                                       JOIN estate_properties p ON t.property_id = p.property_id
                                                       WHERE p.company_id = ? 
                                                       AND t.status = 'Active'
                                                       AND t.lease_end_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)
                                                       ORDER BY t.lease_end_date ASC
                                                       LIMIT 5";
                                    $stmt = $db->prepare($expiring_query);
                                    $stmt->bind_param("i", $company_id);
                                    $stmt->execute();
                                    $expiring = $stmt->get_result();

                                    if ($expiring->num_rows > 0):
                                        while ($lease = $expiring->fetch_assoc()):
                                    ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($lease['full_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo $lease['property_name']; ?></small>
                                                </div>
                                                <span class="badge bg-<?php echo $lease['days_left'] <= 7 ? 'danger' : 'warning'; ?>">
                                                    <?php echo $lease['days_left']; ?> days
                                                </span>
                                            </div>
                                        <?php
                                        endwhile;
                                    else:
                                        ?>
                                        <p class="text-muted text-center">No leases expiring soon</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports Tab -->
                <div class="tab-pane fade" id="reports" role="tabpanel">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                    <h6>Rent Collection Report</h6>
                                    <p class="text-muted small">Monthly rent collection summary</p>
                                    <button class="btn btn-sm btn-outline-primary" onclick="generateReport('rent-collection')">
                                        Generate
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-excel fa-3x text-success mb-3"></i>
                                    <h6>Tenant List</h6>
                                    <p class="text-muted small">Complete tenant directory</p>
                                    <button class="btn btn-sm btn-outline-primary" onclick="exportTenants()">
                                        Export
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-chart-pie fa-3x text-info mb-3"></i>
                                    <h6>Occupancy Analysis</h6>
                                    <p class="text-muted small">Property occupancy rates</p>
                                    <button class="btn btn-sm btn-outline-primary" onclick="generateReport('occupancy')">
                                        View
                                    </button>
                                </div>
                            </div>
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
                <form action="../../api/add-property.php" method="POST" enctype="multipart/form-data">
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
                                <select class="form-control" name="status">
                                    <option value="Available">Available</option>
                                    <option value="Under Maintenance">Under Maintenance</option>
                                    <option value="Under Construction">Under Construction</option>
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
                                <label class="form-label">Number of Units</label>
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

                        <div class="mb-3">
                            <label class="form-label">Property Images</label>
                            <input type="file" class="form-control" name="images[]" multiple accept="image/*">
                            <small class="text-muted">Upload multiple images (max 5MB each)</small>
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

    <!-- Add Tenant Modal -->
    <div class="modal fade" id="addTenantModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Tenant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../api/add-tenant.php" method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Property</label>
                                <select class="form-control select2" name="property_id" required>
                                    <option value="">Select Property</option>
                                    <?php
                                    $prop_query = "SELECT property_id, property_name, address FROM estate_properties WHERE status = 'Available'";
                                    $stmt = $db->prepare($prop_query);
                                    $stmt->execute();
                                    $available_props = $stmt->get_result();
                                    while ($prop = $available_props->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $prop['property_id']; ?>">
                                            <?php echo $prop['property_name']; ?> - <?php echo $prop['address']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unit Number</label>
                                <input type="text" class="form-control" name="unit_number">
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
                                <input type="date" class="form-control" name="lease_start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Lease End Date</label>
                                <input type="date" class="form-control" name="lease_end_date" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Monthly Rent</label>
                                <input type="number" step="0.01" class="form-control" name="monthly_rent" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Deposit Amount</label>
                                <input type="number" step="0.01" class="form-control" name="deposit_amount" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="text" class="form-control" name="emergency_contact_phone">
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

    <!-- Add Maintenance Modal -->
    <div class="modal fade" id="addMaintenanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Maintenance Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../api/add-maintenance.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Property</label>
                            <select class="form-control" name="property_id" required>
                                <option value="">Select Property</option>
                                <?php
                                $prop_query = "SELECT property_id, property_name FROM estate_properties";
                                $stmt = $db->prepare($prop_query);
                                $stmt->execute();
                                $props = $stmt->get_result();
                                while ($prop = $props->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $prop['property_id']; ?>">
                                        <?php echo $prop['property_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tenant (Optional)</label>
                            <select class="form-control" name="tenant_id">
                                <option value="">Not Reported by Tenant</option>
                                <?php
                                $tenant_query = "SELECT tenant_id, full_name FROM estate_tenants WHERE status = 'Active'";
                                $tenants = $db->query($tenant_query);
                                while ($tenant = $tenants->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $tenant['tenant_id']; ?>">
                                        <?php echo $tenant['full_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Request Date</label>
                            <input type="date" class="form-control" name="request_date"
                                value="<?php echo date('Y-m-d'); ?>" required>
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

    <!-- Record Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <form action="../../api/record-payment.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Tenant</label>
                            <select class="form-control" name="tenant_id" id="payment_tenant_id" required>
                                <option value="">-- Select Tenant --</option>
                                <?php
                                // Fetch tenants
                                $tenants = "SELECT tenant_id, full_name, property_id FROM estate_tenants WHERE status='Active'";
                                $stmt = $db->prepare($tenants);
                                $stmt->execute();
                                $tenants_result = $stmt->get_result();
                                while ($row = $tenants_result->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $row['tenant_id']; ?>">
                                        <?php echo $row['full_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Property</label>
                            <select class="form-control" name="property_id" id="payment_property_id" required>
                                <option value="">-- Select Property --</option>
                                <?php
                                // Fetch properties
                                $properties = "SELECT property_id, property_name FROM estate_properties WHERE status='Occupied' OR status='Available'";
                                $stmt = $db->prepare($properties);
                                $stmt->execute();
                                $properties_result = $stmt->get_result();
                                while ($row = $properties_result->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $row['property_id']; ?>">
                                        <?php echo $row['property_name']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- <div class="mb-3">
                            <label class="form-label">Tenant</label>
                            <input type="text" class="form-control" id="payment_tenant_name" readonly>
                        </div> -->

                        <div class="mb-3">
                            <label class="form-label">Payment Date</label>
                            <input type="date" class="form-control" name="payment_date"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" class="form-control"
                                name="amount" id="payment_amount" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select class="form-control" name="payment_method" required>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Mobile Money">Mobile Money</option>
                                <option value="Credit Card">Credit Card</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Transaction Reference</label>
                            <input type="text" class="form-control" name="transaction_reference">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Period Start</label>
                                <input type="date" class="form-control"
                                    name="payment_period_start" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Period End</label>
                                <input type="date" class="form-control"
                                    name="payment_period_end" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>

    <script>
        $(document).ready(function() {
            $('#paymentModal').on('show.bs.modal', function(event) {
                const button = $(event.relatedTarget);

                $('#payment_tenant_id').val(button.data('tenant-id'));
                $('#payment_property_id').val(button.data('property-id'));
                $('#payment_tenant_name').val(button.data('tenant-name'));
            });
            document.getElementById('payment_tenant_id').addEventListener('change', function() {
                let selected = this.options[this.selectedIndex];
                let propertyId = selected.getAttribute('data-property');

                if (propertyId) {
                    document.getElementById('payment_property_id').value = propertyId;
                }
            });
            // Initialize DataTables
            $('#tenantsTable').DataTable({
                pageLength: 25,
                order: [
                    [0, 'asc']
                ]
            });

            $('#paymentsTable').DataTable({
                pageLength: 25,
                order: [
                    [3, 'desc']
                ]
            });

            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#addTenantModal')
            });

            // Calculate lease dates
            $('#lease_start_date, #lease_end_date').change(function() {
                let start = new Date($('#lease_start_date').val());
                let end = new Date($('#lease_end_date').val());

                if (start && end) {
                    let diffTime = Math.abs(end - start);
                    let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    let diffMonths = Math.round(diffDays / 30);

                    if (diffMonths > 0) {
                        $('#lease_duration').val(diffMonths + ' months');
                    }
                }
            });
        });

        function recordPayment(tenantId, tenantName, monthlyRent) {
            $('#payment_tenant_id').val(tenantId);
            $('#payment_tenant_name').val(tenantName);
            $('#payment_amount').val(monthlyRent);

            // Set default period to current month
            let today = new Date();
            let firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            let lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

            $('input[name="period_start"]').val(firstDay.toISOString().split('T')[0]);
            $('input[name="period_end"]').val(lastDay.toISOString().split('T')[0]);

            $('#paymentModal').modal('show');
        }

        function assignMaintenance(maintenanceId) {
            let contractor = prompt('Enter contractor name:');
            let phone = prompt('Enter contractor phone number:');
            if (contractor) {
                $.post('../../api/assign-maintenance.php', {
                    id: maintenanceId,
                    contractor: contractor,
                    phone: phone
                }, function() {
                    location.reload();
                });
            }
        }

        function completeMaintenance(maintenanceId) {
            if (confirm('Mark this maintenance as complete?')) {
                $.post('../../api/complete-maintenance.php', {
                    id: maintenanceId
                }, function() {
                    location.reload();
                });
            }
        }

        function generateReport(type) {
            window.location.href = '../../reports/generate.php?type=' + type + '&company=estate';
        }

        function exportTenants() {
            window.location.href = '../../reports/export.php?type=tenants&format=csv';
        }
    </script>
</body>

</html>