<?php
require_once '../../includes/session.php';
$session->requireLogin();

// Check permission
if (!hasPermission('works', 'view')) {
    $_SESSION['error'] = 'You do not have permission to access this module.';
    header('Location: ../../admin/dashboard.php');
    exit();
}

$current_user = currentUser();
$company_id = $session->getCompanyId();

global $db;

// Get statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM works_projects WHERE company_id = ? AND status = 'In Progress') as active_projects,
                (SELECT COUNT(*) FROM works_projects WHERE company_id = ? AND status = 'Completed') as completed_projects,
                (SELECT COUNT(*) FROM works_employees WHERE status = 'Active') as total_employees,
                (SELECT COUNT(*) FROM procurement_products WHERE category = 'Building Materials' AND current_stock <= minimum_stock) as low_materials,
                (SELECT COALESCE(SUM(budget), 0) FROM works_projects WHERE company_id = ? AND status != 'Completed') as total_budget,
                (SELECT COALESCE(SUM(actual_cost), 0) FROM works_projects WHERE company_id = ?) as total_cost";
$stmt = $db->prepare($stats_query);
$stmt->bind_param("iiii", $company_id, $company_id, $company_id, $company_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get active projects
$projects_query = "SELECT * FROM works_projects 
                   WHERE company_id = ? AND status = 'In Progress'
                   ORDER BY end_date ASC";
$stmt = $db->prepare($projects_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$active_projects = $stmt->get_result();

// Get recent daily reports
$reports_query = "SELECT dr.*, p.project_name, u.full_name as reporter_name
    FROM works_daily_reports dr
    JOIN works_projects p ON dr.project_id = p.project_id
    LEFT JOIN users u ON dr.submitted_by = u.user_id
    WHERE p.company_id = ?
    ORDER BY dr.created_at DESC
    LIMIT 10";
$stmt = $db->prepare($reports_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$recent_reports = $stmt->get_result();

// Get upcoming deadlines
$deadlines_query = "SELECT project_name, end_date, 
                    DATEDIFF(end_date, CURRENT_DATE()) as days_left
                    FROM works_projects 
                    WHERE company_id = ? AND status = 'In Progress'
                    AND end_date IS NOT NULL
                    ORDER BY end_date ASC LIMIT 5";
$stmt = $db->prepare($deadlines_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$upcoming_deadlines = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Works & Construction - <?php echo APP_NAME; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- FullCalendar -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.css' rel='stylesheet' />

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Custom CSS -->
    <link href="../../assets/css/style.css" rel="stylesheet">

    <!-- Custom Styles Specific to workd -->
    <link href="../../assets/css/works/style.css" rel="stylesheet">
</head>

<body class="module-works">
    <div class="wrapper container-fluid p-0">
        <!-- Include sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <?php include '../../includes/top-nav.php'; ?>

            <!-- Module Header -->
                        <div class="module-header">
<button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
    <i class="fas fa-bars"></i>
</button>
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="h3 mb-2">Works & Construction Management</h1>
                        <p class="mb-0 opacity-75">Manage projects, employees, and construction materials</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-light me-2" data-bs-toggle="modal" data-bs-target="#newProjectModal">
                            <i class="fas fa-plus-circle me-2"></i>New Project
                        </button>
                        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#dailyReportModal">
                            <i class="fas fa-clipboard-list me-2"></i>Daily Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-action-grid mb-4">
                <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#newProjectModal">
                    <i class="fas fa-hard-hat"></i>
                    <span>Start Project</span>
                </div>
                <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Employee</span>
                </div>
                <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                    <i class="fas fa-box"></i>
                    <span>Add Material</span>
                </div>
                <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#dailyReportModal">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Daily Report</span>
                </div>
                <div class="quick-action-btn" onclick="window.location.href='site-visits.php'">
                    <i class="fas fa-camera"></i>
                    <span>Site Visits</span>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-hard-hat"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['active_projects']; ?></h3>
                        <p class="stat-label">Active Projects</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4cc9f0, #4895ef);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['completed_projects']; ?></h3>
                        <p class="stat-label">Completed</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f8961e, #f3722c);">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['total_employees']; ?></h3>
                        <p class="stat-label">Employees</p>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #b02a37);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['low_materials']; ?></h3>
                        <p class="stat-label">Low Materials</p>
                    </div>
                </div>
            </div>

            <!-- Budget Summary -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Budget Overview</h5>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Budget:</span>
                                <strong><?php echo format_money($stats['total_budget']); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Actual Cost:</span>
                                <strong><?php echo format_money($stats['total_cost']); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Variance:</span>
                                <strong class="<?php echo ($stats['total_budget'] - $stats['total_cost']) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo format_money($stats['total_budget'] - $stats['total_cost']); ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Upcoming Deadlines</h5>
                            <?php if ($upcoming_deadlines->num_rows > 0): ?>
                                <?php while ($deadline = $upcoming_deadlines->fetch_assoc()): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><?php echo htmlspecialchars($deadline['project_name']); ?></span>
                                        <span class="deadline-badge bg-<?php
                                                                        echo $deadline['days_left'] <= 7 ? 'danger' : ($deadline['days_left'] <= 14 ? 'warning' : 'info');
                                                                        ?> text-white">
                                            <?php echo $deadline['days_left']; ?> days left
                                        </span>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-muted mb-0">No upcoming deadlines</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="worksTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="projects-tab" data-bs-toggle="tab" data-bs-target="#projects" type="button" role="tab">
                        <i class="fas fa-hard-hat me-2"></i>Projects
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline" type="button" role="tab">
                        <i class="fas fa-chart-line me-2"></i>Timeline
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="employees-tab" data-bs-toggle="tab" data-bs-target="#employees" type="button" role="tab">
                        <i class="fas fa-users me-2"></i>Employees
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab">
                        <i class="fas fa-box me-2"></i>Materials
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                        <i class="fas fa-clipboard-list me-2"></i>Daily Reports
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="worksTabContent">
                <!-- Projects Tab -->
                <div class="tab-pane fade show active" id="projects" role="tabpanel">
                    <div class="row">
                        <?php
                        $all_projects_query = "SELECT * FROM works_projects WHERE company_id = ? ORDER BY created_at DESC";
                        $stmt = $db->prepare($all_projects_query);
                        $stmt->bind_param("i", $company_id);
                        $stmt->execute();
                        $all_projects = $stmt->get_result();

                        while ($project = $all_projects->fetch_assoc()):
                        ?>
                            <div class="col-md-6">
                                <div class="project-card">
                                    <div class="project-header">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="project-title"><?php echo htmlspecialchars($project['project_name']); ?></h5>
                                                <p class="project-meta">
                                                    <span><i class="fas fa-code"></i> <?php echo $project['project_code']; ?></span>
                                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($project['location']); ?></span>
                                                </p>
                                            </div>
                                            <span class="badge bg-<?php
                                                                    echo $project['status'] == 'In Progress' ? 'primary' : ($project['status'] == 'Completed' ? 'success' : ($project['status'] == 'On Hold' ? 'warning' : 'secondary'));
                                                                    ?>">
                                                <?php echo $project['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="project-body">
                                        <div class="progress-section">
                                            <div class="progress-label">
                                                <span>Progress</span>
                                                <span><?php echo $project['progress_percentage']; ?>%</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-primary" style="width: <?php echo $project['progress_percentage']; ?>%"></div>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <small class="text-muted d-block">Start Date</small>
                                                <strong><?php echo format_date($project['start_date']); ?></strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">End Date</small>
                                                <strong><?php echo $project['end_date'] ? format_date($project['end_date']) : 'TBD'; ?></strong>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <small class="text-muted d-block">Budget</small>
                                                <strong><?php echo format_money($project['budget']); ?></strong>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted d-block">Actual Cost</small>
                                                <strong><?php echo format_money($project['actual_cost']); ?></strong>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-primary" onclick="viewProject(<?php echo $project['project_id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-sm btn-success" onclick="updateProgress(<?php echo $project['project_id']; ?>)">
                                                <i class="fas fa-chart-line"></i> Update
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="dailyReport(<?php echo $project['project_id']; ?>)">
                                                <i class="fas fa-clipboard-list"></i> Report
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Timeline Tab -->
                <div class="tab-pane fade" id="timeline" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Project Timeline</h5>
                            <h7 id="projectName"></h7>
                        </div>
                        <div class="card-body">
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>

                <!-- Employees Tab -->
                <div class="tab-pane fade" id="employees" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Employees</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                <i class="fas fa-plus-circle me-2"></i>Add Employee
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="employeesTable">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Name</th>
                                            <th>Position</th>
                                            <th>Department</th>
                                            <th>Phone</th>
                                            <th>Hire Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $employees_query = "SELECT * FROM works_employees ORDER BY full_name ASC";
                                        $employees = $db->query($employees_query);
                                        while ($emp = $employees->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><?php echo $emp['employee_code']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="employee-avatar me-2">
                                                            <?php echo get_avatar_letter($emp['full_name']); ?>
                                                        </div>
                                                        <?php echo htmlspecialchars($emp['full_name']); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo $emp['position']; ?></td>
                                                <td><?php echo $emp['department']; ?></td>
                                                <td><?php echo $emp['phone']; ?></td>
                                                <td><?php echo format_date($emp['hire_date']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $emp['status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo $emp['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewEmployee(<?php echo $emp['employee_id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="editEmployee(<?php echo $emp['employee_id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="assignToProject(<?php echo $emp['employee_id']; ?>)">
                                                        <i class="fas fa-tasks"></i>
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

                <!-- Materials Tab -->
                <div class="tab-pane fade" id="materials" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Materials Inventory</h5>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                                        <i class="fas fa-plus-circle me-2"></i>Add Material
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="materialsTable">
                                            <thead>
                                                <tr>
                                                    <th>Code</th>
                                                    <th>Material Name</th>
                                                    <th>Category</th>
                                                    <th>Unit</th>
                                                    <th>Stock</th>
                                                    <th>Unit Cost</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $materials_query = "SELECT * FROM procurement_products WHERE category = 'Building Materials' ORDER BY product_name ASC";
                                                $materials = $db->query($materials_query);
                                                while ($mat = $materials->fetch_assoc()):
                                                ?>
                                                    <tr>
                                                        <td><?php echo $mat['product_code']; ?></td>
                                                        <td><?php echo htmlspecialchars($mat['product_name']); ?></td>
                                                        <td><?php echo $mat['category']; ?></td>
                                                        <td><?php echo $mat['unit']; ?></td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <span class="<?php echo $mat['current_stock'] <= $mat['minimum_stock'] ? 'text-danger fw-bold' : ''; ?>">
                                                                    <?php echo $mat['current_stock']; ?>
                                                                </span>
                                                                <?php if ($mat['current_stock'] <= $mat['minimum_stock']): ?>
                                                                    <i class="fas fa-exclamation-circle text-danger ms-2"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td><?php echo format_money($mat['unit_price']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php
                                                                                    echo $mat['status'] == 'Available' ? 'success' : ($mat['status'] == 'Low Stock' ? 'warning' : 'danger');
                                                                                    ?>">
                                                                <?php echo $mat['status']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-info" onclick="viewMaterial(<?php echo $mat['product_id']; ?>)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-primary" onclick="editMaterial(<?php echo $mat['product_id']; ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-success" onclick="issueMaterial(<?php echo $mat['product_id']; ?>)">
                                                                <i class="fas fa-arrow-right"></i>
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

                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Low Stock Alert</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $low_stock_query = "SELECT * FROM procurement_products WHERE category = 'Building Materials' AND current_stock <= minimum_stock";
                                    $low_stock = $db->query($low_stock_query);
                                    if ($low_stock->num_rows > 0):
                                        while ($material = $low_stock->fetch_assoc()):
                                    ?>
                                            <div class="material-alert">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($material['product_name']); ?></strong>
                                                        <br>
                                                        <small>Current: <?php echo $material['current_stock']; ?> <?php echo $material['unit']; ?></small>
                                                        <br>
                                                        <small>Min: <?php echo $material['minimum_stock']; ?> <?php echo $material['unit']; ?></small>
                                                    </div>
                                                    <button class="btn btn-sm btn-warning" onclick="orderMaterial(<?php echo $material['product_id']; ?>)">
                                                        Order
                                                    </button>
                                                </div>
                                            </div>
                                        <?php
                                        endwhile;
                                    else:
                                        ?>
                                        <p class="text-muted text-center">No low stock items</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="card mt-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Material Usage Today</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $usage_query = "SELECT pm.*, m.product_name, p.project_name, m.unit
                                                   FROM works_project_materials pm
                                                   JOIN procurement_products m ON pm.material_id = m.product_id
                                                   JOIN works_projects p ON pm.project_id = p.project_id
                                                   WHERE DATE(pm.date_used) = CURDATE()
                                                   ORDER BY pm.created_at DESC LIMIT 5";
                                    $usage = $db->query($usage_query);
                                    if ($usage->num_rows > 0):
                                        while ($use = $usage->fetch_assoc()):
                                    ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($use['product_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo $use['project_name']; ?></small>
                                                </div>
                                                <span class="badge bg-info">
                                                    <?php echo $use['quantity']; ?> <?php echo $use['unit']; ?>
                                                </span>
                                            </div>
                                        <?php
                                        endwhile;
                                    else:
                                        ?>
                                        <p class="text-muted text-center">No usage recorded today</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Reports Tab -->
                <div class="tab-pane fade" id="reports" role="tabpanel">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Daily Reports</h5>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#dailyReportModal">
                                <i class="fas fa-plus-circle me-2"></i>New Report
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if ($recent_reports->num_rows > 0): ?>
                                <?php while ($report = $recent_reports->fetch_assoc()): ?>
                                    <div class="report-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($report['project_name']); ?></h6>
                                                <p class="mb-1 text-muted small">
                                                    <i class="fas fa-user me-1"></i> <?php echo $report['reporter_name']; ?>
                                                    <i class="fas fa-clock ms-2 me-1"></i> <?php echo timeAgo($report['created_at']); ?>
                                                </p>
                                            </div>
                                            <span class="badge bg-<?php echo $report['status'] == 'Approved' ? 'success' : 'warning'; ?>">
                                                <?php echo $report['status']; ?>
                                            </span>
                                        </div>
                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars(substr($report['work_description'], 0, 150))); ?>...</p>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewReport(<?php echo $report['report_id']; ?>)">
                                                Read More
                                            </button>
                                            <?php if ($report['photos']): ?>
                                                <button class="btn btn-sm btn-outline-info" onclick="viewPhotos('<?php echo $report['photos']; ?>')">
                                                    <i class="fas fa-images"></i> Photos
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-muted text-center py-4">No daily reports found</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Project Modal -->
    <div class="modal fade" id="newProjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Start New Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/new-project.php" method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Project Code</label>
                                <input type="text" class="form-control" name="project_code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Project Name</label>
                                <input type="text" class="form-control" name="project_name" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Project Type</label>
                                <select class="form-control" name="project_type" required>
                                    <option value="Construction">Construction</option>
                                    <option value="Renovation">Renovation</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Infrastructure">Infrastructure</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Client Name</label>
                                <input type="text" class="form-control" name="client_name">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date (Estimated)</label>
                                <input type="date" class="form-control" name="end_date">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Budget</label>
                                <input type="number" step="0.01" class="form-control" name="budget" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contingency</label>
                                <input type="number" step="0.01" class="form-control" name="contingency" value="0">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Project Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Project Manager</label>
                            <select class="form-control" name="project_manager">
                                <option value="">Select Manager</option>
                                <?php
                                $users = $db->query("SELECT user_id, full_name FROM users WHERE company_id = $company_id AND role IN ('Manager', 'CompanyAdmin')");
                                while ($user = $users->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $user['user_id']; ?>"><?php echo $user['full_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/add-employee.php" method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Employee Code</label>
                                <input type="text" class="form-control" name="employee_code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ID Number</label>
                                <input type="text" class="form-control" name="id_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Position</label>
                                <input type="text" class="form-control" name="position" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <input type="text" class="form-control" name="department">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Hire Date</label>
                                <input type="date" class="form-control" name="hire_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contract Type</label>
                                <select class="form-control" name="contract_type">
                                    <option value="Permanent">Permanent</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Temporary">Temporary</option>
                                    <option value="Casual">Casual</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Hourly Rate</label>
                                <input type="number" step="0.01" class="form-control" name="hourly_rate">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Daily Rate</label>
                                <input type="number" step="0.01" class="form-control" name="daily_rate">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Monthly Salary</label>
                                <input type="number" step="0.01" class="form-control" name="monthly_salary">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Contact</label>
                                <input type="text" class="form-control" name="emergency_contact">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Emergency Phone</label>
                                <input type="text" class="form-control" name="emergency_phone">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Material Modal -->
    <div class="modal fade" id="addMaterialModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/add-material.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Material Code</label>
                            <input type="text" class="form-control" name="material_code" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Material Name</label>
                            <input type="text" class="form-control" name="material_name" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-control" name="category" required>
                                    <option value="Cement">Cement</option>
                                    <option value="Sand">Sand</option>
                                    <option value="Aggregate">Aggregate</option>
                                    <option value="Steel">Steel</option>
                                    <option value="Timber">Timber</option>
                                    <option value="Electrical">Electrical</option>
                                    <option value="Plumbing">Plumbing</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unit</label>
                                <select class="form-control" name="unit" required>
                                    <option value="bags">Bags</option>
                                    <option value="tons">Tons</option>
                                    <option value="kg">Kilograms</option>
                                    <option value="pieces">Pieces</option>
                                    <option value="meters">Meters</option>
                                    <option value="liters">Liters</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Unit Cost</label>
                                <input type="number" step="0.01" class="form-control" name="unit_cost" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Current Stock</label>
                                <input type="number" step="0.01" class="form-control" name="current_stock" value="0">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Minimum Stock</label>
                                <input type="number" step="0.01" class="form-control" name="minimum_stock" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier</label>
                                <input type="text" class="form-control" name="supplier">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Material</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Daily Report Modal -->
    <div class="modal fade" id="dailyReportModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Daily Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/daily-report.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Project</label>
                            <select class="form-control" name="project_id" required>
                                <option value="">Select Project</option>
                                <?php
                                $projects = $db->query("SELECT project_id, project_name FROM works_projects WHERE company_id = $company_id AND status = 'In Progress'");
                                while ($proj = $projects->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $proj['project_id']; ?>"><?php echo $proj['project_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Report Date</label>
                                <input type="date" class="form-control" name="report_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Weather Conditions</label>
                                <select class="form-control" name="weather_conditions">
                                    <option value="Sunny">Sunny</option>
                                    <option value="Cloudy">Cloudy</option>
                                    <option value="Rainy">Rainy</option>
                                    <option value="Windy">Windy</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Work Description</label>
                            <textarea class="form-control" name="work_description" rows="4" required></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Employees Present</label>
                                <input type="number" class="form-control" name="employees_present" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Hours Worked</label>
                                <input type="number" step="0.5" class="form-control" name="hours_worked" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Materials Used</label>
                            <div id="materialsUsed">
                                <div class="row mb-2">
                                    <div class="col-md-5">
                                        <select class="form-control" name="materials[0][id]">
                                            <option value="">Select Material</option>
                                            <?php
                                            $mats = $db->query("SELECT product_id, product_name FROM procurement_products WHERE category = 'Building Materials' ORDER BY product_name ASC");
                                            while ($mat = $mats->fetch_assoc()):
                                            ?>
                                                <option value="<?php echo $mat['product_id']; ?>"><?php echo $mat['product_name']; ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" step="0.01" class="form-control" name="materials[0][quantity]" placeholder="Quantity">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-success btn-sm" onclick="addMaterialField()">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Equipment Used</label>
                            <textarea class="form-control" name="equipment_used" rows="2" placeholder="List equipment used today"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Challenges</label>
                                <textarea class="form-control" name="challenges" rows="2"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Achievements</label>
                                <textarea class="form-control" name="achievements" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Plan for Tomorrow</label>
                            <textarea class="form-control" name="next_plan" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Site Photos</label>
                            <input type="file" class="form-control" name="photos[]" multiple accept="image/*">
                            <small class="text-muted">Upload photos of the site work</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Report</button>
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
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js'></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#employeesTable').DataTable();
            $('#materialsTable').DataTable();

            // Initialize FullCalendar
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: 'ajax/get-project-events.php',
                eventClick: function(info) {
                    window.location.href = 'view-project.php?id=' + info.event.id;
                }
            });
            calendar.render();
        });

        let materialCount = 1;

        function addMaterialField() {
            let html = `
                <div class="row mb-2">
                    <div class="col-md-5">
                        <select class="form-control" name="materials[${materialCount}][id]">
                            <option value="">Select Material</option>
                            <?php
                            $mats = $db->query("SELECT product_id, product_name FROM procurement_products WHERE category = 'Building Materials' ORDER BY product_name ASC");
                            while ($mat = $mats->fetch_assoc()):
                            ?>
                            <option value="<?php echo $mat['product_id']; ?>"><?php echo $mat['product_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="number" step="0.01" class="form-control" name="materials[${materialCount}][quantity]" placeholder="Quantity">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.row').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            $('#materialsUsed').append(html);
            materialCount++;
        }

        function viewProject(id) {
            window.location.href = 'view-project.php?id=' + id;
        }

        function updateProgress(id) {
            let progress = prompt('Enter new progress percentage (0-100):');
            if (progress !== null) {
                $.post('ajax/update-progress.php', {
                    project_id: id,
                    progress: progress
                }, function() {
                    location.reload();
                });
            }
        }

        function dailyReport(projectId) {
            $('#dailyReportModal select[name="project_id"]').val(projectId);
            $('#dailyReportModal').modal('show');
        }

        function viewEmployee(id) {
            window.location.href = 'view-employee.php?id=' + id;
        }

        function editEmployee(id) {
            window.location.href = 'edit-employee.php?id=' + id;
        }

        function assignToProject(employeeId) {
            // Open assignment modal
            $('#assignEmployeeModal select[name="employee_id"]').val(employeeId);
            $('#assignEmployeeModal').modal('show');
        }

        function viewMaterial(id) {
            window.location.href = 'view-material.php?id=' + id;
        }

        function editMaterial(id) {
            window.location.href = 'edit-material.php?id=' + id;
        }

        function issueMaterial(id) {
            window.location.href = 'issue-material.php?id=' + id;
        }

        function orderMaterial(id) {
            window.location.href = '../procurement/create-po.php?material=' + id;
        }

        function viewReport(id) {
            window.location.href = 'view-report.php?id=' + id;
        }

        function viewPhotos(photos) {
            // Open photo gallery modal
            $('#photoGallery').modal('show');
        }
    </script>
</body>

</html>