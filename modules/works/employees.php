<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/session.php';

$session->requireLogin();

$current_user = currentUser();
$company_id   = $session->getCompanyId();
$role         = $session->getRole();
$user_id      = (int) $_SESSION['user_id'];

global $db;

// Access check
if ($current_user['company_type'] != 'Works' && !in_array($role, ['SuperAdmin', 'CompanyAdmin', 'Manager'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: ../../login.php");
    exit();
}

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_ajax && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    // ADD EMPLOYEE
    if ($action === 'add_employee') {

        $employee_code    = trim($_POST['employee_code']    ?? '');
        $full_name        = trim($_POST['full_name']        ?? '');
        $id_number        = trim($_POST['id_number']        ?? '');
        $phone            = trim($_POST['phone']            ?? '');
        $alt_phone        = trim($_POST['alternate_phone']  ?? '');
        $email            = trim($_POST['email']            ?? '');
        $position         = trim($_POST['position']         ?? '');
        $department       = trim($_POST['department']       ?? '');
        $specialization   = trim($_POST['specialization']   ?? '');
        $hire_date        = trim($_POST['hire_date']        ?? '');
        $contract_type    = trim($_POST['contract_type']    ?? 'Contract');
        $hourly_rate      = !empty($_POST['hourly_rate'])   ? (float) $_POST['hourly_rate']   : null;
        $daily_rate       = !empty($_POST['daily_rate'])    ? (float) $_POST['daily_rate']    : null;
        $monthly_salary   = !empty($_POST['monthly_salary']) ? (float) $_POST['monthly_salary'] : null;
        $emergency_name   = trim($_POST['emergency_contact'] ?? '');
        $emergency_phone  = trim($_POST['emergency_phone']  ?? '');

        // Required fields
        if (!$employee_code || !$full_name || !$phone || !$position || !$hire_date) {
            echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
            exit;
        }

        // Check code is unique
        $chk = $db->prepare("SELECT employee_id FROM works_employees WHERE employee_code = ? LIMIT 1");
        $chk->bind_param('s', $employee_code);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            echo json_encode(['success' => false, 'error' => 'Employee code already exists.']);
            exit;
        }

        $stmt = $db->prepare("
            INSERT INTO works_employees
                (employee_code, full_name, id_number, phone, alternate_phone, email,
                 position, department, specialization,
                 hire_date, contract_type,
                 hourly_rate, daily_rate, monthly_salary,
                 emergency_contact, emergency_phone,
                 status, created_at, admin_approvals)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', NOW(), 'Pending')
        ");
        $stmt->bind_param(
            'sssssssssssdddss',
            $employee_code,
            $full_name,
            $id_number,
            $phone,
            $alt_phone,
            $email,
            $position,
            $department,
            $specialization,
            $hire_date,
            $contract_type,
            $hourly_rate,
            $daily_rate,
            $monthly_salary,
            $emergency_name,
            $emergency_phone
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Employee added successfully.']);
        } else {
            error_log('add_employee failed: ' . $stmt->error);
            echo json_encode(['success' => false, 'error' => 'Could not add employee. Please try again.']);
        }
        exit;
    }

    //  EDIT EMPLOYEE
    if ($action === 'edit_employee') {

        $employee_id    = (int) ($_POST['employee_id']    ?? 0);
        $full_name      = trim($_POST['full_name']        ?? '');
        $phone          = trim($_POST['phone']            ?? '');
        $alt_phone      = trim($_POST['alternate_phone']  ?? '');
        $email          = trim($_POST['email']            ?? '');
        $position       = trim($_POST['position']         ?? '');
        $department     = trim($_POST['department']       ?? '');
        $specialization = trim($_POST['specialization']   ?? '');
        $contract_type  = trim($_POST['contract_type']    ?? '');
        $hourly_rate    = !empty($_POST['hourly_rate'])   ? (float) $_POST['hourly_rate']   : null;
        $daily_rate     = !empty($_POST['daily_rate'])    ? (float) $_POST['daily_rate']    : null;
        $monthly_salary = !empty($_POST['monthly_salary']) ? (float) $_POST['monthly_salary'] : null;
        $emergency_name = trim($_POST['emergency_contact'] ?? '');
        $emergency_phone = trim($_POST['emergency_phone']  ?? '');
        $status         = trim($_POST['status']           ?? 'Active');

        if (!$employee_id || !$full_name || !$phone || !$position) {
            echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
            exit;
        }

        $stmt = $db->prepare("
            UPDATE works_employees SET
                full_name        = ?,
                phone            = ?,
                alternate_phone  = ?,
                email            = ?,
                position         = ?,
                department       = ?,
                specialization   = ?,
                contract_type    = ?,
                hourly_rate      = ?,
                daily_rate       = ?,
                monthly_salary   = ?,
                emergency_contact= ?,
                emergency_phone  = ?,
                status           = ?,
                updated_at       = NOW()
            WHERE employee_id = ?
        ");
        $stmt->bind_param(
            'ssssssssdddsssi',
            $full_name,
            $phone,
            $alt_phone,
            $email,
            $position,
            $department,
            $specialization,
            $contract_type,
            $hourly_rate,
            $daily_rate,
            $monthly_salary,
            $emergency_name,
            $emergency_phone,
            $status,
            $employee_id
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Employee updated successfully.']);
        } else {
            error_log('edit_employee failed: ' . $stmt->error);
            echo json_encode(['success' => false, 'error' => 'Could not update employee.']);
        }
        exit;
    }

    //  ASSIGN TO PROJECT
    if ($action === 'assign_project') {

        $employee_id = (int)   ($_POST['employee_id'] ?? 0);
        $project_id  = (int)   ($_POST['project_id']  ?? 0);
        $role_name   = trim($_POST['role_name']        ?? '');
        $start_date  = trim($_POST['start_date']       ?? '');
        $daily_rate  = !empty($_POST['daily_rate']) ? (float) $_POST['daily_rate'] : null;

        if (!$employee_id || !$project_id || !$role_name || !$start_date) {
            echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
            exit;
        }

        // Check not already assigned and active on this project
        $chk = $db->prepare("
            SELECT assignment_id FROM works_project_assignments
            WHERE employee_id = ? AND project_id = ? AND status = 'Active' LIMIT 1
        ");
        $chk->bind_param('ii', $employee_id, $project_id);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            echo json_encode(['success' => false, 'error' => 'Employee is already actively assigned to this project.']);
            exit;
        }

        $stmt = $db->prepare("
            INSERT INTO works_project_assignments
                (project_id, employee_id, role, start_date, daily_rate, status, assigned_by, created_at)
            VALUES (?, ?, ?, ?, ?, 'Active', ?, NOW())
        ");
        $stmt->bind_param(
            'iissdi',
            $project_id,
            $employee_id,
            $role_name,
            $start_date,
            $daily_rate,
            $user_id,
            // bind_param needs variable refs — use variables
            ...[]
        );
        // Re-do with explicit vars (bind_param requires variable references)
        $stmt = $db->prepare("
            INSERT INTO works_project_assignments
                (project_id, employee_id, role, start_date, daily_rate, status, assigned_by, created_at)
            VALUES (?, ?, ?, ?, ?, 'Active', ?, NOW())
        ");
        $stmt->bind_param('iissdi', $project_id, $employee_id, $role_name, $start_date, $daily_rate, $user_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Employee assigned to project successfully.']);
        } else {
            error_log('assign_project failed: ' . $stmt->error);
            echo json_encode(['success' => false, 'error' => 'Could not assign employee.']);
        }
        exit;
    }

    // GET EMPLOYEE 
    if (
        $action === 'get_employee' && $_SERVER['REQUEST_METHOD'] === 'GET' ||
        (isset($_POST['action']) && $_POST['action'] === 'get_employee')
    ) {
        // handled below via GET
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// GET handler for fetching a single employee
if ($is_ajax && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_employee') {
    header('Content-Type: application/json');

    $employee_id = (int) ($_GET['employee_id'] ?? 0);
    if (!$employee_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid employee ID']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT e.*,
               GROUP_CONCAT(p.project_name ORDER BY pa.start_date DESC SEPARATOR ', ') AS current_projects
        FROM   works_employees e
        LEFT   JOIN works_project_assignments pa ON pa.employee_id = e.employee_id AND pa.status = 'Active'
        LEFT   JOIN works_projects            p  ON p.project_id   = pa.project_id
        WHERE  e.employee_id = ?
        GROUP  BY e.employee_id
        LIMIT  1
    ");
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();

    if (!$employee) {
        echo json_encode(['success' => false, 'error' => 'Employee not found']);
        exit;
    }

    echo json_encode(['success' => true, 'employee' => $employee]);
    exit;
}


// Stat counts
$stats = [];
$s = $db->query("
    SELECT
        COUNT(*)                                                           AS total,
        SUM(CASE WHEN status = 'Active'   THEN 1 ELSE 0 END)             AS active,
        SUM(CASE WHEN contract_type = 'Permanent' THEN 1 ELSE 0 END)     AS permanent
    FROM works_employees
")->fetch_assoc();
$stats = $s;

// All employees for the table
$employees = $db->query("SELECT * FROM works_employees ORDER BY full_name ASC");

// Projects list for the assign modal
$projects_res = $db->query("
    SELECT project_id, project_name
    FROM   works_projects
    WHERE  company_id = $company_id AND status = 'In Progress'
    ORDER  BY project_name
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees – <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="../../assets/css/works/style.css" rel="stylesheet">
</head>

<body>
    <div class="container-fluid p-0 module-works works">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-content">

            <!-- Header -->
            <div class="department-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-2">Works & Construction - Employees</h1>
                        <p class="mb-0 opacity-75 text-white">Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>!</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-light text-dark p-2">
                            <i class="far fa-calendar me-2"></i><?php echo date('F j, Y'); ?>
                        </span>
                        <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                            <i class="fas fa-bars"></i>
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                            <i class="fas fa-plus-circle me-2"></i>Add Employee
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stat cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-users"></i></div>
                        <h3 class="stat-value"><?php echo $stats['total']; ?></h3>
                        <p class="stat-label">Total Employees</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#43aa8b,#90be6d)">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['active']; ?></h3>
                        <p class="stat-label">Active</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#4cc9f0,#4895ef)">
                            <i class="fas fa-id-badge"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['permanent']; ?></h3>
                        <p class="stat-label">Permanent</p>
                    </div>
                </div>
            </div>

            <!-- Employees table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">All Employees</h5>
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
                                    <th>Contract</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($emp = $employees->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($emp['employee_code']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="employee-avatar me-2">
                                                    <?php echo get_avatar_letter($emp['full_name']); ?>
                                                </div>
                                                <?php echo htmlspecialchars($emp['full_name']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['position']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['department'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($emp['phone']); ?></td>
                                        <td><?php echo format_date($emp['hire_date']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $emp['contract_type']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php
                                                                    echo $emp['status'] === 'Active' ? 'success'
                                                                        : ($emp['status'] === 'On Leave' ? 'warning' : 'secondary');
                                                                    ?>">
                                                <?php echo $emp['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-sm btn-info" title="View"
                                                    onclick="viewEmployee(<?php echo $emp['employee_id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-primary" title="Edit"
                                                    onclick="editEmployee(<?php echo $emp['employee_id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success" title="Assign to Project"
                                                    onclick="assignToProject(<?php echo $emp['employee_id']; ?>)">
                                                    <i class="fas fa-tasks"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div><!-- /main-content -->
    </div>

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2 text-primary"></i>Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="addEmpAlert" class="d-none mb-3"></div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Employee Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="employee_code" id="addEmpCode" required
                                placeholder="e.g. EMP-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" id="addEmpName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ID Number</label>
                            <input type="text" class="form-control" name="id_number" id="addEmpIdNum">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="phone" id="addEmpPhone" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Alternate Phone</label>
                            <input type="text" class="form-control" name="alternate_phone" id="addEmpAltPhone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" name="email" id="addEmpEmail">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Position <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="position" id="addEmpPosition" required
                                placeholder="e.g. Site Engineer">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Department</label>
                            <input type="text" class="form-control" name="department" id="addEmpDept">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Specialization</label>
                            <input type="text" class="form-control" name="specialization" id="addEmpSpec">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Hire Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="hire_date" id="addEmpHireDate" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contract Type</label>
                            <select class="form-select" name="contract_type" id="addEmpContract">
                                <option value="Permanent">Permanent</option>
                                <option value="Contract" selected>Contract</option>
                                <option value="Temporary">Temporary</option>
                                <option value="Casual">Casual</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-3">
                    <p class="fw-semibold text-muted small mb-2">RATES (fill at least one)</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Hourly Rate</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="hourly_rate" id="addEmpHourly">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Daily Rate</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="daily_rate" id="addEmpDaily">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Monthly Salary</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="monthly_salary" id="addEmpMonthly">
                        </div>
                    </div>

                    <hr class="my-3">
                    <p class="fw-semibold text-muted small mb-2">EMERGENCY CONTACT</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Name</label>
                            <input type="text" class="form-control" name="emergency_contact" id="addEmpEmergName">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Phone</label>
                            <input type="text" class="form-control" name="emergency_phone" id="addEmpEmergPhone">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="addEmpBtn">
                        <span id="addEmpSpinner" class="spinner-border spinner-border-sm me-1 d-none"></span>
                        Add Employee
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── View Employee Modal ────────────────────────────────────── -->
    <div class="modal fade" id="viewEmployeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user me-2 text-info"></i>Employee Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewEmpSpinner" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                    <div id="viewEmpContent" class="d-none">
                        <!-- Avatar + name header -->
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="employee-avatar" style="width:56px;height:56px;font-size:1.4rem" id="veAvatar"></div>
                            <div>
                                <h5 class="mb-0" id="veName"></h5>
                                <div class="text-muted small" id="vePosition"></div>
                            </div>
                            <div class="ms-auto" id="veStatusBadge"></div>
                        </div>

                        <div class="row g-3">
                            <!-- Personal info -->
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold">Personal Info</div>
                                    <div class="card-body small">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th class="text-muted w-40">Code</th>
                                                <td id="veCode"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">ID Number</th>
                                                <td id="veIdNum"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Phone</th>
                                                <td id="vePhone"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Alt Phone</th>
                                                <td id="veAltPhone"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Email</th>
                                                <td id="veEmail"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Emergency</th>
                                                <td id="veEmergency"></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- Work info -->
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold">Work Info</div>
                                    <div class="card-body small">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th class="text-muted w-40">Department</th>
                                                <td id="veDept"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Specialization</th>
                                                <td id="veSpec"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Contract</th>
                                                <td id="veContract"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Hire Date</th>
                                                <td id="veHireDate"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Hourly Rate</th>
                                                <td id="veHourly"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Daily Rate</th>
                                                <td id="veDaily"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Monthly</th>
                                                <td id="veMonthly"></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <!-- Current projects -->
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header fw-semibold">Current Project Assignments</div>
                                    <div class="card-body small" id="veProjects"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!--  Employee Modal -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2 text-primary"></i>Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="editEmpAlert" class="d-none mb-3"></div>
                    <input type="hidden" id="editEmpId">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editEmpName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editEmpPhone" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Alternate Phone</label>
                            <input type="text" class="form-control" id="editEmpAltPhone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" id="editEmpEmail">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Position <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editEmpPosition" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Department</label>
                            <input type="text" class="form-control" id="editEmpDept">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Specialization</label>
                            <input type="text" class="form-control" id="editEmpSpec">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contract Type</label>
                            <select class="form-select" id="editEmpContract">
                                <option value="Permanent">Permanent</option>
                                <option value="Contract">Contract</option>
                                <option value="Temporary">Temporary</option>
                                <option value="Casual">Casual</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select class="form-select" id="editEmpStatus">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-3">
                    <p class="fw-semibold text-muted small mb-2">RATES</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Hourly Rate</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="editEmpHourly">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Daily Rate</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="editEmpDaily">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Monthly Salary</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="editEmpMonthly">
                        </div>
                    </div>

                    <hr class="my-3">
                    <p class="fw-semibold text-muted small mb-2">EMERGENCY CONTACT</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Name</label>
                            <input type="text" class="form-control" id="editEmpEmergName">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Phone</label>
                            <input type="text" class="form-control" id="editEmpEmergPhone">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="editEmpBtn">
                        <span id="editEmpSpinner" class="spinner-border spinner-border-sm me-1 d-none"></span>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign to Project Modal -->
    <div class="modal fade" id="assignProjectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-tasks me-2 text-success"></i>Assign to Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="assignAlert" class="d-none mb-3"></div>
                    <input type="hidden" id="assignEmpId">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Employee</label>
                        <input type="text" class="form-control" id="assignEmpName" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Project <span class="text-danger">*</span></label>
                        <select class="form-select" id="assignProjectId" required>
                            <option value="">Select Project…</option>
                            <?php while ($p = $projects_res->fetch_assoc()): ?>
                                <option value="<?= $p['project_id'] ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role on Project <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="assignRole" required
                            placeholder="e.g. Foreman, Labourer, Electrician">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="assignStartDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Daily Rate for this Assignment</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="assignDailyRate"
                            placeholder="Leave blank to use employee default">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="assignBtn">
                        <span id="assignSpinner" class="spinner-border spinner-border-sm me-1 d-none"></span>
                        Assign
                    </button>
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
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>

    <script>
        // Current page URL — all AJAX posts go back to this same file
        var PAGE_URL = window.location.pathname;

        $(document).ready(function() {
            // Initialise DataTables on the employees table
            $('#employeesTable').DataTable({
                order: [
                    [1, 'asc']
                ],
                pageLength: 25
            });

            // Set today's date as default for the assign start date
            $('#assignStartDate').val(new Date().toISOString().split('T')[0]);
        });

        // VIEW EMPLOYEE
        // Fetches employee data then populates the view modal
        function viewEmployee(id) {
            $('#viewEmpSpinner').removeClass('d-none');
            $('#viewEmpContent').addClass('d-none');
            $('#viewEmployeeModal').modal('show');

            $.get(PAGE_URL, {
                    action: 'get_employee',
                    employee_id: id
                })
                .done(function(res) {
                    if (!res.success) {
                        alert(res.error || 'Failed to load employee.');
                        return;
                    }
                    var e = res.employee;

                    // Avatar + header
                    $('#veAvatar').text(e.full_name ? e.full_name.charAt(0).toUpperCase() : '?');
                    $('#veName').text(e.full_name || '');
                    $('#vePosition').text(e.position || '');

                    var sc = {
                        Active: 'bg-success',
                        Inactive: 'bg-secondary',
                        'On Leave': 'bg-warning'
                    };
                    $('#veStatusBadge').html('<span class="badge ' + (sc[e.status] || 'bg-secondary') + ' fs-6">' + escHtml(e.status) + '</span>');

                    // Personal info table
                    $('#veCode').text(e.employee_code || '—');
                    $('#veIdNum').text(e.id_number || '—');
                    $('#vePhone').text(e.phone || '—');
                    $('#veAltPhone').text(e.alternate_phone || '—');
                    $('#veEmail').text(e.email || '—');
                    $('#veEmergency').text(
                        e.emergency_contact ?
                        e.emergency_contact + (e.emergency_phone ? ' / ' + e.emergency_phone : '') :
                        '—'
                    );

                    // Work info table
                    $('#veDept').text(e.department || '—');
                    $('#veSpec').text(e.specialization || '—');
                    $('#veContract').text(e.contract_type || '—');
                    $('#veHireDate').text(formatDate(e.hire_date));
                    $('#veHourly').text(e.hourly_rate ? formatMoney(e.hourly_rate) + ' /hr' : '—');
                    $('#veDaily').text(e.daily_rate ? formatMoney(e.daily_rate) + ' /day' : '—');
                    $('#veMonthly').text(e.monthly_salary ? formatMoney(e.monthly_salary) + ' /mo' : '—');

                    // Current projects
                    $('#veProjects').html(
                        e.current_projects ?
                        '<span class="badge bg-primary me-1">' + escHtml(e.current_projects).replace(/,\s*/g, '</span><span class="badge bg-primary me-1">') + '</span>' :
                        '<span class="text-muted">Not assigned to any active project</span>'
                    );

                    $('#viewEmpSpinner').addClass('d-none');
                    $('#viewEmpContent').removeClass('d-none');
                })
                .fail(function() {
                    alert('Network error. Please try again.');
                });
        }

        // EDIT EMPLOYEE
        // Fetches data first to pre-fill all fields, then opens the edit modal
        function editEmployee(id) {
            $('#editEmpId').val(id);
            $('#editEmpAlert').addClass('d-none');

            // Pre-fill fields from a GET to the same page
            $.get(PAGE_URL, {
                    action: 'get_employee',
                    employee_id: id
                })
                .done(function(res) {
                    if (!res.success) {
                        alert(res.error || 'Failed to load employee.');
                        return;
                    }
                    var e = res.employee;

                    $('#editEmpName').val(e.full_name || '');
                    $('#editEmpPhone').val(e.phone || '');
                    $('#editEmpAltPhone').val(e.alternate_phone || '');
                    $('#editEmpEmail').val(e.email || '');
                    $('#editEmpPosition').val(e.position || '');
                    $('#editEmpDept').val(e.department || '');
                    $('#editEmpSpec').val(e.specialization || '');
                    $('#editEmpContract').val(e.contract_type || 'Contract');
                    $('#editEmpStatus').val(e.status || 'Active');
                    $('#editEmpHourly').val(e.hourly_rate || '');
                    $('#editEmpDaily').val(e.daily_rate || '');
                    $('#editEmpMonthly').val(e.monthly_salary || '');
                    $('#editEmpEmergName').val(e.emergency_contact || '');
                    $('#editEmpEmergPhone').val(e.emergency_phone || '');

                    $('#editEmployeeModal').modal('show');
                })
                .fail(function() {
                    alert('Network error. Please try again.');
                });
        }

        // Submit edit
        $('#editEmpBtn').on('click', function() {
            var $btn = $(this),
                $spinner = $('#editEmpSpinner'),
                $alert = $('#editEmpAlert');

            if (!$('#editEmpName').val() || !$('#editEmpPhone').val() || !$('#editEmpPosition').val()) {
                $alert.removeClass('d-none').addClass('alert alert-warning').text('Please fill in all required fields.');
                return;
            }

            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            $alert.addClass('d-none');

            $.post(PAGE_URL, {
                    action: 'edit_employee',
                    employee_id: $('#editEmpId').val(),
                    full_name: $('#editEmpName').val(),
                    phone: $('#editEmpPhone').val(),
                    alternate_phone: $('#editEmpAltPhone').val(),
                    email: $('#editEmpEmail').val(),
                    position: $('#editEmpPosition').val(),
                    department: $('#editEmpDept').val(),
                    specialization: $('#editEmpSpec').val(),
                    contract_type: $('#editEmpContract').val(),
                    status: $('#editEmpStatus').val(),
                    hourly_rate: $('#editEmpHourly').val(),
                    daily_rate: $('#editEmpDaily').val(),
                    monthly_salary: $('#editEmpMonthly').val(),
                    emergency_contact: $('#editEmpEmergName').val(),
                    emergency_phone: $('#editEmpEmergPhone').val()
                })
                .done(function(res) {
                    if (res.success) {
                        $alert.removeClass('d-none alert-danger alert-warning').addClass('alert alert-success').text('Employee updated successfully!');
                        setTimeout(function() {
                            $('#editEmployeeModal').modal('hide');
                            location.reload();
                        }, 1000);
                    } else {
                        $alert.removeClass('d-none alert-success alert-warning').addClass('alert alert-danger').text(res.error || 'Update failed.');
                    }
                })
                .fail(function() {
                    $alert.removeClass('d-none alert-success alert-warning').addClass('alert alert-danger').text('Network error. Please try again.');
                })
                .always(function() {
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                });
        });

        // ASSIGN TO PROJECT
        function assignToProject(id) {
            // Fetch employee name to display in the modal
            $.get(PAGE_URL, {
                    action: 'get_employee',
                    employee_id: id
                })
                .done(function(res) {
                    if (!res.success) {
                        alert(res.error || 'Failed to load employee.');
                        return;
                    }
                    var e = res.employee;

                    $('#assignEmpId').val(id);
                    $('#assignEmpName').val(e.full_name || '');
                    $('#assignDailyRate').val(e.daily_rate || '');
                    $('#assignRole').val('');
                    $('#assignProjectId').prop('selectedIndex', 0);
                    $('#assignAlert').addClass('d-none');
                    $('#assignProjectModal').modal('show');
                })
                .fail(function() {
                    alert('Network error. Please try again.');
                });
        }

        // Submit assignment
        $('#assignBtn').on('click', function() {
            var $btn = $(this),
                $spinner = $('#assignSpinner'),
                $alert = $('#assignAlert');

            if (!$('#assignProjectId').val() || !$('#assignRole').val() || !$('#assignStartDate').val()) {
                $alert.removeClass('d-none').addClass('alert alert-warning').text('Please fill in all required fields.');
                return;
            }

            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            $alert.addClass('d-none');

            $.post(PAGE_URL, {
                    action: 'assign_project',
                    employee_id: $('#assignEmpId').val(),
                    project_id: $('#assignProjectId').val(),
                    role_name: $('#assignRole').val(),
                    start_date: $('#assignStartDate').val(),
                    daily_rate: $('#assignDailyRate').val()
                })
                .done(function(res) {
                    if (res.success) {
                        $alert.removeClass('d-none alert-danger alert-warning').addClass('alert alert-success').text('Employee assigned successfully!');
                        setTimeout(function() {
                            $('#assignProjectModal').modal('hide');
                        }, 1200);
                    } else {
                        $alert.removeClass('d-none alert-success alert-warning').addClass('alert alert-danger').text(res.error || 'Assignment failed.');
                    }
                })
                .fail(function() {
                    $alert.removeClass('d-none alert-success alert-warning').addClass('alert alert-danger').text('Network error. Please try again.');
                })
                .always(function() {
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                });
        });

        // ADD EMPLOYEE SUBMIT
        $('#addEmpBtn').on('click', function() {
            var $btn = $(this),
                $spinner = $('#addEmpSpinner'),
                $alert = $('#addEmpAlert');

            // Simple required-field check
            var valid = true;
            $('#addEmployeeModal [required]').each(function() {
                $(this).val() ? $(this).removeClass('is-invalid') : ($(this).addClass('is-invalid'), valid = false);
            });
            if (!valid) {
                $alert.removeClass('d-none alert-success alert-danger').addClass('alert alert-warning').text('Please fill in all required fields.');
                return;
            }

            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            $alert.addClass('d-none');

            $.post(PAGE_URL, {
                    action: 'add_employee',
                    employee_code: $('#addEmpCode').val(),
                    full_name: $('#addEmpName').val(),
                    id_number: $('#addEmpIdNum').val(),
                    phone: $('#addEmpPhone').val(),
                    alternate_phone: $('#addEmpAltPhone').val(),
                    email: $('#addEmpEmail').val(),
                    position: $('#addEmpPosition').val(),
                    department: $('#addEmpDept').val(),
                    specialization: $('#addEmpSpec').val(),
                    hire_date: $('#addEmpHireDate').val(),
                    contract_type: $('#addEmpContract').val(),
                    hourly_rate: $('#addEmpHourly').val(),
                    daily_rate: $('#addEmpDaily').val(),
                    monthly_salary: $('#addEmpMonthly').val(),
                    emergency_contact: $('#addEmpEmergName').val(),
                    emergency_phone: $('#addEmpEmergPhone').val()
                })
                .done(function(res) {
                    if (res.success) {
                        $alert.removeClass('d-none alert-danger alert-warning').addClass('alert alert-success').text('Employee added successfully!');
                        setTimeout(function() {
                            $('#addEmployeeModal').modal('hide');
                            location.reload();
                        }, 1200);
                    } else {
                        $alert.removeClass('d-none alert-success alert-warning').addClass('alert alert-danger').text(res.error || 'Failed to add employee.');
                    }
                })
                .fail(function() {
                    $alert.removeClass('d-none alert-success alert-warning').addClass('alert alert-danger').text('Network error. Please try again.');
                })
                .always(function() {
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                });
        });

        // Clear is-invalid on user input
        $(document).on('input change', '#addEmployeeModal [required]', function() {
            if ($(this).val()) $(this).removeClass('is-invalid');
        });

        // Reset add form when modal opens
        $('#addEmployeeModal').on('show.bs.modal', function() {
            $(this).find('input').val('');
            $(this).find('select').prop('selectedIndex', 0);
            $('#addEmpAlert').addClass('d-none');
        });

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