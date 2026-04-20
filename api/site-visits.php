<?php
require_once '../includes/session.php';
$session->requireLogin();
if (!hasPermission('works', 'view')) { header('Location: index.php'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Site Visits - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/top-nav.php'; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Site Visits</h4>
            <a href="../modules/works/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        </div>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-camera fa-4x text-muted mb-4 d-block opacity-50"></i>
                <h5 class="text-muted">Site Visits Coming Soon</h5>
                <p class="text-muted">This section will allow you to log and manage site visit reports with photo evidence.</p>
                <a href="index.php" class="btn btn-primary mt-2">
                    <i class="fas fa-arrow-left me-1"></i>Return to Works
                </a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>