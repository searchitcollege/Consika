<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('estate', 'edit')) {
    header('Location: index.php'); exit();
}
global $db;
$property_id = (int)($_GET['id'] ?? 0);
if (!$property_id) { header('Location: index.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $property_name  = trim($_POST['property_name']  ?? '');
    $property_type  = trim($_POST['property_type']  ?? '');
    $address        = trim($_POST['address']        ?? '');
    $city           = trim($_POST['city']           ?? '') ?: null;
    $total_area     = $_POST['total_area']     !== '' ? (float)$_POST['total_area']     : null;
    $units          = (int)($_POST['units']    ?? 1);
    $purchase_price = $_POST['purchase_price'] !== '' ? (float)$_POST['purchase_price'] : null;
    $current_value  = $_POST['current_value']  !== '' ? (float)$_POST['current_value']  : null;
    $status         = trim($_POST['status']    ?? 'Available');
    $description    = trim($_POST['description'] ?? '') ?: null;
    $updated_by     = (int)currentUser()['user_id'];

    $stmt = $db->prepare("
        UPDATE estate_properties SET
            property_name = ?, property_type = ?, address = ?, city = ?,
            total_area = ?, units = ?, purchase_price = ?, current_value = ?,
            status = ?, description = ?
        WHERE property_id = ?
    ");
    $stmt->bind_param(
        "ssssdiddssi",
        $property_name, $property_type, $address, $city,
        $total_area, $units, $purchase_price, $current_value,
        $status, $description, $property_id
    );
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Property updated successfully.';
    } else {
        $_SESSION['error'] = 'Failed to update property.';
    }
    $stmt->close();
    header("Location: ./property-details.php?id={$property_id}");
    exit();
}

$stmt = $db->prepare("SELECT * FROM estate_properties WHERE property_id = ?");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$p) { header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Property - <?php echo APP_NAME; ?></title>
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
            <h4>Edit Property — <?php echo htmlspecialchars($p['property_name']); ?></h4>
            <a href="./property-details.php?id=<?php echo $property_id; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back
            </a>
        </div>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Property Name</label>
                            <input type="text" class="form-control" name="property_name" value="<?php echo htmlspecialchars($p['property_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Property Type</label>
                            <select class="form-control" name="property_type" required>
                                <?php foreach (['Residential','Commercial','Land','Industrial'] as $t): ?>
                                    <option value="<?php echo $t; ?>" <?php echo $p['property_type'] == $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2" required><?php echo htmlspecialchars($p['address']); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($p['city'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Total Area (sqm)</label>
                            <input type="number" step="0.01" class="form-control" name="total_area" value="<?php echo $p['total_area'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Units</label>
                            <input type="number" class="form-control" name="units" value="<?php echo $p['units']; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Purchase Price</label>
                            <input type="number" step="0.01" class="form-control" name="purchase_price" value="<?php echo $p['purchase_price'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Current Value</label>
                            <input type="number" step="0.01" class="form-control" name="current_value" value="<?php echo $p['current_value'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status">
                                <?php foreach (['Available','Occupied','Under Maintenance','Under Construction','Sold'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $p['status'] == $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($p['description'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="./property-details.php?id=<?php echo $property_id; ?>" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>