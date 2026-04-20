<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('blockfactory', 'view')) { header('Location: index.php'); exit(); }
global $db;
$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) { header('Location: index.php'); exit(); }

$stmt = $db->prepare("SELECT * FROM blockfactory_products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$p) { header('Location: index.php'); exit(); }

$recent_prod = $db->prepare("
    SELECT * FROM blockfactory_production
    WHERE product_id = ? ORDER BY production_date DESC LIMIT 5
");
$recent_prod->bind_param("i", $product_id);
$recent_prod->execute();
$batches = $recent_prod->get_result();
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
                <a href="../modules/blockfactory/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Current Stock</div>
                        <div class="fw-bold fs-2 <?php echo $p['current_stock'] <= $p['reorder_level'] ? 'text-danger' : 'text-success'; ?>">
                            <?php echo number_format($p['current_stock']); ?>
                        </div>
                        <div class="text-muted small">units</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Price per Unit</div>
                        <div class="fw-bold fs-4 text-primary"><?php echo format_money($p['price_per_unit']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Stock Value</div>
                        <div class="fw-bold fs-4"><?php echo format_money($p['current_stock'] * $p['price_per_unit']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Product Specifications</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Type</th><td><?php echo $p['product_type']; ?></td></tr>
                            <tr><th class="text-muted">Dimensions</th><td><?php echo $p['dimensions']; ?></td></tr>
                            <tr><th class="text-muted">Weight</th><td><?php echo $p['weight_kg'] ? $p['weight_kg'] . ' kg' : '—'; ?></td></tr>
                            <tr><th class="text-muted">Strength</th><td><?php echo $p['strength_mpa'] ? $p['strength_mpa'] . ' MPa' : '—'; ?></td></tr>
                            <tr><th class="text-muted">Color</th><td><?php echo $p['color'] ?? 'Grey'; ?></td></tr>
                            <tr><th class="text-muted">Cost per Unit</th><td><?php echo $p['cost_per_unit'] ? format_money($p['cost_per_unit']) : '—'; ?></td></tr>
                            <tr><th class="text-muted">Min Stock</th><td><?php echo $p['minimum_stock']; ?></td></tr>
                            <tr><th class="text-muted">Reorder Level</th><td><?php echo $p['reorder_level']; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Recent Production Batches</div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Batch</th><th>Date</th><th>Good</th><th>Defect %</th></tr>
                            </thead>
                            <tbody>
                            <?php if ($batches->num_rows > 0): while ($b = $batches->fetch_assoc()): ?>
                                <tr>
                                    <td><a href="view-batch.php?id=<?php echo $b['production_id']; ?>"><?php echo $b['batch_number']; ?></a></td>
                                    <td><?php echo format_date($b['production_date']); ?></td>
                                    <td><?php echo $b['good_quantity']; ?></td>
                                    <td><span class="badge bg-<?php echo $b['defect_rate'] <= 2 ? 'success' : ($b['defect_rate'] <= 5 ? 'warning' : 'danger'); ?>"><?php echo $b['defect_rate']; ?>%</span></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No batches yet</td></tr>
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