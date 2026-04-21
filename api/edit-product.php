<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('procurement', 'edit')) { header('Location: index.php'); exit(); }
global $db;
$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) { header('Location: index.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_code  = trim($_POST['product_code']  ?? '');
    $product_name  = trim($_POST['product_name']  ?? '');
    $category      = trim($_POST['category']      ?? '') ?: null;
    $sub_category  = trim($_POST['sub_category']  ?? '') ?: null;
    $description   = trim($_POST['description']   ?? '') ?: null;
    $unit          = trim($_POST['unit']          ?? '');
    $minimum_stock = (int)($_POST['minimum_stock'] ?? 0);
    $maximum_stock = (int)($_POST['maximum_stock'] ?? 1000);
    $reorder_level = (int)($_POST['reorder_level'] ?? 10);
    $unit_price    = (float)($_POST['unit_price']   ?? 0);
    $selling_price = $_POST['selling_price'] !== '' ? (float)$_POST['selling_price'] : null;
    $tax_rate      = (float)($_POST['tax_rate'] ?? 16);
    $location      = trim($_POST['location'] ?? '') ?: null;
    $status        = trim($_POST['status'] ?? 'Active');

    $stmt = $db->prepare("
        UPDATE procurement_products SET
            product_code = ?, product_name = ?, category = ?, sub_category = ?,
            description = ?, unit = ?, minimum_stock = ?, maximum_stock = ?,
            reorder_level = ?, unit_price = ?, selling_price = ?,
            tax_rate = ?, location = ?, status = ?
        WHERE product_id = ?
    ");
    $stmt->bind_param(
        "ssssssiiiiidssi",
        $product_code, $product_name, $category, $sub_category,
        $description, $unit, $minimum_stock, $maximum_stock,
        $reorder_level, $unit_price, $selling_price,
        $tax_rate, $location, $status,
        $product_id
    );

    if ($stmt->execute()) { $_SESSION['success'] = 'Product updated.'; }
    else { $_SESSION['error'] = 'Update failed.'; }
    $stmt->close();
    header("Location: view-product.php?id={$product_id}");
    exit();
}

$stmt = $db->prepare("SELECT * FROM procurement_products WHERE product_id = ?");
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
            <a href="./view-product.php?id=<?php echo $product_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
        <?php if (isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Product Code</label><input type="text" class="form-control" name="product_code" value="<?php echo htmlspecialchars($p['product_code']); ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Product Name</label><input type="text" class="form-control" name="product_name" value="<?php echo htmlspecialchars($p['product_name']); ?>" required></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Category</label><input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($p['category'] ?? ''); ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Sub Category</label><input type="text" class="form-control" name="sub_category" value="<?php echo htmlspecialchars($p['sub_category'] ?? ''); ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit</label>
                            <select class="form-control" name="unit" required>
                                <?php foreach (['pcs','kg','liters','meters','boxes','bags'] as $u): ?>
                                    <option value="<?php echo $u; ?>" <?php echo $p['unit'] == $u ? 'selected' : ''; ?>><?php echo $u; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status">
                                <?php foreach (['Active','Inactive','Discontinued'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $p['status'] == $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Min Stock</label><input type="number" class="form-control" name="minimum_stock" value="<?php echo $p['minimum_stock']; ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Max Stock</label><input type="number" class="form-control" name="maximum_stock" value="<?php echo $p['maximum_stock']; ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Reorder Level</label><input type="number" class="form-control" name="reorder_level" value="<?php echo $p['reorder_level']; ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Unit Price</label><input type="number" step="0.01" class="form-control" name="unit_price" value="<?php echo $p['unit_price']; ?>" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Selling Price</label><input type="number" step="0.01" class="form-control" name="selling_price" value="<?php echo $p['selling_price'] ?? ''; ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Tax Rate (%)</label><input type="number" step="0.01" class="form-control" name="tax_rate" value="<?php echo $p['tax_rate']; ?>"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Location</label><input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($p['location'] ?? ''); ?>"></div>
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