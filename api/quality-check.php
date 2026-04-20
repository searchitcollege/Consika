<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('blockfactory', 'edit')) { header('Location: index.php'); exit(); }
global $db;
$production_id = (int)($_GET['id'] ?? 0);
if (!$production_id) { header('Location: index.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quality_check_passed = isset($_POST['quality_check_passed']) ? 1 : 0;
    $quality_notes        = trim($_POST['quality_notes'] ?? '') ?: null;
    $good_quantity        = (int)($_POST['good_quantity']        ?? 0);
    $defective_quantity   = (int)($_POST['defective_quantity']   ?? 0);
    $produced_quantity    = $good_quantity + $defective_quantity;
    $defect_rate          = $produced_quantity > 0
        ? round(($defective_quantity / $produced_quantity) * 100, 2) : 0.00;

    $stmt = $db->prepare("
        UPDATE blockfactory_production SET
            quality_check_passed = ?,
            quality_notes        = ?,
            good_quantity        = ?,
            defective_quantity   = ?,
            defect_rate          = ?
        WHERE production_id = ?
    ");
    $stmt->bind_param("isiidi",
        $quality_check_passed, $quality_notes,
        $good_quantity, $defective_quantity, $defect_rate,
        $production_id
    );

    if ($stmt->execute()) {
        // Recalculate stock: remove old good_qty, add new good_qty
        $old_stmt = $db->prepare("SELECT good_quantity, product_id FROM blockfactory_production WHERE production_id = ?");
        $old_stmt->bind_param("i", $production_id);
        $old_stmt->execute();
        $old = $old_stmt->get_result()->fetch_assoc();
        $old_stmt->close();

        $diff = $good_quantity - (int)$old['good_quantity'];
        if ($diff !== 0) {
            $stock_upd = $db->prepare("
                UPDATE blockfactory_products
                SET current_stock = current_stock + ?
                WHERE product_id = ?
            ");
            $stock_upd->bind_param("ii", $diff, $old['product_id']);
            $stock_upd->execute();
            $stock_upd->close();
        }

        $log_ip  = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_id = (int)currentUser()['user_id'];
        $log_desc = "Quality check updated for production ID {$production_id}";
        $log = $db->prepare("
            INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
            VALUES (?, 'Quality Check', ?, ?, 'blockfactory', ?)
        ");
        $log->bind_param("issi", $user_id, $log_desc, $log_ip, $production_id);
        $log->execute();
        $log->close();

        $_SESSION['success'] = 'Quality check updated successfully.';
    } else {
        $_SESSION['error'] = 'Failed to update quality check.';
    }
    $stmt->close();
    header("Location: view-batch.php?id={$production_id}");
    exit();
}

$stmt = $db->prepare("
    SELECT p.*, pr.product_name
    FROM blockfactory_production p
    JOIN blockfactory_products pr ON p.product_id = pr.product_id
    WHERE p.production_id = ?
");
$stmt->bind_param("i", $production_id);
$stmt->execute();
$batch = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$batch) { header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quality Check - <?php echo $batch['batch_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Quality Check — Batch <?php echo $batch['batch_number']; ?></h4>
            <a href="view-batch.php?id=<?php echo $production_id; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back
            </a>
        </div>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header fw-semibold">
                <?php echo htmlspecialchars($batch['product_name']); ?> —
                <?php echo format_date($batch['production_date']); ?> —
                <?php echo $batch['shift']; ?> shift
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Good Quantity</label>
                            <input type="number" class="form-control" name="good_quantity"
                                value="<?php echo $batch['good_quantity']; ?>" required min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Defective Quantity</label>
                            <input type="number" class="form-control" name="defective_quantity"
                                value="<?php echo $batch['defective_quantity']; ?>" required min="0">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="quality_check_passed"
                                    id="qcPassed" <?php echo $batch['quality_check_passed'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="qcPassed">
                                    Quality Check Passed
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quality Notes</label>
                        <textarea class="form-control" name="quality_notes" rows="3"
                            placeholder="Describe any quality issues or observations..."><?php echo htmlspecialchars($batch['quality_notes'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Quality Check</button>
                    <a href="view-batch.php?id=<?php echo $production_id; ?>" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>