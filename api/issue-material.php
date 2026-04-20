<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('works', 'create')) { header('Location: index.php'); exit(); }
global $db;
$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) { header('Location: index.php'); exit(); }

$company_id = $session->getCompanyId();
if (empty($company_id)) {
    $result     = $db->query("SELECT company_id FROM companies WHERE company_type = 'Works' LIMIT 1");
    $company_id = (int)($result->fetch_assoc()['company_id'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $quantity   = (float)($_POST['quantity']   ?? 0);
    $date_used  = trim($_POST['date_used']     ?? date('Y-m-d'));
    $notes      = trim($_POST['notes']         ?? '') ?: null;
    $created_by = (int)currentUser()['user_id'];

    if (!$project_id || $quantity <= 0) {
        $_SESSION['error'] = 'Please select a project and enter a valid quantity.';
        header("Location: issue-material.php?id={$product_id}");
        exit();
    }

    // Check stock
    $stock_check = $db->prepare("SELECT current_stock, unit_price, product_name FROM procurement_products WHERE product_id = ?");
    $stock_check->bind_param("i", $product_id);
    $stock_check->execute();
    $mat = $stock_check->get_result()->fetch_assoc();
    $stock_check->close();

    if ($quantity > (float)$mat['current_stock']) {
        $_SESSION['error'] = "Insufficient stock. Available: {$mat['current_stock']}";
        header("Location: issue-material.php?id={$product_id}");
        exit();
    }

    $unit_cost  = (float)$mat['unit_price'];
    $total_cost = round($unit_cost * $quantity, 2);

    // Insert usage record
    $stmt = $db->prepare("
        INSERT INTO works_project_materials
            (project_id, material_id, quantity, unit_cost, total_cost, date_used, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iidddsi",
        $project_id, $product_id, $quantity,
        $unit_cost, $total_cost, $date_used, $created_by
    );

    if (!$stmt->execute()) {
        $_SESSION['error'] = 'Failed to issue material.';
        header("Location: issue-material.php?id={$product_id}");
        exit();
    }
    $stmt->close();

    // Reduce stock
    $stock_upd = $db->prepare("
        UPDATE procurement_products
        SET current_stock = current_stock - ?
        WHERE product_id = ?
    ");
    $stock_upd->bind_param("di", $quantity, $product_id);
    $stock_upd->execute();
    $stock_upd->close();

    $log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $log_desc = "Issued {$quantity} of {$mat['product_name']} to project ID {$project_id}";
    $log = $db->prepare("
        INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
        VALUES (?, 'Issue Material', ?, ?, 'works', ?)
    ");
    $log->bind_param("issi", $created_by, $log_desc, $log_ip, $project_id);
    $log->execute();
    $log->close();

    $_SESSION['success'] = "Material issued successfully.";
    header('Location: index.php');
    exit();
}

$stmt = $db->prepare("SELECT * FROM procurement_products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$mat = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$mat) { header('Location: index.php'); exit(); }

$projects_stmt = $db->prepare("
    SELECT project_id, project_name, project_code
    FROM works_projects
    WHERE company_id = ? AND status = 'In Progress'
    ORDER BY project_name
");
$projects_stmt->bind_param("i", $company_id);
$projects_stmt->execute();
$projects = $projects_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Issue Material - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Issue Material — <?php echo htmlspecialchars($mat['product_name']); ?></h4>
            <a href="../modules/works/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <div class="card" style="max-width: 500px;">
            <div class="card-body">
                <table class="table table-sm table-borderless mb-4">
                    <tr><th class="text-muted" style="width:40%">Material</th><td><?php echo htmlspecialchars($mat['product_name']); ?></td></tr>
                    <tr><th class="text-muted">Current Stock</th><td><strong class="<?php echo $mat['current_stock'] <= $mat['minimum_stock'] ? 'text-danger' : 'text-success'; ?>"><?php echo $mat['current_stock']; ?> <?php echo $mat['unit']; ?></strong></td></tr>
                    <tr><th class="text-muted">Unit Cost</th><td><?php echo format_money($mat['unit_price']); ?></td></tr>
                </table>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Project <span class="text-danger">*</span></label>
                        <select class="form-control" name="project_id" required>
                            <option value="">Select Project...</option>
                            <?php while ($proj = $projects->fetch_assoc()): ?>
                                <option value="<?php echo $proj['project_id']; ?>">
                                    <?php echo htmlspecialchars($proj['project_name']); ?> (<?php echo $proj['project_code']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Quantity (<?php echo $mat['unit']; ?>) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="quantity"
                            min="0.01" max="<?php echo $mat['current_stock']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date Used</label>
                        <input type="date" class="form-control" name="date_used" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-arrow-right me-1"></i>Issue Material
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