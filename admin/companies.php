<?php
require_once '../includes/session.php';
$session->requireLogin();

// Only SuperAdmin can access this page
if ($session->getRole() != 'SuperAdmin') {
    $_SESSION['error'] = 'You do not have permission to access this page.';
    header('Location: dashboard.php');
    exit();
}

$current_user = currentUser();

global $db;

// Handle company actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $company_name        = trim($_POST['company_name']        ?? '');
                $company_type        = trim($_POST['company_type']        ?? '');
                $registration_number = trim($_POST['registration_number'] ?? '') ?: null;
                $tax_number          = trim($_POST['tax_number']          ?? '') ?: null;
                $address             = trim($_POST['address']             ?? '') ?: null;
                $phone               = trim($_POST['phone']               ?? '') ?: null;
                $email               = trim($_POST['email']               ?? '') ?: null;
                $website             = trim($_POST['website']             ?? '') ?: null;
                $established_date    = trim($_POST['established_date']    ?? '') ?: null;
                $status              = trim($_POST['status']              ?? 'Active');

                $stmt = $db->prepare("
                    INSERT INTO companies
                        (company_name, company_type, registration_number, tax_number,
                        address, phone, email, website, established_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "ssssssssss",
                    $company_name,
                    $company_type,
                    $registration_number,
                    $tax_number,
                    $address,
                    $phone,
                    $email,
                    $website,
                    $established_date,
                    $status
                );

                if ($stmt->execute()) {
                    $stmt->close();

                    $id_result  = $db->query("SELECT LAST_INSERT_ID() AS new_id");
                    $company_id = (int)$id_result->fetch_assoc()['new_id'];

                    // Create default admin user
                    $default_username = strtolower(str_replace(' ', '_', $company_name)) . '_admin';
                    $default_password = password_hash('Admin@123', PASSWORD_DEFAULT);
                    $default_email    = 'admin@' . strtolower(str_replace(' ', '', $company_name)) . '.com';
                    $full_name        = $company_name . ' Administrator';
                    $role             = 'CompanyAdmin';
                    $user_status      = 'Active';

                    $user_stmt = $db->prepare("
                        INSERT INTO users (username, password, email, full_name, role, company_id, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $user_stmt->bind_param(
                        "sssssis",
                        $default_username,
                        $default_password,
                        $default_email,
                        $full_name,
                        $role,
                        $company_id,
                        $user_status
                    );
                    $user_stmt->execute();
                    $user_stmt->close();

                    log_activity($current_user['user_id'], 'Add Company', "Added company: $company_name");
                    $_SESSION['success'] = 'Company added successfully with default admin user (password: Admin@123)';
                } else {
                    $_SESSION['error'] = 'Error adding company';
                }
                break;

            case 'edit':
                $company_id          = (int)($_POST['company_id'] ?? 0);
                $company_name        = trim($_POST['company_name']        ?? '');
                $company_type        = trim($_POST['company_type']        ?? '');
                $registration_number = trim($_POST['registration_number'] ?? '') ?: null;
                $tax_number          = trim($_POST['tax_number']          ?? '') ?: null;
                $address             = trim($_POST['address']             ?? '') ?: null;
                $phone               = trim($_POST['phone']               ?? '') ?: null;
                $email               = trim($_POST['email']               ?? '') ?: null;
                $website             = trim($_POST['website']             ?? '') ?: null;
                $established_date    = trim($_POST['established_date']    ?? '') ?: null;
                $status              = trim($_POST['status']              ?? 'Active');

                $stmt = $db->prepare("
                        UPDATE companies SET
                            company_name = ?, company_type = ?, registration_number = ?,
                            tax_number = ?, address = ?, phone = ?, email = ?,
                            website = ?, established_date = ?, status = ?
                        WHERE company_id = ?
                    ");
                $stmt->bind_param(
                    "ssssssssssi",
                    $company_name,
                    $company_type,
                    $registration_number,
                    $tax_number,
                    $address,
                    $phone,
                    $email,
                    $website,
                    $established_date,
                    $status,
                    $company_id
                );

                if ($stmt->execute()) {
                    log_activity($current_user['user_id'], 'Edit Company', "Edited company ID: $company_id");
                    $_SESSION['success'] = 'Company updated successfully';
                } else {
                    $_SESSION['error'] = 'Error updating company';
                }
                $stmt->close();
                break;
            case 'delete':
                $company_id = intval($_POST['company_id']);

                // Check if company has users
                $check = $db->prepare("SELECT COUNT(*) as count FROM users WHERE company_id = ?");
                $check->bind_param("i", $company_id);
                $check->execute();
                $result = $check->get_result();
                $user_count = $result->fetch_assoc()['count'];

                if ($user_count > 0) {
                    $_SESSION['error'] = 'Cannot delete company with existing users. Deactivate instead.';
                } else {
                    $sql = "DELETE FROM companies WHERE company_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("i", $company_id);

                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Company deleted successfully';
                        log_activity($current_user['user_id'], 'Delete Company', "Deleted company ID: $company_id");
                    } else {
                        $_SESSION['error'] = 'Error deleting company';
                    }
                }
                break;
        }

        header('Location: companies.php');
        exit();
    }
}

// Get all companies
$companies_query = "SELECT c.*, 
                    (SELECT COUNT(*) FROM users WHERE company_id = c.company_id) as user_count,
                    (SELECT COUNT(*) FROM users WHERE company_id = c.company_id AND last_login IS NOT NULL) as active_users
                    FROM companies c 
                    ORDER BY c.created_at DESC";
$companies = $db->query($companies_query);

// Get company for editing
$edit_company = null;
if (isset($_GET['edit'])) {
    $company_id = intval($_GET['edit']);
    $edit_query = "SELECT * FROM companies WHERE company_id = ?";
    $stmt = $db->prepare($edit_query);
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $edit_company = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Management - <?php echo APP_NAME; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">

    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">

    <style>
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .company-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: transform 0.3s;
            border-left: 4px solid;
        }

        .company-card.estate {
            border-left-color: #4361ee;
        }

        .company-card.procurement {
            border-left-color: #4cc9f0;
        }

        .company-card.works {
            border-left-color: #f72585;
        }

        .company-card.blockfactory {
            border-left-color: #43e97b;
        }

        .company-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .company-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            margin-bottom: 15px;
        }

        .company-icon.estate {
            background: linear-gradient(135deg, #4361ee, #3f37c9);
        }

        .company-icon.procurement {
            background: linear-gradient(135deg, #4cc9f0, #4895ef);
        }

        .company-icon.works {
            background: linear-gradient(135deg, #f72585, #b5179e);
        }

        .company-icon.blockfactory {
            background: linear-gradient(135deg, #43e97b, #38f9d7);
        }

        .stats-badge {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
        }

        .stats-badge .number {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            display: block;
        }

        .stats-badge .label {
            font-size: 12px;
            color: #666;
        }

        .type-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="container-fluid p-0">
            <!-- Include sidebar -->
            <?php include '../includes/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Top Navigation -->
                <?php include '../includes/top-nav.php'; ?>

                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">Company Management</h4>
                        <p class="text-muted mb-0">Manage your companies and their settings</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                        <i class="fas fa-plus-circle me-2"></i>Add New Company
                    </button>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Companies Grid -->
                <div class="row">
                    <?php while ($company = $companies->fetch_assoc()):
                        $type_class = strtolower(str_replace(' ', '', $company['company_type']));
                    ?>
                        <div class="col-md-6">
                            <div class="company-card <?php echo $type_class; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="company-icon <?php echo $type_class; ?>">
                                            <?php
                                            $icons = [
                                                'Estate' => 'building',
                                                'Procurement' => 'truck',
                                                'Works' => 'tools',
                                                'Block Factory' => 'cubes'
                                            ];
                                            echo '<i class="fas fa-' . $icons[$company['company_type']] . '"></i>';
                                            ?>
                                        </div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($company['company_name']); ?></h5>
                                        <p class="text-muted mb-2">
                                            <span class="type-badge bg-<?php
                                                                        echo $company['company_type'] == 'Estate' ? 'primary' : ($company['company_type'] == 'Procurement' ? 'info' : ($company['company_type'] == 'Works' ? 'danger' : 'success'));
                                                                        ?> text-white">
                                                <?php echo $company['company_type']; ?>
                                            </span>
                                            <?php if ($company['status'] != 'Active'): ?>
                                                <span class="badge bg-secondary ms-2"><?php echo $company['status']; ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="?edit=<?php echo $company['company_id']; ?>">
                                                    <i class="fas fa-edit me-2"></i>Edit
                                                </a></li>
                                            <li><a class="dropdown-item" href="view-company.php?id=<?php echo $company['company_id']; ?>">
                                                    <i class="fas fa-eye me-2"></i>View Details
                                                </a></li>
                                            <li><a class="dropdown-item" href="users.php?company=<?php echo $company['company_id']; ?>">
                                                    <i class="fas fa-users me-2"></i>Manage Users
                                                </a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteCompany(<?php echo $company['company_id']; ?>, '<?php echo htmlspecialchars($company['company_name']); ?>')">
                                                    <i class="fas fa-trash me-2"></i>Delete
                                                </a></li>
                                        </ul>
                                    </div>
                                </div>

                                <p class="text-muted small mb-3">
                                    <i class="fas fa-map-marker-alt me-1"></i> <?php echo $company['address'] ?: 'No address'; ?>
                                </p>

                                <div class="row g-2 mb-3">
                                    <div class="col-4">
                                        <div class="stats-badge">
                                            <span class="number"><?php echo $company['user_count']; ?></span>
                                            <span class="label">Users</span>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stats-badge">
                                            <span class="number"><?php echo $company['active_users']; ?></span>
                                            <span class="label">Active</span>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="stats-badge">
                                            <span class="number"><?php echo date('Y', strtotime($company['established_date'] ?: $company['created_at'])); ?></span>
                                            <span class="label">Est.</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Registration</small>
                                        <span><?php echo $company['registration_number'] ?: 'N/A'; ?></span>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Tax Number</small>
                                        <span><?php echo $company['tax_number'] ?: 'N/A'; ?></span>
                                    </div>
                                </div>

                                <?php if ($company['phone'] || $company['email']): ?>
                                    <hr>
                                    <div class="row g-2">
                                        <?php if ($company['phone']): ?>
                                            <div class="col-6">
                                                <i class="fas fa-phone me-1 text-muted"></i> <?php echo $company['phone']; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($company['email']): ?>
                                            <div class="col-6">
                                                <i class="fas fa-envelope me-1 text-muted"></i> <?php echo $company['email']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Company Modal -->
    <div class="modal fade" id="addCompanyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="companies.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control" name="company_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Type</label>
                                <select class="form-control" name="company_type" required>
                                    <option value="Estate">Estate</option>
                                    <option value="Procurement">Procurement</option>
                                    <option value="Works">Works</option>
                                    <option value="Block Factory">Block Factory</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Registration Number</label>
                                <input type="text" class="form-control" name="registration_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tax Number</label>
                                <input type="text" class="form-control" name="tax_number">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Website</label>
                                <input type="url" class="form-control" name="website">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Established Date</label>
                                <input type="date" class="form-control" name="established_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            A default admin user will be created for this company.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Company</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Company Modal -->
    <?php if ($edit_company): ?>
        <div class="modal fade show" id="editCompanyModal" tabindex="-1" style="display: block;">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Company</h5>
                        <a href="companies.php" class="btn-close"></a>
                    </div>
                    <form action="companies.php" method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="company_id" value="<?php echo $edit_company['company_id']; ?>">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" class="form-control" name="company_name" value="<?php echo htmlspecialchars($edit_company['company_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company Type</label>
                                    <select class="form-control" name="company_type" required>
                                        <option value="Estate" <?php echo $edit_company['company_type'] == 'Estate' ? 'selected' : ''; ?>>Estate</option>
                                        <option value="Procurement" <?php echo $edit_company['company_type'] == 'Procurement' ? 'selected' : ''; ?>>Procurement</option>
                                        <option value="Works" <?php echo $edit_company['company_type'] == 'Works' ? 'selected' : ''; ?>>Works</option>
                                        <option value="Block Factory" <?php echo $edit_company['company_type'] == 'Block Factory' ? 'selected' : ''; ?>>Block Factory</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Registration Number</label>
                                    <input type="text" class="form-control" name="registration_number" value="<?php echo $edit_company['registration_number']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tax Number</label>
                                    <input type="text" class="form-control" name="tax_number" value="<?php echo $edit_company['tax_number']; ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2"><?php echo $edit_company['address']; ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" value="<?php echo $edit_company['phone']; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo $edit_company['email']; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Website</label>
                                    <input type="url" class="form-control" name="website" value="<?php echo $edit_company['website']; ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Established Date</label>
                                    <input type="date" class="form-control" name="established_date" value="<?php echo $edit_company['established_date']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-control" name="status">
                                        <option value="Active" <?php echo $edit_company['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="Inactive" <?php echo $edit_company['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="companies.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Company</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete company <strong id="deleteCompanyName"></strong>?</p>
                    <p class="text-danger">This will also delete all associated data. Consider deactivating instead.</p>
                </div>
                <div class="modal-footer">
                    <form action="companies.php" method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="company_id" id="deleteCompanyId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Company</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>

    <script>
        function deleteCompany(id, name) {
            $('#deleteCompanyId').val(id);
            $('#deleteCompanyName').text(name);
            $('#deleteModal').modal('show');
        }
    </script>
</body>

</html>