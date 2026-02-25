<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db_connection.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

$session->requireLogin();

if (!isset($_GET['id'])) {
    die('No receipt ID provided');
}

$payment_id = intval($_GET['id']);
$company_id = $session->getCompanyId();

$query = "SELECT p.*, t.full_name as tenant_name, t.tenant_code, t.monthly_rent,
          pr.property_name, pr.property_code, pr.address,
          u.full_name as recorded_by_name
          FROM estate_payments p
          JOIN estate_tenants t ON p.tenant_id = t.tenant_id
          JOIN estate_properties pr ON p.property_id = pr.property_id
          LEFT JOIN users u ON p.recorded_by = u.user_id
          WHERE p.payment_id = ? AND pr.company_id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("ii", $payment_id, $company_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    die('Receipt not found');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?php echo $payment['receipt_number']; ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 20px;
            padding: 0;
            background: #fff;
        }
        .receipt {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            border: 2px dashed #333;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0;
            font-size: 14px;
        }
        .receipt-no {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 15px 0;
            padding: 5px;
            background: #f0f0f0;
        }
        .details {
            margin: 20px 0;
        }
        .details table {
            width: 100%;
            border-collapse: collapse;
        }
        .details td {
            padding: 5px;
            border-bottom: 1px dotted #ccc;
        }
        .details td:first-child {
            font-weight: bold;
            width: 40%;
        }
        .amount {
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background: #f0f0f0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            border-top: 1px solid #333;
            padding-top: 10px;
        }
        .print-btn {
            text-align: center;
            margin-top: 20px;
        }
        .print-btn button {
            padding: 10px 30px;
            font-size: 16px;
            cursor: pointer;
        }
        @media print {
            .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h2><?php echo APP_NAME; ?></h2>
            <p>Estate Management</p>
        </div>
        
        <div class="receipt-no">
            RECEIPT: <?php echo $payment['receipt_number']; ?>
        </div>
        
        <div class="details">
            <table>
                <tr>
                    <td>Date:</td>
                    <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                </tr>
                <tr>
                    <td>Tenant:</td>
                    <td><?php echo htmlspecialchars($payment['tenant_name']); ?></td>
                </tr>
                <tr>
                    <td>Tenant Code:</td>
                    <td><?php echo $payment['tenant_code']; ?></td>
                </tr>
                <tr>
                    <td>Property:</td>
                    <td><?php echo htmlspecialchars($payment['property_name']); ?></td>
                </tr>
                <tr>
                    <td>Property Code:</td>
                    <td><?php echo $payment['property_code']; ?></td>
                </tr>
                <tr>
                    <td>Period:</td>
                    <td><?php echo date('d/m/Y', strtotime($payment['payment_period_start'])); ?> - <?php echo date('d/m/Y', strtotime($payment['payment_period_end'])); ?></td>
                </tr>
                <tr>
                    <td>Payment Method:</td>
                    <td><?php echo $payment['payment_method']; ?></td>
                </tr>
                <?php if ($payment['transaction_reference']): ?>
                <tr>
                    <td>Reference:</td>
                    <td><?php echo $payment['transaction_reference']; ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Recorded By:</td>
                    <td><?php echo $payment['recorded_by_name'] ?? 'System'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="amount">
            AMOUNT PAID: <?php echo formatMoney($payment['amount']); ?>
        </div>
        
        <div class="footer">
            <p>Thank you for your payment!</p>
            <p>This is a computer generated receipt</p>
        </div>
    </div>
    
    <div class="print-btn">
        <button onclick="window.print()">Print Receipt</button>
        <button onclick="window.close()">Close</button>
    </div>
</body>
</html>