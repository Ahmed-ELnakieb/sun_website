<?php

/**
 * Sun Trading Company - Product Management
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Helper function to handle single image upload
function handleSingleImageUpload($productId, $file, $db, $currentUser = null)
{
    $uploadDir = '../uploads/products';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Get existing images for this product and delete them
    $existingImages = $db->fetchAll(
        "SELECT * FROM product_images WHERE product_id = :id",
        ['id' => $productId]
    );

    // Delete existing images from filesystem and database
    foreach ($existingImages as $existingImg) {
        // Delete main image file
        $mainImagePath = '../' . $existingImg['image_path'];
        if (file_exists($mainImagePath)) {
            unlink($mainImagePath);
        }

        // Delete thumbnail if it exists
        $thumbnailPath = '../uploads/thumbnails/' . basename($existingImg['image_path']);
        if (file_exists($thumbnailPath)) {
            unlink($thumbnailPath);
        }
    }

    // Delete all existing image records from database
    $db->delete('product_images', 'product_id = :product_id', ['product_id' => $productId]);

    // Validate and upload new image
    $validation = validateImage($file);
    if ($validation !== true) {
        return $validation;
    }

    $fileName = generateFileName($file['name']);
    $filePath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return "Failed to upload image file.";
    }

    // Create thumbnail
    $thumbDir = '../uploads/thumbnails';
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }
    $thumbPath = $thumbDir . '/' . $fileName;
    createThumbnail($filePath, $thumbPath, 300, 300);

    // Save to database - always set as primary
    $imageData = [
        'product_id' => $productId,
        'image_path' => 'uploads/products/' . $fileName,
        'image_name' => $file['name'],
        'alt_text_en' => '',
        'alt_text_ar' => '',
        'is_primary' => 1, // Always set as primary
        'sort_order' => 0,
        'file_size' => $file['size']
    ];

    $imageId = $db->insert('product_images', $imageData);
    if ($imageId) {
        // Log the image upload activity
        if ($currentUser) {
            $db->insert('activity_logs', [
                'user_id' => $currentUser['id'],
                'action' => 'image_upload',
                'table_name' => 'product_images',
                'record_id' => $imageId,
                'old_values' => null,
                'new_values' => json_encode($imageData),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        }
        return true;
    } else {
        return "Failed to save image to database.";
    }
}

$auth = new Auth();
$auth->requireAuth();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

$action = $_GET['action'] ?? 'list';
$productId = (int)($_GET['id'] ?? 0);
$success = '';
$error = '';

// Initialize search parameters (used in header and filters)
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';

// Initialize other variables
$categories = [];
$products = [];
$totalPages = 0;
$page = 1;
$flashMessages = function_exists('getFlashMessages') ? getFlashMessages() : [];

// Get statistics for dashboard
try {
    $totalProductsResult = $db->fetchOne("SELECT COUNT(*) as count FROM products");
    $totalProducts = (int)($totalProductsResult['count'] ?? 0);
} catch (Exception $e) {
    $totalProducts = 0;
    $error = 'Database error: ' . $e->getMessage();
}

// Get categories for filter
$categories = $db->fetchAll("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");

// Handle form submissions
if ($_POST) {
    // DEBUG: Log all POST and FILES data
    error_log("=== FORM SUBMISSION DEBUG ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    error_log("Product ID: " . $productId);
    error_log("================================");

    // Handle product save/update (main form)
    if (isset($_POST['save_product'])) {
        $data = [
            'name_ar' => sanitize($_POST['name_ar']),
            'name_en' => sanitize($_POST['name_en']),
            'description_ar' => sanitize($_POST['description_ar']),
            'description_en' => sanitize($_POST['description_en']),
            'features_ar' => sanitize($_POST['features_ar']),
            'features_en' => sanitize($_POST['features_en']),
            'origin_ar' => sanitize($_POST['origin_ar']),
            'origin_en' => sanitize($_POST['origin_en']),
            'uses_ar' => sanitize($_POST['uses_ar']),
            'uses_en' => sanitize($_POST['uses_en']),
            'color_ar' => sanitize($_POST['color_ar']),
            'color_en' => sanitize($_POST['color_en']),
            'grain_type_ar' => sanitize($_POST['grain_type_ar']),
            'grain_type_en' => sanitize($_POST['grain_type_en']),
            'quality_info_ar' => sanitize($_POST['quality_info_ar']),
            'quality_info_en' => sanitize($_POST['quality_info_en']),
            'category' => sanitize($_POST['category']),
            'price' => !empty($_POST['price']) ? (float)$_POST['price'] : null,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        if ($productId > 0) {
            // Update existing product - first verify product exists
            $existingProduct = $db->fetchOne("SELECT id FROM products WHERE id = :id", ['id' => $productId]);

            if (!$existingProduct) {
                $error = 'Product not found with ID: ' . $productId;
            } else {
                // DEBUG: Log all POST and FILES data
                error_log("DEBUG: Attempting to update product ID: $productId");
                error_log("DEBUG: Update data: " . print_r($data, true));

                $updateResult = $db->update('products', $data, 'id = :id', ['id' => $productId]);
                error_log("DEBUG: Update result: " . ($updateResult !== false ? "SUCCESS (rows: $updateResult)" : "FAILED"));

                // Consider the update successful if no error occurred (even if 0 rows affected)
                if ($updateResult !== false) {
                    $success = 'Product updated successfully!';

                    // Handle image upload for existing product (from main form only)
                    error_log("DEBUG: Checking for image upload...");
                    error_log("DEBUG: product_image name: " . ($_FILES['product_image']['name'] ?? 'NOT SET'));
                    error_log("DEBUG: product_image error: " . ($_FILES['product_image']['error'] ?? 'NOT SET'));
                    error_log("DEBUG: delete_image flag: " . ($_POST['delete_image'] ?? 'NOT SET'));

                    // Handle image deletion if requested
                    if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
                        error_log("DEBUG: Image deletion requested");
                        $existingImages = $db->fetchAll("SELECT * FROM product_images WHERE product_id = :id", ['id' => $productId]);

                        foreach ($existingImages as $existingImg) {
                            // Delete main image file
                            $mainImagePath = '../' . $existingImg['image_path'];
                            if (file_exists($mainImagePath)) {
                                unlink($mainImagePath);
                            }

                            // Delete thumbnail if it exists
                            $thumbnailPath = '../uploads/thumbnails/' . basename($existingImg['image_path']);
                            if (file_exists($thumbnailPath)) {
                                unlink($thumbnailPath);
                            }
                        }

                        // Delete all existing image records from database
                        $db->delete('product_images', 'product_id = :product_id', ['product_id' => $productId]);
                        $success .= " Image deleted successfully!";
                    }
                    // Handle image upload
                    else if (!empty($_FILES['product_image']['name']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                        error_log("DEBUG: Image upload detected, calling handleSingleImageUpload");
                        $uploadResult = handleSingleImageUpload($productId, $_FILES['product_image'], $db, $currentUser);
                        if ($uploadResult !== true) {
                            $error = "Product updated but image upload failed: " . $uploadResult;
                        } else {
                            $success .= " Image uploaded successfully!";
                        }
                    } else {
                        error_log("DEBUG: No image upload or deletion detected");
                    }
                } else {
                    $error = 'Failed to update product - database error.';
                }
            }
        } else {
            // Create new product
            try {
                $newProductId = $db->insert('products', $data);
                if ($newProductId) {
                    $success = 'Product created successfully!';
                    $productId = $newProductId;

                    // Log the product creation activity
                    $db->insert('activity_logs', [
                        'user_id' => $currentUser['id'],
                        'action' => 'product_create',
                        'table_name' => 'products',
                        'record_id' => $newProductId,
                        'old_values' => null,
                        'new_values' => json_encode($data),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]);

                    // Handle image upload for new product (from main form only)
                    if (!empty($_FILES['product_image']['name']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = handleSingleImageUpload($newProductId, $_FILES['product_image'], $db, $currentUser);
                        if ($uploadResult !== true) {
                            $error = "Product created but image upload failed: " . $uploadResult;
                            $success .= ' (but image upload failed: ' . $uploadResult . ')';
                        } else {
                            $success .= ' Image uploaded successfully!';
                        }
                    }

                    $action = 'edit';
                } else {
                    $error = 'Failed to create product. Please check your input data.';
                }
            } catch (Exception $e) {
                $error = 'Database error while creating product: ' . $e->getMessage();
                error_log("Product creation error: " . $e->getMessage());
            }
        }
    }

    if (isset($_POST['delete_product'])) {
        $deleteId = (int)$_POST['product_id'];

        // Get product data before deletion for logging
        $productToDelete = $db->fetchOne("SELECT * FROM products WHERE id = :id", ['id' => $deleteId]);

        // Delete product images first
        $images = $db->fetchAll("SELECT * FROM product_images WHERE product_id = :id", ['id' => $deleteId]);
        foreach ($images as $image) {
            $imagePath = '../' . $image['image_path'];
            $thumbPath = '../uploads/thumbnails/' . basename($image['image_path']);

            if (file_exists($imagePath)) unlink($imagePath);
            if (file_exists($thumbPath)) unlink($thumbPath);
        }

        $db->delete('product_images', 'product_id = :id', ['id' => $deleteId]);

        // Delete product
        if ($db->delete('products', 'id = :id', ['id' => $deleteId])) {
            $success = 'Product deleted successfully!';

            // Log the product deletion activity
            $db->insert('activity_logs', [
                'user_id' => $currentUser['id'],
                'action' => 'product_delete',
                'table_name' => 'products',
                'record_id' => $deleteId,
                'old_values' => json_encode($productToDelete),
                'new_values' => null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } else {
            $error = 'Failed to delete product.';
        }
    }

    // Handle separate image upload form (independent of product update)
    if (isset($_POST['upload_product_images'])) {
        // Validate product ID first
        if ($productId <= 0) {
            $error = "Invalid product ID. Please save the product first before uploading images.";
        } else {
            // Handle both array and single file inputs
            $files = [];
            if (isset($_FILES['product_images']) && is_array($_FILES['product_images']['name'])) {
                // Multiple files array format
                if (!empty($_FILES['product_images']['name'][0])) {
                    $files[] = [
                        'name' => $_FILES['product_images']['name'][0],
                        'type' => $_FILES['product_images']['type'][0],
                        'tmp_name' => $_FILES['product_images']['tmp_name'][0],
                        'error' => $_FILES['product_images']['error'][0],
                        'size' => $_FILES['product_images']['size'][0]
                    ];
                }
            } else if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'])) {
                // Single file format
                $files[] = $_FILES['product_images'];
            }

            if (!empty($files)) {
                $uploadDir = '../uploads/products';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Get existing images for this product
                try {
                    $existingImages = $db->fetchAll(
                        "SELECT * FROM product_images WHERE product_id = :id",
                        ['id' => $productId]
                    );

                    // Delete existing images from filesystem and database
                    foreach ($existingImages as $existingImg) {
                        // Delete main image file
                        $mainImagePath = '../' . $existingImg['image_path'];
                        if (file_exists($mainImagePath)) {
                            unlink($mainImagePath);
                        }

                        // Delete thumbnail if it exists
                        $thumbnailPath = '../uploads/thumbnails/' . basename($existingImg['image_path']);
                        if (file_exists($thumbnailPath)) {
                            unlink($thumbnailPath);
                        }
                    }

                    // Delete all existing image records from database
                    $db->delete('product_images', 'product_id = :product_id', ['product_id' => $productId]);

                    // Process the uploaded file
                    $file = $files[0]; // Take only the first file for single image per product

                    if ($file['error'] === UPLOAD_ERR_OK) {
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
                                $thumbnailCreated = createThumbnail($filePath, $thumbPath, 300, 300);

                                if (!$thumbnailCreated) {
                                    error_log("Warning: Failed to create thumbnail for {$fileName}");
                                }

                                $imageData = [
                                    'product_id' => $productId,
                                    'image_path' => 'uploads/products/' . $fileName,
                                    'image_name' => $file['name'],
                                    'alt_text_en' => sanitize($_POST['alt_text_en'] ?? ''),
                                    'alt_text_ar' => sanitize($_POST['alt_text_ar'] ?? ''),
                                    'is_primary' => 1, // Always set as primary
                                    'sort_order' => 0,
                                    'file_size' => $file['size']
                                ];

                                $imageId = $db->insert('product_images', $imageData);
                                if ($imageId) {
                                    $success = "Image uploaded successfully! File: {$fileName}";

                                    // Log the image upload activity
                                    $db->insert('activity_logs', [
                                        'user_id' => $currentUser['id'],
                                        'action' => 'image_upload',
                                        'table_name' => 'product_images',
                                        'record_id' => $imageId,
                                        'old_values' => null,
                                        'new_values' => json_encode($imageData),
                                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                                    ]);

                                    // Refresh product images after upload
                                    $productImages = $db->fetchAll(
                                        "SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order, id",
                                        ['id' => $productId]
                                    );
                                } else {
                                    $error = "Failed to save image to database.";
                                    // Clean up uploaded file if database save failed
                                    if (file_exists($filePath)) {
                                        unlink($filePath);
                                    }
                                    if (file_exists($thumbPath)) {
                                        unlink($thumbPath);
                                    }
                                }
                            } else {
                                $error = "Failed to move uploaded file. Check directory permissions.";
                            }
                        } else {
                            $error = "Image validation failed: " . $validation;
                        }
                    } else {
                        $uploadErrors = [
                            UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit.',
                            UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit.',
                            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
                        ];
                        $error = "File upload error: " . ($uploadErrors[$file['error']] ?? "Unknown error code {$file['error']}");
                    }
                } catch (Exception $e) {
                    $error = "Database error during image upload: " . $e->getMessage();
                    error_log("Image upload error: " . $e->getMessage());
                }
            } else {
                $error = "No file was selected for upload.";
            }
        }
    }
}

// Get product data for editing
$product = null;
$productImages = [];
if ($productId > 0) {
    $product = $db->fetchOne("SELECT * FROM products WHERE id = :id", ['id' => $productId]);
    if (!$product && $action !== 'list') {
        $error = 'Product not found.';
        $action = 'list';
    } else {
        $productImages = $db->fetchAll(
            "SELECT * FROM product_images WHERE product_id = :id ORDER BY sort_order, id",
            ['id' => $productId]
        );
    }
}

// Get products list with pagination
if ($action === 'list') {
    $page = (int)($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $whereConditions = [];
    $params = [];

    if ($search) {
        $whereConditions[] = "(name_ar LIKE :search OR name_en LIKE :search OR description_ar LIKE :search OR description_en LIKE :search)";
        $params['search'] = "%{$search}%";
    }

    if ($category) {
        $whereConditions[] = "category = :category";
        $params['category'] = $category;
    }

    if ($status === 'active') {
        $whereConditions[] = "is_active = 1";
    } elseif ($status === 'inactive') {
        $whereConditions[] = "is_active = 0";
    } elseif ($status === 'featured') {
        $whereConditions[] = "is_featured = 1";
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM products {$whereClause}";
    $filteredProductsCount = $db->fetchOne($countSql, $params)['total'] ?? 0;
    $totalPages = ceil($filteredProductsCount / $limit);

    // Get products with primary image
    $sql = "SELECT p.*, 
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
            (SELECT COUNT(*) FROM product_images pi WHERE pi.product_id = p.id) as image_count
            FROM products p 
            {$whereClause} 
            ORDER BY p.created_at DESC 
            LIMIT {$limit} OFFSET {$offset}";

    $products = $db->fetchAll($sql, $params);
}

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
    <title><?php echo $action === 'add' ? 'Add Product' : ($action === 'edit' ? 'Edit Product' : 'Products'); ?> - Sun Trading Admin</title>

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
                                                <a href="products.php" class="<?php echo $action === 'list' ? 'active' : ''; ?>">
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
                                <h6><?php echo $action === 'add' ? 'Add Product' : ($action === 'edit' ? 'Edit Product' : 'Products'); ?></h6>
                                <form class="form-search flex-grow" method="GET">
                                    <fieldset class="name">
                                        <input type="text" placeholder="Search products..." class="show-search style-1" name="search" tabindex="2" value="<?php echo htmlspecialchars($search ?? ''); ?>" aria-required="true">
                                        <input type="hidden" name="action" value="list">
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
                                                                <a href="#" class="body-title name">Product Manager</a>
                                                                <div class="time">Now</div>
                                                            </div>
                                                            <div class="text-tiny desc">Products section ready!</div>
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
                                                <li>
                                                    <div class="notifications-item item-1">
                                                        <div class="image">
                                                            <i class="fas fa-box"></i>
                                                        </div>
                                                        <div>
                                                            <div class="body-title-2">Product Management</div>
                                                            <div class="text-tiny">Manage your product catalog</div>
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
                            <?php if ($action === 'list'): ?>
                                <!-- Products List -->
                                <div class="main-content-wrap">
                                    <div class="flex items-center flex-wrap justify-between gap20 mb-32">
                                        <h3>Products Management</h3>
                                        <a href="products.php?action=add" class="tf-button style-1 type-fill">
                                            <i class="fas fa-plus"></i>
                                            Add Product
                                        </a>
                                    </div>

                                    <!-- Flash Messages -->
                                    <?php foreach ($flashMessages as $message): ?>
                                        <div class="alert-box alert-<?php echo $message['type']; ?> mb-16">
                                            <div class="alert-content">
                                                <div class="text"><?php echo $message['message']; ?></div>
                                                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.style.display='none'"><i class="icon-close"></i></button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if ($success): ?>
                                        <div class="alert-box alert-success mb-16">
                                            <div class="alert-content">
                                                <div class="text"><?php echo $success; ?></div>
                                                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.style.display='none'"><i class="icon-close"></i></button>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($error): ?>
                                        <div class="alert-box alert-danger mb-16">
                                            <div class="alert-content">
                                                <div class="text"><?php echo $error; ?></div>
                                                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.style.display='none'"><i class="icon-close"></i></button>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Statistics Dashboard -->
                                    <div class="flex gap24 mb-32 flex-md-row flex-column">
                                        <div class="w-100">
                                            <div class="wg-card style-1 bg-Primary mb-25">
                                                <div class="icon">
                                                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <rect width="48" height="48" rx="16" fill="white" />
                                                        <path d="M18 20H30V22H18V20ZM18 24H30V26H18V24ZM18 28H24V30H18V28ZM14 16H34C35.1 16 36 16.9 36 18V30C36 31.1 35.1 32 34 32H14C12.9 32 12 31.1 12 30V18C12 16.9 12.9 16 14 16Z" fill="#161326" />
                                                    </svg>
                                                </div>
                                                <div class="content">
                                                    <div>
                                                        <h6 class="counter text-White">
                                                            <?php
                                                            $totalProductsCount = $db->fetchOne("SELECT COUNT(*) as count FROM products")['count'] ?? 0;
                                                            echo $totalProductsCount;
                                                            ?>
                                                        </h6>
                                                        <div class="f12-medium text-White">Total Products</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="wg-card">
                                                <div class="icon">
                                                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <rect width="36" height="36" rx="12" fill="#C0FAA0" />
                                                        <path d="M18 10C13.58 10 10 13.58 10 18S13.58 26 18 26 26 22.42 26 18 22.42 10 18 10ZM21.5 18.5H18.5V21.5C18.5 21.78 18.28 22 18 22S17.5 21.78 17.5 21.5V18.5H14.5C14.22 18.5 14 18.28 14 18S14.22 17.5 14.5 17.5H17.5V14.5C17.5 14.22 17.72 14 18 14S18.5 14.22 18.5 14.5V17.5H21.5C21.78 17.5 22 17.72 22 18S21.78 18.5 21.5 18.5Z" fill="#161326" />
                                                    </svg>
                                                </div>
                                                <div class="content">
                                                    <div>
                                                        <h6 class="counter">
                                                            <?php
                                                            $activeProducts = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE is_active = 1")['count'] ?? 0;
                                                            echo $activeProducts;
                                                            ?>
                                                        </h6>
                                                        <div class="f12-medium">Active Products</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="w-100">
                                            <div class="wg-card mb-25">
                                                <div class="icon">
                                                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <rect width="36" height="36" rx="12" fill="#FFE4B5" />
                                                        <path d="M18 10L20.12 14.36L25 15.09L21.5 18.5L22.24 23.37L18 21.18L13.76 23.37L14.5 18.5L11 15.09L15.88 14.36L18 10Z" fill="#161326" />
                                                    </svg>
                                                </div>
                                                <div class="content">
                                                    <div>
                                                        <h6 class="counter">
                                                            <?php
                                                            $featuredProducts = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE is_featured = 1")['count'] ?? 0;
                                                            echo $featuredProducts;
                                                            ?>
                                                        </h6>
                                                        <div class="f12-medium">Featured Products</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="wg-card style-1 bg-YellowGreen">
                                                <div class="icon">
                                                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <rect width="48" height="48" rx="16" fill="#161326" />
                                                        <path d="M24 12C18.48 12 14 16.48 14 22C14 24.52 14.97 26.84 16.56 28.62L24 36L31.44 28.62C33.03 26.84 34 24.52 34 22C34 16.48 29.52 12 24 12ZM24 26C21.79 26 20 24.21 20 22S21.79 18 24 18S28 19.79 28 22S26.21 26 24 26Z" fill="white" />
                                                    </svg>
                                                </div>
                                                <div class="content">
                                                    <div>
                                                        <h6 class="counter text-White">
                                                            <?php
                                                            $categoriesCount = $db->fetchOne("SELECT COUNT(DISTINCT category) as count FROM products WHERE category IS NOT NULL AND category != ''")['count'] ?? 0;
                                                            echo $categoriesCount;
                                                            ?>
                                                        </h6>
                                                        <div class="f12-medium text-White">Categories</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="w-100">
                                            <div class="wg-card mb-25">
                                                <div class="icon">
                                                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <rect width="36" height="36" rx="12" fill="#E6E6FA" />
                                                        <path d="M18 10C13.03 10 9 14.03 9 19S13.03 28 18 28 27 23.97 27 19 22.97 10 18 10ZM20.5 20.5H15.5C15.22 20.5 15 20.28 15 20S15.22 19.5 15.5 19.5H20.5C20.78 19.5 21 19.72 21 20S20.78 20.5 20.5 20.5ZM22.5 17.5H13.5C13.22 17.5 13 17.28 13 17S13.22 16.5 13.5 16.5H22.5C22.78 16.5 23 16.72 23 17S22.78 17.5 22.5 17.5Z" fill="#161326" />
                                                    </svg>
                                                </div>
                                                <div class="content">
                                                    <div>
                                                        <h6 class="counter">
                                                            <?php
                                                            $totalImages = $db->fetchOne("SELECT COUNT(*) as count FROM product_images")['count'] ?? 0;
                                                            echo $totalImages;
                                                            ?>
                                                        </h6>
                                                        <div class="f12-medium">Product Images</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="wg-card">
                                                <div class="icon">
                                                    <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <rect width="36" height="36" rx="12" fill="#FFF0F5" />
                                                        <path d="M18 10C13.58 10 10 13.58 10 18S13.58 26 18 26 26 22.42 26 18 22.42 10 18 10ZM19 22H17V16H19V22ZM19 14H17V12H19V14Z" fill="#161326" />
                                                    </svg>
                                                </div>
                                                <div class="content">
                                                    <div>
                                                        <h6 class="counter">
                                                            <?php
                                                            $inactiveProducts = $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE is_active = 0")['count'] ?? 0;
                                                            echo $inactiveProducts;
                                                            ?>
                                                        </h6>
                                                        <div class="f12-medium">Inactive Products</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Quick Actions -->
                                    <div class="wg-box mb-32">
                                        <div class="flex items-center justify-between mb-20">
                                            <div class="body-title">Quick Actions</div>
                                        </div>
                                        <div class="flex gap16 flex-wrap">
                                            <a href="products.php?action=add" class="tf-button style-1 type-fill gap8">
                                                <i class="fas fa-plus"></i>
                                                Add New Product
                                            </a>
                                            <a href="?action=list&status=inactive" class="tf-button style-1 type-outline gap8">
                                                <i class="fas fa-eye-slash"></i>
                                                View Inactive
                                            </a>
                                            <a href="?action=list&status=featured" class="tf-button style-1 type-outline gap8">
                                                <i class="fas fa-star"></i>
                                                View Featured
                                            </a>
                                            <a href="?action=list" class="tf-button style-1 type-outline gap8">
                                                <i class="fas fa-list"></i>
                                                View All Products
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Products Table -->
                                    <?php if (!empty($products)): ?>
                                        <div class="wg-box">
                                            <div class="flex items-center justify-between">
                                                <div class="body-title">Products List</div>
                                                <div class="body-text">Total: <?php echo count($products); ?> products</div>
                                            </div>
                                            <div class="wg-table table-all-user">
                                                <div class="table-responsive">
                                                    <table class="table table-striped table-bordered">
                                                        <thead>
                                                            <tr>
                                                                <th class="text-center">Image</th>
                                                                <th>Product Name</th>
                                                                <th class="text-center">Category</th>
                                                                <th class="text-center">Status</th>
                                                                <th class="text-center">Images</th>
                                                                <th class="text-center">Created</th>
                                                                <th class="text-center">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($products as $prod): ?>
                                                                <tr>
                                                                    <td class="text-center">
                                                                        <?php if ($prod['primary_image']): ?>
                                                                            <?php
                                                                            // Simple thumbnail path handling
                                                                            $imagePath = $prod['primary_image'];
                                                                            $filename = basename($imagePath);
                                                                            $thumbnailPath = "../uploads/thumbnails/{$filename}";

                                                                            // Use thumbnail if available, otherwise original
                                                                            if (file_exists($thumbnailPath)) {
                                                                                $displayPath = $thumbnailPath;
                                                                            } else {
                                                                                $displayPath = "../{$imagePath}";
                                                                            }
                                                                            ?>
                                                                            <div class="image">
                                                                                <img src="<?php echo $displayPath; ?>"
                                                                                    alt="Product" class="image-product"
                                                                                    onerror="this.src='../images/products/default.png'">
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <div class="image">
                                                                                <div class="image-default d-flex align-items-center justify-content-center">
                                                                                    <i class="fas fa-box"></i>
                                                                                </div>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <div class="body-title"><?php echo htmlspecialchars($prod['name_en']); ?></div>
                                                                        <div class="text-tiny mt-3"><?php echo htmlspecialchars($prod['name_ar']); ?></div>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <div class="block-available">
                                                                            <span class="body-title-2"><?php echo ucfirst(htmlspecialchars($prod['category'] ?? 'No Category')); ?></span>
                                                                        </div>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <?php if ($prod['is_active']): ?>
                                                                            <div class="block-available available">
                                                                                <span class="body-title-2">Active</span>
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <div class="block-available pending">
                                                                                <span class="body-title-2">Inactive</span>
                                                                            </div>
                                                                        <?php endif; ?>

                                                                        <?php if ($prod['is_featured']): ?>
                                                                            <div class="block-available featured mt-3">
                                                                                <span class="body-title-2">Featured</span>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <div class="body-title-2"><?php echo $prod['image_count']; ?> images</div>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <div class="body-text"><?php echo timeAgo($prod['created_at']); ?></div>
                                                                    </td>
                                                                    <td class="text-center">
                                                                        <div class="list-icon-function">
                                                                            <a href="products.php?action=edit&id=<?php echo $prod['id']; ?>" title="Edit">
                                                                                <div class="item edit">
                                                                                    <i class="fas fa-edit"></i>
                                                                                </div>
                                                                            </a>
                                                                            <a href="javascript:void(0);" onclick="deleteProduct(<?php echo $prod['id']; ?>)" title="Delete">
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

                                                <!-- Pagination -->
                                                <div class="divider"></div>
                                                <div class="flex items-center justify-between flex-wrap gap10">
                                                    <div class="text-tiny">Showing <?php echo count($products); ?> entries</div>
                                                    <div class="wg-pagination">
                                                        <?php echo generatePagination($page, $totalPages, 'products.php', ['action' => 'list', 'search' => $search, 'category' => $category, 'status' => $status]); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="wg-box text-center">
                                            <div class="image">
                                                <i class="icon-package" style="font-size: 64px; color: var(--gray-color);"></i>
                                            </div>
                                            <div class="body-title mt-16">No products found</div>
                                            <div class="body-text">Create your first product to get started!</div>
                                            <div class="mt-20">
                                                <a href="products.php?action=add" class="tf-button style-1 type-fill">
                                                    <i class="fas fa-plus"></i>
                                                    Add Product
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>
                                <!-- Add/Edit Product Form -->
                                <div class="main-content-wrap">
                                    <div class="flex items-center flex-wrap justify-between gap20 mb-32">
                                        <div>
                                            <h3><?php echo $action === 'add' ? 'Add New Product' : 'Edit Product'; ?></h3>
                                            <div class="body-text mt-8">
                                                <?php echo $action === 'add' ? 'Create a new product for your catalog' : 'Update product information'; ?>
                                            </div>
                                        </div>
                                        <a href="products.php" class="tf-button style-1 type-outline">
                                            <i class="icon-arrow-left"></i>
                                            Back to Products
                                        </a>
                                    </div>

                                    <!-- Flash Messages -->
                                    <?php if ($success): ?>
                                        <div class="alert-box alert-success mb-16">
                                            <div class="alert-content">
                                                <div class="text"><?php echo $success; ?></div>
                                                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.style.display='none'"><i class="icon-close"></i></button>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($error): ?>
                                        <div class="alert-box alert-danger mb-16">
                                            <div class="alert-content">
                                                <div class="text"><?php echo $error; ?></div>
                                                <button type="button" class="btn-close" onclick="this.parentElement.parentElement.style.display='none'"><i class="icon-close"></i></button>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="row">
                                        <!-- Product Form -->
                                        <div class="col-lg-8">
                                            <div class="wg-box">
                                                <div class="flex items-center justify-between">
                                                    <div class="body-title">Product Information</div>
                                                </div>
                                                <form method="POST" enctype="multipart/form-data">
                                                    <div class="cols gap22">
                                                        <fieldset class="name">
                                                            <div class="body-title mb-10">Product Name (English)</div>
                                                            <input class="tf-input style-1" name="name_en" type="text"
                                                                value="<?php echo htmlspecialchars($product['name_en'] ?? ''); ?>"
                                                                placeholder="Enter product name in English" required>
                                                        </fieldset>
                                                        <fieldset class="name">
                                                            <div class="body-title mb-10">Product Name (Arabic)</div>
                                                            <input class="tf-input style-1" name="name_ar" type="text"
                                                                value="<?php echo htmlspecialchars($product['name_ar'] ?? ''); ?>"
                                                                placeholder="Enter product name in Arabic" required>
                                                        </fieldset>
                                                    </div>

                                                    <div class="cols gap22">
                                                        <fieldset class="category">
                                                            <div class="body-title mb-10">Category</div>
                                                            <input class="tf-input style-1" name="category" type="text"
                                                                value="<?php echo htmlspecialchars($product['category'] ?? ''); ?>"
                                                                placeholder="e.g., grains, vegetables, fruits">
                                                        </fieldset>
                                                        <fieldset class="price">
                                                            <div class="body-title mb-10">Price (Optional)</div>
                                                            <input class="tf-input style-1" name="price" type="number"
                                                                value="<?php echo $product['price'] ?? ''; ?>"
                                                                step="0.01" min="0" placeholder="0.00">
                                                        </fieldset>
                                                    </div>

                                                    <!-- Product Image Upload - Simple Version -->
                                                    <fieldset class="file">
                                                        <div class="body-title mb-10">Product Image</div>

                                                        <?php if (!empty($productImages)): ?>
                                                            <!-- Current Image Display -->
                                                            <?php
                                                            $currentImg = $productImages[0];
                                                            $imagePath = $currentImg['image_path'];
                                                            $filename = basename($imagePath);
                                                            $thumbnailPath = "../uploads/thumbnails/{$filename}";

                                                            // Use thumbnail if available, otherwise original image
                                                            if (file_exists($thumbnailPath)) {
                                                                $displayPath = $thumbnailPath;
                                                            } else {
                                                                $displayPath = "../{$imagePath}";
                                                            }
                                                            ?>
                                                            <div class="current-image mb-16">
                                                                <div class="body-text mb-8">Current Image:</div>
                                                                <div class="image-container" style="position: relative; display: inline-block;">
                                                                    <img id="currentImage" src="<?php echo $displayPath; ?>" alt="Current product image"
                                                                        style="width: 200px; height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #E5E7EB;"
                                                                        onerror="this.src='../images/products/default.png'">
                                                                </div>
                                                            </div>

                                                            <!-- Replace Image Button -->
                                                            <div class="image-actions mb-16">
                                                                <button type="button" onclick="document.getElementById('product_image').click()"
                                                                    class="tf-button style-1 type-outline" style="margin-right: 8px;">
                                                                    <i class="icon-edit"></i> Replace Image
                                                                </button>
                                                                <button type="button" onclick="deleteCurrentImage()"
                                                                    class="tf-button style-1 type-fill-danger">
                                                                    <i class="icon-trash-2"></i> Delete Image
                                                                </button>
                                                            </div>

                                                        <?php else: ?>
                                                            <!-- No Image State -->
                                                            <div class="no-image mb-16">
                                                                <div class="upload-placeholder" style="border: 2px dashed #D1D5DB; border-radius: 8px; padding: 40px; text-align: center; cursor: pointer;"
                                                                    onclick="document.getElementById('product_image').click()">
                                                                    <i class="icon-upload-cloud" style="font-size: 48px; color: #9CA3AF; margin-bottom: 16px;"></i>
                                                                    <div class="body-text">Click to upload product image</div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Hidden File Input -->
                                                        <input type="file" id="product_image" name="product_image" accept="image/*"
                                                            style="display: none;" onchange="previewNewImage(this)">

                                                        <!-- Image Preview for New Upload -->
                                                        <div id="newImagePreview" style="display: none;" class="mb-16">
                                                            <div class="body-text mb-8">New Image (will replace current on save):</div>
                                                            <img id="previewImg" style="width: 200px; height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #10B981;">
                                                        </div>

                                                        <!-- Hidden field to track image deletion -->
                                                        <input type="hidden" id="delete_image" name="delete_image" value="0">

                                                        <div class="body-text text-tiny mt-8">Recommended size: 800x600px. Supported formats: JPG, PNG, WebP</div>
                                                    </fieldset>

                                                    <div class="cols gap22">
                                                        <fieldset class="description">
                                                            <div class="body-title mb-10">Description (English)</div>
                                                            <textarea class="tf-input style-1" name="description_en" placeholder="Enter product description in English"><?php echo htmlspecialchars($product['description_en'] ?? ''); ?></textarea>
                                                        </fieldset>
                                                        <fieldset class="description">
                                                            <div class="body-title mb-10">Description (Arabic)</div>
                                                            <textarea class="tf-input style-1" name="description_ar" placeholder="Enter product description in Arabic"><?php echo htmlspecialchars($product['description_ar'] ?? ''); ?></textarea>
                                                        </fieldset>
                                                    </div>

                                                    <!-- Rich Product Information -->
                                                    <div class="wg-box style-2 mb-32">
                                                        <div class="flex items-center justify-between">
                                                            <div class="body-title"><i class="fas fa-star"></i> Rich Product Information</div>
                                                        </div>
                                                        <!-- Features -->
                                                        <div class="cols gap22">
                                                            <fieldset class="features">
                                                                <div class="body-title mb-10">Features (English)</div>
                                                                <textarea class="tf-input style-1" name="features_en" placeholder="Distinctive golden color, whole grains..."><?php echo htmlspecialchars($product['features_en'] ?? ''); ?></textarea>
                                                            </fieldset>
                                                            <fieldset class="features">
                                                                <div class="body-title mb-10">Features (Arabic)</div>
                                                                <textarea class="tf-input style-1" name="features_ar" placeholder="لون ذهبي مميز، حبوب كاملة..."><?php echo htmlspecialchars($product['features_ar'] ?? ''); ?></textarea>
                                                            </fieldset>
                                                        </div>

                                                        <!-- Origin -->
                                                        <div class="cols gap22">
                                                            <fieldset class="origin">
                                                                <div class="body-title mb-10">Origin (English)</div>
                                                                <input class="tf-input style-1" name="origin_en" type="text"
                                                                    value="<?php echo htmlspecialchars($product['origin_en'] ?? ''); ?>"
                                                                    placeholder="Argentina, Brazil, Ukraine">
                                                            </fieldset>
                                                            <fieldset class="origin">
                                                                <div class="body-title mb-10">Origin (Arabic)</div>
                                                                <input class="tf-input style-1" name="origin_ar" type="text"
                                                                    value="<?php echo htmlspecialchars($product['origin_ar'] ?? ''); ?>"
                                                                    placeholder="الأرجنتين، البرازيل، أوكرانيا">
                                                            </fieldset>
                                                        </div>

                                                        <!-- Uses -->
                                                        <div class="cols gap22">
                                                            <fieldset class="uses">
                                                                <div class="body-title mb-10">Uses (English)</div>
                                                                <textarea class="tf-input style-1" name="uses_en" placeholder="Animal feed, food industries, starch"><?php echo htmlspecialchars($product['uses_en'] ?? ''); ?></textarea>
                                                            </fieldset>
                                                            <fieldset class="uses">
                                                                <div class="body-title mb-10">Uses (Arabic)</div>
                                                                <textarea class="tf-input style-1" name="uses_ar" placeholder="الأعلاف، الصناعات الغذائية، النشا"><?php echo htmlspecialchars($product['uses_ar'] ?? ''); ?></textarea>
                                                            </fieldset>
                                                        </div>

                                                        <!-- Color and Grain Type -->
                                                        <div class="grid-4 gap22">
                                                            <fieldset class="color">
                                                                <div class="body-title mb-10">Color (English)</div>
                                                                <input class="tf-input style-1" name="color_en" type="text"
                                                                    value="<?php echo htmlspecialchars($product['color_en'] ?? ''); ?>"
                                                                    placeholder="Golden yellow">
                                                            </fieldset>
                                                            <fieldset class="color">
                                                                <div class="body-title mb-10">Color (Arabic)</div>
                                                                <input class="tf-input style-1" name="color_ar" type="text"
                                                                    value="<?php echo htmlspecialchars($product['color_ar'] ?? ''); ?>"
                                                                    placeholder="أصفر ذهبي">
                                                            </fieldset>
                                                            <fieldset class="grain_type">
                                                                <div class="body-title mb-10">Type (English)</div>
                                                                <input class="tf-input style-1" name="grain_type_en" type="text"
                                                                    value="<?php echo htmlspecialchars($product['grain_type_en'] ?? ''); ?>"
                                                                    placeholder="Whole grains">
                                                            </fieldset>
                                                            <fieldset class="grain_type">
                                                                <div class="body-title mb-10">Type (Arabic)</div>
                                                                <input class="tf-input style-1" name="grain_type_ar" type="text"
                                                                    value="<?php echo htmlspecialchars($product['grain_type_ar'] ?? ''); ?>"
                                                                    placeholder="حبوب كاملة">
                                                            </fieldset>
                                                        </div>

                                                        <!-- Quality Information -->
                                                        <div class="cols gap22">
                                                            <fieldset class="quality">
                                                                <div class="body-title mb-10">Quality Info (English)</div>
                                                                <textarea class="tf-input style-1" name="quality_info_en" placeholder="High quality grains free from impurities"><?php echo htmlspecialchars($product['quality_info_en'] ?? ''); ?></textarea>
                                                            </fieldset>
                                                            <fieldset class="quality">
                                                                <div class="body-title mb-10">Quality Info (Arabic)</div>
                                                                <textarea class="tf-input style-1" name="quality_info_ar" placeholder="حبوب عالية الجودة خالية من الشوائب"><?php echo htmlspecialchars($product['quality_info_ar'] ?? ''); ?></textarea>
                                                            </fieldset>
                                                        </div>
                                                    </div>

                                                    <div class="cols gap22">
                                                        <fieldset class="checkbox">
                                                            <div class="tf-cart-checkbox style-1">
                                                                <div class="tf-checkbox-wrapp">
                                                                    <input class="" type="checkbox" id="is_active" name="is_active"
                                                                        <?php echo ($product['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                                                    <div>
                                                                        <i class="icon-check"></i>
                                                                    </div>
                                                                </div>
                                                                <div class="body-title-2">
                                                                    Active (visible on website)
                                                                </div>
                                                            </div>
                                                        </fieldset>
                                                        <fieldset class="checkbox">
                                                            <div class="tf-cart-checkbox style-1">
                                                                <div class="tf-checkbox-wrapp">
                                                                    <input class="" type="checkbox" id="is_featured" name="is_featured"
                                                                        <?php echo ($product['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                                                                    <div>
                                                                        <i class="icon-check"></i>
                                                                    </div>
                                                                </div>
                                                                <div class="body-title-2">
                                                                    Featured product
                                                                </div>
                                                            </div>
                                                        </fieldset>
                                                    </div>

                                                    <div class="bot">
                                                        <button type="submit" name="save_product" class="tf-button style-1 type-fill w-100">
                                                            <i class="icon-save"></i>
                                                            <?php echo $action === 'add' ? 'Create Product' : 'Update Product'; ?>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <!-- Removed complex Product Images sidebar - now handled in main form -->
                                    </div>
                                </div>
                        </div>
                    <?php endif; ?>
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

    <!-- Delete Form (Hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="delete_product" value="1">
        <input type="hidden" name="product_id" id="deleteProductId">
    </form>

    <!-- Javascript -->
    <script src="assets/vendor/js/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="assets/vendor/js/bootstrap-select.min.js"></script>
    <script src="assets/vendor/js/main.js"></script>

    <!-- Admin Panel JavaScript -->
    <script src="assets/js/admin.js"></script>

    <script>
        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product? This will also delete all associated images. This action cannot be undone.')) {
                document.getElementById('deleteProductId').value = productId;
                document.getElementById('deleteForm').submit();
            }
        }

        function deleteProductImage(imageId) {
            if (confirm('Are you sure you want to delete this image?')) {
                window.location.href = `ajax/delete-product-image.php?id=${imageId}&product_id=<?php echo $productId; ?>`;
            }
        }

        // Function to preview new image before upload
        function previewNewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('newImagePreview').style.display = 'block';

                    // Hide current image section if it exists
                    const currentImageSection = document.querySelector('.current-image');
                    if (currentImageSection) {
                        currentImageSection.style.opacity = '0.5';
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Function to delete current image
        function deleteCurrentImage() {
            if (confirm('Are you sure you want to delete the current image? This action will take effect when you save the product.')) {
                // Set the hidden field to indicate deletion
                document.getElementById('delete_image').value = '1';

                // Hide current image preview
                const currentImageSection = document.querySelector('.current-image');
                if (currentImageSection) {
                    currentImageSection.style.display = 'none';
                }

                // Show no-image state
                const noImageSection = document.querySelector('.no-image');
                if (noImageSection) {
                    noImageSection.style.display = 'block';
                } else {
                    // Create no-image placeholder if it doesn't exist
                    const imageContainer = document.querySelector('.image-actions').parentNode;
                    const noImageDiv = document.createElement('div');
                    noImageDiv.className = 'no-image mb-16';
                    noImageDiv.innerHTML = `
                        <div class="upload-placeholder" style="border: 2px dashed #D1D5DB; border-radius: 8px; padding: 40px; text-align: center; cursor: pointer;" 
                             onclick="document.getElementById('product_image').click()">
                            <i class="icon-upload-cloud" style="font-size: 48px; color: #9CA3AF; margin-bottom: 16px;"></i>
                            <div class="body-title mb-8">No Image</div>
                            <div class="body-text">Click to upload product image</div>
                        </div>
                    `;
                    imageContainer.insertBefore(noImageDiv, imageContainer.firstChild);
                }

                // Update button text to indicate pending deletion
                const deleteBtn = document.querySelector('[onclick="deleteCurrentImage()"]');
                if (deleteBtn) {
                    deleteBtn.innerHTML = '<i class="icon-trash-2"></i> Image will be deleted on save';
                    deleteBtn.disabled = true;
                    deleteBtn.style.opacity = '0.6';
                }
            }
        }
    </script>

</body>

</html> }
}
</script>

</body>

</html>