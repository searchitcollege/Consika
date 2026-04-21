<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('procurement', 'edit')) { header('Location: index.php'); exit(); }
global $db;
$supplier_id = (int)($_GET['id'] ?? 0);
if (!$supplier_id) { header('Location: index.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_name  = trim($_POST['supplier_name']  ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '') ?: null;
    $phone          = trim($_POST['phone']          ?? '');
    $email          = trim($_POST['email']          ?? '') ?: null;
    $website        = trim($_POST['website']        ?? '') ?: null;
    $address        = trim($_POST['address']        ?? '') ?: null;
    $category       = trim($_POST['category']       ?? '') ?: null;
    $tax_number     = trim($_POST['tax_number']     ?? '') ?: null;
    $payment_terms  = trim($_POST['payment_terms']  ?? '') ?: null;
    $credit_limit   = $_POST['credit_limit'] !== '' ? (float)$_POST['credit_limit'] : null;
    $status         = trim($_POST['status']         ?? 'Active');

    $stmt = $db->prepare("
        UPDATE procurement_suppliers SET
            supplier_name = ?, contact_person = ?, phone = ?, email = ?, website = ?,
            address = ?, category = ?, tax_number = ?, payment_terms = ?,
            credit_limit = ?, status = ?
        WHERE supplier_id = ?
    ");
    $stmt->bind_param(
        "sssssssssdsi",
        $supplier_name, $contact_person, $phone, $email, $website,
        $address, $category, $tax_number, $payment_terms,
        $credit_limit, $status, $supplier_id
    );
    if ($stmt->execute()) { $_SESSION['success'] = 'Supplier updated successfully.'; }
    else { $_SESSION['error'] = 'Failed to update supplier.'; }
    $stmt->close();
    header("Location: ./view-supplier.php?id={$supplier_id}");
    exit();
}

$stmt = $db->prepare("SELECT * FROM procurement_suppliers WHERE supplier_id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$s) { header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Supplier - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Edit Supplier — <?php echo htmlspecialchars($s['supplier_name']); ?></h4>
            <a href="./view-supplier.php?id=<?php echo $supplier_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
        <?php if (isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Supplier Name</label><input type="text" class="form-control" name="supplier_name" value="<?php echo htmlspecialchars($s['supplier_name']); ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Contact Person</label><input type="text" class="form-control" name="contact_person" value="<?php echo htmlspecialchars($s['contact_person'] ?? ''); ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" class="form-control" name="phone" value="<?php echo $s['phone']; ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?php echo $s['email'] ?? ''; ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Website</label><input type="url" class="form-control" name="website" value="<?php echo $s['website'] ?? ''; ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Category</label><input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($s['category'] ?? ''); ?>"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($s['address'] ?? ''); ?></textarea></div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Tax Number</label><input type="text" class="form-control" name="tax_number" value="<?php echo $s['tax_number'] ?? ''; ?>"></div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Payment Terms</label>
                            <select class="form-control" name="payment_terms">
                                <?php foreach (['Cash on Delivery','Net 15','Net 30','Net 60'] as $pt): ?>
                                    <option value="<?php echo $pt; ?>" <?php echo ($s['payment_terms'] ?? '') == $pt ? 'selected' : ''; ?>><?php echo $pt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3"><label class="form-label">Credit Limit</label><input type="number" step="0.01" class="form-control" name="credit_limit" value="<?php echo $s['credit_limit'] ?? ''; ?>"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status">
                            <?php foreach (['Active','Inactive','Blacklisted'] as $st): ?>
                                <option value="<?php echo $st; ?>" <?php echo $s['status'] == $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="./view-supplier.php?id=<?php echo $supplier_id; ?>" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>