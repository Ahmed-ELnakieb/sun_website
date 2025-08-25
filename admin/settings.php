<?php

/**
 * Sun Trading Company - Settings Management
 * Custom Admin System - Developed by Elnakieb
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$auth->requireAuth();

$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();
$db = Database::getInstance();

$section = $_GET['section'] ?? 'general';
$success = '';
$error = '';

// Handle settings update
if ($_POST && isset($_POST['save_settings'])) {
    $settings = $_POST['settings'] ?? [];
    $updatedCount = 0;

    foreach ($settings as $key => $value) {
        // Skip empty values to prevent overwriting existing settings
        if (trim($value) === '') {
            continue;
        }

        $result = $db->update('site_settings', ['setting_value' => sanitize($value)], 'setting_key = :key', ['key' => $key]);
        if ($result) $updatedCount++;
    }

    if ($updatedCount > 0) {
        $success = "$updatedCount setting(s) updated successfully!";
    } else {
        $error = 'No valid settings to update. Please select an image or enter a value.';
    }
}

// Handle file upload for logo and background images
if ($_POST && isset($_POST['upload_image'])) {
    $imageType = $_POST['image_type'] ?? '';
    $settingKey = $_POST['setting_key'] ?? '';

    if (!empty($_FILES['image_file']['name']) && $imageType && $settingKey) {
        $uploadDir = '../uploads/images/';
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (in_array($_FILES['image_file']['type'], $allowedTypes)) {
            $fileExtension = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $filePath)) {
                $relativePath = 'uploads/images/' . $fileName;

                // Update the setting with new image path
                $result = $db->update('site_settings', ['setting_value' => $relativePath], 'setting_key = :key', ['key' => $settingKey]);

                // Also add to file_uploads table
                $db->insert('file_uploads', [
                    'original_name' => $_FILES['image_file']['name'],
                    'file_name' => $fileName,
                    'file_path' => $relativePath,
                    'file_type' => $_FILES['image_file']['type'],
                    'file_size' => $_FILES['image_file']['size'],
                    'upload_category' => $imageType === 'logo' ? 'logos' : 'general',
                    'uploaded_by' => $currentUser['id']
                ]);

                if ($result) {
                    $success = ucfirst($imageType) . ' image updated successfully!';
                } else {
                    $error = 'Failed to update database.';
                }
            } else {
                $error = 'Failed to upload image file.';
            }
        } else {
            $error = 'Invalid file type. Please upload JPG, PNG, GIF, or WebP images.';
        }
    } else {
        $error = 'Please select an image file.';
    }
}

// Available themes
$availableThemes = [
    'golden' => 'Golden Theme',
    'ocean' => 'Ocean Blue Theme',
    'forest' => 'Forest Green Theme',
    'purple' => 'Royal Purple Theme',
    'sunset' => 'Sunset Red Theme'
];

// Get settings by category
$generalSettings = $db->fetchAll("SELECT * FROM site_settings WHERE category = 'general' OR category = 'company' ORDER BY setting_key") ?? [];
$brandingSettings = $db->fetchAll("SELECT * FROM site_settings WHERE category = 'branding' OR category = 'appearance' ORDER BY setting_key") ?? [];
$contactSettings = $db->fetchAll("SELECT * FROM site_settings WHERE category = 'contact' ORDER BY setting_key") ?? [];
$systemSettings = $db->fetchAll("SELECT * FROM site_settings WHERE category = 'system' ORDER BY setting_key") ?? [];

// Get available images for logo and background selections
$logoImages = $db->fetchAll("SELECT * FROM file_uploads WHERE upload_category IN ('logo', 'logos') ORDER BY created_at DESC") ?? [];
$backgroundImages = $db->fetchAll("SELECT * FROM file_uploads WHERE upload_category = 'general' AND (original_name LIKE '%background%' OR original_name LIKE '%hero%') ORDER BY created_at DESC") ?? [];
$allGeneralImages = $db->fetchAll("SELECT * FROM file_uploads WHERE upload_category = 'general' ORDER BY created_at DESC") ?? [];

// Get settings statistics
$stats = [];
$stats['total_settings'] = $db->fetchOne("SELECT COUNT(*) as count FROM site_settings")['count'] ?? 0;
$stats['general_settings'] = count($generalSettings);
$stats['branding_settings'] = count($brandingSettings);
$stats['contact_settings'] = count($contactSettings);
$stats['system_settings'] = count($systemSettings);

$flashMessages = getFlashMessages();
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US">

<head>
    <meta charset="utf-8">
    <title>Settings Management - Sun Trading Admin Panel</title>
    <meta name="author" content="Sun Trading Company">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

    <link rel="stylesheet" type="text/css" href="assets/vendor/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/animation.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/bootstrap.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/bootstrap-select.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/styles.css">
    <link rel="stylesheet" href="../critso/font/fonts.css">
    <link rel="stylesheet" href="../critso/icon/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="assets/css/admin-critso.css">
    <link rel="shortcut icon" href="../critso/images/favicon.png">

    <style>
        .settings-tab {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px 16px;
            color: #333;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-weight: 500;
        }

        .settings-tab:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            color: #333;
            text-decoration: none;
        }

        .settings-tab.active {
            background: rgba(195, 136, 247, 0.1);
            border-color: #C388F7;
            color: #7B3BE0;
        }

        .image-preview {
            transition: all 0.3s ease;
        }

        .image-preview:hover {
            border-color: #C388F7 !important;
            box-shadow: 0 4px 15px rgba(195, 136, 247, 0.2);
        }

        .image-preview img {
            transition: transform 0.3s ease;
        }

        .image-preview:hover img {
            transform: scale(1.05);
        }

        .background-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            border-color: #C388F7;
            background-color: rgba(195, 136, 247, 0.05);
        }
    </style>
</head>

<body class="counter-scroll">

    <div id="wrapper">
        <div id="page" class="">
            <div class="layout-wrap loader-off">
                <div id="preload" class="preload-container">
                    <div class="preloading">
                        <span></span>
                    </div>
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
                                        <a href="settings.php" class="menu-item-button active">
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
                                <i class="fas fa-cog" style="font-size: 48px; color: #C0FAA0;"></i>
                            </div>
                            <div class="content">
                                <p class="f12-regular text-White">Settings Management</p>
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
                                <h6>Settings Management</h6>
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
                                                                <a href="#" class="body-title name">Settings Manager</a>
                                                                <div class="time">Now</div>
                                                            </div>
                                                            <div class="text-tiny desc">Settings management is ready!</div>
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
                                                            <i class="icon-cog"></i>
                                                        </div>
                                                        <div>
                                                            <div class="body-title-2">Settings</div>
                                                            <div class="text-tiny">Manage your settings</div>
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
                                                <a href="../index.php" target="_blank" class="user-item">
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
                                                    <h3>Settings Management</h3>
                                                    <div class="body-text mt-8">Configure your website settings and preferences</div>
                                                </div>
                                                <div class="flex gap16">
                                                    <a href="javascript:void(0);" onclick="window.open('../', '_blank');" class="tf-button style-1 type-outline">
                                                        <i class="icon-external-link"></i> View Website
                                                    </a>
                                                </div>
                                            </div>

                                            <!-- Statistics Dashboard -->
                                            <div class="flex gap24 mb-32 flex-md-row flex-column">
                                                <div class="flex gap16 flex-wrap" style="flex: 2;">
                                                    <div class="wg-card style-1 bg-Primary" style="min-width: 200px; flex: 1; padding: 16px;">
                                                        <div class="flex items-center gap12">
                                                            <div class="icon">
                                                                <i class="fas fa-cog" style="font-size: 20px; color: #fff; background: rgba(255,255,255,0.1); padding: 8px; border-radius: 6px;"></i>
                                                            </div>
                                                            <div class="content">
                                                                <h6 class="counter text-White mb-0">
                                                                    <span class="number"><?php echo $stats['total_settings']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium text-White">Total Settings</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="wg-card" style="min-width: 200px; flex: 1; padding: 16px;">
                                                        <div class="flex items-center gap12">
                                                            <div class="icon">
                                                                <i class="fas fa-tools" style="font-size: 20px; color: #161326; background: #C0FAA0; padding: 8px; border-radius: 6px;"></i>
                                                            </div>
                                                            <div class="content">
                                                                <h6 class="counter mb-0">
                                                                    <span class="number"><?php echo $stats['general_settings']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium">General</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="wg-card" style="min-width: 200px; flex: 1; padding: 16px;">
                                                        <div class="flex items-center gap12">
                                                            <div class="icon">
                                                                <i class="fas fa-paint-brush" style="font-size: 20px; color: #fff; background: #C388F7; padding: 8px; border-radius: 6px;"></i>
                                                            </div>
                                                            <div class="content">
                                                                <h6 class="counter mb-0">
                                                                    <span class="number"><?php echo $stats['branding_settings']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium">Branding</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="wg-card style-1 bg-YellowGreen" style="min-width: 200px; flex: 1; padding: 16px;">
                                                        <div class="flex items-center gap12">
                                                            <div class="icon">
                                                                <i class="fas fa-address-book" style="font-size: 20px; color: #fff; background: #161326; padding: 8px; border-radius: 6px;"></i>
                                                            </div>
                                                            <div class="content">
                                                                <h6 class="counter mb-0">
                                                                    <span class="number"><?php echo $stats['contact_settings']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium">Contact</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Section Navigation -->
                                            <div class="flex gap16 mb-32 flex-wrap">
                                                <a href="?section=general" class="settings-tab <?php echo $section === 'general' ? 'active' : ''; ?>">
                                                    <i class="fas fa-cog"></i>General Settings
                                                </a>
                                                <a href="?section=branding" class="settings-tab <?php echo $section === 'branding' ? 'active' : ''; ?>">
                                                    <i class="fas fa-paint-brush"></i>Branding
                                                </a>
                                                <a href="?section=contact" class="settings-tab <?php echo $section === 'contact' ? 'active' : ''; ?>">
                                                    <i class="fas fa-address-book"></i>Contact
                                                </a>
                                                <a href="?section=system" class="settings-tab <?php echo $section === 'system' ? 'active' : ''; ?>">
                                                    <i class="fas fa-server"></i>System
                                                </a>
                                            </div>

                                            <!-- Settings Content -->
                                            <?php if ($section === 'general'): ?>
                                                <div class="wg-box">
                                                    <div class="flex items-center justify-between mb-20">
                                                        <div class="body-title">General Settings</div>
                                                        <div class="body-text">Basic website configuration</div>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="row">
                                                            <?php foreach ($generalSettings as $setting): ?>
                                                                <div class="col-md-6 mb-20">
                                                                    <label class="body-title-2 mb-8"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                                                    <input type="text" class="tf-input style-1" name="settings[<?php echo $setting['setting_key']; ?>]" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <button type="submit" name="save_settings" class="tf-button style-1 type-fill">
                                                            <i class="fas fa-save"></i>Save General Settings
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($section === 'branding'): ?>
                                                <div class="wg-box">
                                                    <div class="flex items-center justify-between mb-20">
                                                        <div class="body-title">Branding Settings</div>
                                                        <div class="body-text">Visual identity and theme settings</div>
                                                    </div>

                                                    <!-- Theme Selection -->
                                                    <div class="mb-32">
                                                        <h6 class="body-title-2 mb-16">Theme Configuration</h6>
                                                        <form method="POST" class="mb-20">
                                                            <div class="row">
                                                                <div class="col-md-6 mb-20">
                                                                    <label class="body-title-2 mb-8">Default Theme</label>
                                                                    <select class="tf-input style-1" name="settings[default_theme]">
                                                                        <?php
                                                                        $currentTheme = '';
                                                                        foreach ($brandingSettings as $setting) {
                                                                            if ($setting['setting_key'] === 'default_theme') {
                                                                                $currentTheme = $setting['setting_value'];
                                                                                break;
                                                                            }
                                                                        }
                                                                        foreach ($availableThemes as $value => $label): ?>
                                                                            <option value="<?php echo $value; ?>" <?php echo $currentTheme === $value ? 'selected' : ''; ?>>
                                                                                <?php echo $label; ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <button type="submit" name="save_settings" class="tf-button style-1 type-fill">
                                                                <i class="fas fa-save"></i>Save Theme Settings
                                                            </button>
                                                        </form>
                                                    </div>

                                                    <!-- Logo Management -->
                                                    <div class="mb-32">
                                                        <h6 class="body-title-2 mb-16">Logo Management</h6>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <?php
                                                                $currentLogo = '';
                                                                foreach ($brandingSettings as $setting) {
                                                                    if ($setting['setting_key'] === 'site_logo') {
                                                                        $currentLogo = $setting['setting_value'];
                                                                        break;
                                                                    }
                                                                }
                                                                ?>
                                                                <div class="current-logo mb-16">
                                                                    <label class="body-title-2 mb-8">Current Logo</label>
                                                                    <div class="image-preview" style="border: 2px solid #ddd; border-radius: 8px; padding: 16px; text-align: center; background: #f9f9f9;">
                                                                        <?php if ($currentLogo): ?>
                                                                            <img src="../<?php echo htmlspecialchars($currentLogo); ?>" alt="Current Logo" style="max-width: 200px; max-height: 100px; object-fit: contain;">
                                                                            <div class="text-tiny mt-8"><?php echo htmlspecialchars($currentLogo); ?></div>
                                                                        <?php else: ?>
                                                                            <div class="text-muted">No logo set</div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>

                                                                <!-- Upload New Logo -->
                                                                <form method="POST" enctype="multipart/form-data" class="mb-20">
                                                                    <input type="hidden" name="image_type" value="logo">
                                                                    <input type="hidden" name="setting_key" value="site_logo">
                                                                    <label class="body-title-2 mb-8">Upload New Logo</label>
                                                                    <div class="flex gap12 items-end">
                                                                        <div style="flex: 1;">
                                                                            <input type="file" class="tf-input style-1" name="image_file" accept="image/*" required>
                                                                        </div>
                                                                        <button type="submit" name="upload_image" class="tf-button style-1 type-fill">
                                                                            <i class="fas fa-upload"></i>Upload
                                                                        </button>
                                                                    </div>
                                                                </form>

                                                                <!-- Select from Gallery -->
                                                                <?php if (!empty($logoImages)): ?>
                                                                    <form method="POST" class="mb-16">
                                                                        <label class="body-title-2 mb-8">Select from Gallery</label>
                                                                        <div class="flex gap12 items-end">
                                                                            <div style="flex: 1;">
                                                                                <select class="tf-input style-1" name="settings[site_logo]" required>
                                                                                    <option value="">Choose from uploaded logos...</option>
                                                                                    <?php foreach ($logoImages as $logo): ?>
                                                                                        <option value="<?php echo htmlspecialchars($logo['file_path']); ?>" <?php echo $currentLogo === $logo['file_path'] ? 'selected' : ''; ?>>
                                                                                            <?php echo htmlspecialchars($logo['original_name']); ?>
                                                                                        </option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </div>
                                                                            <button type="submit" name="save_settings" class="tf-button style-1 type-outline">
                                                                                <i class="fas fa-check"></i>Select
                                                                            </button>
                                                                        </div>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Background Images Management -->
                                                    <div class="mb-32">
                                                        <h6 class="body-title-2 mb-16">Background Images</h6>

                                                        <!-- Usage Instructions -->
                                                        <div class="alert alert-info" style="background: rgba(54, 162, 235, 0.1); border: 1px solid rgba(54, 162, 235, 0.3); border-radius: 8px; padding: 12px; margin-bottom: 20px; font-size: 13px;">
                                                            <i class="fas fa-info-circle" style="color: #36A2EB; margin-right: 8px;"></i>
                                                            <strong>Background Image Management:</strong>
                                                            <ul style="margin: 8px 0 0 20px; padding: 0;">
                                                                <li><strong>Upload New:</strong> Use the upload button to add a new background image</li>
                                                                <li><strong>Select from Gallery:</strong> Choose an existing image from the dropdown, then click "Set Background"</li>
                                                                <li><strong>Important:</strong> Do not click "Set Background" without selecting an image to avoid clearing the current background</li>
                                                            </ul>
                                                        </div>

                                                        <div class="row">
                                                            <?php
                                                            $backgroundSettings = [
                                                                'header_background' => 'Header Background (Main)',
                                                                'hero_background' => 'Hero Section Background',
                                                                'contact_background' => 'Contact Section Background'
                                                            ];

                                                            foreach ($backgroundSettings as $key => $label):
                                                                // Get current value
                                                                $currentBg = '';
                                                                foreach ($brandingSettings as $setting) {
                                                                    if ($setting['setting_key'] === $key) {
                                                                        $currentBg = $setting['setting_value'];
                                                                        break;
                                                                    }
                                                                }

                                                                // If no setting exists, create it
                                                                if (!$currentBg) {
                                                                    $db->insert('site_settings', [
                                                                        'setting_key' => $key,
                                                                        'setting_value' => '',
                                                                        'setting_type' => 'image',
                                                                        'category' => 'branding',
                                                                        'description' => $label
                                                                    ]);
                                                                }
                                                            ?>
                                                                <div class="col-md-4 mb-20">
                                                                    <label class="body-title-2 mb-8"><?php echo $label; ?></label>

                                                                    <!-- Current Image Preview -->
                                                                    <div class="image-preview mb-12" style="border: 2px solid #ddd; border-radius: 8px; padding: 12px; text-align: center; background: #f9f9f9; min-height: 120px; display: flex; align-items: center; justify-content: center;">
                                                                        <?php if ($currentBg): ?>
                                                                            <div>
                                                                                <img src="../<?php echo htmlspecialchars($currentBg); ?>" alt="<?php echo $label; ?>" style="max-width: 100px; max-height: 80px; object-fit: cover; border-radius: 4px;">
                                                                                <div class="text-tiny mt-4"><?php echo basename($currentBg); ?></div>
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <div class="text-muted">No image set</div>
                                                                        <?php endif; ?>
                                                                    </div>

                                                                    <!-- Upload New -->
                                                                    <form method="POST" enctype="multipart/form-data" class="mb-12">
                                                                        <input type="hidden" name="image_type" value="background">
                                                                        <input type="hidden" name="setting_key" value="<?php echo $key; ?>">
                                                                        <div class="flex gap8">
                                                                            <input type="file" class="tf-input style-1" name="image_file" accept="image/*" style="font-size: 12px;">
                                                                            <button type="submit" name="upload_image" class="tf-button style-1 type-fill" style="padding: 8px 12px; font-size: 12px;">
                                                                                <i class="fas fa-upload"></i>
                                                                            </button>
                                                                        </div>
                                                                    </form>

                                                                    <!-- Select from Gallery -->
                                                                    <form method="POST" onsubmit="return validateGallerySelection(this)">
                                                                        <select class="tf-input style-1" name="settings[<?php echo $key; ?>]" style="font-size: 12px;" required>
                                                                            <option value="">Choose image...</option>
                                                                            <?php foreach ($allGeneralImages as $img): ?>
                                                                                <option value="<?php echo htmlspecialchars($img['file_path']); ?>" <?php echo $currentBg === $img['file_path'] ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($img['original_name']); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                        <button type="submit" name="save_settings" class="tf-button style-1 type-outline mt-8" style="width: 100%; font-size: 12px;">
                                                                            <i class="fas fa-check"></i>Set Background
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>

                                                    <!-- Other Branding Settings -->
                                                    <div class="mb-20">
                                                        <h6 class="body-title-2 mb-16">Other Branding Settings</h6>
                                                        <form method="POST">
                                                            <div class="row">
                                                                <?php foreach ($brandingSettings as $setting): ?>
                                                                    <?php if (!in_array($setting['setting_key'], ['default_theme', 'site_logo', 'header_background', 'hero_background', 'contact_background'])): ?>
                                                                        <div class="col-md-6 mb-20">
                                                                            <label class="body-title-2 mb-8"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                                                            <input type="text" class="tf-input style-1" name="settings[<?php echo $setting['setting_key']; ?>]" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                                        </div>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <button type="submit" name="save_settings" class="tf-button style-1 type-fill">
                                                                <i class="fas fa-save"></i>Save Other Settings
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($section === 'contact'): ?>
                                                <div class="wg-box">
                                                    <div class="flex items-center justify-between mb-20">
                                                        <div class="body-title">Contact Settings</div>
                                                        <div class="body-text">Contact information</div>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="row">
                                                            <?php foreach ($contactSettings as $setting): ?>
                                                                <div class="col-md-6 mb-20">
                                                                    <label class="body-title-2 mb-8"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                                                    <input type="text" class="tf-input style-1" name="settings[<?php echo $setting['setting_key']; ?>]" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <button type="submit" name="save_settings" class="tf-button style-1 type-fill">
                                                            <i class="fas fa-save"></i>Save Contact Settings
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($section === 'system'): ?>
                                                <div class="wg-box">
                                                    <div class="flex items-center justify-between mb-20">
                                                        <div class="body-title">System Settings</div>
                                                        <div class="body-text">Advanced configuration</div>
                                                    </div>

                                                    <!-- Maintenance Mode Section -->
                                                    <div class="mb-32">
                                                        <h6 class="body-title-2 mb-16">Maintenance Mode</h6>

                                                        <!-- Maintenance Mode Warning -->
                                                        <div class="alert alert-info" style="background: rgba(54, 162, 235, 0.1); border: 1px solid rgba(54, 162, 235, 0.3); border-radius: 8px; padding: 12px; margin-bottom: 20px; font-size: 13px;">
                                                            <i class="fas fa-info-circle" style="color: #36A2EB; margin-right: 8px;"></i>
                                                            <strong>Notice:</strong> Maintenance mode controls are currently disabled. The maintenance system is available but not active.
                                                        </div>

                                                        <?php
                                                        // Get maintenance settings
                                                        $maintenanceEnabled = '';
                                                        $maintenanceTitle = '';
                                                        $maintenanceMessage = '';
                                                        $maintenanceEstimate = '';

                                                        foreach ($systemSettings as $setting) {
                                                            switch ($setting['setting_key']) {
                                                                case 'maintenance_mode':
                                                                    $maintenanceEnabled = $setting['setting_value'];
                                                                    break;
                                                                case 'maintenance_title':
                                                                    $maintenanceTitle = $setting['setting_value'];
                                                                    break;
                                                                case 'maintenance_message':
                                                                    $maintenanceMessage = $setting['setting_value'];
                                                                    break;
                                                                case 'maintenance_estimate':
                                                                    $maintenanceEstimate = $setting['setting_value'];
                                                                    break;
                                                            }
                                                        }
                                                        ?>

                                                        <form method="POST">
                                                            <div class="row">
                                                                <!-- Maintenance Mode Toggle -->
                                                                <div class="col-md-12 mb-20">
                                                                    <label class="body-title-2 mb-8">Maintenance Mode <span style="color: #999; font-size: 12px;">(Disabled)</span></label>
                                                                    <select class="tf-input style-1" name="settings[maintenance_mode]" id="maintenance_mode" disabled style="background-color: #f5f5f5; color: #999; cursor: not-allowed;">
                                                                        <option value="false" <?php echo $maintenanceEnabled === 'false' || empty($maintenanceEnabled) ? 'selected' : ''; ?>>Disabled (Website Active)</option>
                                                                        <option value="true" <?php echo $maintenanceEnabled === 'true' ? 'selected' : ''; ?>>Enabled (Show Maintenance Page)</option>
                                                                    </select>
                                                                    <div class="text-tiny mt-4" style="color: #999;">Maintenance mode controls are currently disabled</div>
                                                                </div>

                                                                <!-- Maintenance Page Title -->
                                                                <div class="col-md-6 mb-20">
                                                                    <label class="body-title-2 mb-8">Maintenance Page Title <span style="color: #999; font-size: 12px;">(Disabled)</span></label>
                                                                    <input type="text" class="tf-input style-1" name="settings[maintenance_title]"
                                                                        value="<?php echo htmlspecialchars($maintenanceTitle); ?>"
                                                                        placeholder="Website Under Maintenance" disabled style="background-color: #f5f5f5; color: #999; cursor: not-allowed;">
                                                                </div>

                                                                <!-- Maintenance Message -->
                                                                <div class="col-md-6 mb-20">
                                                                    <label class="body-title-2 mb-8">Maintenance Message <span style="color: #999; font-size: 12px;">(Disabled)</span></label>
                                                                    <textarea class="tf-input style-1" name="settings[maintenance_message]"
                                                                        rows="3" placeholder="We are working on improving our website..." disabled style="background-color: #f5f5f5; color: #999; cursor: not-allowed;"><?php echo htmlspecialchars($maintenanceMessage); ?></textarea>
                                                                </div>

                                                                <!-- Estimated Time -->
                                                                <div class="col-md-6 mb-20">
                                                                    <label class="body-title-2 mb-8">Estimated Return Time <span style="color: #999; font-size: 12px;">(Disabled)</span></label>
                                                                    <input type="text" class="tf-input style-1" name="settings[maintenance_estimate]"
                                                                        value="<?php echo htmlspecialchars($maintenanceEstimate); ?>"
                                                                        placeholder="Expected return within 24 hours" disabled style="background-color: #f5f5f5; color: #999; cursor: not-allowed;">
                                                                </div>

                                                                <!-- Preview Link -->
                                                                <div class="col-md-6 mb-20">
                                                                    <label class="body-title-2 mb-8">Preview Maintenance Page <span style="color: #999; font-size: 12px;">(Disabled)</span></label>
                                                                    <div class="flex gap8">
                                                                        <button type="button" class="tf-button style-1 type-outline" style="padding: 8px 16px; font-size: 12px; background-color: #f5f5f5; color: #999; cursor: not-allowed;" disabled>
                                                                            <i class="fas fa-eye"></i> Preview Page
                                                                        </button>
                                                                        <button type="button" class="tf-button style-1 type-outline" style="padding: 8px 16px; font-size: 12px; background-color: #f5f5f5; color: #999; cursor: not-allowed;" disabled>
                                                                            <i class="fas fa-globe"></i> Test on Frontend
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <button type="button" class="tf-button style-1 type-fill" disabled style="background-color: #f5f5f5; color: #999; cursor: not-allowed; border-color: #ddd;">
                                                                <i class="fas fa-save"></i>Save Maintenance Settings (Disabled)
                                                            </button>
                                                        </form>
                                                    </div>

                                                    <!-- Other System Settings -->
                                                    <div class="mb-20">
                                                        <h6 class="body-title-2 mb-16">Other System Settings</h6>
                                                        <form method="POST">
                                                            <div class="row">
                                                                <?php foreach ($systemSettings as $setting): ?>
                                                                    <?php if (!in_array($setting['setting_key'], ['maintenance_mode', 'maintenance_title', 'maintenance_message', 'maintenance_estimate'])): ?>
                                                                        <div class="col-md-6 mb-20">
                                                                            <label class="body-title-2 mb-8"><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                                                            <?php if ($setting['setting_type'] === 'boolean'): ?>
                                                                                <select class="tf-input style-1" name="settings[<?php echo $setting['setting_key']; ?>]">
                                                                                    <option value="false" <?php echo $setting['setting_value'] === 'false' ? 'selected' : ''; ?>>Disabled</option>
                                                                                    <option value="true" <?php echo $setting['setting_value'] === 'true' ? 'selected' : ''; ?>>Enabled</option>
                                                                                </select>
                                                                            <?php else: ?>
                                                                                <input type="text" class="tf-input style-1" name="settings[<?php echo $setting['setting_key']; ?>]" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <button type="submit" name="save_settings" class="tf-button style-1 type-fill">
                                                                <i class="fas fa-save"></i>Save Other System Settings
                                                            </button>
                                                        </form>
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

    <script src="assets/vendor/js/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="assets/vendor/js/bootstrap-select.min.js"></script>
    <script src="assets/vendor/js/main.js"></script>

    <script>
        function validateGallerySelection(form) {
            const select = form.querySelector('select');
            if (!select.value || select.value.trim() === '') {
                alert('Please select an image from the gallery before clicking "Set Background".');
                return false;
            }
            return true;
        }

        // Add visual feedback for upload success
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to image previews
            const previews = document.querySelectorAll('.image-preview');
            previews.forEach(preview => {
                preview.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                preview.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>

</body>

</html>