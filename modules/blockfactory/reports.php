<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db_connection.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

$session->requireLogin();

$current_user = $session->getCurrentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

// Ensure only Block Factory users can access
if ($current_user['company_type'] != 'Block Factory' && $role != 'SuperAdmin') {
    $_SESSION['error'] = 'Access denied. Block Factory department only.';
    header('Location: ../../login.php');
    exit();
}

global $db;

// Get date range from request or set defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// Get product filter
$product_filter = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

// Get all products for dropdown
$products = $db->query("SELECT product_id, product_name FROM blockfactory_products WHERE company_id = $company_id AND status = 'Active' ORDER BY product_name");

// Generate reports based on type
$report_data = [];
$chart_data = [];

switch ($report_type) {
    case 'production':
        // Production Report
        $query = "SELECT 
                  DATE_FORMAT(p.production_date, '%Y-%m-%d') as date,
                  DATE_FORMAT(p.production_date, '%Y-%m') as month,
                  COUNT(*) as batch_count,
                  pr.product_name,
                  pr.product_code,
                  SUM(p.planned_quantity) as total_planned,
                  SUM(p.produced_quantity) as total_produced,
                  SUM(p.good_quantity) as total_good,
                  SUM(p.defective_quantity) as total_defective,
                  COALESCE(AVG(p.defect_rate), 0) as avg_defect_rate
                  FROM blockfactory_production p
                  JOIN blockfactory_products pr ON p.product_id = pr.product_id
                  WHERE pr.company_id = ? AND p.production_date BETWEEN ? AND ?";
        
        if ($product_filter > 0) {
            $query .= " AND p.product_id = ?";
            $query .= " GROUP BY DATE_FORMAT(p.production_date, '%Y-%m-%d'), pr.product_id ORDER BY p.production_date DESC";
            $stmt = $db->prepare($query);
            $stmt->bind_param("issi", $company_id, $start_date, $end_date, $product_filter);
        } else {
            $query .= " GROUP BY DATE_FORMAT(p.production_date, '%Y-%m-%d'), pr.product_id ORDER BY p.production_date DESC";
            $stmt = $db->prepare($query);
            $stmt->bind_param("iss", $company_id, $start_date, $end_date);
        }
        $stmt->execute();
        $report_data = $stmt->get_result();
        
        // Get summary stats
        $stats_query = "SELECT 
                        COUNT(DISTINCT p.production_id) as total_batches,
                        COALESCE(SUM(p.produced_quantity), 0) as total_produced,
                        COALESCE(SUM(p.good_quantity), 0) as total_good,
                        COALESCE(SUM(p.defective_quantity), 0) as total_defective,
                        COALESCE(AVG(p.defect_rate), 0) as overall_defect_rate
                        FROM blockfactory_production p
                        JOIN blockfactory_products pr ON p.product_id = pr.product_id
                        WHERE pr.company_id = ? AND p.production_date BETWEEN ? AND ?";
        $stmt = $db->prepare($stats_query);
        $stmt->bind_param("iss", $company_id, $start_date, $end_date);
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc();
        
        // Get chart data (monthly production)
        $chart_query = "SELECT 
                        DATE_FORMAT(p.production_date, '%Y-%m') as month,
                        COALESCE(SUM(p.produced_quantity), 0) as total
                        FROM blockfactory_production p
                        JOIN blockfactory_products pr ON p.product_id = pr.product_id
                        WHERE pr.company_id = ? AND p.production_date BETWEEN DATE_SUB(?, INTERVAL 11 MONTH) AND ?
                        GROUP BY DATE_FORMAT(p.production_date, '%Y-%m')
                        ORDER BY month ASC";
        $stmt = $db->prepare($chart_query);
        $stmt->bind_param("iss", $company_id, $end_date, $end_date);
        $stmt->execute();
        $chart_result = $stmt->get_result();
        
        $chart_labels = [];
        $chart_values = [];
        while ($row = $chart_result->fetch_assoc()) {
            $chart_labels[] = $row['month'];
            $chart_values[] = $row['total'];
        }
        $chart_data = ['labels' => $chart_labels, 'values' => $chart_values];
        break;
        
    case 'sales':
        // Sales Report
        $query = "SELECT 
                  DATE_FORMAT(s.sale_date, '%Y-%m-%d') as date,
                  DATE_FORMAT(s.sale_date, '%Y-%m') as month,
                  s.invoice_number,
                  pr.product_name,
                  pr.product_code,
                  s.quantity,
                  s.unit_price,
                  s.discount,
                  s.subtotal,
                  s.tax_amount,
                  s.total_amount,
                  s.amount_paid,
                  s.balance,
                  s.payment_status,
                  COALESCE(c.customer_name, s.customer_name) as customer
                  FROM blockfactory_sales s
                  JOIN blockfactory_products pr ON s.product_id = pr.product_id
                  LEFT JOIN blockfactory_customers c ON s.customer_id = c.customer_id
                  WHERE pr.company_id = ? AND s.sale_date BETWEEN ? AND ?";
        
        if ($product_filter > 0) {
            $query .= " AND s.product_id = ?";
            $query .= " ORDER BY s.sale_date DESC";
            $stmt = $db->prepare($query);
            $stmt->bind_param("issi", $company_id, $start_date, $end_date, $product_filter);
        } else {
            $query .= " ORDER BY s.sale_date DESC";
            $stmt = $db->prepare($query);
            $stmt->bind_param("iss", $company_id, $start_date, $end_date);
        }
        $stmt->execute();
        $report_data = $stmt->get_result();
        
        // Get summary stats
        $stats_query = "SELECT 
                        COUNT(*) as total_sales,
                        COALESCE(SUM(s.total_amount), 0) as total_revenue,
                        COALESCE(SUM(s.amount_paid), 0) as total_collected,
                        COALESCE(SUM(s.balance), 0) as total_balance,
                        COALESCE(AVG(s.total_amount), 0) as avg_sale_value,
                        COUNT(CASE WHEN s.payment_status = 'Paid' THEN 1 END) as paid_count,
                        COUNT(CASE WHEN s.payment_status = 'Partial' THEN 1 END) as partial_count,
                        COUNT(CASE WHEN s.payment_status = 'Unpaid' THEN 1 END) as unpaid_count
                        FROM blockfactory_sales s
                        JOIN blockfactory_products pr ON s.product_id = pr.product_id
                        WHERE pr.company_id = ? AND s.sale_date BETWEEN ? AND ?";
        $stmt = $db->prepare($stats_query);
        $stmt->bind_param("iss", $company_id, $start_date, $end_date);
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc();
        
        // Get chart data (daily sales)
        $chart_query = "SELECT 
                        DATE_FORMAT(s.sale_date, '%Y-%m-%d') as date,
                        COALESCE(SUM(s.total_amount), 0) as total
                        FROM blockfactory_sales s
                        JOIN blockfactory_products pr ON s.product_id = pr.product_id
                        WHERE pr.company_id = ? AND s.sale_date BETWEEN ? AND ?
                        GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m-%d')
                        ORDER BY date ASC";
        $stmt = $db->prepare($chart_query);
        $stmt->bind_param("iss", $company_id, $start_date, $end_date);
        $stmt->execute();
        $chart_result = $stmt->get_result();
        
        $chart_labels = [];
        $chart_values = [];
        while ($row = $chart_result->fetch_assoc()) {
            $chart_labels[] = $row['date'];
            $chart_values[] = $row['total'];
        }
        $chart_data = ['labels' => $chart_labels, 'values' => $chart_values];
        break;
        
    case 'inventory':
        // Inventory Report
        $query = "SELECT 
                  pr.*,
                  COALESCE((SELECT SUM(produced_quantity) FROM blockfactory_production WHERE product_id = pr.product_id AND production_date BETWEEN ? AND ?), 0) as total_produced,
                  COALESCE((SELECT SUM(quantity) FROM blockfactory_sales WHERE product_id = pr.product_id AND sale_date BETWEEN ? AND ?), 0) as total_sold,
                  (pr.current_stock + COALESCE((SELECT SUM(produced_quantity) FROM blockfactory_production WHERE product_id = pr.product_id AND production_date < ?), 0) - COALESCE((SELECT SUM(quantity) FROM blockfactory_sales WHERE product_id = pr.product_id AND sale_date < ?), 0)) as opening_stock
                  FROM blockfactory_products pr
                  WHERE pr.company_id = ?
                  ORDER BY pr.product_name";
        $stmt = $db->prepare($query);
        $stmt->bind_param("sssssi", $start_date, $end_date, $start_date, $end_date, $start_date, $start_date, $company_id);
        $stmt->execute();
        $report_data = $stmt->get_result();
        
        // Get summary stats
        $stats_query = "SELECT 
                        COUNT(*) as total_products,
                        COALESCE(SUM(current_stock), 0) as total_units,
                        COALESCE(SUM(current_stock * price_per_unit), 0) as inventory_value,
                        COUNT(CASE WHEN current_stock <= reorder_level THEN 1 END) as low_stock_items,
                        COUNT(CASE WHEN current_stock = 0 THEN 1 END) as out_of_stock_items
                        FROM blockfactory_products
                        WHERE company_id = ?";
        $stmt = $db->prepare($stats_query);
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $summary = $stmt->get_result()->fetch_assoc();
        break;
        
    case 'quality':
        // Quality Control Report
        $query = "SELECT 
                  DATE_FORMAT(p.production_date, '%Y-%m') as month,
                  pr.product_name,
                  COUNT(*) as batch_count,
                  COALESCE(SUM(p.produced_quantity), 0) as total_produced,
                  COALESCE(SUM(p.good_quantity), 0) as total_good,
                  COALESCE(SUM(p.defective_quantity), 0) as total_defective,
                  COALESCE(AVG(p.defect_rate), 0) as avg_defect_rate
                  FROM blockfactory_production p
                  JOIN blockfactory_products pr ON p.product_id = pr.product_id
                  WHERE pr.company_id = ? AND p.production_date BETWEEN ? AND ?";
        
        if ($product_filter > 0) {
            $query .= " AND p.product_id = ?";
            $query .= " GROUP BY DATE_FORMAT(p.production_date, '%Y-%m'), pr.product_id ORDER BY month DESC";
            $stmt = $db->prepare($query);
            $stmt->bind_param("issi", $company_id, $start_date, $end_date, $product_filter);
        } else {
            $query .= " GROUP BY DATE_FORMAT(p.production_date, '%Y-%m'), pr.product_id ORDER BY month DESC";
            $stmt = $db->prepare($query);
            $stmt->bind_param("iss", $company_id, $start_date, $end_date);
        }
        $stmt->execute();
        $report_data = $stmt->get_result();
        
        // Get overall defect rate by product
        $defect_query = "SELECT 
                        pr.product_name,
                        COALESCE(AVG(p.defect_rate), 0) as avg_defect_rate,
                        SUM(p.defective_quantity) as total_defects,
                        SUM(p.produced_quantity) as total_produced
                        FROM blockfactory_production p
                        JOIN blockfactory_products pr ON p.product_id = pr.product_id
                        WHERE pr.company_id = ? AND p.production_date BETWEEN ? AND ?
                        GROUP BY pr.product_id
                        ORDER BY avg_defect_rate DESC";
        $stmt = $db->prepare($defect_query);
        $stmt->bind_param("iss", $company_id, $start_date, $end_date);
        $stmt->execute();
        $defect_by_product = $stmt->get_result();
        break;
        
    default:
        // Summary Dashboard
        // Production summary
        $prod_query = "SELECT 
                      COUNT(DISTINCT p.production_id) as total_batches,
                      COALESCE(SUM(p.produced_quantity), 0) as total_produced,
                      COALESCE(SUM(p.good_quantity), 0) as total_good,
                      COALESCE(AVG(p.defect_rate), 0) as avg_defect_rate
                      FROM blockfactory_production p
                      JOIN blockfactory_products pr ON p.product_id = pr.product_id
                      WHERE pr.company_id = ? AND p.production_date BETWEEN ? AND ?";
        $stmt = $db->prepare($prod_query);
        $stmt->bind_param("iss", $company_id, $start_date, $end_date);
        $stmt->execute();
        $prod_summary = $stmt->get_result()->fetch_assoc();
        
        // Sales summary
        $sales_query = "SELECT 
                       COUNT(*) as total_sales,
                       COALESCE(SUM(total_amount), 0) as total_revenue,
                       COALESCE(SUM(amount_paid), 0) as total_collected
                       FROM blockfactory_sales s
                       JOIN blockfactory_products pr ON s.product_id = pr.product_id
                       WHERE pr.company_id = ? AND s.sale_date BETWEEN ? AND ?";
        $stmt = $db->prepare($sales_query);
        $stmt->bind_param("iss", $company_id, $start_date, $end_date);
        $stmt->execute();
        $sales_summary = $stmt->get_result()->fetch_assoc();
        
        // Inventory summary
        $inv_query = "SELECT 
                     COUNT(*) as total_products,
                     COALESCE(SUM(current_stock), 0) as total_units,
                     COALESCE(SUM(current_stock * price_per_unit), 0) as inventory_value,
                     COUNT(CASE WHEN current_stock <= reorder_level THEN 1 END) as low_stock
                     FROM blockfactory_products
                     WHERE company_id = ?";
        $stmt = $db->prepare($inv_query);
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        $inv_summary = $stmt->get_result()->fetch_assoc();
        
        // Raw materials summary
        $raw_query = "SELECT 
                     COUNT(*) as total_materials,
                     COALESCE(SUM(stock_quantity * unit_cost), 0) as material_value,
                     COUNT(CASE WHEN stock_quantity <= minimum_stock THEN 1 END) as low_materials
                     FROM blockfactory_raw_materials";
        $raw_summary = $db->query($raw_query)->fetch_assoc();
        
        $report_data = [
            'production' => $prod_summary,
            'sales' => $sales_summary,
            'inventory' => $inv_summary,
            'materials' => $raw_summary
        ];
        
        // Get top products
        $top_products = $db->query("SELECT pr.product_name, COUNT(s.sale_id) as sale_count, COALESCE(SUM(s.total_amount), 0) as revenue
                                   FROM blockfactory_products pr
                                   LEFT JOIN blockfactory_sales s ON pr.product_id = s.product_id AND s.sale_date BETWEEN '$start_date' AND '$end_date'
                                   WHERE pr.company_id = $company_id
                                   GROUP BY pr.product_id
                                   ORDER BY revenue DESC
                                   LIMIT 5");
        break;
}

$page_title = 'Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Block Factory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #43e97b;
            --secondary-color: #38f9d7;
        }
        
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .report-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary-color);
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-box {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            background: #f8f9fa;
            height: 100%;
        }
        
        .stat-box h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stat-box .label {
            color: #6c757d;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        
        .summary-card h5 {
            color: var(--primary-color);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #1e1e2f, #2a2a40);
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            padding-left: 30px;
        }
        
        .nav-link.active {
            background: var(--primary-color);
            color: white;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-auto">
                <?php include dirname(__DIR__, 2) . '/includes/sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <div class="col">
                <div class="main-content">
                    <!-- Page Header -->
                    <div class="page-header">
                        <h4 class="mb-1">Reports & Analytics</h4>
                        <p class="text-muted mb-0">Generate and view block factory reports</p>
                    </div>
                    
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Report Type</label>
                                <select class="form-control" name="report_type" onchange="this.form.submit()">
                                    <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Summary Dashboard</option>
                                    <option value="production" <?php echo $report_type == 'production' ? 'selected' : ''; ?>>Production Report</option>
                                    <option value="sales" <?php echo $report_type == 'sales' ? 'selected' : ''; ?>>Sales Report</option>
                                    <option value="inventory" <?php echo $report_type == 'inventory' ? 'selected' : ''; ?>>Inventory Report</option>
                                    <option value="quality" <?php echo $report_type == 'quality' ? 'selected' : ''; ?>>Quality Control</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>" onchange="this.form.submit()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Product (Optional)</label>
                                <select class="form-control" name="product_id" onchange="this.form.submit()">
                                    <option value="0">All Products</option>
                                    <?php 
                                    if ($products && $products->num_rows > 0):
                                        $products->data_seek(0);
                                        while($product = $products->fetch_assoc()): 
                                    ?>
                                    <option value="<?php echo $product['product_id']; ?>" <?php echo $product_filter == $product['product_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                    </option>
                                    <?php 
                                        endwhile;
                                    endif; 
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-success w-100" onclick="exportReport()">
                                    <i class="fas fa-download me-2"></i>Export
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Report Content -->
                    <div class="report-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5>
                                <?php 
                                switch($report_type) {
                                    case 'production': echo 'Production Report'; break;
                                    case 'sales': echo 'Sales Report'; break;
                                    case 'inventory': echo 'Inventory Report'; break;
                                    case 'quality': echo 'Quality Control Report'; break;
                                    default: echo 'Summary Dashboard';
                                }
                                ?>
                            </h5>
                            <span class="badge bg-info"><?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></span>
                        </div>
                        
                        <?php if ($report_type == 'summary'): ?>
                            <!-- Summary Dashboard -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="summary-card">
                                        <h5>Production Summary</h5>
                                        <div class="row">
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo number_format($report_data['production']['total_batches'] ?? 0); ?></h3>
                                                    <div class="label">Total Batches</div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo number_format($report_data['production']['total_produced'] ?? 0); ?></h3>
                                                    <div class="label">Units Produced</div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo number_format($report_data['production']['total_good'] ?? 0); ?></h3>
                                                    <div class="label">Good Units</div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo round($report_data['production']['avg_defect_rate'] ?? 0, 2); ?>%</h3>
                                                    <div class="label">Avg Defect Rate</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="summary-card">
                                        <h5>Sales Summary</h5>
                                        <div class="row">
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo number_format($report_data['sales']['total_sales'] ?? 0); ?></h3>
                                                    <div class="label">Total Sales</div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo formatMoney($report_data['sales']['total_revenue'] ?? 0); ?></h3>
                                                    <div class="label">Total Revenue</div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo formatMoney($report_data['sales']['total_collected'] ?? 0); ?></h3>
                                                    <div class="label">Amount Collected</div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo formatMoney(($report_data['sales']['total_revenue'] ?? 0) - ($report_data['sales']['total_collected'] ?? 0)); ?></h3>
                                                    <div class="label">Outstanding</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="summary-card">
                                        <h5>Inventory Summary</h5>
                                        <div class="row">
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo number_format($report_data['inventory']['total_products'] ?? 0); ?></h3>
                                                    <div class="label">Product Types</div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo number_format($report_data['inventory']['total_units'] ?? 0); ?></h3>
                                                    <div class="label">Total Units</div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo formatMoney($report_data['inventory']['inventory_value'] ?? 0); ?></h3>
                                                    <div class="label">Inventory Value</div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo intval($report_data['inventory']['low_stock'] ?? 0); ?></h3>
                                                    <div class="label">Low Stock Items</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="summary-card">
                                        <h5>Raw Materials</h5>
                                        <div class="row">
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo intval($report_data['materials']['total_materials'] ?? 0); ?></h3>
                                                    <div class="label">Material Types</div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo formatMoney($report_data['materials']['material_value'] ?? 0); ?></h3>
                                                    <div class="label">Material Value</div>
                                                </div>
                                            </div>
                                            <div class="col-6 mb-3">
                                                <div class="stat-box">
                                                    <h3><?php echo intval($report_data['materials']['low_materials'] ?? 0); ?></h3>
                                                    <div class="label">Low Materials</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="summary-card">
                                        <h5>Top Products by Revenue</h5>
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Sales Count</th>
                                                    <th>Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($top_products && $top_products->num_rows > 0): ?>
                                                    <?php while($product = $top_products->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                        <td><?php echo $product['sale_count']; ?></td>
                                                        <td><?php echo formatMoney($product['revenue']); ?></td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center py-3">No sales data available</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                        <?php elseif ($report_type == 'production'): ?>
                            <!-- Production Report -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo number_format($summary['total_batches'] ?? 0); ?></h3>
                                        <div class="label">Total Batches</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo number_format($summary['total_produced'] ?? 0); ?></h3>
                                        <div class="label">Units Produced</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo number_format($summary['total_good'] ?? 0); ?></h3>
                                        <div class="label">Good Units</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo round($summary['overall_defect_rate'] ?? 0, 2); ?>%</h3>
                                        <div class="label">Defect Rate</div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($chart_data['labels'])): ?>
                            <div class="mb-4">
                                <canvas id="productionChart" height="300"></canvas>
                            </div>
                            <?php endif; ?>
                            
                            <div class="table-responsive">
                                <table class="table table-hover" id="reportTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Product</th>
                                            <th>Batches</th>
                                            <th>Planned</th>
                                            <th>Produced</th>
                                            <th>Good</th>
                                            <th>Defective</th>
                                            <th>Defect Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($report_data && $report_data->num_rows > 0): ?>
                                            <?php while($row = $report_data->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                                <td><?php echo $row['batch_count']; ?></td>
                                                <td><?php echo number_format($row['total_planned']); ?></td>
                                                <td><?php echo number_format($row['total_produced']); ?></td>
                                                <td><?php echo number_format($row['total_good']); ?></td>
                                                <td><?php echo number_format($row['total_defective']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['avg_defect_rate'] <= 2 ? 'success' : ($row['avg_defect_rate'] <= 5 ? 'warning' : 'danger'); ?>">
                                                        <?php echo round($row['avg_defect_rate'], 2); ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">No production data found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <script>
                                <?php if (!empty($chart_data['labels'])): ?>
                                new Chart(document.getElementById('productionChart'), {
                                    type: 'bar',
                                    data: {
                                        labels: <?php echo json_encode($chart_data['labels']); ?>,
                                        datasets: [{
                                            label: 'Monthly Production',
                                            data: <?php echo json_encode($chart_data['values']); ?>,
                                            backgroundColor: '#43e97b',
                                            borderColor: '#38f9d7',
                                            borderWidth: 1
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                ticks: {
                                                    callback: function(value) {
                                                        return value.toLocaleString();
                                                    }
                                                }
                                            }
                                        }
                                    }
                                });
                                <?php endif; ?>
                            </script>
                            
                        <?php elseif ($report_type == 'sales'): ?>
                            <!-- Sales Report -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo number_format($summary['total_sales'] ?? 0); ?></h3>
                                        <div class="label">Total Sales</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo formatMoney($summary['total_revenue'] ?? 0); ?></h3>
                                        <div class="label">Total Revenue</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo formatMoney($summary['avg_sale_value'] ?? 0); ?></h3>
                                        <div class="label">Avg Sale Value</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo $summary['paid_count'] ?? 0; ?> / <?php echo $summary['partial_count'] ?? 0; ?> / <?php echo $summary['unpaid_count'] ?? 0; ?></h3>
                                        <div class="label">Paid/Partial/Unpaid</div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($chart_data['labels'])): ?>
                            <div class="mb-4">
                                <canvas id="salesChart" height="300"></canvas>
                            </div>
                            <?php endif; ?>
                            
                            <div class="table-responsive">
                                <table class="table table-hover" id="reportTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Invoice</th>
                                            <th>Customer</th>
                                            <th>Product</th>
                                            <th>Qty</th>
                                            <th>Unit Price</th>
                                            <th>Total</th>
                                            <th>Paid</th>
                                            <th>Balance</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($report_data && $report_data->num_rows > 0): ?>
                                            <?php while($row = $report_data->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                                                <td><strong><?php echo $row['invoice_number']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['customer']); ?></td>
                                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                                <td><?php echo number_format($row['quantity']); ?></td>
                                                <td><?php echo formatMoney($row['unit_price']); ?></td>
                                                <td><?php echo formatMoney($row['total_amount']); ?></td>
                                                <td><?php echo formatMoney($row['amount_paid']); ?></td>
                                                <td><?php echo formatMoney($row['balance']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['payment_status'] == 'Paid' ? 'success' : ($row['payment_status'] == 'Partial' ? 'warning' : 'danger'); ?>">
                                                        <?php echo $row['payment_status']; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="10" class="text-center py-4">No sales data found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <script>
                                <?php if (!empty($chart_data['labels'])): ?>
                                new Chart(document.getElementById('salesChart'), {
                                    type: 'line',
                                    data: {
                                        labels: <?php echo json_encode($chart_data['labels']); ?>,
                                        datasets: [{
                                            label: 'Daily Sales',
                                            data: <?php echo json_encode($chart_data['values']); ?>,
                                            borderColor: '#43e97b',
                                            backgroundColor: 'rgba(67, 233, 123, 0.1)',
                                            tension: 0.4,
                                            fill: true
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                ticks: {
                                                    callback: function(value) {
                                                        return 'GHS ' + value.toLocaleString();
                                                    }
                                                }
                                            }
                                        }
                                    }
                                });
                                <?php endif; ?>
                            </script>
                            
                        <?php elseif ($report_type == 'inventory'): ?>
                            <!-- Inventory Report -->
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo number_format($summary['total_products'] ?? 0); ?></h3>
                                        <div class="label">Products</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo number_format($summary['total_units'] ?? 0); ?></h3>
                                        <div class="label">Total Units</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo formatMoney($summary['inventory_value'] ?? 0); ?></h3>
                                        <div class="label">Inventory Value</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="stat-box">
                                        <h3><?php echo intval($summary['low_stock_items'] ?? 0); ?> / <?php echo intval($summary['out_of_stock_items'] ?? 0); ?></h3>
                                        <div class="label">Low/Out of Stock</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover" id="reportTable">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Code</th>
                                            <th>Opening Stock</th>
                                            <th>Produced</th>
                                            <th>Sold</th>
                                            <th>Current Stock</th>
                                            <th>Price</th>
                                            <th>Value</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($report_data && $report_data->num_rows > 0): ?>
                                            <?php while($row = $report_data->fetch_assoc()): 
                                                $stock_status = 'success';
                                                if ($row['current_stock'] <= $row['reorder_level']) {
                                                    $stock_status = 'warning';
                                                }
                                                if ($row['current_stock'] == 0) {
                                                    $stock_status = 'danger';
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                                <td><?php echo $row['product_code']; ?></td>
                                                <td><?php echo number_format($row['opening_stock']); ?></td>
                                                <td><?php echo number_format($row['total_produced']); ?></td>
                                                <td><?php echo number_format($row['total_sold']); ?></td>
                                                <td><?php echo number_format($row['current_stock']); ?></td>
                                                <td><?php echo formatMoney($row['price_per_unit']); ?></td>
                                                <td><?php echo formatMoney($row['current_stock'] * $row['price_per_unit']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $stock_status; ?>">
                                                        <?php 
                                                        if ($row['current_stock'] == 0) echo 'Out of Stock';
                                                        elseif ($row['current_stock'] <= $row['reorder_level']) echo 'Low Stock';
                                                        else echo 'In Stock';
                                                        ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">No inventory data found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                        <?php elseif ($report_type == 'quality'): ?>
                            <!-- Quality Control Report -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5>Defect Rate by Product</h5>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Total Produced</th>
                                                <th>Total Defects</th>
                                                <th>Defect Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($defect_by_product && $defect_by_product->num_rows > 0): ?>
                                                <?php while($defect = $defect_by_product->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($defect['product_name']); ?></td>
                                                    <td><?php echo number_format($defect['total_produced']); ?></td>
                                                    <td><?php echo number_format($defect['total_defects']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $defect['avg_defect_rate'] <= 2 ? 'success' : ($defect['avg_defect_rate'] <= 5 ? 'warning' : 'danger'); ?>">
                                                            <?php echo round($defect['avg_defect_rate'], 2); ?>%
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-3">No quality data available</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover" id="reportTable">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Product</th>
                                            <th>Batches</th>
                                            <th>Produced</th>
                                            <th>Good</th>
                                            <th>Defective</th>
                                            <th>Defect Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($report_data && $report_data->num_rows > 0): ?>
                                            <?php while($row = $report_data->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                                <td><?php echo $row['batch_count']; ?></td>
                                                <td><?php echo number_format($row['total_produced']); ?></td>
                                                <td><?php echo number_format($row['total_good']); ?></td>
                                                <td><?php echo number_format($row['total_defective']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['avg_defect_rate'] <= 2 ? 'success' : ($row['avg_defect_rate'] <= 5 ? 'warning' : 'danger'); ?>">
                                                        <?php echo round($row['avg_defect_rate'], 2); ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">No quality data found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#reportTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25
            });
        });
        
        function exportReport() {
            var report_type = '<?php echo $report_type; ?>';
            var start_date = '<?php echo $start_date; ?>';
            var end_date = '<?php echo $end_date; ?>';
            var product_id = '<?php echo $product_filter; ?>';
            
            window.location.href = 'export.php?type=' + report_type + 
                                   '&start=' + start_date + 
                                   '&end=' + end_date + 
                                   '&product=' + product_id;
        }
    </script>
</body>
</html>