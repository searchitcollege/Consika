<?php
require_once '../includes/session.php';
header('Content-Type: application/json');

$session->requireLogin();

$action = $_GET['action'] ?? '';
$response = ['success' => false, 'data' => null, 'message' => ''];

switch ($action) {
    case 'get_properties':
        $company_id = $session->getCompanyId();
        if ($session->getRole() == 'SuperAdmin') {
            $sql = "SELECT property_id as id, property_name as text FROM estate_properties WHERE status = 'Available'";
            $result = $db->query($sql);
        } else {
            $sql = "SELECT property_id as id, property_name as text FROM estate_properties WHERE company_id = ? AND status = 'Available'";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            $result = $stmt->get_result();
        }
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $response = ['success' => true, 'data' => $data];
        break;
        
    case 'get_tenants':
        $property_id = $_GET['property_id'] ?? 0;
        $sql = "SELECT tenant_id as id, CONCAT(full_name, ' - ', unit_number) as text FROM estate_tenants WHERE property_id = ? AND status = 'Active'";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $response = ['success' => true, 'data' => $data];
        break;
        
    case 'get_products':
        $sql = "SELECT product_id as id, CONCAT(product_name, ' (', current_stock, ' ', unit, ')') as text, current_stock, unit_price FROM procurement_products WHERE status = 'Active'";
        $result = $db->query($sql);
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $response = ['success' => true, 'data' => $data];
        break;
        
    case 'get_suppliers':
        $sql = "SELECT supplier_id as id, supplier_name as text FROM procurement_suppliers WHERE status = 'Active'";
        $result = $db->query($sql);
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $response = ['success' => true, 'data' => $data];
        break;
        
    case 'get_projects':
        $sql = "SELECT project_id as id, project_name as text FROM works_projects WHERE status = 'In Progress'";
        $result = $db->query($sql);
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $response = ['success' => true, 'data' => $data];
        break;
        
    case 'get_employees':
        $sql = "SELECT employee_id as id, CONCAT(full_name, ' - ', position) as text FROM works_employees WHERE status = 'Active'";
        $result = $db->query($sql);
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $response = ['success' => true, 'data' => $data];
        break;
        
    case 'get_product_stock':
        $product_id = $_GET['product_id'] ?? 0;
        $sql = "SELECT current_stock, unit, price_per_unit FROM blockfactory_products WHERE product_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $response = ['success' => true, 'data' => $row];
        } else {
            $response = ['success' => false, 'message' => 'Product not found'];
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Invalid action'];
}

echo json_encode($response);
?>