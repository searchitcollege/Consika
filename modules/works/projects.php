<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/session.php';

$session->requireLogin();

$current_user = currentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

global $db;

//Access Check
if ($current_user['company_type'] != 'Works' && ($role != 'SuperAdmin' && $role != 'CompanyAdmin' && $role != 'Manager')) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: ../../login.php");
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

<body>
    <div class="container-fluid p-0 module-works works">
        <!-- ?Inclusde sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Main content -->
        <div class="main-content">
            <!-- Module Header -->
            <div class="department-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-2">Works & Construction - Projects</h1>
                        <p class="mb-0 opacity-75 text-white">Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>!</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-light text-dark p-2">
                            <i class="far fa-calendar me-2"></i><?php echo date('F j, Y'); ?>
                        </span>
                        <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Card -->
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
                <!-- Budgest Summarry -->
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
            </div>

            <!-- Deadlines upcoming -->
            <div class="row mb-4">
                <div class="col-md-6">
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

            <!-- Divider to contain button for add new project -->
            <div class="row">
                <div class="col--6 d-flex justify-content-center mb-4">
                    <div class="quick-action-btn w-100" data-bs-toggle="modal" data-bs-target="#newProjectModal">
                        <i class="fas fa-hard-hat"></i>
                        <span>Start Project</span>
                    </div>
                </div>
            </div>

            <!-- Projects list -->
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
    </div>

    <!-- modals for new, view and report for works -->
    <!-- New Project Modal -->
    <div class="modal fade" id="newProjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Start New Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../../api/new-project.php" method="POST">
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
                                WHERE  company_id = $company_id
                                  AND  status = 'In Progress'
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

    <!-- Hidden template for material options — PHP renders this once, JS clones from it -->
    <select id="_materialOptionsTpl" class="d-none" aria-hidden="true">
        <option value="">Select Material…</option>
        <?php
        $mats = $db->query("SELECT product_id, product_name FROM procurement_products WHERE category = 'Building Materials' ORDER BY product_name");
        while ($mat = $mats->fetch_assoc()):
        ?>
            <option value="<?= $mat['product_id'] ?>"><?= htmlspecialchars($mat['product_name']) ?></option>
        <?php endwhile; ?>
    </select>

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
        function viewProject(projectId) {
            $('#projectModalSpinner').removeClass('d-none');
            $('#projectModalContent').addClass('d-none');
            $('#projectModalError').addClass('d-none').text('');
            $('#viewProjectModal').modal('show');

            $.ajax({
                    url: '../../api/get-project-details.php',
                    method: 'GET',
                    data: {
                        project_id: projectId
                    },
                    dataType: 'json'
                })
                .done(function(data) {
                    if (!data.success) {
                        showModalError(data.error || 'Failed to load project.');
                        return;
                    }
                    populateProjectModal(data);
                })
                .fail(function(xhr) {
                    var msg = 'Could not load project details.';
                    try {
                        var parsed = JSON.parse(xhr.responseText);
                        if (parsed.error) msg = parsed.error;
                    } catch (e) {}
                    showModalError(msg);
                });
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
    </script>
</body>