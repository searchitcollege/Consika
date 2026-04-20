<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('estate', 'view')) {
    header('Location: ../../admin/dashboard.php'); exit();
}
global $db;
$property_id = (int)($_GET['id'] ?? 0);
if (!$property_id) { header('Location: index.php'); exit(); }

$stmt = $db->prepare("SELECT * FROM estate_properties WHERE property_id = ?");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$property) { $_SESSION['error'] = 'Property not found.'; header('Location: index.php'); exit(); }

$tenants_stmt = $db->prepare("SELECT * FROM estate_tenants WHERE property_id = ? ORDER BY lease_start_date DESC");
$tenants_stmt->bind_param("i", $property_id);
$tenants_stmt->execute();
$tenants = $tenants_stmt->get_result();

$payments_stmt = $db->prepare("
    SELECT ep.*, et.full_name as tenant_name
    FROM estate_payments ep
    JOIN estate_tenants et ON ep.tenant_id = et.tenant_id
    WHERE ep.property_id = ?
    ORDER BY ep.payment_date DESC LIMIT 20
");
$payments_stmt->bind_param("i", $property_id);
$payments_stmt->execute();
$payments = $payments_stmt->get_result();

$maintenance_stmt = $db->prepare("
    SELECT * FROM estate_maintenance WHERE property_id = ?
    ORDER BY request_date DESC LIMIT 10
");
$maintenance_stmt->bind_param("i", $property_id);
$maintenance_stmt->execute();
$maintenance = $maintenance_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($property['property_name']); ?> - <?php echo APP_NAME; ?></title>
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
            <div>
                <h4><?php echo htmlspecialchars($property['property_name']); ?></h4>
                <span class="badge bg-<?php echo $property['status'] == 'Available' ? 'success' : ($property['status'] == 'Occupied' ? 'primary' : 'warning'); ?>">
                    <?php echo $property['status']; ?>
                </span>
                <span class="badge bg-secondary ms-2"><?php echo $property['property_type']; ?></span>
            </div>
            <div>
                <a href="./edit-property.php?id=<?php echo $property_id; ?>" class="btn btn-primary btn-sm me-2">
                    <i class="fas fa-edit me-1"></i>Edit
                </a>
                <a href="../modules/estate/index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Property Details</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Code</th><td><?php echo $property['property_code']; ?></td></tr>
                            <tr><th class="text-muted">Address</th><td><?php echo htmlspecialchars($property['address']); ?></td></tr>
                            <tr><th class="text-muted">City</th><td><?php echo $property['city'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Units</th><td><?php echo $property['units']; ?></td></tr>
                            <tr><th class="text-muted">Total Area</th><td><?php echo $property['total_area'] ? $property['total_area'] . ' sqm' : '—'; ?></td></tr>
                            <tr><th class="text-muted">Purchase Price</th><td><?php echo $property['purchase_price'] ? format_money($property['purchase_price']) : '—'; ?></td></tr>
                            <tr><th class="text-muted">Current Value</th><td><?php echo $property['current_value'] ? format_money($property['current_value']) : '—'; ?></td></tr>
                        </table>
                        <?php if ($property['description']): ?>
                            <p class="mt-2 small text-muted"><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Active Tenants</div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Name</th><th>Rent</th><th>Lease Ends</th><th></th></tr>
                            </thead>
                            <tbody>
                            <?php while ($t = $tenants->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($t['full_name']); ?></td>
                                    <td><?php echo format_money($t['monthly_rent']); ?></td>
                                    <td><?php echo format_date($t['lease_end_date']); ?></td>
                                    <td><a href="./tenant-details.php?id=<?php echo $t['tenant_id']; ?>" class="btn btn-xs btn-outline-primary btn-sm">View</a></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header fw-semibold">Recent Payments</div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Tenant</th><th>Amount</th><th>Date</th><th>Receipt</th></tr>
                            </thead>
                            <tbody>
                            <?php while ($p = $payments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['tenant_name']); ?></td>
                                    <td><?php echo format_money($p['amount']); ?></td>
                                    <td><?php echo format_date($p['payment_date']); ?></td>
                                    <td><a href="./receipt.php?id=<?php echo $p['payment_id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print"></i></a></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header fw-semibold">Maintenance History</div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Issue</th><th>Priority</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                            <?php while ($m = $maintenance->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(substr($m['description'], 0, 40)); ?>...</td>
                                    <td><span class="badge bg-<?php echo $m['priority'] == 'Emergency' ? 'danger' : ($m['priority'] == 'High' ? 'warning' : 'info'); ?>"><?php echo $m['priority']; ?></span></td>
                                    <td><span class="badge bg-<?php echo $m['status'] == 'Completed' ? 'success' : 'secondary'; ?>"><?php echo $m['status']; ?></span></td>
                                    <td><?php echo format_date($m['request_date']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>