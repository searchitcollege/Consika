<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/session.php';

$session->requireLogin();

$current_user = currentUser();
$company_id   = $session->getCompanyId();
$role         = $session->getRole();

global $db;

// Access check
if ($current_user['company_type'] != 'Works' && !in_array($role, ['SuperAdmin', 'CompanyAdmin', 'Manager'])) {
    $_SESSION['error'] = "You do not have permission to access this page.";
    header("Location: ../../login.php");
    exit();
}

// Summary counts for the stat cards
$stats_stmt = $db->prepare("
    SELECT
        COUNT(*)                                                          AS total_reports,
        SUM(CASE WHEN dr.status = 'Approved'  THEN 1 ELSE 0 END)        AS approved,
        SUM(CASE WHEN dr.status = 'Submitted' THEN 1 ELSE 0 END)        AS pending,
        SUM(CASE WHEN dr.report_date = CURDATE() THEN 1 ELSE 0 END)     AS today
    FROM  works_daily_reports dr
    JOIN  works_projects p ON p.project_id = dr.project_id
    WHERE p.company_id = ?
");
$stats_stmt->bind_param('i', $company_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// All reports for the list (limit 50 — add pagination later if needed)
$reports_stmt = $db->prepare("
    SELECT
        dr.report_id,
        dr.report_date,
        dr.status,
        dr.weather_conditions,
        dr.employees_present,
        dr.hours_worked,
        dr.work_description,
        dr.photos,
        dr.created_at,
        p.project_name,
        p.project_code,
        u.full_name AS reporter_name
    FROM  works_daily_reports dr
    JOIN  works_projects p ON p.project_id  = dr.project_id
    LEFT  JOIN users     u ON u.user_id     = dr.submitted_by
    WHERE p.company_id = ?
    ORDER BY dr.report_date DESC, dr.created_at DESC
");
$reports_stmt->bind_param('i', $company_id);
$reports_stmt->execute();
$reports = $reports_stmt->get_result();

// Projects list for the new-report modal
$projects_stmt = $db->prepare("
    SELECT project_id, project_name
    FROM   works_projects
    WHERE  company_id = ? AND status = 'In Progress'
    ORDER  BY project_name
");
$projects_stmt->bind_param('i', $company_id);
$projects_stmt->execute();
$projects_list = $projects_stmt->get_result();

// Materials list for the new-report modal
$mats_result = $db->query("
    SELECT product_id, product_name
    FROM   procurement_products
    WHERE  category = 'works'
    ORDER  BY product_name ASC
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Reports – <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="../../assets/css/works/style.css" rel="stylesheet">
</head>

<body>
    <div class="container-fluid p-0 module-works works">
        <?php include '../../includes/sidebar.php'; ?>

        <div class="main-content">

            <!-- Header -->
            <div class="department-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-2">Works & Construction - PROJECTS</h1>
                        <p class="mb-0 opacity-75 text-white">Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>!</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-light text-dark p-2">
                            <i class="far fa-calendar me-2"></i><?php echo date('F j, Y'); ?>
                        </span>
                        <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                            <i class="fas fa-bars"></i>
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dailyReportModal">
                            <i class="fas fa-plus-circle me-2"></i>New Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stat cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                        <h3 class="stat-value"><?php echo $stats['total_reports']; ?></h3>
                        <p class="stat-label">Total Reports</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#4cc9f0,#4895ef)">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h3 class="stat-value"><?php echo $stats['today']; ?></h3>
                        <p class="stat-label">Today</p>
                    </div>
                </div>
            </div>

            <!-- Reports list -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">All Daily Reports</h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($reports->num_rows > 0): ?>
                        <div class="list-group list-group-flush" id="reportsList">
                            <?php while ($r = $reports->fetch_assoc()):
                                $has_photos = !empty($r['photos']);
                                $status_color = $r['status'] === 'Approved' ? 'success'
                                    : ($r['status'] === 'Submitted' ? 'primary' : 'secondary');
                                $preview = htmlspecialchars(substr($r['work_description'], 0, 160));
                                if (strlen($r['work_description']) > 160) $preview .= '…';
                            ?>
                                <div class="list-group-item list-group-item-action px-4 py-3">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <!-- Project name + code -->
                                            <span class="fw-semibold"><?php echo htmlspecialchars($r['project_name']); ?></span>
                                            <span class="text-muted ms-2 small"><?php echo $r['project_code']; ?></span>
                                        </div>
                                        <span class="badge bg-<?php echo $status_color; ?> ms-2 flex-shrink-0">
                                            <?php echo $r['status']; ?>
                                        </span>
                                    </div>

                                    <!-- Meta row: reporter, date, weather, staff, hours -->
                                    <div class="text-muted small mb-2 d-flex flex-wrap gap-3">
                                        <span><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($r['reporter_name'] ?? '—'); ?></span>
                                        <span><i class="fas fa-calendar me-1"></i><?php echo date('d M Y', strtotime($r['report_date'])); ?></span>
                                        <?php if ($r['weather_conditions']): ?>
                                            <span><i class="fas fa-cloud-sun me-1"></i><?php echo htmlspecialchars($r['weather_conditions']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($r['employees_present']): ?>
                                            <span><i class="fas fa-hard-hat me-1"></i><?php echo $r['employees_present']; ?> workers</span>
                                        <?php endif; ?>
                                        <?php if ($r['hours_worked']): ?>
                                            <span><i class="fas fa-clock me-1"></i><?php echo $r['hours_worked']; ?> hrs</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Description preview -->
                                    <p class="mb-2 small"><?php echo $preview; ?></p>

                                    <!-- Action buttons -->
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-primary"
                                            onclick="viewReport(<?php echo $r['report_id']; ?>)">
                                            <i class="fas fa-eye me-1"></i>View Full Report
                                        </button>
                                        <?php if ($has_photos): ?>
                                            <button class="btn btn-sm btn-outline-info"
                                                onclick="viewReport(<?php echo $r['report_id']; ?>, true)">
                                                <i class="fas fa-images me-1"></i>Photos
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-5">No daily reports found.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /main-content -->
    </div>

    <!-- View Report Modal -->
    <div class="modal fade" id="viewReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2 text-primary"></i>
                        <span id="vrProjectName"></span>
                        <span id="vrProjectCode" class="badge bg-secondary ms-2 fw-normal"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <!-- Loading spinner -->
                    <div id="vrSpinner" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Loading report…</p>
                    </div>

                    <!-- Error state -->
                    <div id="vrError" class="alert alert-danger d-none"></div>

                    <!-- Content (shown after load) -->
                    <div id="vrContent" class="d-none">

                        <!-- Row 1: stat pills -->
                        <div class="row g-3 mb-4">
                            <div class="col-6 col-md-3">
                                <div class="card border-0 bg-light text-center h-100">
                                    <div class="card-body py-3">
                                        <div class="text-muted small mb-1">Date</div>
                                        <div class="fw-semibold" id="vrDate"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card border-0 bg-light text-center h-100">
                                    <div class="card-body py-3">
                                        <div class="text-muted small mb-1">Status</div>
                                        <div id="vrStatus"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card border-0 bg-light text-center h-100">
                                    <div class="card-body py-3">
                                        <div class="text-muted small mb-1">Workers</div>
                                        <div class="fw-semibold fs-5" id="vrWorkers"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card border-0 bg-light text-center h-100">
                                    <div class="card-body py-3">
                                        <div class="text-muted small mb-1">Hours</div>
                                        <div class="fw-semibold fs-5" id="vrHours"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: meta info -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold">Report Info</div>
                                    <div class="card-body small">
                                        <table class="table table-sm table-borderless mb-0">
                                            <tr>
                                                <th class="text-muted" style="width:40%">Submitted By</th>
                                                <td id="vrSubmittedBy"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Location</th>
                                                <td id="vrLocation"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Weather</th>
                                                <td id="vrWeather"></td>
                                            </tr>
                                            <tr>
                                                <th class="text-muted">Submitted At</th>
                                                <td id="vrCreatedAt"></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <!-- Materials used table -->
                                <div class="card h-100">
                                    <div class="card-header fw-semibold">
                                        Materials Used
                                        <span class="badge bg-primary ms-1" id="vrMaterialCount">0</span>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive" style="max-height:160px">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th>Material</th>
                                                        <th>Qty</th>
                                                        <th>Unit Cost</th>
                                                        <th>Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="vrMaterials"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Work Description -->
                        <div class="card mb-3">
                            <div class="card-header fw-semibold">Work Description</div>
                            <div class="card-body" id="vrWorkDescription"></div>
                        </div>

                        <!-- Challenges / Achievements / Next Plan -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4" id="vrChallengesWrapper">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold text-danger">
                                        <i class="fas fa-exclamation-triangle me-1"></i>Challenges
                                    </div>
                                    <div class="card-body small" id="vrChallenges"></div>
                                </div>
                            </div>
                            <div class="col-md-4" id="vrAchievementsWrapper">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold text-success">
                                        <i class="fas fa-trophy me-1"></i>Achievements
                                    </div>
                                    <div class="card-body small" id="vrAchievements"></div>
                                </div>
                            </div>
                            <div class="col-md-4" id="vrNextPlanWrapper">
                                <div class="card h-100">
                                    <div class="card-header fw-semibold text-info">
                                        <i class="fas fa-arrow-right me-1"></i>Plan for Tomorrow
                                    </div>
                                    <div class="card-body small" id="vrNextPlan"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Equipment -->
                        <div class="card mb-3" id="vrEquipmentWrapper">
                            <div class="card-header fw-semibold">Equipment Used</div>
                            <div class="card-body small" id="vrEquipment"></div>
                        </div>

                        <!-- Supervisor Notes -->
                        <div class="card mb-3 d-none" id="vrSupervisorWrapper">
                            <div class="card-header fw-semibold">Supervisor Notes</div>
                            <div class="card-body small" id="vrSupervisorNotes"></div>
                        </div>

                        <!-- Photos -->
                        <div class="card d-none" id="vrPhotosCard">
                            <div class="card-header fw-semibold">
                                <i class="fas fa-images me-2"></i>Site Photos
                                <span class="badge bg-secondary ms-1" id="vrPhotoCount">0</span>
                            </div>
                            <div class="card-body">
                                <div class="row g-2" id="vrPhotosGrid"></div>
                            </div>
                        </div>

                    </div><!-- /vrContent -->
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>

            </div>
        </div>
    </div>

    <!-- New Report Modal  -->
    <div class="modal fade" id="dailyReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2 text-primary"></i>Submit Daily Report
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="newReportAlert" class="d-none mb-3"></div>

                    <!-- Project -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Project <span class="text-danger">*</span></label>
                        <select class="form-select" name="project_id" id="newReportProjectId" required>
                            <option value="">Select Project…</option>
                            <?php while ($proj = $projects_list->fetch_assoc()): ?>
                                <option value="<?= $proj['project_id'] ?>"><?= htmlspecialchars($proj['project_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Report Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="report_date"
                                value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Weather</label>
                            <select class="form-select" name="weather_conditions">
                                <option value="Sunny">☀️ Sunny</option>
                                <option value="Cloudy">⛅ Cloudy</option>
                                <option value="Rainy">🌧️ Rainy</option>
                                <option value="Windy">💨 Windy</option>
                                <option value="Overcast">🌥️ Overcast</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Work Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="work_description" rows="4"
                            placeholder="Describe the work carried out today…" required></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Employees Present <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="employees_present" min="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Hours Worked <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" class="form-control" name="hours_worked" min="0" max="24" required>
                        </div>
                    </div>

                    <!-- Materials -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-semibold mb-0">Materials Used</label>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="addMaterialRow()">
                                <i class="fas fa-plus me-1"></i>Add Row
                            </button>
                        </div>
                        <div id="materialsContainer"></div>
                        <div class="text-muted small mt-1">Leave blank if no materials were used today.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Equipment Used</label>
                        <textarea class="form-control" name="equipment_used" rows="2"
                            placeholder="e.g. Excavator, Concrete mixer…"></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Challenges</label>
                            <textarea class="form-control" name="challenges" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Achievements</label>
                            <textarea class="form-control" name="achievements" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Plan for Tomorrow</label>
                        <textarea class="form-control" name="next_plan" rows="2"></textarea>
                    </div>

                    <div class="mb-1">
                        <label class="form-label fw-semibold">Site Photos</label>
                        <input type="file" class="form-control" name="photos[]" id="newReportPhotos"
                            multiple accept="image/*">
                        <div class="text-muted small mt-1">Max 5 MB per photo.</div>
                        <div id="photoPreviewRow" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submitReportBtn">
                        <span id="submitReportSpinner" class="spinner-border spinner-border-sm me-1 d-none"></span>
                        Submit Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden material options template — PHP renders once, JS clones per row -->
    <select id="_materialOptionsTpl" class="d-none" aria-hidden="true">
        <option value="">Select Material…</option>
        <?php if ($mats_result): while ($mat = $mats_result->fetch_assoc()): ?>
                <option value="<?= $mat['product_id'] ?>"><?= htmlspecialchars($mat['product_name']) ?></option>
        <?php endwhile;
        endif; ?>
    </select>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>

    <script>
        var _matIndex = 0;

        // VIEW REPORT
        // Opens the view modal and fetches full details via AJAX
        // scrollToPhotos = true scrolls straight to the photos section after load
        function viewReport(reportId, scrollToPhotos) {
            scrollToPhotos = scrollToPhotos || false;

            // Reset modal to loading state
            $('#vrSpinner').removeClass('d-none');
            $('#vrContent').addClass('d-none');
            $('#vrError').addClass('d-none').text('');
            $('#viewReportModal').modal('show');

            $.ajax({
                    url: '../../api/get-reports-daily.php',
                    method: 'GET',
                    data: {
                        report_id: reportId
                    },
                    dataType: 'json'
                })
                .done(function(data) {
                    if (!data.success) {
                        showVrError(data.error || 'Failed to load report.');
                        return;
                    }
                    populateReportModal(data, scrollToPhotos);
                })
                .fail(function(xhr) {
                    var msg = 'Could not load report details.';
                    try {
                        var p = JSON.parse(xhr.responseText);
                        if (p.error) msg = p.error;
                    } catch (e) {}
                    showVrError(msg);
                });
        }

        function showVrError(msg) {
            $('#vrSpinner').addClass('d-none');
            $('#vrError').removeClass('d-none').text(msg);
        }

        function populateReportModal(data, scrollToPhotos) {
            var r = data.report;

            //  Header 
            $('#vrProjectName').text(r.project_name || '');
            $('#vrProjectCode').text(r.project_code || '');

            // Stat pills 
            $('#vrDate').text(formatDate(r.report_date));

            var sc = {
                Approved: 'bg-success',
                Submitted: 'bg-primary',
                Draft: 'bg-secondary'
            };
            $('#vrStatus').html('<span class="badge ' + (sc[r.status] || 'bg-secondary') + ' fs-6">' + escHtml(r.status) + '</span>');
            $('#vrWorkers').text(r.employees_present || '—');
            $('#vrHours').text(r.hours_worked ? r.hours_worked + ' hrs' : '—');

            // Report info table 
            $('#vrSubmittedBy').text(r.submitted_by_name || '—');
            $('#vrLocation').text(r.project_location || '—');
            $('#vrWeather').text(r.weather_conditions || '—');
            $('#vrCreatedAt').text(formatDateTime(r.created_at));

            // Materials table 
            var $matBody = $('#vrMaterials').empty();
            $('#vrMaterialCount').text(data.materials.length);
            if (data.materials.length) {
                $.each(data.materials, function(_, m) {
                    $matBody.append(
                        '<tr>' +
                        '<td>' + escHtml(m.product_name) + '</td>' +
                        '<td>' + m.quantity + '</td>' +
                        '<td>' + formatMoney(m.unit_cost) + '</td>' +
                        '<td>' + formatMoney(m.total_cost) + '</td>' +
                        '</tr>'
                    );
                });
            } else {
                $matBody.append('<tr><td colspan="4" class="text-muted text-center py-2">None recorded</td></tr>');
            }

            // Text sections 
            // Convert newlines to <br> for readability
            $('#vrWorkDescription').html(escHtml(r.work_description || '—').replace(/\n/g, '<br>'));

            setOptionalSection('#vrChallengesWrapper', '#vrChallenges', r.challenges);
            setOptionalSection('#vrAchievementsWrapper', '#vrAchievements', r.achievements);
            setOptionalSection('#vrNextPlanWrapper', '#vrNextPlan', r.next_plan);
            setOptionalSection('#vrEquipmentWrapper', '#vrEquipment', r.equipment_used);

            // Supervisor notes (shown only if present)
            if (r.supervisor_notes) {
                $('#vrSupervisorNotes').html(escHtml(r.supervisor_notes).replace(/\n/g, '<br>'));
                $('#vrSupervisorWrapper').removeClass('d-none');
            } else {
                $('#vrSupervisorWrapper').addClass('d-none');
            }

            // Photos
            var $grid = $('#vrPhotosGrid').empty();
            $('#vrPhotoCount').text(data.photos.length);

            if (data.photos.length) {
                $('#vrPhotosCard').removeClass('d-none');
                $.each(data.photos, function(i, path) {
                    // Adjust the base URL to your project root
                    var url = '../../' + path;
                    $grid.append(
                        '<div class="col-6 col-md-3">' +
                        '<a href="' + url + '" target="_blank">' +
                        '<img src="' + url + '" class="img-fluid rounded border w-100"' +
                        ' style="height:140px;object-fit:cover"' +
                        ' alt="Site photo ' + (i + 1) + '">' +
                        '</a>' +
                        '</div>'
                    );
                });
            } else {
                $('#vrPhotosCard').addClass('d-none');
            }

            // Reveal
            $('#vrSpinner').addClass('d-none');
            $('#vrContent').removeClass('d-none');

            // If the Photos button was clicked, scroll down to the photos card
            if (scrollToPhotos && data.photos.length) {
                setTimeout(function() {
                    var el = document.getElementById('vrPhotosCard');
                    if (el) el.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }, 150);
            }
        }

        // Show/hide an optional section based on whether the field has content
        function setOptionalSection(wrapperSel, contentSel, value) {
            if (value && value.trim()) {
                $(contentSel).html(escHtml(value).replace(/\n/g, '<br>'));
                $(wrapperSel).removeClass('d-none');
            } else {
                $(wrapperSel).addClass('d-none');
            }
        }

        // MATERIAL ROWS (new report modal)
        function addMaterialRow() {
            var idx = _matIndex++;
            $('#materialsContainer').append(
                '<div class="row g-2 mb-2 material-row align-items-center">' +
                '<div class="col-md-7">' +
                '<select class="form-select form-select-sm" name="materials[' + idx + '][id]">' +
                $('#_materialOptionsTpl').html() +
                '</select>' +
                '</div>' +
                '<div class="col-md-3">' +
                '<input type="number" step="0.01" min="0" class="form-control form-control-sm"' +
                ' name="materials[' + idx + '][quantity]" placeholder="Qty">' +
                '</div>' +
                '<div class="col-md-2 text-end">' +
                '<button type="button" class="btn btn-outline-danger btn-sm remove-mat-row">' +
                '<i class="fas fa-times"></i>' +
                '</button>' +
                '</div>' +
                '</div>'
            );
        }

        $(document).on('click', '.remove-mat-row', function() {
            $(this).closest('.material-row').remove();
        });

        // PHOTO PREVIEW (new report modal)
        $('#newReportPhotos').on('change', function() {
            var $p = $('#photoPreviewRow').empty();
            $.each(this.files, function(_, f) {
                if (!f.type.startsWith('image/') || f.size > 5 * 1024 * 1024) return;
                var r = new FileReader();
                r.onload = function(e) {
                    $p.append('<div style="width:72px;height:72px"><img src="' + e.target.result +
                        '" class="rounded border w-100 h-100" style="object-fit:cover"></div>');
                };
                r.readAsDataURL(f);
            });
        });

        // RESET new report modal each time it opens
        $('#dailyReportModal').on('show.bs.modal', function() {
            $('#newReportAlert').addClass('d-none').text('');
            $(this).find('input[type=text], input[type=number], textarea').val('');
            $(this).find('input[type=date]').val('<?= date("Y-m-d") ?>');
            $(this).find('select').prop('selectedIndex', 0);
            $('#materialsContainer').empty();
            _matIndex = 0;
            addMaterialRow();
            $('#newReportPhotos').val('');
            $('#photoPreviewRow').empty();
        });

        // SUBMIT new report via AJAX
        $('#submitReportBtn').on('click', function() {
            var $modal = $('#dailyReportModal');
            var $btn = $(this);
            var $spinner = $('#submitReportSpinner');
            var $alert = $('#newReportAlert');

            // Highlight missing required fields
            var valid = true;
            $modal.find('[required]').each(function() {
                $(this).val() ? $(this).removeClass('is-invalid') : ($(this).addClass('is-invalid'), valid = false);
            });
            if (!valid) {
                $alert.removeClass('d-none alert-success alert-danger')
                    .addClass('alert alert-warning')
                    .text('Please fill in all required fields.');
                return;
            }

            // Build FormData so file uploads are included
            var fd = new FormData();
            $modal.find('input[name], select[name], textarea[name]').each(function() {
                if (!$(this).is('input[type=file]')) fd.append($(this).attr('name'), $(this).val() || '');
            });
            var files = $('#newReportPhotos')[0].files;
            for (var i = 0; i < files.length; i++) fd.append('photos[]', files[i]);

            $btn.prop('disabled', true);
            $spinner.removeClass('d-none');
            $alert.addClass('d-none');

            $.ajax({
                    url: '../../api/daily-report.php',
                    method: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false
                })
                .done(function(res) {
                    var d = (typeof res === 'string') ? JSON.parse(res) : res;
                    if (d.success) {
                        $alert.removeClass('d-none alert-danger alert-warning')
                            .addClass('alert alert-success')
                            .html('<i class="fas fa-check-circle me-1"></i> Report submitted successfully!');
                        setTimeout(function() {
                            $('#dailyReportModal').modal('hide');
                            location.reload();
                        }, 1200);
                    } else {
                        $alert.removeClass('d-none alert-success alert-warning')
                            .addClass('alert alert-danger')
                            .text(d.error || 'Submission failed. Please try again.');
                    }
                })
                .fail(function() {
                    $alert.removeClass('d-none alert-success alert-warning')
                        .addClass('alert alert-danger')
                        .text('Network error. Please check your connection and try again.');
                })
                .always(function() {
                    $btn.prop('disabled', false);
                    $spinner.addClass('d-none');
                });
        });

        // Clear is-invalid highlight as the user fixes fields
        $(document).on('input change', '#dailyReportModal [required]', function() {
            if ($(this).val()) $(this).removeClass('is-invalid');
        });

        // HELPERS
        function formatDate(d) {
            if (!d) return '—';
            var dt = new Date(d);
            return isNaN(dt.getTime()) ? d : dt.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        function formatDateTime(d) {
            if (!d) return '—';
            var dt = new Date(d);
            return isNaN(dt.getTime()) ? d :
                dt.toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                }) + ' ' +
                dt.toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
        }

        function formatMoney(n) {
            return parseFloat(n || 0).toLocaleString('en-GH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function escHtml(str) {
            return $('<div>').text(str || '').html();
        }
    </script>
</body>

</html>