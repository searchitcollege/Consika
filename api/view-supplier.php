<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('procurement', 'view')) { header('Location: index.php'); exit(); }
global $db;
$supplier_id = (int)($_GET['id'] ?? 0);
if (!$supplier_id) { header('Location: index.php'); exit(); }

$stmt = $db->prepare("SELECT * FROM procurement_suppliers WHERE supplier_id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$s) { header('Location: index.php'); exit(); }

$po_stmt = $db->prepare("
    SELECT * FROM procurement_purchase_orders
    WHERE supplier_id = ? ORDER BY order_date DESC LIMIT 10
");
$po_stmt->bind_param("i", $supplier_id);
$po_stmt->execute();
$orders = $po_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($s['supplier_name']); ?> - <?php echo APP_NAME; ?></title>
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
                <h4><?php echo htmlspecialchars($s['supplier_name']); ?></h4>
                <span class="badge bg-<?php echo $s['status'] == 'Active' ? 'success' : 'secondary'; ?>"><?php echo $s['status']; ?></span>
                <span class="text-muted ms-2 small"><?php echo $s['supplier_code']; ?></span>
            </div>
            <div>
                <a href="./edit-supplier.php?id=<?php echo $supplier_id; ?>" class="btn btn-primary btn-sm me-2"><i class="fas fa-edit me-1"></i>Edit</a>
                <a href="../modules/procurement/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Supplier Details</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Contact Person</th><td><?php echo $s['contact_person'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Phone</th><td><?php echo $s['phone']; ?></td></tr>
                            <tr><th class="text-muted">Email</th><td><?php echo $s['email'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Website</th><td><?php echo $s['website'] ? '<a href="'.$s['website'].'" target="_blank">'.$s['website'].'</a>' : '—'; ?></td></tr>
                            <tr><th class="text-muted">Category</th><td><?php echo $s['category'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Address</th><td><?php echo $s['address'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Tax Number</th><td><?php echo $s['tax_number'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Payment Terms</th><td><?php echo $s['payment_terms'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Credit Limit</th><td><?php echo $s['credit_limit'] ? format_money($s['credit_limit']) : '—'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Purchase Orders</div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>PO Number</th><th>Date</th><th>Total</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                            <?php if ($orders->num_rows > 0): while ($po = $orders->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $po['po_number']; ?></td>
                                    <td><?php echo format_date($po['order_date']); ?></td>
                                    <td><?php echo format_money($po['total_amount']); ?></td>
                                    <td><span class="badge bg-<?php echo $po['delivery_status'] == 'Completed' ? 'success' : 'warning'; ?>"><?php echo $po['delivery_status']; ?></span></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No orders yet</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>