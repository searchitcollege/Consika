<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db_connection.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

$session->requireLogin();

$current_user = $session->getCurrentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

// Ensure only Works users can access
if ($current_user['company_type'] != 'Works' && $role != 'SuperAdmin') {
    $_SESSION['error'] = 'Access denied. Works department only.';
    header('Location: ../../login.php');
    exit();
}

global $db;

// Get works-specific statistics
$stats = [];

// Active projects
$stmt = $db->prepare("SELECT COUNT(*) as count FROM works_projects WHERE company_id = ? AND status = 'In Progress'");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['active_projects'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Completed projects
$stmt = $db->prepare("SELECT COUNT(*) as count FROM works_projects WHERE company_id = ? AND status = 'Completed'");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['completed_projects'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Total employees
$result = $db->query("SELECT COUNT(*) as count FROM works_employees WHERE status = 'Active'");
$stats['total_employees'] = $result->fetch_assoc()['count'] ?? 0;

// Total materials
$result = $db->query("SELECT COUNT(*) as count FROM works_materials");
$stats['total_materials'] = $result->fetch_assoc()['count'] ?? 0;

// Total budget
$stmt = $db->prepare("SELECT COALESCE(SUM(budget), 0) as total FROM works_projects WHERE company_id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['total_budget'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Actual cost
$stmt = $db->prepare("SELECT COALESCE(SUM(actual_cost), 0) as total FROM works_projects WHERE company_id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['actual_cost'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Low materials
$result = $db->query("SELECT COUNT(*) as count FROM works_materials WHERE current_stock <= minimum_stock");
$stats['low_materials'] = $result->fetch_assoc()['count'] ?? 0;

// Active projects list
$active_projects = [];
$projects_query = "SELECT * FROM works_projects 
                   WHERE company_id = ? AND status = 'In Progress'
                   ORDER BY end_date ASC";
$stmt = $db->prepare($projects_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$active_projects = $stmt->get_result();

// Recent daily reports
$recent_reports = [];
$reports_query = "SELECT dr.*, p.project_name, u.full_name as reporter_name
                 FROM works_daily_reports dr
                 JOIN works_projects p ON dr.project_id = p.project_id
                 JOIN users u ON dr.submitted_by = u.user_id
                 WHERE p.company_id = ?
                 ORDER BY dr.created_at DESC LIMIT 10";
$stmt = $db->prepare($reports_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$recent_reports = $stmt->get_result();

// Upcoming deadlines
$deadlines_query = "SELECT project_name, end_date, progress_percentage,
                    DATEDIFF(end_date, CURRENT_DATE()) as days_left
                    FROM works_projects 
                    WHERE company_id = ? AND status = 'In Progress'
                    AND end_date IS NOT NULL
                    ORDER BY end_date ASC LIMIT 5";
$stmt = $db->prepare($deadlines_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$upcoming_deadlines = $stmt->get_result();

// Material usage today
$usage_query = "SELECT pm.*, m.material_name, p.project_name
               FROM works_project_materials pm
               JOIN works_materials m ON pm.material_id = m.material_id
               JOIN works_projects p ON pm.project_id = p.project_id
               WHERE DATE(pm.date_used) = CURDATE()
               ORDER BY pm.created_at DESC LIMIT 10";
$today_usage = $db->query($usage_query);

$page_title = 'Works Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Works Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #f72585;
            --secondary-color: #b5179e;
        }
        
        .department-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(247, 37, 133, 0.3);
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .project-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .project-card.in-progress { border-left-color: #f72585; }
        .project-card.completed { border-left-color: #43e97b; }
        .project-card.on-hold { border-left-color: #f8961e; }
        
        .progress {
            height: 8px;
            border-radius: 4px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 25px;
        }
        
        .quick-action-btn {
            background: white;
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .quick-action-btn:hover {
            border-color: var(--primary-color);
            background: #f8f9fa;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(247, 37, 133, 0.2);
        }
        
        .quick-action-btn i {
            font-size: 28px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .deadline-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .deadline-urgent { background: #f8d7da; color: #721c24; }
        .deadline-warning { background: #fff3cd; color: #856404; }
        .deadline-normal { background: #d4edda; color: #155724; }
        
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
                <div class="sidebar p-3">
                    <div class="text-center mb-4">
                        <h4>Works & Construction</h4>
                        <p class="small text-muted">Project Management</p>
                    </div>
                    
                    <div class="user-info text-center mb-4 p-3 bg-white bg-opacity-10 rounded">
                        <div class="avatar mx-auto mb-2" style="width: 50px; height: 50px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                            <?php echo getAvatarLetter($current_user['full_name']); ?>
                        </div>
                        <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong>
                        <p class="small text-muted"><?php echo $current_user['company_name'] ?? 'Works Dept'; ?></p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link active">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="projects.php" class="nav-link">
                                <i class="fas fa-hard-hat me-2"></i>Projects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="employees.php" class="nav-link">
                                <i class="fas fa-users me-2"></i>Employees
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="materials.php" class="nav-link">
                                <i class="fas fa-box me-2"></i>Materials
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="daily-reports.php" class="nav-link">
                                <i class="fas fa-clipboard-list me-2"></i>Daily Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="reports.php" class="nav-link">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item mt-5">
                            <a href="../../api/logout.php" class="nav-link text-danger">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col">
                <div class="main-content">
                    <!-- Header -->
                    <div class="department-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-2">Works & Construction Dashboard</h2>
                                <p class="mb-0 opacity-75">Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>!</p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-light text-dark p-2">
                                    <i class="far fa-calendar me-2"></i><?php echo date('F j, Y'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stats Row -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-hard-hat"></i>
                                </div>
                                <h3 class="mb-1"><?php echo $stats['active_projects']; ?></h3>
                                <p class="text-muted mb-0">Active Projects</p>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up me-1"></i><?php echo $stats['completed_projects']; ?> completed
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #4cc9f0, #4895ef);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3 class="mb-1"><?php echo $stats['total_employees']; ?></h3>
                                <p class="text-muted mb-0">Active Employees</p>
                                <small class="text-info">
                                    <i class="fas fa-user-plus me-1"></i>On site
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #f8961e, #f3722c);">
                                    <i class="fas fa-box"></i>
                                </div>
                                <h3 class="mb-1"><?php echo $stats['total_materials']; ?></h3>
                                <p class="text-muted mb-0">Materials</p>
                                <small class="text-warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i><?php echo $stats['low_materials']; ?> low stock
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <h3 class="mb-1"><?php echo formatMoney($stats['total_budget']); ?></h3>
                                <p class="text-muted mb-0">Total Budget</p>
                                <small class="text-danger">
                                    <i class="fas fa-arrow-down me-1"></i><?php echo formatMoney($stats['actual_cost']); ?> spent
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Second Row -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="stat-card">
                                <h5 class="mb-3">Active Projects</h5>
                                <?php if ($active_projects && $active_projects->num_rows > 0): ?>
                                    <?php while($project = $active_projects->fetch_assoc()): ?>
                                    <div class="project-card in-progress">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($project['project_name']); ?></h6>
                                                <small class="text-muted">Code: <?php echo $project['project_code']; ?></small>
                                            </div>
                                            <span class="badge bg-primary"><?php echo $project['progress_percentage']; ?>%</span>
                                        </div>
                                        <div class="progress mb-2">
                                            <div class="progress-bar bg-primary" style="width: <?php echo $project['progress_percentage']; ?>%"></div>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <small>
                                                <i class="far fa-calendar me-1"></i>Ends: <?php echo $project['end_date'] ? date('d/m/Y', strtotime($project['end_date'])) : 'TBD'; ?>
                                            </small>
                                            <small>
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($project['location']); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No active projects</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="stat-card mt-3">
                                <h5 class="mb-3">Today's Material Usage</h5>
                                <?php if ($today_usage && $today_usage->num_rows > 0): ?>
                                    <?php while($usage = $today_usage->fetch_assoc()): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 border-bottom">
                                        <div>
                                            <strong><?php echo htmlspecialchars($usage['material_name']); ?></strong>
                                            <br>
                                            <small><?php echo htmlspecialchars($usage['project_name']); ?></small>
                                        </div>
                                        <span class="badge bg-info">
                                            <?php echo $usage['quantity']; ?> units
                                        </span>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No material usage recorded today</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="stat-card">
                                <h5 class="mb-3">Upcoming Deadlines</h5>
                                <?php if ($upcoming_deadlines && $upcoming_deadlines->num_rows > 0): ?>
                                    <?php while($deadline = $upcoming_deadlines->fetch_assoc()): 
                                        $days_left = $deadline['days_left'];
                                        $badge_class = $days_left <= 7 ? 'deadline-urgent' : ($days_left <= 14 ? 'deadline-warning' : 'deadline-normal');
                                    ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 border-bottom">
                                        <div>
                                            <strong><?php echo htmlspecialchars($deadline['project_name']); ?></strong>
                                            <br>
                                            <small>Progress: <?php echo $deadline['progress_percentage']; ?>%</small>
                                        </div>
                                        <span class="deadline-badge <?php echo $badge_class; ?>">
                                            <?php echo $days_left; ?> days left
                                        </span>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No upcoming deadlines</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="stat-card mt-3">
                                <h5 class="mb-3">Recent Daily Reports</h5>
                                <?php if ($recent_reports && $recent_reports->num_rows > 0): ?>
                                    <?php while($report = $recent_reports->fetch_assoc()): ?>
                                    <div class="p-2 border-bottom">
                                        <div class="d-flex justify-content-between">
                                            <strong><?php echo htmlspecialchars($report['project_name']); ?></strong>
                                            <small class="text-muted"><?php echo timeAgo($report['created_at']); ?></small>
                                        </div>
                                        <p class="small mb-0"><?php echo substr(htmlspecialchars($report['work_description']), 0, 100); ?>...</p>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No recent reports</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="quick-actions mt-4">
                        <div class="quick-action-btn" onclick="window.location.href='new-project.php'">
                            <i class="fas fa-plus-circle"></i>
                            <span>New Project</span>
                            <small class="text-muted d-block">Start a project</small>
                        </div>
                        <div class="quick-action-btn" onclick="window.location.href='add-employee.php'">
                            <i class="fas fa-user-plus"></i>
                            <span>Add Employee</span>
                            <small class="text-muted d-block">Hire new worker</small>
                        </div>
                        <div class="quick-action-btn" onclick="window.location.href='add-material.php'">
                            <i class="fas fa-box"></i>
                            <span>Add Material</span>
                            <small class="text-muted d-block">New inventory item</small>
                        </div>
                        <div class="quick-action-btn" onclick="window.location.href='daily-report.php'">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Daily Report</span>
                            <small class="text-muted d-block">Submit report</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>