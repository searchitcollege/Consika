<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!in_array($session->getRole(), ['SuperAdmin', 'CompanyAdmin'])) {
    header('Location: dashboard.php');
    exit();
}
global $db;

$filter = trim($_GET['filter'] ?? 'Pending'); // Pending | Approved | Rejected | All

// Build one pending list from all tables
$pending_items = [];

$sources = [
    'estate' => [
        ['table' => 'estate_properties',   'id' => 'property_id',   'label_col' => 'property_name',   'link' => '../modules/estate/property-details.php?id='],
        ['table' => 'estate_tenants',       'id' => 'tenant_id',     'label_col' => 'full_name',        'link' => '../api/tenant-details.php?id='],
        ['table' => 'estate_payments',      'id' => 'payment_id',    'label_col' => 'receipt_number',   'link' => '../api/receipt.php?id='],
        ['table' => 'estate_maintenance',   'id' => 'maintenance_id', 'label_col' => 'description',      'link' => null],
    ],
    'procurement' => [
        ['table' => 'procurement_suppliers',       'id' => 'supplier_id', 'label_col' => 'supplier_name', 'link' => '../api/view-supplier.php?id='],
        ['table' => 'procurement_products',        'id' => 'product_id',  'label_col' => 'product_name',  'link' => '../api/view-product.php?id='],
        ['table' => 'procurement_purchase_orders', 'id' => 'po_id',       'label_col' => 'po_number',     'link' => '../api/view-po.php?id='],
    ],
    'works' => [
        ['table' => 'works_projects',        'id' => 'project_id',  'label_col' => 'project_name', 'link' => null],
        ['table' => 'works_employees',       'id' => 'employee_id', 'label_col' => 'full_name',    'link' => '../api/view-employee.php?id='],
        ['table' => 'works_daily_reports',   'id' => 'report_id',   'label_col' => 'report_date',  'link' => null],
    ],
    'blockfactory' => [
        ['table' => 'blockfactory_production',   'id' => 'production_id', 'label_col' => 'batch_number',   'link' => '../api/view-batch.php?id='],
        ['table' => 'blockfactory_sales',        'id' => 'sale_id',       'label_col' => 'invoice_number', 'link' => '../api/view-sale.php?id='],
        ['table' => 'blockfactory_customers',    'id' => 'customer_id',   'label_col' => 'customer_name',  'link' => '../api/view-customer.php?id='],
        ['table' => 'blockfactory_deliveries',   'id' => 'delivery_id',   'label_col' => 'delivery_note',  'link' => '../api/view-delivery.php?id='],
        ['table' => 'blockfactory_raw_materials', 'id' => 'material_id',   'label_col' => 'material_name',  'link' => null],
    ],
];

$where_status = $filter === 'All' ? "1=1" : "admin_approvals = '{$filter}'";

$counts = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];

foreach ($sources as $module => $tables) {
    foreach ($tables as $src) {
        $result = $db->query("
            SELECT `{$src['id']}` as record_id,
                   `{$src['label_col']}` as label,
                   admin_approvals,
                   approved_at,
                   rejection_reason,
                   created_at
            FROM `{$src['table']}`
            WHERE {$where_status}
            ORDER BY created_at DESC
            LIMIT 100
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['module']    = $module;
                $row['table']     = $src['table'];
                $row['id_col']    = $src['id'];
                $row['link']      = $src['link'] ? $src['link'] . $row['record_id'] : null;
                $row['type']      = ucwords(str_replace('_', ' ', str_replace([$module . '_', 'estate_', 'procurement_', 'works_', 'blockfactory_'], '', $src['table'])));
                $pending_items[]  = $row;
                if (in_array($row['admin_approvals'], ['Pending', 'Approved', 'Rejected'])) {
                    $counts[$row['admin_approvals']] = ($counts[$row['admin_approvals']] ?? 0) + 1;
                }
            }
        }
    }
}

// Sort by created_at desc
usort($pending_items, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Approvals - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .approval-row {
            transition: opacity .3s ease;
        }

        .approval-row.processing {
            opacity: 0.4;
            pointer-events: none;
        }

        .module-badge-estate {
            background: #4e54c8;
        }

        .module-badge-procurement {
            background: #f7971e;
        }

        .module-badge-works {
            background: #11998e;
        }

        .module-badge-blockfactory {
            background: #eb3349;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/top-nav.php'; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success'];
                                                    unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4><i class="fas fa-check-circle me-2 text-primary"></i>Approvals</h4>
                <div class="d-flex gap-2">
                    <?php foreach (['Pending', 'Approved', 'Rejected', 'All'] as $f): ?>
                        <a href="?filter=<?php echo $f; ?>"
                            class="btn btn-sm <?php echo $filter === $f ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                            <?php echo $f; ?>
                            <?php if ($f !== 'All' && isset($counts[$f]) && $counts[$f] > 0): ?>
                                <span class="badge bg-<?php echo $f === 'Pending' ? 'warning text-dark' : ($f === 'Approved' ? 'success' : 'danger'); ?> ms-1">
                                    <?php echo $counts[$f]; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Summary cards (always show pending counts) -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-warning">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="fs-2 text-warning"><i class="fas fa-clock"></i></div>
                            <div>
                                <div class="fw-bold fs-3 text-warning"><?php echo $counts['Pending']; ?></div>
                                <div class="text-muted small">Pending Approval</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="fs-2 text-success"><i class="fas fa-check"></i></div>
                            <div>
                                <div class="fw-bold fs-3 text-success"><?php echo $counts['Approved']; ?></div>
                                <div class="text-muted small">Approved</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-danger">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="fs-2 text-danger"><i class="fas fa-times"></i></div>
                            <div>
                                <div class="fw-bold fs-3 text-danger"><?php echo $counts['Rejected']; ?></div>
                                <div class="text-muted small">Rejected</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="fs-2 text-secondary"><i class="fas fa-layer-group"></i></div>
                            <div>
                                <div class="fw-bold fs-3"><?php echo count($pending_items); ?></div>
                                <div class="text-muted small">Showing Now</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0" id="approvalsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Module</th>
                                <th>Type</th>
                                <th>Record</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Details</th>
                                <?php if ($filter === 'Pending' || $filter === 'All'): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_items)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fas fa-check-double fa-2x d-block mb-2 text-success opacity-50"></i>
                                        <?php echo $filter === 'Pending' ? 'No pending approvals. All caught up!' : 'No records found.'; ?>
                                    </td>
                                </tr>
                                <?php else: foreach ($pending_items as $item): ?>
                                    <tr class="approval-row"
                                        id="row-<?php echo $item['table']; ?>-<?php echo $item['record_id']; ?>"
                                        data-table="<?php echo $item['table']; ?>"
                                        data-id="<?php echo $item['record_id']; ?>">
                                        <td>
                                            <span class="badge text-white module-badge-<?php echo $item['module']; ?>">
                                                <?php echo ucfirst($item['module']); ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small"><?php echo $item['type']; ?></td>
                                        <td>
                                            <?php if ($item['link']): ?>
                                                <a href="<?php echo $item['link']; ?>" target="_blank" class="fw-semibold text-decoration-none">
                                                    <?php echo htmlspecialchars($item['label']); ?>
                                                    <i class="fas fa-external-link-alt ms-1 small"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="fw-semibold"><?php echo htmlspecialchars($item['label']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $st = $item['admin_approvals'];
                                            $sc = $st === 'Approved' ? 'success' : ($st === 'Rejected' ? 'danger' : 'warning text-dark');
                                            ?>
                                            <span class="badge bg-<?php echo $sc; ?>"><?php echo $st; ?></span>
                                            <?php if ($st === 'Rejected' && $item['rejection_reason']): ?>
                                                <i class="fas fa-info-circle text-muted ms-1"
                                                    title="<?php echo htmlspecialchars($item['rejection_reason']); ?>"
                                                    data-bs-toggle="tooltip"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?php echo $item['created_at'] ? format_datetime($item['created_at']) : '—'; ?></small></td>
                                        <td>
                                            <?php if ($item['approved_at']): ?>
                                                <small class="text-muted"><?php echo format_datetime($item['approved_at']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($filter === 'Pending' || $filter === 'All'): ?>
                                            <td>
                                                <?php if ($item['admin_approvals'] === 'Pending'): ?>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-success btn-sm px-2"
                                                            onclick="handleApproval('<?php echo $item['table']; ?>', <?php echo $item['record_id']; ?>, 'approve')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-danger btn-sm px-2"
                                                            onclick="promptReject('<?php echo $item['table']; ?>', <?php echo $item['record_id']; ?>)">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">—</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                            <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-times-circle me-2"></i>Reject Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Optionally provide a reason for rejection:</p>
                    <textarea id="rejectReason" class="form-control" rows="3"
                        placeholder="e.g. Missing documentation, incorrect details..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmRejectBtn">
                        <i class="fas fa-times me-1"></i>Confirm Reject
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script>
        $('#approvalsTable').DataTable({
            order: [
                [4, 'desc']
            ],
            pageLength: 25
        });

        // Init tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el);
        });

        let _rejectTable = '',
            _rejectId = 0;
        const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));

        function handleApproval(table, id, action, reason = '') {
            const rowId = `#row-${table}-${id}`;
            $(rowId).addClass('processing');
            $.post('../api/approve.php', {
                table,
                id,
                action,
                reason
            }, function(res) {
                if (res.success) {
                    if (action === 'approve') {
                        $(rowId).find('.badge').first().removeClass('bg-warning text-dark').addClass('bg-success').text('Approved');
                        $(rowId).find('td:last-child').html('<span class="text-muted small">—</span>');
                    } else {
                        $(rowId).find('.badge').first().removeClass('bg-warning text-dark').addClass('bg-danger').text('Rejected');
                        $(rowId).find('td:last-child').html('<span class="text-muted small">—</span>');
                    }
                    // Update summary counts
                    const pendingEl = document.querySelector('.text-warning.fw-bold.fs-3');
                    if (pendingEl) pendingEl.textContent = Math.max(0, parseInt(pendingEl.textContent) - 1);
                } else {
                    alert('Error: ' + (res.message || 'Unknown error'));
                }
                $(rowId).removeClass('processing');
            }, 'json').fail(function() {
                alert('Request failed. Please try again.');
                $(rowId).removeClass('processing');
            });
        }

        function promptReject(table, id) {
            _rejectTable = table;
            _rejectId = id;
            $('#rejectReason').val('');
            rejectModal.show();
        }

        $('#confirmRejectBtn').on('click', function() {
            const reason = $('#rejectReason').val().trim();
            rejectModal.hide();
            handleApproval(_rejectTable, _rejectId, 'reject', reason);
        });

        // Approve All Pending button logic (optional bulk)
        function approveAll() {
            if (!confirm('Approve ALL pending items on this page?')) return;
            document.querySelectorAll('.approval-row').forEach(row => {
                const badge = row.querySelector('.badge');
                if (badge && badge.textContent.trim() === 'Pending') {
                    const table = row.dataset.table;
                    const id = row.dataset.id;
                    handleApproval(table, id, 'approve');
                }
            });
        }
    </script>
</body>

</html>