<?php
require_once '../includes/session.php';
$session->requireLogin();
global $db;

$type   = trim($_GET['type']   ?? '');
$format = trim($_GET['format'] ?? 'csv');

$company_id = $session->getCompanyId();
if (empty($company_id)) {
    $res        = $db->query("SELECT company_id FROM companies WHERE company_type = 'Estate' LIMIT 1");
    $company_id = (int)($res->fetch_assoc()['company_id'] ?? 0);
}

if ($type === 'tenants') {
    $stmt = $db->prepare("
        SELECT t.tenant_code, t.full_name, t.id_number, t.phone, t.email,
               p.property_name, t.lease_start_date, t.lease_end_date,
               t.monthly_rent, t.deposit_amount, t.status
        FROM estate_tenants t
        JOIN estate_properties p ON t.property_id = p.property_id
        WHERE p.company_id = ?
        ORDER BY t.full_name
    ");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $rows = $stmt->get_result();

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="tenants_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Code','Name','ID Number','Phone','Email','Property','Lease Start','Lease End','Monthly Rent','Deposit','Status']);
        while ($row = $rows->fetch_assoc()) {
            fputcsv($out, [
                $row['tenant_code'], $row['full_name'], $row['id_number'],
                $row['phone'], $row['email'], $row['property_name'],
                $row['lease_start_date'], $row['lease_end_date'],
                $row['monthly_rent'], $row['deposit_amount'], $row['status']
            ]);
        }
        fclose($out);
        exit();
    }
}

// Fallback
$_SESSION['error'] = 'Export type not supported.';
header('Location: ../modules/estate/index.php');
exit();