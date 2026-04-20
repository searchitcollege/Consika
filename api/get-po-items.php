<?php
require_once '../includes/session.php';
$session->requireLogin();

global $db;
$po_id = (int)($_GET['po_id'] ?? 0);

if (!$po_id) {
    echo '<p class="text-muted">Invalid PO selected.</p>';
    exit();
}

$stmt = $db->prepare("
    SELECT pi.po_item_id, pi.quantity, pi.received_quantity, pi.unit_price,
           pp.product_name, pp.unit
    FROM procurement_po_items pi
    JOIN procurement_products pp ON pp.product_id = pi.product_id
    WHERE pi.po_id = ?
    AND pi.status != 'Cancelled'
");
$stmt->bind_param("i", $po_id);
$stmt->execute();
$items = $stmt->get_result();

if ($items->num_rows === 0) {
    echo '<p class="text-muted">No items found for this PO.</p>';
    exit();
}
?>
<table class="table table-sm mb-3">
    <thead>
        <tr>
            <th>Product</th>
            <th>Ordered</th>
            <th>Received</th>
            <th>Receive Now</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($item = $items->fetch_assoc()): 
            $remaining = $item['quantity'] - $item['received_quantity'];
        ?>
        <tr>
            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
            <td><?php echo $item['quantity']; ?> <?php echo $item['unit']; ?></td>
            <td><?php echo $item['received_quantity']; ?></td>
            <td>
                <input type="number" class="form-control form-control-sm"
                    name="receive[<?php echo $item['po_item_id']; ?>]"
                    min="0" max="<?php echo $remaining; ?>"
                    value="<?php echo $remaining; ?>"
                    placeholder="0">
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>