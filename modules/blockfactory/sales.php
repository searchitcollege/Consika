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

// Handle sales actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
                $customer_name = $db->escapeString($_POST['customer_name'] ?? 'Walk-in Customer');
                $customer_phone = $db->escapeString($_POST['customer_phone'] ?? '');
                $sale_date = $_POST['sale_date'];
                $product_id = intval($_POST['product_id']);
                $quantity = intval($_POST['quantity']);
                $unit_price = floatval($_POST['unit_price']);
                $discount = floatval($_POST['discount'] ?? 0);
                $payment_method = $db->escapeString($_POST['payment_method']);
                $amount_paid = floatval($_POST['amount_paid'] ?? 0);
                $delivery_address = $db->escapeString($_POST['delivery_address'] ?? '');
                $notes = $db->escapeString($_POST['notes'] ?? '');
                
                // Calculate totals
                $subtotal = $quantity * $unit_price;
                $tax = $subtotal * 0.16; // 16% VAT
                $total_amount = $subtotal + $tax - $discount;
                
                // Check stock availability
                $stock_check = $db->prepare("SELECT current_stock FROM blockfactory_products WHERE product_id = ? AND company_id = ?");
                $stock_check->bind_param("ii", $product_id, $company_id);
                $stock_check->execute();
                $stock = $stock_check->get_result()->fetch_assoc();
                
                if ($stock['current_stock'] < $quantity) {
                    $_SESSION['error'] = 'Insufficient stock. Available: ' . $stock['current_stock'];
                } else {
                    // Generate invoice number
                    $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    $sql = "INSERT INTO blockfactory_sales (invoice_number, customer_id, customer_name, customer_phone, 
                            sale_date, product_id, quantity, unit_price, discount, subtotal, tax_amount, total_amount, 
                            amount_paid, balance, payment_method, payment_status, delivery_address, notes, sales_person) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    
                    $balance = $total_amount - $amount_paid;
                    $payment_status = $balance <= 0 ? 'Paid' : ($amount_paid > 0 ? 'Partial' : 'Unpaid');
                    
                    $stmt->bind_param("sisssiidddddddssssi", $invoice_number, $customer_id, $customer_name, $customer_phone,
                                     $sale_date, $product_id, $quantity, $unit_price, $discount, $subtotal, $tax,
                                     $total_amount, $amount_paid, $balance, $payment_method, $payment_status,
                                     $delivery_address, $notes, $current_user['user_id']);
                    
                    if ($stmt->execute()) {
                        // Update product stock
                        $update_stock = "UPDATE blockfactory_products SET current_stock = current_stock - ? WHERE product_id = ?";
                        $stock_stmt = $db->prepare($update_stock);
                        $stock_stmt->bind_param("ii", $quantity, $product_id);
                        $stock_stmt->execute();
                        
                        $_SESSION['success'] = 'Sale recorded successfully. Invoice: ' . $invoice_number;
                        log_activity($current_user['user_id'], 'Add Sale', "Recorded sale: $invoice_number");
                    } else {
                        $_SESSION['error'] = 'Error recording sale: ' . $db->error();
                    }
                }
                break;
                
            case 'update_payment':
                $sale_id = intval($_POST['sale_id']);
                $amount_paid = floatval($_POST['amount_paid']);
                
                // Get current sale data
                $get_sql = "SELECT total_amount, amount_paid FROM blockfactory_sales WHERE sale_id = ?";
                $get_stmt = $db->prepare($get_sql);
                $get_stmt->bind_param("i", $sale_id);
                $get_stmt->execute();
                $sale = $get_stmt->get_result()->fetch_assoc();
                
                $new_amount_paid = $sale['amount_paid'] + $amount_paid;
                $balance = $sale['total_amount'] - $new_amount_paid;
                $payment_status = $balance <= 0 ? 'Paid' : 'Partial';
                
                $sql = "UPDATE blockfactory_sales SET amount_paid = ?, balance = ?, payment_status = ? WHERE sale_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("ddsi", $new_amount_paid, $balance, $payment_status, $sale_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Payment updated successfully';
                    log_activity($current_user['user_id'], 'Update Payment', "Updated payment for sale ID: $sale_id");
                } else {
                    $_SESSION['error'] = 'Error updating payment';
                }
                break;
                
            case 'delete':
                $sale_id = intval($_POST['sale_id']);
                
                // Get sale data before deleting
                $get_sql = "SELECT product_id, quantity FROM blockfactory_sales WHERE sale_id = ?";
                $get_stmt = $db->prepare($get_sql);
                $get_stmt->bind_param("i", $sale_id);
                $get_stmt->execute();
                $sale = $get_stmt->get_result()->fetch_assoc();
                
                if ($sale) {
                    // Restore stock
                    $update_stock = "UPDATE blockfactory_products SET current_stock = current_stock + ? WHERE product_id = ?";
                    $stock_stmt = $db->prepare($update_stock);
                    $stock_stmt->bind_param("ii", $sale['quantity'], $sale['product_id']);
                    $stock_stmt->execute();
                    
                    // Delete sale
                    $sql = "DELETE FROM blockfactory_sales WHERE sale_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param("i", $sale_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = 'Sale deleted successfully';
                        log_activity($current_user['user_id'], 'Delete Sale', "Deleted sale ID: $sale_id");
                    } else {
                        $_SESSION['error'] = 'Error deleting sale';
                    }
                }
                break;
        }
        header('Location: sales.php');
        exit();
    }
}

// Get filter parameters
$customer_filter = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$product_filter = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $db->escapeString($_GET['status']) : '';

// Build query for sales
$query = "SELECT s.*, pr.product_name, pr.product_code, c.customer_name as customer_company,
          u.full_name as sales_person_name
          FROM blockfactory_sales s
          JOIN blockfactory_products pr ON s.product_id = pr.product_id
          LEFT JOIN blockfactory_customers c ON s.customer_id = c.customer_id
          LEFT JOIN users u ON s.sales_person = u.user_id
          WHERE pr.company_id = ?";
$params = [$company_id];
$types = "i";

if ($customer_filter > 0) {
    $query .= " AND s.customer_id = ?";
    $params[] = $customer_filter;
    $types .= "i";
}
if ($product_filter > 0) {
    $query .= " AND s.product_id = ?";
    $params[] = $product_filter;
    $types .= "i";
}
if (!empty($date_from)) {
    $query .= " AND s.sale_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if (!empty($date_to)) {
    $query .= " AND s.sale_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}
if (!empty($status_filter)) {
    $query .= " AND s.payment_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
$query .= " ORDER BY s.sale_date DESC, s.created_at DESC";

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$sales = $stmt->get_result();

// Get customers for dropdown
$customers = $db->query("SELECT customer_id, customer_name FROM blockfactory_customers WHERE status = 'Active' ORDER BY customer_name");

// Get products for dropdown
$products = $db->query("SELECT product_id, product_name, product_code, price_per_unit, current_stock FROM blockfactory_products WHERE company_id = $company_id AND status = 'Active' ORDER BY product_name");

// Get summary statistics
$stats_query = "SELECT 
                COUNT(*) as total_sales,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(SUM(amount_paid), 0) as total_collected,
                COALESCE(SUM(balance), 0) as total_balance,
                COUNT(CASE WHEN payment_status = 'Unpaid' THEN 1 END) as unpaid_count,
                COUNT(CASE WHEN payment_status = 'Partial' THEN 1 END) as partial_count
                FROM blockfactory_sales s
                JOIN blockfactory_products pr ON s.product_id = pr.product_id
                WHERE pr.company_id = ? AND s.sale_date BETWEEN ? AND ?";
$stmt = $db->prepare($stats_query);
$stmt->bind_param("iss", $company_id, $date_from, $date_to);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$page_title = 'Sales';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales - Block Factory</title>
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
        
        .payment-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .payment-paid { background: #d4edda; color: #155724; }
        .payment-partial { background: #fff3cd; color: #856404; }
        .payment-unpaid { background: #f8d7da; color: #721c24; }
        
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
                            <h4 class="mb-1">Sales Management</h4>
                            <p class="text-muted mb-0">Record and track sales transactions</p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSaleModal">
                            <i class="fas fa-plus-circle me-2"></i>New Sale
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
                                <p class="text-muted mb-1">Total Sales</p>
                                <h3><?php echo intval($stats['total_sales'] ?? 0); ?></h3>
                                <small>Selected period</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Total Revenue</p>
                                <h3><?php echo formatMoney($stats['total_revenue'] ?? 0); ?></h3>
                                <small>Invoice value</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Collected</p>
                                <h3><?php echo formatMoney($stats['total_collected'] ?? 0); ?></h3>
                                <small><?php echo round(($stats['total_revenue'] > 0 ? ($stats['total_collected'] / $stats['total_revenue']) * 100 : 0), 2); ?>% paid</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <p class="text-muted mb-1">Outstanding</p>
                                <h3><?php echo formatMoney($stats['total_balance'] ?? 0); ?></h3>
                                <small><?php echo intval($stats['unpaid_count'] + $stats['partial_count']); ?> pending</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Customer</label>
                                <select class="form-control" name="customer_id" onchange="this.form.submit()">
                                    <option value="0">All Customers</option>
                                    <?php 
                                    $customers->data_seek(0);
                                    while($customer = $customers->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $customer['customer_id']; ?>" <?php echo $customer_filter == $customer['customer_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['customer_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Product</label>
                                <select class="form-control" name="product_id" onchange="this.form.submit()">
                                    <option value="0">All Products</option>
                                    <?php 
                                    $products->data_seek(0);
                                    while($product = $products->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $product['product_id']; ?>" <?php echo $product_filter == $product['product_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Payment Status</label>
                                <select class="form-control" name="status" onchange="this.form.submit()">
                                    <option value="">All</option>
                                    <option value="Paid" <?php echo $status_filter == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="Partial" <?php echo $status_filter == 'Partial' ? 'selected' : ''; ?>>Partial</option>
                                    <option value="Unpaid" <?php echo $status_filter == 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <a href="sales.php" class="btn btn-secondary w-100">Clear Filters</a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Sales Table -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="salesTable">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Date</th>
                                            <th>Customer</th>
                                            <th>Product</th>
                                            <th>Qty</th>
                                            <th>Unit Price</th>
                                            <th>Total</th>
                                            <th>Paid</th>
                                            <th>Balance</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($sales && $sales->num_rows > 0): ?>
                                            <?php while($sale = $sales->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?php echo $sale['invoice_number']; ?></strong></td>
                                                <td><?php echo date('d/m/Y', strtotime($sale['sale_date'])); ?></td>
                                                <td>
                                                    <?php 
                                                    if ($sale['customer_id']) {
                                                        echo htmlspecialchars($sale['customer_company'] ?? $sale['customer_name']);
                                                    } else {
                                                        echo htmlspecialchars($sale['customer_name']);
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                                                <td><?php echo number_format($sale['quantity']); ?></td>
                                                <td><?php echo formatMoney($sale['unit_price']); ?></td>
                                                <td><?php echo formatMoney($sale['total_amount']); ?></td>
                                                <td><?php echo formatMoney($sale['amount_paid']); ?></td>
                                                <td><?php echo formatMoney($sale['balance']); ?></td>
                                                <td>
                                                    <span class="payment-badge payment-<?php echo strtolower($sale['payment_status']); ?>">
                                                        <?php echo $sale['payment_status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" onclick="viewSale(<?php echo $sale['sale_id']; ?>)" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="printInvoice(<?php echo $sale['sale_id']; ?>)" title="Print Invoice">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                    <?php if ($sale['payment_status'] != 'Paid'): ?>
                                                        <button class="btn btn-sm btn-warning" onclick="recordPayment(<?php echo $sale['sale_id']; ?>)" title="Record Payment">
                                                            <i class="fas fa-money-bill"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($sale['delivery_status'] != 'Delivered'): ?>
                                                        <button class="btn btn-sm btn-primary" onclick="scheduleDelivery(<?php echo $sale['sale_id']; ?>)" title="Schedule Delivery">
                                                            <i class="fas fa-truck"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($role == 'SuperAdmin'): ?>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteSale(<?php echo $sale['sale_id']; ?>, '<?php echo $sale['invoice_number']; ?>')" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="11" class="text-center py-4">
                                                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                                    <p class="text-muted">No sales records found</p>
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
    
    <!-- Add Sale Modal -->
    <div class="modal fade" id="addSaleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Sale</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer</label>
                                <select class="form-control" name="customer_id" id="customerSelect">
                                    <option value="">Walk-in Customer</option>
                                    <?php 
                                    $customers->data_seek(0);
                                    while($customer = $customers->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $customer['customer_id']; ?>">
                                        <?php echo htmlspecialchars($customer['customer_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" id="walkinFields">
                                <label class="form-label">Customer Name</label>
                                <input type="text" class="form-control" name="customer_name" placeholder="Enter name">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sale Date</label>
                                <input type="date" class="form-control" name="sale_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone (Optional)</label>
                                <input type="text" class="form-control" name="customer_phone">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product</label>
                                <select class="form-control" name="product_id" id="productSelect" required>
                                    <option value="">Select Product</option>
                                    <?php 
                                    $products->data_seek(0);
                                    while($product = $products->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $product['product_id']; ?>" 
                                            data-price="<?php echo $product['price_per_unit']; ?>"
                                            data-stock="<?php echo $product['current_stock']; ?>">
                                        <?php echo htmlspecialchars($product['product_name']); ?> (Stock: <?php echo $product['current_stock']; ?>)
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="quantity" id="quantity" required min="1">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Available Stock</label>
                                <input type="text" class="form-control" id="availableStock" readonly>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Unit Price</label>
                                <input type="number" step="0.01" class="form-control" name="unit_price" id="unitPrice" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Discount</label>
                                <input type="number" step="0.01" class="form-control" name="discount" id="discount" value="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Total Amount</label>
                                <input type="text" class="form-control" id="totalAmount" readonly>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Method</label>
                                <select class="form-control" name="payment_method" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Mobile Money">Mobile Money</option>
                                    <option value="Credit">Credit</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount Paid</label>
                                <input type="number" step="0.01" class="form-control" name="amount_paid" id="amountPaid" value="0">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Delivery Address</label>
                            <textarea class="form-control" name="delivery_address" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Process Sale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Record Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_payment">
                    <input type="hidden" name="sale_id" id="payment_sale_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Invoice</label>
                            <input type="text" class="form-control" id="payment_invoice" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Total Amount</label>
                            <input type="text" class="form-control" id="payment_total" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Paid</label>
                            <input type="text" class="form-control" id="payment_paid" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Balance</label>
                            <input type="text" class="form-control" id="payment_balance" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount to Pay</label>
                            <input type="number" step="0.01" class="form-control" name="amount_paid" required min="1">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Record Payment</button>
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
                    <p>Are you sure you want to delete invoice <strong id="deleteInvoice"></strong>?</p>
                    <p class="text-danger">This will restore product stock.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="sale_id" id="deleteSaleId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Sale</button>
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
            $('#salesTable').DataTable({
                order: [[1, 'desc']],
                pageLength: 25
            });
            
            // Handle customer selection
            $('#customerSelect').change(function() {
                if ($(this).val()) {
                    $('#walkinFields input').prop('required', false);
                } else {
                    $('#walkinFields input').prop('required', true);
                }
            });
            
            // Handle product selection
            $('#productSelect').change(function() {
                var price = $(this).find(':selected').data('price');
                var stock = $(this).find(':selected').data('stock');
                $('#unitPrice').val(price);
                $('#availableStock').val(stock);
                calculateTotal();
            });
            
            // Calculate total
            $('#quantity, #unitPrice, #discount').on('input', calculateTotal);
            
            function calculateTotal() {
                var qty = parseFloat($('#quantity').val()) || 0;
                var price = parseFloat($('#unitPrice').val()) || 0;
                var discount = parseFloat($('#discount').val()) || 0;
                var subtotal = qty * price;
                var tax = subtotal * 0.16;
                var total = subtotal + tax - discount;
                $('#totalAmount').val('GHS ' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
            }
        });
        
        function viewSale(id) {
            window.location.href = 'sale-details.php?id=' + id;
        }
        
        function printInvoice(id) {
            window.open('print-invoice.php?id=' + id, '_blank', 'width=800,height=600');
        }
        
        function recordPayment(id) {
            $.get('ajax/get-sale.php', {id: id}, function(data) {
                $('#payment_sale_id').val(data.sale_id);
                $('#payment_invoice').val(data.invoice_number);
                $('#payment_total').val('GHS ' + data.total_amount.toFixed(2));
                $('#payment_paid').val('GHS ' + data.amount_paid.toFixed(2));
                $('#payment_balance').val('GHS ' + data.balance.toFixed(2));
                $('#paymentModal').modal('show');
            }, 'json');
        }
        
        function scheduleDelivery(id) {
            window.location.href = 'schedule-delivery.php?sale_id=' + id;
        }
        
        function deleteSale(id, invoice) {
            $('#deleteSaleId').val(id);
            $('#deleteInvoice').text(invoice);
            $('#deleteModal').modal('show');
        }
    </script>
</body>
</html>