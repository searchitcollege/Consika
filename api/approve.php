<?php
require_once '../includes/session.php';
$session->requireLogin();

// Only SuperAdmin or CompanyAdmin can approve
if (!in_array($session->getRole(), ['SuperAdmin', 'CompanyAdmin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$table     = trim($_POST['table']  ?? '');
$record_id = (int)($_POST['id']    ?? 0);
$action    = trim($_POST['action'] ?? ''); // 'approve' or 'reject'
$reason    = trim($_POST['reason'] ?? '') ?: null;

// Whitelist tables and their primary key columns
$allowed = [
    'estate_properties'              => 'property_id',
    'estate_tenants'                 => 'tenant_id',
    'estate_payments'                => 'payment_id',
    'estate_maintenance'             => 'maintenance_id',
    'procurement_suppliers'          => 'supplier_id',
    'procurement_products'           => 'product_id',
    'procurement_purchase_orders'    => 'po_id',
    'works_projects'                 => 'project_id',
    'works_employees'                => 'employee_id',
    'works_daily_reports'            => 'report_id',
    'works_project_materials'        => 'id',
    'blockfactory_production'        => 'production_id',
    'blockfactory_sales'             => 'sale_id',
    'blockfactory_customers'         => 'customer_id',
    'blockfactory_deliveries'        => 'delivery_id',
    'blockfactory_raw_materials'     => 'material_id',
];

if (!isset($allowed[$table]) || !$record_id || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$id_col          = $allowed[$table];
$new_status      = $action === 'approve' ? 'Approved' : 'Rejected';
$approver_id     = (int)currentUser()['user_id'];
$approved_at     = date('Y-m-d H:i:s');

global $db;

// Use safe column names (already whitelisted above)
$stmt = $db->prepare("
    UPDATE `{$table}`
    SET admin_approvals = ?,
        approved_by     = ?,
        approved_at     = ?,
        rejection_reason = ?
    WHERE `{$id_col}` = ?
");
$stmt->bind_param("sissi",
    $new_status, $approver_id, $approved_at, $reason, $record_id
);

if ($stmt->execute()) {
    $stmt->close();
    // Log it
    $log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';
    $log_desc = "{$new_status} record ID {$record_id} in {$table}" . ($reason ? " | Reason: {$reason}" : '');
    $log = $db->prepare("
        INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
        VALUES (?, ?, ?, ?, 'approvals', ?)
    ");
    $log->bind_param("isssi", $approver_id, $new_status, $log_desc, $log_ip, $record_id);
    $log->execute();
    $log->close();
    echo json_encode(['success' => true, 'status' => $new_status]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}