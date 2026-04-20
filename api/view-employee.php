<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('works', 'view')) { header('Location: index.php'); exit(); }
global $db;
$employee_id = (int)($_GET['id'] ?? 0);
if (!$employee_id) { header('Location: index.php'); exit(); }

$stmt = $db->prepare("SELECT * FROM works_employees WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$emp = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$emp) { header('Location: index.php'); exit(); }

$assign_stmt = $db->prepare("
    SELECT pa.*, wp.project_name, wp.project_code
    FROM works_project_assignments pa
    JOIN works_projects wp ON pa.project_id = wp.project_id
    WHERE pa.employee_id = ?
    ORDER BY pa.start_date DESC
");
$assign_stmt->bind_param("i", $employee_id);
$assign_stmt->execute();
$assignments = $assign_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($emp['full_name']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4><?php echo htmlspecialchars($emp['full_name']); ?></h4>
                <span class="badge bg-<?php echo $emp['status'] == 'Active' ? 'success' : 'secondary'; ?>"><?php echo $emp['status']; ?></span>
                <span class="text-muted ms-2 small"><?php echo $emp['employee_code']; ?></span>
            </div>
            <div>
                <a href="./edit-employee.php?id=<?php echo $employee_id; ?>" class="btn btn-primary btn-sm me-2"><i class="fas fa-edit me-1"></i>Edit</a>
                <a href="../modules/works/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Personal Information</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Position</th><td><?php echo $emp['position']; ?></td></tr>
                            <tr><th class="text-muted">Department</th><td><?php echo $emp['department'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Phone</th><td><?php echo $emp['phone']; ?></td></tr>
                            <tr><th class="text-muted">Email</th><td><?php echo $emp['email'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">ID Number</th><td><?php echo $emp['id_number'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Address</th><td><?php echo $emp['address'] ?: '—'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Employment Details</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Hire Date</th><td><?php echo format_date($emp['hire_date']); ?></td></tr>
                            <tr><th class="text-muted">Contract Type</th><td><?php echo $emp['contract_type']; ?></td></tr>
                            <tr><th class="text-muted">Hourly Rate</th><td><?php echo $emp['hourly_rate'] ? format_money($emp['hourly_rate']) : '—'; ?></td></tr>
                            <tr><th class="text-muted">Daily Rate</th><td><?php echo $emp['daily_rate'] ? format_money($emp['daily_rate']) : '—'; ?></td></tr>
                            <tr><th class="text-muted">Monthly Salary</th><td><?php echo $emp['monthly_salary'] ? format_money($emp['monthly_salary']) : '—'; ?></td></tr>
                            <tr><th class="text-muted">Emergency Contact</th><td><?php echo $emp['emergency_contact'] ?: '—'; ?> <?php echo $emp['emergency_phone'] ? '(' . $emp['emergency_phone'] . ')' : ''; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header fw-semibold">Project Assignments</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Project</th><th>Role</th><th>Start Date</th><th>Status</th><th>Hours Worked</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($assignments->num_rows > 0): while ($a = $assignments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['project_name']); ?> <small class="text-muted"><?php echo $a['project_code']; ?></small></td>
                            <td><?php echo $a['role']; ?></td>
                            <td><?php echo format_date($a['start_date']); ?></td>
                            <td><span class="badge bg-<?php echo $a['status'] == 'Active' ? 'success' : 'secondary'; ?>"><?php echo $a['status']; ?></span></td>
                            <td><?php echo $a['hours_worked']; ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No project assignments</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>