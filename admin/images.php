<?php

/**
 * Sun Trading Company - Image Gallery Management
 * Custom Admin System - Developed by Elnakieb
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$auth = new Auth();
$auth->requireAuth();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// Handle actions
$action = $_GET['action'] ?? 'gallery';
$success = '';
$error = '';

// Handle file upload
if ($_POST && isset($_POST['upload_images'])) {
    if (!empty($_FILES['gallery_images']['name'][0])) {
        $uploadDir = '../uploads/images';
        $uploadedFiles = [];
        $uploadCount = 0;

        // Create upload directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Process each uploaded file
        for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
            if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['gallery_images']['name'][$i],
                    'type' => $_FILES['gallery_images']['type'][$i],
                    'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                    'error' => $_FILES['gallery_images']['error'][$i],
                    'size' => $_FILES['gallery_images']['size'][$i]
                ];

                $validation = validateImage($file);
                if ($validation === true) {
                    $fileName = generateFileName($file['name']);
                    $filePath = $uploadDir . '/' . $fileName;

                    if (move_uploaded_file($file['tmp_name'], $filePath)) {
                        // Create thumbnail
                        $thumbDir = '../uploads/thumbnails';
                        if (!is_dir($thumbDir)) {
                            mkdir($thumbDir, 0755, true);
                        }
                        $thumbPath = $thumbDir . '/' . $fileName;
                        createThumbnail($filePath, $thumbPath, 300, 300);

                        // Save to database
                        $fileData = [
                            'original_name' => $file['name'],
                            'file_name' => $fileName,
                            'file_path' => 'uploads/images/' . $fileName,
                            'file_type' => $file['type'],
                            'file_size' => $file['size'],
                            'upload_category' => $_POST['upload_category'] ?? 'general',
                            'uploaded_by' => $currentUser['id']
                        ];

                        $imageId = $db->insert('file_uploads', $fileData);
                        if ($imageId) {
                            $uploadedFiles[] = $file['name'];
                            $uploadCount++;

                            // Log the image upload activity
                            $db->insert('activity_logs', [
                                'user_id' => $currentUser['id'],
                                'action' => 'gallery_upload',
                                'table_name' => 'file_uploads',
                                'record_id' => $imageId,
                                'old_values' => null,
                                'new_values' => json_encode($fileData),
                                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                            ]);
                        }
                    }
                } else {
                    $error .= "Error uploading {$file['name']}: {$validation}<br>";
                }
            }
        }

        if ($uploadCount > 0) {
            $success = $uploadCount . ' image(s) uploaded successfully to gallery!';
        }
    } else {
        $error = 'Please select at least one image to upload.';
    }
}

// Handle edit metadata
if ($_POST && isset($_POST['edit_image_metadata'])) {
    $imageId = (int)$_POST['image_id'];
    $oldImage = $db->fetchOne("SELECT * FROM file_uploads WHERE id = :id", ['id' => $imageId]);

    if ($oldImage) {
        $updateData = [
            'upload_category' => sanitize($_POST['edit_category']),
        ];

        // Only update original_name if provided
        if (!empty($_POST['edit_name'])) {
            $updateData['original_name'] = sanitize($_POST['edit_name']);
        }

        if ($db->update('file_uploads', $updateData, 'id = :id', ['id' => $imageId])) {
            $success = 'Image metadata updated successfully!';

            // Log the metadata update activity
            $db->insert('activity_logs', [
                'user_id' => $currentUser['id'],
                'action' => 'gallery_metadata_update',
                'table_name' => 'file_uploads',
                'record_id' => $imageId,
                'old_values' => json_encode($oldImage),
                'new_values' => json_encode($updateData),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } else {
            $error = 'Failed to update image metadata.';
        }
    } else {
        $error = 'Image not found.';
    }
}

// Handle replace image
if ($_POST && isset($_POST['replace_gallery_image'])) {
    $imageId = (int)$_POST['image_id'];
    $oldImage = $db->fetchOne("SELECT * FROM file_uploads WHERE id = :id", ['id' => $imageId]);

    if ($oldImage && !empty($_FILES['replacement_image']['name'])) {
        $file = [
            'name' => $_FILES['replacement_image']['name'],
            'type' => $_FILES['replacement_image']['type'],
            'tmp_name' => $_FILES['replacement_image']['tmp_name'],
            'error' => $_FILES['replacement_image']['error'],
            'size' => $_FILES['replacement_image']['size']
        ];

        $validation = validateImage($file);
        if ($validation === true) {
            // Keep the same filename and path - just overwrite the existing file
            $existingFilePath = '../' . $oldImage['file_path'];
            $existingThumbPath = '../uploads/thumbnails/' . $oldImage['file_name'];

            // Delete old files first
            if (file_exists($existingFilePath)) {
                unlink($existingFilePath);
            }
            if (file_exists($existingThumbPath)) {
                unlink($existingThumbPath);
            }

            // Move new file to the same path with same filename
            if (move_uploaded_file($file['tmp_name'], $existingFilePath)) {
                // Create new thumbnail with same filename
                createThumbnail($existingFilePath, $existingThumbPath, 300, 300);

                // Update only file metadata in database (keep same filename and path)
                $updateData = [
                    'original_name' => $file['name'], // Update the original name to new file
                    'file_type' => $file['type'],
                    'file_size' => $file['size'],
                    'upload_category' => $_POST['replace_category'] ?? $oldImage['upload_category']
                    // file_name and file_path remain the same to maintain frontend references
                ];

                if ($db->update('file_uploads', $updateData, 'id = :id', ['id' => $imageId])) {
                    $success = 'Image replaced successfully! All frontend references maintained.';

                    // Log the image replacement activity
                    $db->insert('activity_logs', [
                        'user_id' => $currentUser['id'],
                        'action' => 'gallery_image_replace',
                        'table_name' => 'file_uploads',
                        'record_id' => $imageId,
                        'old_values' => json_encode($oldImage),
                        'new_values' => json_encode(array_merge($oldImage, $updateData)),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]);
                } else {
                    $error = 'Failed to update database record.';
                }
            } else {
                $error = 'Failed to replace image file.';
            }
        } else {
            $error = 'Image validation failed: ' . $validation;
        }
    } else {
        $error = 'Image not found or no replacement image provided.';
    }
}

// Handle delete
if ($_POST && isset($_POST['delete_gallery_image'])) {
    $imageId = (int)$_POST['image_id'];
    $imageToDelete = $db->fetchOne("SELECT * FROM file_uploads WHERE id = :id", ['id' => $imageId]);

    if ($imageToDelete) {
        // Delete physical files
        $imagePath = '../' . $imageToDelete['file_path'];
        $thumbPath = '../uploads/thumbnails/' . $imageToDelete['file_name'];

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
        }

        // Delete from database
        if ($db->delete('file_uploads', 'id = :id', ['id' => $imageId])) {
            $success = 'Image deleted successfully from gallery.';

            // Log the image deletion activity
            $db->insert('activity_logs', [
                'user_id' => $currentUser['id'],
                'action' => 'gallery_image_delete',
                'table_name' => 'file_uploads',
                'record_id' => $imageId,
                'old_values' => json_encode($imageToDelete),
                'new_values' => null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } else {
            $error = 'Failed to delete image from database.';
        }
    } else {
        $error = 'Image not found.';
    }
}

// Get gallery statistics
$stats = [];

// Total images
$stats['total_images'] = $db->fetchOne("SELECT COUNT(*) as count FROM file_uploads")['count'] ?? 0;

// Total storage used
$stats['storage_used'] = $db->fetchOne("SELECT SUM(file_size) as total FROM file_uploads")['total'] ?? 0;

// Recent uploads (last 7 days)
$stats['recent_uploads'] = $db->fetchOne("SELECT COUNT(*) as count FROM file_uploads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['count'] ?? 0;

// Categories count
$stats['categories'] = $db->fetchOne("SELECT COUNT(DISTINCT upload_category) as count FROM file_uploads WHERE upload_category IS NOT NULL AND upload_category != ''")['count'] ?? 0;

// Initialize search parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$view_mode = $_GET['view'] ?? 'grid';

// Get images with pagination and filters
$page = (int)($_GET['page'] ?? 1);
$limit = $view_mode === 'list' ? 15 : 24;
$offset = ($page - 1) * $limit;

$whereConditions = [];
$params = [];

if ($category) {
    $whereConditions[] = "upload_category = :category";
    $params['category'] = $category;
}

if ($search) {
    $whereConditions[] = "(original_name LIKE :search OR file_name LIKE :search)";
    $params['search'] = "%{$search}%";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM file_uploads {$whereClause}";
$filteredImagesCount = $db->fetchOne($countSql, $params)['total'] ?? 0;
$totalPages = ceil($filteredImagesCount / $limit);

// Get images
$sql = "SELECT fu.*, au.full_name as uploaded_by_name 
        FROM file_uploads fu 
        LEFT JOIN admin_users au ON fu.uploaded_by = au.id 
        {$whereClause} 
        ORDER BY fu.created_at DESC 
        LIMIT {$limit} OFFSET {$offset}";

$images = $db->fetchAll($sql, $params);

// Get categories for filter
$categories = $db->fetchAll("SELECT DISTINCT upload_category FROM file_uploads WHERE upload_category IS NOT NULL AND upload_category != '' ORDER BY upload_category");

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
    <title>Image Gallery - Sun Trading Admin Panel</title>

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
        .image-gallery-item {
            transition: transform 0.2s ease;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .image-gallery-item:hover {
            transform: translateY(-3px);
        }

        .image-gallery-thumb {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }

        .image-actions {
            position: absolute;
            top: 8px;
            right: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .image-gallery-item:hover .image-actions {
            opacity: 1;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 6px;
            padding: 6px;
            color: #333;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            margin: 0;
        }

        .action-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .action-btn i {
            font-size: 14px;
        }

        /* Specific button styling for different actions */
        .action-btn:nth-child(1) {
            background: rgba(192, 250, 160, 0.95);
            /* Copy - Green */
            color: #161326;
        }

        .action-btn:nth-child(1):hover {
            background: rgba(192, 250, 160, 1);
        }

        .action-btn:nth-child(2) {
            background: rgba(195, 136, 247, 0.95);
            /* Edit - Purple */
            color: #fff;
        }

        .action-btn:nth-child(2):hover {
            background: rgba(195, 136, 247, 1);
        }

        .action-btn:nth-child(3) {
            background: rgba(255, 193, 7, 0.95);
            /* Replace - Yellow */
            color: #161326;
        }

        .action-btn:nth-child(3):hover {
            background: rgba(255, 193, 7, 1);
        }

        .action-btn:nth-child(4) {
            background: rgba(220, 53, 69, 0.95);
            /* Delete - Red */
            color: #fff;
        }

        .action-btn:nth-child(4):hover {
            background: rgba(220, 53, 69, 1);
        }

        /* Ensure buttons are always on top and visible */
        .image-gallery-item {
            position: relative;
            overflow: hidden;
        }

        .image-actions {
            z-index: 10;
        }

        .action-btn {
            position: relative;
            z-index: 11;
        }

        /* Ensure icons are properly displayed */
        .action-btn i:before {
            display: inline-block;
            font-style: normal;
            font-variant: normal;
            text-rendering: auto;
            -webkit-font-smoothing: antialiased;
        }

        /* Debug styling to ensure all buttons are visible */
        .image-actions .action-btn {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        .image-gallery-item:hover .image-actions .action-btn {
            opacity: 1 !important;
        }

        .dropzone-area {
            border: 2px dashed #C0FAA0;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: rgba(192, 250, 160, 0.05);
        }

        .dropzone-area:hover,
        .dropzone-area.dragover {
            border-color: #161326;
            background: rgba(192, 250, 160, 0.1);
        }

        .file-preview {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }

        .modal {
            z-index: 9999;
        }

        .modal-backdrop {
            z-index: 9998;
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
                                        <a href="javascript:void(0);" class="menu-item-button active">
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
                                                <a href="images.php" class="active">
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
                                        <a href="../index.php" target="_blank" class="menu-item-button">
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
                                <i class="fas fa-images" style="font-size: 48px; color: #C0FAA0;"></i>
                            </div>
                            <div class="content">
                                <p class="f12-regular text-White">Gallery Management</p>
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
                                <h6>Image Gallery</h6>
                                <form class="form-search flex-grow" method="GET">
                                    <fieldset class="name">
                                        <input type="text" placeholder="Search images..." class="show-search style-1" name="search" tabindex="2" value="<?php echo htmlspecialchars($search ?? ''); ?>" aria-required="true">
                                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category ?? ''); ?>">
                                        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode ?? 'grid'); ?>">
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
                                                                <a href="#" class="body-title name">Gallery Manager</a>
                                                                <div class="time">Now</div>
                                                            </div>
                                                            <div class="text-tiny desc">Image gallery is ready!</div>
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
                                                            <i class="icon-image"></i>
                                                        </div>
                                                        <div>
                                                            <div class="body-title-2">Image Gallery</div>
                                                            <div class="text-tiny">Manage your media files</div>
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
                                                    <h3>Image Gallery</h3>
                                                    <div class="body-text mt-8">Manage your website images and media files</div>
                                                </div>
                                                <div class="flex gap16">
                                                    <button class="tf-button style-1 type-outline" onclick="toggleView()">
                                                        <i class="<?php echo $view_mode === 'grid' ? 'icon-list' : 'icon-th'; ?>"></i>
                                                        <?php echo $view_mode === 'grid' ? 'List View' : 'Grid View'; ?>
                                                    </button>
                                                    <button class="tf-button style-1 type-fill" onclick="openUploadModal()">
                                                        <i class="icon-upload"></i>
                                                        Upload Images
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Important Notice Alert -->
                                            <div class="alert alert-warning" style="border-left: 4px solid #f39c12; background-color: #fff3cd; border: 1px solid #fceaa7; margin-bottom: 24px;">
                                                <div class="flex items-start gap12">
                                                    <i class="icon-alert-triangle" style="color: #f39c12; font-size: 20px; margin-top: 2px;"></i>
                                                    <div>
                                                        <h6 style="color: #8a6d3b; margin: 0 0 8px 0; font-weight: 600;">🔄 Important: Use "Replace" to Update Images</h6>
                                                        <p style="color: #8a6d3b; margin: 0; font-size: 14px; line-height: 1.5;">
                                                            <strong>To update website images correctly:</strong> Always use the <strong>"Replace"</strong> button instead of deleting and re-uploading.
                                                            This keeps the same filename and maintains all frontend connections (backgrounds, logos, etc.).
                                                            Deleting and re-uploading will break website display because the frontend is connected by filename.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Statistics Dashboard & Filters -->
                                            <div class="flex gap24 mb-32 flex-md-row flex-column">
                                                <!-- Statistics Cards -->
                                                <div class="flex gap16 flex-wrap" style="flex: 2;">
                                                    <div class="wg-card style-1 bg-Primary" style="min-width: 200px; flex: 1; padding: 16px;">
                                                        <div class="flex items-center gap12">
                                                            <div class="icon">
                                                                <i class="fas fa-images" style="font-size: 20px; color: #fff; background: rgba(255,255,255,0.1); padding: 8px; border-radius: 6px;"></i>
                                                            </div>
                                                            <div class="content">
                                                                <h6 class="counter text-White mb-0">
                                                                    <span class="number" data-speed="2000" data-to="<?php echo $stats['total_images']; ?>" data-inviewport="yes"><?php echo $stats['total_images']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium text-White">Total Images</div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="wg-card" style="min-width: 200px; flex: 1; padding: 16px;">
                                                        <div class="flex items-center gap12">
                                                            <div class="icon">
                                                                <i class="fas fa-hdd" style="font-size: 20px; color: #161326; background: #C0FAA0; padding: 8px; border-radius: 6px;"></i>
                                                            </div>
                                                            <div class="content">
                                                                <h6 class="counter mb-0">
                                                                    <span class="text" data-speed="2000" data-inviewport="yes"><?php echo formatFileSize($stats['storage_used']); ?></span>
                                                                </h6>
                                                                <div class="f12-medium">Storage Used</div>
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
                                                                    <span class="number" data-speed="2000" data-to="<?php echo $stats['recent_uploads']; ?>" data-inviewport="yes"><?php echo $stats['recent_uploads']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium">Recent (7d)</div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="wg-card style-1 bg-YellowGreen" style="min-width: 200px; flex: 1; padding: 16px;">
                                                        <div class="flex items-center gap12">
                                                            <div class="icon">
                                                                <i class="fas fa-tags" style="font-size: 20px; color: #fff; background: #161326; padding: 8px; border-radius: 6px;"></i>
                                                            </div>
                                                            <div class="content">
                                                                <h6 class="counter mb-0">
                                                                    <span class="number" data-speed="2000" data-to="<?php echo $stats['categories']; ?>" data-inviewport="yes"><?php echo $stats['categories']; ?></span>
                                                                </h6>
                                                                <div class="f12-medium">Categories</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Gallery Filters -->
                                                <div class="wg-box style-1 bg-Gainsboro shadow-none widget-tabs" style="flex: 1; min-width: 300px;">
                                                    <div>
                                                        <div class="title mb-16">
                                                            <div class="label-01">Gallery Filters</div>
                                                        </div>
                                                        <form method="GET" class="flex flex-column gap12">
                                                            <fieldset class="search">
                                                                <input type="text" class="tf-input style-1" name="search"
                                                                    value="<?php echo htmlspecialchars($search); ?>"
                                                                    placeholder="Search images...">
                                                            </fieldset>
                                                            <fieldset class="category">
                                                                <select class="tf-input style-1" name="category">
                                                                    <option value="">All Categories</option>
                                                                    <?php foreach ($categories as $cat): ?>
                                                                        <option value="<?php echo htmlspecialchars($cat['upload_category']); ?>"
                                                                            <?php echo $category === $cat['upload_category'] ? 'selected' : ''; ?>>
                                                                            <?php echo ucfirst(htmlspecialchars($cat['upload_category'])); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </fieldset>
                                                            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
                                                            <div class="flex gap8">
                                                                <button type="submit" class="tf-button style-1 type-fill" style="flex: 1;">
                                                                    <i class="fas fa-search"></i>
                                                                    Filter
                                                                </button>
                                                                <a href="images.php" class="tf-button style-1 type-outline" style="flex: 1;">
                                                                    <i class="fas fa-times"></i>
                                                                    Clear
                                                                </a>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Images Gallery -->
                                            <?php if (!empty($images)): ?>
                                                <?php if ($view_mode === 'grid'): ?>
                                                    <!-- Grid View -->
                                                    <div class="wg-box">
                                                        <div class="flex items-center justify-between mb-20">
                                                            <div class="body-title">Gallery Images</div>
                                                            <div class="body-text">Total: <?php echo count($images); ?> images</div>
                                                        </div>
                                                        <div class="grid-4 gap16">
                                                            <?php foreach ($images as $image): ?>
                                                                <div class="image-gallery-item position-relative" onclick="viewImageModal('<?php echo htmlspecialchars($image['file_path']); ?>', '<?php echo htmlspecialchars($image['original_name']); ?>')">
                                                                    <div class="image position-relative">
                                                                        <?php
                                                                        $thumbnailPath = "../uploads/thumbnails/{$image['file_name']}";
                                                                        $displayPath = file_exists($thumbnailPath) ? $thumbnailPath : "../{$image['file_path']}";
                                                                        ?>
                                                                        <img src="<?php echo $displayPath; ?>"
                                                                            alt="<?php echo htmlspecialchars($image['original_name']); ?>"
                                                                            class="image-gallery-thumb"
                                                                            onerror="this.src='../images/default-placeholder.png'">

                                                                        <div class="image-actions">
                                                                            <button class="action-btn" onclick="event.stopPropagation(); copyImageUrl('<?php echo htmlspecialchars($image['file_path']); ?>')" title="Copy URL">
                                                                                <i class="fas fa-copy"></i>
                                                                            </button>
                                                                            <button class="action-btn" onclick="event.stopPropagation(); editImageModal(<?php echo $image['id']; ?>, '<?php echo htmlspecialchars($image['original_name']); ?>', '<?php echo htmlspecialchars($image['upload_category']); ?>')" title="Edit">
                                                                                <i class="fas fa-edit"></i>
                                                                            </button>
                                                                            <button class="action-btn" onclick="event.stopPropagation(); replaceImageModal(<?php echo $image['id']; ?>, '<?php echo htmlspecialchars($image['original_name']); ?>', '<?php echo htmlspecialchars($image['upload_category']); ?>')" title="Replace - Keeps same filename & frontend connections" style="background-color: #f39c12; color: white;">
                                                                                <i class="fas fa-exchange-alt"></i>
                                                                            </button>
                                                                            <button class="action-btn" onclick="event.stopPropagation(); deleteGalleryImage(<?php echo $image['id']; ?>)" title="Delete">
                                                                                <i class="fas fa-trash"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                    <div class="content mt-12">
                                                                        <h6 class="body-title-2 text-truncate" title="<?php echo htmlspecialchars($image['original_name']); ?>">
                                                                            <?php echo htmlspecialchars(substr($image['original_name'], 0, 20)); ?><?php echo strlen($image['original_name']) > 20 ? '...' : ''; ?>
                                                                        </h6>
                                                                        <div class="text-tiny mt-3">
                                                                            <div><strong>Size:</strong> <?php echo formatFileSize($image['file_size']); ?></div>
                                                                            <div><strong>Category:</strong> <?php echo ucfirst(htmlspecialchars($image['upload_category'])); ?></div>
                                                                            <div><strong>Uploaded:</strong> <?php echo timeAgo($image['created_at']); ?></div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- List View -->
                                                    <div class="wg-box">
                                                        <div class="flex items-center justify-between">
                                                            <div class="body-title">Gallery Images</div>
                                                            <div class="body-text">Total: <?php echo count($images); ?> images</div>
                                                        </div>
                                                        <div class="wg-table table-all-user">
                                                            <div class="table-responsive">
                                                                <table class="table table-striped table-bordered">
                                                                    <thead>
                                                                        <tr>
                                                                            <th class="text-center">Preview</th>
                                                                            <th>Filename</th>
                                                                            <th class="text-center">Category</th>
                                                                            <th class="text-center">Size</th>
                                                                            <th class="text-center">Uploaded</th>
                                                                            <th class="text-center">Actions</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($images as $image): ?>
                                                                            <tr>
                                                                                <td class="text-center">
                                                                                    <div class="image">
                                                                                        <?php
                                                                                        $thumbnailPath = "../uploads/thumbnails/{$image['file_name']}";
                                                                                        $displayPath = file_exists($thumbnailPath) ? $thumbnailPath : "../{$image['file_path']}";
                                                                                        ?>
                                                                                        <img src="<?php echo $displayPath; ?>"
                                                                                            alt="<?php echo htmlspecialchars($image['original_name']); ?>"
                                                                                            class="image-product" style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px;"
                                                                                            onclick="viewImageModal('<?php echo htmlspecialchars($image['file_path']); ?>', '<?php echo htmlspecialchars($image['original_name']); ?>')"
                                                                                            onerror="this.src='../images/default-placeholder.png'">
                                                                                    </div>
                                                                                </td>
                                                                                <td>
                                                                                    <div class="body-title"><?php echo htmlspecialchars($image['original_name']); ?></div>
                                                                                    <div class="text-tiny mt-3"><?php echo htmlspecialchars($image['file_name']); ?></div>
                                                                                </td>
                                                                                <td class="text-center">
                                                                                    <div class="block-available">
                                                                                        <span class="body-title-2"><?php echo ucfirst(htmlspecialchars($image['upload_category'])); ?></span>
                                                                                    </div>
                                                                                </td>
                                                                                <td class="text-center">
                                                                                    <div class="body-title-2"><?php echo formatFileSize($image['file_size']); ?></div>
                                                                                </td>
                                                                                <td class="text-center">
                                                                                    <div class="body-text"><?php echo timeAgo($image['created_at']); ?></div>
                                                                                    <div class="text-tiny"><?php echo htmlspecialchars($image['uploaded_by_name'] ?? 'Unknown'); ?></div>
                                                                                </td>
                                                                                <td class="text-center">
                                                                                    <div class="list-icon-function">
                                                                                        <a href="javascript:void(0);" onclick="copyImageUrl('<?php echo htmlspecialchars($image['file_path']); ?>')" title="Copy URL">
                                                                                            <div class="item copy">
                                                                                                <i class="fas fa-copy"></i>
                                                                                            </div>
                                                                                        </a>
                                                                                        <a href="javascript:void(0);" onclick="editImageModal(<?php echo $image['id']; ?>, '<?php echo htmlspecialchars($image['original_name']); ?>', '<?php echo htmlspecialchars($image['upload_category']); ?>')" title="Edit">
                                                                                            <div class="item edit">
                                                                                                <i class="fas fa-edit"></i>
                                                                                            </div>
                                                                                        </a>
                                                                                        <a href="javascript:void(0);" onclick="replaceImageModal(<?php echo $image['id']; ?>, '<?php echo htmlspecialchars($image['original_name']); ?>', '<?php echo htmlspecialchars($image['upload_category']); ?>')" title="Replace - Keeps same filename & frontend connections" style="color: #f39c12;">
                                                                                            <div class="item sync">
                                                                                                <i class="fas fa-exchange-alt"></i>
                                                                                            </div>
                                                                                        </a>
                                                                                        <a href="javascript:void(0);" onclick="deleteGalleryImage(<?php echo $image['id']; ?>)" title="Delete">
                                                                                            <div class="item trash">
                                                                                                <i class="fas fa-trash"></i>
                                                                                            </div>
                                                                                        </a>
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

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel">Upload Images</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="dropzone-area" id="dropzoneArea">
                            <div class="dropzone-content">
                                <i class="icon-upload" style="font-size: 48px; color: #C0FAA0; margin-bottom: 16px;"></i>
                                <h5>Drag & Drop Images Here</h5>
                                <p class="text-muted">or click to browse files</p>
                                <input type="file" id="galleryImages" name="gallery_images[]" multiple accept="image/*" style="display: none;">
                                <button type="button" class="tf-button style-1 type-outline mt-3" onclick="document.getElementById('galleryImages').click()">
                                    <i class="icon-folder"></i> Browse Files
                                </button>
                            </div>
                        </div>

                        <div class="mt-3" id="filePreview"></div>

                        <div class="mt-3">
                            <label for="uploadCategory" class="form-label">Category</label>
                            <select class="tf-input style-1" name="upload_category" id="uploadCategory">
                                <option value="general">General</option>
                                <option value="products">Products</option>
                                <option value="banners">Banners</option>
                                <option value="logos">Logos</option>
                                <option value="thumbnails">Thumbnails</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="tf-button style-1 type-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="upload_images" class="tf-button style-1 type-fill">
                            <i class="icon-upload"></i> Upload Images
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Image Modal -->
    <div class="modal fade" id="viewImageModal" tabindex="-1" aria-labelledby="viewImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewImageModalLabel">View Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="viewImageSrc" src="" alt="" class="img-fluid" style="max-height: 80vh; border-radius: 8px;">
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Image Modal -->
    <div class="modal fade" id="editImageModal" tabindex="-1" aria-labelledby="editImageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editImageModalLabel">Edit Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="image_id" id="editImageId">

                        <div class="mb-3">
                            <label for="editImageName" class="form-label">Image Name</label>
                            <input type="text" class="tf-input style-1" name="edit_name" id="editImageName" placeholder="Leave blank to keep current name">
                        </div>

                        <div class="mb-3">
                            <label for="editImageCategory" class="form-label">Category</label>
                            <select class="tf-input style-1" name="edit_category" id="editImageCategory">
                                <option value="general">General</option>
                                <option value="products">Products</option>
                                <option value="banners">Banners</option>
                                <option value="logos">Logos</option>
                                <option value="thumbnails">Thumbnails</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="tf-button style-1 type-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_image_metadata" class="tf-button style-1 type-fill">
                            <i class="icon-edit1"></i> Update Image
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Replace Image Modal -->
    <div class="modal fade" id="replaceImageModal" tabindex="-1" aria-labelledby="replaceImageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="replaceImageModalLabel">Replace Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="image_id" id="replaceImageId">

                        <div class="alert alert-info">
                            <i class="icon-info"></i>
                            <strong>Replace Image:</strong> The new image will replace the current file keeping the same filename and path. All frontend references will be maintained automatically.
                        </div>

                        <div class="mb-3">
                            <label for="replacementImage" class="form-label">New Image File</label>
                            <input type="file" class="tf-input style-1" name="replacement_image" id="replacementImage" accept="image/*" required>
                        </div>

                        <div class="mb-3">
                            <label for="replaceImageCategory" class="form-label">Category</label>
                            <select class="tf-input style-1" name="replace_category" id="replaceImageCategory">
                                <option value="general">General</option>
                                <option value="products">Products</option>
                                <option value="banners">Banners</option>
                                <option value="logos">Logos</option>
                                <option value="thumbnails">Thumbnails</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="tf-button style-1 type-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="replace_gallery_image" class="tf-button style-1 type-fill">
                            <i class="icon-arrow-swap"></i> Replace Image
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteImageModal" tabindex="-1" aria-labelledby="deleteImageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteImageModalLabel">Delete Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="image_id" id="deleteImageId">

                        <div class="alert alert-danger">
                            <i class="icon-alert-triangle"></i>
                            <strong>Warning:</strong> This action cannot be undone. The image file will be permanently deleted.
                        </div>

                        <p>Are you sure you want to delete this image?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="tf-button style-1 type-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_gallery_image" class="tf-button style-1 type-fill bg-danger">
                            <i class="icon-trash"></i> Delete Image
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="divider"></div>
    <div class="flex items-center justify-between flex-wrap gap10 mt-20">
        <div class="text-tiny">Showing <?php echo count($images); ?> of <?php echo $filteredImagesCount; ?> entries</div>
        <div class="wg-pagination">
            <?php echo generatePagination($page, $totalPages, 'images.php', ['search' => $search, 'category' => $category, 'view' => $view_mode]); ?>
        </div>
    </div>
<?php else: ?>
    <div class="wg-box text-center">
        <div class="image">
            <i class="icon-image" style="font-size: 64px; color: var(--gray-color);"></i>
        </div>
        <div class="body-title mt-16">No images found</div>
        <div class="body-text">Upload your first images to get started with the gallery!</div>
        <div class="mt-20">
            <button class="tf-button style-1 type-fill" onclick="openUploadModal()">
                <i class="icon-upload"></i>
                Upload Images
            </button>
        </div>
    </div>
<?php endif; ?>

<!-- Javascript -->
<script src="assets/vendor/js/jquery.min.js"></script>
<script src="assets/vendor/js/bootstrap.min.js"></script>
<script src="assets/vendor/js/bootstrap-select.min.js"></script>
<script src="assets/vendor/js/main.js"></script>

<!-- Custom Gallery JavaScript -->
<script>
    // View mode toggle
    function toggleView() {
        const currentView = new URLSearchParams(window.location.search).get('view') || 'grid';
        const newView = currentView === 'grid' ? 'list' : 'grid';
        const url = new URL(window.location);
        url.searchParams.set('view', newView);
        window.location.href = url.toString();
    }

    // Open upload modal
    function openUploadModal() {
        const modal = new bootstrap.Modal(document.getElementById('uploadModal'));
        modal.show();
    }

    // View image modal
    function viewImageModal(imagePath, imageName) {
        document.getElementById('viewImageSrc').src = '../' + imagePath;
        document.getElementById('viewImageModalLabel').textContent = imageName;
        const modal = new bootstrap.Modal(document.getElementById('viewImageModal'));
        modal.show();
    }

    // Edit image modal
    function editImageModal(imageId, imageName, imageCategory) {
        document.getElementById('editImageId').value = imageId;
        document.getElementById('editImageName').value = '';
        document.getElementById('editImageName').placeholder = imageName;
        document.getElementById('editImageCategory').value = imageCategory;
        const modal = new bootstrap.Modal(document.getElementById('editImageModal'));
        modal.show();
    }

    // Replace image modal
    function replaceImageModal(imageId, imageName, imageCategory) {
        document.getElementById('replaceImageId').value = imageId;
        document.getElementById('replaceImageCategory').value = imageCategory;
        document.getElementById('replaceImageModalLabel').textContent = 'Replace: ' + imageName;
        const modal = new bootstrap.Modal(document.getElementById('replaceImageModal'));
        modal.show();
    }

    // Delete image
    function deleteGalleryImage(imageId) {
        document.getElementById('deleteImageId').value = imageId;
        const modal = new bootstrap.Modal(document.getElementById('deleteImageModal'));
        modal.show();
    }

    // Copy image URL
    function copyImageUrl(imagePath) {
        const fullUrl = window.location.origin + '/' + imagePath;
        navigator.clipboard.writeText(fullUrl).then(() => {
            // Show success notification
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
            alert.innerHTML = `
                    <i class="icon-check-circle"></i> Image URL copied to clipboard!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
            document.body.appendChild(alert);
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 3000);
        }).catch(() => {
            alert('Failed to copy URL to clipboard');
        });
    }

    // Drag and drop functionality
    document.addEventListener('DOMContentLoaded', function() {
        const dropzone = document.getElementById('dropzoneArea');
        const fileInput = document.getElementById('galleryImages');
        const filePreview = document.getElementById('filePreview');

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Highlight drop area when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropzone.classList.add('dragover');
        }

        function unhighlight(e) {
            dropzone.classList.remove('dragover');
        }

        // Handle dropped files
        dropzone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            handleFiles(files);
        }

        // Handle file input change
        fileInput.addEventListener('change', function(e) {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            filePreview.innerHTML = '';
            [...files].forEach(previewFile);
        }

        function previewFile(file) {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onloadend = function() {
                const div = document.createElement('div');
                div.className = 'd-inline-block me-2 mb-2';
                div.innerHTML = `
                        <img src="${reader.result}" class="file-preview" alt="Preview">
                        <div class="text-tiny text-center mt-1">${file.name}</div>
                    `;
                filePreview.appendChild(div);
            }
        }

        // Click to browse
        dropzone.addEventListener('click', function() {
            fileInput.click();
        });
    });
</script>

</body>

</html>