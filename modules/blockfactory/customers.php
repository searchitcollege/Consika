<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db_connection.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

$session->requireLogin();

$current_user = $session->getCurrentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

// Ensure only Block Factory users can access
if ($current_user['company_type'] != 'Block Factory' && $role != 'SuperAdmin') {
    $_SESSION['error'] = 'Access denied. Block Factory department only.';
    header('Location: ../../login.php');
    exit();
}

global $db;

// Handle customer actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $customer_code = $db->escapeString($_POST['customer_code']);
                $customer_name = $db->escapeString($_POST['customer_name']);
                $contact_person = $db->escapeString($_POST['contact_person']);
                $phone = $db->escapeString($_POST['phone']);
                $email = $db->escapeString($_POST['email']);
                $address = $db->escapeString($_POST['address']);
                $city = $db->escapeString($_POST['city']);
                $customer_type = $db->escapeString($_POST['customer_type']);
                $tax_number = $db->escapeString($_POST['tax_number']);
                $credit_limit = floatval($_POST['credit_limit'] ?? 0);
                $payment_terms = $db->escapeString($_POST['payment_terms']);
                $notes = $db->escapeString($_POST['notes']);
                
                $sql = "INSERT INTO blockfactory_customers (customer_code, customer_name, contact_person, phone, email, 
                        address, city, customer_type, tax_number, credit_limit, payment_terms, notes, status, admin_approvals) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', 'Pending)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("sssssssssdss", $customer_code, $customer_name, $contact_person, $phone, $email,
                                 $address, $city, $customer_type, $tax_number, $credit_limit, $payment_terms, $notes);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Customer added successfully';
                    log_activity($current_user['user_id'], 'Add Customer', "Added customer: $customer_name");
                } else {
                    $_SESSION['error'] = 'Error adding customer: ' . $db->error();
                }
                break;
                
            case 'edit':
                $customer_id = intval($_POST['customer_id']);
                $customer_name = $db->escapeString($_POST['customer_name']);
                $contact_person = $db->escapeString($_POST['contact_person']);
                $phone = $db->escapeString($_POST['phone']);
                $email = $db->escapeString($_POST['email']);
                $address = $db->escapeString($_POST['address']);
                $city = $db->escapeString($_POST['city']);
                $customer_type = $db->escapeString($_POST['customer_type']);
                $tax_number = $db->escapeString($_POST['tax_number']);
                $credit_limit = floatval($_POST['credit_limit'] ?? 0);
                $payment_terms = $db->escapeString($_POST['payment_terms']);
                $notes = $db->escapeString($_POST['notes']);
                $status = $db->escapeString($_POST['status']);
                
                $sql = "UPDATE blockfactory_customers SET 
                        customer_name = ?, contact_person = ?, phone = ?, email = ?, 
                        address = ?, city = ?, customer_type = ?, tax_number = ?, 
                        credit_limit = ?, payment_terms = ?, notes = ?, status = ? 
                        WHERE customer_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("ssssssssdsssi", $customer_name, $contact_person, $phone, $email,
                                 $address, $city, $customer_type, $tax_number, $credit_limit,
                                 $payment_terms, $notes, $status, $customer_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Customer updated successfully';
                    log_activity($current_user['user_id'], 'Edit Customer', "Edited customer ID: $customer_id");
                } else {
                    $_SESSION['error'] = 'Error updating customer';
                }
                break;
                
            case 'delete':
                $customer_id = intval($_POST['customer_id']);
                
                // Check if customer has sales
                $check = $db->prepare("SELECT COUNT(*) as count FROM blockfactory_sales WHERE customer_id = ?");
                $check->bind_param("i", $customer_id);
                $check->execute();
                $sales_count = $check->get_result()->fetch_assoc()['count'];
                
                if ($sales_count > 0) {
                    $_SESSION['error'] = 'Cannot delete customer with existing sales records';
                } else {
                    $sql = "DELETE FROM blockfactory_customers WHERE customer_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("i", $customer_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Customer deleted successfully';
                        log_activity($current_user['user_id'], 'Delete Customer', "Deleted customer ID: $customer_id");
                    } else {
                        $_SESSION['error'] = 'Error deleting customer';
                    }
                }
                break;
        }
        header('Location: customers.php');
        exit();
    }
}

// Get filter parameters
$type_filter = isset($_GET['type']) ? $db->escapeString($_GET['type']) : '';
$status_filter = isset($_GET['status']) ? $db->escapeString($_GET['status']) : '';

// Build query for customers
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM blockfactory_sales WHERE customer_id = c.customer_id) as total_sales,
          (SELECT COALESCE(SUM(total_amount), 0) FROM blockfactory_sales WHERE customer_id = c.customer_id) as total_purchases,
          (SELECT COALESCE(SUM(balance), 0) FROM blockfactory_sales WHERE customer_id = c.customer_id AND payment_status != 'Paid') as outstanding_balance
          FROM blockfactory_customers c
          WHERE 1=1";
$params = [];
$types = "";

if (!empty($type_filter)) {
    $query .= " AND c.customer_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}
if (!empty($status_filter)) {
    $query .= " AND c.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
$query .= " ORDER BY c.customer_name ASC";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$customers = $stmt->get_result();

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_customers,
                COUNT(CASE WHEN status = 'Active' THEN 1 END) as active_customers,
                COUNT(CASE WHEN customer_type = 'Company' THEN 1 END) as companies,
                COUNT(CASE WHEN customer_type = 'Individual' THEN 1 END) as individuals,
                COALESCE(SUM(CASE WHEN status = 'Active' THEN credit_limit ELSE 0 END), 0) as total_credit_limit
                FROM blockfactory_customers";
$stats = $db->query($stats_query)->fetch_assoc();

$page_title = 'Customers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Block Factory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #43e97b;
            --secondary-color: #38f9d7;
        }
        
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }
        
        .stats-card h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        
        .filter-section {
            background: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        .type-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e2e3e5;
            color: #383d41;
        }
        
        .customer-avatar {
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
        
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #1e1e2f, #2a2a40);
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            padding-left: 30px;
        }
        
        .nav-link.active {
            background: var(--primary-color);
            color: white;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-auto">
                <?php include dirname(__DIR__, 2) . '/includes/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col">
                <div class="main-content">
                    <!-- Page Header -->
                    <div class="page-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">Customer Management</h4>
                            <p class="text-muted mb-0">Manage your customers and clients</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                            <i class="fas fa-user-plus me-2"></i>Add New Customer
                        </button>
                    </div>
                    
                    <!-- Alert Messages -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Stats Cards -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Total Customers</p>
                                <h3><?php echo intval($stats['total_customers'] ?? 0); ?></h3>
                                <small>Registered customers</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Active Customers</p>
                                <h3><?php echo intval($stats['active_customers'] ?? 0); ?></h3>
                                <small>Currently active</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Companies</p>
                                <h3><?php echo intval($stats['companies'] ?? 0); ?></h3>
                                <small>Business clients</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Credit Limit</p>
                                <h3><?php echo formatMoney($stats['total_credit_limit'] ?? 0); ?></h3>
                                <small>Total available credit</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Customer Type</label>
                                <select class="form-control" name="type" onchange="this.form.submit()">
                                    <option value="">All Types</option>
                                    <option value="Individual" <?php echo $type_filter == 'Individual' ? 'selected' : ''; ?>>Individual</option>
                                    <option value="Company" <?php echo $type_filter == 'Company' ? 'selected' : ''; ?>>Company</option>
                                    <option value="Contractor" <?php echo $type_filter == 'Contractor' ? 'selected' : ''; ?>>Contractor</option>
                                    <option value="Government" <?php echo $type_filter == 'Government' ? 'selected' : ''; ?>>Government</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="Active" <?php echo $status_filter == 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo $status_filter == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <a href="customers.php" class="btn btn-secondary">Clear Filters</a>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="button" class="btn btn-success" onclick="exportCustomers()">
                                    <i class="fas fa-download me-2"></i>Export
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Customers Table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="customersTable">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Contact</th>
                                            <th>Type</th>
                                            <th>Total Sales</th>
                                            <th>Total Purchases</th>
                                            <th>Outstanding</th>
                                            <th>Credit Limit</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($customers && $customers->num_rows > 0): ?>
                                            <?php while($customer = $customers->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="customer-avatar me-2">
                                                            <?php echo getAvatarLetter($customer['customer_name']); ?>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($customer['customer_name']); ?></strong>
                                                            <br>
                                                            <small class="text-muted"><?php echo $customer['customer_code']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($customer['contact_person']): ?>
                                                        <strong><?php echo htmlspecialchars($customer['contact_person']); ?></strong><br>
                                                    <?php endif; ?>
                                                    <i class="fas fa-phone me-1"></i><?php echo $customer['phone']; ?><br>
                                                    <i class="fas fa-envelope me-1"></i><?php echo $customer['email']; ?>
                                                </td>
                                                <td>
                                                    <span class="type-badge"><?php echo $customer['customer_type']; ?></span>
                                                </td>
                                                <td><?php echo $customer['total_sales']; ?></td>
                                                <td><?php echo formatMoney($customer['total_purchases'] ?? 0); ?></td>
                                                <td>
                                                    <?php if ($customer['outstanding_balance'] > 0): ?>
                                                        <span class="text-danger fw-bold"><?php echo formatMoney($customer['outstanding_balance']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-success"><?php echo formatMoney(0); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatMoney($customer['credit_limit']); ?></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo strtolower($customer['status']); ?>">
                                                        <?php echo $customer['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewCustomer(<?php echo $customer['customer_id']; ?>)" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="editCustomer(<?php echo $customer['customer_id']; ?>)" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="newSale(<?php echo $customer['customer_id']; ?>)" title="New Sale">
                                                        <i class="fas fa-shopping-cart"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" onclick="viewStatement(<?php echo $customer['customer_id']; ?>)" title="Statement">
                                                        <i class="fas fa-file-invoice"></i>
                                                    </button>
                                                    <?php if ($role == 'SuperAdmin' && $customer['total_sales'] == 0): ?>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteCustomer(<?php echo $customer['customer_id']; ?>, '<?php echo $customer['customer_name']; ?>')" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
                                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No customers found</p>
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
        </div>
    </div>
    
    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Code</label>
                                <input type="text" class="form-control" name="customer_code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Name</label>
                                <input type="text" class="form-control" name="customer_name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Person</label>
                                <input type="text" class="form-control" name="contact_person">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Type</label>
                                <select class="form-control" name="customer_type" required>
                                    <option value="Individual">Individual</option>
                                    <option value="Company">Company</option>
                                    <option value="Contractor">Contractor</option>
                                    <option value="Government">Government</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tax Number</label>
                                <input type="text" class="form-control" name="tax_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Credit Limit</label>
                                <input type="number" step="0.01" class="form-control" name="credit_limit" value="0">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Terms</label>
                                <select class="form-control" name="payment_terms">
                                    <option value="">Select Terms</option>
                                    <option value="Cash on Delivery">Cash on Delivery</option>
                                    <option value="Net 15">Net 15</option>
                                    <option value="Net 30">Net 30</option>
                                    <option value="Net 45">Net 45</option>
                                    <option value="Net 60">Net 60</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Customer Modal -->
    <div class="modal fade" id="editCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editCustomerForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="customer_id" id="edit_customer_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Code</label>
                                <input type="text" class="form-control" id="edit_customer_code" readonly disabled>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Name</label>
                                <input type="text" class="form-control" name="customer_name" id="edit_customer_name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Person</label>
                                <input type="text" class="form-control" name="contact_person" id="edit_contact_person">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" id="edit_phone" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" id="edit_email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Type</label>
                                <select class="form-control" name="customer_type" id="edit_customer_type" required>
                                    <option value="Individual">Individual</option>
                                    <option value="Company">Company</option>
                                    <option value="Contractor">Contractor</option>
                                    <option value="Government">Government</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address" id="edit_address">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" id="edit_city">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tax Number</label>
                                <input type="text" class="form-control" name="tax_number" id="edit_tax_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Credit Limit</label>
                                <input type="number" step="0.01" class="form-control" name="credit_limit" id="edit_credit_limit">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Terms</label>
                                <select class="form-control" name="payment_terms" id="edit_payment_terms">
                                    <option value="">Select Terms</option>
                                    <option value="Cash on Delivery">Cash on Delivery</option>
                                    <option value="Net 15">Net 15</option>
                                    <option value="Net 30">Net 30</option>
                                    <option value="Net 45">Net 45</option>
                                    <option value="Net 60">Net 60</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status" id="edit_status" required>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Customer</button>
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
                    <p>Are you sure you want to delete <strong id="deleteCustomerName"></strong>?</p>
                    <p class="text-danger">This can only be done if customer has no sales records.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="customer_id" id="deleteCustomerId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Customer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#customersTable').DataTable({
                order: [[0, 'asc']],
                pageLength: 25
            });
        });
        
        function viewCustomer(id) {
            window.location.href = 'customer-details.php?id=' + id;
        }
        
        function editCustomer(id) {
            $.get('ajax/get-customer.php', {id: id}, function(data) {
                $('#edit_customer_id').val(data.customer_id);
                $('#edit_customer_code').val(data.customer_code);
                $('#edit_customer_name').val(data.customer_name);
                $('#edit_contact_person').val(data.contact_person);
                $('#edit_phone').val(data.phone);
                $('#edit_email').val(data.email);
                $('#edit_customer_type').val(data.customer_type);
                $('#edit_address').val(data.address);
                $('#edit_city').val(data.city);
                $('#edit_tax_number').val(data.tax_number);
                $('#edit_credit_limit').val(data.credit_limit);
                $('#edit_payment_terms').val(data.payment_terms);
                $('#edit_status').val(data.status);
                $('#edit_notes').val(data.notes);
                $('#editCustomerModal').modal('show');
            }, 'json');
        }
        
        function newSale(id) {
            window.location.href = 'sales.php?customer_id=' + id;
        }
        
        function viewStatement(id) {
            window.location.href = 'customer-statement.php?id=' + id;
        }
        
        function deleteCustomer(id, name) {
            $('#deleteCustomerId').val(id);
            $('#deleteCustomerName').text(name);
            $('#deleteModal').modal('show');
        }
        
        function exportCustomers() {
            var type = '<?php echo $type_filter; ?>';
            var status = '<?php echo $status_filter; ?>';
            window.location.href = 'export-customers.php?type=' + type + '&status=' + status;
        }
    </script>
</body>
</html>