<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('blockfactory', 'view')) { header('Location: index.php'); exit(); }
global $db;
$delivery_id = (int)($_GET['id'] ?? 0);
if (!$delivery_id) { header('Location: index.php'); exit(); }

$stmt = $db->prepare("
    SELECT d.*, s.invoice_number, s.customer_name, s.total_amount,
           pr.product_name
    FROM blockfactory_deliveries d
    JOIN blockfactory_sales s ON d.sale_id = s.sale_id
    JOIN blockfactory_products pr ON s.product_id = pr.product_id
    WHERE d.delivery_id = ?
");
$stmt->bind_param("i", $delivery_id);
$stmt->execute();
$d = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$d) { header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivery <?php echo $d['delivery_note']; ?> - <?php echo APP_NAME; ?></title>
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
                <h4>Delivery Note: <?php echo $d['delivery_note']; ?></h4>
                <span class="badge bg-<?php echo $d['status'] == 'Delivered' ? 'success' : ($d['status'] == 'In Transit' ? 'warning' : 'info'); ?>">
                    <?php echo $d['status']; ?>
                </span>
            </div>
            <a href="../modules/blockfactory/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Delivery Information</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Invoice</th><td><a href="view-sale.php?id=<?php echo $d['sale_id']; ?>"><?php echo $d['invoice_number']; ?></a></td></tr>
                            <tr><th class="text-muted">Customer</th><td><?php echo htmlspecialchars($d['customer_name']); ?></td></tr>
                            <tr><th class="text-muted">Product</th><td><?php echo htmlspecialchars($d['product_name']); ?></td></tr>
                            <tr><th class="text-muted">Quantity</th><td><?php echo number_format($d['quantity']); ?> units</td></tr>
                            <tr><th class="text-muted">Delivery Date</th><td><?php echo format_date($d['delivery_date']); ?></td></tr>
                            <tr><th class="text-muted">Destination</th><td><?php echo nl2br(htmlspecialchars($d['destination'])); ?></td></tr>
                            <tr><th class="text-muted">Delivery Charges</th><td><?php echo format_money($d['delivery_charges']); ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Driver & Vehicle</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Driver</th><td><?php echo $d['driver_name']; ?></td></tr>
                            <tr><th class="text-muted">Driver Phone</th><td><?php echo $d['driver_phone'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Vehicle</th><td><?php echo $d['vehicle_number']; ?></td></tr>
                            <tr><th class="text-muted">Recipient</th><td><?php echo $d['recipient_name'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Recipient Phone</th><td><?php echo $d['recipient_phone'] ?: '—'; ?></td></tr>
                        </table>
                        <?php if ($d['notes']): ?>
                            <p class="small text-muted mt-2"><?php echo nl2br(htmlspecialchars($d['notes'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($d['status'] !== 'Delivered'): ?>
        <div class="card">
            <div class="card-body">
                <button class="btn btn-success" onclick="if(confirm('Mark as delivered?')) { $.post('ajax/mark-delivered.php', {id: <?php echo $delivery_id; ?>}, function(){ location.reload(); }); }">
                    <i class="fas fa-check me-1"></i>Mark as Delivered
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>