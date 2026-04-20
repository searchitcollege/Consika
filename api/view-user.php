<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!in_array($session->getRole(), ['SuperAdmin', 'CompanyAdmin'])) {
    header('Location: dashboard.php');
    exit();
}
global $db;
$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) {
    header('Location: users.php');
    exit();
}

$stmt = $db->prepare("
    SELECT u.*, c.company_name, c.company_type
    FROM users u
    LEFT JOIN companies c ON u.company_id = c.company_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) {
    header('Location: users.php');
    exit();
}

$perms_stmt = $db->prepare("SELECT * FROM user_permissions WHERE user_id = ?");
$perms_stmt->bind_param("i", $user_id);
$perms_stmt->execute();
$permissions = $perms_stmt->get_result();

$activity_stmt = $db->prepare("
    SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 20
");
$activity_stmt->bind_param("i", $user_id);
$activity_stmt->execute();
$activity = $activity_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($user['full_name']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include '../includes/top-nav.php'; ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <span class="badge bg-<?php echo $user['status'] == 'Active' ? 'success' : 'secondary'; ?>"><?php echo $user['status']; ?></span>
                    <span class="badge bg-info ms-1"><?php echo $user['role']; ?></span>
                </div>
                <div>
                    <button onclick="editUser(<?php echo $user['user_id']; ?>)" class="btn btn-primary btn-sm me-2"><i class="fas fa-edit me-1"></i>Edit</button>
                    <a href="../admin/users.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">User Information</div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <th class="text-muted" style="width:40%">Username</th>
                                    <td><?php echo $user['username']; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Email</th>
                                    <td><?php echo $user['email']; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Phone</th>
                                    <td><?php echo $user['phone'] ?: '—'; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Company</th>
                                    <td><?php echo $user['company_name'] ?: 'All Companies'; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Last Login</th>
                                    <td><?php echo $user['last_login'] ? format_datetime($user['last_login']) : 'Never'; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Last IP</th>
                                    <td><?php echo $user['last_ip'] ?: '—'; ?></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Created</th>
                                    <td><?php echo format_date($user['created_at']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">Module Permissions</div>
                        <div class="card-body">
                            <?php if ($permissions->num_rows > 0): ?>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Module</th>
                                            <th>View</th>
                                            <th>Create</th>
                                            <th>Edit</th>
                                            <th>Delete</th>
                                            <th>Approve</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($perm = $permissions->fetch_assoc()): ?>
                                            <tr>
                                                <td class="text-capitalize"><?php echo $perm['module_name']; ?></td>
                                                <td><?php echo $perm['can_view'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'; ?></td>
                                                <td><?php echo $perm['can_create'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'; ?></td>
                                                <td><?php echo $perm['can_edit'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'; ?></td>
                                                <td><?php echo $perm['can_delete'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'; ?></td>
                                                <td><?php echo $perm['can_approve'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-muted">No specific permissions set (uses role defaults).</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header fw-semibold">Recent Activity</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Action</th>
                                <th>Description</th>
                                <th>Module</th>
                                <th>IP</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($activity->num_rows > 0): while ($a = $activity->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($a['action']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($a['description'] ?? '', 0, 60)); ?></td>
                                        <td><?php echo $a['module'] ?: '—'; ?></td>
                                        <td><small><?php echo $a['ip_address']; ?></small></td>
                                        <td><small><?php echo format_datetime($a['created_at']); ?></small></td>
                                    </tr>
                                <?php endwhile;
                            else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">No activity recorded</td>
                                </tr>
                            <?php endif; ?>
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
    <script>
        function editUser(id) {
            $.ajax({
                url: './get-user.php',
                type: 'GET',
                data: {
                    id: id
                },
                dataType: 'json',
                success: function(data) {
                    $('#edit_user_id').val(data.user_id);
                    $('#edit_username').val(data.username);
                    $('#edit_email').val(data.email);
                    $('#edit_fullname').val(data.full_name);
                    $('#edit_phone').val(data.phone);
                    $('#edit_role').val(data.role);
                    $('#edit_status').val(data.status);
                    $('#editUserModal').modal('show');
                }
            });
        }
    </script>
</body>

</html>