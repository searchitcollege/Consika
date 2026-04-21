<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('procurement', 'view')) { header('Location: index.php'); exit(); }
global $db;
$po_id = (int)($_GET['id'] ?? 0);
if (!$po_id) { header('Location: index.php'); exit(); }

$stmt = $db->prepare("
    SELECT po.*, s.supplier_name, s.contact_person, s.phone as supplier_phone, s.email as supplier_email
    FROM procurement_purchase_orders po
    JOIN procurement_suppliers s ON po.supplier_id = s.supplier_id
    WHERE po.po_id = ?
");
$stmt->bind_param("i", $po_id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$po) { header('Location: index.php'); exit(); }

$items_stmt = $db->prepare("
    SELECT pi.*, pp.product_name, pp.unit
    FROM procurement_po_items pi
    JOIN procurement_products pp ON pi.product_id = pp.product_id
    WHERE pi.po_id = ?
");
$items_stmt->bind_param("i", $po_id);
$items_stmt->execute();
$items = $items_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PO <?php echo $po['po_number']; ?> - <?php echo APP_NAME; ?></title>
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
                <h4>Purchase Order: <?php echo $po['po_number']; ?></h4>
                <span class="badge bg-<?php echo $po['delivery_status'] == 'Completed' ? 'success' : ($po['delivery_status'] == 'Partial' ? 'warning' : 'info'); ?>">
                    Delivery: <?php echo $po['delivery_status']; ?>
                </span>
                <span class="badge bg-<?php echo $po['payment_status'] == 'Paid' ? 'success' : ($po['payment_status'] == 'Partial' ? 'warning' : 'danger'); ?> ms-1">
                    Payment: <?php echo $po['payment_status']; ?>
                </span>
                <span class="badge bg-<?php echo $po['approval_status'] == 'Approved' ? 'success' : 'secondary'; ?> ms-1">
                    <?php echo $po['approval_status']; ?>
                </span>
            </div>
            <div>
                <!-- <?php if ($po['delivery_status'] != 'Completed'): ?>
                    <a href="receive-po.php?id=<?php echo $po_id; ?>" class="btn btn-success btn-sm me-2">
                        <i class="fas fa-truck-loading me-1"></i>Receive Stock
                    </a>
                <?php endif; ?> -->
                <a href="../modules/procurement/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Order Information</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Order Date</th><td><?php echo format_date($po['order_date']); ?></td></tr>
                            <tr><th class="text-muted">Expected Delivery</th><td><?php echo $po['expected_delivery'] ? format_date($po['expected_delivery']) : '—'; ?></td></tr>
                            <tr><th class="text-muted">Delivery Date</th><td><?php echo $po['delivery_date'] ? format_date($po['delivery_date']) : '—'; ?></td></tr>
                            <tr><th class="text-muted">Total Amount</th><td><strong><?php echo format_money($po['total_amount']); ?></strong></td></tr>
                            <?php if ($po['notes']): ?>
                            <tr><th class="text-muted">Notes</th><td><?php echo htmlspecialchars($po['notes']); ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Supplier</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Name</th><td><strong><?php echo htmlspecialchars($po['supplier_name']); ?></strong></td></tr>
                            <tr><th class="text-muted">Contact</th><td><?php echo $po['contact_person'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Phone</th><td><?php echo $po['supplier_phone'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Email</th><td><?php echo $po['supplier_email'] ?: '—'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">Order Items</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Product</th><th>Qty Ordered</th><th>Unit Price</th><th>Discount</th><th>Total</th><th>Received</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($item = $items->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?> <small class="text-muted">(<?php echo $item['unit']; ?>)</small></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo format_money($item['unit_price']); ?></td>
                            <td><?php echo format_money($item['discount']); ?></td>
                            <td><?php echo format_money($item['total_price']); ?></td>
                            <td><?php echo $item['received_quantity']; ?></td>
                            <td><span class="badge bg-<?php echo $item['status'] == 'Received' ? 'success' : ($item['status'] == 'Partial' ? 'warning' : 'info'); ?>"><?php echo $item['status']; ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end fw-bold">Total:</td>
                            <td class="fw-bold"><?php echo format_money($po['total_amount']); ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>