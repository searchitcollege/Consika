<!-- THIS PAGE WAS SEPARATELY CREATED BECAUSE OF REQUESTYS OF PURCHASE ORDERS TO BE PUT IN FROM OTHER DEPARTMENTS -->

<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/session.php';

$session->requireLogin();

$current_user = currentUser();
$company_id = $session->getCompanyId();
$role = $session->getRole();

if ($current_user['company_type'] != 'Procurement' && ($role != 'SuperAdmin' && $role != 'CompanyAdmin' && $role != 'Manager')) {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ../../login.php');
    exit();
}

global $db;


//    HANDLE APPROVAL ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $po_id = $_POST['po_id'] ?? null;
    $action = $_POST['action'] ?? null;
    $note = $_POST['note'] ?? '';

    if (!$po_id) {
        $_SESSION['error'] = "Invalid Purchase Order.";
        header("Location: approvals.php");
        exit();
    }

    if ($action === "approve") {

        $sql = "UPDATE procurement_purchase_orders
                SET approval_status='Approved',
                    payment_status='Paid',
                    notes = CONCAT(IFNULL(notes,''), '\nApproved by {$current_user['full_name']} on ', NOW(), '\n{$note}')
                WHERE po_id=?";
    } elseif ($action === "reject") {

        $sql = "UPDATE procurement_purchase_orders
                SET approval_status='Rejected',
                    payment_status='Unpaid',
                    notes = CONCAT(IFNULL(notes,''), '\nRejected by {$current_user['full_name']} on ', NOW(), '\n{$note}')
                WHERE po_id=?";
    } elseif ($action === "deliver") {

        $sql = "UPDATE procurement_purchase_orders
                SET delivery_status='Completed',
                    delivery_date = NOW(),
                    notes = CONCAT(IFNULL(notes,''), '\nDelivered on ', NOW(), '\n{$note}')
                WHERE po_id=?";


        // 2. Add stock to inventory
        $sql2 = "
                UPDATE procurement_po_items pi
                JOIN procurement_products p ON pi.product_id = p.product_id
                SET 
                    p.current_stock = p.current_stock + pi.quantity,
                    pi.status = 'Received'
                WHERE pi.po_id = ? 
                AND pi.status = 'Pending'
            ";

        $stmt2 = $db->prepare($sql2);
        $stmt2->bind_param("i", $po_id);
        $stmt2->execute();
    } else {

        $_SESSION['error'] = "Invalid action.";
        header("Location: approvals.php");
        exit();
    }

    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $po_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Purchase order updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update purchase order.";
    }

    header("Location: approvals.php");
    exit();
}

//    FETCH PENDING APPROVALS
$sql = "
    SELECT 
        po.po_id,
        po.po_number,
        po.total_amount,
        po.order_date,
        po.approval_status,
        s.supplier_name,
        u.full_name AS created_by
    FROM procurement_purchase_orders po
    JOIN procurement_suppliers s ON po.supplier_id = s.supplier_id
    LEFT JOIN users u ON po.created_by = u.user_id
    WHERE po.approval_status = 'Pending'
    ORDER BY po.created_at DESC
    ";

$result = $db->query($sql);

$approved_sql = "
    SELECT po.*, s.supplier_name
    FROM procurement_purchase_orders po
    JOIN procurement_suppliers s ON po.supplier_id=s.supplier_id
    WHERE po.approval_status='Approved'
    AND po.delivery_status='Pending'
    ORDER BY po.order_date DESC";

$approved_orders = $db->query($approved_sql);

$delivered_sql = "
    SELECT po.*, s.supplier_name
    FROM procurement_purchase_orders po
    JOIN procurement_suppliers s ON po.supplier_id=s.supplier_id
    WHERE po.delivery_status='Completed'
    ORDER BY po.order_date DESC";

$delivered_orders = $db->query($delivered_sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Procurement Management - <?php echo APP_NAME; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">

    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Custom CSS -->
    <link href="../../assets/css/style.css" rel="stylesheet">

    <!-- Custom CSS For jus Procuremnt Pages-->
    <link href="../../assets/css/procurement/style.css" rel="stylesheet">

</head>

<body>
    <div class="container-fluid p-0">
        <!-- Include sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Main content -->
        <div class="col">
            <div class="main-content">
                <!-- Header -->
                <div class="module-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2">Procurement - Purchase Order Approvals</h2>
                            <p class="mb-0 opacity-75 text-white">Welcome back, <?php echo htmlspecialchars($current_user['full_name']); ?>!</p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light text-dark p-2">
                                <i class="far fa-calendar me-2"></i><?php echo date('F j, Y'); ?>
                            </span>
                            <button id="sidebarToggle" class="btn btn-dark d-md-none m-2">
                                <i class="fas fa-bars"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php echo $_SESSION['success'];
                        unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php echo $_SESSION['error'];
                        unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5>Pending Approvals</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>PO Number</th>
                                        <th>Supplier</th>
                                        <th>Order Date</th>
                                        <th>Total</th>
                                        <th>Created By</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?php echo $row['po_number']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                                <td><?php echo format_date($row['order_date']); ?></td>
                                                <td><?php echo format_money($row['total_amount']); ?></td>
                                                <td><?php echo htmlspecialchars($row['created_by']); ?></td>
                                                <td>
                                                    <button
                                                        class="btn btn-sm btn-success"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#approveModal"
                                                        data-id="<?php echo $row['po_id']; ?>">
                                                        Approve
                                                    </button>

                                                    <button
                                                        class="btn btn-sm btn-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#rejectModal"
                                                        data-id="<?php echo $row['po_id']; ?>">
                                                        Reject
                                                    </button>

                                                    <a onclick="viewEntity('../../api/get_purchase_order.php',<?php echo $row['po_id']; ?>, 'Purchase Order')" class="btn btn-sm btn-info">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No pending approvals.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Approved Orders (Awaiting Delivery)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>PO</th>
                                        <th>Supplier</th>
                                        <th>Total</th>
                                        <th>Order Date</th>
                                        <th>Action</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $approved_orders->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['po_number']; ?></td>
                                            <td><?php echo $row['supplier_name']; ?></td>
                                            <td><?php echo format_money($row['total_amount']); ?></td>
                                            <td><?php echo format_date($row['order_date']); ?></td>
                                            <td><?php echo nl2br(htmlspecialchars($row['notes'] ?? '')); ?></td>
                                            <td>
                                                <button
                                                    class="btn btn-warning btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deliverModal"
                                                    data-id="<?php echo $row['po_id']; ?>">
                                                    Mark Delivered
                                                </button>
                                                <button
                                                    class="btn btn-info btn-sm"
                                                    onclick="viewEntity('../../api/get_purchase_order.php',<?php echo $row['po_id']; ?>, 'Purchase Order')">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Delivered Orders</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>PO</th>
                                    <th>Supplier</th>
                                    <th>Total</th>
                                    <th>Order Date</th>
                                    <th>Approval</th>
                                    <th>Payment</th>
                                    <th>Delivery</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $delivered_orders->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['po_number']; ?></td>
                                        <td><?php echo $row['supplier_name']; ?></td>
                                        <td><?php echo format_money($row['total_amount']); ?></td>
                                        <td><?php echo format_date($row['order_date']); ?></td>

                                        <td>
                                            <span class="badge bg-<?php echo $row['approval_status'] == 'Approved' ? 'success' : 'secondary'; ?>">
                                                <?php echo $row['approval_status']; ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="badge bg-<?php echo $row['payment_status'] == 'Paid' ? 'success' : 'danger'; ?>">
                                                <?php echo $row['payment_status']; ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo $row['delivery_status']; ?>
                                            </span>
                                        </td>

                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- APPROVE MODAL -->
    <div class="modal fade" id="approveModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5>Approve Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="po_id" id="approve_po_id">
                        <input type="hidden" name="action" value="approve">
                        <label>Approval Note</label>
                        <textarea name="note" class="form-control"></textarea>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-success">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- REJECT MODAL -->
    <div class="modal fade" id="rejectModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5>Reject Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="po_id" id="reject_po_id">
                        <input type="hidden" name="action" value="reject">
                        <label>Reason</label>
                        <textarea name="note" class="form-control" required></textarea>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deliverModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5>Mark Order as Delivered</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="po_id" id="deliver_po_id">
                        <input type="hidden" name="action" value="deliver">
                        <label>Delivery Note</label>
                        <textarea name="note" class="form-control"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-warning">
                            Confirm Delivery
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../assets/js/main.js"></script>
    <script src="../../assets/js/modules.js"></script>
    <script>
        var approveModal = document.getElementById('approveModal')
        approveModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget
            var id = button.getAttribute('data-id')
            document.getElementById('approve_po_id').value = id
        })

        var rejectModal = document.getElementById('rejectModal')
        rejectModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget
            var id = button.getAttribute('data-id')
            document.getElementById('reject_po_id').value = id
        })

        var deliverModal = document.getElementById('deliverModal')
        deliverModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget
            var id = button.getAttribute('data-id')
            document.getElementById('deliver_po_id').value = id
        })

        // / FOR REUSABK MODAL VIEWING INSTANCE
        let viewModalInstance;

        function showModal(title, data) {
            let html = '<div class="container-fluid">';
            for (const key in data) {
                if (Array.isArray(data[key])) {
                    // For nested arrays (like PO line items)
                    html += `<h6 class="mt-3">${key.replace(/_/g,' ')}</h6><ul>`;
                    data[key].forEach(item => {
                        html += '<li>' + Object.entries(item)
                            .map(([k, v]) => `${k}: ${v}`)
                            .join(', ') + '</li>';
                    });
                    html += '</ul>';
                } else {
                    html += `<p><strong>${key.replace(/_/g,' ')}</strong>: ${data[key]}</p>`;
                }
            }
            html += '</div>';

            document.getElementById('modalBody').innerHTML = html;
            document.querySelector('#viewModal .modal-title').innerText = title;

            if (!viewModalInstance) {
                viewModalInstance = new bootstrap.Modal(document.getElementById('viewModal'));
            }
            viewModalInstance.show();
        }

        function viewEntity(endpoint, id, title) {
            fetch(`${endpoint}?id=${id}`)
                .then(res => res.json())
                .then(data => showModal(title, data))
                .catch(err => alert('Error loading data: ' + err));
        }

        // /////////////////////////////////////////////////////

        function viewPO(id) {
            window.location.href = 'view-po.php?id=' + id;
        }
    </script>
</body>

</html>