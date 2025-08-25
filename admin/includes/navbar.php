<?php

/**
 * Admin Top Navigation Bar
 */
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <!-- Mobile menu toggle -->
        <button class="btn btn-dark d-lg-none me-2" type="button" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>

        <!-- Page title -->
        <span class="navbar-brand mb-0 h1 d-lg-none">Admin Panel</span>

        <!-- Desktop spacer -->
        <div class="d-none d-lg-block" style="width: 250px;"></div>

        <!-- Search bar - DISABLED -->
        <?php /*
        <form class="d-flex flex-grow-1 mx-3" id="searchForm">
            <div class="input-group">
                <input class="form-control" type="search" placeholder="Search products, images..." id="globalSearch">
                <button class="btn btn-outline-light" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
        */ ?>

        <!-- Right side items -->
        <ul class="navbar-nav">
            <!-- Notifications -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                    <i class="fas fa-bell"></i>
                    <span class="badge bg-danger" id="notificationCount">3</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="width: 300px;">
                    <li>
                        <h6 class="dropdown-header">Notifications</h6>
                    </li>
                    <li><a class="dropdown-item" href="#">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-upload text-white"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="small">New images uploaded</div>
                                    <div class="text-muted small">5 minutes ago</div>
                                </div>
                            </div>
                        </a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item text-center" href="logs.php">View All Activity</a></li>
                </ul>
            </li>

            <!-- User profile -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                    <div class="me-2">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                            <i class="fas fa-user text-white"></i>
                        </div>
                    </div>
                    <span class="d-none d-md-inline"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php">
                            <i class="fas fa-user me-2"></i>Profile
                        </a></li>
                    <li><a class="dropdown-item" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a></li>
                </ul>
            </li>
        </ul>
    </div>
</nav>