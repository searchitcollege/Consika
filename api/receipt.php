<?php
require_once '../includes/session.php';
$session->requireLogin();
global $db;
$payment_id = (int)($_GET['id'] ?? 0);
if (!$payment_id) { header('Location: index.php'); exit(); }

$stmt = $db->prepare("
    SELECT ep.*, et.full_name as tenant_name, et.phone as tenant_phone,
           ep2.property_name, c.company_name, c.phone as company_phone, c.email as company_email, c.address as company_address
    FROM estate_payments ep
    JOIN estate_tenants et ON ep.tenant_id = et.tenant_id
    JOIN estate_properties ep2 ON ep.property_id = ep2.property_id
    JOIN companies c ON ep2.company_id = c.company_id
    WHERE ep.payment_id = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$pay = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$pay) { echo 'Payment not found.'; exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt <?php echo $pay['receipt_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f4f4; }
        .receipt { max-width: 600px; margin: 30px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        .receipt-header { text-align: center; border-bottom: 2px solid #4361ee; padding-bottom: 20px; margin-bottom: 20px; }
        .receipt-title { font-size: 24px; font-weight: 700; color: #4361ee; }
        .receipt-number { font-size: 14px; color: #666; }
        .amount-box { background: #f8f9fa; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
        .amount-box .amount { font-size: 36px; font-weight: 700; color: #28a745; }
        @media print { .no-print { display: none !important; } body { background: white; } .receipt { box-shadow: none; } }
    </style>
</head>
<body>
<div class="receipt">
    <div class="receipt-header">
        <div class="receipt-title"><?php echo htmlspecialchars($pay['company_name']); ?></div>
        <div class="text-muted small"><?php echo $pay['company_address'] ?? ''; ?> | <?php echo $pay['company_phone'] ?? ''; ?></div>
        <div class="receipt-number mt-2"><strong>RENT PAYMENT RECEIPT</strong></div>
        <div class="text-muted"><?php echo $pay['receipt_number']; ?></div>
    </div>

    <div class="amount-box">
        <div class="text-muted small mb-1">Amount Received</div>
        <div class="amount"><?php echo format_money($pay['amount']); ?></div>
    </div>

    <table class="table table-sm table-borderless">
        <tr><th class="text-muted" style="width:40%">Received From</th><td><strong><?php echo htmlspecialchars($pay['tenant_name']); ?></strong></td></tr>
        <tr><th class="text-muted">Property</th><td><?php echo htmlspecialchars($pay['property_name']); ?></td></tr>
        <tr><th class="text-muted">Payment Date</th><td><?php echo format_date($pay['payment_date']); ?></td></tr>
        <tr><th class="text-muted">Payment Period</th><td><?php echo format_date($pay['payment_period_start']); ?> – <?php echo format_date($pay['payment_period_end']); ?></td></tr>
        <tr><th class="text-muted">Payment Method</th><td><?php echo $pay['payment_method']; ?></td></tr>
        <?php if ($pay['transaction_reference']): ?>
        <tr><th class="text-muted">Reference</th><td><?php echo $pay['transaction_reference']; ?></td></tr>
        <?php endif; ?>
        <?php if ($pay['late_fee'] > 0): ?>
        <tr><th class="text-muted">Late Fee</th><td><?php echo format_money($pay['late_fee']); ?></td></tr>
        <tr><th class="text-muted">Total</th><td><strong><?php echo format_money($pay['total_amount']); ?></strong></td></tr>
        <?php endif; ?>
    </table>

    <div class="text-center mt-4 text-muted small">
        <p>Thank you for your payment. This is an official receipt.</p>
        <p>Generated: <?php echo date('d M Y H:i'); ?></p>
    </div>

    <div class="text-center mt-3 no-print">
        <button onclick="window.print()" class="btn btn-primary btn-sm me-2"><i class="fas fa-print me-1"></i>Print</button>
        <button onclick="window.close()" class="btn btn-secondary btn-sm">Close</button>
    </div>
</div>
</body>
</html>