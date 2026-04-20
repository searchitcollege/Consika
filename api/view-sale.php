<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('blockfactory', 'view')) { header('Location: index.php'); exit(); }
global $db;
$sale_id = (int)($_GET['id'] ?? 0);
if (!$sale_id) { header('Location: index.php'); exit(); }

$stmt = $db->prepare("
    SELECT s.*, pr.product_name, pr.dimensions, c.customer_name as cust_linked
    FROM blockfactory_sales s
    JOIN blockfactory_products pr ON s.product_id = pr.product_id
    LEFT JOIN blockfactory_customers c ON s.customer_id = c.customer_id
    WHERE s.sale_id = ?
");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$sale) { header('Location: index.php'); exit(); }

$deliveries_stmt = $db->prepare("
    SELECT * FROM blockfactory_deliveries WHERE sale_id = ? ORDER BY delivery_date DESC
");
$deliveries_stmt->bind_param("i", $sale_id);
$deliveries_stmt->execute();
$deliveries = $deliveries_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo $sale['invoice_number']; ?> - <?php echo APP_NAME; ?></title>
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
                <h4>Invoice #<?php echo $sale['invoice_number']; ?></h4>
                <span class="badge bg-<?php echo $sale['payment_status'] == 'Paid' ? 'success' : ($sale['payment_status'] == 'Partial' ? 'warning' : 'danger'); ?>">
                    <?php echo $sale['payment_status']; ?>
                </span>
                <span class="badge bg-<?php echo $sale['delivery_status'] == 'Delivered' ? 'success' : 'info'; ?> ms-1">
                    <?php echo $sale['delivery_status']; ?>
                </span>
            </div>
            <div>
                <button onclick="window.open('print-invoice.php?id=<?php echo $sale_id; ?>','_blank')" class="btn btn-outline-secondary btn-sm me-2">
                    <i class="fas fa-print me-1"></i>Print
                </button>
                <a href="../modules/blockfactory/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Sale Details</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Customer</th><td><strong><?php echo htmlspecialchars($sale['customer_name']); ?></strong></td></tr>
                            <tr><th class="text-muted">Phone</th><td><?php echo $sale['customer_phone'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Sale Date</th><td><?php echo format_date($sale['sale_date']); ?></td></tr>
                            <tr><th class="text-muted">Product</th><td><?php echo htmlspecialchars($sale['product_name']); ?></td></tr>
                            <tr><th class="text-muted">Quantity</th><td><?php echo number_format($sale['quantity']); ?> units</td></tr>
                            <tr><th class="text-muted">Unit Price</th><td><?php echo format_money($sale['unit_price']); ?></td></tr>
                            <tr><th class="text-muted">Discount</th><td><?php echo format_money($sale['discount']); ?></td></tr>
                            <tr><th class="text-muted">Subtotal</th><td><?php echo format_money($sale['subtotal']); ?></td></tr>
                            <tr><th class="text-muted">Total</th><td><strong class="text-primary"><?php echo format_money($sale['total_amount']); ?></strong></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Payment Information</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Method</th><td><?php echo $sale['payment_method']; ?></td></tr>
                            <tr><th class="text-muted">Amount Paid</th><td class="text-success"><?php echo format_money($sale['amount_paid']); ?></td></tr>
                            <tr><th class="text-muted">Balance</th><td class="<?php echo $sale['balance'] > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo format_money($sale['balance']); ?></td></tr>
                            <tr><th class="text-muted">Delivery Address</th><td><?php echo nl2br(htmlspecialchars($sale['delivery_address'] ?? '—')); ?></td></tr>
                        </table>
                        <?php if ($sale['notes']): ?>
                            <p class="small text-muted mt-2"><?php echo nl2br(htmlspecialchars($sale['notes'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">Delivery Records</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Delivery Note</th><th>Date</th><th>Vehicle</th><th>Driver</th><th>Status</th><th>Qty</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($deliveries->num_rows > 0): while ($d = $deliveries->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $d['delivery_note']; ?></td>
                            <td><?php echo format_date($d['delivery_date']); ?></td>
                            <td><?php echo $d['vehicle_number']; ?></td>
                            <td><?php echo $d['driver_name']; ?></td>
                            <td><span class="badge bg-<?php echo $d['status'] == 'Delivered' ? 'success' : ($d['status'] == 'In Transit' ? 'warning' : 'info'); ?>"><?php echo $d['status']; ?></span></td>
                            <td><?php echo $d['quantity']; ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No deliveries scheduled yet</td></tr>
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