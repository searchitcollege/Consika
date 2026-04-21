<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db_connection.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

$session->requireLogin();

$current_user = $session->getCurrentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

// Access check
if ($current_user['company_type'] != 'Procurement' && ($role != 'SuperAdmin' && $role != 'CompanyAdmin' && $role != 'Manager')) {
    $_SESSION['error'] = 'Access denied. Procurement department only.';
    header('Location: ../../login.php');
    exit();
}

global $db;

// Handle product actions (add)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        // Process add supplier form
        $supplier_code = $db->escapeString($_POST['supplier_code']);
        $supplier_name = $db->escapeString($_POST['supplier_name']);
        $contact_person = $db->escapeString($_POST['contact_person']);
        $phone = $db->escapeString($_POST['phone']);
        $email = $db->escapeString($_POST['email']);
        $website = $db->escapeString($_POST['website']);
        $address = $db->escapeString($_POST['address']);
        $category = $db->escapeString($_POST['category']);
        $tax_number = $db->escapeString($_POST['tax_number']);
        $payment_terms = $db->escapeString($_POST['payment_terms']);
        $credit_limit = floatval($_POST['credit_limit']);

        // Insert into database
        $sql = "INSERT INTO procurement_suppliers 
                (company_id, supplier_code, supplier_name, contact_person, phone, email, website, address, category, tax_number, payment_terms, credit_limit, admin_approvals) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("issssssssssd", $company_id, $supplier_code, $supplier_name, $contact_person, $phone, $email, $website, $address, $category, $tax_number, $payment_terms, $credit_limit);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Supplier added successfully";
            log_activity(
                $current_user['user_id'],
                'Add Supplier',
                "Added supplier: $supplier_name"
            );
        } else {
            $_SESSION['error'] = "Error adding supplier";
        }
        header('Location: suppliers.php');
        exit();
    }
}

// Fetch suppliers for this company
$stmt = $db->prepare("SELECT * FROM procurement_suppliers WHERE company_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$suppliers = $stmt->get_result();

// Get supplier-specific statistics
$stats = [];

// Total suppliers
$stmt = $db->prepare("SELECT COUNT(*) as count FROM procurement_suppliers WHERE company_id = ? AND status = 'Active'");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['total_suppliers'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Top suppliers by order value
$top_suppliers = [];
$top_query = "SELECT s.supplier_name, COUNT(po.po_id) as order_count, COALESCE(SUM(po.total_amount), 0) as total_value
              FROM procurement_suppliers s
              LEFT JOIN procurement_purchase_orders po ON s.supplier_id = po.supplier_id
              WHERE s.company_id = ? AND s.status = 'Active'
              GROUP BY s.supplier_id
              ORDER BY total_value DESC
              LIMIT 5";
$stmt = $db->prepare($top_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$top_suppliers = $stmt->get_result();

$page_title = 'Suppliers';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procurement Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="../../assets/css/style.css" rel="stylesheet">

    <!-- Custom CSS For jus Procuremnt Pages-->
    <link href="../../assets/css/procurement/style.css" rel="stylesheet">

</head>

<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Include sidebar -->
            <?php include '../../includes/sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col">
                <div class="main-content">
                    <!-- Header -->
                    <div class="module-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-2">Procurement - Suppliers</h2>
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

                    <!-- Stats Row -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <h3 class="mb-1"><?php echo $stats['total_suppliers']; ?></h3>
                                <p class="text-muted mb-0">Active Suppliers</p>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up me-1"></i>12% this month
                                </small>
                            </div>
                        </div>

                        <div class="col-md-9">
                            <div class="stat-card">
                                <h5 class="mb-3">Top Suppliers</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Supplier</th>
                                                <th>Orders</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($supplier = $top_suppliers->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                                                    <td><?php echo $supplier['order_count']; ?></td>
                                                    <td><?php echo formatMoney($supplier['total_value']); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions
                        <div class="quick-actions mt-4">
                            <div class="quick-action-btn" onclick="window.location.href='add-supplier.php'">
                                <i class="fas fa-plus-circle"></i>
                                <span>Add Supplier</span>
                                <small class="text-muted d-block">Register new supplier</small>
                            </div>
                        </div> -->

                        <!-- Suppliers Table -->
                        <div class="card supplier-table mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Suppliers</h5>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                                    <i class="fas fa-plus-circle me-2"></i>Add Supplier
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="suppliersTable">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Supplier Name</th>
                                                <th>Contact Person</th>
                                                <th>Phone</th>
                                                <th>Email</th>
                                                <th>Category</th>
                                                <th>Rating</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $suppliers_query = "SELECT * FROM procurement_suppliers 
                                                           WHERE company_id = ? 
                                                           ORDER BY supplier_name ASC";
                                            $stmt = $db->prepare($suppliers_query);
                                            $stmt->bind_param("i", $company_id);
                                            $stmt->execute();
                                            $suppliers = $stmt->get_result();
                                            while ($supplier = $suppliers->fetch_assoc()):
                                            ?>
                                                <tr>
                                                    <td><?php echo $supplier['supplier_code']; ?></td>
                                                    <td><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($supplier['contact_person']); ?></td>
                                                    <td><?php echo $supplier['phone']; ?></td>
                                                    <td><?php echo $supplier['email']; ?></td>
                                                    <td><?php echo $supplier['category']; ?></td>
                                                    <td>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= $supplier['rating'] ? 'text-warning' : 'text-secondary'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $supplier['status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo $supplier['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary" onclick="viewEntity('../../api/get_supplier.php',<?php echo $supplier['supplier_id']; ?>,'Supplier')">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <!-- <button class="btn btn-sm btn-primary" onclick="editSupplier(<?php echo $supplier['supplier_id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button> -->
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Add Supplier Modal -->
                        <div class="modal fade" id="addSupplierModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add New Supplier</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form action="suppliers.php" method="POST">
                                        <input type="hidden" name="action" value="add">
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Supplier Code</label>
                                                    <input type="text" class="form-control" name="supplier_code" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Supplier Name</label>
                                                    <input type="text" class="form-control" name="supplier_name" required>
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
                                                    <label class="form-label">Website</label>
                                                    <input type="url" class="form-control" name="website">
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Address</label>
                                                <textarea class="form-control" name="address" rows="2"></textarea>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Category</label>
                                                    <input type="text" class="form-control" name="category" placeholder="e.g., Building Materials, Electronics">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Tax Number</label>
                                                    <input type="text" class="form-control" name="tax_number">
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Payment Terms</label>
                                                    <select class="form-control" name="payment_terms">
                                                        <option value="Cash on Delivery">Cash on Delivery</option>
                                                        <option value="Net 15">Net 15</option>
                                                        <option value="Net 30">Net 30</option>
                                                        <option value="Net 60">Net 60</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Credit Limit</label>
                                                    <input type="number" step="0.01" class="form-control" name="credit_limit">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Add Supplier</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="viewModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="modalBody"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>

    <script>
        // / FOR REUSABK MODAL VIEWING INSTANCE
        let viewModalInstance;

        function showModal(title, data) {
            let html = '<div class="container-fluid">';
            for (const key in data) {
                if (Array.isArray(data[key])) {
                    // For nested arrays (like PO line items)
                    html += `<h6 class="mt-3">${key.replace(/_/g,' ')}</h6><ul>`;
                    data[key].forEach(item => {
                        html += '<li>' + Object.entries(item)
                            .map(([k, v]) => `${k}: ${v}`)
                            .join(', ') + '</li>';
                    });
                    html += '</ul>';
                } else {
                    html += `<p><strong>${key.replace(/_/g,' ')}</strong>: ${data[key]}</p>`;
                }
            }
            html += '</div>';

            document.getElementById('modalBody').innerHTML = html;
            document.querySelector('#viewModal .modal-title').innerText = title;

            if (!viewModalInstance) {
                viewModalInstance = new bootstrap.Modal(document.getElementById('viewModal'));
            }
            viewModalInstance.show();
        }

        function viewEntity(endpoint, id, title) {
            fetch(`${endpoint}?id=${id}`)
                .then(res => res.json())
                .then(data => showModal(title, data))
                .catch(err => alert('Error loading data: ' + err));
        }

// /////////////////////////////////////////////////////

        function reorderProduct(id) {
            window.location.href = 'create-po.php?product=' + id;
        }

        $(document).ready(function() {
            // Initialize DataTables
            $('#suppliersTable').DataTable();
        });

        function editSupplier(id) {
            window.location.href = '../../api/edit-supplier.php?id=' + id;
        }
    </script>
</body>

</html>