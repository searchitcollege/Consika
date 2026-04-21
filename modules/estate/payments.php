<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db_connection.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

$session->requireLogin();

$current_user = $session->getCurrentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

// Ensure only Estate users can access
if ($current_user['company_type'] != 'Estate') {
    $_SESSION['error'] = 'Access denied. Estate department only.';
    header('Location: ../../api/logout.php');
    exit();
}

global $db;

// Handle payment actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $tenant_id = intval($_POST['tenant_id']);
                $amount = floatval($_POST['amount']);
                $payment_date = $_POST['payment_date'];
                $payment_method = $db->escapeString($_POST['payment_method']);
                $transaction_ref = $db->escapeString($_POST['transaction_ref']);
                $period_start = $_POST['period_start'];
                $period_end = $_POST['period_end'];
                $notes = $db->escapeString($_POST['notes']);
                
                // Get property_id from tenant
                $prop_query = "SELECT property_id, monthly_rent FROM estate_tenants WHERE tenant_id = ?";
                $stmt = $db->prepare($prop_query);
                $stmt->bind_param("i", $tenant_id);
                $stmt->execute();
                $tenant = $stmt->get_result()->fetch_assoc();
                $property_id = $tenant['property_id'];
                
                // Generate receipt number
                $receipt_no = 'RCT-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                
                $sql = "INSERT INTO estate_payments (tenant_id, property_id, amount, payment_date, payment_method, 
                        transaction_reference, payment_period_start, payment_period_end, notes, receipt_number, recorded_by, admin_approvals) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("iidsssssssi", $tenant_id, $property_id, $amount, $payment_date, $payment_method,
                                 $transaction_ref, $period_start, $period_end, $notes, $receipt_no, $current_user['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Payment recorded successfully. Receipt: ' . $receipt_no;
                    log_activity($current_user['user_id'], 'Record Payment', "Recorded payment of " . formatMoney($amount) . " for tenant ID: $tenant_id");
                } else {
                    $_SESSION['error'] = 'Error recording payment: ' . $db->error();
                }
                break;
                
            case 'delete':
                $payment_id = intval($_POST['payment_id']);
                
                $sql = "DELETE FROM estate_payments WHERE payment_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("i", $payment_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Payment deleted successfully';
                    log_activity($current_user['user_id'], 'Delete Payment', "Deleted payment ID: $payment_id");
                } else {
                    $_SESSION['error'] = 'Error deleting payment';
                }
                break;
        }
        header('Location: payments.php');
        exit();
    }
}

// Get filter parameters
$tenant_filter = isset($_GET['tenant_id']) ? intval($_GET['tenant_id']) : 0;
$property_filter = isset($_GET['property_id']) ? intval($_GET['property_id']) : 0;
$month_filter = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get all payments
$query = "SELECT p.*, t.full_name as tenant_name, t.tenant_code, pr.property_name, pr.property_code,
          u.full_name as recorded_by_name
          FROM estate_payments p
          JOIN estate_tenants t ON p.tenant_id = t.tenant_id
          JOIN estate_properties pr ON p.property_id = pr.property_id
          LEFT JOIN users u ON p.recorded_by = u.user_id
          WHERE pr.company_id = ?";
$params = [$company_id];
$types = "i";

if ($tenant_filter > 0) {
    $query .= " AND p.tenant_id = ?";
    $params[] = $tenant_filter;
    $types .= "i";
}
if ($property_filter > 0) {
    $query .= " AND p.property_id = ?";
    $params[] = $property_filter;
    $types .= "i";
}
if (isset($_GET['month']) && $_GET['month'] != '') {
    $query .= " AND DATE_FORMAT(p.payment_date, '%Y-%m') = ?";
    $params[] = $month_filter;
    $types .= "s";
}
$query .= " ORDER BY p.payment_date DESC";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$payments = $stmt->get_result();

// Get summary statistics
$summary_query = "SELECT 
                  COUNT(*) as total_payments,
                  COALESCE(SUM(amount), 0) as total_amount,
                  COALESCE(AVG(amount), 0) as avg_amount,
                  COUNT(DISTINCT tenant_id) as unique_tenants
                  FROM estate_payments p
                  JOIN estate_properties pr ON p.property_id = pr.property_id
                  WHERE pr.company_id = ? AND MONTH(p.payment_date) = MONTH(CURDATE()) AND YEAR(p.payment_date) = YEAR(CURDATE())";
$stmt = $db->prepare($summary_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Get properties for filter
$properties = $db->query("SELECT property_id, property_name FROM estate_properties WHERE company_id = $company_id ORDER BY property_name");

// Get tenants for filter - FIXED: Join with properties table to get company_id
$tenants = $db->query("SELECT t.tenant_id, t.full_name 
                      FROM estate_tenants t
                      JOIN estate_properties p ON t.property_id = p.property_id
                      WHERE p.company_id = $company_id 
                      ORDER BY t.full_name");

$page_title = 'Payments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Estate Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
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
            border-left: 4px solid #4361ee;
        }
        .stats-card h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
        .filter-section {
            background: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .receipt-link {
            color: #4361ee;
            text-decoration: none;
            cursor: pointer;
        }
        .receipt-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0">
                <?php include dirname(__DIR__, 2) . '/includes/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">Payments Management</h4>
                        <p class="text-muted mb-0">Record and track rent payments</p>
                    </div>
                    <div>
                        <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                            <i class="fas fa-bars"></i>
                        </button>
                        <!-- <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                            <i class="fas fa-plus-circle me-2"></i>Record New Payment
                        </button> -->
                    </div>
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
                            <p class="text-muted mb-1">This Month's Payments</p>
                            <h3><?php echo intval($summary['total_payments'] ?? 0); ?></h3>
                            <small><?php echo intval($summary['unique_tenants'] ?? 0); ?> tenants paid</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <p class="text-muted mb-1">Total Collected</p>
                            <h3><?php echo formatMoney($summary['total_amount'] ?? 0); ?></h3>
                            <small>This month</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <p class="text-muted mb-1">Average Payment</p>
                            <h3><?php echo formatMoney($summary['avg_amount'] ?? 0); ?></h3>
                            <small>Per transaction</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <p class="text-muted mb-1">Collection Rate</p>
                            <h3>85%</h3>
                            <small>Target: 95%</small>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Filter by Tenant</label>
                            <select class="form-control" name="tenant_id" onchange="this.form.submit()">
                                <option value="">All Tenants</option>
                                <?php 
                                // Reset tenants pointer
                                $tenants->data_seek(0);
                                while($tenant = $tenants->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $tenant['tenant_id']; ?>" <?php echo $tenant_filter == $tenant['tenant_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tenant['full_name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filter by Property</label>
                            <select class="form-control" name="property_id" onchange="this.form.submit()">
                                <option value="">All Properties</option>
                                <?php 
                                // Reset properties pointer
                                $properties->data_seek(0);
                                while($prop = $properties->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $prop['property_id']; ?>" <?php echo $property_filter == $prop['property_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prop['property_name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Month</label>
                            <input type="month" class="form-control" name="month" value="<?php echo $month_filter; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="payments.php" class="btn btn-secondary">Clear Filters</a>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-success" onclick="exportPayments()">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Payments Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="paymentsTable">
                                <thead>
                                    <tr>
                                        <th>Receipt No.</th>
                                        <th>Date</th>
                                        <th>Tenant</th>
                                        <th>Property</th>
                                        <th>Period</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Recorded By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($payments && $payments->num_rows > 0): ?>
                                        <?php while($payment = $payments->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong class="receipt-link" onclick="printReceipt(<?php echo $payment['payment_id']; ?>)">
                                                    <?php echo $payment['receipt_number']; ?>
                                                </strong>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($payment['tenant_name']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo $payment['tenant_code']; ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($payment['property_name']); ?></td>
                                            <td>
                                                <?php echo date('d/m', strtotime($payment['payment_period_start'])); ?> - 
                                                <?php echo date('d/m/y', strtotime($payment['payment_period_end'])); ?>
                                            </td>
                                            <td><strong><?php echo formatMoney($payment['amount']); ?></strong></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $payment['payment_method']; ?></span>
                                            </td>
                                            <td><?php echo $payment['recorded_by_name'] ?? 'System'; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="printReceipt(<?php echo $payment['payment_id']; ?>)" title="Print Receipt">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                                <?php if ($role == 'SuperAdmin'): ?>
                                                <button class="btn btn-sm btn-danger" onclick="deletePayment(<?php echo $payment['payment_id']; ?>, '<?php echo $payment['receipt_number']; ?>')" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No payment records found</p>
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
    
    <!-- Add Payment Modal -->
    <div class="modal fade" id="addPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record New Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Select Tenant</label>
                                <select class="form-control" name="tenant_id" id="tenantSelect" required>
                                    <option value="">Choose Tenant</option>
                                    <?php 
                                    // Get active tenants for dropdown with proper company filtering
                                    $tenant_list = $db->query("SELECT t.tenant_id, t.full_name, t.monthly_rent, p.property_name 
                                                              FROM estate_tenants t
                                                              JOIN estate_properties p ON t.property_id = p.property_id
                                                              WHERE t.status = 'Active' AND p.company_id = $company_id
                                                              ORDER BY t.full_name");
                                    if ($tenant_list && $tenant_list->num_rows > 0):
                                        while($t = $tenant_list->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $t['tenant_id']; ?>" 
                                            data-rent="<?php echo $t['monthly_rent']; ?>"
                                            data-property="<?php echo $t['property_name']; ?>">
                                        <?php echo $t['full_name']; ?> - <?php echo $t['property_name']; ?>
                                    </option>
                                    <?php 
                                        endwhile;
                                    endif;
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Date</label>
                                <input type="date" class="form-control" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount</label>
                                <input type="number" step="0.01" class="form-control" name="amount" id="paymentAmount" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method</label>
                                <select class="form-control" name="payment_method" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Mobile Money">Mobile Money</option>
                                    <option value="Credit Card">Credit Card</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Period Start</label>
                                <input type="date" class="form-control" name="period_start" id="period_start" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Period End</label>
                                <input type="date" class="form-control" name="period_end" id="period_end" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Transaction Reference (Optional)</label>
                            <input type="text" class="form-control" name="transaction_ref" placeholder="Cheque no./Transaction ID">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Record Payment</button>
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
                    <p>Are you sure you want to delete payment <strong id="deleteReceipt"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="payment_id" id="deletePaymentId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Payment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#paymentsTable').DataTable({
                order: [[1, 'desc']]
            });
            
            // Auto-fill amount when tenant selected
            $('#tenantSelect').change(function() {
                var rent = $(this).find(':selected').data('rent');
                if (rent) {
                    $('#paymentAmount').val(rent);
                    
                    // Auto-fill period with current month
                    var today = new Date();
                    var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                    var lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    
                    // Format dates as YYYY-MM-DD
                    var firstDayStr = firstDay.toISOString().split('T')[0];
                    var lastDayStr = lastDay.toISOString().split('T')[0];
                    
                    $('input[name="period_start"]').val(firstDayStr);
                    $('input[name="period_end"]').val(lastDayStr);
                }
            });
            
            // Set default period to current month if not set
            if (!$('input[name="period_start"]').val()) {
                var today = new Date();
                var firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
                var lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                
                $('input[name="period_start"]').val(firstDay.toISOString().split('T')[0]);
                $('input[name="period_end"]').val(lastDay.toISOString().split('T')[0]);
            }
        });
        
        function printReceipt(id) {
            window.open('../../api/receipt.php?id=' + id, '_blank', 'width=800,height=600');
        }
        
        function deletePayment(id, receipt) {
            $('#deletePaymentId').val(id);
            $('#deleteReceipt').text(receipt);
            $('#deleteModal').modal('show');
        }
        
        function exportPayments() {
            var month = $('input[name="month"]').val();
            window.location.href = 'export-payments.php?month=' + month;
        }
    </script>
</body>
</html>