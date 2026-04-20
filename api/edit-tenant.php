<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('estate', 'edit')) { header('Location: index.php'); exit(); }
global $db;
$tenant_id = (int)($_GET['id'] ?? 0);
if (!$tenant_id) { header('Location: index.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name']    ?? '');
    $phone        = trim($_POST['phone']        ?? '');
    $email        = trim($_POST['email']        ?? '') ?: null;
    $occupation   = trim($_POST['occupation']   ?? '') ?: null;
    $employer     = trim($_POST['employer']     ?? '') ?: null;
    $monthly_rent = (float)($_POST['monthly_rent'] ?? 0);
    $lease_end    = trim($_POST['lease_end_date']   ?? '');
    $status       = trim($_POST['status']       ?? 'Active');
    $emergency_contact_name  = trim($_POST['emergency_contact_name']  ?? '') ?: null;
    $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '') ?: null;

    $stmt = $db->prepare("
        UPDATE estate_tenants SET
            full_name = ?, phone = ?, email = ?, occupation = ?, employer = ?,
            monthly_rent = ?, lease_end_date = ?, status = ?,
            emergency_contact_name = ?, emergency_contact_phone = ?
        WHERE tenant_id = ?
    ");
    $stmt->bind_param(
        "sssssdssssi",
        $full_name, $phone, $email, $occupation, $employer,
        $monthly_rent, $lease_end, $status,
        $emergency_contact_name, $emergency_contact_phone,
        $tenant_id
    );
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Tenant updated successfully.';
    } else {
        $_SESSION['error'] = 'Failed to update tenant.';
    }
    $stmt->close();
    header("Location: ./tenant-details.php?id={$tenant_id}");
    exit();
}

$stmt = $db->prepare("SELECT * FROM estate_tenants WHERE tenant_id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$t) { header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Tenant - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Edit Tenant — <?php echo htmlspecialchars($t['full_name']); ?></h4>
            <a href="./tenant-details.php?id=<?php echo $tenant_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($t['full_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" value="<?php echo $t['phone']; ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo $t['email'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status">
                                <?php foreach (['Active','Notice','Terminated','Past'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $t['status'] == $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Monthly Rent</label>
                            <input type="number" step="0.01" class="form-control" name="monthly_rent" value="<?php echo $t['monthly_rent']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Lease End Date</label>
                            <input type="date" class="form-control" name="lease_end_date" value="<?php echo $t['lease_end_date']; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Occupation</label>
                            <input type="text" class="form-control" name="occupation" value="<?php echo htmlspecialchars($t['occupation'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Employer</label>
                            <input type="text" class="form-control" name="employer" value="<?php echo htmlspecialchars($t['employer'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" class="form-control" name="emergency_contact_name" value="<?php echo htmlspecialchars($t['emergency_contact_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Emergency Contact Phone</label>
                            <input type="text" class="form-control" name="emergency_contact_phone" value="<?php echo $t['emergency_contact_phone'] ?? ''; ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="./tenant-details.php?id=<?php echo $tenant_id; ?>" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>