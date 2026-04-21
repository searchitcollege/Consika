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

// Get date range from request or set defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// Get properties for filter
$property_filter = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;

// Get all properties for dropdown
$properties = $db->query("SELECT property_id, property_name FROM estate_properties WHERE company_id = $company_id ORDER BY property_name");

// Generate reports based on type
$report_data = [];
$chart_data = [];

switch ($report_type) {
    case 'rent_collection':
        // Rent Collection Report
        $query = "SELECT 
                  DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                  COUNT(*) as payment_count,
                  COALESCE(SUM(p.amount), 0) as total_collected,
                  COUNT(DISTINCT p.tenant_id) as tenants_paid,
                  (SELECT COUNT(*) FROM estate_tenants WHERE status = 'Active') as total_tenants
                  FROM estate_payments p
                  JOIN estate_properties pr ON p.property_id = pr.property_id
                  WHERE pr.company_id = ? AND p.payment_date BETWEEN ? AND ?";
        
        if ($property_filter > 0) {
            $query .= " AND p.property_id = ?";
            $query .= " GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m') ORDER BY month DESC";
            $stmt = $db->prepare($query);
            $stmt->bind_param("issi", $company_id, $start_date, $end_date, $property_filter);
        } else {
            $query .= " GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m') ORDER BY month DESC";
            $stmt = $db->prepare($query);
            $stmt->bind_param("iss", $company_id, $start_date, $end_date);
        }
        $stmt->execute();
        $report_data = $stmt->get_result();
        
        // Get chart data
        $chart_query = "SELECT 
                        DATE_FORMAT(payment_date, '%Y-%m') as month,
                        COALESCE(SUM(amount), 0) as total
                        FROM estate_payments p
                        JOIN estate_properties pr ON p.property_id = pr.property_id
                        WHERE pr.company_id = ? AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                        ORDER BY month ASC";
        $stmt = $db->prepare($chart_query);
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $chart_result = $stmt->get_result();
        
        $chart_labels = [];
        $chart_values = [];
        while ($row = $chart_result->fetch_assoc()) {
            $chart_labels[] = $row['month'];
            $chart_values[] = $row['total'];
        }
        $chart_data = ['labels' => $chart_labels, 'values' => $chart_values];
        break;
        
    case 'occupancy':
        // Occupancy Report
        $query = "SELECT 
                  p.property_id,
                  p.property_name,
                  p.property_type,
                  p.units as total_units,
                  COUNT(t.tenant_id) as occupied_units,
                  (p.units - COUNT(t.tenant_id)) as vacant_units,
                  COALESCE(SUM(t.monthly_rent), 0) as potential_rent,
                  COALESCE((SELECT SUM(amount) FROM estate_payments WHERE property_id = p.property_id AND payment_date BETWEEN ? AND ?), 0) as actual_rent
                  FROM estate_properties p
                  LEFT JOIN estate_tenants t ON p.property_id = t.property_id AND t.status = 'Active'
                  WHERE p.company_id = ?
                  GROUP BY p.property_id";
        
        if ($property_filter > 0) {
            $query .= " HAVING p.property_id = ?";
            $stmt = $db->prepare($query);
            $stmt->bind_param("ssii", $start_date, $end_date, $company_id, $property_filter);
        } else {
            $stmt = $db->prepare($query);
            $stmt->bind_param("ssi", $start_date, $end_date, $company_id);
        }
        $stmt->execute();
        $report_data = $stmt->get_result();
        
        // Calculate totals
        $total_units = 0;
        $occupied_units = 0;
        $temp_result = clone $report_data;
        $temp_result->data_seek(0);
        while ($row = $temp_result->fetch_assoc()) {
            $total_units += $row['total_units'];
            $occupied_units += $row['occupied_units'];
        }
        $overall_occupancy = $total_units > 0 ? round(($occupied_units / $total_units) * 100, 2) : 0;
        break;
        
    case 'maintenance':
        // Maintenance Report
        $query = "SELECT 
                  DATE_FORMAT(m.request_date, '%Y-%m') as month,
                  COUNT(*) as total_requests,
                  SUM(CASE WHEN m.priority = 'Emergency' THEN 1 ELSE 0 END) as emergency,
                  SUM(CASE WHEN m.priority = 'High' THEN 1 ELSE 0 END) as high,
                  SUM(CASE WHEN m.priority = 'Medium' THEN 1 ELSE 0 END) as medium,
                  SUM(CASE WHEN m.priority = 'Low' THEN 1 ELSE 0 END) as low,
                  SUM(CASE WHEN m.status = 'Completed' THEN 1 ELSE 0 END) as completed,
                  COALESCE(AVG(CASE WHEN m.status = 'Completed' THEN DATEDIFF(m.completion_date, m.request_date) ELSE NULL END), 0) as avg_days,
                  COALESCE(SUM(m.actual_cost), 0) as total_cost
                  FROM estate_maintenance m
                  JOIN estate_properties p ON m.property_id = p.property_id
                  WHERE p.company_id = ? AND m.request_date BETWEEN ? AND ?";
        
        if ($property_filter > 0) {
            $query .= " AND m.property_id = ?";
            $query .= " GROUP BY DATE_FORMAT(m.request_date, '%Y-%m') ORDER BY month DESC";
            $stmt = $db->prepare($query);
            $stmt->bind_param("issi", $company_id, $start_date, $end_date, $property_filter);
        } else {
            $query .= " GROUP BY DATE_FORMAT(m.request_date, '%Y-%m') ORDER BY month DESC";
            $stmt = $db->prepare($query);
            $stmt->bind_param("iss", $company_id, $start_date, $end_date);
        }
        $stmt->execute();
        $report_data = $stmt->get_result();
        break;
        
    case 'tenant_arrears':
        // Tenant Arrears Report
        $query = "SELECT 
                  t.tenant_id,
                  t.full_name,
                  t.tenant_code,
                  p.property_name,
                  t.monthly_rent,
                  t.lease_start_date,
                  t.lease_end_date,
                  DATEDIFF(CURDATE(), t.lease_end_date) as days_overdue,
                  COALESCE((SELECT SUM(amount) FROM estate_payments WHERE tenant_id = t.tenant_id AND payment_period_end < CURDATE()), 0) as paid_to_date,
                  COALESCE((SELECT SUM(amount) FROM estate_payments WHERE tenant_id = t.tenant_id), 0) as total_paid
                  FROM estate_tenants t
                  JOIN estate_properties p ON t.property_id = p.property_id
                  WHERE p.company_id = ? AND t.status = 'Active'
                  HAVING days_overdue > 0
                  ORDER BY days_overdue DESC";
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $report_data = $stmt->get_result();
        break;
        
    default:
        // Summary Report
        // Rent summary
        $rent_query = "SELECT 
                      COALESCE(SUM(amount), 0) as total_collected,
                      COUNT(*) as payment_count,
                      COUNT(DISTINCT tenant_id) as paying_tenants
                      FROM estate_payments p
                      JOIN estate_properties pr ON p.property_id = pr.property_id
                      WHERE pr.company_id = ? AND p.payment_date BETWEEN ? AND ?";
        $stmt = $db->prepare($rent_query);
        $stmt->bind_param("iss", $company_id, $start_date, $end_date);
        $stmt->execute();
        $rent_summary = $stmt->get_result()->fetch_assoc();
        
        // Property summary
        $prop_query = "SELECT 
                      COUNT(*) as total_properties,
                      SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
                      SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
                      SUM(CASE WHEN status = 'Under Maintenance' THEN 1 ELSE 0 END) as maintenance
                      FROM estate_properties
                      WHERE company_id = ?";
        $stmt = $db->prepare($prop_query);
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $prop_summary = $stmt->get_result()->fetch_assoc();
        
        // Tenant summary - FIXED: Using simple separate queries to avoid syntax issues
        $total_query = "SELECT COUNT(*) as total FROM estate_tenants";
        $stmt = $db->prepare($total_query);
        $stmt->execute();
        $total_result = $stmt->get_result()->fetch_assoc();
        
        $active_query = "SELECT COUNT(*) as count FROM estate_tenants WHERE status = 'Active'";
        $stmt = $db->prepare($active_query);
        $stmt->execute();
        $active_result = $stmt->get_result()->fetch_assoc();
        
        $notice_query = "SELECT COUNT(*) as count FROM estate_tenants WHERE status = 'Notice'";
        $stmt = $db->prepare($notice_query);
        $stmt->execute();
        $notice_result = $stmt->get_result()->fetch_assoc();
        
        $terminated_query = "SELECT COUNT(*) as count FROM estate_tenants WHERE status = 'Terminated'";
        $stmt = $db->prepare($terminated_query);
        $stmt->execute();
        $terminated_result = $stmt->get_result()->fetch_assoc();
        
        $tenant_summary = [
            'total_tenants' => $total_result['total'] ?? 0,
            'active' => $active_result['count'] ?? 0,
            'notice' => $notice_result['count'] ?? 0,
            'terminated' => $terminated_result['count'] ?? 0
        ];
        
        // Maintenance summary
        $maint_query = "SELECT 
                       COUNT(*) as total_requests,
                       SUM(CASE WHEN m.status = 'Pending' THEN 1 ELSE 0 END) as pending,
                       SUM(CASE WHEN m.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
                       SUM(CASE WHEN m.status = 'Completed' THEN 1 ELSE 0 END) as completed
                       FROM estate_maintenance m
                       JOIN estate_properties p ON m.property_id = p.property_id
                       WHERE p.company_id = ? AND m.request_date BETWEEN ? AND ?";
        $stmt = $db->prepare($maint_query);
        $stmt->bind_param("iss", $company_id, $start_date, $end_date);
        $stmt->execute();
        $maint_summary = $stmt->get_result()->fetch_assoc();
        
        $report_data = [
            'rent' => $rent_summary,
            'properties' => $prop_summary,
            'tenants' => $tenant_summary,
            'maintenance' => $maint_summary
        ];
        break;
}

$page_title = 'Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Estate Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .report-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #4361ee;
        }
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-box {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .stat-box h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .report-type-btn {
            margin: 5px;
        }
        .report-type-btn.active {
            background: #4361ee;
            color: white;
            border-color: #4361ee;
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
                <div class="page-header">
                    <h4 class="mb-1">Reports & Analytics</h4>
                    <p class="text-muted mb-0">Generate and view estate reports</p>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Report Type</label>
                            <select class="form-control" name="report_type" onchange="this.form.submit()">
                                <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                                <option value="rent_collection" <?php echo $report_type == 'rent_collection' ? 'selected' : ''; ?>>Rent Collection</option>
                                <option value="occupancy" <?php echo $report_type == 'occupancy' ? 'selected' : ''; ?>>Occupancy Report</option>
                                <option value="maintenance" <?php echo $report_type == 'maintenance' ? 'selected' : ''; ?>>Maintenance Report</option>
                                <option value="tenant_arrears" <?php echo $report_type == 'tenant_arrears' ? 'selected' : ''; ?>>Tenant Arrears</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Property (Optional)</label>
                            <select class="form-control" name="property_id" onchange="this.form.submit()">
                                <option value="0">All Properties</option>
                                <?php 
                                // Reset properties pointer
                                $properties->data_seek(0);
                                while($prop = $properties->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $prop['property_id']; ?>" <?php echo $property_filter == $prop['property_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prop['property_name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-success w-100" onclick="exportReport()">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Report Content -->
                <div class="report-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5>
                            <?php 
                            switch($report_type) {
                                case 'rent_collection': echo 'Rent Collection Report'; break;
                                case 'occupancy': echo 'Occupancy Report'; break;
                                case 'maintenance': echo 'Maintenance Report'; break;
                                case 'tenant_arrears': echo 'Tenant Arrears Report'; break;
                                default: echo 'Summary Report';
                            }
                            ?>
                        </h5>
                        <span class="badge bg-info"><?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></span>
                    </div>
                    
                    <?php if ($report_type == 'summary'): ?>
                        <!-- Summary Report -->
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-3">Rent Collection Summary</h6>
                                <div class="stat-box mb-3">
                                    <p class="text-muted mb-1">Total Collected</p>
                                    <h3><?php echo formatMoney($report_data['rent']['total_collected'] ?? 0); ?></h3>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="stat-box">
                                            <p class="text-muted mb-1">Payments</p>
                                            <h3><?php echo intval($report_data['rent']['payment_count'] ?? 0); ?></h3>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-box">
                                            <p class="text-muted mb-1">Paying Tenants</p>
                                            <h3><?php echo intval($report_data['rent']['paying_tenants'] ?? 0); ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="mb-3">Property Status</h6>
                                <div class="stat-box mb-3">
                                    <p class="text-muted mb-1">Total Properties</p>
                                    <h3><?php echo intval($report_data['properties']['total_properties'] ?? 0); ?></h3>
                                </div>
                                <div class="row">
                                    <div class="col-4">
                                        <div class="stat-box" style="background: #d4edda;">
                                            <p class="text-muted mb-1">Occupied</p>
                                            <h3><?php echo intval($report_data['properties']['occupied'] ?? 0); ?></h3>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-box" style="background: #cce5ff;">
                                            <p class="text-muted mb-1">Available</p>
                                            <h3><?php echo intval($report_data['properties']['available'] ?? 0); ?></h3>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-box" style="background: #fff3cd;">
                                            <p class="text-muted mb-1">Maintenance</p>
                                            <h3><?php echo intval($report_data['properties']['maintenance'] ?? 0); ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <h6 class="mb-3">Tenant Summary</h6>
                                <div class="stat-box mb-3">
                                    <p class="text-muted mb-1">Total Tenants</p>
                                    <h3><?php echo intval($report_data['tenants']['total_tenants'] ?? 0); ?></h3>
                                </div>
                                <div class="row">
                                    <div class="col-4">
                                        <div class="stat-box" style="background: #d4edda;">
                                            <p class="text-muted mb-1">Active</p>
                                            <h3><?php echo intval($report_data['tenants']['active'] ?? 0); ?></h3>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-box" style="background: #fff3cd;">
                                            <p class="text-muted mb-1">Notice</p>
                                            <h3><?php echo intval($report_data['tenants']['notice'] ?? 0); ?></h3>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-box" style="background: #f8d7da;">
                                            <p class="text-muted mb-1">Terminated</p>
                                            <h3><?php echo intval($report_data['tenants']['terminated'] ?? 0); ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="mb-3">Maintenance Summary</h6>
                                <div class="stat-box mb-3">
                                    <p class="text-muted mb-1">Total Requests</p>
                                    <h3><?php echo intval($report_data['maintenance']['total_requests'] ?? 0); ?></h3>
                                </div>
                                <div class="row">
                                    <div class="col-4">
                                        <div class="stat-box" style="background: #fff3cd;">
                                            <p class="text-muted mb-1">Pending</p>
                                            <h3><?php echo intval($report_data['maintenance']['pending'] ?? 0); ?></h3>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-box" style="background: #cce5ff;">
                                            <p class="text-muted mb-1">In Progress</p>
                                            <h3><?php echo intval($report_data['maintenance']['in_progress'] ?? 0); ?></h3>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stat-box" style="background: #d4edda;">
                                            <p class="text-muted mb-1">Completed</p>
                                            <h3><?php echo intval($report_data['maintenance']['completed'] ?? 0); ?></h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($report_type == 'rent_collection'): ?>
                        <!-- Rent Collection Report -->
                        <?php if (!empty($chart_data['labels'])): ?>
                        <div class="mb-4">
                            <canvas id="rentChart" height="300"></canvas>
                        </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Payments</th>
                                        <th>Total Collected</th>
                                        <th>Tenants Paid</th>
                                        <th>Collection Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($report_data && $report_data->num_rows > 0): ?>
                                        <?php while($row = $report_data->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></strong></td>
                                            <td><?php echo $row['payment_count']; ?></td>
                                            <td><?php echo formatMoney($row['total_collected']); ?></td>
                                            <td><?php echo $row['tenants_paid']; ?> / <?php echo $row['total_tenants']; ?></td>
                                            <td>
                                                <?php 
                                                $rate = $row['total_tenants'] > 0 ? round(($row['tenants_paid'] / $row['total_tenants']) * 100, 2) : 0;
                                                ?>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $rate; ?>%;">
                                                        <?php echo $rate; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">No data available for the selected period</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <script>
                            <?php if (!empty($chart_data['labels'])): ?>
                            new Chart(document.getElementById('rentChart'), {
                                type: 'line',
                                data: {
                                    labels: <?php echo json_encode($chart_data['labels']); ?>,
                                    datasets: [{
                                        label: 'Monthly Rent Collection',
                                        data: <?php echo json_encode($chart_data['values']); ?>,
                                        borderColor: '#4361ee',
                                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                                        tension: 0.4,
                                        fill: true
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: false
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                callback: function(value) {
                                                    return 'GHS ' + value.toLocaleString();
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                            <?php endif; ?>
                        </script>
                        
                    <?php elseif ($report_type == 'occupancy'): ?>
                        <!-- Occupancy Report -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="stat-box">
                                    <p class="text-muted mb-1">Overall Occupancy Rate</p>
                                    <h3><?php echo $overall_occupancy; ?>%</h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-box">
                                    <p class="text-muted mb-1">Total Units</p>
                                    <h3><?php echo $total_units; ?></h3>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-box">
                                    <p class="text-muted mb-1">Occupied Units</p>
                                    <h3><?php echo $occupied_units; ?></h3>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Property</th>
                                        <th>Type</th>
                                        <th>Total Units</th>
                                        <th>Occupied</th>
                                        <th>Vacant</th>
                                        <th>Occupancy Rate</th>
                                        <th>Potential Rent</th>
                                        <th>Actual Rent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($report_data && $report_data->num_rows > 0):
                                        $report_data->data_seek(0);
                                        while($row = $report_data->fetch_assoc()): 
                                        $occ_rate = $row['total_units'] > 0 ? round(($row['occupied_units'] / $row['total_units']) * 100, 2) : 0;
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['property_name']); ?></strong></td>
                                        <td><?php echo $row['property_type']; ?></td>
                                        <td><?php echo $row['total_units']; ?></td>
                                        <td><?php echo $row['occupied_units']; ?></td>
                                        <td><?php echo $row['vacant_units']; ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-<?php echo $occ_rate >= 80 ? 'success' : ($occ_rate >= 50 ? 'warning' : 'danger'); ?>" 
                                                     style="width: <?php echo $occ_rate; ?>%;">
                                                    <?php echo $occ_rate; ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo formatMoney($row['potential_rent']); ?></td>
                                        <td><?php echo formatMoney($row['actual_rent']); ?></td>
                                    </tr>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">No data available</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                    <?php elseif ($report_type == 'maintenance'): ?>
                        <!-- Maintenance Report -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Total</th>
                                        <th>Emergency</th>
                                        <th>High</th>
                                        <th>Medium</th>
                                        <th>Low</th>
                                        <th>Completed</th>
                                        <th>Avg Days</th>
                                        <th>Total Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($report_data && $report_data->num_rows > 0): ?>
                                        <?php while($row = $report_data->fetch_assoc()): ?>
                                        <tr>
                                            <td><strong><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></strong></td>
                                            <td><?php echo $row['total_requests']; ?></td>
                                            <td><span class="badge bg-danger"><?php echo $row['emergency']; ?></span></td>
                                            <td><span class="badge bg-warning"><?php echo $row['high']; ?></span></td>
                                            <td><span class="badge bg-info"><?php echo $row['medium']; ?></span></td>
                                            <td><span class="badge bg-secondary"><?php echo $row['low']; ?></span></td>
                                            <td><?php echo $row['completed']; ?></td>
                                            <td><?php echo round($row['avg_days']); ?> days</td>
                                            <td><?php echo formatMoney($row['total_cost']); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">No maintenance data available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                    <?php elseif ($report_type == 'tenant_arrears'): ?>
                        <!-- Tenant Arrears Report -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Property</th>
                                        <th>Monthly Rent</th>
                                        <th>Lease End</th>
                                        <th>Days Overdue</th>
                                        <th>Total Paid</th>
                                        <th>Arrears</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($report_data && $report_data->num_rows > 0):
                                        $total_arrears = 0;
                                        while($row = $report_data->fetch_assoc()): 
                                        $arrears = ($row['days_overdue'] / 30) * $row['monthly_rent'];
                                        $total_arrears += $arrears;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                            <br>
                                            <small><?php echo $row['tenant_code']; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['property_name']); ?></td>
                                        <td><?php echo formatMoney($row['monthly_rent']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($row['lease_end_date'])); ?></td>
                                        <td><span class="badge bg-<?php echo $row['days_overdue'] > 30 ? 'danger' : 'warning'; ?>"><?php echo $row['days_overdue']; ?> days</span></td>
                                        <td><?php echo formatMoney($row['total_paid']); ?></td>
                                        <td><strong class="text-danger"><?php echo formatMoney($arrears); ?></strong></td>
                                        <td>
                                            <?php if ($row['days_overdue'] > 30): ?>
                                                <span class="badge bg-danger">Critical</span>
                                            <?php elseif ($row['days_overdue'] > 15): ?>
                                                <span class="badge bg-warning">Warning</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">Notice</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php 
                                        endwhile; 
                                    ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-info">
                                        <th colspan="6" class="text-end">Total Arrears:</th>
                                        <th colspan="2"><?php echo formatMoney($total_arrears); ?></th>
                                    </tr>
                                </tfoot>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">No tenants with arrears found</td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function exportReport() {
            var report_type = '<?php echo $report_type; ?>';
            var start_date = '<?php echo $start_date; ?>';
            var end_date = '<?php echo $end_date; ?>';
            var property_id = '<?php echo $property_filter; ?>';
            
            window.location.href = 'export.php?type=' + report_type + 
                                   '&start=' + start_date + 
                                   '&end=' + end_date + 
                                   '&property=' + property_id;
        }
    </script>
</body>
</html>