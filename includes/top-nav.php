<?php
$current_user = $session->getCurrentUser();
$role = $_SESSION['role'] ?? '';
?>

<!-- Top Navigation -->
<div class="top-nav d-flex justify-content-between align-items-center px-4 py-3 shadow-sm bg-white mb-4">

    <!-- Left: Page Title -->
    <div class="page-title">
        <h5 class="mb-0 fw-semibold">
            <?php echo $page_title ?? 'Dashboard'; ?>
        </h5>
        <small class="text-muted">
            Welcome back, <?php echo htmlspecialchars($current_user['full_name'] ?? 'User'); ?>
        </small>
    </div>

    <!-- Right: Actions -->
    <div class="d-flex align-items-center gap-3">

        <!-- Notifications -->
        <div class="position-relative cursor-pointer"
            onclick="window.location.href='<?php echo baseUrl('admin/notifications.php'); ?>'">

            <i class="far fa-bell fs-5"></i>

            <!-- Badge -->
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                0
            </span>
        </div>
        <?php if ($role === 'SuperAdmin'): ?>
            <button id="sidebarToggle" class="btn btn-dark d-md-none">
                <i class="fas fa-bars"></i>
            </button>
        <?php endif; ?>

        <!-- User Dropdown -->
        <div class="dropdown">

            <div class="d-flex align-items-center gap-2 cursor-pointer"
                role="button"
                data-bs-toggle="dropdown"
                aria-expanded="false">

                <!-- Avatar -->
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                    style="width: 38px; height: 38px; font-weight: 600;">
                    <?php echo getAvatarLetter($current_user['full_name'] ?? 'U'); ?>
                </div>

                <!-- Name & Role -->
                <div class="d-none d-md-block">
                    <div class="fw-semibold small">
                        <?php echo htmlspecialchars($current_user['full_name'] ?? 'User'); ?>
                    </div>
                    <div class="text-muted" style="font-size: 12px;">
                        <?php echo htmlspecialchars($current_user['company_name'] ?? 'System Admin'); ?>
                    </div>
                </div>

                <i class="fas fa-chevron-down text-muted small"></i>
            </div>

            <!-- Dropdown Menu -->
            <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-2">

                <li>
                    <a class="dropdown-item d-flex align-items-center"
                        href="<?php echo baseUrl('admin/profile.php'); ?>">
                        <i class="fas fa-user me-2 text-muted"></i> Profile
                    </a>
                </li>

                <li>
                    <a class="dropdown-item d-flex align-items-center"
                        href="<?php echo baseUrl('admin/settings.php'); ?>">
                        <i class="fas fa-cog me-2 text-muted"></i> Settings
                    </a>
                </li>

                <li>
                    <hr class="dropdown-divider">
                </li>

                <li>
                    <a class="dropdown-item d-flex align-items-center text-danger"
                        href="<?php echo baseUrl('api/logout.php'); ?>">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>

            </ul>
        </div>

    </div>
</div>