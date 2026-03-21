<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

global $db;

// Input validation
$project_id = filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT);
if (!$project_id || $project_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid project ID']);
    exit;
}

try {
    // Core project info
    $stmt = $db->prepare("
        SELECT p.*,
               u.full_name AS manager_name,
               u.phone     AS manager_phone,
               c.company_name
        FROM   works_projects p
        LEFT   JOIN users     u ON u.user_id    = p.project_manager
        LEFT   JOIN companies c ON c.company_id = p.company_id
        WHERE  p.project_id = ?
        LIMIT  1
    ");
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();   // single row — correct

    if (!$project) {
        http_response_code(404);
        echo json_encode(['error' => 'Project not found']);
        exit;
    }

    // Progress history 
    $stmt = $db->prepare("
        SELECT pp.*, u.full_name AS reporter_name
        FROM   works_project_progress pp
        LEFT   JOIN users u ON u.user_id = pp.reported_by
        WHERE  pp.project_id = ?
        ORDER  BY pp.report_date DESC
        LIMIT  5
    ");
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $progress_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Active employee assignments 
    $stmt = $db->prepare("
        SELECT pa.role, pa.start_date, pa.status,
               e.full_name AS employee_name, e.position, e.phone
        FROM   works_project_assignments pa
        JOIN   works_employees           e ON e.employee_id = pa.employee_id
        WHERE  pa.project_id = ?
          AND  pa.status = 'Active'
        ORDER  BY e.full_name
    ");
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Recent material usage 
    $stmt = $db->prepare("
        SELECT pm.date_used, pm.quantity, pm.unit_cost, pm.total_cost,
               m.product_name, m.unit
        FROM   works_project_materials pm
        JOIN   procurement_products m ON m.product_id = pm.material_id
        WHERE  pm.project_id = ? AND m.category = 'Building Materials'
        ORDER  BY pm.date_used DESC
        LIMIT  10
    ");
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Recent daily reports
    $stmt = $db->prepare("
        SELECT dr.report_date, dr.work_description, dr.employees_present,
               dr.hours_worked, dr.weather_conditions, dr.status,
               u.full_name AS submitted_by_name
        FROM   works_daily_reports dr
        LEFT   JOIN users u ON u.user_id = dr.submitted_by
        WHERE  dr.project_id = ?
        ORDER  BY dr.report_date DESC
        LIMIT  5
    ");
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $daily_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Budget summary
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total_cost), 0) AS total_materials_cost
        FROM   works_project_materials
        WHERE  project_id = ?
    ");
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $mat_row       = $stmt->get_result()->fetch_assoc();
    $material_cost = (float) $mat_row['total_materials_cost'];

    $budget_summary = [
        'budget'             => (float) $project['budget'],
        'actual_cost'        => (float) $project['actual_cost'],
        'contingency'        => (float) $project['contingency'],
        'total_budget'       => (float) ($project['total_budget'] ?? $project['budget']),
        'materials_cost'     => $material_cost,
        'budget_utilisation' => $project['budget'] > 0
            ? round(($project['actual_cost'] / $project['budget']) * 100, 1)
            : 0,
    ];

    echo json_encode([
        'success'          => true,
        'project'          => $project,
        'progress_history' => $progress_history,
        'assignments'      => $assignments,
        'materials'        => $materials,
        'daily_reports'    => $daily_reports,
        'budget_summary'   => $budget_summary,
    ]);

} catch (Exception $e) {
    error_log('get-project-details.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'A server error occurred. Please try again.']);
}
?>