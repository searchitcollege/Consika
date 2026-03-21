<?php
// Use absolute paths to avoid path issues
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db_connection.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/session.php';

$session->requireLogin();

$current_user = $session->getCurrentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

// Get statistics based on role
global $db;

$stats = [];

if ($role == 'SuperAdmin') {
    // Super admin sees all companies stats
    $result = $db->query("SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
    
    $result = $db->query("SELECT COUNT(*) as count FROM companies WHERE status = 'Active'");
    $stats['total_companies'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
    
    // Check if estate tables exist before querying
    $check_estate = $db->query("SHOW TABLES LIKE 'estate_properties'");
    if ($check_estate && $check_estate->num_rows > 0) {
        $result = $db->query("SELECT COUNT(*) as count FROM estate_properties");
        $stats['total_properties'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
        
        $result = $db->query("SELECT COUNT(*) as count FROM estate_tenants WHERE status = 'Active'");
        $stats['total_tenants'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
    } else {
        $stats['total_properties'] = 0;
        $stats['total_tenants'] = 0;
    }
    
    // Check if procurement tables exist
    $check_procurement = $db->query("SHOW TABLES LIKE 'procurement_suppliers'");
    if ($check_procurement && $check_procurement->num_rows > 0) {
        $result = $db->query("SELECT COUNT(*) as count FROM procurement_suppliers WHERE status = 'Active'");
        $stats['total_suppliers'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
        
        $result = $db->query("SELECT COUNT(*) as count FROM procurement_purchase_orders WHERE delivery_status = 'Pending'");
        $stats['pending_orders'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
    } else {
        $stats['total_suppliers'] = 0;
        $stats['pending_orders'] = 0;
    }
    
    // Check if works tables exist
    $check_works = $db->query("SHOW TABLES LIKE 'works_projects'");
    if ($check_works && $check_works->num_rows > 0) {
        $result = $db->query("SELECT COUNT(*) as count FROM works_projects WHERE status = 'In Progress'");
        $stats['active_projects'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
    } else {
        $stats['active_projects'] = 0;
    }
    
    // Check if blockfactory tables exist
    $check_block = $db->query("SHOW TABLES LIKE 'blockfactory_products'");
    if ($check_block && $check_block->num_rows > 0) {
        $stock_query = "SELECT COALESCE(SUM(current_stock), 0) as total FROM blockfactory_products";
        $stock_result = $db->query($stock_query);
        $stats['total_blocks'] = $stock_result ? ($stock_result->fetch_assoc()['total'] ?? 0) : 0;
    } else {
        $stats['total_blocks'] = 0;
    }
    
} else {
    // Company admin sees only their company stats
    if (!empty($current_user['company_type'])) {
        switch ($current_user['company_type']) {
            case 'Estate':
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM estate_properties WHERE company_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $company_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stats['total_properties'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
                    $stmt->close();
                } else {
                    $stats['total_properties'] = 0;
                }
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM estate_tenants WHERE status = 'Active' AND property_id IN (SELECT property_id FROM estate_properties WHERE company_id = ?)");
                if ($stmt) {
                    $stmt->bind_param("i", $company_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stats['total_tenants'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
                    $stmt->close();
                } else {
                    $stats['total_tenants'] = 0;
                }
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM estate_maintenance WHERE status = 'Pending' AND property_id IN (SELECT property_id FROM estate_properties WHERE company_id = ?)");
                if ($stmt) {
                    $stmt->bind_param("i", $company_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stats['pending_maintenance'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
                    $stmt->close();
                } else {
                    $stats['pending_maintenance'] = 0;
                }
                
                // Get monthly rent
                $rent_query = "SELECT COALESCE(SUM(amount), 0) as total FROM estate_payments 
                              WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
                              AND YEAR(payment_date) = YEAR(CURRENT_DATE())
                              AND property_id IN (SELECT property_id FROM estate_properties WHERE company_id = ?)";
                $stmt = $db->prepare($rent_query);
                if ($stmt) {
                    $stmt->bind_param("i", $company_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stats['monthly_rent'] = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;
                    $stmt->close();
                } else {
                    $stats['monthly_rent'] = 0;
                }
                break;
                
            case 'Procurement':
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM procurement_suppliers WHERE company_id = ? AND status = 'Active'");
                if ($stmt) {
                    $stmt->bind_param("i", $company_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stats['total_suppliers'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
                    $stmt->close();
                } else {
                    $stats['total_suppliers'] = 0;
                }
                
                $result = $db->query("SELECT COUNT(*) as count FROM procurement_products");
                $stats['total_products'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM procurement_purchase_orders WHERE delivery_status = 'Pending' AND supplier_id IN (SELECT supplier_id FROM procurement_suppliers WHERE company_id = ?)");
                if ($stmt) {
                    $stmt->bind_param("i", $company_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stats['pending_orders'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
                    $stmt->close();
                } else {
                    $stats['pending_orders'] = 0;
                }
                
                $result = $db->query("SELECT COUNT(*) as count FROM procurement_products WHERE current_stock <= minimum_stock");
                $stats['low_stock'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
                break;
                
            case 'Works':
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM works_projects WHERE company_id = ? AND status = 'In Progress'");
                if ($stmt) {
                    $stmt->bind_param("i", $company_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stats['active_projects'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
                    $stmt->close();
                } else {
                    $stats['active_projects'] = 0;
                }
                
                $result = $db->query("SELECT COUNT(*) as count FROM works_employees WHERE status = 'Active'");
                $stats['total_employees'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
                
                $result = $db->query("SELECT COUNT(*) as count FROM works_materials");
                $stats['total_materials'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
                
                // Get total project budget
                $budget_query = "SELECT COALESCE(SUM(budget), 0) as total FROM works_projects WHERE company_id = ? AND status != 'Completed'";
                $stmt = $db->prepare($budget_query);
                if ($stmt) {
                    $stmt->bind_param("i", $company_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stats['total_budget'] = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;
                    $stmt->close();
                } else {
                    $stats['total_budget'] = 0;
                }
                break;
                
            case 'Block Factory':
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM blockfactory_products WHERE company_id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $company_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stats['total_products'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
                    $stmt->close();
                } else {
                    $stats['total_products'] = 0;
                }
                
                $stock_query = "SELECT COALESCE(SUM(current_stock), 0) as total FROM blockfactory_products WHERE company_id = ?";
                $stmt = $db->prepare($stock_query);
                if ($stmt) {
                    $stmt->bind_param("i", $company_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stats['total_blocks'] = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;
                    $stmt->close();
                } else {
                    $stats['total_blocks'] = 0;
                }
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM blockfactory_production WHERE production_date = CURRENT_DATE()");
                if ($stmt) {
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stats['today_production'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
                    $stmt->close();
                } else {
                    $stats['today_production'] = 0;
                }
                
                // Get today's sales
                $sales_query = "SELECT COALESCE(SUM(total_amount), 0) as total FROM blockfactory_sales WHERE sale_date = CURRENT_DATE()";
                $result = $db->query($sales_query);
                $stats['today_sales'] = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;
                break;
        }
    }
}

// Get recent activities
$recent_activities = [];
$activity_query = "SELECT a.*, u.full_name, u.profile_picture 
                  FROM activity_log a 
                  JOIN users u ON a.user_id = u.user_id 
                  ORDER BY a.created_at DESC LIMIT 10";
$result = $db->query($activity_query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo defined('APP_NAME') ? APP_NAME : 'Company Management'; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --dark-color: #1e1e2f;
            --light-color: #f8f9fa;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f4f7fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .wrapper {
            display: flex;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--dark-color) 0%, #2a2a40 100%);
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            transition: all 0.3s;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .sidebar .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar .sidebar-header h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            background: linear-gradient(135deg, #fff 0%, #e0e0e0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .sidebar .sidebar-header p {
            margin: 5px 0 0;
            font-size: 13px;
            opacity: 0.7;
        }
        
        .sidebar .user-info {
            padding: 20px;
            background: rgba(255,255,255,0.05);
            margin-bottom: 10px;
        }
        
        .sidebar .user-info .avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .sidebar .user-details {
            line-height: 1.4;
        }
        
        .sidebar .user-details strong {
            display: block;
            margin-bottom: 5px;
        }
        
        .sidebar .user-details small {
            color: rgba(255,255,255,0.7);
            font-size: 12px;
        }
        
        .sidebar .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar .nav-item {
            margin: 5px 15px;
        }
        
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            gap: 12px;
        }
        
        .sidebar .nav-link i {
            width: 22px;
            font-size: 18px;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            padding-left: 20px;
        }
        
        .sidebar .nav-link.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .sidebar .nav-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 15px 20px;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        /* Top Navigation */
        .top-nav {
            background: white;
            padding: 15px 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .page-title p {
            margin: 5px 0 0;
            color: #666;
            font-size: 14px;
        }
        
        .top-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .notification-badge {
            position: relative;
            cursor: pointer;
        }
        
        .notification-badge i {
            font-size: 22px;
            color: #666;
        }
        
        .badge-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 50%;
        }
        
        .user-dropdown {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 10px;
            transition: background 0.3s;
        }
        
        .user-dropdown:hover {
            background: #f0f0f0;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }
        
        .user-info-text {
            line-height: 1.3;
        }
        
        .user-info-text .name {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .user-info-text .role {
            font-size: 12px;
            color: #666;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border-radius: 10px;
            padding: 10px 0;
        }
        
        .dropdown-item {
            padding: 8px 20px;
            color: #333;
            transition: all 0.3s;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
        }
        
        .dropdown-item i {
            width: 20px;
        }

        .chart-container {
            position: relative;
            height: 300px; /* set your desired height */
            width: 100%;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-icon.primary { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
        .stat-icon.success { background: linear-gradient(135deg, #4cc9f0, #4895ef); }
        .stat-icon.warning { background: linear-gradient(135deg, #f8961e, #f3722c); }
        .stat-icon.danger { background: linear-gradient(135deg, #f72585, #b5179e); }
        
        .stat-details {
            flex: 1;
        }
        
        .stat-details h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .stat-details p {
            margin: 5px 0 0;
            color: #666;
            font-size: 14px;
        }
        
        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .chart-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-card .card-header h4 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        /* Activity List */
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-weight: 600;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-details .activity-title {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 3px;
        }
        
        .activity-details .activity-time {
            font-size: 12px;
            color: #999;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 25px;
        }
        
        .quick-action-btn {
            background: white;
            border: none;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            background: var(--primary-color);
            color: white;
        }
        
        .quick-action-btn i {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
        
        .quick-action-btn:hover i {
            color: white;
        }
        
        .quick-action-btn span {
            display: block;
            font-size: 14px;
            font-weight: 500;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge.bg-danger { background: var(--danger-color); color: white; }
        .badge.bg-warning { background: var(--warning-color); color: white; }
        .badge.bg-info { background: var(--success-color); color: white; }
        .badge.bg-secondary { background: #6c757d; color: white; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }
            .sidebar.active {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .charts-row {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3><?php echo defined('APP_NAME') ? APP_NAME : 'Company Management'; ?></h3>
                <p>v<?php echo defined('APP_VERSION') ? APP_VERSION : '1.0'; ?></p>
            </div>
            
            <div class="user-info">
                <div class="avatar">
                    <?php echo getAvatarLetter($current_user['full_name'] ?? 'User'); ?>
                </div>
                <div class="user-details">
                    <strong><?php echo htmlspecialchars($current_user['full_name'] ?? 'User'); ?></strong>
                    <div class="user-role">
                        <span class="badge bg-<?php 
                            echo $role == 'SuperAdmin' ? 'danger' : 
                                ($role == 'CompanyAdmin' ? 'warning' : 
                                ($role == 'Manager' ? 'info' : 'secondary')); 
                        ?>">
                            <?php echo $role ?? 'Staff'; ?>
                        </span>
                    </div>
                    <?php if (!empty($current_user['company_name'])): ?>
                        <small><?php echo htmlspecialchars($current_user['company_name']); ?></small>
                    <?php endif; ?>
                </div>
            </div>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <?php if ($role == 'SuperAdmin'): ?>
                <li class="nav-item">
                    <a href="companies.php" class="nav-link">
                        <i class="fas fa-building"></i>
                        <span>Companies</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li class="nav-divider"></li>
                <?php endif; ?>
                
                <!-- Company Modules -->
                <?php if ($role == 'SuperAdmin' || (!empty($current_user['company_type']) && $current_user['company_type'] == 'Estate')): ?>
                <li class="nav-item">
                    <a href="../modules/estate/" class="nav-link">
                        <i class="fas fa-building"></i>
                        <span>Estate Management</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if ($role == 'SuperAdmin' || (!empty($current_user['company_type']) && $current_user['company_type'] == 'Procurement')): ?>
                <li class="nav-item">
                    <a href="../modules/procurement/" class="nav-link">
                        <i class="fas fa-truck"></i>
                        <span>Procurement</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if ($role == 'SuperAdmin' || (!empty($current_user['company_type']) && $current_user['company_type'] == 'Works')): ?>
                <li class="nav-item">
                    <a href="../modules/works/" class="nav-link">
                        <i class="fas fa-tools"></i>
                        <span>Works & Construction</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if ($role == 'SuperAdmin' || (!empty($current_user['company_type']) && $current_user['company_type'] == 'Block Factory')): ?>
                <li class="nav-item">
                    <a href="../modules/blockfactory/" class="nav-link">
                        <i class="fas fa-cubes"></i>
                        <span>Block Factory</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-divider"></li>
                
                <li class="nav-item">
                    <a href="../reports/" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="../api/logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="page-title">
                    <h2>Dashboard</h2>
                    <p>Welcome back, <?php echo htmlspecialchars($current_user['full_name'] ?? 'User'); ?>!</p>
                </div>
                
                <div class="top-actions">
                    <div class="notification-badge" onclick="window.location.href='notifications.php'">
                        <i class="far fa-bell"></i>
                        <span class="badge-count">0</span>
                    </div>
                    
                    <div class="user-dropdown" data-bs-toggle="dropdown">
                        <div class="user-avatar">
                            <?php echo getAvatarLetter($current_user['full_name'] ?? 'User'); ?>
                        </div>
                        <div class="user-info-text">
                            <div class="name"><?php echo htmlspecialchars($current_user['full_name'] ?? 'User'); ?></div>
                            <div class="role"><?php echo $current_user['company_name'] ?? 'System Admin'; ?></div>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../api/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <?php if ($role == 'SuperAdmin'): ?>
                    <!-- Super Admin Stats -->
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_companies'] ?? 0; ?></h3>
                            <p>Active Companies</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_users'] ?? 0; ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_properties'] ?? 0; ?></h3>
                            <p>Properties</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-cubes"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['total_blocks'] ?? 0); ?></h3>
                            <p>Blocks in Stock</p>
                        </div>
                    </div>
                    
                <?php elseif (!empty($current_user['company_type']) && $current_user['company_type'] == 'Estate'): ?>
                    <!-- Estate Stats -->
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_properties'] ?? 0; ?></h3>
                            <p>Total Properties</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_tenants'] ?? 0; ?></h3>
                            <p>Active Tenants</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['pending_maintenance'] ?? 0; ?></h3>
                            <p>Pending Maintenance</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-money-bill"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo formatMoney($stats['monthly_rent'] ?? 0); ?></h3>
                            <p>Monthly Rent</p>
                        </div>
                    </div>
                    
                <?php elseif (!empty($current_user['company_type']) && $current_user['company_type'] == 'Procurement'): ?>
                    <!-- Procurement Stats -->
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_suppliers'] ?? 0; ?></h3>
                            <p>Active Suppliers</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_products'] ?? 0; ?></h3>
                            <p>Products</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['pending_orders'] ?? 0; ?></h3>
                            <p>Pending Orders</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['low_stock'] ?? 0; ?></h3>
                            <p>Low Stock Items</p>
                        </div>
                    </div>
                    
                <?php elseif (!empty($current_user['company_type']) && $current_user['company_type'] == 'Works'): ?>
                    <!-- Works Stats -->
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-hard-hat"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['active_projects'] ?? 0; ?></h3>
                            <p>Active Projects</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_employees'] ?? 0; ?></h3>
                            <p>Employees</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_materials'] ?? 0; ?></h3>
                            <p>Materials</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo formatMoney($stats['total_budget'] ?? 0); ?></h3>
                            <p>Total Budget</p>
                        </div>
                    </div>
                    
                <?php elseif (!empty($current_user['company_type']) && $current_user['company_type'] == 'Block Factory'): ?>
                    <!-- Block Factory Stats -->
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-cubes"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo number_format($stats['total_blocks'] ?? 0); ?></h3>
                            <p>Blocks in Stock</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['total_products'] ?? 0; ?></h3>
                            <p>Product Types</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-industry"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo $stats['today_production'] ?? 0; ?></h3>
                            <p>Today's Batches</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-details">
                            <h3><?php echo formatMoney($stats['today_sales'] ?? 0); ?></h3>
                            <p>Today's Sales</p>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Default/No Company Stats -->
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="stat-details">
                            <h3>0</h3>
                            <p>No Data Available</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Charts Row -->
            <div class="charts-row">
                <div class="chart-card">
                    <div class="card-header">
                        <h4>Monthly Performance</h4>
                        <select class="form-select form-select-sm w-auto" id="yearSelect">
                            <option value="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></option>
                            <option value="<?php echo date('Y')-1; ?>"><?php echo date('Y')-1; ?></option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="monthlyChart" height="300"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="card-header">
                        <h4>Recent Activities</h4>
                        <a href="../reports/activity-log.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div style="max-height: 350px; overflow-y: auto;">
                        <ul class="activity-list">
                            <?php if (!empty($recent_activities)): ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                <li class="activity-item">
                                    <div class="activity-avatar">
                                        <?php echo getAvatarLetter($activity['full_name'] ?? 'System'); ?>
                                    </div>
                                    <div class="activity-details">
                                        <div class="activity-title">
                                            <?php echo htmlspecialchars($activity['action'] ?? 'Activity'); ?>
                                        </div>
                                        <div class="activity-time">
                                            <?php echo timeAgo($activity['created_at'] ?? ''); ?>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="activity-item">
                                    <div class="activity-avatar">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div class="activity-details">
                                        <div class="activity-title">No recent activities</div>
                                        <div class="activity-time">-</div>
                                    </div>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <?php if ($role == 'SuperAdmin' || (!empty($current_user['company_type']) && $current_user['company_type'] == 'Estate')): ?>
                <button class="quick-action-btn" onclick="window.location.href='../modules/estate/add-property.php'">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Property</span>
                </button>
                <?php endif; ?>
                
                <?php if ($role == 'SuperAdmin' || (!empty($current_user['company_type']) && $current_user['company_type'] == 'Procurement')): ?>
                <button class="quick-action-btn" onclick="window.location.href='../modules/procurement/create-po.php'">
                    <i class="fas fa-file-invoice"></i>
                    <span>Create Purchase Order</span>
                </button>
                <?php endif; ?>
                
                <?php if ($role == 'SuperAdmin' || (!empty($current_user['company_type']) && $current_user['company_type'] == 'Works')): ?>
                <button class="quick-action-btn" onclick="window.location.href='../modules/works/new-project.php'">
                    <i class="fas fa-project-diagram"></i>
                    <span>New Project</span>
                </button>
                <?php endif; ?>
                
                <?php if ($role == 'SuperAdmin' || (!empty($current_user['company_type']) && $current_user['company_type'] == 'Block Factory')): ?>
                <button class="quick-action-btn" onclick="window.location.href='../modules/blockfactory/new-production.php'">
                    <i class="fas fa-industry"></i>
                    <span>Record Production</span>
                </button>
                <?php endif; ?>
                
                <button class="quick-action-btn" onclick="window.location.href='../reports/generate.php'">
                    <i class="fas fa-file-pdf"></i>
                    <span>Generate Report</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Check if chart element exists
            const chartElement = document.getElementById('monthlyChart');
            if (chartElement) {
                try {
                    const ctx = chartElement.getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                            datasets: [{
                                label: 'Revenue',
                                data: [12000, 19000, 15000, 25000, 22000, 30000, 28000, 32000, 29000, 35000, 40000, 45000],
                                borderColor: '#4361ee',
                                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
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
                } catch (e) {
                    console.log('Chart error:', e);
                }
            }
            
            // Refresh session periodically
            setInterval(function() {
                $.get('../api/refresh-session.php')
                    .done(function() {
                        console.log('Session refreshed');
                    })
                    .fail(function() {
                        console.log('Session refresh failed - user may be logged out');
                    });
            }, 300000); // Refresh every 5 minutes
        });
        
        // Sidebar toggle for mobile
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>