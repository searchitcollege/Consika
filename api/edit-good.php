<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('blockfactory', 'edit')) { header('Location: index.php'); exit(); }
global $db;
$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) { header('Location: index.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name   = trim($_POST['product_name']   ?? '');
    $product_type   = trim($_POST['product_type']   ?? '');
    $dimensions     = trim($_POST['dimensions']     ?? '');
    $weight_kg      = $_POST['weight_kg']     !== '' ? (float)$_POST['weight_kg']     : null;
    $strength_mpa   = trim($_POST['strength_mpa']   ?? '') ?: null;
    $color          = trim($_POST['color']          ?? 'Grey');
    $price_per_unit = (float)($_POST['price_per_unit'] ?? 0);
    $cost_per_unit  = $_POST['cost_per_unit'] !== '' ? (float)$_POST['cost_per_unit'] : null;
    $minimum_stock  = (int)($_POST['minimum_stock']  ?? 100);
    $reorder_level  = (int)($_POST['reorder_level']  ?? 200);
    $status         = trim($_POST['status']         ?? 'Active');
    $description    = trim($_POST['description']    ?? '') ?: null;

    $stmt = $db->prepare("
        UPDATE blockfactory_products SET
            product_name = ?, product_type = ?, dimensions = ?,
            weight_kg = ?, strength_mpa = ?, color = ?,
            price_per_unit = ?, cost_per_unit = ?,
            minimum_stock = ?, reorder_level = ?,
            status = ?, description = ?
        WHERE product_id = ?
    ");
    $stmt->bind_param(
        "sssdssddiissi",
        $product_name, $product_type, $dimensions,
        $weight_kg, $strength_mpa, $color,
        $price_per_unit, $cost_per_unit,
        $minimum_stock, $reorder_level,
        $status, $description,
        $product_id
    );
    if ($stmt->execute()) { $_SESSION['success'] = 'Product updated.'; }
    else { $_SESSION['error'] = 'Update failed.'; }
    $stmt->close();
    header("Location: view-product.php?id={$product_id}");
    exit();
}

$stmt = $db->prepare("SELECT * FROM blockfactory_products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$p) { header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Edit Product — <?php echo htmlspecialchars($p['product_name']); ?></h4>
            <a href="./view-good.php?id=<?php echo $product_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
        <?php if (isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Product Name</label><input type="text" class="form-control" name="product_name" value="<?php echo htmlspecialchars($p['product_name']); ?>" required></div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Type</label>
                            <select class="form-control" name="product_type" required>
                                <?php foreach (['Solid Block','Hollow Block','Interlocking Block','Paving Block','Kerbstones'] as $t): ?>
                                    <option value="<?php echo $t; ?>" <?php echo $p['product_type'] == $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Dimensions</label><input type="text" class="form-control" name="dimensions" value="<?php echo $p['dimensions']; ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Color</label><input type="text" class="form-control" name="color" value="<?php echo $p['color'] ?? 'Grey'; ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Weight (kg)</label><input type="number" step="0.01" class="form-control" name="weight_kg" value="<?php echo $p['weight_kg'] ?? ''; ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Strength (MPa)</label><input type="text" class="form-control" name="strength_mpa" value="<?php echo $p['strength_mpa'] ?? ''; ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Price per Unit</label><input type="number" step="0.01" class="form-control" name="price_per_unit" value="<?php echo $p['price_per_unit']; ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Cost per Unit</label><input type="number" step="0.01" class="form-control" name="cost_per_unit" value="<?php echo $p['cost_per_unit'] ?? ''; ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Min Stock</label><input type="number" class="form-control" name="minimum_stock" value="<?php echo $p['minimum_stock']; ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Reorder Level</label><input type="number" class="form-control" name="reorder_level" value="<?php echo $p['reorder_level']; ?>"></div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status">
                                <?php foreach (['Active','Inactive'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $p['status'] == $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($p['description'] ?? ''); ?></textarea></div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="view-product.php?id=<?php echo $product_id; ?>" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>