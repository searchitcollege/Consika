<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('works', 'edit') && !hasPermission('blockfactory', 'view')) { header('Location: index.php'); exit(); }
global $db;
$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) { header('Location: index.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name  = trim($_POST['product_name']  ?? '');
    $unit          = trim($_POST['unit']          ?? '');
    $unit_price    = (float)($_POST['unit_price']   ?? 0);
    $minimum_stock = (int)($_POST['minimum_stock'] ?? 0);
    $maximum_stock = (int)($_POST['maximum_stock'] ?? 1000);
    $reorder_level = (int)($_POST['reorder_level'] ?? 10);
    $location      = trim($_POST['location'] ?? '') ?: null;
    $status        = trim($_POST['status'] ?? 'Active');

    $stmt = $db->prepare("
        UPDATE procurement_products SET
            product_name = ?, unit = ?, unit_price = ?,
            minimum_stock = ?, maximum_stock = ?, reorder_level = ?,
            location = ?, status = ?
        WHERE product_id = ?
    ");
    $stmt->bind_param(
        "ssdiiissi",
        $product_name, $unit, $unit_price,
        $minimum_stock, $maximum_stock, $reorder_level,
        $location, $status,
        $product_id
    );
    if ($stmt->execute()) { $_SESSION['success'] = 'Material updated.'; }
    else { $_SESSION['error'] = 'Update failed.'; }
    $stmt->close();
    header("Location: view-material.php?id={$product_id}");
    exit();
}

$stmt = $db->prepare("SELECT * FROM procurement_products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$mat = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$mat) { header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Material - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Edit Material — <?php echo htmlspecialchars($mat['product_name']); ?></h4>
            <a href="view-product.php?id=<?php echo $product_id; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back
            </a>
        </div>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <div class="card" style="max-width: 700px;">
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Material Name</label>
                        <input type="text" class="form-control" name="product_name"
                            value="<?php echo htmlspecialchars($mat['product_name']); ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit</label>
                            <select class="form-control" name="unit" required>
                                <?php foreach (['pcs','kg','liters','meters','boxes','bags','tons'] as $u): ?>
                                    <option value="<?php echo $u; ?>" <?php echo $mat['unit'] == $u ? 'selected' : ''; ?>><?php echo $u; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit Price</label>
                            <input type="number" step="0.01" class="form-control" name="unit_price"
                                value="<?php echo $mat['unit_price']; ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Min Stock</label>
                            <input type="number" class="form-control" name="minimum_stock" value="<?php echo $mat['minimum_stock']; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Stock</label>
                            <input type="number" class="form-control" name="maximum_stock" value="<?php echo $mat['maximum_stock']; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" class="form-control" name="reorder_level" value="<?php echo $mat['reorder_level']; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location"
                                value="<?php echo htmlspecialchars($mat['location'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status">
                                <?php foreach (['Active','Inactive','Discontinued'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $mat['status'] == $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="view-material.php?id=<?php echo $product_id; ?>" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>