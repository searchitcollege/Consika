<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('works', 'edit')) { header('Location: index.php'); exit(); }
global $db;
$employee_id = (int)($_GET['id'] ?? 0);
if (!$employee_id) { header('Location: index.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name         = trim($_POST['full_name']         ?? '');
    $phone             = trim($_POST['phone']             ?? '');
    $position          = trim($_POST['position']          ?? '');
    $department        = trim($_POST['department']        ?? '') ?: null;
    $contract_type     = trim($_POST['contract_type']     ?? 'Contract');
    $status            = trim($_POST['status']            ?? 'Active');
    $hourly_rate       = $_POST['hourly_rate']    !== '' ? (float)$_POST['hourly_rate']    : null;
    $daily_rate        = $_POST['daily_rate']     !== '' ? (float)$_POST['daily_rate']     : null;
    $monthly_salary    = $_POST['monthly_salary'] !== '' ? (float)$_POST['monthly_salary'] : null;
    $emergency_contact = trim($_POST['emergency_contact'] ?? '') ?: null;
    $emergency_phone   = trim($_POST['emergency_phone']   ?? '') ?: null;

    $stmt = $db->prepare("
        UPDATE works_employees SET
            full_name = ?, phone = ?, position = ?, department = ?,
            contract_type = ?, status = ?,
            hourly_rate = ?, daily_rate = ?, monthly_salary = ?,
            emergency_contact = ?, emergency_phone = ?
        WHERE employee_id = ?
    ");
    $stmt->bind_param(
        "ssssssdddssi",
        $full_name, $phone, $position, $department,
        $contract_type, $status,
        $hourly_rate, $daily_rate, $monthly_salary,
        $emergency_contact, $emergency_phone,
        $employee_id
    );
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Employee updated successfully.';
    } else {
        $_SESSION['error'] = 'Failed to update employee.';
    }
    $stmt->close();
    header("Location: ./view-employee.php?id={$employee_id}");
    exit();
}

$stmt = $db->prepare("SELECT * FROM works_employees WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$e = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$e) { header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Employee - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Edit Employee — <?php echo htmlspecialchars($e['full_name']); ?></h4>
            <a href="./view-employee.php?id=<?php echo $employee_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
        <?php if (isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($e['full_name']); ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" class="form-control" name="phone" value="<?php echo $e['phone']; ?>" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Position</label><input type="text" class="form-control" name="position" value="<?php echo htmlspecialchars($e['position']); ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Department</label><input type="text" class="form-control" name="department" value="<?php echo htmlspecialchars($e['department'] ?? ''); ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contract Type</label>
                            <select class="form-control" name="contract_type">
                                <?php foreach (['Permanent','Contract','Temporary','Casual'] as $ct): ?>
                                    <option value="<?php echo $ct; ?>" <?php echo $e['contract_type'] == $ct ? 'selected' : ''; ?>><?php echo $ct; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status">
                                <?php foreach (['Active','Inactive','On Leave'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $e['status'] == $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Hourly Rate</label><input type="number" step="0.01" class="form-control" name="hourly_rate" value="<?php echo $e['hourly_rate'] ?? ''; ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Daily Rate</label><input type="number" step="0.01" class="form-control" name="daily_rate" value="<?php echo $e['daily_rate'] ?? ''; ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Monthly Salary</label><input type="number" step="0.01" class="form-control" name="monthly_salary" value="<?php echo $e['monthly_salary'] ?? ''; ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Emergency Contact</label><input type="text" class="form-control" name="emergency_contact" value="<?php echo htmlspecialchars($e['emergency_contact'] ?? ''); ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Emergency Phone</label><input type="text" class="form-control" name="emergency_phone" value="<?php echo $e['emergency_phone'] ?? ''; ?>"></div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="./view-employee.php?id=<?php echo $employee_id; ?>" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>