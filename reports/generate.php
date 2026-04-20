<?php
require_once '../includes/session.php';
$session->requireLogin();
global $db;

$type    = trim($_GET['type']    ?? '');
$company = trim($_GET['company'] ?? '');

$company_id = $session->getCompanyId();
if (empty($company_id)) {
    if ($company) {
        $type_map = ['estate' => 'Estate', 'procurement' => 'Procurement', 'works' => 'Works', 'blockfactory' => 'Block Factory'];
        $ctype    = $type_map[$company] ?? null;
        if ($ctype) {
            $res        = $db->query("SELECT company_id FROM companies WHERE company_type = '{$ctype}' LIMIT 1");
            $company_id = (int)($res->fetch_assoc()['company_id'] ?? 0);
        }
    }
}

$title = match($type) {
    'rent-collection' => 'Rent Collection Report',
    'occupancy'       => 'Occupancy Analysis',
    default           => 'Report'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h4><?php echo $title; ?></h4>
            <div>
                <button onclick="window.print()" class="btn btn-primary btn-sm me-2">
                    <i class="fas fa-print me-1"></i>Print
                </button>
                <a href="../modules/<?php echo $company; ?>/index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <?php if ($type === 'rent-collection'): ?>
            <?php
            $month = date('Y-m');
            $stmt  = $db->prepare("
                SELECT ep.*, et.full_name as tenant_name, epr.property_name
                FROM estate_payments ep
                JOIN estate_tenants et ON ep.tenant_id = et.tenant_id
                JOIN estate_properties epr ON ep.property_id = epr.property_id
                WHERE epr.company_id = ?
                AND DATE_FORMAT(ep.payment_date, '%Y-%m') = ?
                ORDER BY ep.payment_date DESC
            ");
            $stmt->bind_param("is", $company_id, $month);
            $stmt->execute();
            $payments = $stmt->get_result();
            $total    = 0;
            $rows     = [];
            while ($row = $payments->fetch_assoc()) { $rows[] = $row; $total += $row['amount']; }
            ?>
            <div class="card">
                <div class="card-header">
                    <strong>Rent Collection — <?php echo date('F Y'); ?></strong>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Tenant</th><th>Property</th><th>Date</th><th>Method</th><th>Amount</th><th>Receipt</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['tenant_name']); ?></td>
                                <td><?php echo htmlspecialchars($p['property_name']); ?></td>
                                <td><?php echo format_date($p['payment_date']); ?></td>
                                <td><?php echo $p['payment_method']; ?></td>
                                <td><?php echo format_money($p['amount']); ?></td>
                                <td><?php echo $p['receipt_number']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Total Collected:</td>
                                <td class="fw-bold text-success"><?php echo format_money($total); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        <?php elseif ($type === 'occupancy'): ?>
            <?php
            $stmt = $db->prepare("
                SELECT p.*,
                    COUNT(t.tenant_id) as tenant_count,
                    COALESCE(SUM(t.monthly_rent), 0) as total_rent
                FROM estate_properties p
                LEFT JOIN estate_tenants t ON p.property_id = t.property_id AND t.status = 'Active'
                WHERE p.company_id = ?
                GROUP BY p.property_id
                ORDER BY p.property_name
            ");
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            $props = $stmt->get_result();
            ?>
            <div class="card">
                <div class="card-header"><strong>Occupancy Analysis</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Property</th><th>Type</th><th>Units</th><th>Tenants</th><th>Occupancy</th><th>Monthly Rent</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php while ($p = $props->fetch_assoc()):
                            $occ_pct = $p['units'] > 0 ? round(($p['tenant_count'] / $p['units']) * 100) : 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['property_name']); ?></td>
                                <td><?php echo $p['property_type']; ?></td>
                                <td><?php echo $p['units']; ?></td>
                                <td><?php echo $p['tenant_count']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height:8px; min-width:80px;">
                                            <div class="progress-bar bg-<?php echo $occ_pct >= 80 ? 'success' : ($occ_pct >= 50 ? 'warning' : 'danger'); ?>"
                                                style="width: <?php echo $occ_pct; ?>%"></div>
                                        </div>
                                        <small><?php echo $occ_pct; ?>%</small>
                                    </div>
                                </td>
                                <td><?php echo format_money($p['total_rent']); ?></td>
                                <td><span class="badge bg-<?php echo $p['status'] == 'Occupied' ? 'primary' : ($p['status'] == 'Available' ? 'success' : 'warning'); ?>"><?php echo $p['status']; ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-file-chart-bar fa-4x text-muted mb-3 d-block opacity-50"></i>
                    <h5 class="text-muted">Report type "<?php echo htmlspecialchars($type); ?>" not configured.</h5>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>