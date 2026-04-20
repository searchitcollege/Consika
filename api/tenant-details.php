<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('estate', 'view')) { header('Location: index.php'); exit(); }
global $db;
$tenant_id = (int)($_GET['id'] ?? 0);
if (!$tenant_id) { header('Location: index.php'); exit(); }

$stmt = $db->prepare("
    SELECT t.*, p.property_name, p.address as property_address
    FROM estate_tenants t
    JOIN estate_properties p ON t.property_id = p.property_id
    WHERE t.tenant_id = ?
");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$tenant = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$tenant) { $_SESSION['error'] = 'Tenant not found.'; header('Location: index.php'); exit(); }

$payments_stmt = $db->prepare("
    SELECT * FROM estate_payments WHERE tenant_id = ? ORDER BY payment_date DESC LIMIT 20
");
$payments_stmt->bind_param("i", $tenant_id);
$payments_stmt->execute();
$payments = $payments_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($tenant['full_name']); ?> - <?php echo APP_NAME; ?></title>
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
                <h4><?php echo htmlspecialchars($tenant['full_name']); ?></h4>
                <span class="badge bg-<?php echo $tenant['status'] == 'Active' ? 'success' : 'secondary'; ?>"><?php echo $tenant['status']; ?></span>
                <span class="text-muted ms-2 small"><?php echo $tenant['tenant_code']; ?></span>
            </div>
            <div>
                <a href="./edit-tenant.php?id=<?php echo $tenant_id; ?>" class="btn btn-primary btn-sm me-2"><i class="fas fa-edit me-1"></i>Edit</a>
                <a href="../modules/estate/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Tenant Information</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Property</th><td><?php echo htmlspecialchars($tenant['property_name']); ?></td></tr>
                            <tr><th class="text-muted">ID Type</th><td><?php echo $tenant['id_type']; ?></td></tr>
                            <tr><th class="text-muted">ID Number</th><td><?php echo $tenant['id_number']; ?></td></tr>
                            <tr><th class="text-muted">Phone</th><td><?php echo $tenant['phone']; ?></td></tr>
                            <tr><th class="text-muted">Email</th><td><?php echo $tenant['email'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Occupation</th><td><?php echo $tenant['occupation'] ?: '—'; ?></td></tr>
                            <tr><th class="text-muted">Employer</th><td><?php echo $tenant['employer'] ?: '—'; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Lease Details</div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:40%">Start Date</th><td><?php echo format_date($tenant['lease_start_date']); ?></td></tr>
                            <tr><th class="text-muted">End Date</th><td><?php echo format_date($tenant['lease_end_date']); ?></td></tr>
                            <tr><th class="text-muted">Duration</th><td><?php echo $tenant['lease_duration_months'] ? $tenant['lease_duration_months'] . ' months' : '—'; ?></td></tr>
                            <tr><th class="text-muted">Monthly Rent</th><td><strong><?php echo format_money($tenant['monthly_rent']); ?></strong></td></tr>
                            <tr><th class="text-muted">Deposit</th><td><?php echo $tenant['deposit_amount'] ? format_money($tenant['deposit_amount']) : '—'; ?></td></tr>
                            <tr><th class="text-muted">Payment Frequency</th><td><?php echo $tenant['payment_frequency']; ?></td></tr>
                            <tr><th class="text-muted">Emergency Contact</th><td><?php echo $tenant['emergency_contact_name'] ?: '—'; ?> <?php echo $tenant['emergency_contact_phone'] ? '(' . $tenant['emergency_contact_phone'] . ')' : ''; ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">Payment History</div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Receipt</th><th>Date</th><th>Period</th><th>Amount</th><th>Method</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php if ($payments->num_rows > 0): while ($pay = $payments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $pay['receipt_number']; ?></td>
                            <td><?php echo format_date($pay['payment_date']); ?></td>
                            <td><?php echo format_date($pay['payment_period_start']); ?> – <?php echo format_date($pay['payment_period_end']); ?></td>
                            <td><?php echo format_money($pay['amount']); ?></td>
                            <td><?php echo $pay['payment_method']; ?></td>
                            <td><span class="badge bg-success"><?php echo $pay['status']; ?></span></td>
                            <td><a href="./receipt.php?id=<?php echo $pay['payment_id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print"></i></a></td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No payments recorded</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>