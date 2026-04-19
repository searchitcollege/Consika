<?php
require_once '../includes/session.php';
$session->requireLogin();

if (!hasPermission('estate', 'create')) {
    $_SESSION['error'] = 'You do not have permission to record payments.';
    header('Location: ./logout.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/dashboard.php');
    exit();
}

global $db;

$current_user = currentUser();
$company_id   = $session->getCompanyId();

// Resolve company_id for SuperAdmin
if (empty($company_id)) {
    $result     = $db->query("SELECT company_id FROM companies WHERE company_type = 'Estate' LIMIT 1");
    $row        = $result->fetch_assoc();
    $company_id = (int)($row['company_id'] ?? 0);
}

if (empty($company_id)) {
    $_SESSION['error'] = 'Estate company not found.';
    header('Location: ../admin/dashboard.php');
    exit();
}

// ── Sanitise inputs ──────────────────────────────────────────────────────────
$tenant_id             = (int)($_POST['tenant_id']             ?? 0);
$property_id           = (int)($_POST['property_id']           ?? 0);
$payment_date          = trim($_POST['payment_date']           ?? '');
$amount                = isset($_POST['amount']) && $_POST['amount'] !== '' ? (float)$_POST['amount'] : 0;
$payment_method        = trim($_POST['payment_method']         ?? '');
$transaction_reference = trim($_POST['transaction_reference']  ?? '');
$period_start          = trim($_POST['payment_period_start']   ?? '');
$period_end            = trim($_POST['payment_period_end']     ?? '');
$notes                 = trim($_POST['notes']                  ?? '');
$recorded_by           = (int)$current_user['user_id'];

// ── Validation ───────────────────────────────────────────────────────────────
$allowed_methods = ['Cash', 'Bank Transfer', 'Cheque', 'Mobile Money', 'Credit Card'];

if (!$tenant_id || !$property_id || empty($payment_date) || $amount <= 0 ||
    empty($payment_method) || empty($period_start) || empty($period_end)) {
    $_SESSION['error'] = 'Please fill in all required payment fields.';
    header('Location: ../admin/dashboard.php');
    exit();
}

if (!in_array($payment_method, $allowed_methods)) {
    $_SESSION['error'] = 'Invalid payment method selected.';
    header('Location: ../admin/dashboard.php');
    exit();
}

if (!strtotime($payment_date)) {
    $_SESSION['error'] = 'Invalid payment date.';
    header('Location: ../admin/dashboard.php');
    exit();
}

if (!strtotime($period_start) || !strtotime($period_end) || strtotime($period_end) < strtotime($period_start)) {
    $_SESSION['error'] = 'Invalid payment period dates.';
    header('Location: ../admin/dashboard.php');
    exit();
}

// ── Verify tenant & property belong to this company ──────────────────────────
$owner_check = $db->prepare("
    SELECT t.tenant_id, t.monthly_rent, t.full_name
    FROM estate_tenants t
    JOIN estate_properties p ON t.property_id = p.property_id
    WHERE t.tenant_id = ?
      AND p.property_id = ?
      AND p.company_id = ?
      AND t.status = 'Active'
");
$owner_check->bind_param("iii", $tenant_id, $property_id, $company_id);
$owner_check->execute();
$tenant_data = $owner_check->get_result()->fetch_assoc();
$owner_check->close();

if (!$tenant_data) {
    $_SESSION['error'] = 'Invalid tenant or property selection.';
    header('Location: ../admin/dashboard.php');
    exit();
}

$tenant_name  = $tenant_data['full_name'];
$monthly_rent = (float)$tenant_data['monthly_rent'];

// ── Determine payment status ─────────────────────────────────────────────────
if ($amount >= $monthly_rent) {
    $payment_status = 'Paid';
} else {
    $payment_status = 'Partial';
}

// ── Generate unique receipt number and status ───────────────────────────────────────────
$receipt_number = 'RCPT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

// Collision guard
$rcpt_check = $db->prepare("SELECT payment_id FROM estate_payments WHERE receipt_number = ?");
$rcpt_check->bind_param("s", $receipt_number);
$rcpt_check->execute();
$rcpt_check->store_result();
if ($rcpt_check->num_rows > 0) {
    $receipt_number = 'RCPT-' . date('Ymd') . '-' . strtoupper(uniqid());
}
$rcpt_check->close();

// ── Calculations ─────────────────────────────────────────────────────────────
$late_fee     = 0.00;
$total_amount = $amount + $late_fee;

// ── Nullable helpers ─────────────────────────────────────────────────────────
$ref_val   = !empty($transaction_reference) ? $transaction_reference : null;
$notes_val = !empty($notes)                 ? $notes                 : null;

// ── Insert payment ───────────────────────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO estate_payments
        (tenant_id, property_id, payment_date, amount,
         payment_method, transaction_reference,
         payment_period_start, payment_period_end,
         late_fee, total_amount, receipt_number,
         notes, recorded_by, status)
    VALUES
        (?, ?, ?, ?,
         ?, ?,
         ?, ?,
         ?, ?, ?,
         ?, ?, ?)
");

$stmt->bind_param(
    "iisdssssddssss",
    $tenant_id,
    $property_id,
    $payment_date,
    $amount,
    $payment_method,
    $ref_val,
    $period_start,
    $period_end,
    $late_fee,
    $total_amount,
    $receipt_number,
    $notes_val,
    $recorded_by,
    $payment_status
);

if (!$stmt->execute()) {
    $_SESSION['error'] = 'Failed to record payment. Please try again.';
    header('Location: ../admin/dashboard.php');
    exit();
}

$stmt->close();

// ── Retrieve new payment_id ──────────────────────────────────────────────────
$id_result  = $db->query("SELECT LAST_INSERT_ID() AS new_id");
$payment_id = (int)$id_result->fetch_assoc()['new_id'];

// ── Activity log ─────────────────────────────────────────────────────────────
$log_desc = "Recorded payment of {$amount} (Receipt: {$receipt_number}) for tenant: {$tenant_name}";
$log_ip   = $_SERVER['REMOTE_ADDR'] ?? '';
$module   = 'estate';

$log = $db->prepare("
    INSERT INTO activity_log (user_id, action, description, ip_address, module, reference_id)
    VALUES (?, 'Record Payment', ?, ?, ?, ?)
");
$log->bind_param("isssi", $recorded_by, $log_desc, $log_ip, $module, $payment_id);
$log->execute();
$log->close();

$_SESSION['success'] = "Payment recorded successfully. Receipt No: {$receipt_number}";
header('Location: ../admin/dashboard.php');
exit();