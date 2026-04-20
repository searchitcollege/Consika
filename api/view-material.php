<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('works', 'view') && !hasPermission('blockfactory', 'view')) { header('Location: ../modules/blockfactory/index.php'); exit(); }
global $db;
$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) { header('Location: ../modules/blockfactory/index.php'); exit(); }

$stmt = $db->prepare("SELECT * FROM procurement_products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$mat = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$mat) { header('Location: index.php'); exit(); }

$usage_stmt = $db->prepare("
    SELECT pm.*, wp.project_name
    FROM works_project_materials pm
    JOIN works_projects wp ON pm.project_id = wp.project_id
    WHERE pm.material_id = ?
    ORDER BY pm.date_used DESC LIMIT 15
");
$usage_stmt->bind_param("i", $product_id);
$usage_stmt->execute();
$usage = $usage_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($mat['product_name']); ?> - <?php echo APP_NAME; ?></title>
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
                <h4><?php echo htmlspecialchars($mat['product_name']); ?></h4>
                <span class="badge bg-<?php echo $mat['current_stock'] <= $mat['minimum_stock'] ? 'danger' : 'success'; ?>">
                    Stock: <?php echo $mat['current_stock']; ?> <?php echo $mat['unit']; ?>
                </span>
                <span class="text-muted ms-2 small"><?php echo $mat['product_code']; ?></span>
            </div>
            <div>
                <a href="edit-material.php?id=<?php echo $product_id; ?>" class="btn btn-primary btn-sm me-2">
                    <i class="fas fa-edit me-1"></i>Edit
                </a>
                <a href="../modules//works/index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Current Stock</div>
                        <div class="fw-bold fs-2 <?php echo $mat['current_stock'] <= $mat['minimum_stock'] ? 'text-danger' : 'text-success'; ?>">
                            <?php echo $mat['current_stock']; ?>
                        </div>
                        <div class="text-muted small"><?php echo $mat['unit']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Unit Price</div>
                        <div class="fw-bold fs-4 text-primary"><?php echo format_money($mat['unit_price']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-muted small">Min Stock Level</div>
                        <div class="fw-bold fs-4"><?php echo $mat['minimum_stock']; ?> <?php echo $mat['unit']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header fw-semibold">Material Details</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Category</th><td><?php echo $mat['category'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Unit</th><td><?php echo $mat['unit']; ?></td></tr>
                            <tr><th class="text-muted">Reorder Level</th><td><?php echo $mat['reorder_level']; ?></td></tr>
                            <tr><th class="text-muted">Max Stock</th><td><?php echo $mat['maximum_stock']; ?></td></tr>
                            <tr><th class="text-muted">Location</th><td><?php echo $mat['location'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Status</th><td><span class="badge bg-<?php echo $mat['status'] == 'Active' ? 'success' : 'secondary'; ?>"><?php echo $mat['status']; ?></span></td></tr>
                        </table>
                        <?php if ($mat['description']): ?>
                            <p class="text-muted small"><?php echo nl2br(htmlspecialchars($mat['description'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">Recent Project Usage</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Project</th><th>Quantity</th><th>Unit Cost</th><th>Total Cost</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($usage->num_rows > 0): while ($u = $usage->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['project_name']); ?></td>
                            <td><?php echo $u['quantity']; ?> <?php echo $mat['unit']; ?></td>
                            <td><?php echo format_money($u['unit_cost']); ?></td>
                            <td><?php echo format_money($u['total_cost']); ?></td>
                            <td><?php echo format_date($u['date_used']); ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No usage recorded</td></tr>
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