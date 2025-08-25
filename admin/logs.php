<?php

/**
 * Sun Trading Company - Activity Logs
 * Based on Critso Template Structure
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$auth->requireAdmin(); // Logs management requires admin privileges

$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();
$db = Database::getInstance();

$success = '';
$error = '';

// Handle log cleanup - Delete all logs
if ($_POST && isset($_POST['delete_all_logs'])) {
    try {
        $affectedRows = $db->execute("DELETE FROM activity_logs");
        if ($affectedRows !== false) {
            if ($affectedRows > 0) {
                $success = "Successfully deleted all $affectedRows log entries!";
            } else {
                $success = "No logs found to delete.";
            }
        } else {
            $error = 'Failed to delete logs. Database query failed.';
        }
    } catch (Exception $e) {
        $error = 'Error deleting logs: ' . $e->getMessage();
    }
}

// Get log statistics
$stats = [];
$stats['total_logs'] = $db->fetchOne("SELECT COUNT(*) as count FROM activity_logs")['count'] ?? 0;
$stats['today_logs'] = $db->fetchOne("SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()")['count'] ?? 0;
$stats['error_logs'] = $db->fetchOne("SELECT COUNT(*) as count FROM activity_logs WHERE action LIKE '%error%' OR action LIKE '%fail%'")['count'] ?? 0;
$stats['user_actions'] = $db->fetchOne("SELECT COUNT(*) as count FROM activity_logs WHERE action LIKE '%user%'")['count'] ?? 0;

// Initialize filters
$action = $_GET['action'] ?? '';
$date = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Get logs with filters
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$whereConditions = [];
$params = [];

if ($action) {
    $whereConditions[] = "al.action LIKE :action";
    $params['action'] = "%{$action}%";
}
if ($date) {
    $whereConditions[] = "DATE(al.created_at) = :date";
    $params['date'] = $date;
}
if ($search) {
    $whereConditions[] = "(al.action LIKE :search OR al.table_name LIKE :search2 OR au.username LIKE :search3)";
    $searchTerm = "%{$search}%";
    $params['search'] = $searchTerm;
    $params['search2'] = $searchTerm;
    $params['search3'] = $searchTerm;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM activity_logs al LEFT JOIN admin_users au ON al.user_id = au.id {$whereClause}";
$result = $db->fetchOne($countSql, $params);
$filteredLogCount = $result['total'] ?? 0;
$totalPages = ceil($filteredLogCount / $limit);

// Get logs
$sql = "SELECT al.*, au.username, au.full_name 
        FROM activity_logs al 
        LEFT JOIN admin_users au ON al.user_id = au.id 
        {$whereClause} 
        ORDER BY al.created_at DESC 
        LIMIT {$limit} OFFSET {$offset}";

$logList = $db->fetchAll($sql, $params) ?? [];

$flashMessages = getFlashMessages();
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US">

<head>
    <meta charset="utf-8">
    <title>Activity Logs - Sun Trading Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

    <link rel="stylesheet" type="text/css" href="assets/vendor/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/bootstrap.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/styles.css">
    <link rel="stylesheet" href="assets/vendor/font/fonts.css">
    <link rel="stylesheet" href="assets/vendor/icon/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="shortcut icon" href="assets/vendor/images/favicon.png">

    <style>
        .log-action {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .action-create {
            background: rgba(192, 250, 160, 0.2);
            color: #161326;
        }

        .action-update {
            background: rgba(195, 136, 247, 0.1);
            color: #7B3BE0;
        }

        .action-delete {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .action-login {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
        }

        .action-logout {
            background: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }

        .action-default {
            background: rgba(0, 0, 0, 0.1);
            color: #333;
        }
    </style>
</head>

<body class="counter-scroll">
    <div id="wrapper">
        <div id="page" class="">
            <div class="layout-wrap loader-off">
                <div id="preload" class="preload-container">
                    <div class="preloading"><span></span></div>
                </div>

                <!-- section-menu-left -->
                <div class="section-menu-left">
                    <div class="box-logo">
                        <a href="dashboard.php" id="site-logo-inner">
                            <div style="display: flex; align-items: center; color: #fff;">
                                <i class="fas fa-sun" style="font-size: 32px; margin-right: 12px; color: #C0FAA0;"></i>
                                <div>
                                    <div style="font-size: 18px; font-weight: bold;">Sun Trading</div>
                                    <div style="font-size: 12px; opacity: 0.8;">Admin Panel</div>
                                </div>
                            </div>
                        </a>
                        <div class="button-show-hide">
                            <i class="icon-back"></i>
                        </div>
                    </div>
                    <div class="section-menu-left-wrap">
                        <div class="center">
                            <div class="center-item">
                                <div class="center-heading f14-regular text-Gray menu-heading mb-12">Navigation</div>
                            </div>
                            <div class="center-item">
                                <ul class="">
                                    <li class="menu-item">
                                        <a href="dashboard.php" class="menu-item-button">
                                            <div class="icon"><i class="fas fa-tachometer-alt"></i></div>
                                            <div class="text">Dashboard</div>
                                        </a>
                                    </li>
                                    <li class="menu-item has-children">
                                        <a href="javascript:void(0);" class="menu-item-button">
                                            <div class="icon"><i class="fas fa-box"></i></div>
                                            <div class="text">Products</div>
                                        </a>
                                        <ul class="sub-menu">
                                            <li class="sub-menu-item">
                                                <a href="products.php" class="">
                                                    <div class="text">All Products</div>
                                                </a>
                                            </li>
                                            <li class="sub-menu-item">
                                                <a href="images.php" class="">
                                                    <div class="text">Image Gallery</div>
                                                </a>
                                            </li>
                                        </ul>
                                    </li>
                                    <li class="menu-item">
                                        <a href="content.php" class="menu-item-button">
                                            <div class="icon"><i class="fas fa-edit"></i></div>
                                            <div class="text">Content</div>
                                        </a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="settings.php" class="menu-item-button">
                                            <div class="icon"><i class="fas fa-cog"></i></div>
                                            <div class="text">Settings</div>
                                        </a>
                                    </li>
                                    <li class="menu-item has-children active">
                                        <a href="javascript:void(0);" class="menu-item-button">
                                            <div class="icon"><i class="fas fa-tools"></i></div>
                                            <div class="text">System</div>
                                        </a>
                                        <ul class="sub-menu">
                                            <li class="sub-menu-item">
                                                <a href="users.php" class="">
                                                    <div class="text">Users</div>
                                                </a>
                                            </li>
                                            <li class="sub-menu-item">
                                                <a href="backup.php" class="">
                                                    <div class="text">Backup</div>
                                                </a>
                                            </li>
                                            <li class="sub-menu-item">
                                                <a href="logs.php" class="active">
                                                    <div class="text">Logs</div>
                                                </a>
                                            </li>
                                        </ul>
                                    </li>
                                    <li class="menu-item">
                                        <a href="../index.php" target="_blank" class="menu-item-button">
                                            <div class="icon"><i class="fas fa-external-link-alt"></i></div>
                                            <div class="text">View Website</div>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="bottom">
                            <div class="image">
                                <i class="fas fa-list-alt" style="font-size: 48px; color: #C0FAA0;"></i>
                            </div>
                            <div class="content">
                                <p class="f12-regular text-White">Activity Logs</p>
                                <p class="f12-bold text-White">Developed by <a href="mailto:ahmedelnakieb95@gmail.com" style="color: #C0FAA0; text-decoration: none;">Elnakieb</a></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- section-content-right -->
                <div class="section-content-right">
                    <!-- header-dashboard -->
                    <div class="header-dashboard">
                        <div class="wrap">
                            <div class="header-left">
                                <div class="button-show-hide"><i class="icon-menu"></i></div>
                                <h6>Activity Logs</h6>
                            </div>
                            <div class="header-grid">
                                <div class="header-btn">
                                    <div class="popup-wrap noti type-header">
                                        <div class="dropdown">
                                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <span class="header-item"><i class="icon-notification1"></i></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="line1"></div>
                                <div class="popup-wrap user type-header">
                                    <div class="dropdown">
                                        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <span class="header-user wg-user">
                                                <span class="image">
                                                    <i class="fas fa-user-circle" style="font-size: 32px; color: #C388F7;"></i>
                                                </span>
                                                <span class="content flex flex-column">
                                                    <span class="label-02 text-Black name"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                                                    <span class="f14-regular text-Gray">Admin</span>
                                                </span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- main-content -->
                    <div class="main-content">
                        <div class="main-content-inner">
                            <?php if ($success): ?>
                                <div class="alert alert-success" style="margin: 20px;"><?php echo $success; ?></div>
                            <?php endif; ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger" style="margin: 20px;"><?php echo $error; ?></div>
                            <?php endif; ?>

                            <div class="main-content-wrap">
                                <div class="tf-container">
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <!-- Page Header -->
                                            <div class="flex items-center flex-wrap justify-between gap20 mb-32">
                                                <div>
                                                    <h3>Activity Logs</h3>
                                                    <div class="body-text mt-8">Monitor system activities and user actions</div>
                                                </div>
                                                <div class="flex gap16">
                                                    <button class="tf-button style-1 type-outline" onclick="exportLogs()">
                                                        <i class="fas fa-download"></i>Export Logs
                                                    </button>
                                                    <button class="tf-button style-1 type-fill" onclick="openDeleteAllModal()" style="background: #dc3545; border-color: #dc3545;">
                                                        <i class="fas fa-trash"></i>Delete All Logs
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Statistics Cards -->
                                            <div class="flex gap16 flex-wrap mb-32">
                                                <div class="wg-card style-1 bg-Primary" style="min-width: 200px; flex: 1; padding: 16px;">
                                                    <div class="flex items-center gap12">
                                                        <div class="icon">
                                                            <i class="fas fa-list-alt" style="font-size: 20px; color: #fff; background: rgba(255,255,255,0.1); padding: 8px; border-radius: 6px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <h6 class="counter text-White mb-0">
                                                                <span class="number"><?php echo $stats['total_logs']; ?></span>
                                                            </h6>
                                                            <div class="f12-medium text-White">Total Logs</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="wg-card" style="min-width: 200px; flex: 1; padding: 16px;">
                                                    <div class="flex items-center gap12">
                                                        <div class="icon">
                                                            <i class="fas fa-calendar-day" style="font-size: 20px; color: #161326; background: #C0FAA0; padding: 8px; border-radius: 6px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <h6 class="counter mb-0">
                                                                <span class="number"><?php echo $stats['today_logs']; ?></span>
                                                            </h6>
                                                            <div class="f12-medium">Today</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="wg-card" style="min-width: 200px; flex: 1; padding: 16px;">
                                                    <div class="flex items-center gap12">
                                                        <div class="icon">
                                                            <i class="fas fa-exclamation-triangle" style="font-size: 20px; color: #fff; background: #dc3545; padding: 8px; border-radius: 6px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <h6 class="counter mb-0">
                                                                <span class="number"><?php echo $stats['error_logs']; ?></span>
                                                            </h6>
                                                            <div class="f12-medium">Errors</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="wg-card style-1 bg-YellowGreen" style="min-width: 200px; flex: 1; padding: 16px;">
                                                    <div class="flex items-center gap12">
                                                        <div class="icon">
                                                            <i class="fas fa-user-cog" style="font-size: 20px; color: #fff; background: #161326; padding: 8px; border-radius: 6px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <h6 class="counter mb-0">
                                                                <span class="number"><?php echo $stats['user_actions']; ?></span>
                                                            </h6>
                                                            <div class="f12-medium">User Actions</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Search and Filters -->
                                            <div class="wg-box mb-24">
                                                <div class="flex items-center justify-between mb-20">
                                                    <div class="body-title">Filter Logs</div>
                                                </div>
                                                <form method="GET" class="flex gap16 flex-wrap">
                                                    <fieldset style="flex: 1; min-width: 200px;">
                                                        <input type="text" class="tf-input style-1" name="search"
                                                            value="<?php echo htmlspecialchars($search); ?>"
                                                            placeholder="Search actions, tables, users...">
                                                    </fieldset>
                                                    <fieldset style="flex: 1; min-width: 150px;">
                                                        <input type="text" class="tf-input style-1" name="action"
                                                            value="<?php echo htmlspecialchars($action); ?>"
                                                            placeholder="Action type">
                                                    </fieldset>
                                                    <fieldset style="flex: 1; min-width: 150px;">
                                                        <input type="date" class="tf-input style-1" name="date"
                                                            value="<?php echo htmlspecialchars($date); ?>">
                                                    </fieldset>
                                                    <button type="submit" class="tf-button style-1 type-fill">
                                                        <i class="fas fa-search"></i>Filter
                                                    </button>
                                                    <a href="logs.php" class="tf-button style-1 type-outline">
                                                        <i class="fas fa-times"></i>Clear
                                                    </a>
                                                </form>
                                            </div>

                                            <!-- Logs Table -->
                                            <?php if (!empty($logList)): ?>
                                                <div class="wg-box">
                                                    <div class="flex items-center justify-between mb-20">
                                                        <div class="body-title">Activity Logs</div>
                                                        <div class="body-text">Showing <?php echo count($logList); ?> of <?php echo $filteredLogCount; ?> logs</div>
                                                    </div>
                                                    <div class="wg-table table-all-user">
                                                        <div class="table-responsive">
                                                            <table class="table table-striped table-bordered">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Date/Time</th>
                                                                        <th>User</th>
                                                                        <th>Action</th>
                                                                        <th>Table</th>
                                                                        <th>Record ID</th>
                                                                        <th>IP Address</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($logList as $log): ?>
                                                                        <tr>
                                                                            <td>
                                                                                <div class="body-title"><?php echo date('M j, Y', strtotime($log['created_at'])); ?></div>
                                                                                <div class="text-tiny"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                                                                            </td>
                                                                            <td>
                                                                                <div class="flex items-center gap8">
                                                                                    <i class="fas fa-user-circle" style="color: #C388F7;"></i>
                                                                                    <div>
                                                                                        <div class="body-title-2"><?php echo htmlspecialchars($log['full_name'] ?? 'System'); ?></div>
                                                                                        <div class="text-tiny">@<?php echo htmlspecialchars($log['username'] ?? 'system'); ?></div>
                                                                                    </div>
                                                                                </div>
                                                                            </td>
                                                                            <td>
                                                                                <?php
                                                                                $actionClass = 'action-default';
                                                                                if (strpos($log['action'], 'create') !== false) $actionClass = 'action-create';
                                                                                elseif (strpos($log['action'], 'update') !== false) $actionClass = 'action-update';
                                                                                elseif (strpos($log['action'], 'delete') !== false) $actionClass = 'action-delete';
                                                                                elseif (strpos($log['action'], 'login') !== false) $actionClass = 'action-login';
                                                                                elseif (strpos($log['action'], 'logout') !== false) $actionClass = 'action-logout';
                                                                                ?>
                                                                                <span class="log-action <?php echo $actionClass; ?>">
                                                                                    <?php echo htmlspecialchars($log['action']); ?>
                                                                                </span>
                                                                            </td>
                                                                            <td><?php echo htmlspecialchars($log['table_name'] ?? '-'); ?></td>
                                                                            <td><?php echo $log['record_id'] ? '#' . $log['record_id'] : '-'; ?></td>
                                                                            <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="wg-box text-center p-40">
                                                    <i class="fas fa-list-alt" style="font-size: 48px; color: #ddd; margin-bottom: 16px;"></i>
                                                    <h5>No Logs Found</h5>
                                                    <p class="text-muted">No activity logs match your current filters.</p>
                                                    <a href="logs.php" class="tf-button style-1 type-fill">
                                                        <i class="fas fa-refresh"></i>Clear Filters
                                                    </a>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Pagination -->
                                            <?php if ($totalPages > 1): ?>
                                                <div class="flex items-center justify-between mt-32">
                                                    <div class="body-text">
                                                        Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>
                                                        (<?php echo $filteredLogCount; ?> total logs)
                                                    </div>
                                                    <div class="wg-pagination">
                                                        <?php if ($page > 1): ?>
                                                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action); ?>&date=<?php echo urlencode($date); ?>" class="pagination-item">
                                                                <i class="icon-chevron-left"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action); ?>&date=<?php echo urlencode($date); ?>"
                                                                class="pagination-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        <?php endfor; ?>

                                                        <?php if ($page < $totalPages): ?>
                                                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($action); ?>&date=<?php echo urlencode($date); ?>" class="pagination-item">
                                                                <i class="icon-chevron-right"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete All Logs Modal -->
    <div class="modal fade" id="deleteAllModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete All Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong>all activity logs</strong>?</p>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This action cannot be undone. All log entries will be permanently deleted.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="tf-button style-1 type-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_all_logs" class="tf-button style-1 type-fill" style="background: #dc3545; border-color: #dc3545;">
                            <i class="fas fa-trash"></i>Delete All Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/vendor/js/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="assets/vendor/js/main.js"></script>

    <script>
        function openDeleteAllModal() {
            $('#deleteAllModal').modal('show');
        }

        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.location.href = 'logs.php?' + params.toString();
        }
    </script>
</body>

</html>