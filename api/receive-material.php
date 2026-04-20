<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('blockfactory', 'edit')) { header('Location: index.php'); exit(); }
global $db;
$material_id = (int)($_GET['id'] ?? 0);
if (!$material_id) { header('Location: index.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity_received = (float)($_POST['quantity_received'] ?? 0);
    $notes             = trim($_POST['notes'] ?? '') ?: null;

    if ($quantity_received <= 0) {
        $_SESSION['error'] = 'Quantity must be greater than zero.';
        header("Location: receive-material.php?id={$material_id}");
        exit();
    }

    $upd = $db->prepare("
        UPDATE blockfactory_raw_materials
        SET stock_quantity = stock_quantity + ?,
            status = CASE
                WHEN (stock_quantity + ?) <= minimum_stock THEN 'Low Stock'
                ELSE 'Available'
            END
        WHERE material_id = ?
    ");
    $upd->bind_param("ddi", $quantity_received, $quantity_received, $material_id);

    if ($upd->execute()) {
        $upd->close();
        $log_ip  = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_id = (int)currentUser()['user_id'];
        $log_desc = "Received {$quantity_received} units for raw material ID {$material_id}";
        $log = $db->prepare("
            INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
            VALUES (?, 'Receive Material', ?, ?, 'blockfactory', ?)
        ");
        $log->bind_param("issi", $user_id, $log_desc, $log_ip, $material_id);
        $log->execute();
        $log->close();
        $_SESSION['success'] = 'Material stock updated successfully.';
    } else {
        $_SESSION['error'] = 'Failed to update stock.';
    }
    header('Location: index.php');
    exit();
}

$stmt = $db->prepare("SELECT * FROM blockfactory_raw_materials WHERE material_id = ?");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$mat = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$mat) { header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receive Material - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Receive Material — <?php echo htmlspecialchars($mat['material_name']); ?></h4>
            <a href="../modules/blockfactory/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <div class="card" style="max-width: 500px;">
            <div class="card-body">
                <table class="table table-sm table-borderless mb-4">
                    <tr><th class="text-muted" style="width:40%">Material</th><td><?php echo htmlspecialchars($mat['material_name']); ?></td></tr>
                    <tr><th class="text-muted">Type</th><td><?php echo $mat['material_type']; ?></td></tr>
                    <tr><th class="text-muted">Current Stock</th><td><strong><?php echo $mat['stock_quantity']; ?> <?php echo $mat['unit']; ?></strong></td></tr>
                    <tr><th class="text-muted">Min Stock</th><td><?php echo $mat['minimum_stock']; ?> <?php echo $mat['unit']; ?></td></tr>
                </table>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Quantity Received (<?php echo $mat['unit']; ?>)</label>
                        <input type="number" step="0.01" class="form-control" name="quantity_received"
                            min="0.01" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"
                            placeholder="Supplier, delivery note number, etc."></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-arrow-down me-1"></i>Receive Stock
                    </button>
                    <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>