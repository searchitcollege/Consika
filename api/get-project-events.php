<?php
require_once '../includes/session.php';
$session->requireLogin();

global $db;
header('Content-Type: application/json');

$company_id = $session->getCompanyId();
if (empty($company_id)) {
    $result     = $db->query("SELECT company_id FROM companies WHERE company_type = 'Works' LIMIT 1");
    $company_id = (int)($result->fetch_assoc()['company_id'] ?? 0);
}

$stmt = $db->prepare("
    SELECT project_id, project_name, start_date, end_date, status, progress_percentage
    FROM works_projects
    WHERE company_id = ?
    ORDER BY start_date ASC
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$projects = $stmt->get_result();

$colors = [
    'Planning'    => '#4cc9f0',
    'In Progress' => '#4361ee',
    'On Hold'     => '#f8961e',
    'Completed'   => '#28a745',
    'Cancelled'   => '#dc3545',
];

$events = [];
while ($p = $projects->fetch_assoc()) {
    $color = $colors[$p['status']] ?? '#6c757d';
    $events[] = [
        'id'                => $p['project_id'],
        'title'             => $p['project_name'] . ' (' . $p['progress_percentage'] . '%)',
        'start'             => $p['start_date'],
        'end'               => $p['end_date'] ?: $p['start_date'],
        'backgroundColor'   => $color,
        'borderColor'       => $color,
        'textColor'         => '#fff',
        'extendedProps'     => [
            'status'   => $p['status'],
            'progress' => $p['progress_percentage'],
        ],
    ];
}

echo json_encode($events);