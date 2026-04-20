<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('procurement', 'view')) { header('Location: index.php'); exit(); }
global $db;
$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) { header('Location: index.php'); exit(); }

$stmt = $db->prepare("SELECT * FROM procurement_products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$p) { header('Location: index.php'); exit(); }

$history_stmt = $db->prepare("
    SELECT * FROM procurement_inventory
    WHERE product_id = ? ORDER BY transaction_date DESC LIMIT 20
");
$history_stmt->bind_param("i", $product_id);
$history_stmt->execute();
$history = $history_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($p['product_name']); ?> - <?php echo APP_NAME; ?></title>
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
                <h4><?php echo htmlspecialchars($p['product_name']); ?></h4>
                <span class="badge bg-<?php echo $p['status'] == 'Active' ? 'success' : 'secondary'; ?>"><?php echo $p['status']; ?></span>
                <span class="text-muted ms-2 small"><?php echo $p['product_code']; ?></span>
            </div>
            <div>
                <a href="edit-product.php?id=<?php echo $product_id; ?>" class="btn btn-primary btn-sm me-2"><i class="fas fa-edit me-1"></i>Edit</a>
                <a href="../modules/procurement/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center"><div class="card-body">
                    <div class="text-muted small">Current Stock</div>
                    <div class="fw-bold fs-2 <?php echo $p['current_stock'] <= $p['minimum_stock'] ? 'text-danger' : 'text-success'; ?>"><?php echo $p['current_stock']; ?></div>
                    <div class="text-muted small"><?php echo $p['unit']; ?></div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card text-center"><div class="card-body">
                    <div class="text-muted small">Unit Price</div>
                    <div class="fw-bold fs-4 text-primary"><?php echo format_money($p['unit_price']); ?></div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card text-center"><div class="card-body">
                    <div class="text-muted small">Stock Value</div>
                    <div class="fw-bold fs-4"><?php echo format_money($p['current_stock'] * $p['unit_price']); ?></div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card text-center"><div class="card-body">
                    <div class="text-muted small">Reorder Level</div>
                    <div class="fw-bold fs-4"><?php echo $p['reorder_level']; ?></div>
                </div></div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header fw-semibold">Product Details</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Category</th><td><?php echo $p['category'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Sub Category</th><td><?php echo $p['sub_category'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Unit</th><td><?php echo $p['unit']; ?></td></tr>
                            <tr><th class="text-muted">Min Stock</th><td><?php echo $p['minimum_stock']; ?></td></tr>
                            <tr><th class="text-muted">Max Stock</th><td><?php echo $p['maximum_stock']; ?></td></tr>
                            <tr><th class="text-muted">Tax Rate</th><td><?php echo $p['tax_rate']; ?>%</td></tr>
                            <tr><th class="text-muted">Location</th><td><?php echo $p['location'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Barcode</th><td><?php echo $p['barcode'] ?: '—'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">Stock Movement History</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Date</th><th>Type</th><th>Quantity</th><th>Previous</th><th>New Balance</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($history->num_rows > 0): while ($h = $history->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo format_date($h['transaction_date']); ?></td>
                            <td><span class="badge bg-<?php echo $h['transaction_type'] == 'Purchase' ? 'success' : ($h['transaction_type'] == 'Sale' ? 'primary' : 'warning'); ?>"><?php echo $h['transaction_type']; ?></span></td>
                            <td class="<?php echo in_array($h['transaction_type'], ['Purchase','Return']) ? 'text-success' : 'text-danger'; ?>">
                                <?php echo in_array($h['transaction_type'], ['Purchase','Return']) ? '+' : '-'; ?><?php echo $h['quantity']; ?>
                            </td>
                            <td><?php echo $h['previous_balance']; ?></td>
                            <td><?php echo $h['new_balance']; ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No movement history</td></tr>
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