<?php
require_once '../includes/session.php';
$session->requireLogin();
if ($session->getRole() !== 'SuperAdmin' && $session->getRole() !== 'CompanyAdmin') {
    header('Location: ../admin/dashboard.php'); exit();
}
global $db;

$current_user = currentUser();
$company_id   = $session->getCompanyId();
$is_super     = $session->getRole() === 'SuperAdmin';

if ($is_super) {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name, u.username
        FROM activity_log a
        JOIN users u ON a.user_id = u.user_id
        ORDER BY a.created_at DESC
        LIMIT 200
    ");
    $stmt->execute();
} else {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name, u.username
        FROM activity_log a
        JOIN users u ON a.user_id = u.user_id
        WHERE u.company_id = ?
        ORDER BY a.created_at DESC
        LIMIT 200
    ");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
}
$logs = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activity Log - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Activity Log</h4>
            <a href="../admin/dashboard.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Dashboard
            </a>
        </div>
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0" id="activityTable">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Module</th>
                            <th>IP Address</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($log['full_name']); ?></div>
                                <small class="text-muted"><?php echo $log['username']; ?></small>
                            </td>
                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($log['action']); ?></span></td>
                            <td><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                            <td>
                                <?php if ($log['module']): ?>
                                    <span class="badge bg-secondary text-capitalize"><?php echo $log['module']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo $log['ip_address']; ?></small></td>
                            <td><small><?php echo format_datetime($log['created_at']); ?></small></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script>$('#activityTable').DataTable({ order: [[5, 'desc']] });</script>
</body>
</html>