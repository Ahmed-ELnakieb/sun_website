<?php

/**
 * Admin Sidebar Navigation
 */

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="d-flex align-items-center">
            <div class="sidebar-logo">
                <i class="fas fa-sun"></i>
            </div>
            <div class="sidebar-brand">
                <h5 class="mb-0">Sun Trading</h5>
                <small class="text-muted">Admin Panel</small>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'products' ? 'active' : ''; ?>" href="products.php">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'images' ? 'active' : ''; ?>" href="images.php">
                    <i class="fas fa-images"></i>
                    <span>Image Gallery</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'content' ? 'active' : ''; ?>" href="content.php">
                    <i class="fas fa-edit"></i>
                    <span>Website Content</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>

            <li class="nav-divider"></li>

            <?php if ($auth->isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>" href="users.php">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'backup' ? 'active' : ''; ?>" href="backup.php">
                        <i class="fas fa-database"></i>
                        <span>Database Backup</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo $currentPage === 'logs' ? 'active' : ''; ?>" href="logs.php">
                        <i class="fas fa-history"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>
            <?php endif; ?>

            <li class="nav-divider"></li>

            <li class="nav-item">
                <a class="nav-link" href="javascript:void(0);" onclick="window.open('../', '_blank');">
                    <i class="fas fa-external-link-alt"></i>
                    <span>View Website</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>