<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db_connection.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

$session->requireLogin();

$company_id = $session->getCompanyId();
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="payments_' . $month . '.csv"');

$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, ['Receipt No.', 'Date', 'Tenant', 'Property', 'Period Start', 'Period End', 'Amount', 'Method', 'Reference', 'Recorded By']);

// Get payments for the month
$query = "SELECT p.receipt_number, p.payment_date, t.full_name as tenant, 
          pr.property_name, p.payment_period_start, p.payment_period_end,
          p.amount, p.payment_method, p.transaction_reference, u.full_name as recorded_by
          FROM estate_payments p
          JOIN estate_tenants t ON p.tenant_id = t.tenant_id
          JOIN estate_properties pr ON p.property_id = pr.property_id
          LEFT JOIN users u ON p.recorded_by = u.user_id
          WHERE pr.company_id = ? AND DATE_FORMAT(p.payment_date, '%Y-%m') = ?
          ORDER BY p.payment_date DESC";

$stmt = $db->prepare($query);
$stmt->bind_param("is", $company_id, $month);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['receipt_number'],
        date('d/m/Y', strtotime($row['payment_date'])),
        $row['tenant'],
        $row['property_name'],
        date('d/m/Y', strtotime($row['payment_period_start'])),
        date('d/m/Y', strtotime($row['payment_period_end'])),
        $row['amount'],
        $row['payment_method'],
        $row['transaction_reference'],
        $row['recorded_by']
    ]);
}

fclose($output);
?>