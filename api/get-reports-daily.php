<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

global $db;

// VALIDATE INPUT

$report_id = isset($_GET['report_id']) ? (int) $_GET['report_id'] : 0;

if ($report_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid report ID']);
    exit;
}

//  FETCH THE REPORT — join project + submitter name
//    Also confirm the report belongs to this company (security check)

$stmt = $db->prepare("
    SELECT
        dr.*,
        p.project_name,
        p.project_code,
        p.location        AS project_location,
        u.full_name       AS submitted_by_name
    FROM  works_daily_reports dr
    JOIN  works_projects p ON p.project_id = dr.project_id
    LEFT  JOIN users     u ON u.user_id    = dr.submitted_by
    WHERE dr.report_id  = ?
    LIMIT 1
");
$stmt->bind_param('i', $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Report not found']);
    exit;
}

//  FETCH MATERIALS USED for this report
//    We match by project_id + date_used so it works even if no direct FK exists

$mat_stmt = $db->prepare("
    SELECT
        pm.quantity,
        pm.unit_cost,
        pm.total_cost,
        pm.used_by,
        pp.product_name,
        pp.unit
    FROM  works_project_materials pm
    JOIN  procurement_products    pp ON pp.product_id  = pm.material_id
    WHERE pm.project_id = ?
      AND pm.date_used  = ?
    ORDER BY pp.product_name
");
$mat_stmt->bind_param('is', $report['project_id'], $report['report_date']);
$mat_stmt->execute();
$mat_result = $mat_stmt->get_result();

$materials = [];
while ($row = $mat_result->fetch_assoc()) {
    $materials[] = $row;
}

// DECODE PHOTOS
//    photos is stored as a JSON array of file paths e.g. ["uploads/...","uploads/..."]

$photos = [];
if (!empty($report['photos'])) {
    $decoded = json_decode($report['photos'], true);
    if (is_array($decoded)) {
        $photos = $decoded;
    }
}

//  RETURN EVERYTHING

echo json_encode([
    'success'   => true,
    'report'    => $report,
    'materials' => $materials,
    'photos'    => $photos,
]);