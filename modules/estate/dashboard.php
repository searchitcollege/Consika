<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/db_connection.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/session.php';

$session->requireLogin();

$current_user = $session->getCurrentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

// Ensure only Estate users can access
if ($current_user['company_type'] != 'Estate') {
    $_SESSION['error'] = 'Access denied. Estate department only.';
    header('Location: ../../api/logout.php');
    exit();
}

global $db;

// Get estate-specific statistics
$stats = [];

// Total properties
$stmt = $db->prepare("SELECT COUNT(*) as count FROM estate_properties WHERE company_id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['total_properties'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Active tenants
$stmt = $db->prepare("SELECT COUNT(*) as count FROM estate_tenants WHERE status = 'Active' AND property_id IN (SELECT property_id FROM estate_properties WHERE company_id = ?)");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['active_tenants'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Pending maintenance
$stmt = $db->prepare("SELECT COUNT(*) as count FROM estate_maintenance WHERE status = 'Pending' AND property_id IN (SELECT property_id FROM estate_properties WHERE company_id = ?)");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['pending_maintenance'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;

// Monthly rent collection
$rent_query = "SELECT COALESCE(SUM(amount), 0) as total FROM estate_payments 
               WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) 
               AND YEAR(payment_date) = YEAR(CURRENT_DATE())
               AND property_id IN (SELECT property_id FROM estate_properties WHERE company_id = ?)";
$stmt = $db->prepare($rent_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$stats['monthly_rent'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// Occupancy rate
$total_units = 0;
$occupied_units = 0;
$units_query = "SELECT SUM(units) as total FROM estate_properties WHERE company_id = ?";
$stmt = $db->prepare($units_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$total_units = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

$occupied_query = "SELECT COUNT(*) as occupied FROM estate_tenants WHERE status = 'Active' AND property_id IN (SELECT property_id FROM estate_properties WHERE company_id = ?)";
$stmt = $db->prepare($occupied_query);
$stmt->bind_param("i", $company_id);
$stmt->execute();
$occupied_units = $stmt->get_result()->fetch_assoc()['occupied'] ?? 0;

$stats['occupancy_rate'] = $total_units > 0 ? round(($occupied_units / $total_units) * 100, 2) : 0;

// Recent activities
$recent_activities = [];
$activity_query = "SELECT a.*, u.full_name 
                  FROM activity_log a 
                  JOIN users u ON a.user_id = u.user_id 
                  WHERE a.module = 'estate' OR a.module IS NULL
                  ORDER BY a.created_at DESC LIMIT 10";
$result = $db->query($activity_query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}

$page_title = 'Estate Dashboard';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estate Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .department-header {
            background: linear-gradient(135deg, #4361ee, #3f37c9);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, #4361ee, #3f37c9);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
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
            border-color: #4361ee;
            background: #f8f9fa;
            transform: translateY(-3px);
        }
    </style>
</head>

<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <?php include dirname(__DIR__, 2) . '/includes/sidebar.php'; ?>
            <!-- Main Content -->
            <div class="col p-4">
                <div class="main-content">
                    <div class="department-header justify-content-between">
                        <div>
                            <h2>Estate Dashboard</h2>
                            <p class='text-white'>Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>!</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light text-dark p-2">
                                <i class="far fa-calendar me-2"></i>April 20, 2026 </span>
                            <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                                <i class="fas fa-bars"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <h3><?php echo $stats['total_properties']; ?></h3>
                                <p>Total Properties</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #4cc9f0, #4895ef);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h3><?php echo $stats['active_tenants']; ?></h3>
                                <p>Active Tenants</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #f8961e, #f3722c);">
                                    <i class="fas fa-tools"></i>
                                </div>
                                <h3><?php echo $stats['pending_maintenance']; ?></h3>
                                <p>Pending Maintenance</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #f72585, #b5179e);">
                                    <i class="fas fa-money-bill"></i>
                                </div>
                                <h3><?php echo formatMoney($stats['monthly_rent']); ?></h3>
                                <p>Monthly Rent</p>
                            </div>
                        </div>
                    </div>

                    <!-- Second Stats Row -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Occupancy Rate</h5>
                                </div>
                                <div class="card-body">
                                    <div class="progress" style="height: 30px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo $stats['occupancy_rate']; ?>%;">
                                            <?php echo $stats['occupancy_rate']; ?>%
                                        </div>
                                    </div>
                                    <p class="mt-2"><?php echo $occupied_units; ?> out of <?php echo $total_units; ?> units occupied</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Recent Activities</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled">
                                        <?php foreach ($recent_activities as $activity): ?>
                                            <li class="mb-2">
                                                <small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small>
                                                <p class="mb-0"><?php echo htmlspecialchars($activity['description']); ?></p>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <!-- <div class="quick-actions mt-4">
                    <div class="quick-action-btn" onclick="window.location.href='./prop.php'">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Property</span>
                    </div>
                    <div class="quick-action-btn" onclick="window.location.href='add-tenant.php'">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Tenant</span>
                    </div>
                    <div class="quick-action-btn" onclick="window.location.href='record-payment.php'">
                        <i class="fas fa-money-bill"></i>
                        <span>Record Payment</span>
                    </div>
                    <div class="quick-action-btn" onclick="window.location.href='maintenance-request.php'">
                        <i class="fas fa-tools"></i>
                        <span>Maintenance Request</span>
                    </div>
                </div> -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>
</body>

</html>