<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('blockfactory', 'view')) { header('Location: index.php'); exit(); }
global $db;
$production_id = (int)($_GET['id'] ?? 0);
if (!$production_id) { header('Location: index.php'); exit(); }

$stmt = $db->prepare("
    SELECT p.*, pr.product_name, pr.product_type, pr.dimensions
    FROM blockfactory_production p
    JOIN blockfactory_products pr ON p.product_id = pr.product_id
    WHERE p.production_id = ?
");
$stmt->bind_param("i", $production_id);
$stmt->execute();
$batch = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$batch) { $_SESSION['error'] = 'Batch not found.'; header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Batch <?php echo $batch['batch_number']; ?> - <?php echo APP_NAME; ?></title>
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
                <h4>Batch #<?php echo $batch['batch_number']; ?></h4>
                <span class="badge bg-<?php echo $batch['quality_check_passed'] ? 'success' : 'danger'; ?>">
                    <?php echo $batch['quality_check_passed'] ? 'Quality Passed' : 'Quality Failed'; ?>
                </span>
            </div>
            <a href="../modules/blockfactory/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Production Details</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:45%">Product</th><td><?php echo htmlspecialchars($batch['product_name']); ?></td></tr>
                            <tr><th class="text-muted">Type</th><td><?php echo $batch['product_type']; ?></td></tr>
                            <tr><th class="text-muted">Dimensions</th><td><?php echo $batch['dimensions']; ?></td></tr>
                            <tr><th class="text-muted">Production Date</th><td><?php echo format_date($batch['production_date']); ?></td></tr>
                            <tr><th class="text-muted">Shift</th><td><?php echo $batch['shift']; ?></td></tr>
                            <tr><th class="text-muted">Supervisor</th><td><?php echo $batch['supervisor']; ?></td></tr>
                            <tr><th class="text-muted">Machine Used</th><td><?php echo $batch['machine_used'] ?: '—'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Production Statistics</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:45%">Planned</th><td><?php echo number_format($batch['planned_quantity']); ?></td></tr>
                            <tr><th class="text-muted">Produced</th><td><?php echo number_format($batch['produced_quantity']); ?></td></tr>
                            <tr><th class="text-muted">Good</th><td class="text-success fw-bold"><?php echo number_format($batch['good_quantity']); ?></td></tr>
                            <tr><th class="text-muted">Defective</th><td class="text-danger"><?php echo number_format($batch['defective_quantity']); ?></td></tr>
                            <tr>
                                <th class="text-muted">Defect Rate</th>
                                <td>
                                    <span class="badge bg-<?php echo $batch['defect_rate'] <= 2 ? 'success' : ($batch['defect_rate'] <= 5 ? 'warning' : 'danger'); ?>">
                                        <?php echo $batch['defect_rate']; ?>%
                                    </span>
                                </td>
                            </tr>
                            <tr><th class="text-muted">Unit Cost</th><td><?php echo $batch['unit_cost'] ? format_money($batch['unit_cost']) : '—'; ?></td></tr>
                            <tr><th class="text-muted">Total Cost</th><td><?php echo $batch['total_cost'] ? format_money($batch['total_cost']) : '—'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header fw-semibold">Raw Materials Used</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:45%">Cement</th><td><?php echo $batch['cement_used'] ? $batch['cement_used'] . ' kg' : '—'; ?></td></tr>
                            <tr><th class="text-muted">Sand</th><td><?php echo $batch['sand_used'] ? $batch['sand_used'] . ' kg' : '—'; ?></td></tr>
                            <tr><th class="text-muted">Aggregate</th><td><?php echo $batch['aggregate_used'] ? $batch['aggregate_used'] . ' kg' : '—'; ?></td></tr>
                            <tr><th class="text-muted">Water</th><td><?php echo $batch['water_used'] ? $batch['water_used'] . ' L' : '—'; ?></td></tr>
                            <tr><th class="text-muted">Additive</th><td><?php echo $batch['additive_used'] ? $batch['additive_used'] . ' kg' : '—'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header fw-semibold">Quality Notes</div>
                    <div class="card-body">
                        <?php if ($batch['quality_notes']): ?>
                            <p><?php echo nl2br(htmlspecialchars($batch['quality_notes'])); ?></p>
                        <?php else: ?>
                            <p class="text-muted">No quality notes recorded.</p>
                        <?php endif; ?>
                        <?php if ($batch['notes']): ?>
                            <hr>
                            <strong class="small text-muted">General Notes</strong>
                            <p class="small"><?php echo nl2br(htmlspecialchars($batch['notes'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>