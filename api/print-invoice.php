<?php
require_once '../includes/session.php';
$session->requireLogin();
global $db;
$sale_id = (int)($_GET['id'] ?? 0);
if (!$sale_id) { echo 'Invalid sale.'; exit(); }

$stmt = $db->prepare("
    SELECT s.*, pr.product_name, pr.dimensions,
           c.company_name, c.address as company_address, c.phone as company_phone, c.email as company_email
    FROM blockfactory_sales s
    JOIN blockfactory_products pr ON s.product_id = pr.product_id
    JOIN companies c ON c.company_type = 'Block Factory'
    WHERE s.sale_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$sale) { echo 'Sale not found.'; exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo $sale['invoice_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: white; font-size: 14px; }
        .invoice-box { max-width: 800px; margin: 20px auto; padding: 30px; border: 1px solid #ddd; }
        .invoice-header { border-bottom: 3px solid #43e97b; padding-bottom: 20px; margin-bottom: 20px; }
        .company-name { font-size: 24px; font-weight: 700; color: #43e97b; }
        .invoice-title { font-size: 20px; font-weight: 700; color: #333; }
        .total-row { background: #f8f9fa; font-weight: 700; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="invoice-box">
    <div class="invoice-header d-flex justify-content-between">
        <div>
            <div class="company-name"><?php echo htmlspecialchars($sale['company_name']); ?></div>
            <div class="text-muted small"><?php echo $sale['company_address'] ?? ''; ?></div>
            <div class="text-muted small"><?php echo $sale['company_phone'] ?? ''; ?> | <?php echo $sale['company_email'] ?? ''; ?></div>
        </div>
        <div class="text-end">
            <div class="invoice-title">INVOICE</div>
            <div class="text-muted"><?php echo $sale['invoice_number']; ?></div>
            <div class="text-muted small">Date: <?php echo format_date($sale['sale_date']); ?></div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <strong>Bill To:</strong>
            <div><?php echo htmlspecialchars($sale['customer_name']); ?></div>
            <?php if ($sale['customer_phone']): ?>
                <div class="text-muted small"><?php echo $sale['customer_phone']; ?></div>
            <?php endif; ?>
            <?php if ($sale['delivery_address']): ?>
                <div class="text-muted small"><?php echo nl2br(htmlspecialchars($sale['delivery_address'])); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th>Description</th>
                <th class="text-center">Quantity</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <?php echo htmlspecialchars($sale['product_name']); ?>
                    <?php if ($sale['dimensions']): ?>
                        <small class="text-muted d-block"><?php echo $sale['dimensions']; ?></small>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?php echo number_format($sale['quantity']); ?></td>
                <td class="text-end"><?php echo format_money($sale['unit_price']); ?></td>
                <td class="text-end"><?php echo format_money($sale['quantity'] * $sale['unit_price']); ?></td>
            </tr>
        </tbody>
        <tfoot>
            <?php if ($sale['discount'] > 0): ?>
            <tr>
                <td colspan="3" class="text-end">Discount</td>
                <td class="text-end text-danger">-<?php echo format_money($sale['discount']); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td colspan="3" class="text-end">TOTAL</td>
                <td class="text-end"><?php echo format_money($sale['total_amount']); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-end">Amount Paid</td>
                <td class="text-end text-success"><?php echo format_money($sale['amount_paid']); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-end fw-bold">Balance Due</td>
                <td class="text-end fw-bold <?php echo $sale['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                    <?php echo format_money($sale['balance']); ?>
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="row mt-4">
        <div class="col-6">
            <strong>Payment Method:</strong> <?php echo $sale['payment_method']; ?><br>
            <strong>Payment Status:</strong> <?php echo $sale['payment_status']; ?>
        </div>
    </div>

    <div class="text-center mt-4 text-muted small">
        <p>Thank you for your business!</p>
    </div>

    <div class="text-center mt-3 no-print">
        <button onclick="window.print()" class="btn btn-success btn-sm me-2"><i class="fas fa-print me-1"></i>Print</button>
        <button onclick="window.close()" class="btn btn-secondary btn-sm">Close</button>
    </div>
</div>
</body>
</html>