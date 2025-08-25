<?php

/**
 * Sun Trading Company - Website Content Management
 * Based on Critso Template Structure
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$auth->requireAuth();

$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();
$db = Database::getInstance();

// Handle actions
$action = $_GET['action'] ?? 'content';
$success = '';
$error = '';

// Handle content creation
if ($_POST && isset($_POST['save_content'])) {
    $data = [
        'content_key' => sanitize($_POST['content_key']),
        'title_ar' => sanitize($_POST['title_ar']),
        'title_en' => sanitize($_POST['title_en']),
        'content_ar' => sanitize($_POST['content_ar']),
        'content_en' => sanitize($_POST['content_en']),
        'page_section' => sanitize($_POST['page_section']),
        'is_active' => 1
    ];

    $contentId = $db->insert('website_content', $data);
    if ($contentId) {
        $success = 'Content created successfully!';

        // Log the content creation activity
        $db->insert('activity_logs', [
            'user_id' => $currentUser['id'],
            'action' => 'content_create',
            'table_name' => 'website_content',
            'record_id' => $contentId,
            'old_values' => null,
            'new_values' => json_encode($data),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } else {
        $error = 'Failed to create content.';
    }
}

// Handle content update
if ($_POST && isset($_POST['update_content'])) {
    $id = (int)$_POST['content_id'];
    $oldContent = $db->fetchOne("SELECT * FROM website_content WHERE id = :id", ['id' => $id]);

    $data = [
        // content_key is intentionally excluded from updates to prevent frontend connection breakage
        'title_ar' => sanitize($_POST['title_ar']),
        'title_en' => sanitize($_POST['title_en']),
        'content_ar' => sanitize($_POST['content_ar']),
        'content_en' => sanitize($_POST['content_en']),
        'page_section' => sanitize($_POST['page_section'])
    ];

    if ($db->update('website_content', $data, 'id = :id', ['id' => $id])) {
        $success = 'Content updated successfully!';

        // Log the content update activity
        $db->insert('activity_logs', [
            'user_id' => $currentUser['id'],
            'action' => 'content_update',
            'table_name' => 'website_content',
            'record_id' => $id,
            'old_values' => json_encode($oldContent),
            'new_values' => json_encode($data),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } else {
        $error = 'Failed to update content.';
    }
}

// Handle content deletion
if ($_POST && isset($_POST['delete_content'])) {
    $id = (int)$_POST['content_id'];
    $contentToDelete = $db->fetchOne("SELECT * FROM website_content WHERE id = :id", ['id' => $id]);

    if ($contentToDelete && $db->delete('website_content', 'id = :id', ['id' => $id])) {
        $success = 'Content deleted successfully!';

        // Log the content deletion activity
        $db->insert('activity_logs', [
            'user_id' => $currentUser['id'],
            'action' => 'content_delete',
            'table_name' => 'website_content',
            'record_id' => $id,
            'old_values' => json_encode($contentToDelete),
            'new_values' => null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } else {
        $error = 'Failed to delete content.';
    }
}

// Handle toggle active status
if ($_POST && isset($_POST['toggle_status'])) {
    $id = (int)$_POST['content_id'];
    $currentStatus = (int)$_POST['current_status'];
    $newStatus = $currentStatus ? 0 : 1;

    if ($db->update('website_content', ['is_active' => $newStatus], 'id = :id', ['id' => $id])) {
        $success = 'Content status updated successfully!';
    } else {
        $error = 'Failed to update content status.';
    }
}

// Get content statistics
$stats = [];
$stats['total_content'] = $db->fetchOne("SELECT COUNT(*) as count FROM website_content")['count'] ?? 0;
$stats['active_content'] = $db->fetchOne("SELECT COUNT(*) as count FROM website_content WHERE is_active = 1")['count'] ?? 0;
$stats['recent_content'] = $db->fetchOne("SELECT COUNT(*) as count FROM website_content WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['count'] ?? 0;
$stats['sections'] = $db->fetchOne("SELECT COUNT(DISTINCT page_section) as count FROM website_content WHERE page_section IS NOT NULL AND page_section != ''")['count'] ?? 0;

// Initialize search parameters
$search = $_GET['search'] ?? '';
$section = $_GET['section'] ?? '';
$status = $_GET['status'] ?? '';
$view_mode = $_GET['view'] ?? 'cards';

// Get content with pagination and filters
$page = (int)($_GET['page'] ?? 1);
$limit = $view_mode === 'table' ? 15 : 12;
$offset = ($page - 1) * $limit;

$whereConditions = [];
$params = [];

if ($section) {
    $whereConditions[] = "page_section = :section";
    $params['section'] = $section;
}

if ($status !== '') {
    $whereConditions[] = "is_active = :status";
    $params['status'] = (int)$status;
}

if ($search) {
    $whereConditions[] = "(content_key LIKE :search1 OR title_ar LIKE :search2 OR title_en LIKE :search3 OR content_ar LIKE :search4 OR content_en LIKE :search5)";
    $searchTerm = "%{$search}%";
    $params['search1'] = $searchTerm;
    $params['search2'] = $searchTerm;
    $params['search3'] = $searchTerm;
    $params['search4'] = $searchTerm;
    $params['search5'] = $searchTerm;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM website_content {$whereClause}";
$result = $db->fetchOne($countSql, $params);
$filteredContentCount = $result['total'] ?? 0;
$totalPages = ceil($filteredContentCount / $limit);

// Debug output (temporary)
if ($search) {
    echo "<!-- DEBUG: Search='$search', SQL='$countSql', Params=" . print_r($params, true) . " Result=" . print_r($result, true) . " Count=$filteredContentCount TotalPages=$totalPages -->";
}

// Get content
$sql = "SELECT wc.* 
        FROM website_content wc 
        {$whereClause} 
        ORDER BY wc.page_section, wc.content_key 
        LIMIT {$limit} OFFSET {$offset}";

$contentList = $db->fetchAll($sql, $params);

// Group content by section for cards view
$contentBySection = [];
foreach ($contentList as $item) {
    $contentBySection[$item['page_section']][] = $item;
}

// Get sections for filter
$sections = $db->fetchAll("SELECT DISTINCT page_section FROM website_content WHERE page_section IS NOT NULL AND page_section != '' ORDER BY page_section");

// Flash messages
$flashMessages = getFlashMessages();
?>
<!DOCTYPE html>
<!--[if IE 8 ]><html class="ie" xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!-->
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US">
<!--<![endif]-->

<head>
    <!-- Basic Page Needs -->
    <meta charset="utf-8">
    <!--[if IE]><meta http-equiv='X-UA-Compatible' content='IE=edge,chrome=1'><![endif]-->
    <title>Website Content - Sun Trading Admin Panel</title>

    <meta name="author" content="Sun Trading Company">

    <!-- Mobile Specific Metas -->
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

    <!-- Theme Style -->
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/animation.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/bootstrap.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/bootstrap-select.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/styles.css">

    <!-- Font -->
    <link rel="stylesheet" href="assets/vendor/font/fonts.css">

    <!-- Icon -->
    <link rel="stylesheet" href="assets/vendor/icon/style.css">

    <!-- Font Awesome for Admin Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Admin Custom CSS -->
    <link rel="stylesheet" type="text/css" href="assets/css/admin-critso.css">

    <!-- Favicon and Touch Icons  -->
    <link rel="shortcut icon" href="assets/vendor/images/favicon.png">
    <link rel="apple-touch-icon-precomposed" href="assets/vendor/images/favicon.png">

    <style>
        .content-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            border: 1px solid #eee;
            border-radius: 12px;
            overflow: hidden;
        }

        .content-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .content-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 6px 12px;
            color: #333;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
            flex: 1;
            justify-content: center;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .action-btn.edit {
            background: rgba(195, 136, 247, 0.1);
            border-color: #C388F7;
            color: #7B3BE0;
        }

        .action-btn.edit:hover {
            background: rgba(195, 136, 247, 0.2);
        }

        .action-btn.delete {
            background: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
            color: #dc3545;
        }

        .action-btn.delete:hover {
            background: rgba(220, 53, 69, 0.2);
        }

        .action-btn.toggle {
            background: rgba(192, 250, 160, 0.1);
            border-color: #C0FAA0;
            color: #161326;
        }

        .action-btn.toggle:hover {
            background: rgba(192, 250, 160, 0.2);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: rgba(192, 250, 160, 0.2);
            color: #161326;
        }

        .status-inactive {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .section-label {
            background: rgba(195, 136, 247, 0.1);
            color: #7B3BE0;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .modal {
            z-index: 9999;
        }

        .modal-backdrop {
            z-index: 9998;
        }

        .language-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .language-tab {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .language-tab.active {
            background: #C388F7;
            color: white;
            border-color: #C388F7;
        }

        .language-content {
            display: none;
        }

        .language-content.active {
            display: block;
        }
    </style>

</head>

<body class="counter-scroll">

    <!-- #wrapper -->
    <div id="wrapper">
        <!-- #page -->
        <div id="page" class="">
            <!-- layout-wrap -->
            <div class="layout-wrap loader-off">
                <!-- preload -->
                <div id="preload" class="preload-container">
                    <div class="preloading">
                        <span></span>
                    </div>
                </div>
                <!-- /preload -->
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
                                            <div class="icon">
                                                <i class="fas fa-tachometer-alt"></i>
                                            </div>
                                            <div class="text">Dashboard</div>
                                        </a>
                                    </li>
                                    <li class="menu-item has-children">
                                        <a href="javascript:void(0);" class="menu-item-button">
                                            <div class="icon">
                                                <i class="fas fa-box"></i>
                                            </div>
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
                                        <a href="content.php" class="menu-item-button active">
                                            <div class="icon">
                                                <i class="fas fa-edit"></i>
                                            </div>
                                            <div class="text">Content</div>
                                        </a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="settings.php" class="menu-item-button">
                                            <div class="icon">
                                                <i class="fas fa-cog"></i>
                                            </div>
                                            <div class="text">Settings</div>
                                        </a>
                                    </li>
                                    <?php if ($isAdmin): ?>
                                        <li class="menu-item has-children">
                                            <a href="javascript:void(0);" class="menu-item-button">
                                                <div class="icon">
                                                    <i class="fas fa-tools"></i>
                                                </div>
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
                                                    <a href="logs.php" class="">
                                                        <div class="text">Logs</div>
                                                    </a>
                                                </li>
                                            </ul>
                                        </li>
                                    <?php endif; ?>
                                    <li class="menu-item">
                                        <a href="javascript:void(0);" onclick="window.open('../', '_blank');" class="menu-item-button">
                                            <div class="icon">
                                                <i class="fas fa-external-link-alt"></i>
                                            </div>
                                            <div class="text">View Website</div>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="bottom">
                            <div class="image">
                                <i class="fas fa-edit" style="font-size: 48px; color: #C0FAA0;"></i>
                            </div>
                            <div class="content">
                                <p class="f12-regular text-White">Content Management</p>
                                <p class="f12-bold text-White">Developed by <a href="mailto:ahmedelnakieb95@gmail.com" style="color: #C0FAA0; text-decoration: none;">Elnakieb</a></p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /section-menu-left -->
                <!-- section-content-right -->
                <div class="section-content-right">
                    <!-- header-dashboard -->
                    <div class="header-dashboard">
                        <div class="wrap">
                            <div class="header-left">
                                <div class="button-show-hide">
                                    <i class="icon-menu"></i>
                                </div>
                                <h6>Website Content</h6>
                                <form class="form-search flex-grow" method="GET">
                                    <fieldset class="name">
                                        <input type="text" placeholder="Search content..." class="show-search style-1" name="search" tabindex="2" value="<?php echo htmlspecialchars($search ?? ''); ?>" aria-required="true">
                                        <input type="hidden" name="section" value="<?php echo htmlspecialchars($section ?? ''); ?>">
                                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status ?? ''); ?>">
                                        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode ?? 'cards'); ?>">
                                    </fieldset>
                                    <div class="button-submit">
                                        <button class="" type="submit"><i class="icon-search-normal1"></i></button>
                                    </div>
                                </form>
                            </div>
                            <div class="header-grid">
                                <div class="header-btn">
                                    <div class="popup-wrap message type-header">
                                        <div class="dropdown">
                                            <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="header-item">
                                                    <i class="icon-sms"></i>
                                                </span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end has-content" aria-labelledby="dropdownMenuButton1">
                                                <li>
                                                    <h6>Messages</h6>
                                                </li>
                                                <li>
                                                    <div class="message-item w-full wg-user active">
                                                        <div class="image">
                                                            <i class="fas fa-user-circle" style="font-size: 40px; color: #C388F7;"></i>
                                                        </div>
                                                        <div class="flex-grow">
                                                            <div class="flex items-center justify-between">
                                                                <a href="#" class="body-title name">Content Manager</a>
                                                                <div class="time">Now</div>
                                                            </div>
                                                            <div class="text-tiny desc">Content management is ready!</div>
                                                        </div>
                                                    </div>
                                                </li>
                                                <li>
                                                    <a href="logs.php" class="tf-button style-1 f12-bold w-100">
                                                        View All
                                                        <i class="icon icon-send"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="popup-wrap noti type-header">
                                        <div class="dropdown">
                                            <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="header-item">
                                                    <i class="icon-notification1"></i>
                                                </span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end has-content" aria-labelledby="dropdownMenuButton2">
                                                <li>
                                                    <h6>Notifications</h6>
                                                </li>
                                                <li>
                                                    <div class="notifications-item item-1">
                                                        <div class="image">
                                                            <i class="icon-edit"></i>
                                                        </div>
                                                        <div>
                                                            <div class="body-title-2">Website Content</div>
                                                            <div class="text-tiny">Manage your content</div>
                                                        </div>
                                                    </div>
                                                </li>
                                                <li>
                                                    <a href="logs.php" class="tf-button style-1 f12-bold w-100">
                                                        View All
                                                        <i class="icon icon-send"></i>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="line1"></div>
                                <div class="popup-wrap user type-header">
                                    <div class="dropdown">
                                        <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton3" data-bs-toggle="dropdown" aria-expanded="false">
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
                                        <ul class="dropdown-menu dropdown-menu-end has-content" aria-labelledby="dropdownMenuButton3">
                                            <li>
                                                <a href="profile.php" class="user-item">
                                                    <div class="body-title-2">Profile</div>
                                                </a>
                                            </li>
                                            <li>
                                                <a href="settings.php" class="user-item">
                                                    <div class="body-title-2">Settings</div>
                                                </a>
                                            </li>
                                            <li>
                                                <a href="javascript:void(0);" onclick="window.open('../', '_blank');" class="user-item">
                                                    <div class="body-title-2">View Website</div>
                                                </a>
                                            </li>
                                            <li>
                                                <a href="logout.php" class="user-item">
                                                    <div class="body-title-2">Log out</div>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- /header-dashboard -->
                    <!-- main-content -->
                    <div class="main-content">
                        <!-- main-content-wrap -->
                        <div class="main-content-inner">
                            <!-- Flash Messages -->
                            <?php foreach ($flashMessages as $message): ?>
                                <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert" style="margin: 20px;">
                                    <?php echo htmlspecialchars($message['message']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert" style="margin: 20px;">
                                    <?php echo $success; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin: 20px;">
                                    <?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <!-- main-content-wrap -->
                            <div class="main-content-wrap">
                                <div class="tf-container">
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <!-- Page Header -->
                                            <div class="flex items-center flex-wrap justify-between gap20 mb-32">
                                                <div>
                                                    <h3>Website Content</h3>
                                                    <div class="body-text mt-8">Manage dynamic content for your website</div>
                                                </div>
                                                <div class="flex gap16">
                                                    <button class="tf-button style-1 type-outline" onclick="toggleView()">
                                                        <i class="<?php echo $view_mode === 'cards' ? 'icon-list' : 'icon-th'; ?>"></i>
                                                        <?php echo $view_mode === 'cards' ? 'Table View' : 'Cards View'; ?>
                                                    </button>
                                                    <button class="tf-button style-1 type-fill" onclick="openAddContentModal()">
                                                        <i class="icon-plus"></i>
                                                        Add Content
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Statistics Dashboard & Filters -->
                                            <div class="flex gap24 mb-32 flex-md-row flex-column">
                                                <!-- Statistics Cards -->
                                                <div class="flex gap16 flex-wrap" style="flex: 2;">
                                                    <div class="wg-card style-1 bg-Primary" style="min-width: 200px; flex: 1; padding: 16px;">
                                                        <div class="flex items-center gap12">
                                                            <div class="icon">
                                                                <i class="fas fa-file-alt" style="font-size: 20px; color: #fff; background: rgba(255,255,255,0.1); padding: 8px; border-radius: 6px;"></i>
                                                            </div>
                                                            <div class="content">
                                                                <h6 class="counter text-White mb-0">
                                                                    <span class="number" data-speed="2000" data-to="<?php echo $stats['total_content']; ?>" data-inviewport="yes"><?php echo $stats['total_content']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium text-White">Total Content</div>
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
                                                                    <span class="number" data-speed="2000" data-to="<?php echo $stats['active_content']; ?>" data-inviewport="yes"><?php echo $stats['active_content']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium">Active Content</div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="wg-card" style="min-width: 200px; flex: 1; padding: 16px;">
                                                        <div class="flex items-center gap12">
                                                            <div class="icon">
                                                                <i class="fas fa-clock" style="font-size: 20px; color: #fff; background: #161326; padding: 8px; border-radius: 6px;"></i>
                                                            </div>
                                                            <div class="content">
                                                                <h6 class="counter mb-0">
                                                                    <span class="number" data-speed="2000" data-to="<?php echo $stats['recent_content']; ?>" data-inviewport="yes"><?php echo $stats['recent_content']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium">Recent (7d)</div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="wg-card style-1 bg-YellowGreen" style="min-width: 200px; flex: 1; padding: 16px;">
                                                        <div class="flex items-center gap12">
                                                            <div class="icon">
                                                                <i class="fas fa-layer-group" style="font-size: 20px; color: #fff; background: #161326; padding: 8px; border-radius: 6px;"></i>
                                                            </div>
                                                            <div class="content">
                                                                <h6 class="counter mb-0">
                                                                    <span class="number" data-speed="2000" data-to="<?php echo $stats['sections']; ?>" data-inviewport="yes"><?php echo $stats['sections']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium">Sections</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Content Filters -->
                                                <div class="wg-box style-1 bg-Gainsboro shadow-none widget-tabs" style="flex: 1; min-width: 300px;">
                                                    <div>
                                                        <div class="title mb-16">
                                                            <div class="label-01">Content Filters</div>
                                                        </div>

                                                        <!-- Quick Filters -->
                                                        <div class="mb-16">
                                                            <div class="body-text mb-8">Quick Filters:</div>
                                                            <div class="flex gap8 flex-wrap">
                                                                <a href="content.php" class="tf-button style-1 type-outline" style="font-size: 11px; padding: 4px 8px;">
                                                                    <i class="fas fa-list"></i> All
                                                                </a>
                                                                <a href="content.php?status=1" class="tf-button style-1 <?php echo $status === '1' ? 'type-fill' : 'type-outline'; ?>" style="font-size: 11px; padding: 4px 8px;">
                                                                    <i class="fas fa-check-circle"></i> Active
                                                                </a>
                                                                <a href="content.php?status=0" class="tf-button style-1 <?php echo $status === '0' ? 'type-fill' : 'type-outline'; ?>" style="font-size: 11px; padding: 4px 8px;">
                                                                    <i class="fas fa-times-circle"></i> Inactive
                                                                </a>
                                                                <a href="content.php?section=hero" class="tf-button style-1 <?php echo $section === 'hero' ? 'type-fill' : 'type-outline'; ?>" style="font-size: 11px; padding: 4px 8px;">
                                                                    <i class="fas fa-star"></i> Hero
                                                                </a>
                                                                <a href="content.php?section=about" class="tf-button style-1 <?php echo $section === 'about' ? 'type-fill' : 'type-outline'; ?>" style="font-size: 11px; padding: 4px 8px;">
                                                                    <i class="fas fa-info-circle"></i> About
                                                                </a>
                                                            </div>
                                                        </div>

                                                        <form method="GET" class="flex flex-column gap12">
                                                            <fieldset class="search">
                                                                <input type="text" class="tf-input style-1" name="search"
                                                                    value="<?php echo htmlspecialchars($search); ?>"
                                                                    placeholder="Search content keys, titles...">
                                                            </fieldset>
                                                            <div class="flex gap8">
                                                                <fieldset class="section" style="flex: 1;">
                                                                    <select class="tf-input style-1" name="section">
                                                                        <option value="">All Sections</option>
                                                                        <?php foreach ($sections as $sec): ?>
                                                                            <option value="<?php echo htmlspecialchars($sec['page_section']); ?>"
                                                                                <?php echo $section === $sec['page_section'] ? 'selected' : ''; ?>>
                                                                                <?php echo ucfirst(htmlspecialchars($sec['page_section'])); ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </fieldset>
                                                                <fieldset class="status" style="flex: 1;">
                                                                    <select class="tf-input style-1" name="status">
                                                                        <option value="">All Status</option>
                                                                        <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Active</option>
                                                                        <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Inactive</option>
                                                                    </select>
                                                                </fieldset>
                                                            </div>
                                                            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
                                                            <div class="flex gap8">
                                                                <button type="submit" class="tf-button style-1 type-fill" style="flex: 1;">
                                                                    <i class="fas fa-search"></i>
                                                                    Filter
                                                                </button>
                                                                <a href="content.php" class="tf-button style-1 type-outline" style="flex: 1;">
                                                                    <i class="fas fa-times"></i>
                                                                    Clear
                                                                </a>
                                                            </div>
                                                        </form>

                                                        <!-- Filter Results Info -->
                                                        <?php if ($search || $section || $status !== ''): ?>
                                                            <div class="mt-16 p-12" style="background: rgba(195, 136, 247, 0.1); border-radius: 6px;">
                                                                <div class="body-text" style="font-size: 12px; color: #7B3BE0;">
                                                                    <i class="fas fa-filter"></i> Filtered Results:
                                                                    <strong><?php echo $filteredContentCount; ?></strong> of <?php echo $stats['total_content']; ?> items
                                                                    <?php if ($search): ?>
                                                                        <br><i class="fas fa-search"></i> Search: "<?php echo htmlspecialchars($search); ?>"
                                                                    <?php endif; ?>
                                                                    <?php if ($section): ?>
                                                                        <br><i class="fas fa-layer-group"></i> Section: <?php echo ucfirst(htmlspecialchars($section)); ?>
                                                                    <?php endif; ?>
                                                                    <?php if ($status !== ''): ?>
                                                                        <br><i class="fas fa-toggle-on"></i> Status: <?php echo $status === '1' ? 'Active' : 'Inactive'; ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Content Display -->
                                            <?php if (!empty($contentList)): ?>
                                                <?php if ($view_mode === 'cards'): ?>
                                                    <!-- Cards View by Section -->
                                                    <?php foreach ($contentBySection as $sectionName => $items): ?>
                                                        <div class="wg-box mb-24">
                                                            <div class="flex items-center justify-between mb-20">
                                                                <div class="body-title"><?php echo ucfirst($sectionName); ?> Section</div>
                                                                <div class="body-text"><?php echo count($items); ?> items</div>
                                                            </div>
                                                            <div class="grid-3 gap16">
                                                                <?php foreach ($items as $item): ?>
                                                                    <div class="content-card wg-card p-16">
                                                                        <div class="flex items-center justify-between mb-12">
                                                                            <div class="section-label"><?php echo htmlspecialchars($item['page_section']); ?></div>
                                                                            <div class="status-badge <?php echo $item['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                                                <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                            </div>
                                                                        </div>
                                                                        <h6 class="body-title-2 mb-8"><?php echo htmlspecialchars($item['content_key']); ?></h6>
                                                                        <div class="text-tiny mb-8">
                                                                            <strong>EN:</strong> <?php echo htmlspecialchars(substr($item['title_en'] ?: 'No title', 0, 40)); ?><?php echo strlen($item['title_en']) > 40 ? '...' : ''; ?>
                                                                        </div>
                                                                        <div class="text-tiny mb-8">
                                                                            <strong>AR:</strong> <?php echo htmlspecialchars(substr($item['title_ar'] ?: 'No title', 0, 40)); ?><?php echo strlen($item['title_ar']) > 40 ? '...' : ''; ?>
                                                                        </div>
                                                                        <div class="text-tiny mb-12">
                                                                            <strong>Content:</strong> <?php echo htmlspecialchars(substr($item['content_en'] ?: $item['content_ar'] ?: 'No content', 0, 60)); ?><?php echo strlen($item['content_en'] ?: $item['content_ar'] ?: '') > 60 ? '...' : ''; ?>
                                                                        </div>
                                                                        <div class="content-actions">
                                                                            <button class="action-btn edit" onclick="editContent(<?php echo $item['id']; ?>)" title="Edit">
                                                                                <i class="fas fa-edit"></i>
                                                                                Edit
                                                                            </button>
                                                                            <button class="action-btn toggle" onclick="toggleContentStatus(<?php echo $item['id']; ?>, <?php echo $item['is_active']; ?>)" title="Toggle Status">
                                                                                <i class="fas fa-toggle-<?php echo $item['is_active'] ? 'on' : 'off'; ?>"></i>
                                                                                Toggle
                                                                            </button>
                                                                            <button class="action-btn delete" onclick="deleteContent(<?php echo $item['id']; ?>)" title="Delete">
                                                                                <i class="fas fa-trash"></i>
                                                                                Delete
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <!-- Table View -->
                                                    <div class="wg-box">
                                                        <div class="flex items-center justify-between mb-20">
                                                            <div class="body-title">Website Content</div>
                                                            <div class="body-text">Total: <?php echo count($contentList); ?> items</div>
                                                        </div>
                                                        <div class="wg-table table-all-user">
                                                            <div class="table-responsive">
                                                                <table class="table table-striped table-bordered">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Content Key</th>
                                                                            <th>Section</th>
                                                                            <th>English Title</th>
                                                                            <th>Arabic Title</th>
                                                                            <th class="text-center">Status</th>
                                                                            <th class="text-center">Actions</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($contentList as $item): ?>
                                                                            <tr>
                                                                                <td>
                                                                                    <div class="body-title"><?php echo htmlspecialchars($item['content_key']); ?></div>
                                                                                </td>
                                                                                <td>
                                                                                    <div class="section-label" style="display: inline-block;"><?php echo htmlspecialchars($item['page_section']); ?></div>
                                                                                </td>
                                                                                <td><?php echo htmlspecialchars(substr($item['title_en'] ?: '-', 0, 30)); ?><?php echo strlen($item['title_en']) > 30 ? '...' : ''; ?></td>
                                                                                <td><?php echo htmlspecialchars(substr($item['title_ar'] ?: '-', 0, 30)); ?><?php echo strlen($item['title_ar']) > 30 ? '...' : ''; ?></td>
                                                                                <td class="text-center">
                                                                                    <div class="status-badge <?php echo $item['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                                                        <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                                                                    </div>
                                                                                </td>
                                                                                <td class="text-center">
                                                                                    <div class="list-icon-function">
                                                                                        <button class="item edit" onclick="editContent(<?php echo $item['id']; ?>)" title="Edit">
                                                                                            <i class="fas fa-edit"></i>
                                                                                        </button>
                                                                                        <button class="item toggle" onclick="toggleContentStatus(<?php echo $item['id']; ?>, <?php echo $item['is_active']; ?>)" title="Toggle Status">
                                                                                            <i class="fas fa-toggle-<?php echo $item['is_active'] ? 'on' : 'off'; ?>"></i>
                                                                                        </button>
                                                                                        <button class="item delete" onclick="deleteContent(<?php echo $item['id']; ?>)" title="Delete">
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
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="wg-box text-center p-40">
                                                    <i class="fas fa-file-alt" style="font-size: 48px; color: #ddd; margin-bottom: 16px;"></i>
                                                    <h5>No Content Found</h5>
                                                    <p class="text-muted">Start by adding your first content item.</p>
                                                    <button class="tf-button style-1 type-fill" onclick="openAddContentModal()">
                                                        <i class="icon-plus"></i>
                                                        Add Content
                                                    </button>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Pagination -->
                                            <?php if ($totalPages > 1): ?>
                                                <div class="flex items-center justify-between mt-32">
                                                    <div class="body-text">
                                                        Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>
                                                        (<?php echo $filteredContentCount; ?> total items)
                                                        <?php if ($search || $section || $status !== ''): ?>
                                                            <span style="color: #7B3BE0;">- Filtered results</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="wg-pagination">
                                                        <?php if ($page > 1): ?>
                                                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&section=<?php echo urlencode($section); ?>&status=<?php echo urlencode($status); ?>&view=<?php echo urlencode($view_mode); ?>" class="pagination-item">
                                                                <i class="icon-chevron-left"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&section=<?php echo urlencode($section); ?>&status=<?php echo urlencode($status); ?>&view=<?php echo urlencode($view_mode); ?>"
                                                                class="pagination-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        <?php endfor; ?>

                                                        <?php if ($page < $totalPages): ?>
                                                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&section=<?php echo urlencode($section); ?>&status=<?php echo urlencode($status); ?>&view=<?php echo urlencode($view_mode); ?>" class="pagination-item">
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
                    <!-- /main-content -->
                </div>
                <!-- /section-content-right -->
            </div>
            <!-- /layout-wrap -->
        </div>
        <!-- /#page -->
    </div>
    <!-- /#wrapper -->

    <!-- Add Content Modal -->
    <div class="modal fade" id="addContentModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="POST" id="addContentForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Content</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <fieldset class="mb-3">
                                    <label class="form-label">Content Key * <small class="text-muted">(Must be unique - connects to frontend)</small></label>
                                    <input type="text" name="content_key" class="tf-input style-1" placeholder="unique_content_key" required>
                                    <small class="text-muted">This key connects the content to the frontend display. Choose carefully as it cannot be changed later.</small>
                                </fieldset>
                                <fieldset class="mb-3">
                                    <label class="form-label">Page Section *</label>
                                    <select name="page_section" class="tf-input style-1" required>
                                        <option value="">Select Section</option>
                                        <option value="hero">Hero</option>
                                        <option value="about">About</option>
                                        <option value="products">Products</option>
                                        <option value="services">Services</option>
                                        <option value="contact">Contact</option>
                                        <option value="footer">Footer</option>
                                    </select>
                                </fieldset>
                            </div>
                            <div class="col-md-6">
                                <div class="language-tabs">
                                    <div class="language-tab active" onclick="switchLanguage('ar')">Arabic</div>
                                    <div class="language-tab" onclick="switchLanguage('en')">English</div>
                                </div>
                                <div class="language-content active" id="content-ar">
                                    <fieldset class="mb-3">
                                        <label class="form-label">Arabic Title</label>
                                        <input type="text" name="title_ar" class="tf-input style-1" placeholder="العنوان بالعربية">
                                    </fieldset>
                                    <fieldset class="mb-3">
                                        <label class="form-label">Arabic Content</label>
                                        <textarea name="content_ar" class="tf-input style-1" rows="4" placeholder="المحتوى بالعربية"></textarea>
                                    </fieldset>
                                </div>
                                <div class="language-content" id="content-en">
                                    <fieldset class="mb-3">
                                        <label class="form-label">English Title</label>
                                        <input type="text" name="title_en" class="tf-input style-1" placeholder="English Title">
                                    </fieldset>
                                    <fieldset class="mb-3">
                                        <label class="form-label">English Content</label>
                                        <textarea name="content_en" class="tf-input style-1" rows="4" placeholder="English Content"></textarea>
                                    </fieldset>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="tf-button style-1 type-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_content" class="tf-button style-1 type-fill">Save Content</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Content Modal -->
    <div class="modal fade" id="editContentModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="POST" id="editContentForm">
                    <input type="hidden" name="content_id" id="edit_content_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Content</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <fieldset class="mb-3">
                                    <label class="form-label">Content Key * <small class="text-muted">(Read-only - Frontend Connection)</small></label>
                                    <input type="text" name="content_key" id="edit_content_key" class="tf-input style-1" readonly style="background-color: #f8f9fa; cursor: not-allowed;" title="Content Key cannot be changed as it connects to frontend display">
                                    <small class="text-muted">This key is used to connect content to the frontend and cannot be modified.</small>
                                </fieldset>
                                <fieldset class="mb-3">
                                    <label class="form-label">Page Section *</label>
                                    <select name="page_section" id="edit_page_section" class="tf-input style-1" required>
                                        <option value="hero">Hero</option>
                                        <option value="about">About</option>
                                        <option value="products">Products</option>
                                        <option value="services">Services</option>
                                        <option value="contact">Contact</option>
                                        <option value="footer">Footer</option>
                                    </select>
                                </fieldset>
                            </div>
                            <div class="col-md-6">
                                <div class="language-tabs">
                                    <div class="language-tab active" onclick="switchEditLanguage('ar')">Arabic</div>
                                    <div class="language-tab" onclick="switchEditLanguage('en')">English</div>
                                </div>
                                <div class="language-content active" id="edit-content-ar">
                                    <fieldset class="mb-3">
                                        <label class="form-label">Arabic Title</label>
                                        <input type="text" name="title_ar" id="edit_title_ar" class="tf-input style-1">
                                    </fieldset>
                                    <fieldset class="mb-3">
                                        <label class="form-label">Arabic Content</label>
                                        <textarea name="content_ar" id="edit_content_ar" class="tf-input style-1" rows="4"></textarea>
                                    </fieldset>
                                </div>
                                <div class="language-content" id="edit-content-en">
                                    <fieldset class="mb-3">
                                        <label class="form-label">English Title</label>
                                        <input type="text" name="title_en" id="edit_title_en" class="tf-input style-1">
                                    </fieldset>
                                    <fieldset class="mb-3">
                                        <label class="form-label">English Content</label>
                                        <textarea name="content_en" id="edit_content_en" class="tf-input style-1" rows="4"></textarea>
                                    </fieldset>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="tf-button style-1 type-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_content" class="tf-button style-1 type-fill">Update Content</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteContentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deleteContentForm">
                    <input type="hidden" name="content_id" id="delete_content_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center">
                            <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #dc3545; margin-bottom: 16px;"></i>
                            <h6>Are you sure?</h6>
                            <p>Are you sure you want to delete this content? This action cannot be undone.</p>
                            <p><strong>Content Key:</strong> <span id="delete_content_key"></span></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="tf-button style-1 type-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_content" class="tf-button style-1 type-fill bg-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toggle Status Form -->
    <form method="POST" id="toggleStatusForm" style="display: none;">
        <input type="hidden" name="content_id" id="toggle_content_id">
        <input type="hidden" name="current_status" id="toggle_current_status">
        <input type="hidden" name="toggle_status" value="1">
    </form>

    <!-- Scripts -->
    <script src="assets/vendor/js/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="assets/vendor/js/bootstrap-select.min.js"></script>
    <script src="assets/vendor/js/main.js"></script>

    <script>
        // Content data for JavaScript access
        const contentData = <?php echo json_encode($contentList); ?>;

        function openAddContentModal() {
            new bootstrap.Modal(document.getElementById('addContentModal')).show();
        }

        function editContent(contentId) {
            const content = contentData.find(item => item.id == contentId);
            if (!content) return;

            // Populate edit form
            document.getElementById('edit_content_id').value = content.id;
            document.getElementById('edit_content_key').value = content.content_key;
            document.getElementById('edit_title_ar').value = content.title_ar || '';
            document.getElementById('edit_title_en').value = content.title_en || '';
            document.getElementById('edit_content_ar').value = content.content_ar || '';
            document.getElementById('edit_content_en').value = content.content_en || '';
            document.getElementById('edit_page_section').value = content.page_section;

            // Show modal
            new bootstrap.Modal(document.getElementById('editContentModal')).show();
        }

        function deleteContent(contentId) {
            const content = contentData.find(item => item.id == contentId);
            if (!content) return;

            // Populate delete form
            document.getElementById('delete_content_id').value = content.id;
            document.getElementById('delete_content_key').textContent = content.content_key;

            // Show modal
            new bootstrap.Modal(document.getElementById('deleteContentModal')).show();
        }

        function toggleContentStatus(contentId, currentStatus) {
            document.getElementById('toggle_content_id').value = contentId;
            document.getElementById('toggle_current_status').value = currentStatus;
            document.getElementById('toggleStatusForm').submit();
        }

        function toggleView() {
            const currentView = '<?php echo $view_mode; ?>';
            const newView = currentView === 'cards' ? 'table' : 'cards';
            const url = new URL(window.location);
            url.searchParams.set('view', newView);
            window.location.href = url.toString();
        }

        function switchLanguage(lang) {
            // Remove active class from all tabs and content
            document.querySelectorAll('#addContentModal .language-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('#addContentModal .language-content').forEach(content => content.classList.remove('active'));

            // Add active class to selected tab and content
            event.target.classList.add('active');
            document.getElementById('content-' + lang).classList.add('active');
        }

        function switchEditLanguage(lang) {
            // Remove active class from all tabs and content
            document.querySelectorAll('#editContentModal .language-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('#editContentModal .language-content').forEach(content => content.classList.remove('active'));

            // Add active class to selected tab and content
            event.target.classList.add('active');
            document.getElementById('edit-content-' + lang).classList.add('active');
        }

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                if (alert.classList.contains('show')) {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                }
            });
        }, 5000);

        // Enhanced search functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Focus search input on page load if no filters are active
            const searchInput = document.querySelector('input[name="search"]');
            const hasFilters = '<?php echo $search || $section || $status !== '' ? "true" : "false"; ?>';

            if (searchInput && hasFilters === 'false') {
                searchInput.focus();
            }

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + K: Focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }

                // Ctrl/Cmd + Enter: Submit search
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    if (searchInput.form) {
                        searchInput.form.submit();
                    }
                }

                // Escape: Clear search
                if (e.key === 'Escape' && document.activeElement === searchInput) {
                    searchInput.value = '';
                    searchInput.form.submit();
                }

                // Ctrl/Cmd + N: Add new content
                if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                    e.preventDefault();
                    openAddContentModal();
                }
            });

            // Auto-submit search after typing stops (debounced)
            let searchTimeout;
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        // Only auto-submit if user has typed something meaningful
                        if (this.value.length >= 2 || this.value.length === 0) {
                            this.form.submit();
                        }
                    }, 800); // 800ms delay
                });
            }

            // Highlight search terms in results
            const searchTerm = '<?php echo addslashes($search); ?>';
            if (searchTerm && searchTerm.length > 0) {
                const regex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                document.querySelectorAll('.content-card, .table tbody').forEach(function(container) {
                    const walker = document.createTreeWalker(
                        container,
                        NodeFilter.SHOW_TEXT,
                        null,
                        false
                    );

                    const textNodes = [];
                    let node;
                    while (node = walker.nextNode()) {
                        if (node.nodeValue.trim() && regex.test(node.nodeValue)) {
                            textNodes.push(node);
                        }
                    }

                    textNodes.forEach(function(textNode) {
                        const highlightedText = textNode.nodeValue.replace(regex, '<mark style="background: #C388F7; color: #fff; padding: 2px 4px; border-radius: 3px;">$1</mark>');
                        const span = document.createElement('span');
                        span.innerHTML = highlightedText;
                        textNode.parentNode.replaceChild(span, textNode);
                    });
                });
            }

            // Add tooltips for keyboard shortcuts
            if (searchInput) {
                searchInput.setAttribute('title', 'Keyboard shortcuts:\nCtrl+K: Focus search\nCtrl+Enter: Submit\nEscape: Clear\nCtrl+N: Add content');
            }
        });
    </script>

</body>

</html>