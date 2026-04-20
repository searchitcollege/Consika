<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('procurement', 'edit')) { header('Location: index.php'); exit(); }
global $db;
$po_id = (int)($_GET['id'] ?? 0);
if (!$po_id) { header('Location: index.php'); exit(); }

$stmt = $db->prepare("
    SELECT po.*, s.supplier_name
    FROM procurement_purchase_orders po
    JOIN procurement_suppliers s ON po.supplier_id = s.supplier_id
    WHERE po.po_id = ? AND po.delivery_status != 'Completed'
");
$stmt->bind_param("i", $po_id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$po) { $_SESSION['error'] = 'PO not found or already completed.'; header('Location: index.php'); exit(); }

$items_stmt = $db->prepare("
    SELECT pi.*, pp.product_name, pp.unit
    FROM procurement_po_items pi
    JOIN procurement_products pp ON pi.product_id = pp.product_id
    WHERE pi.po_id = ? AND pi.status != 'Cancelled' AND pi.status != 'Received'
");
$items_stmt->bind_param("i", $po_id);
$items_stmt->execute();
$items = $items_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receive Stock - <?php echo $po['po_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Receive Stock — <?php echo $po['po_number']; ?></h4>
            <a href="./view-po.php?id=<?php echo $po_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-body">
                <strong>Supplier:</strong> <?php echo htmlspecialchars($po['supplier_name']); ?> |
                <strong>Expected:</strong> <?php echo $po['expected_delivery'] ? format_date($po['expected_delivery']) : '—'; ?> |
                <strong>Total:</strong> <?php echo format_money($po['total_amount']); ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">Items to Receive</div>
            <div class="card-body">
                <form action="process/receive-stock.php" method="POST">
                    <input type="hidden" name="po_id" value="<?php echo $po_id; ?>">
                    <table class="table">
                        <thead class="table-light">
                            <tr><th>Product</th><th>Ordered</th><th>Received</th><th>Remaining</th><th>Receive Now</th></tr>
                        </thead>
                        <tbody>
                        <?php while ($item = $items->fetch_assoc()):
                            $remaining = $item['quantity'] - $item['received_quantity'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?> <small class="text-muted">(<?php echo $item['unit']; ?>)</small></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo $item['received_quantity']; ?></td>
                                <td class="<?php echo $remaining > 0 ? 'text-warning' : 'text-success'; ?>"><?php echo $remaining; ?></td>
                                <td style="width: 150px;">
                                    <input type="number" class="form-control form-control-sm"
                                        name="receive[<?php echo $item['po_item_id']; ?>]"
                                        min="0" max="<?php echo $remaining; ?>"
                                        value="<?php echo $remaining; ?>">
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                    <div class="mb-3">
                        <label class="form-label">Receiving Notes</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Optional notes about this delivery..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>Confirm Receipt</button>
                    <a href="view-po.php?id=<?php echo $po_id; ?>" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>