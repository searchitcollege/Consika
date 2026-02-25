<?php
require_once '../includes/session.php';
$session->requireLogin();

$current_user = currentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

global $db;

// Get saved reports
$reports_query = "SELECT r.*, u.full_name as created_by_name 
                 FROM saved_reports r
                 JOIN users u ON r.generated_by = u.user_id
                 WHERE r.generated_by = ? OR r.is_global = 1
                 ORDER BY r.created_at DESC";
$stmt = $db->prepare($reports_query);
$stmt->bind_param("i", $current_user['user_id']);
$stmt->execute();
$saved_reports = $stmt->get_result();

// Get report templates
$templates = [
    'estate' => [
        'name' => 'Estate Reports',
        'reports' => [
            'rent-collection' => 'Rent Collection Report',
            'tenant-directory' => 'Tenant Directory',
            'occupancy-rate' => 'Occupancy Rate Analysis',
            'maintenance-summary' => 'Maintenance Summary',
            'lease-expiry' => 'Upcoming Lease Expiries',
            'property-valuation' => 'Property Valuation Report',
            'income-statement' => 'Income Statement (Estate)'
        ]
    ],
    'procurement' => [
        'name' => 'Procurement Reports',
        'reports' => [
            'purchase-orders' => 'Purchase Orders Report',
            'supplier-performance' => 'Supplier Performance',
            'inventory-status' => 'Inventory Status',
            'stock-movement' => 'Stock Movement Analysis',
            'procurement-summary' => 'Procurement Summary',
            'low-stock' => 'Low Stock Alert',
            'supplier-directory' => 'Supplier Directory'
        ]
    ],
    'works' => [
        'name' => 'Works Reports',
        'reports' => [
            'project-progress' => 'Project Progress Report',
            'project-cost' => 'Project Cost Analysis',
            'employee-hours' => 'Employee Hours Report',
            'material-usage' => 'Material Usage Report',
            'daily-reports' => 'Daily Reports Summary',
            'project-timeline' => 'Project Timeline',
            'budget-variance' => 'Budget Variance Analysis'
        ]
    ],
    'blockfactory' => [
        'name' => 'Block Factory Reports',
        'reports' => [
            'production-summary' => 'Production Summary',
            'sales-report' => 'Sales Report',
            'inventory-report' => 'Inventory Report',
            'quality-control' => 'Quality Control Report',
            'delivery-status' => 'Delivery Status',
            'customer-report' => 'Customer Report',
            'profit-loss' => 'Profit & Loss Statement'
        ]
    ],
    'financial' => [
        'name' => 'Financial Reports',
        'reports' => [
            'income-statement' => 'Income Statement',
            'balance-sheet' => 'Balance Sheet',
            'cash-flow' => 'Cash Flow Statement',
            'accounts-receivable' => 'Accounts Receivable',
            'accounts-payable' => 'Accounts Payable',
            'tax-summary' => 'Tax Summary',
            'budget-vs-actual' => 'Budget vs Actual'
        ]
    ],
    'cross-company' => [
        'name' => 'Cross-Company Reports',
        'reports' => [
            'consolidated-income' => 'Consolidated Income',
            'intercompany-transactions' => 'Intercompany Transactions',
            'group-performance' => 'Group Performance Dashboard',
            'company-comparison' => 'Company Comparison'
        ]
    ]
];

// Get companies for filter
if ($role == 'SuperAdmin') {
    $companies = $db->query("SELECT company_id, company_name FROM companies WHERE status = 'Active'");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <!-- Date Range Picker -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
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
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: all 0.3s;
            border: 1px solid #e9ecef;
            cursor: pointer;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #4361ee;
        }
        
        .report-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }
        
        .report-icon.estate { background: linear-gradient(135deg, #4361ee, #3f37c9); }
        .report-icon.procurement { background: linear-gradient(135deg, #4cc9f0, #4895ef); }
        .report-icon.works { background: linear-gradient(135deg, #f72585, #b5179e); }
        .report-icon.blockfactory { background: linear-gradient(135deg, #43e97b, #38f9d7); }
        .report-icon.financial { background: linear-gradient(135deg, #f8961e, #f3722c); }
        .report-icon.cross-company { background: linear-gradient(135deg, #6c757d, #495057); }
        
        .report-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .report-description {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .saved-report-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: background 0.3s;
        }
        
        .saved-report-item:hover {
            background: #f8f9fa;
        }
        
        .saved-report-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #495057;
        }
        
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .report-preview {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-top: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .export-options {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
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
<body>
    <div class="wrapper">
        <!-- Include sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <?php include '../includes/top-nav.php'; ?>
            
            <!-- Page Header -->
            <div class="page-header">
                <h4 class="mb-1">Reports & Analytics</h4>
                <p class="text-muted mb-0">Generate and manage business reports</p>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-control" id="reportType">
                            <option value="">Select Report Type</option>
                            <?php foreach ($templates as $key => $category): ?>
                            <optgroup label="<?php echo $category['name']; ?>">
                                <?php foreach ($category['reports'] as $report_key => $report_name): ?>
                                <option value="<?php echo $key . '.' . $report_key; ?>"><?php echo $report_name; ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($role == 'SuperAdmin'): ?>
                    <div class="col-md-2">
                        <label class="form-label">Company</label>
                        <select class="form-control" id="companyFilter">
                            <option value="all">All Companies</option>
                            <?php while($company = $companies->fetch_assoc()): ?>
                            <option value="<?php echo $company['company_id']; ?>"><?php echo $company['company_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-3">
                        <label class="form-label">Date Range</label>
                        <input type="text" class="form-control" id="dateRange" name="dateRange">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Format</label>
                        <select class="form-control" id="exportFormat">
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel</option>
                            <option value="csv">CSV</option>
                            <option value="html">HTML</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" onclick="generateReport()">
                            <i class="fas fa-chart-line me-2"></i>Generate
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="reportsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" role="tab">
                        <i class="fas fa-file-alt me-2"></i>Report Templates
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="saved-tab" data-bs-toggle="tab" data-bs-target="#saved" type="button" role="tab">
                        <i class="fas fa-save me-2"></i>Saved Reports
                        <?php if ($saved_reports->num_rows > 0): ?>
                        <span class="badge bg-primary ms-2"><?php echo $saved_reports->num_rows; ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="scheduled-tab" data-bs-toggle="tab" data-bs-target="#scheduled" type="button" role="tab">
                        <i class="fas fa-clock me-2"></i>Scheduled Reports
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab">
                        <i class="fas fa-tachometer-alt me-2"></i>Analytics Dashboard
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="reportsTabContent">
                <!-- Templates Tab -->
                <div class="tab-pane fade show active" id="templates" role="tabpanel">
                    <?php foreach ($templates as $category_key => $category): ?>
                    <div class="mb-4">
                        <h5 class="mb-3"><?php echo $category['name']; ?></h5>
                        <div class="row">
                            <?php foreach ($category['reports'] as $report_key => $report_name): 
                                $icon_class = $category_key;
                                $description = getReportDescription($category_key, $report_key);
                            ?>
                            <div class="col-md-3">
                                <div class="report-card" onclick="selectReport('<?php echo $category_key . '.' . $report_key; ?>', '<?php echo $report_name; ?>')">
                                    <div class="report-icon <?php echo $icon_class; ?>">
                                        <i class="fas fa-<?php echo getReportIcon($category_key, $report_key); ?>"></i>
                                    </div>
                                    <h6 class="report-title"><?php echo $report_name; ?></h6>
                                    <p class="report-description"><?php echo $description; ?></p>
                                    <small class="text-muted">Click to generate</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Saved Reports Tab -->
                <div class="tab-pane fade" id="saved" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Saved Reports</h5>
                            <button class="btn btn-sm btn-primary" onclick="refreshSavedReports()">
                                <i class="fas fa-sync-alt me-2"></i>Refresh
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if ($saved_reports->num_rows > 0): ?>
                                <div class="list-group">
                                    <?php while($report = $saved_reports->fetch_assoc()): ?>
                                    <div class="saved-report-item">
                                        <div class="saved-report-icon">
                                            <i class="fas fa-<?php echo $report['format'] == 'pdf' ? 'file-pdf' : ($report['format'] == 'excel' ? 'file-excel' : 'file-alt'); ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($report['report_name']); ?></h6>
                                            <small class="text-muted">
                                                Generated by <?php echo $report['created_by_name']; ?> on <?php echo format_datetime($report['generated_date']); ?>
                                            </small>
                                        </div>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewReport(<?php echo $report['report_id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="downloadReport(<?php echo $report['report_id']; ?>)">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteReport(<?php echo $report['report_id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-4">No saved reports found</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Scheduled Reports Tab -->
                <div class="tab-pane fade" id="scheduled" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Scheduled Reports</h5>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                                <i class="fas fa-plus-circle me-2"></i>Schedule New Report
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="scheduledTable">
                                    <thead>
                                        <tr>
                                            <th>Report Name</th>
                                            <th>Type</th>
                                            <th>Frequency</th>
                                            <th>Next Run</th>
                                            <th>Recipients</th>
                                            <th>Format</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $scheduled_query = "SELECT rs.*, u.email as created_by_email
                                                           FROM report_schedules rs
                                                           JOIN users u ON rs.created_by = u.user_id
                                                           WHERE rs.created_by = ? OR rs.is_public = 1
                                                           ORDER BY rs.next_run ASC";
                                        $stmt = $db->prepare($scheduled_query);
                                        $stmt->bind_param("i", $current_user['user_id']);
                                        $stmt->execute();
                                        $scheduled = $stmt->get_result();
                                        
                                        while($schedule = $scheduled->fetch_assoc()):
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($schedule['report_name']); ?></td>
                                            <td><?php echo $schedule['report_type']; ?></td>
                                            <td><?php echo $schedule['frequency']; ?></td>
                                            <td>
                                                <?php echo format_date($schedule['next_run']); ?>
                                                <?php if(strtotime($schedule['next_run']) < time()): ?>
                                                    <span class="badge bg-danger ms-2">Overdue</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $recipients = explode(',', $schedule['recipients']);
                                                foreach($recipients as $i => $email):
                                                    if($i < 2) echo substr($email, 0, 15) . '... ';
                                                endforeach;
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo strtoupper($schedule['format']); ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="editSchedule(<?php echo $schedule['schedule_id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteSchedule(<?php echo $schedule['schedule_id']; ?>)">
                                                    <i class="fas fa-times"></i>
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
                
                <!-- Analytics Dashboard Tab -->
                <div class="tab-pane fade" id="dashboard" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Revenue Overview</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="revenueChart" height="300"></canvas>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Top Performing Companies</h5>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="companyChart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Monthly Growth</h5>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="growthChart" height="250"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Key Metrics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Total Revenue</span>
                                            <strong id="totalRevenue">KES 0</strong>
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar bg-success" style="width: 75%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Active Projects</span>
                                            <strong id="activeProjects">0</strong>
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar bg-primary" style="width: 60%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Occupancy Rate</span>
                                            <strong id="occupancyRate">0%</strong>
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar bg-info" style="width: 45%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Inventory Value</span>
                                            <strong id="inventoryValue">KES 0</strong>
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar bg-warning" style="width: 30%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Recent Activities</h5>
                                </div>
                                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                                    <div id="recentActivities"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Report Preview Section -->
            <div class="report-preview" id="reportPreview" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 id="previewTitle">Report Preview</h5>
                    <div class="export-options">
                        <button class="btn btn-sm btn-outline-primary" onclick="exportReport('pdf')">
                            <i class="fas fa-file-pdf me-2"></i>PDF
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="exportReport('excel')">
                            <i class="fas fa-file-excel me-2"></i>Excel
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="exportReport('csv')">
                            <i class="fas fa-file-csv me-2"></i>CSV
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="printReport()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                        <button class="btn btn-sm btn-outline-warning" onclick="saveReport()">
                            <i class="fas fa-save me-2"></i>Save
                        </button>
                    </div>
                </div>
                <div id="previewContent">
                    <!-- Report content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Schedule Report Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/schedule-report.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Report Name</label>
                            <input type="text" class="form-control" name="report_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Report Type</label>
                            <select class="form-control" name="report_type" id="scheduleReportType" required>
                                <option value="">Select Report</option>
                                <?php foreach ($templates as $category_key => $category): ?>
                                <optgroup label="<?php echo $category['name']; ?>">
                                    <?php foreach ($category['reports'] as $report_key => $report_name): ?>
                                    <option value="<?php echo $category_key . '.' . $report_key; ?>"><?php echo $report_name; ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Frequency</label>
                            <select class="form-control" name="frequency" required>
                                <option value="Daily">Daily</option>
                                <option value="Weekly">Weekly</option>
                                <option value="Monthly">Monthly</option>
                                <option value="Quarterly">Quarterly</option>
                                <option value="Yearly">Yearly</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Format</label>
                            <select class="form-control" name="format">
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Recipients (comma-separated emails)</label>
                            <textarea class="form-control" name="recipients" rows="2" placeholder="email1@example.com, email2@example.com"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Schedule Report</button>
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
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="../assets/js/main.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize Date Range Picker
            $('#dateRange').daterangepicker({
                startDate: moment().startOf('month'),
                endDate: moment().endOf('month'),
                ranges: {
                   'Today': [moment(), moment()],
                   'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                   'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                   'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                   'This Month': [moment().startOf('month'), moment().endOf('month')],
                   'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                   'This Year': [moment().startOf('year'), moment().endOf('year')],
                   'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
                }
            });
            
            // Initialize DataTables
            $('#scheduledTable').DataTable({
                pageLength: 10,
                order: [[3, 'asc']]
            });
            
            // Load analytics dashboard
            loadAnalyticsDashboard();
        });
        
        let currentReportType = '';
        let currentReportName = '';
        
        function selectReport(type, name) {
            currentReportType = type;
            currentReportName = name;
            
            // Set in filter section
            $('#reportType').val(type);
            
            // Generate preview
            generateReport();
        }
        
        function generateReport() {
            let reportType = $('#reportType').val();
            let dateRange = $('#dateRange').val();
            let companyFilter = $('#companyFilter').val();
            let format = $('#exportFormat').val();
            
            if (!reportType) {
                alert('Please select a report type');
                return;
            }
            
            // Parse date range
            let dates = dateRange.split(' - ');
            let startDate = dates[0];
            let endDate = dates[1];
            
            $('#reportPreview').show();
            $('#previewTitle').text(currentReportName || 'Report Preview');
            $('#previewContent').html('<div class="text-center"><div class="spinner"></div><p class="mt-3">Generating report...</p></div>');
            
            // Load preview via AJAX
            $.post('ajax/generate-report-preview.php', {
                type: reportType,
                start_date: startDate,
                end_date: endDate,
                company_id: companyFilter
            }, function(data) {
                $('#previewContent').html(data);
            }).fail(function() {
                $('#previewContent').html('<div class="alert alert-danger">Error generating report</div>');
            });
        }
        
        function exportReport(format) {
            let reportType = $('#reportType').val();
            let dateRange = $('#dateRange').val();
            let companyFilter = $('#companyFilter').val();
            
            if (!reportType) {
                alert('Please select a report type');
                return;
            }
            
            let dates = dateRange.split(' - ');
            let startDate = dates[0];
            let endDate = dates[1];
            
            window.location.href = 'export.php?type=' + reportType + 
                                   '&start=' + startDate + 
                                   '&end=' + endDate + 
                                   '&company=' + companyFilter + 
                                   '&format=' + format;
        }
        
        function printReport() {
            window.print();
        }
        
        function saveReport() {
            let reportType = $('#reportType').val();
            let dateRange = $('#dateRange').val();
            let companyFilter = $('#companyFilter').val();
            
            let reportName = prompt('Enter a name for this report:', currentReportName || 'My Report');
            if (!reportName) return;
            
            let dates = dateRange.split(' - ');
            
            $.post('process/save-report.php', {
                name: reportName,
                type: reportType,
                start_date: dates[0],
                end_date: dates[1],
                company_id: companyFilter
            }, function(response) {
                if (response.success) {
                    alert('Report saved successfully');
                    location.reload();
                } else {
                    alert('Error saving report: ' + response.message);
                }
            }, 'json');
        }
        
        function viewReport(id) {
            window.open('view.php?id=' + id, '_blank');
        }
        
        function downloadReport(id) {
            window.location.href = 'download.php?id=' + id;
        }
        
        function deleteReport(id) {
            if (confirm('Are you sure you want to delete this report?')) {
                $.post('process/delete-report.php', {id: id}, function() {
                    location.reload();
                });
            }
        }
        
        function editSchedule(id) {
            window.location.href = 'edit-schedule.php?id=' + id;
        }
        
        function deleteSchedule(id) {
            if (confirm('Are you sure you want to delete this schedule?')) {
                $.post('process/delete-schedule.php', {id: id}, function() {
                    location.reload();
                });
            }
        }
        
        function refreshSavedReports() {
            location.reload();
        }
        
        function loadAnalyticsDashboard() {
            // Load revenue chart
            $.get('ajax/revenue-data.php', function(data) {
                new Chart(document.getElementById('revenueChart'), {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Revenue',
                            data: data.values,
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
                        }
                    }
                });
                
                // Update metrics
                $('#totalRevenue').text(formatMoney(data.total_revenue));
                $('#activeProjects').text(data.active_projects);
                $('#occupancyRate').text(data.occupancy_rate + '%');
                $('#inventoryValue').text(formatMoney(data.inventory_value));
                
                // Load activities
                let activitiesHtml = '';
                data.recent_activities.forEach(function(activity) {
                    activitiesHtml += `
                        <div class="mb-2 pb-2 border-bottom">
                            <small class="text-muted">${activity.time}</small>
                            <p class="mb-0">${activity.description}</p>
                        </div>
                    `;
                });
                $('#recentActivities').html(activitiesHtml);
            });
        }
        
        function formatMoney(amount) {
            return 'KES ' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
    </script>
</body>
</html>

<?php
// Helper functions
function getReportIcon($category, $report) {
    $icons = [
        'estate' => [
            'rent-collection' => 'money-bill-wave',
            'tenant-directory' => 'users',
            'occupancy-rate' => 'chart-pie',
            'maintenance-summary' => 'tools',
            'lease-expiry' => 'calendar-alt',
            'property-valuation' => 'home',
            'income-statement' => 'chart-line'
        ],
        'procurement' => [
            'purchase-orders' => 'file-invoice',
            'supplier-performance' => 'star',
            'inventory-status' => 'boxes',
            'stock-movement' => 'exchange-alt',
            'procurement-summary' => 'shopping-cart',
            'low-stock' => 'exclamation-triangle',
            'supplier-directory' => 'address-book'
        ],
        'works' => [
            'project-progress' => 'tasks',
            'project-cost' => 'coins',
            'employee-hours' => 'clock',
            'material-usage' => 'box',
            'daily-reports' => 'clipboard-list',
            'project-timeline' => 'calendar-check',
            'budget-variance' => 'chart-bar'
        ],
        'blockfactory' => [
            'production-summary' => 'industry',
            'sales-report' => 'chart-line',
            'inventory-report' => 'warehouse',
            'quality-control' => 'check-circle',
            'delivery-status' => 'truck',
            'customer-report' => 'users',
            'profit-loss' => 'chart-pie'
        ],
        'financial' => [
            'income-statement' => 'file-invoice-dollar',
            'balance-sheet' => 'balance-scale',
            'cash-flow' => 'money-bill-wave',
            'accounts-receivable' => 'hand-holding-usd',
            'accounts-payable' => 'hand-holding',
            'tax-summary' => 'file-invoice',
            'budget-vs-actual' => 'chart-line'
        ],
        'cross-company' => [
            'consolidated-income' => 'building',
            'intercompany-transactions' => 'exchange-alt',
            'group-performance' => 'chart-pie',
            'company-comparison' => 'balance-scale'
        ]
    ];
    
    return $icons[$category][$report] ?? 'file-alt';
}

function getReportDescription($category, $report) {
    $descriptions = [
        'estate' => [
            'rent-collection' => 'Monthly rent collection summary with tenant details',
            'tenant-directory' => 'Complete list of all tenants with contact information',
            'occupancy-rate' => 'Property occupancy rates and vacancy analysis',
            'maintenance-summary' => 'Overview of maintenance requests and costs',
            'lease-expiry' => 'Upcoming lease expirations and renewals',
            'property-valuation' => 'Property values and market analysis',
            'income-statement' => 'Revenue and expenses for estate properties'
        ],
        'procurement' => [
            'purchase-orders' => 'List of all purchase orders with status',
            'supplier-performance' => 'Supplier ratings and performance metrics',
            'inventory-status' => 'Current inventory levels by product',
            'stock-movement' => 'Stock in/out transactions history',
            'procurement-summary' => 'Procurement spend analysis',
            'low-stock' => 'Items below reorder level',
            'supplier-directory' => 'Complete supplier contact information'
        ],
        'works' => [
            'project-progress' => 'Project completion status and milestones',
            'project-cost' => 'Actual vs budgeted project costs',
            'employee-hours' => 'Employee hours worked by project',
            'material-usage' => 'Materials consumed by project',
            'daily-reports' => 'Daily site reports summary',
            'project-timeline' => 'Project schedules and deadlines',
            'budget-variance' => 'Budget vs actual variance analysis'
        ],
        'blockfactory' => [
            'production-summary' => 'Daily/Monthly production volumes',
            'sales-report' => 'Sales transactions and revenue',
            'inventory-report' => 'Current block inventory by type',
            'quality-control' => 'Defect rates and quality metrics',
            'delivery-status' => 'Delivery schedules and status',
            'customer-report' => 'Customer purchase history',
            'profit-loss' => 'Revenue, costs and profitability'
        ],
        'financial' => [
            'income-statement' => 'Revenue and expenses summary',
            'balance-sheet' => 'Assets, liabilities and equity',
            'cash-flow' => 'Cash inflows and outflows',
            'accounts-receivable' => 'Outstanding customer payments',
            'accounts-payable' => 'Outstanding supplier payments',
            'tax-summary' => 'Tax calculations and filings',
            'budget-vs-actual' => 'Budget performance analysis'
        ],
        'cross-company' => [
            'consolidated-income' => 'Combined revenue across all companies',
            'intercompany-transactions' => 'Transactions between companies',
            'group-performance' => 'Overall group performance metrics',
            'company-comparison' => 'Compare performance across companies'
        ]
    ];
    
    return $descriptions[$category][$report] ?? 'Generate detailed report with customizable parameters';
}
?>