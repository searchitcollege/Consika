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

$stmt = $db->prepare("
    SELECT s.*,
        COUNT(po.po_id) as total_orders,
        COALESCE(SUM(po.total_amount), 0) as total_value,
        SUM(CASE WHEN po.delivery_status = 'Completed' THEN 1 ELSE 0 END) as completed_orders,
        AVG(CASE WHEN po.delivery_date IS NOT NULL AND po.expected_delivery IS NOT NULL
            THEN DATEDIFF(po.delivery_date, po.expected_delivery) ELSE NULL END) as avg_delay_days
    FROM procurement_suppliers s
    LEFT JOIN procurement_purchase_orders po ON po.supplier_id = s.supplier_id
    WHERE s.company_id = ?
    GROUP BY s.supplier_id
    ORDER BY total_value DESC
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$suppliers = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supplier Performance - <?php echo APP_NAME; ?></title>
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
            <h4>Supplier Performance</h4>
            <a href="../modules/procurement/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0" id="perfTable">
                    <thead class="table-light">
                        <tr>
                            <th>Supplier</th>
                            <th>Category</th>
                            <th>Total Orders</th>
                            <th>Completed</th>
                            <th>Completion Rate</th>
                            <th>Total Value</th>
                            <th>Avg Delay (days)</th>
                            <th>Rating</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($s = $suppliers->fetch_assoc()):
                        $rate = $s['total_orders'] > 0
                            ? round(($s['completed_orders'] / $s['total_orders']) * 100) : 0;
                        $rate_color = $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger');
                        $delay = $s['avg_delay_days'] !== null ? round($s['avg_delay_days'], 1) : null;
                        $delay_color = $delay === null ? 'secondary'
                            : ($delay <= 0 ? 'success' : ($delay <= 3 ? 'warning' : 'danger'));
                    ?>
                        <tr>
                            <td>
                                <a href="view-supplier.php?id=<?php echo $s['supplier_id']; ?>" class="fw-semibold text-decoration-none">
                                    <?php echo htmlspecialchars($s['supplier_name']); ?>
                                </a>
                                <div class="text-muted small"><?php echo $s['supplier_code']; ?></div>
                            </td>
                            <td><?php echo $s['category'] ?: '—'; ?></td>
                            <td><?php echo $s['total_orders']; ?></td>
                            <td><?php echo $s['completed_orders']; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 8px;">
                                        <div class="progress-bar bg-<?php echo $rate_color; ?>"
                                            style="width: <?php echo $rate; ?>%"></div>
                                    </div>
                                    <small><?php echo $rate; ?>%</small>
                                </div>
                            </td>
                            <td><?php echo format_money($s['total_value']); ?></td>
                            <td>
                                <?php if ($delay !== null): ?>
                                    <span class="badge bg-<?php echo $delay_color; ?>">
                                        <?php echo $delay >= 0 ? '+' . $delay : $delay; ?> days
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= ($s['rating'] ?? 0) ? 'text-warning' : 'text-secondary'; ?>" style="font-size: 12px;"></i>
                                <?php endfor; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $s['status'] == 'Active' ? 'success' : ($s['status'] == 'Blacklisted' ? 'danger' : 'secondary'); ?>">
                                    <?php echo $s['status']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
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
<script>$('#perfTable').DataTable({ order: [[5, 'desc']] });</script>
</body>
</html>