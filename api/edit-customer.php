<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('blockfactory', 'edit')) { header('Location: index.php'); exit(); }
global $db;
$customer_id = (int)($_GET['id'] ?? 0);
if (!$customer_id) { header('Location: index.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name  = trim($_POST['customer_name']  ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '') ?: null;
    $phone          = trim($_POST['phone']          ?? '');
    $email          = trim($_POST['email']          ?? '') ?: null;
    $address        = trim($_POST['address']        ?? '') ?: null;
    $city           = trim($_POST['city']           ?? '') ?: null;
    $customer_type  = trim($_POST['customer_type']  ?? 'Individual');
    $tax_number     = trim($_POST['tax_number']     ?? '') ?: null;
    $credit_limit   = $_POST['credit_limit'] !== '' ? (float)$_POST['credit_limit'] : null;
    $status         = trim($_POST['status']         ?? 'Active');
    $notes          = trim($_POST['notes']          ?? '') ?: null;

    $stmt = $db->prepare("
        UPDATE blockfactory_customers SET
            customer_name = ?, contact_person = ?, phone = ?, email = ?,
            address = ?, city = ?, customer_type = ?, tax_number = ?,
            credit_limit = ?, status = ?, notes = ?
        WHERE customer_id = ?
    ");
    $stmt->bind_param(
        "ssssssssdssi",
        $customer_name, $contact_person, $phone, $email,
        $address, $city, $customer_type, $tax_number,
        $credit_limit, $status, $notes,
        $customer_id
    );
    if ($stmt->execute()) { $_SESSION['success'] = 'Customer updated.'; }
    else { $_SESSION['error'] = 'Update failed.'; }
    $stmt->close();
    header("Location: view-customer.php?id={$customer_id}");
    exit();
}

$stmt = $db->prepare("SELECT * FROM blockfactory_customers WHERE customer_id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$c) { header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Customer - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Edit Customer — <?php echo htmlspecialchars($c['customer_name']); ?></h4>
            <a href="./view-customer.php?id=<?php echo $customer_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
        <?php if (isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Customer Name</label><input type="text" class="form-control" name="customer_name" value="<?php echo htmlspecialchars($c['customer_name']); ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Contact Person</label><input type="text" class="form-control" name="contact_person" value="<?php echo htmlspecialchars($c['contact_person'] ?? ''); ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" class="form-control" name="phone" value="<?php echo $c['phone']; ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?php echo $c['email'] ?? ''; ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer Type</label>
                            <select class="form-control" name="customer_type">
                                <?php foreach (['Individual','Company','Contractor','Government'] as $t): ?>
                                    <option value="<?php echo $t; ?>" <?php echo $c['customer_type'] == $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status">
                                <?php foreach (['Active','Inactive'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $c['status'] == $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">City</label><input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($c['city'] ?? ''); ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Tax Number</label><input type="text" class="form-control" name="tax_number" value="<?php echo $c['tax_number'] ?? ''; ?>"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Credit Limit</label><input type="number" step="0.01" class="form-control" name="credit_limit" value="<?php echo $c['credit_limit'] ?? ''; ?>"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($c['address'] ?? ''); ?></textarea></div>
                    <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars($c['notes'] ?? ''); ?></textarea></div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="view-customer.php?id=<?php echo $customer_id; ?>" class="btn btn-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>