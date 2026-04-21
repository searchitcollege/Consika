<?php
// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use absolute paths to avoid path issues
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db_connection.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/session.php';

$session->requireLogin();

// Only SuperAdmin and CompanyAdmin can access this page
if (!in_array($session->getRole(), ['SuperAdmin', 'CompanyAdmin'])) {
    $_SESSION['error'] = 'You do not have permission to access this page.';
    header('Location: dashboard.php');
    exit();
}

$current_user = getCurrentUser(); // Changed from currentUser() to getCurrentUser()
$is_super_admin = $session->getRole() == 'SuperAdmin';

global $db;

// Initialize variables
$companies = null;
$edit_user = null;
$user_permissions = [];

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username   = trim($_POST['username']   ?? '');
                $email      = trim($_POST['email']      ?? '');
                $password   = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
                $full_name  = trim($_POST['full_name']  ?? '');
                $phone      = trim($_POST['phone']      ?? '');
                $role       = trim($_POST['role']       ?? 'Staff');
                $company_id = $is_super_admin
                    ? (($_POST['company_id'] ?? '') !== '' ? (int)$_POST['company_id'] : null)
                    : $session->getCompanyId();
                $status = trim($_POST['status'] ?? 'Active');

                // Check duplicate username or email
                $check = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
                $check->bind_param("ss", $username, $email);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $_SESSION['error'] = 'Username or email already exists';
                } else {
                    $check->close();
                    $stmt = $db->prepare("
                        INSERT INTO users (username, password, email, full_name, phone, role, company_id, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param(
                        "ssssssis",
                        $username,
                        $password,
                        $email,
                        $full_name,
                        $phone,
                        $role,
                        $company_id,
                        $status
                    );
                    if ($stmt->execute()) {
                        $stmt->close();
                        log_activity($current_user['user_id'], 'Add User', "Added user: $username");
                        $_SESSION['success'] = 'User added successfully';
                    } else {
                        $_SESSION['error'] = 'Error adding user';
                    }
                }
                break;

            case 'edit':
                $user_id   = (int)($_POST['user_id'] ?? 0);
                $email     = trim($_POST['email']     ?? '');
                $full_name = trim($_POST['full_name'] ?? '');
                $phone     = trim($_POST['phone']     ?? '');
                $role      = trim($_POST['role']      ?? 'Staff');
                $status    = trim($_POST['status']    ?? 'Active');

                $stmt = $db->prepare("
                    UPDATE users SET email = ?, full_name = ?, phone = ?, role = ?, status = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param("sssssi", $email, $full_name, $phone, $role, $status, $user_id);

                if ($stmt->execute()) {
                    $stmt->close();

                    if (!empty($_POST['new_password'])) {
                        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                        $pwd = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $pwd->bind_param("si", $new_password, $user_id);
                        $pwd->execute();
                        $pwd->close();
                    }

                    log_activity($current_user['user_id'], 'Edit User', "Edited user ID: $user_id");
                    $_SESSION['success'] = 'User updated successfully';
                } else {
                    $_SESSION['error'] = 'Error updating user';
                }
                break;
            case 'delete':
                $user_id = intval($_POST['user_id']);

                // Don't allow deleting own account
                if ($user_id == $current_user['user_id']) {
                    $_SESSION['error'] = 'You cannot delete your own account';
                } else {
                    $sql = "DELETE FROM users WHERE user_id = ?";
                    $stmt = $db->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("i", $user_id);

                        if ($stmt->execute()) {
                            $_SESSION['success'] = 'User deleted successfully';
                            if (function_exists('log_activity')) {
                                log_activity($current_user['user_id'], 'Delete User', "Deleted user ID: $user_id");
                            }
                        } else {
                            $_SESSION['error'] = 'Error deleting user: ' . $db->error();
                        }
                        $stmt->close();
                    }
                }
                break;

            case 'update_permissions':
                $user_id = intval($_POST['user_id']);

                // Delete existing permissions
                $db->query("DELETE FROM user_permissions WHERE user_id = $user_id");

                // Insert new permissions
                if (isset($_POST['modules']) && is_array($_POST['modules'])) {
                    $success_count = 0;
                    foreach ($_POST['modules'] as $module => $perms) {
                        $can_view = isset($perms['view']) ? 1 : 0;
                        $can_create = isset($perms['create']) ? 1 : 0;
                        $can_edit = isset($perms['edit']) ? 1 : 0;
                        $can_delete = isset($perms['delete']) ? 1 : 0;
                        $can_approve = isset($perms['approve']) ? 1 : 0;

                        $sql = "INSERT INTO user_permissions (user_id, module_name, can_view, can_create, can_edit, can_delete, can_approve) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $db->prepare($sql);
                        if ($stmt) {
                            $stmt->bind_param("isiiiii", $user_id, $module, $can_view, $can_create, $can_edit, $can_delete, $can_approve);
                            if ($stmt->execute()) {
                                $success_count++;
                            }
                            $stmt->close();
                        }
                    }

                    if ($success_count > 0) {
                        $_SESSION['success'] = 'Permissions updated successfully';
                        if (function_exists('log_activity')) {
                            log_activity($current_user['user_id'], 'Update Permissions', "Updated permissions for user ID: $user_id");
                        }
                    } else {
                        $_SESSION['error'] = 'Error updating permissions';
                    }
                }
                break;
        }

        header('Location: users.php');
        exit();
    }
}

// Get users based on role
$users = null;
if ($is_super_admin) {
    $users_query = "SELECT u.*, c.company_name 
                    FROM users u 
                    LEFT JOIN companies c ON u.company_id = c.company_id 
                    ORDER BY u.created_at DESC";
    $users = $db->query($users_query);
} else {
    $company_id = $session->getCompanyId();
    $users_query = "SELECT * FROM users WHERE company_id = ? ORDER BY created_at DESC";
    $stmt = $db->prepare($users_query);
    if ($stmt) {
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $users = $stmt->get_result();
    }
}

// Get companies for dropdown
if ($is_super_admin) {
    $companies = $db->query("SELECT company_id, company_name FROM companies WHERE status = 'Active'");
}

// Get user permissions if editing
if (isset($_GET['edit'])) {
    $user_id = intval($_GET['edit']);
    $edit_query = "SELECT * FROM users WHERE user_id = ?";
    $stmt = $db->prepare($edit_query);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $edit_user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    // Get user permissions
    $perms_query = "SELECT * FROM user_permissions WHERE user_id = ?";
    $stmt = $db->prepare($perms_query);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $perms_result = $stmt->get_result();
        while ($perm = $perms_result->fetch_assoc()) {
            $user_permissions[$perm['module_name']] = $perm;
        }
        $stmt->close();
    }
}

// Set page title for top navigation
$page_title = 'User Management';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo defined('APP_NAME') ? APP_NAME : 'Company Management'; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">

    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --dark-color: #1e1e2f;
            --light-color: #f8f9fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f4f7fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .wrapper {
            display: flex;
        }

        /* Sidebar - already in sidebar.php */

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s;
        }

        .page-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .role-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            display: inline-block;
        }

        .role-superadmin {
            background: #dc3545;
        }

        .role-companyadmin {
            background: #fd7e14;
        }

        .role-manager {
            background: #0d6efd;
        }

        .role-staff {
            background: #6c757d;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }

        .modal-xl {
            max-width: 90%;
        }

        .permission-group {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
        }

        .permission-group h6 {
            margin-bottom: 10px;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }

        .permission-checkboxes {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .permission-checkboxes .form-check {
            min-width: 80px;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            margin: 0 2px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .modal-xl {
                max-width: 95%;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <!-- Include sidebar -->
        <?php include dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <?php include dirname(__DIR__) . '/includes/top-nav.php'; ?>

            <!-- Page Header -->
            <div class=" d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">User Management</h4>
                    <p class="text-muted mb-0">Manage system users and their permissions</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus-circle me-2"></i>Add New User
                </button>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Users Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="usersTable">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <?php if ($is_super_admin): ?>
                                        <th>Company</th>
                                    <?php endif; ?>
                                    <th>Role</th>
                                    <th>Last Login</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users && $users->num_rows > 0): ?>
                                    <?php while ($user = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-2">
                                                        <?php echo getAvatarLetter($user['full_name'] ?? 'U'); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['username'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone'] ?? ''); ?></td>
                                            <?php if ($is_super_admin): ?>
                                                <td><?php echo htmlspecialchars($user['company_name'] ?? 'All Companies'); ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <span class="role-badge role-<?php echo strtolower($user['role'] ?? 'staff'); ?>">
                                                    <?php echo $user['role'] ?? 'Staff'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                if (!empty($user['last_login'])) {
                                                    echo formatDateTime($user['last_login']);
                                                } else {
                                                    echo '<span class="text-muted">Never</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($user['status'] ?? 'inactive'); ?>">
                                                    <?php echo $user['status'] ?? 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="viewUser(<?php echo $user['user_id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-primary" onclick="editUser(<?php echo $user['user_id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="managePermissions(<?php echo $user['user_id']; ?>)" title="Permissions">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <?php if ($user['user_id'] != $current_user['user_id']): ?>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username'] ?? ''); ?>')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?php echo $is_super_admin ? 9 : 8; ?>" class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No users found</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="users.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                                <small class="text-muted">Min 8 characters with letters and numbers</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role</label>
                                <select class="form-control" name="role" required>
                                    <option value="Staff">Staff</option>
                                    <option value="Manager">Manager</option>
                                    <?php if ($is_super_admin): ?>
                                        <option value="CompanyAdmin">Company Admin</option>
                                        <option value="SuperAdmin">Super Admin</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <?php if ($is_super_admin && $companies && $companies->num_rows > 0): ?>
                            <div class="mb-3">
                                <label class="form-label">Company</label>
                                <select class="form-control" name="company_id">
                                    <option value="">All Companies (Super Admin)</option>
                                    <?php while ($company = $companies->fetch_assoc()): ?>
                                        <option value="<?php echo $company['company_id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                                    <?php endwhile; ?>
                                    <?php // Reset pointer for potential later use
                                    if ($companies) $companies->data_seek(0);
                                    ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="users.php" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" id="edit_username" readonly disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" id="edit_password">
                                <small class="text-muted">Leave blank to keep current password</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" id="edit_fullname" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" id="edit_phone" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role</label>
                                <select class="form-control" name="role" id="edit_role" required>
                                    <option value="Staff">Staff</option>
                                    <option value="Manager">Manager</option>
                                    <?php if ($is_super_admin): ?>
                                        <option value="CompanyAdmin">Company Admin</option>
                                        <option value="SuperAdmin">Super Admin</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status" id="edit_status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Permissions Modal -->
    <div class="modal fade" id="permissionsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage User Permissions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="users.php" method="POST">
                    <input type="hidden" name="action" value="update_permissions">
                    <input type="hidden" name="user_id" id="perm_user_id">
                    <div class="modal-body">
                        <p class="text-muted mb-3">Configure module access for this user. Company Admins and Super Admins automatically have full access.</p>

                        <div class="row">
                            <!-- Estate Module -->
                            <div class="col-md-6">
                                <div class="permission-group">
                                    <h6><i class="fas fa-building me-2"></i>Estate Management</h6>
                                    <div class="permission-checkboxes">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[estate][view]" id="perm_estate_view">
                                            <label class="form-check-label" for="perm_estate_view">View</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[estate][create]" id="perm_estate_create">
                                            <label class="form-check-label" for="perm_estate_create">Create</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[estate][edit]" id="perm_estate_edit">
                                            <label class="form-check-label" for="perm_estate_edit">Edit</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[estate][delete]" id="perm_estate_delete">
                                            <label class="form-check-label" for="perm_estate_delete">Delete</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[estate][approve]" id="perm_estate_approve">
                                            <label class="form-check-label" for="perm_estate_approve">Approve</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Procurement Module -->
                            <div class="col-md-6">
                                <div class="permission-group">
                                    <h6><i class="fas fa-truck me-2"></i>Procurement</h6>
                                    <div class="permission-checkboxes">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[procurement][view]" id="perm_proc_view">
                                            <label class="form-check-label" for="perm_proc_view">View</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[procurement][create]" id="perm_proc_create">
                                            <label class="form-check-label" for="perm_proc_create">Create</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[procurement][edit]" id="perm_proc_edit">
                                            <label class="form-check-label" for="perm_proc_edit">Edit</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[procurement][delete]" id="perm_proc_delete">
                                            <label class="form-check-label" for="perm_proc_delete">Delete</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[procurement][approve]" id="perm_proc_approve">
                                            <label class="form-check-label" for="perm_proc_approve">Approve</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Works Module -->
                            <div class="col-md-6">
                                <div class="permission-group">
                                    <h6><i class="fas fa-tools me-2"></i>Works & Construction</h6>
                                    <div class="permission-checkboxes">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[works][view]" id="perm_works_view">
                                            <label class="form-check-label" for="perm_works_view">View</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[works][create]" id="perm_works_create">
                                            <label class="form-check-label" for="perm_works_create">Create</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[works][edit]" id="perm_works_edit">
                                            <label class="form-check-label" for="perm_works_edit">Edit</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[works][delete]" id="perm_works_delete">
                                            <label class="form-check-label" for="perm_works_delete">Delete</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[works][approve]" id="perm_works_approve">
                                            <label class="form-check-label" for="perm_works_approve">Approve</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Block Factory Module -->
                            <div class="col-md-6">
                                <div class="permission-group">
                                    <h6><i class="fas fa-cubes me-2"></i>Block Factory</h6>
                                    <div class="permission-checkboxes">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[blockfactory][view]" id="perm_block_view">
                                            <label class="form-check-label" for="perm_block_view">View</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[blockfactory][create]" id="perm_block_create">
                                            <label class="form-check-label" for="perm_block_create">Create</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[blockfactory][edit]" id="perm_block_edit">
                                            <label class="form-check-label" for="perm_block_edit">Edit</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[blockfactory][delete]" id="perm_block_delete">
                                            <label class="form-check-label" for="perm_block_delete">Delete</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[blockfactory][approve]" id="perm_block_approve">
                                            <label class="form-check-label" for="perm_block_approve">Approve</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reports Module -->
                            <div class="col-md-6">
                                <div class="permission-group">
                                    <h6><i class="fas fa-chart-bar me-2"></i>Reports</h6>
                                    <div class="permission-checkboxes">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[reports][view]" id="perm_reports_view">
                                            <label class="form-check-label" for="perm_reports_view">View</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[reports][create]" id="perm_reports_create">
                                            <label class="form-check-label" for="perm_reports_create">Create</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="modules[reports][export]" id="perm_reports_export">
                                            <label class="form-check-label" for="perm_reports_export">Export</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Admin Module (Super Admin only) -->
                            <?php if ($is_super_admin): ?>
                                <div class="col-md-6">
                                    <div class="permission-group">
                                        <h6><i class="fas fa-cog me-2"></i>Admin</h6>
                                        <div class="permission-checkboxes">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="modules[admin][users]" id="perm_admin_users">
                                                <label class="form-check-label" for="perm_admin_users">Manage Users</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="modules[admin][companies]" id="perm_admin_companies">
                                                <label class="form-check-label" for="perm_admin_companies">Manage Companies</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="modules[admin][settings]" id="perm_admin_settings">
                                                <label class="form-check-label" for="perm_admin_settings">System Settings</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Permissions</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete user <strong id="deleteUsername"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form action="users.php" method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- AJAX loader for edit user -->
    <div style="display: none;" id="ajaxLoader">
        <div class="text-center p-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="../assets/js/main.js"></script>

    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#usersTable').DataTable({
                order: [
                    [0, 'asc']
                ],
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    zeroRecords: "No matching records found",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });

            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 5000);
        });

        function viewUser(id) {
            window.location.href = '../api/view-user.php?id=' + id;
        }

        function editUser(id) {
            // Show loading
            $('#editUserModal .modal-body').prepend($('#ajaxLoader').html());

            $.ajax({
                url: '../api/get-user.php',
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
                    $('#edit_password').val(''); // Clear password field

                    $('#editUserModal').modal('show');
                },
                error: function(xhr, status, error) {
                    alert('Error loading user data: ' + error);
                },
                complete: function() {
                    // Remove loader
                    $('#editUserModal .spinner-border').closest('div').remove();
                }
            });
        }

        function managePermissions(id) {
            $('#perm_user_id').val(id);

            // Show loading
            $('#permissionsModal .modal-body').prepend($('#ajaxLoader').html());

            $.ajax({
                url: '../api/get-user-permissions.php',
                type: 'GET',
                data: {
                    id: id
                },
                dataType: 'json',
                success: function(data) {
                    // Reset all checkboxes
                    $('.permission-checkboxes input[type="checkbox"]').prop('checked', false);

                    // Set checked based on existing permissions
                    if (data) {
                        $.each(data, function(module, perms) {
                            $.each(perms, function(perm, value) {
                                if (value == 1) {
                                    $('#perm_' + module + '_' + perm).prop('checked', true);
                                }
                            });
                        });
                    }

                    $('#permissionsModal').modal('show');
                },
                error: function(xhr, status, error) {
                    alert('Error loading permissions: ' + error);
                },
                complete: function() {
                    // Remove loader
                    $('#permissionsModal .spinner-border').closest('div').remove();
                }
            });
        }

        function deleteUser(id, username) {
            $('#deleteUserId').val(id);
            $('#deleteUsername').text(username);
            $('#deleteModal').modal('show');
        }
    </script>
</body>

</html>