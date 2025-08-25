<?php

/**
 * Sun Trading Company - Admin Dashboard
 * Custom Admin System - Developed by Elnakieb
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$auth = new Auth();
$auth->requireAuth();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// Get dashboard statistics
$stats = [];

// Total products
$stats['products'] = $db->fetchOne("SELECT COUNT(*) as count FROM products")['count'] ?? 0;

// Total product images
$stats['images'] = $db->fetchOne("SELECT COUNT(*) as count FROM product_images")['count'] ?? 0;

// Total file uploads
$stats['files'] = $db->fetchOne("SELECT COUNT(*) as count FROM file_uploads")['count'] ?? 0;

// Total users
$stats['users'] = $db->fetchOne("SELECT COUNT(*) as count FROM admin_users WHERE is_active = 1")['count'] ?? 0;

// Recent activity
$recentActivity = $db->fetchAll(
    "SELECT al.*, au.full_name 
     FROM activity_logs al 
     LEFT JOIN admin_users au ON al.user_id = au.id 
     ORDER BY al.created_at DESC 
     LIMIT 10"
);

// Recent products
$recentProducts = $db->fetchAll(
    "SELECT p.*, 
     (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image
     FROM products p 
     ORDER BY p.created_at DESC 
     LIMIT 5"
);

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
    <title>Dashboard - Sun Trading Admin Panel</title>

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

    <!-- Favicon and Touch Icons  -->
    <link rel="shortcut icon" href="assets/vendor/images/favicon.png">
    <link rel="apple-touch-icon-precomposed" href="assets/vendor/images/favicon.png">

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
                                        <a href="dashboard.php" class="menu-item-button active">
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
                                        <a href="content.php" class="menu-item-button">
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
                                    <?php if ($auth->isAdmin()): ?>
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
                                <i class="fas fa-crown" style="font-size: 48px; color: #C0FAA0;"></i>
                            </div>
                            <div class="content">
                                <p class="f12-regular text-White">Admin Panel v2.0</p>
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
                                <h6>Admin Dashboard</h6>
                                <form class="form-search flex-grow">
                                    <fieldset class="name">
                                        <input type="text" placeholder="Search products, images, content..." class="show-search style-1" name="name" tabindex="2" value="" aria-required="true" required="">
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
                                                    <i class="fas fa-envelope"></i>
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
                                                                <a href="#" class="body-title name">System Admin</a>
                                                                <div class="time">Now</div>
                                                            </div>
                                                            <div class="text-tiny desc">Welcome to your admin panel!</div>
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
                                                    <i class="fas fa-bell"></i>
                                                </span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end has-content" aria-labelledby="dropdownMenuButton2">
                                                <li>
                                                    <h6>Notifications</h6>
                                                </li>
                                                <?php foreach (array_slice($recentActivity, 0, 3) as $activity): ?>
                                                    <li>
                                                        <div class="notifications-item item-1">
                                                            <div class="image">
                                                                <i class="fas fa-info-circle"></i>
                                                            </div>
                                                            <div>
                                                                <div class="body-title-2"><?php echo htmlspecialchars($activity['action']); ?></div>
                                                                <div class="text-tiny"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></div>
                                                            </div>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
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

                            <!-- main-content-wrap -->
                            <div class="main-content-wrap">
                                <div class="tf-container">
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="flex gap24 mb-32 flex-md-row flex-column">
                                                <div class="w-100">
                                                    <div class="wg-card style-1 bg-Primary mb-25">
                                                        <div class="icon">
                                                            <i class="fas fa-box" style="font-size: 32px; color: #fff; background: rgba(255,255,255,0.1); padding: 12px; border-radius: 12px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <div>
                                                                <h6 class="counter text-White">
                                                                    <span class="number" data-speed="2000" data-to="<?php echo $stats['products']; ?>" data-inviewport="yes"><?php echo $stats['products']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium text-White">Total Products</div>
                                                            </div>
                                                            <div class="chart-small">
                                                                <div style="text-align: center; color: #C0FAA0; margin-top: 10px;">
                                                                    <i class="fas fa-arrow-up"></i> Active
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="wg-card">
                                                        <div class="icon">
                                                            <i class="fas fa-images" style="font-size: 32px; color: #161326; background: #C0FAA0; padding: 12px; border-radius: 12px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <div>
                                                                <h6 class="counter">
                                                                    <span class="number" data-speed="2000" data-to="<?php echo $stats['images']; ?>" data-inviewport="yes"><?php echo $stats['images']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium">Product Images</div>
                                                            </div>
                                                            <div class="chart-small">
                                                                <div style="text-align: center; color: #2BC155; margin-top: 10px;">
                                                                    <i class="fas fa-check"></i> Gallery
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="w-100">
                                                    <div class="wg-card mb-25">
                                                        <div class="icon">
                                                            <i class="fas fa-users" style="font-size: 32px; color: #fff; background: #161326; padding: 12px; border-radius: 12px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <div>
                                                                <h6 class="counter">
                                                                    <span class="number" data-speed="2000" data-to="<?php echo $stats['users']; ?>" data-inviewport="yes"><?php echo $stats['users']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium">Admin Users</div>
                                                            </div>
                                                            <div class="chart-small">
                                                                <div style="text-align: center; color: #C388F7; margin-top: 10px;">
                                                                    <i class="fas fa-user-shield"></i> Active
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="wg-card style-1 bg-YellowGreen">
                                                        <div class="icon">
                                                            <i class="fas fa-file" style="font-size: 32px; color: #fff; background: #161326; padding: 12px; border-radius: 12px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <div>
                                                                <h6 class="counter">
                                                                    <span class="number" data-speed="2000" data-to="<?php echo $stats['files']; ?>" data-inviewport="yes"><?php echo $stats['files']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium">File Uploads</div>
                                                            </div>
                                                            <div class="chart-small">
                                                                <div style="text-align: center; color: #161326; margin-top: 10px;">
                                                                    <i class="fas fa-cloud"></i> Storage
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Admin Quick Actions Panel -->
                                            <div class="wg-box style-1 bg-Gainsboro shadow-none widget-tabs mb-32">
                                                <div>
                                                    <div class="title mb-16">
                                                        <div class="label-01">Quick Actions</div>
                                                        <ul class="widget-menu-tab">
                                                            <li class="item-title f12-medium active">
                                                                <span class="inner">Admin</span>
                                                            </li>
                                                            <li class="item-title f12-medium">
                                                                <span class="inner">Content</span>
                                                            </li>
                                                            <li class="item-title f12-medium">
                                                                <span class="inner">System</span>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <div class="flex gap16 items-center flex-wrap">
                                                            <a href="products.php" class="tf-button style-default gap8 f14-bold text-Primary">
                                                                <i class="fas fa-plus"></i>
                                                                Add Product
                                                            </a>
                                                            <a href="images.php" class="tf-button style-default gap8 f14-bold text-Orchid">
                                                                <i class="fas fa-upload"></i>
                                                                Upload Images
                                                            </a>
                                                        </div>
                                                        <a href="products.php" class="tf-button style-default gap8 f14-bold text-Primary">
                                                            <i class="fas fa-eye"></i>
                                                            View All Products
                                                        </a>
                                                    </div>
                                                </div>
                                                <div class="widget-content-tab">
                                                    <div class="widget-content-inner active" style="padding: 20px;">
                                                        <div class="flex gap16 items-center flex-wrap">
                                                            <a href="products.php?action=add" class="tf-button f12-bold" style="flex: 1;">
                                                                <i class="fas fa-box"></i> Add Product
                                                            </a>
                                                            <a href="images.php" class="tf-button f12-bold" style="flex: 1;">
                                                                <i class="fas fa-images"></i> Manage Images
                                                            </a>
                                                            <a href="users.php" class="tf-button f12-bold" style="flex: 1;">
                                                                <i class="fas fa-users"></i> Manage Users
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <div class="widget-content-inner" style="padding: 20px;">
                                                        <div class="flex gap16 items-center flex-wrap">
                                                            <a href="content.php" class="tf-button f12-bold" style="flex: 1;">
                                                                <i class="fas fa-edit"></i> Edit Content
                                                            </a>
                                                            <a href="settings.php" class="tf-button f12-bold" style="flex: 1;">
                                                                <i class="fas fa-cog"></i> Site Settings
                                                            </a>
                                                            <a href="../index.php" target="_blank" class="tf-button f12-bold" style="flex: 1;">
                                                                <i class="fas fa-external-link-alt"></i> View Site
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <div class="widget-content-inner" style="padding: 20px;">
                                                        <div class="flex gap16 items-center flex-wrap">
                                                            <a href="backup.php" class="tf-button f12-bold" style="flex: 1;">
                                                                <i class="fas fa-download"></i> Backup
                                                            </a>
                                                            <a href="logs.php" class="tf-button f12-bold" style="flex: 1;">
                                                                <i class="fas fa-list"></i> View Logs
                                                            </a>
                                                            <a href="settings.php" class="tf-button f12-bold" style="flex: 1;">
                                                                <i class="fas fa-shield-alt"></i> Security
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <!-- Recent Activity -->
                                            <div class="wg-box style-1 bg-Primary shadow-none mb-32">
                                                <div>
                                                    <div class="title mb-10">
                                                        <div class="label-01 text-White">Recent Activity</div>
                                                        <div class="dropdown default">
                                                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                <span class="icon-more text-White"></span>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li><a href="logs.php">View All Logs</a></li>
                                                                <li><a href="logs.php?filter=today">Today Only</a></li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                    <div class="flex gap16 items-center">
                                                        <div class="tf-cart-checkbox style-1">
                                                            <div class="tf-checkbox-wrapp">
                                                                <input class="" type="checkbox" checked="">
                                                                <div><i class="icon-check"></i></div>
                                                            </div>
                                                            <div class="f12-medium text-Gray">Products</div>
                                                        </div>
                                                        <div class="tf-cart-checkbox style-1">
                                                            <div class="tf-checkbox-wrapp">
                                                                <input class="" type="checkbox" checked="">
                                                                <div><i class="icon-check"></i></div>
                                                            </div>
                                                            <div class="f12-medium text-Gray">Images</div>
                                                        </div>
                                                        <div class="tf-cart-checkbox style-1">
                                                            <div class="tf-checkbox-wrapp">
                                                                <input class="" type="checkbox">
                                                                <div><i class="icon-check"></i></div>
                                                            </div>
                                                            <div class="f12-medium text-Gray">Users</div>
                                                        </div>
                                                        <div class="tf-cart-checkbox style-1">
                                                            <div class="tf-checkbox-wrapp">
                                                                <input class="" type="checkbox">
                                                                <div><i class="icon-check"></i></div>
                                                            </div>
                                                            <div class="f12-medium text-Gray">System</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div style="padding: 20px; color: white;">
                                                    <?php if (!empty($recentActivity)): ?>
                                                        <?php foreach (array_slice($recentActivity, 0, 5) as $activity): ?>
                                                            <div style="margin-bottom: 15px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                                                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                                                    <div>
                                                                        <div class="f14-medium text-White"><?php echo htmlspecialchars($activity['action']); ?></div>
                                                                        <div class="f12-regular" style="color: #C0FAA0;"><?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?></div>
                                                                    </div>
                                                                    <div class="f12-regular text-Gray"><?php echo date('M j, H:i', strtotime($activity['created_at'])); ?></div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div style="text-align: center; color: #C0FAA0; padding: 20px;">
                                                            <i class="fas fa-info-circle" style="font-size: 24px; margin-bottom: 10px;"></i>
                                                            <div>No recent activity</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <!-- Recent Products -->
                                            <div class="flex gap24 mb-32 flex-md-row flex-column">
                                                <div class="wg-box gap16">
                                                    <div>
                                                        <div class="title mb-12">
                                                            <div class="label-01">Recent Products</div>
                                                            <div class="dropdown default">
                                                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                    <span class="icon-more"></span>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li><a href="products.php">View All Products</a></li>
                                                                    <li><a href="products.php?action=add">Add New Product</a></li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($recentProducts)): ?>
                                                        <table class="tab-sell-order">
                                                            <thead>
                                                                <tr>
                                                                    <th class="f14-regular text-Gray">Name</th>
                                                                    <th class="f14-regular text-Gray">Status</th>
                                                                    <th class="f14-regular text-Gray">Date</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($recentProducts as $product): ?>
                                                                    <tr>
                                                                        <td class="f14-regular"><?php
                                                                                                $productName = $product['name_en'] ?? $product['name_ar'] ?? 'Unknown Product';
                                                                                                echo htmlspecialchars(substr($productName, 0, 15));
                                                                                                echo strlen($productName) > 15 ? '...' : '';
                                                                                                ?></td>
                                                                        <td class="f14-regular"><?php echo isset($product['is_active']) && $product['is_active'] ? '<span style="color: #2BC155;">Active</span>' : '<span style="color: #F44336;">Inactive</span>'; ?></td>
                                                                        <td class="f14-regular"><?php echo isset($product['created_at']) ? date('M j', strtotime($product['created_at'])) : 'N/A'; ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php else: ?>
                                                        <div style="text-align: center; color: #999; padding: 30px;">
                                                            <i class="fas fa-box" style="font-size: 32px; margin-bottom: 10px;"></i>
                                                            <div>No products yet</div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <a href="products.php" class="tf-button f12-bold w-100">
                                                        View All Products
                                                        <i class="icon icon-send"></i>
                                                    </a>
                                                </div>

                                                <div class="wg-box gap16 bg-YellowGreen">
                                                    <div>
                                                        <div class="title mb-12">
                                                            <div class="label-01">System Status</div>
                                                            <div class="dropdown default">
                                                                <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                    <span class="icon-more"></span>
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end">
                                                                    <li><a href="settings.php">System Settings</a></li>
                                                                    <li><a href="backup.php">Create Backup</a></li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <table class="tab-sell-order">
                                                        <thead>
                                                            <tr>
                                                                <th class="f14-regular">Component</th>
                                                                <th class="f14-regular">Status</th>
                                                                <th class="f14-regular">Last Check</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td class="f14-regular">Database</td>
                                                                <td class="f14-regular"><span style="color: #2BC155;">Online</span></td>
                                                                <td class="f14-regular">Now</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="f14-regular">File System</td>
                                                                <td class="f14-regular"><span style="color: #2BC155;">OK</span></td>
                                                                <td class="f14-regular">1m ago</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="f14-regular">Admin Panel</td>
                                                                <td class="f14-regular"><span style="color: #2BC155;">Active</span></td>
                                                                <td class="f14-regular">Now</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="f14-regular">Security</td>
                                                                <td class="f14-regular"><span style="color: #2BC155;">Secure</span></td>
                                                                <td class="f14-regular">5m ago</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                    <a href="settings.php" class="tf-button style-1 f12-bold w-100">
                                                        System Settings
                                                        <i class="icon icon-send"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- /main-content-wrap -->
                        </div>
                        <!-- /main-content-wrap -->

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

    <!-- Javascript -->
    <script src="assets/vendor/js/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="assets/vendor/js/bootstrap-select.min.js"></script>
    <script src="assets/vendor/js/main.js"></script>

    <!-- Admin Panel JavaScript -->
    <script src="assets/js/admin.js"></script>

</body>

</html>