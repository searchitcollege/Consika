<?php
// Get current user from session
$current_user = $session->getCurrentUser();
?>
<!-- Top Navigation -->
<div class="top-nav">
    <div class="page-title">
        <h2><?php echo $page_title ?? 'Dashboard'; ?></h2>
        <p>Welcome back, <?php echo htmlspecialchars($current_user['full_name'] ?? 'User'); ?>!</p>
    </div>

    <div class="top-actions">
        <div class="notification-badge" onclick="window.location.href='<?php echo baseUrl('admin/notifications.php'); ?>'">
            <i class="far fa-bell"></i>
            <span class="badge-count">0</span>
        </div>

        <div class="user-dropdown" data-bs-toggle="dropdown">
            <div class="user-avatar">
                <?php echo getAvatarLetter($current_user['full_name'] ?? 'User'); ?>
            </div>
            <div class="user-info-text">
                <div class="name"><?php echo htmlspecialchars($current_user['full_name'] ?? 'User'); ?></div>
                <div class="role"><?php echo $current_user['company_name'] ?? 'System Admin'; ?></div>
            </div>

            <i class="fas fa-chevron-down"></i>
        </div>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="<?php echo baseUrl('admin/profile.php'); ?>"><i class="fas fa-user me-2"></i>Profile</a></li>
            <li><a class="dropdown-item" href="<?php echo baseUrl('admin/settings.php'); ?>"><i class="fas fa-cog me-2"></i>Settings</a></li>
            <li>
                <hr class="dropdown-divider">
            </li>
            <li><a class="dropdown-item" href="<?php echo baseUrl('api/logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
    </div>
</div>