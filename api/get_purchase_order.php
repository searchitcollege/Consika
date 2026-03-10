<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';

$po_id = intval($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT po.*, s.supplier_name, s.supplier_code, s.contact_person, s.email, s.phone 
                      FROM procurement_purchase_orders po
                      JOIN procurement_suppliers s ON po.supplier_id = s.supplier_id
                      WHERE po.po_id = ?");
$stmt->bind_param("i", $po_id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();

// Include line items
$items = [];
$stmt2 = $db->prepare("SELECT * FROM procurement_po_items WHERE po_id = ?");
$stmt2->bind_param("i", $po_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
while($row = $result2->fetch_assoc()) $items[] = $row;
$po['items'] = $items;

header('Content-Type: application/json');
echo json_encode($po);