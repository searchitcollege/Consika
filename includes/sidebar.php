<?php
// Get current user and role from session
$current_user = $session->getCurrentUser();
$role = $session->getRole();
$company_id = $session->getCompanyId();
$company_type = $current_user['company_type'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h3 style="color: #fff;"><?php echo defined('APP_NAME') ? APP_NAME : 'Company Management'; ?></h3>
        <p>
            <?php 
            if ($role == 'SuperAdmin') {
                echo 'Administrator';
            } else {
                echo $company_type ?: 'Department';
            }
            ?>
        </p>
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
        <?php if ($role == 'SuperAdmin'): ?>
            <!-- Admin Dashboard -->
            <li class="nav-item">
                <a href="<?php echo baseUrl('admin/dashboard.php'); ?>" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Admin Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo baseUrl('admin/companies.php'); ?>" class="nav-link <?php echo $current_page == 'companies.php' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>Companies</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo baseUrl('admin/users.php'); ?>" class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li class="nav-divider"></li>
            
            <!-- Department Links for Admin -->
            <li class="nav-item">
                <a href="<?php echo baseUrl('modules/estate/index.php'); ?>" class="nav-link">
                    <i class="fas fa-building"></i>
                    <span>Accounts</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo baseUrl('modules/procurement/index.php'); ?>" class="nav-link">
                    <i class="fas fa-truck"></i>
                    <span>Procurement</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo baseUrl('modules/works/index.php'); ?>" class="nav-link">
                    <i class="fas fa-tools"></i>
                    <span>Works</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?php echo baseUrl('modules/blockfactory/index.php'); ?>" class="nav-link">
                    <i class="fas fa-cubes"></i>
                    <span>Block Factory</span>
                </a>
            </li>
        
        <?php else: ?>
            <!-- Department-Specific Links -->
            <?php if ($company_type == 'Estate'): ?>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/estate/dashboard.php'); ?>" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/estate/properties.php'); ?>" class="nav-link">
                        <i class="fas fa-building"></i>
                        <span>Properties</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/estate/tenants.php'); ?>" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Tenants</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/estate/payments.php'); ?>" class="nav-link">
                        <i class="fas fa-money-bill"></i>
                        <span>Payments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/estate/maintenance.php'); ?>" class="nav-link">
                        <i class="fas fa-tools"></i>
                        <span>Maintenance</span>
                    </a>
                </li>
            
            <?php elseif ($company_type == 'Procurement'): ?>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/procurement/dashboard.php'); ?>" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/procurement/suppliers.php'); ?>" class="nav-link">
                        <i class="fas fa-truck"></i>
                        <span>Suppliers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/procurement/products.php'); ?>" class="nav-link">
                        <i class="fas fa-box"></i>
                        <span>Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/procurement/purchase.php'); ?>" class="nav-link">
                        <i class="fas fa-file-invoice"></i>
                        <span>Purchase Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/procurement/approvals.php'); ?>" class="nav-link">
                        <i class="fas fa-warehouse"></i>
                        <span>Approvals</span>
                    </a>
                </li>
            
            <?php elseif ($company_type == 'Works'): ?>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/works/dashboard.php'); ?>" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/works/projects.php'); ?>" class="nav-link">
                        <i class="fas fa-hard-hat"></i>
                        <span>Projects</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/works/employees.php'); ?>" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Employees</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/works/materials.php'); ?>" class="nav-link">
                        <i class="fas fa-box"></i>
                        <span>Materials</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/works/daily-reports.php'); ?>" class="nav-link">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Daily Reports</span>
                    </a>
                </li>
            
            <?php elseif ($company_type == 'Block Factory'): ?>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/blockfactory/dashboard.php'); ?>" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/blockfactory/production.php'); ?>" class="nav-link">
                        <i class="fas fa-industry"></i>
                        <span>Production</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/blockfactory/products.php'); ?>" class="nav-link">
                        <i class="fas fa-cubes"></i>
                        <span>Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/blockfactory/sales.php'); ?>" class="nav-link">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Sales</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/blockfactory/raw-materials.php'); ?>" class="nav-link">
                        <i class="fas fa-boxes"></i>
                        <span>Raw Materials</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo baseUrl('modules/blockfactory/deliveries.php'); ?>" class="nav-link">
                        <i class="fas fa-truck"></i>
                        <span>Deliveries</span>
                    </a>
                </li>
            <?php endif; ?>
            
            <!-- Common Links for All Departments -->
            <li class="nav-divider"></li>
            <li class="nav-item">
                <a href="<?php echo baseUrl('reports/department-reports.php'); ?>" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
        <?php endif; ?>
        
        <li class="nav-divider"></li>
        <li class="nav-item">
            <a href="<?php echo baseUrl('api/logout.php'); ?>" class="nav-link text-danger">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<style>
.sidebar {
    width: 280px;
    background: linear-gradient(135deg, #1e1e2f, #2a2a40);
    color: white;
    height: 100vh;
    position: fixed;
    overflow-y: auto;
    transition: all 0.3s;
    z-index: 1000;
}

.sidebar .sidebar-header {
    padding: 25px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar .sidebar-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.sidebar .sidebar-header p {
    margin: 5px 0 0;
    font-size: 13px;
    opacity: 0.7;
}

.sidebar .user-info {
    padding: 20px;
    background: rgba(255,255,255,0.05);
    margin: 10px;
    border-radius: 10px;
}

.sidebar .user-info .avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 10px;
}

.sidebar .nav-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar .nav-item {
    margin: 5px 10px;
}

.sidebar .nav-link {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    color: rgba(255,255,255,0.7);
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s;
    gap: 12px;
}

.sidebar .nav-link i {
    width: 20px;
    font-size: 16px;
}

.sidebar .nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    padding-left: 20px;
}

.sidebar .nav-link.active {
    background: #4361ee;
    color: white;
}

.sidebar .nav-link.text-danger:hover {
    background: #dc3545;
    color: white !important;
}

.sidebar .nav-divider {
    height: 1px;
    background: rgba(255,255,255,0.1);
    margin: 15px 10px;
}

.badge {
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.badge.bg-danger { background: #dc3545; }
.badge.bg-warning { background: #ffc107; color: #000; }
.badge.bg-info { background: #17a2b8; }
.badge.bg-secondary { background: #6c757d; }

@media (max-width: 768px) {
    .sidebar {
        left: -280px;
    }
    .sidebar.active {
        left: 0;
    }
}
</style>