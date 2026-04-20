<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('procurement', 'view')) { header('Location: index.php'); exit(); }
global $db;

$company_id = $session->getCompanyId();
if (empty($company_id)) {
    $result     = $db->query("SELECT company_id FROM companies WHERE company_type = 'Procurement' LIMIT 1");
    $company_id = (int)($result->fetch_assoc()['company_id'] ?? 0);
}

$products = $db->query("
    SELECT *,
        CASE
            WHEN current_stock <= 0 THEN 'Out of Stock'
            WHEN current_stock <= minimum_stock THEN 'Low Stock'
            WHEN current_stock <= reorder_level THEN 'Reorder Soon'
            ELSE 'OK'
        END as stock_status
    FROM procurement_products
    WHERE status = 'Active'
    ORDER BY (current_stock - minimum_stock) ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Check - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Inventory Check</h4>
            <a href="../modules/works/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>

        <?php
        $out = 0; $low = 0; $reorder = 0; $ok = 0;
        $all = [];
        while ($p = $products->fetch_assoc()) {
            $all[] = $p;
            if ($p['stock_status'] == 'Out of Stock') $out++;
            elseif ($p['stock_status'] == 'Low Stock') $low++;
            elseif ($p['stock_status'] == 'Reorder Soon') $reorder++;
            else $ok++;
        }
        ?>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center border-danger">
                    <div class="card-body">
                        <div class="text-danger fw-bold fs-2"><?php echo $out; ?></div>
                        <div class="text-muted small">Out of Stock</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <div class="text-warning fw-bold fs-2"><?php echo $low; ?></div>
                        <div class="text-muted small">Low Stock</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-info">
                    <div class="card-body">
                        <div class="text-info fw-bold fs-2"><?php echo $reorder; ?></div>
                        <div class="text-muted small">Reorder Soon</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <div class="text-success fw-bold fs-2"><?php echo $ok; ?></div>
                        <div class="text-muted small">OK</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">All Products</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0" id="inventoryTable">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Product</th>
                            <th>Unit</th>
                            <th>Current</th>
                            <th>Min</th>
                            <th>Reorder</th>
                            <th>Max</th>
                            <th>Status</th>
                            <th>Stock Bar</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($all as $p):
                        $pct = $p['maximum_stock'] > 0
                            ? min(100, round(($p['current_stock'] / $p['maximum_stock']) * 100))
                            : 0;
                        $bar_color = $p['stock_status'] == 'Out of Stock' ? 'bg-danger'
                            : ($p['stock_status'] == 'Low Stock' ? 'bg-warning'
                            : ($p['stock_status'] == 'Reorder Soon' ? 'bg-info' : 'bg-success'));
                        $badge_color = $p['stock_status'] == 'Out of Stock' ? 'danger'
                            : ($p['stock_status'] == 'Low Stock' ? 'warning'
                            : ($p['stock_status'] == 'Reorder Soon' ? 'info' : 'success'));
                    ?>
                        <tr>
                            <td><?php echo $p['product_code']; ?></td>
                            <td><?php echo htmlspecialchars($p['product_name']); ?></td>
                            <td><?php echo $p['unit']; ?></td>
                            <td class="fw-bold <?php echo $p['current_stock'] <= $p['minimum_stock'] ? 'text-danger' : ''; ?>">
                                <?php echo $p['current_stock']; ?>
                            </td>
                            <td class="text-muted"><?php echo $p['minimum_stock']; ?></td>
                            <td class="text-muted"><?php echo $p['reorder_level']; ?></td>
                            <td class="text-muted"><?php echo $p['maximum_stock']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $badge_color; ?>">
                                    <?php echo $p['stock_status']; ?>
                                </span>
                            </td>
                            <td style="width: 120px;">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar <?php echo $bar_color; ?>"
                                        style="width: <?php echo $pct; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $pct; ?>%</small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script>$('#inventoryTable').DataTable({ order: [[3, 'asc']] });</script>
</body>
</html>