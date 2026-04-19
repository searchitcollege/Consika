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

if (empty($company_id) || $company_id == null) {
    $result = $db->query("SELECT company_id FROM companies WHERE company_type = 'Works' LIMIT 1");
    $row = $result->fetch_assoc();
    $company_id = (int)($row['company_id'] ?? 0);
}

if (empty($company_id)) {
    $_SESSION['error'] = 'Works company not found.';
    header('Location: ../../admin/dashboard.php');
    exit();
}

if (isset($_POST['action']) && $_POST['action'] === 'update_progress') {
    $project_id = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
    $progress   = isset($_POST['progress'])   ? (int) $_POST['progress']   : -1;

    if ($project_id <= 0 || $progress < 0 || $progress > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }

    // Porgress
    $stmt = $db->prepare("
        UPDATE works_projects
        SET    progress_percentage = ?,
               updated_at          = NOW()
        WHERE  project_id = ?
    ");
    $stmt->bind_param('ii', $progress, $project_id);

    if ($stmt->execute() && $stmt->affected_rows >= 0) {
        // Also insert a progress history record
        $user_id = $_SESSION['user_id'];
        $today   = date('Y-m-d');
        $note    = "Progress manually updated to {$progress}%";

        $stmt2 = $db->prepare("
            INSERT INTO works_project_progress
                (project_id, report_date, completion_percentage, description, reported_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt2->bind_param('isisi', $project_id, $today, $progress, $note, $user_id);
        $stmt2->execute(); // non-fatal if this fails

        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update progress']);
    }
    exit;
}

// ============================================================
// CREATE PURCHASE ORDER
// ============================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'add' &&
    ($_POST['form']   ?? '') === 'po'
) {

    $supplier_id       = (int)($_POST['supplier_id']       ?? 0);
    $order_date        = trim($_POST['order_date']         ?? '');
    $expected_delivery = trim($_POST['expected_delivery']  ?? '') ?: null;
    $notes             = trim($_POST['notes']              ?? '') ?: null;
    $items             = $_POST['items'] ?? [];
    $created_by        = (int)$current_user['user_id'];

    if (!$supplier_id || empty($order_date) || empty($items)) {
        $_SESSION['error'] = 'Please fill required fields and add at least one item.';
        header('Location: index.php');
        exit();
    }

    // ── Validate supplier belongs to this company ────────────────────────────
    $sup_check = $db->prepare("SELECT supplier_id FROM procurement_suppliers WHERE supplier_id = ? AND company_id = ?");
    $sup_check->bind_param("ii", $supplier_id, $company_id);
    $sup_check->execute();
    $sup_check->store_result();

    if ($sup_check->num_rows === 0) {
        $_SESSION['error'] = 'Invalid supplier selected.';
        header('Location: index.php');
        exit();
    }
    $sup_check->close();

    // ── Calculate totals ─────────────────────────────────────────────────────
    $subtotal = 0;
    $valid_items = [];

    foreach ($items as $item) {
        if (empty($item['product_id'])) continue;

        $quantity = (int)($item['quantity']   ?? 0);
        $price    = (float)($item['unit_price'] ?? 0);
        $discount = (float)($item['discount']   ?? 0);

        if ($quantity <= 0 || $price <= 0) continue;

        $line_total  = ($quantity * $price) - $discount;
        $subtotal   += $line_total;

        $valid_items[] = [
            'product_id' => (int)$item['product_id'],
            'quantity'   => $quantity,
            'price'      => $price,
            'discount'   => $discount,
            'line_total' => $line_total,
        ];
    }

    if (empty($valid_items)) {
        $_SESSION['error'] = 'Please add at least one valid item.';
        header('Location: index.php');
        exit();
    }

    $tax_amount   = $subtotal * 0.16;
    $total_amount = $subtotal + $tax_amount;

    // ── Generate unique PO number ─────────────────────────────────────────────
    $po_number = 'PO-' . date('Y') . '-' . rand(1000, 9999);

    $po_check = $db->prepare("SELECT po_id FROM procurement_purchase_orders WHERE po_number = ?");
    $po_check->bind_param("s", $po_number);
    $po_check->execute();
    $po_check->store_result();
    if ($po_check->num_rows > 0) {
        $po_number = 'PO-' . date('Y') . '-' . rand(1000, 9999) . rand(0, 9);
    }
    $po_check->close();

    $delivery_status = 'Pending';
    $payment_status  = 'Unpaid';
    $approval_status = 'Pending';

    // ── Insert PO header ──────────────────────────────────────────────────────
    $stmt = $db->prepare("
        INSERT INTO procurement_purchase_orders
            (po_number, supplier_id, order_date, expected_delivery, notes,
             delivery_status, payment_status, total_amount, approval_status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sisssssdsi",
        $po_number,
        $supplier_id,
        $order_date,
        $expected_delivery,
        $notes,
        $delivery_status,
        $payment_status,
        $total_amount,
        $approval_status,
        $created_by
    );

    if (!$stmt->execute()) {
        $_SESSION['error'] = 'Failed to create purchase order.';
        header('Location: index.php');
        exit();
    }
    $stmt->close();

    // ── Retrieve new po_id ────────────────────────────────────────────────────
    $id_result = $db->query("SELECT LAST_INSERT_ID() AS new_id");
    $po_id     = (int)$id_result->fetch_assoc()['new_id'];

    // ── Insert PO items ───────────────────────────────────────────────────────
    foreach ($valid_items as $item) {
        $stmt_item = $db->prepare("
            INSERT INTO procurement_po_items
                (po_id, product_id, quantity, unit_price, discount, total_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt_item->bind_param(
            "iiiddd",
            $po_id,
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $item['discount'],
            $item['line_total']
        );
        $stmt_item->execute();
        $stmt_item->close();
    }

    // ── Activity log ──────────────────────────────────────────────────────────
    $log_desc = "Created purchase order {$po_number} for supplier ID {$supplier_id}";
    $log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';

    $log = $db->prepare("
        INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
        VALUES (?, 'Create PO', ?, ?, 'procurement', ?)
    ");
    $log->bind_param("issi", $created_by, $log_desc, $log_ip, $po_id);
    $log->execute();
    $log->close();

    $_SESSION['success'] = "Purchase Order {$po_number} created successfully.";
    header('Location: index.php');
    exit();
}

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

// All reports for the list (limit 50 — add pagination later if needed)
$reports_stmt = $db->prepare("
    SELECT
        dr.report_id,
        dr.report_date,
        dr.status,
        dr.weather_conditions,
        dr.employees_present,
        dr.hours_worked,
        dr.work_description,
        dr.photos,
        dr.created_at,
        p.project_name,
        p.project_code,
        u.full_name AS reporter_name
    FROM  works_daily_reports dr
    JOIN  works_projects p ON p.project_id  = dr.project_id
    LEFT  JOIN users     u ON u.user_id     = dr.submitted_by
    WHERE p.company_id = ?
    ORDER BY dr.report_date DESC, dr.created_at DESC
");
$reports_stmt->bind_param('i', $company_id);
$reports_stmt->execute();
$reports = $reports_stmt->get_result();
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
            <div class="department-header">
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
                <div class="quick-action-btn" data-bs-toggle="modal" data-bs-target="#addProductModal">
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
                        $all_projects_query = "SELECT * FROM works_projects ORDER BY created_at DESC";
                        $stmt = $db->prepare($all_projects_query);
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
                                            <button
                                                class="btn btn-sm <?php if ($project['status'] === 'In Progress' or $project['status'] === 'On Hold' or $project['status'] === 'Completed') {
                                                                        echo 'btn-info';
                                                                    } ?>"
                                                onclick="<?php if ($project['status'] === 'In Progress' or $project['status'] === 'On Hold' or $project['status'] === 'Completed') {
                                                                echo 'dailyReport(' . $project['project_id'] . ')';
                                                            } ?>"> <i class="fas fa-clipboard-list"></i> Report
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
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
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
                                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#createPOModal">
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
                    <!-- Reports list -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">All Daily Reports</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if ($reports->num_rows > 0): ?>
                                <div class="list-group list-group-flush" id="reportsList">
                                    <?php while ($r = $reports->fetch_assoc()):
                                        $has_photos = !empty($r['photos']);
                                        $status_color = $r['status'] === 'Approved' ? 'success'
                                            : ($r['status'] === 'Submitted' ? 'primary' : 'secondary');
                                        $preview = htmlspecialchars(substr($r['work_description'], 0, 160));
                                        if (strlen($r['work_description']) > 160) $preview .= '…';
                                    ?>
                                        <div class="list-group-item list-group-item-action px-4 py-3">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <div>
                                                    <!-- Project name + code -->
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($r['project_name']); ?></span>
                                                    <span class="text-muted ms-2 small"><?php echo $r['project_code']; ?></span>
                                                </div>
                                                <span class="badge bg-<?php echo $status_color; ?> ms-2 flex-shrink-0">
                                                    <?php echo $r['status']; ?>
                                                </span>
                                            </div>

                                            <!-- Meta row: reporter, date, weather, staff, hours -->
                                            <div class="text-muted small mb-2 d-flex flex-wrap gap-3">
                                                <span><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($r['reporter_name'] ?? '—'); ?></span>
                                                <span><i class="fas fa-calendar me-1"></i><?php echo date('d M Y', strtotime($r['report_date'])); ?></span>
                                                <?php if ($r['weather_conditions']): ?>
                                                    <span><i class="fas fa-cloud-sun me-1"></i><?php echo htmlspecialchars($r['weather_conditions']); ?></span>
                                                <?php endif; ?>
                                                <?php if ($r['employees_present']): ?>
                                                    <span><i class="fas fa-hard-hat me-1"></i><?php echo $r['employees_present']; ?> workers</span>
                                                <?php endif; ?>
                                                <?php if ($r['hours_worked']): ?>
                                                    <span><i class="fas fa-clock me-1"></i><?php echo $r['hours_worked']; ?> hrs</span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Description preview -->
                                            <p class="mb-2 small"><?php echo $preview; ?></p>

                                            <!-- Action buttons -->
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-primary"
                                                    onclick="viewReport(<?php echo $r['report_id']; ?>)">
                                                    <i class="fas fa-eye me-1"></i>View Full Report
                                                </button>
                                                <?php if ($has_photos): ?>
                                                    <button class="btn btn-sm btn-outline-info"
                                                        onclick="viewReport(<?php echo $r['report_id']; ?>, true)">
                                                        <i class="fas fa-images me-1"></i>Photos
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-5">No daily reports found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Purchase Order Modal -->
    <div class="modal fade" id="createPOModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Purchase Order</h5>
                    <button type="button" class="btn-close"></button>
                </div>
                <form action="index.php" method="POST" id="poForm">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="form" value="po">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Supplier</label>
                                <select class="form-control select2" name="supplier_id" required>
                                    <option value="">Select Supplier</option>
                                    <?php
                                    $supplierss = $db->query("SELECT supplier_id, supplier_name FROM procurement_suppliers WHERE status = 'Active'");
                                    while ($sup = $supplierss->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $sup['supplier_id']; ?>"><?php echo $sup['supplier_name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Order Date</label>
                                <input type="date" class="form-control" name="order_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Expected Delivery</label>
                                <input type="date" class="form-control" name="expected_delivery">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Shipping Address</label>
                            <textarea class="form-control" name="shipping_address" rows="2">Same as company address</textarea>
                        </div>

                        <h6 class="mt-4 mb-3">Order Items</h6>
                        <table class="table" id="poItemsTable">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Discount</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="poItems">
                                <tr class="po-item">
                                    <td>
                                        <select class="form-control product-select" name="items[0][product_id]" required>
                                            <option value="">Select Product</option>
                                            <?php
                                            $products = $db->query("SELECT product_id, product_name, unit_price FROM procurement_products WHERE status = 'Active'");
                                            while ($prod = $products->fetch_assoc()):
                                            ?>
                                                <option value="<?php echo $prod['product_id']; ?>" data-price="<?php echo $prod['unit_price']; ?>">
                                                    <?php echo $prod['product_name']; ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control quantity" name="items[0][quantity]" required min="1">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control unit-price" name="items[0][unit_price]" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" class="form-control discount" name="items[0][discount]" value="0">
                                    </td>
                                    <td class="item-total">0.00</td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm remove-item" disabled>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6">
                                        <button type="button" class="btn btn-sm btn-primary" id="addItem">
                                            <i class="fas fa-plus"></i> Add Item
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                    <td><strong id="subtotal">0.00</strong></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end">Tax (16%):</td>
                                    <td id="tax">0.00</td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                    <td><strong id="grand-total">0.00</strong></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Purchase Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View entityb modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                                $users = $db->query("SELECT user_id, full_name FROM users WHERE role IN ('Manager', 'CompanyAdmin', 'SuperAdmin') ORDER BY full_name ASC");
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

    <!-- TODO: view modal which is actually essentially timelines  -->
    <div class="modal fade" id="viewProjectModal" tabindex="-1" aria-labelledby="viewProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewProjectModalLabel">
                        <span id="modalProjectCode" class="badge bg-secondary me-2"></span>
                        <span id="modalProjectName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <!-- Loading spinner -->
                    <div id="projectModalSpinner" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Loading project details…</p>
                    </div>

                    <!-- Error state -->
                    <div id="projectModalError" class="alert alert-danger d-none"></div>

                    <!-- Content -->
                    <div id="projectModalContent" class="d-none">
                        <!-- Row 1: key stats -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="card border-0 bg-light h-100">
                                    <div class="card-body">
                                        <div class="text-muted small">Status</div>
                                        <div id="modalStatus"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 bg-light h-100">
                                    <div class="card-body">
                                        <div class="text-muted small">Progress</div>
                                        <div class="fw-bold fs-4" id="modalProgress"></div>
                                        <div class="progress mt-1" style="height:6px">
                                            <div id="modalProgressBar" class="progress-bar" role="progressbar"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 bg-light h-100">
                                    <div class="card-body">
                                        <div class="text-muted small">Budget</div>
                                        <div class="fw-bold" id="modalBudget"></div>
                                        <div class="text-muted small">Spent: <span id="modalSpent"></span></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 bg-light h-100">
                                    <div class="card-body">
                                        <div class="text-muted small">Timeline</div>
                                        <div class="small" id="modalTimeline"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: details + assignments -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold">Project Details</div>
                                    <div class="card-body small">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th class="text-muted" style="width:35%">Type</th>
                                                <td id="modalType"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Location</th>
                                                <td id="modalLocation"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Client</th>
                                                <td id="modalClient"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Manager</th>
                                                <td id="modalManager"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Supervisor</th>
                                                <td id="modalSupervisor"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Company</th>
                                                <td id="modalCompany"></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold">
                                        Assigned Employees
                                        <span class="badge bg-primary ms-1" id="modalAssignmentCount">0</span>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive" style="max-height:180px">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Role</th>
                                                        <th>Since</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="modalAssignments"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: materials + daily reports table -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold">Recent Material Usage</div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive" style="max-height:180px">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th>Material</th>
                                                        <th>Qty</th>
                                                        <th>Cost</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="modalMaterials"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold">Recent Daily Reports</div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive" style="max-height:180px">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Description</th>
                                                        <th>Staff</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="modalDailyReports"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div id="modalDescriptionWrapper" class="d-none mb-4">
                            <div class="card">
                                <div class="card-header fw-semibold">Description</div>
                                <div class="card-body small" id="modalDescription"></div>
                            </div>
                        </div>

                        <!-- ── Activity Timeline ──────────────────────────────────────── -->
                        <div class="card">
                            <div class="card-header fw-semibold d-flex align-items-center justify-content-between">
                                <span><i class="fas fa-stream me-2 text-primary"></i>Activity Timeline</span>
                                <span class="badge bg-light text-dark border" id="modalTimelineCount">0 entries</span>
                            </div>
                            <div class="card-body" style="max-height:320px; overflow-y:auto; padding: 0 1rem;">
                                <div id="modalActivityTimeline" class="py-3">
                                    <!-- populated by JS -->
                                </div>
                            </div>
                        </div>

                    </div><!-- /projectModalContent -->
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
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
                <form action="../../api/add-employee.php" method="POST">
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
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
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

    <!-- Daily Report Modal -->
    <div class="modal fade" id="dailyReportModal" tabindex="-1" aria-labelledby="dailyReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="dailyReportModalLabel">
                        <i class="fas fa-clipboard-list me-2 text-primary"></i>Submit Daily Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <!-- Alert area for success/error feedback -->
                    <div id="dailyReportAlert" class="d-none mb-3"></div>

                    <!-- Project + Date + Weather -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Project <span class="text-danger">*</span></label>
                            <!-- id="dailyReportProjectId" lets dailyReport(id) pre-select the project -->
                            <select class="form-select" name="project_id" id="dailyReportProjectId" required>
                                <option value="">Select Project…</option>
                                <?php
                                $projects = $db->query("
                                SELECT project_id, project_name
                                FROM   works_projects
                                WHERE   status = 'In Progress'
                                ORDER  BY project_name
                            ");
                                while ($proj = $projects->fetch_assoc()):
                                ?>
                                    <option value="<?= $proj['project_id'] ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Report Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="report_date"
                                value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Weather Conditions</label>
                            <select class="form-select" name="weather_conditions">
                                <option value="Sunny">☀️ Sunny</option>
                                <option value="Cloudy">⛅ Cloudy</option>
                                <option value="Rainy">🌧️ Rainy</option>
                                <option value="Windy">💨 Windy</option>
                                <option value="Overcast">🌥️ Overcast</option>
                            </select>
                        </div>
                    </div>

                    <!-- Work Description -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Work Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="work_description" rows="3"
                            placeholder="Describe the work carried out today…" required></textarea>
                    </div>

                    <!-- Staff & Hours -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Employees Present <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="employees_present"
                                min="0" placeholder="e.g. 12" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Hours Worked <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" class="form-control" name="hours_worked"
                                min="0" max="24" placeholder="e.g. 8" required>
                        </div>
                    </div>

                    <!-- Materials Used -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-semibold mb-0">Materials Used</label>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="addMaterialRow()">
                                <i class="fas fa-plus me-1"></i>Add Row
                            </button>
                        </div>
                        <div id="materialsContainer">
                            <!-- first row injected by addMaterialRow() on modal open -->
                        </div>
                        <div class="text-muted small mt-1">Leave blank if no materials were used today.</div>
                    </div>

                    <!-- Equipment -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Equipment Used</label>
                        <textarea class="form-control" name="equipment_used" rows="2"
                            placeholder="e.g. Excavator, Concrete mixer, Scaffolding…"></textarea>
                    </div>

                    <!-- Challenges & Achievements -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Challenges</label>
                            <textarea class="form-control" name="challenges" rows="2"
                                placeholder="Any issues or blockers encountered…"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Achievements</label>
                            <textarea class="form-control" name="achievements" rows="2"
                                placeholder="Milestones or notable progress…"></textarea>
                        </div>
                    </div>

                    <!-- Plan for Tomorrow -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Plan for Tomorrow</label>
                        <textarea class="form-control" name="next_plan" rows="2"
                            placeholder="What is planned for the next working day…"></textarea>
                    </div>

                    <!-- Site Photos -->
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Site Photos</label>
                        <input type="file" class="form-control" name="photos[]" id="dailyReportPhotos"
                            multiple accept="image/*">
                        <div class="text-muted small mt-1">
                            <i class="fas fa-info-circle me-1"></i>
                            You can select multiple photos. Max 5 MB each.
                        </div>
                        <!-- Preview thumbnails -->
                        <div id="photoPreviewRow" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </div>

                </div><!-- /modal-body -->

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submitDailyReportBtn">
                        <span id="submitDailyReportSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                        Submit Report
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- View Report Modal -->
    <div class="modal fade" id="viewReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2 text-primary"></i>
                        <span id="vrProjectName"></span>
                        <span id="vrProjectCode" class="badge bg-secondary ms-2 fw-normal"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <!-- Loading spinner -->
                    <div id="vrSpinner" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Loading report…</p>
                    </div>

                    <!-- Error state -->
                    <div id="vrError" class="alert alert-danger d-none"></div>

                    <!-- Content (shown after load) -->
                    <div id="vrContent" class="d-none">

                        <!-- Row 1: stat pills -->
                        <div class="row g-3 mb-4">
                            <div class="col-6 col-md-3">
                                <div class="card border-0 bg-light text-center h-100">
                                    <div class="card-body py-3">
                                        <div class="text-muted small mb-1">Date</div>
                                        <div class="fw-semibold" id="vrDate"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card border-0 bg-light text-center h-100">
                                    <div class="card-body py-3">
                                        <div class="text-muted small mb-1">Status</div>
                                        <div id="vrStatus"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card border-0 bg-light text-center h-100">
                                    <div class="card-body py-3">
                                        <div class="text-muted small mb-1">Workers</div>
                                        <div class="fw-semibold fs-5" id="vrWorkers"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card border-0 bg-light text-center h-100">
                                    <div class="card-body py-3">
                                        <div class="text-muted small mb-1">Hours</div>
                                        <div class="fw-semibold fs-5" id="vrHours"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: meta info -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold">Report Info</div>
                                    <div class="card-body small">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th class="text-muted" style="width:40%">Submitted By</th>
                                                <td id="vrSubmittedBy"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Location</th>
                                                <td id="vrLocation"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Weather</th>
                                                <td id="vrWeather"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Submitted At</th>
                                                <td id="vrCreatedAt"></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- Materials used table -->
                                <div class="card h-100">
                                    <div class="card-header fw-semibold">
                                        Materials Used
                                        <span class="badge bg-primary ms-1" id="vrMaterialCount">0</span>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive" style="max-height:160px">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th>Material</th>
                                                        <th>Qty</th>
                                                        <th>Unit Cost</th>
                                                        <th>Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="vrMaterials"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Work Description -->
                        <div class="card mb-3">
                            <div class="card-header fw-semibold">Work Description</div>
                            <div class="card-body" id="vrWorkDescription"></div>
                        </div>

                        <!-- Challenges / Achievements / Next Plan -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4" id="vrChallengesWrapper">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold text-danger">
                                        <i class="fas fa-exclamation-triangle me-1"></i>Challenges
                                    </div>
                                    <div class="card-body small" id="vrChallenges"></div>
                                </div>
                            </div>
                            <div class="col-md-4" id="vrAchievementsWrapper">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold text-success">
                                        <i class="fas fa-trophy me-1"></i>Achievements
                                    </div>
                                    <div class="card-body small" id="vrAchievements"></div>
                                </div>
                            </div>
                            <div class="col-md-4" id="vrNextPlanWrapper">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold text-info">
                                        <i class="fas fa-arrow-right me-1"></i>Plan for Tomorrow
                                    </div>
                                    <div class="card-body small" id="vrNextPlan"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Equipment -->
                        <div class="card mb-3" id="vrEquipmentWrapper">
                            <div class="card-header fw-semibold">Equipment Used</div>
                            <div class="card-body small" id="vrEquipment"></div>
                        </div>

                        <!-- Supervisor Notes -->
                        <div class="card mb-3 d-none" id="vrSupervisorWrapper">
                            <div class="card-header fw-semibold">Supervisor Notes</div>
                            <div class="card-body small" id="vrSupervisorNotes"></div>
                        </div>

                        <!-- Photos -->
                        <div class="card d-none" id="vrPhotosCard">
                            <div class="card-header fw-semibold">
                                <i class="fas fa-images me-2"></i>Site Photos
                                <span class="badge bg-secondary ms-1" id="vrPhotoCount">0</span>
                            </div>
                            <div class="card-body">
                                <div class="row g-2" id="vrPhotosGrid"></div>
                            </div>
                        </div>

                    </div><!-- /vrContent -->
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>

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
            $('#poTable').DataTable({
                order: [
                    [2, 'desc']
                ]
            });
            $('#suppliersTable').DataTable();
            $('#productsTable').DataTable();
            $('#inventoryTable').DataTable({
                order: [
                    [0, 'desc']
                ]
            });

            // Add PO item
            let itemCount = 1;
            $('#addItem').click(function() {
                let newRow = `
                    <tr class="po-item">
                        <td>
                            <select class="form-control product-select" name="items[${itemCount}][product_id]" required>
                                <option value="">Select Product</option>
                                <?php
                                $products = $db->query("SELECT product_id, product_name, unit_price FROM procurement_products WHERE status = 'Active'");
                                while ($prod = $products->fetch_assoc()):
                                ?>
                                <option value="<?php echo $prod['product_id']; ?>" data-price="<?php echo $prod['unit_price']; ?>">
                                    <?php echo $prod['product_name']; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </td>
                        <td>
                            <input type="number" class="form-control quantity" name="items[${itemCount}][quantity]" required min="1">
                        </td>
                        <td>
                            <input type="number" step="0.01" class="form-control unit-price" name="items[${itemCount}][unit_price]" required>
                        </td>
                        <td>
                            <input type="number" step="0.01" class="form-control discount" name="items[${itemCount}][discount]" value="0">
                        </td>
                        <td class="item-total">0.00</td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm remove-item">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    </tr>
                `;
                $('#poItems').append(newRow);
                itemCount++;
            });

            // Remove PO item
            $(document).on('click', '.remove-item', function() {
                if ($('.po-item').length > 1) {
                    $(this).closest('tr').remove();
                    calculateTotal();
                }
            });

            // Calculate item total
            $(document).on('change keyup', '.quantity, .unit-price, .discount, .product-select', function() {
                let row = $(this).closest('tr');
                let quantity = parseFloat(row.find('.quantity').val()) || 0;
                let price = parseFloat(row.find('.unit-price').val()) || 0;
                let discount = parseFloat(row.find('.discount').val()) || 0;

                let subtotal = quantity * price;
                let total = subtotal - discount;

                row.find('.item-total').text(formatMoney(total));
                calculateTotal();
            });

            // Set unit price when product selected
            $(document).on('change', '.product-select', function() {
                let price = $(this).find(':selected').data('price');
                $(this).closest('tr').find('.unit-price').val(price).trigger('change');
            });

            
            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#createPOModal')
            });

            function calculateTotal() {
                let subtotal = 0;
                $('.po-item').each(function() {
                    let totalText = $(this).find('.item-total').text();
                    subtotal += parseFloat(totalText.replace(/[^0-9.-]+/g, '')) || 0;
                });

                let tax = subtotal * 0.16;
                let grandTotal = subtotal + tax;

                $('#subtotal').text(formatMoney(subtotal));
                $('#tax').text(formatMoney(tax));
                $('#grand-total').text(formatMoney(grandTotal));
            }

            function formatMoney(amount) {
                return 'GHS ' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            }

            // Load PO items for receiving
            $('select[name="po_id"]').change(function() {
                let poId = $(this).val();
                if (poId) {
                    $.get('ajax/get-po-items.php', {
                        po_id: poId
                    }, function(data) {
                        $('#poItemsList').html(data);
                    });
                }
            });
        });

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

        let materialCount = 1;

        // Material row counter 
        var _matIndex = 0;

        // Build the option list once so we don't repeat inline PHP in every new row
        var _materialOptions = (function() {
            // Collect all <option> elements already rendered in the first select
            // (we'll clone from a hidden template instead — see below)
            return '';
        }());

        // Update projects progress, view project and show daily report alert and modals
        function updateProgress(id) {
            var progress = prompt('Enter new progress percentage (0-100):');
            if (progress === null) return;

            var value = parseInt(progress, 10);
            if (isNaN(value) || value < 0 || value > 100) {
                alert('Please enter a valid number between 0 and 100.');
                return;
            }

            $.post(window.location.pathname, {
                    action: 'update_progress',
                    project_id: id,
                    progress: value
                })
                .done(function(res) {
                    location.reload();
                })
                .fail(function() {
                    alert('Failed to update progress. Please try again.');
                });
        }

        // view porject with details in a modal
        function viewReport(reportId, scrollToPhotos) {
            scrollToPhotos = scrollToPhotos || false;

            // Reset modal to loading state
            $('#vrSpinner').removeClass('d-none');
            $('#vrContent').addClass('d-none');
            $('#vrError').addClass('d-none').text('');
            $('#viewReportModal').modal('show');

            $.ajax({
                    url: '../../api/get-reports-daily.php',
                    method: 'GET',
                    data: {
                        report_id: reportId
                    },
                    dataType: 'json'
                })
                .done(function(data) {
                    if (!data.success) {
                        showVrError(data.error || 'Failed to load report.');
                        return;
                    }
                    populateReportModal(data, scrollToPhotos);
                })
                .fail(function(xhr) {
                    var msg = 'Could not load report details.';
                    try {
                        var p = JSON.parse(xhr.responseText);
                        if (p.error) msg = p.error;
                    } catch (e) {}
                    showVrError(msg);
                });
        }

        function showVrError(msg) {
            $('#vrSpinner').addClass('d-none');
            $('#vrError').removeClass('d-none').text(msg);
        }

        function showModalError(msg) {
            $('#projectModalSpinner').addClass('d-none');
            $('#projectModalError').removeClass('d-none').text(msg);
        }

        function populateProjectModal(data) {
            var p = data.project;
            var bs = data.budget_summary;

            // Header
            $('#modalProjectCode').text(p.project_code || '');
            $('#modalProjectName').text(p.project_name || '');
            $('#modalViewFullBtn').attr('href', 'view-project.php?id=' + p.project_id);

            // Status badge
            var statusColors = {
                'Planning': 'bg-info',
                'In Progress': 'bg-primary',
                'On Hold': 'bg-warning text-dark',
                'Completed': 'bg-success',
                'Cancelled': 'bg-danger'
            };
            var statusClass = statusColors[p.status] || 'bg-secondary';
            $('#modalStatus').html('<span class="badge ' + statusClass + ' fs-6">' + escHtml(p.status) + '</span>');

            // Progress bar
            var pct = parseFloat(p.progress_percentage) || 0;
            $('#modalProgress').text(pct + '%');
            $('#modalProgressBar')
                .css('width', pct + '%')
                .attr('aria-valuenow', pct)
                .removeClass('bg-success bg-warning bg-danger')
                .addClass(pct >= 75 ? 'bg-success' : pct >= 40 ? 'bg-warning' : 'bg-danger');

            // Budget
            $('#modalBudget').text('GHS ' + formatNumber(bs.budget));
            $('#modalSpent').text('GHS ' + formatNumber(bs.actual_cost));

            // Timeline dates card
            $('#modalTimeline').html(
                '<span class="fw-semibold">Start:</span> ' + formatDate(p.start_date) + '<br>' +
                '<span class="fw-semibold">End:</span> ' + (p.end_date ? formatDate(p.end_date) : '—')
            );

            // Details table
            $('#modalType').text(p.project_type || '—');
            $('#modalLocation').text(p.location || '—');
            $('#modalClient').text(p.client_name || '—');
            $('#modalManager').text(
                p.manager_name ?
                p.manager_name + (p.manager_phone ? ' (' + p.manager_phone + ')' : '') :
                '—'
            );
            $('#modalSupervisor').text(p.site_supervisor || '—');
            $('#modalCompany').text(p.company_name || '—');

            // Description
            if (p.description) {
                $('#modalDescription').text(p.description);
                $('#modalDescriptionWrapper').removeClass('d-none');
            } else {
                $('#modalDescriptionWrapper').addClass('d-none');
            }

            // Assignments
            var $ta = $('#modalAssignments').empty();
            $('#modalAssignmentCount').text(data.assignments.length);
            if (data.assignments.length) {
                $.each(data.assignments, function(_, a) {
                    $ta.append(
                        '<tr><td>' + escHtml(a.employee_name) + '</td>' +
                        '<td>' + escHtml(a.role) + '</td>' +
                        '<td>' + formatDate(a.start_date) + '</td></tr>'
                    );
                });
            } else {
                $ta.append('<tr><td colspan="3" class="text-muted text-center py-2">No active assignments</td></tr>');
            }

            // Materials
            var $tm = $('#modalMaterials').empty();
            if (data.materials.length) {
                $.each(data.materials, function(_, m) {
                    $tm.append(
                        '<tr><td>' + escHtml(m.product_name) +
                        '<td>' + m.quantity +
                        ' <small class="text-muted">(' + escHtml(m.unit) + ')</small></td>' +
                        '<td>GHS ' + formatNumber(m.total_cost) + '</td>' +
                        '<td>' + formatDate(m.date_used) + '</td></tr>'
                    );
                });
            } else {
                $tm.append('<tr><td colspan="4" class="text-muted text-center py-2">No material usage recorded</td></tr>');
            }

            // Daily reports table
            var $td = $('#modalDailyReports').empty();
            if (data.daily_reports.length) {
                $.each(data.daily_reports, function(_, r) {
                    var desc = r.work_description || '';
                    var preview = desc.length > 60 ? desc.substring(0, 60) + '…' : desc;
                    var dClass = r.status === 'Approved' ?
                        'bg-success' : r.status === 'Submitted' ? 'bg-primary' : 'bg-secondary';
                    $td.append(
                        '<tr>' +
                        '<td>' + formatDate(r.report_date) + '</td>' +
                        '<td title="' + escHtml(desc) + '">' + escHtml(preview) + '</td>' +
                        '<td>' + (r.employees_present !== null ? r.employees_present : '—') + '</td>' +
                        '<td><span class="badge ' + dClass + '">' + escHtml(r.status) + '</span></td>' +
                        '</tr>'
                    );
                });
            } else {
                $td.append('<tr><td colspan="4" class="text-muted text-center py-2">No reports submitted</td></tr>');
            }

            // Activity timeline
            var entries = [];

            $.each(data.daily_reports || [], function(_, r) {
                entries.push({
                    date: r.report_date,
                    type: 'report',
                    label: 'Daily Report',
                    desc: r.work_description || '(no description)',
                    sub: (r.employees_present ? r.employees_present + ' staff · ' : '') +
                        (r.weather_conditions || ''),
                    status: r.status
                });
            });

            $.each(data.progress_history || [], function(_, h) {
                entries.push({
                    date: h.report_date,
                    type: 'progress',
                    label: 'Progress: ' + (h.completion_percentage || 0) + '%',
                    desc: h.milestone || h.description || '(no notes)',
                    sub: h.reporter_name ? 'By ' + h.reporter_name : '',
                    status: null
                });
            });

            // Sort newest first
            entries.sort(function(a, b) {
                return new Date(b.date) - new Date(a.date);
            });

            var $tl = $('#modalActivityTimeline').empty();
            $('#modalTimelineCount').text(entries.length + (entries.length === 1 ? ' entry' : ' entries'));

            if (!entries.length) {
                $tl.html(
                    '<div class="text-center text-muted py-4">' +
                    '<i class="fas fa-calendar-times fs-3 mb-2 d-block opacity-50"></i>' +
                    'No activity recorded yet</div>'
                );
            } else {
                $.each(entries, function(_, e) {
                    var isReport = e.type === 'report';
                    var dotColor = isReport ? 'bg-primary' : 'bg-success';
                    var icon = isReport ? 'fa-file-alt' : 'fa-chart-line';

                    // Status badge (only for daily reports)
                    var statusBadge = '';
                    if (isReport && e.status) {
                        var sc = e.status === 'Approved' ?
                            'bg-success' : e.status === 'Submitted' ? 'bg-primary' : 'bg-secondary';
                        statusBadge = ' <span class="badge ' + sc + ' ms-1" style="font-size:.65rem">' +
                            escHtml(e.status) + '</span>';
                    }

                    var desc = e.desc || '';
                    var preview = desc.length > 90 ? desc.substring(0, 90) + '…' : desc;

                    $tl.append(
                        '<div class="tl-item">' +
                        '<div class="tl-dot ' + dotColor + ' text-white">' +
                        '<i class="fas ' + icon + '"></i>' +
                        '</div>' +
                        '<div class="tl-body">' +
                        '<div class="d-flex align-items-center gap-2 mb-1">' +
                        '<span class="tl-date">' + formatDate(e.date) + '</span>' +
                        '<span class="badge bg-light text-dark border" style="font-size:.7rem">' +
                        escHtml(e.label) +
                        '</span>' +
                        statusBadge +
                        '</div>' +
                        '<div class="tl-desc" title="' + escHtml(desc) + '">' + escHtml(preview) + '</div>' +
                        (e.sub ? '<div class="tl-meta">' + escHtml(e.sub) + '</div>' : '') +
                        '</div>' +
                        '</div>'
                    );
                });
            }

            // Reveal
            $('#projectModalSpinner').addClass('d-none');
            $('#projectModalContent').removeClass('d-none');
        }

        function populateReportModal(data, scrollToPhotos) {
            var r = data.report;

            //  Header 
            $('#vrProjectName').text(r.project_name || '');
            $('#vrProjectCode').text(r.project_code || '');

            // Stat pills 
            $('#vrDate').text(formatDate(r.report_date));

            var sc = {
                Approved: 'bg-success',
                Submitted: 'bg-primary',
                Draft: 'bg-secondary'
            };
            $('#vrStatus').html('<span class="badge ' + (sc[r.status] || 'bg-secondary') + ' fs-6">' + escHtml(r.status) + '</span>');
            $('#vrWorkers').text(r.employees_present || '—');
            $('#vrHours').text(r.hours_worked ? r.hours_worked + ' hrs' : '—');

            // Report info table 
            $('#vrSubmittedBy').text(r.submitted_by_name || '—');
            $('#vrLocation').text(r.project_location || '—');
            $('#vrWeather').text(r.weather_conditions || '—');
            $('#vrCreatedAt').text(formatDateTime(r.created_at));

            // Materials table 
            var $matBody = $('#vrMaterials').empty();
            $('#vrMaterialCount').text(data.materials.length);
            if (data.materials.length) {
                $.each(data.materials, function(_, m) {
                    $matBody.append(
                        '<tr>' +
                        '<td>' + escHtml(m.product_name) + '</td>' +
                        '<td>' + m.quantity + '</td>' +
                        '<td>' + formatMoney(m.unit_cost) + '</td>' +
                        '<td>' + formatMoney(m.total_cost) + '</td>' +
                        '</tr>'
                    );
                });
            } else {
                $matBody.append('<tr><td colspan="4" class="text-muted text-center py-2">None recorded</td></tr>');
            }

            // Text sections 
            // Convert newlines to <br> for readability
            $('#vrWorkDescription').html(escHtml(r.work_description || '—').replace(/\n/g, '<br>'));

            setOptionalSection('#vrChallengesWrapper', '#vrChallenges', r.challenges);
            setOptionalSection('#vrAchievementsWrapper', '#vrAchievements', r.achievements);
            setOptionalSection('#vrNextPlanWrapper', '#vrNextPlan', r.next_plan);
            setOptionalSection('#vrEquipmentWrapper', '#vrEquipment', r.equipment_used);

            // Supervisor notes (shown only if present)
            if (r.supervisor_notes) {
                $('#vrSupervisorNotes').html(escHtml(r.supervisor_notes).replace(/\n/g, '<br>'));
                $('#vrSupervisorWrapper').removeClass('d-none');
            } else {
                $('#vrSupervisorWrapper').addClass('d-none');
            }

            // Photos
            var $grid = $('#vrPhotosGrid').empty();
            $('#vrPhotoCount').text(data.photos.length);

            if (data.photos.length) {
                $('#vrPhotosCard').removeClass('d-none');
                $.each(data.photos, function(i, path) {
                    // Adjust the base URL to your project root
                    var url = '../../' + path;
                    $grid.append(
                        '<div class="col-6 col-md-3">' +
                        '<a href="' + url + '" target="_blank">' +
                        '<img src="' + url + '" class="img-fluid rounded border w-100"' +
                        ' style="height:140px;object-fit:cover"' +
                        ' alt="Site photo ' + (i + 1) + '">' +
                        '</a>' +
                        '</div>'
                    );
                });
            } else {
                $('#vrPhotosCard').addClass('d-none');
            }

            // Reveal
            $('#vrSpinner').addClass('d-none');
            $('#vrContent').removeClass('d-none');

            // If the Photos button was clicked, scroll down to the photos card
            if (scrollToPhotos && data.photos.length) {
                setTimeout(function() {
                    var el = document.getElementById('vrPhotosCard');
                    if (el) el.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }, 150);
            }
        }

        // Show/hide an optional section based on whether the field has content
        function setOptionalSection(wrapperSel, contentSel, value) {
            if (value && value.trim()) {
                $(contentSel).html(escHtml(value).replace(/\n/g, '<br>'));
                $(wrapperSel).removeClass('d-none');
            } else {
                $(wrapperSel).addClass('d-none');
            }
        }

        // dailyReport Modal  
        function dailyReport(projectId) {
            $('#dailyReportProjectId').val(projectId);
            $('#dailyReportModal').modal('show');
        }

        function addMaterialRow() {
            var idx = _matIndex++;
            var $row = $(
                '<div class="row g-2 mb-2 material-row align-items-center">' +
                '<div class="col-md-6">' +
                '<select class="form-select form-select-sm" name="materials[' + idx + '][id]">' +
                $('#_materialOptionsTpl').html() +
                '</select>' +
                '</div>' +
                '<div class="col-md-4">' +
                '<input type="number" step="0.01" min="0" class="form-control form-control-sm"' +
                ' name="materials[' + idx + '][quantity]" placeholder="Quantity">' +
                '</div>' +
                '<div class="col-md-2 text-end">' +
                '<button type="button" class="btn btn-outline-danger btn-sm remove-mat-row" title="Remove">' +
                '<i class="fas fa-times"></i>' +
                '</button>' +
                '</div>' +
                '</div>'
            );
            $('#materialsContainer').append($row);
        }

        // Remove row via delegation
        $(document).on('click', '.remove-mat-row', function() {
            $(this).closest('.material-row').remove();
        });

        // Photo preview 
        $('#dailyReportPhotos').on('change', function() {
            var $preview = $('#photoPreviewRow').empty();
            var files = this.files;
            var max = 5 * 1024 * 1024; // 5 MB

            $.each(files, function(_, file) {
                if (!file.type.startsWith('image/')) return;
                if (file.size > max) {
                    $preview.append(
                        '<div class="alert alert-warning py-1 px-2 small mb-0">' +
                        file.name + ' exceeds 5 MB and will be skipped.</div>'
                    );
                    return;
                }
                var reader = new FileReader();
                reader.onload = function(e) {
                    $preview.append(
                        '<div class="position-relative" style="width:72px;height:72px">' +
                        '<img src="' + e.target.result + '" class="rounded border object-fit-cover w-100 h-100">' +
                        '</div>'
                    );
                };
                reader.readAsDataURL(file);
            });
        });

        // Reset modal on open 
        $('#dailyReportModal').on('show.bs.modal', function() {
            // Clear alert
            $('#dailyReportAlert').addClass('d-none').text('');

            // Reset form fields
            $(this).find('input[type=text], input[type=number], textarea').val('');
            $(this).find('input[type=date]').val('<?= date("Y-m-d") ?>');
            $(this).find('select').prop('selectedIndex', 0);

            // Clear materials and add one blank row
            $('#materialsContainer').empty();
            _matIndex = 0;
            addMaterialRow();

            // Clear photos
            $('#dailyReportPhotos').val('');
            $('#photoPreviewRow').empty();
        });

        // AJAX submit
        $('#submitDailyReportBtn').on('click', function() {
            var $modal = $('#dailyReportModal');
            var $btn = $(this);
            var $spinner = $('#submitDailyReportSpinner');
            var $alert = $('#dailyReportAlert');

            // Basic HTML5 validation
            var form = $modal.find('select, input, textarea');
            var valid = true;
            $modal.find('[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('is-invalid');
                    valid = false;
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
            if (!valid) {
                $alert.removeClass('d-none alert-success alert-danger')
                    .addClass('alert alert-warning')
                    .text('Please fill in all required fields.');
                return;
            }

            // Build FormData (handles files)
            var fd = new FormData();
            $modal.find('input[name], select[name], textarea[name]').each(function() {
                var name = $(this).attr('name');
                if ($(this).is('input[type=file]')) return; // handled separately
                fd.append(name, $(this).val() || '');
            });
            // Append files
            var files = $('#dailyReportPhotos')[0].files;
            for (var i = 0; i < files.length; i++) {
                fd.append('photos[]', files[i]);
            }

            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            $alert.addClass('d-none');

            $.ajax({
                    url: '../../api/daily-report.php',
                    method: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false
                })
                .done(function(res) {
                    var data = (typeof res === 'string') ? JSON.parse(res) : res;
                    if (data.success) {
                        $alert.removeClass('d-none alert-danger alert-warning')
                            .addClass('alert alert-success')
                            .html('<i class="fas fa-check-circle me-1"></i> Report submitted successfully!');
                        setTimeout(function() {
                            $('#dailyReportModal').modal('hide');
                            location.reload();
                        }, 1200);
                    } else {
                        $alert.removeClass('d-none alert-success alert-warning')
                            .addClass('alert alert-danger')
                            .text(data.error || 'Submission failed. Please try again.');
                    }
                })
                .fail(function() {
                    $alert.removeClass('d-none alert-success alert-warning')
                        .addClass('alert alert-danger')
                        .text('Network error. Please check your connection and try again.');
                })
                .always(function() {
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                });
        });

        // Clear is-invalid on input
        $(document).on('input change', '#dailyReportModal [required]', function() {
            if ($(this).val()) $(this).removeClass('is-invalid');
        });

        // Utility helpers 
        function formatNumber(n) {
            return parseFloat(n || 0).toLocaleString('en-KE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatDate(d) {
            if (!d) return '—';
            var dt = new Date(d);
            return isNaN(dt.getTime()) ? d : dt.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        function escHtml(str) {
            return $('<div>').text(str || '').html();
        }

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

        // function viewPhotos(photos) {
        //     // Open photo gallery modal
        //     $('#photoGallery').modal('show');
        // }

        // HELPERS
        function formatDate(d) {
            if (!d) return '—';
            var dt = new Date(d);
            return isNaN(dt.getTime()) ? d : dt.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        function formatDateTime(d) {
            if (!d) return '—';
            var dt = new Date(d);
            return isNaN(dt.getTime()) ? d :
                dt.toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                }) + ' ' +
                dt.toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
        }

        function formatMoney(n) {
            return parseFloat(n || 0).toLocaleString('en-GH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function escHtml(str) {
            return $('<div>').text(str || '').html();
        }
    </script>
</body>

</html>