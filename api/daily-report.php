<?php
require_once '../includes/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

global $db;

$project_id        = isset($_POST['project_id'])        ? (int)   $_POST['project_id']        : 0;
$report_date       = isset($_POST['report_date'])        ? trim($_POST['report_date'])          : '';
$work_description  = isset($_POST['work_description'])   ? trim($_POST['work_description'])     : '';
$employees_present = isset($_POST['employees_present'])  ? (int)   $_POST['employees_present'] : 0;
$hours_worked      = isset($_POST['hours_worked'])       ? (float) $_POST['hours_worked']       : 0.0;
$weather           = isset($_POST['weather_conditions']) ? trim($_POST['weather_conditions'])   : '';
$equipment_used    = isset($_POST['equipment_used'])     ? trim($_POST['equipment_used'])       : '';
$challenges        = isset($_POST['challenges'])         ? trim($_POST['challenges'])           : '';
$achievements      = isset($_POST['achievements'])       ? trim($_POST['achievements'])         : '';
$next_plan         = isset($_POST['next_plan'])          ? trim($_POST['next_plan'])            : '';

// Materials comes in as an array of rows: [ ['id' => X, 'quantity' => Y], ... ]
$materials = (isset($_POST['materials']) && is_array($_POST['materials']))
    ? $_POST['materials']
    : [];

// VALIDATE REQUIRED FIELDS
if (!$project_id || !$report_date || !$work_description || !$employees_present || !$hours_worked) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
    exit;
}

// Report date cannot be in the future
if ($report_date > date('Y-m-d')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Report date cannot be in the future.']);
    exit;
}

// HANDLE PHOTO UPLOADS  (optional — skipped if no photos were selected)
$photos_json   = null;                      // will hold a JSON array of file paths
$allowed_ext   = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$max_file_size = 5 * 1024 * 1024;          // 5 MB per photo

if (!empty($_FILES['photos']['name'][0])) {

    // Save under uploads/daily-reports/YYYY/MM/ relative to the project root
    $upload_dir = '../uploads/daily-reports/' . date('Y/m') . '/';

    // Create the folder if it doesn't exist yet
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $saved_paths = [];

    foreach ($_FILES['photos']['tmp_name'] as $i => $tmp_path) {

        // Skip if the upload itself failed
        if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;

        // Skip files that are too large
        if ($_FILES['photos']['size'][$i] > $max_file_size) continue;

        // Only allow image extensions
        $ext = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) continue;

        // Build a unique filename so uploads never overwrite each other
        $filename = 'rpt_' . $project_id . '_' . date('Ymd_His') . '_' . $i . '.' . $ext;

        if (move_uploaded_file($tmp_path, $upload_dir . $filename)) {
            $saved_paths[] = 'uploads/daily-reports/' . date('Y/m') . '/' . $filename;
        }
    }

    // Only set photos_json if at least one photo was saved successfully
    if (!empty($saved_paths)) {
        $photos_json = json_encode($saved_paths);
    }
}

// 5. INSERT THE DAILY REPORT ROW
$stmt = $db->prepare("
    INSERT INTO works_daily_reports (
        project_id, report_date, weather_conditions, work_description,
        employees_present, hours_worked, equipment_used,
        challenges, achievements, next_plan,
        photos, submitted_by, status, created_at
    ) VALUES (
        ?, ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?,
        ?, ?, 'Submitted', NOW()
    )
");

$stmt->bind_param(
    'isssidsssssi',   // one letter per ? above, in the same order
    $project_id,
    $report_date,
    $weather,
    $work_description,
    $employees_present,
    $hours_worked,
    $equipment_used,
    $challenges,
    $achievements,
    $next_plan,
    $photos_json,
    $user_id
);

if (!$stmt->execute()) {
    // Log the real error server-side but don't expose it to the browser
    error_log('daily-report.php insert failed: ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not save the report. Please try again.']);
    exit;
}

// Grab the new row's ID for the response
$id_result = $db->query("SELECT LAST_INSERT_ID() AS id");
$id_row    = $id_result->fetch_assoc();
$report_id = (int) $id_row['id'];

// SAVE MATERIALS USED  (optional — skipped if no materials were added)
foreach ($materials as $mat) {

    $mat_id = (int)   ($mat['id']       ?? 0);
    $qty    = (float) ($mat['quantity'] ?? 0);

    // Skip rows where the dropdown was left blank or quantity is zero
    if ($mat_id <= 0 || $qty <= 0) continue;

    // Look up the unit cost for this material
    $cost_stmt = $db->prepare("SELECT unit_price FROM procurement_products WHERE product_id = ? LIMIT 1");
    $cost_stmt->bind_param('i', $mat_id);
    $cost_stmt->execute();
    $cost_row   = $cost_stmt->get_result()->fetch_assoc();
    $unit_cost  = $cost_row ? (float) $cost_row['unit_price'] : 0.00;
    $total_cost = round($unit_cost * $qty, 2);

    // Record this material usage against the project
    $mat_stmt = $db->prepare("
        INSERT INTO works_project_materials
            (project_id, material_id, quantity, unit_cost, total_cost, date_used, recorded_by, created_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $mat_stmt->bind_param('iidddsi', $project_id, $mat_id, $qty, $unit_cost, $total_cost, $report_date, $user_id);
    $mat_stmt->execute();

    // Grab the usage_id we just created so we can link it to the report
    $uid_row   = $db->query("SELECT LAST_INSERT_ID() AS id")->fetch_assoc();
    $usage_ids[] = (int) $uid_row['id'];

    // Deduct quantity from stock and update the status label automatically
    $stock_stmt = $db->prepare("
        UPDATE procurement_products
        SET
            current_stock = GREATEST(0, current_stock - ?),
            updated_at = NOW()
        WHERE product_id = ?
    ");
    $stock_stmt->bind_param('di', $qty, $mat_id);
    $stock_stmt->execute();

    // TODO: Update the daily reports ncolumn materials_used and link to work_projects_materials 
    // usage_id whilst also updating the used_by with user_id
    $upd_stmt = $db->prepare("
        UPDATE works_daily_reports
        SET    materials_used = ?
        WHERE  report_id      = ?
    ");
    $upd_stmt->bind_param('ii', $usage_ids, $report_id);
    $upd_stmt->execute();
}

echo json_encode([
    'success'   => true,
    'message'   => 'Daily report submitted successfully.'
]);
