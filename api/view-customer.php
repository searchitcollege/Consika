<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('blockfactory', 'view')) { header('Location: index.php'); exit(); }
global $db;
$customer_id = (int)($_GET['id'] ?? 0);
if (!$customer_id) { header('Location: index.php'); exit(); }

$stmt = $db->prepare("SELECT * FROM blockfactory_customers WHERE customer_id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$c) { header('Location: index.php'); exit(); }

$sales_stmt = $db->prepare("
    SELECT s.*, pr.product_name
    FROM blockfactory_sales s
    JOIN blockfactory_products pr ON s.product_id = pr.product_id
    WHERE s.customer_id = ?
    ORDER BY s.sale_date DESC LIMIT 20
");
$sales_stmt->bind_param("i", $customer_id);
$sales_stmt->execute();
$sales = $sales_stmt->get_result();

$totals_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_spent,
        COALESCE(SUM(balance), 0) as total_outstanding
    FROM blockfactory_sales
    WHERE customer_id = ?
");
$totals_stmt->bind_param("i", $customer_id);
$totals_stmt->execute();
$totals = $totals_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($c['customer_name']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4><?php echo htmlspecialchars($c['customer_name']); ?></h4>
                <span class="badge bg-<?php echo $c['status'] == 'Active' ? 'success' : 'secondary'; ?>"><?php echo $c['status']; ?></span>
                <span class="badge bg-info ms-1"><?php echo $c['customer_type']; ?></span>
                <span class="text-muted ms-2 small"><?php echo $c['customer_code']; ?></span>
            </div>
            <div>
                <a href="edit-customer.php?id=<?php echo $customer_id; ?>" class="btn btn-primary btn-sm me-2"><i class="fas fa-edit me-1"></i>Edit</a>
                <a href="../modules/blockfactory/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Total Orders</div>
                        <div class="fw-bold fs-3"><?php echo $totals['total_orders']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Total Spent</div>
                        <div class="fw-bold fs-5 text-primary"><?php echo format_money($totals['total_spent']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Outstanding Balance</div>
                        <div class="fw-bold fs-5 <?php echo $totals['total_outstanding'] > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo format_money($totals['total_outstanding']); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Credit Limit</div>
                        <div class="fw-bold fs-5"><?php echo $c['credit_limit'] ? format_money($c['credit_limit']) : '—'; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Contact Information</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Contact Person</th><td><?php echo $c['contact_person'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Phone</th><td><?php echo $c['phone']; ?></td></tr>
                            <tr><th class="text-muted">Alternate Phone</th><td><?php echo $c['alternate_phone'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Email</th><td><?php echo $c['email'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Address</th><td><?php echo nl2br(htmlspecialchars($c['address'] ?? '—')); ?></td></tr>
                            <tr><th class="text-muted">City</th><td><?php echo $c['city'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Tax Number</th><td><?php echo $c['tax_number'] ?: '—'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">Purchase History</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Invoice</th><th>Date</th><th>Product</th><th>Qty</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($sales->num_rows > 0): while ($s = $sales->fetch_assoc()): ?>
                        <tr>
                            <td><a href="view-sale.php?id=<?php echo $s['sale_id']; ?>"><?php echo $s['invoice_number']; ?></a></td>
                            <td><?php echo format_date($s['sale_date']); ?></td>
                            <td><?php echo htmlspecialchars($s['product_name']); ?></td>
                            <td><?php echo number_format($s['quantity']); ?></td>
                            <td><?php echo format_money($s['total_amount']); ?></td>
                            <td><?php echo format_money($s['amount_paid']); ?></td>
                            <td class="<?php echo $s['balance'] > 0 ? 'text-danger' : ''; ?>"><?php echo format_money($s['balance']); ?></td>
                            <td><span class="badge bg-<?php echo $s['payment_status'] == 'Paid' ? 'success' : ($s['payment_status'] == 'Partial' ? 'warning' : 'danger'); ?>"><?php echo $s['payment_status']; ?></span></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="8" class="text-center text-muted py-3">No purchases yet</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>