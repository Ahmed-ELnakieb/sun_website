<?php

/**
 * Sun Trading Company - User Management
 * Custom Admin System - Developed by Elnakieb
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$auth->requireAdmin(); // User management requires admin privileges

$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();
$db = Database::getInstance();

$success = '';
$error = '';

// Handle user operations
if ($_POST && isset($_POST['save_user'])) {
    try {
        // Validate required fields
        if (empty($_POST['username']) || empty($_POST['email']) || empty($_POST['full_name']) || empty($_POST['password'])) {
            $error = 'All fields are required.';
        } else {
            // Check if username or email already exists
            $existingUser = $db->fetchOne("SELECT id FROM admin_users WHERE username = :username OR email = :email", [
                'username' => sanitize($_POST['username']),
                'email' => sanitize($_POST['email'])
            ]);

            if ($existingUser) {
                $error = 'Username or email already exists.';
            } else {
                $data = [
                    'username' => sanitize($_POST['username']),
                    'email' => sanitize($_POST['email']),
                    'full_name' => sanitize($_POST['full_name']),
                    'password_hash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                    'role' => sanitize($_POST['role']),
                    'is_active' => 1
                ];

                $userId = $db->insert('admin_users', $data);
                if ($userId) {
                    $success = 'User created successfully!';
                    $db->insert('activity_logs', [
                        'user_id' => $currentUser['id'],
                        'action' => 'user_create',
                        'table_name' => 'admin_users',
                        'record_id' => $userId,
                        'new_values' => json_encode($data),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                    ]);
                } else {
                    $error = 'Failed to create user. Database error.';
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Error creating user: ' . $e->getMessage();
    }
}

// Handle user update
if ($_POST && isset($_POST['update_user'])) {
    try {
        $id = (int)$_POST['user_id'];
        if (empty($id)) {
            $error = 'Invalid user ID.';
        } else {
            $oldUser = $db->fetchOne("SELECT * FROM admin_users WHERE id = :id", ['id' => $id]);

            if (!$oldUser) {
                $error = 'User not found.';
            } else {
                $data = [
                    'username' => sanitize($_POST['username']),
                    'email' => sanitize($_POST['email']),
                    'full_name' => sanitize($_POST['full_name']),
                    'role' => sanitize($_POST['role']),
                    'is_active' => (int)$_POST['is_active']
                ];

                // Only update password if provided
                if (!empty($_POST['password'])) {
                    $data['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }

                $result = $db->update('admin_users', $data, 'id = :id', ['id' => $id]);
                if ($result) {
                    $success = 'User updated successfully!';
                    $db->insert('activity_logs', [
                        'user_id' => $currentUser['id'],
                        'action' => 'user_update',
                        'table_name' => 'admin_users',
                        'record_id' => $id,
                        'old_values' => json_encode($oldUser),
                        'new_values' => json_encode($data),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                    ]);
                } else {
                    $error = 'Failed to update user.';
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Error updating user: ' . $e->getMessage();
    }
}

// Handle user deletion
if ($_POST && isset($_POST['delete_user'])) {
    try {
        $id = (int)$_POST['user_id'];
        if (empty($id)) {
            $error = 'Invalid user ID.';
        } else {
            $userToDelete = $db->fetchOne("SELECT * FROM admin_users WHERE id = :id", ['id' => $id]);

            if (!$userToDelete) {
                $error = 'User not found.';
            } else {
                $result = $db->delete('admin_users', 'id = :id', ['id' => $id]);
                if ($result) {
                    $success = 'User deleted successfully!';
                    $db->insert('activity_logs', [
                        'user_id' => $currentUser['id'],
                        'action' => 'user_delete',
                        'table_name' => 'admin_users',
                        'record_id' => $id,
                        'old_values' => json_encode($userToDelete),
                        'new_values' => null,
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                    ]);
                } else {
                    $error = 'Failed to delete user.';
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Error deleting user: ' . $e->getMessage();
    }
}

// Handle toggle active status
if ($_POST && isset($_POST['toggle_status'])) {
    try {
        $id = (int)$_POST['user_id'];
        $currentStatus = (int)$_POST['current_status'];
        $newStatus = $currentStatus ? 0 : 1;

        if (empty($id)) {
            $error = 'Invalid user ID.';
        } else {
            $result = $db->update('admin_users', ['is_active' => $newStatus], 'id = :id', ['id' => $id]);
            if ($result) {
                $success = 'User status updated successfully!';
                $db->insert('activity_logs', [
                    'user_id' => $currentUser['id'],
                    'action' => 'user_status_toggle',
                    'table_name' => 'admin_users',
                    'record_id' => $id,
                    'old_values' => json_encode(['is_active' => $currentStatus]),
                    'new_values' => json_encode(['is_active' => $newStatus]),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                ]);
            } else {
                $error = 'Failed to update user status.';
            }
        }
    } catch (Exception $e) {
        $error = 'Error updating user status: ' . $e->getMessage();
    }
}

// Get user statistics
$stats = [];
$stats['total_users'] = $db->fetchOne("SELECT COUNT(*) as count FROM admin_users")['count'] ?? 0;
$stats['active_users'] = $db->fetchOne("SELECT COUNT(*) as count FROM admin_users WHERE is_active = 1")['count'] ?? 0;
$stats['admin_users'] = $db->fetchOne("SELECT COUNT(*) as count FROM admin_users WHERE role = 'admin'")['count'] ?? 0;
$stats['recent_users'] = $db->fetchOne("SELECT COUNT(*) as count FROM admin_users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['count'] ?? 0;

// Get users with filters
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 15;
$offset = ($page - 1) * $limit;

$whereConditions = [];
$params = [];

if ($role) {
    $whereConditions[] = "role = :role";
    $params['role'] = $role;
}
if ($status !== '') {
    $whereConditions[] = "is_active = :status";
    $params['status'] = (int)$status;
}
if ($search) {
    $whereConditions[] = "(username LIKE :search OR email LIKE :search2 OR full_name LIKE :search3)";
    $searchTerm = "%{$search}%";
    $params['search'] = $searchTerm;
    $params['search2'] = $searchTerm;
    $params['search3'] = $searchTerm;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
$countSql = "SELECT COUNT(*) as total FROM admin_users {$whereClause}";
$result = $db->fetchOne($countSql, $params);
$filteredUserCount = $result['total'] ?? 0;
$totalPages = ceil($filteredUserCount / $limit);

$sql = "SELECT * FROM admin_users {$whereClause} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}";
$userList = $db->fetchAll($sql, $params);

$flashMessages = getFlashMessages();
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US">

<head>
    <meta charset="utf-8">
    <title>User Management - Sun Trading Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/bootstrap.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/styles.css">
    <link rel="stylesheet" href="assets/vendor/font/fonts.css">
    <link rel="stylesheet" href="assets/vendor/icon/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="shortcut icon" href="assets/vendor/images/favicon.png">
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
                                                <a href="users.php" class="active">
                                                    <div class="text">Users</div>
                                                </a>
                                            </li>
                                            <li class="sub-menu-item">
                                                <a href="backup.php" class="">
                                                    <div class="text">Backup</div>
                                                </a>
                                            </li>
                                            <li class="sub-menu-item">
                                                <a href="logs.php" class="">
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
                                <i class="fas fa-users" style="font-size: 48px; color: #C0FAA0;"></i>
                            </div>
                            <div class="content">
                                <p class="f12-regular text-White">User Management</p>
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
                                <h6>User Management</h6>
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
                                                    <h3>User Management</h3>
                                                    <div class="body-text mt-8">Manage system users and permissions</div>
                                                </div>
                                                <div class="flex gap16">
                                                    <button class="tf-button style-1 type-fill" onclick="openAddUserModal()">
                                                        <i class="icon-plus"></i>Add User
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Statistics Cards -->
                                            <div class="flex gap16 flex-wrap mb-32">
                                                <div class="wg-card style-1 bg-Primary" style="min-width: 200px; flex: 1; padding: 16px;">
                                                    <div class="flex items-center gap12">
                                                        <div class="icon">
                                                            <i class="fas fa-users" style="font-size: 20px; color: #fff; background: rgba(255,255,255,0.1); padding: 8px; border-radius: 6px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <h6 class="counter text-White mb-0">
                                                                <span class="number"><?php echo $stats['total_users']; ?></span>
                                                            </h6>
                                                            <div class="f12-medium text-White">Total Users</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="wg-card" style="min-width: 200px; flex: 1; padding: 16px;">
                                                    <div class="flex items-center gap12">
                                                        <div class="icon">
                                                            <i class="fas fa-check-circle" style="font-size: 20px; color: #161326; background: #C0FAA0; padding: 8px; border-radius: 6px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <h6 class="counter mb-0">
                                                                <span class="number"><?php echo $stats['active_users']; ?></span>
                                                            </h6>
                                                            <div class="f12-medium">Active Users</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="wg-card" style="min-width: 200px; flex: 1; padding: 16px;">
                                                    <div class="flex items-center gap12">
                                                        <div class="icon">
                                                            <i class="fas fa-crown" style="font-size: 20px; color: #fff; background: #C388F7; padding: 8px; border-radius: 6px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <h6 class="counter mb-0">
                                                                <span class="number"><?php echo $stats['admin_users']; ?></span>
                                                            </h6>
                                                            <div class="f12-medium">Admins</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="wg-card style-1 bg-YellowGreen" style="min-width: 200px; flex: 1; padding: 16px;">
                                                    <div class="flex items-center gap12">
                                                        <div class="icon">
                                                            <i class="fas fa-clock" style="font-size: 20px; color: #fff; background: #161326; padding: 8px; border-radius: 6px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <h6 class="counter mb-0">
                                                                <span class="number"><?php echo $stats['recent_users']; ?></span>
                                                            </h6>
                                                            <div class="f12-medium">Recent (7d)</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Search and Filters -->
                                            <div class="wg-box mb-24">
                                                <div class="flex items-center justify-between mb-20">
                                                    <div class="body-title">Filter Users</div>
                                                </div>
                                                <form method="GET" class="flex gap16 flex-wrap">
                                                    <fieldset style="flex: 1; min-width: 200px;">
                                                        <input type="text" class="tf-input style-1" name="search" id="userSearch"
                                                            value="<?php echo htmlspecialchars($search); ?>"
                                                            placeholder="Search users...">
                                                    </fieldset>
                                                    <fieldset style="flex: 1; min-width: 150px;">
                                                        <select class="tf-input style-1" name="role" id="roleFilter">
                                                            <option value="">All Roles</option>
                                                            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                            <option value="editor" <?php echo $role === 'editor' ? 'selected' : ''; ?>>Editor</option>
                                                        </select>
                                                    </fieldset>
                                                    <fieldset style="flex: 1; min-width: 150px;">
                                                        <select class="tf-input style-1" name="status" id="statusFilter">
                                                            <option value="">All Status</option>
                                                            <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Active</option>
                                                            <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Inactive</option>
                                                        </select>
                                                    </fieldset>
                                                    <button type="submit" class="tf-button style-1 type-fill">
                                                        <i class="fas fa-search"></i>Filter
                                                    </button>
                                                    <a href="users.php" class="tf-button style-1 type-outline">
                                                        <i class="fas fa-times"></i>Clear
                                                    </a>
                                                </form>
                                            </div>

                                            <!-- Users Table -->
                                            <?php if (!empty($userList)): ?>
                                                <div class="wg-box">
                                                    <div class="flex items-center justify-between mb-20">
                                                        <div class="body-title">System Users</div>
                                                        <div class="body-text">Total: <?php echo count($userList); ?> users</div>
                                                    </div>
                                                    <div class="wg-table table-all-user">
                                                        <div class="table-responsive">
                                                            <table class="table table-striped table-bordered">
                                                                <thead>
                                                                    <tr>
                                                                        <th>User</th>
                                                                        <th>Email</th>
                                                                        <th>Role</th>
                                                                        <th>Status</th>
                                                                        <th>Actions</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($userList as $user): ?>
                                                                        <tr>
                                                                            <td>
                                                                                <div class="flex items-center gap12">
                                                                                    <div class="image">
                                                                                        <i class="fas fa-user-circle" style="font-size: 32px; color: #C388F7;"></i>
                                                                                    </div>
                                                                                    <div class="content">
                                                                                        <div class="body-title"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                                                        <div class="text-tiny">@<?php echo htmlspecialchars($user['username']); ?></div>
                                                                                    </div>
                                                                                </div>
                                                                            </td>
                                                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                                            <td>
                                                                                <span style="background: rgba(195, 136, 247, 0.1); color: #7B3BE0; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                                                                    <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                                                                </span>
                                                                            </td>
                                                                            <td>
                                                                                <span style="padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; <?php echo $user['is_active'] ? 'background: rgba(192, 250, 160, 0.2); color: #161326;' : 'background: rgba(220, 53, 69, 0.1); color: #dc3545;'; ?>">
                                                                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                                </span>
                                                                            </td>
                                                                            <td>
                                                                                <div class="list-icon-function">
                                                                                    <button class="item edit" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit">
                                                                                        <i class="fas fa-edit"></i>
                                                                                    </button>
                                                                                    <button class="item toggle" onclick="toggleUserStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active']; ?>)" title="Toggle Status">
                                                                                        <i class="fas fa-toggle-<?php echo $user['is_active'] ? 'on' : 'off'; ?>"></i>
                                                                                    </button>
                                                                                    <button class="item delete" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Delete">
                                                                                        <i class="fas fa-trash"></i>
                                                                                    </button>
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="wg-box text-center p-40">
                                                    <i class="fas fa-users" style="font-size: 48px; color: #ddd; margin-bottom: 16px;"></i>
                                                    <h5>No Users Found</h5>
                                                    <p class="text-muted">Start by adding your first user.</p>
                                                    <button class="tf-button style-1 type-fill" onclick="openAddUserModal()">
                                                        <i class="icon-plus"></i>Add User
                                                    </button>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Pagination -->
                                            <?php if ($totalPages > 1): ?>
                                                <div class="flex items-center justify-between mt-32">
                                                    <div class="body-text">
                                                        Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>
                                                        (<?php echo $filteredUserCount; ?> total users)
                                                        <?php if ($search || $role || $status !== ''): ?>
                                                            <span style="color: #7B3BE0;">- Filtered results</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="wg-pagination">
                                                        <?php if ($page > 1): ?>
                                                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>&status=<?php echo urlencode($status); ?>" class="pagination-item">
                                                                <i class="icon-chevron-left"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>&status=<?php echo urlencode($status); ?>"
                                                                class="pagination-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        <?php endfor; ?>

                                                        <?php if ($page < $totalPages): ?>
                                                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role); ?>&status=<?php echo urlencode($status); ?>" class="pagination-item">
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

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-16">
                            <label class="body-title-2 mb-8">Username</label>
                            <input type="text" class="tf-input style-1" name="username" required>
                        </div>
                        <div class="mb-16">
                            <label class="body-title-2 mb-8">Full Name</label>
                            <input type="text" class="tf-input style-1" name="full_name" required>
                        </div>
                        <div class="mb-16">
                            <label class="body-title-2 mb-8">Email</label>
                            <input type="email" class="tf-input style-1" name="email" required>
                        </div>
                        <div class="mb-16">
                            <label class="body-title-2 mb-8">Password</label>
                            <input type="password" class="tf-input style-1" name="password" required>
                        </div>
                        <div class="mb-16">
                            <label class="body-title-2 mb-8">Role</label>
                            <select class="tf-input style-1" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="editor">Editor</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="tf-button style-1 type-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_user" class="tf-button style-1 type-fill">
                            <i class="fas fa-save"></i>Save User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-body">
                        <div class="mb-16">
                            <label class="body-title-2 mb-8">Username</label>
                            <input type="text" class="tf-input style-1" name="username" id="edit_username" required>
                        </div>
                        <div class="mb-16">
                            <label class="body-title-2 mb-8">Full Name</label>
                            <input type="text" class="tf-input style-1" name="full_name" id="edit_full_name" required>
                        </div>
                        <div class="mb-16">
                            <label class="body-title-2 mb-8">Email</label>
                            <input type="email" class="tf-input style-1" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-16">
                            <label class="body-title-2 mb-8">Password (leave blank to keep current)</label>
                            <input type="password" class="tf-input style-1" name="password" id="edit_password">
                        </div>
                        <div class="mb-16">
                            <label class="body-title-2 mb-8">Role</label>
                            <select class="tf-input style-1" name="role" id="edit_role" required>
                                <option value="admin">Admin</option>
                                <option value="editor">Editor</option>
                            </select>
                        </div>
                        <div class="mb-16">
                            <label class="body-title-2 mb-8">Status</label>
                            <select class="tf-input style-1" name="is_active" id="edit_is_active" required>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="tf-button style-1 type-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_user" class="tf-button style-1 type-fill">
                            <i class="fas fa-save"></i>Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <div class="modal-body">
                        <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                        <div class="alert alert-warning">
                            <strong>Warning:</strong> This will permanently delete the user account.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="tf-button style-1 type-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_user" class="tf-button style-1 type-fill" style="background: #dc3545; border-color: #dc3545;">
                            <i class="fas fa-trash"></i>Delete User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toggle Status Form -->
    <form method="POST" id="toggleStatusForm" style="display: none;">
        <input type="hidden" name="user_id" id="toggle_user_id">
        <input type="hidden" name="current_status" id="toggle_current_status">
        <input type="hidden" name="toggle_status" value="1">
    </form>

    <script src="../critso/js/jquery.min.js"></script>
    <script src="../critso/js/bootstrap.min.js"></script>
    <script src="../critso/js/main.js"></script>

    <script>
        // Open Add User Modal
        function openAddUserModal() {
            $('#addUserModal').modal('show');
        }

        // Edit User
        function editUser(userId) {
            // Find user data from the table row
            const userRow = $('button[onclick="editUser(' + userId + ')"]').closest('tr');

            // Extract user data from table cells
            const fullName = userRow.find('td:eq(0) .body-title').text().trim();
            const username = userRow.find('td:eq(0) .text-tiny').text().replace('@', '').trim();
            const email = userRow.find('td:eq(1)').text().trim();
            const role = userRow.find('td:eq(2) span').text().toLowerCase().trim();
            const statusText = userRow.find('td:eq(3) span').text().toLowerCase().trim();
            const isActive = statusText === 'active' ? 1 : 0;

            // Populate edit form
            $('#edit_user_id').val(userId);
            $('#edit_username').val(username);
            $('#edit_full_name').val(fullName);
            $('#edit_email').val(email);
            $('#edit_role').val(role);
            $('#edit_is_active').val(isActive);
            $('#edit_password').val(''); // Always empty for security

            // Show modal
            $('#editUserModal').modal('show');
        }

        // Delete User
        function deleteUser(userId) {
            $('#delete_user_id').val(userId);
            $('#deleteUserModal').modal('show');
        }

        // Toggle User Status
        function toggleUserStatus(userId, currentStatus) {
            if (confirm('Are you sure you want to change this user\'s status?')) {
                $('#toggle_user_id').val(userId);
                $('#toggle_current_status').val(currentStatus);
                $('#toggleStatusForm').submit();
            }
        }

        // Add search functionality
        function filterUsers() {
            const search = $('#userSearch').val().toLowerCase();
            const role = $('#roleFilter').val();
            const status = $('#statusFilter').val();

            $('tbody tr').each(function() {
                const row = $(this);
                const username = row.find('td:eq(0) .text-tiny').text().toLowerCase();
                const fullName = row.find('td:eq(0) .body-title').text().toLowerCase();
                const email = row.find('td:eq(1)').text().toLowerCase();
                const userRole = row.find('td:eq(2) span').text().toLowerCase();
                const userStatus = row.find('td:eq(3) span').text().toLowerCase();

                let showRow = true;

                // Search filter
                if (search && !username.includes(search) && !fullName.includes(search) && !email.includes(search)) {
                    showRow = false;
                }

                // Role filter
                if (role && userRole !== role.toLowerCase()) {
                    showRow = false;
                }

                // Status filter
                if (status !== '' && ((status === '1' && userStatus !== 'active') || (status === '0' && userStatus !== 'inactive'))) {
                    showRow = false;
                }

                row.toggle(showRow);
            });
        }

        // Bind search events
        $(document).ready(function() {
            $('#userSearch, #roleFilter, #statusFilter').on('input change', filterUsers);
        });
    </script>
</body>

</html>