<?php

/**
 * Sun Trading Company - Admin API Endpoints
 * Provides JSON API for frontend integration
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$db = Database::getInstance();
$endpoint = $_GET['endpoint'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// API Response helper
function apiResponse($data = null, $message = '', $status = 200, $success = true)
{
    http_response_code($status);
    return json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ]);
}

// Error handler
function apiError($message, $status = 400)
{
    return apiResponse(null, $message, $status, false);
}

try {
    switch ($endpoint) {
        case 'products':
            handleProductsAPI($db, $method);
            break;

        case 'settings':
            handleSettingsAPI($db, $method);
            break;

        case 'content':
            handleContentAPI($db, $method);
            break;

        case 'images':
            handleImagesAPI($db, $method);
            break;

        case 'stats':
            handleStatsAPI($db, $method);
            break;

        default:
            echo apiError('Invalid API endpoint', 404);
            break;
    }
} catch (Exception $e) {
    echo apiError('Internal server error: ' . $e->getMessage(), 500);
}

/**
 * Products API Handler
 */
function handleProductsAPI($db, $method)
{
    switch ($method) {
        case 'GET':
            $page = (int)($_GET['page'] ?? 1);
            $limit = min((int)($_GET['limit'] ?? 10), 50); // Max 50 items per page
            $offset = ($page - 1) * $limit;

            $category = $_GET['category'] ?? '';
            $featured = $_GET['featured'] ?? '';
            $active = $_GET['active'] ?? 'true';

            $whereConditions = [];
            $params = [];

            if ($category) {
                $whereConditions[] = "category = :category";
                $params['category'] = $category;
            }

            if ($featured === 'true') {
                $whereConditions[] = "is_featured = 1";
            }

            if ($active === 'true') {
                $whereConditions[] = "is_active = 1";
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM products {$whereClause}";
            $totalProducts = $db->fetchOne($countSql, $params)['total'] ?? 0;

            // Get products with images and all rich information
            $sql = "SELECT p.*, 
                    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as primary_image,
                    (SELECT JSON_ARRAYAGG(JSON_OBJECT('id', pi.id, 'path', pi.image_path, 'alt_ar', pi.alt_text_ar, 'alt_en', pi.alt_text_en)) 
                     FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.sort_order, pi.id) as images
                    FROM products p 
                    {$whereClause} 
                    ORDER BY p.is_featured DESC, p.sort_order ASC, p.created_at DESC 
                    LIMIT {$limit} OFFSET {$offset}";

            $products = $db->fetchAll($sql, $params);

            // Process images JSON
            foreach ($products as &$product) {
                $product['images'] = $product['images'] ? json_decode($product['images'], true) : [];
                $product['image_count'] = count($product['images']);
            }

            echo apiResponse([
                'products' => $products,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($totalProducts / $limit),
                    'total_items' => $totalProducts,
                    'items_per_page' => $limit
                ]
            ], 'Products retrieved successfully');
            break;

        default:
            echo apiError('Method not allowed for products endpoint', 405);
    }
}

/**
 * Settings API Handler
 */
function handleSettingsAPI($db, $method)
{
    switch ($method) {
        case 'GET':
            $category = $_GET['category'] ?? '';

            $whereClause = $category ? 'WHERE category = :category' : '';
            $params = $category ? ['category' => $category] : [];

            $sql = "SELECT setting_key, setting_value, setting_type FROM site_settings {$whereClause}";
            $settings = $db->fetchAll($sql, $params);

            // Convert to key-value pairs
            $settingsData = [];
            foreach ($settings as $setting) {
                $value = $setting['setting_value'];

                // Convert boolean strings to actual booleans
                if ($setting['setting_type'] === 'boolean') {
                    $value = $value === 'true';
                }
                // Parse JSON if applicable
                elseif ($setting['setting_type'] === 'json') {
                    $value = json_decode($value, true);
                }

                $settingsData[$setting['setting_key']] = $value;
            }

            echo apiResponse($settingsData, 'Settings retrieved successfully');
            break;

        default:
            echo apiError('Method not allowed for settings endpoint', 405);
    }
}

/**
 * Content API Handler
 */
function handleContentAPI($db, $method)
{
    switch ($method) {
        case 'GET':
            $section = $_GET['section'] ?? '';
            $language = $_GET['language'] ?? 'en';

            $whereClause = $section ? 'WHERE page_section = :section AND is_active = 1' : 'WHERE is_active = 1';
            $params = $section ? ['section' => $section] : [];

            $sql = "SELECT content_key, title_ar, title_en, content_ar, content_en, 
                           meta_description_ar, meta_description_en, page_section 
                    FROM website_content {$whereClause} ORDER BY content_key";

            $content = $db->fetchAll($sql, $params);

            // Process content based on language preference
            $processedContent = [];
            foreach ($content as $item) {
                $processedContent[$item['content_key']] = [
                    'title' => $language === 'ar' ? $item['title_ar'] : $item['title_en'],
                    'content' => $language === 'ar' ? $item['content_ar'] : $item['content_en'],
                    'meta_description' => $language === 'ar' ? $item['meta_description_ar'] : $item['meta_description_en'],
                    'section' => $item['page_section'],
                    'titles' => [
                        'ar' => $item['title_ar'],
                        'en' => $item['title_en']
                    ],
                    'contents' => [
                        'ar' => $item['content_ar'],
                        'en' => $item['content_en']
                    ]
                ];
            }

            echo apiResponse($processedContent, 'Content retrieved successfully');
            break;

        default:
            echo apiError('Method not allowed for content endpoint', 405);
    }
}

/**
 * Images API Handler
 */
function handleImagesAPI($db, $method)
{
    switch ($method) {
        case 'GET':
            $category = $_GET['category'] ?? '';
            $limit = min((int)($_GET['limit'] ?? 20), 100);

            $whereClause = $category ? 'WHERE upload_category = :category' : '';
            $params = $category ? ['category' => $category] : [];

            $sql = "SELECT file_name, file_path, original_name, file_size, upload_category, created_at 
                    FROM file_uploads {$whereClause} 
                    ORDER BY created_at DESC 
                    LIMIT {$limit}";

            $images = $db->fetchAll($sql, $params);

            // Add full URLs
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
                '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);

            foreach ($images as &$image) {
                $image['full_url'] = $baseUrl . '/../' . $image['file_path'];
                $image['thumbnail_url'] = $baseUrl . '/../uploads/thumbnails/' . $image['file_name'];
                $image['file_size_formatted'] = formatFileSize($image['file_size']);
            }

            echo apiResponse($images, 'Images retrieved successfully');
            break;

        default:
            echo apiError('Method not allowed for images endpoint', 405);
    }
}

/**
 * Statistics API Handler
 */
function handleStatsAPI($db, $method)
{
    switch ($method) {
        case 'GET':
            $stats = [];

            // Product statistics
            $stats['products'] = [
                'total' => $db->fetchOne("SELECT COUNT(*) as count FROM products")['count'] ?? 0,
                'active' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE is_active = 1")['count'] ?? 0,
                'featured' => $db->fetchOne("SELECT COUNT(*) as count FROM products WHERE is_featured = 1")['count'] ?? 0,
                'by_category' => $db->fetchAll("SELECT category, COUNT(*) as count FROM products WHERE category IS NOT NULL GROUP BY category ORDER BY count DESC")
            ];

            // Image statistics
            $stats['images'] = [
                'total' => $db->fetchOne("SELECT COUNT(*) as count FROM file_uploads")['count'] ?? 0,
                'total_size' => $db->fetchOne("SELECT SUM(file_size) as size FROM file_uploads")['size'] ?? 0,
                'by_category' => $db->fetchAll("SELECT upload_category, COUNT(*) as count FROM file_uploads GROUP BY upload_category ORDER BY count DESC")
            ];

            // Recent activity
            $stats['recent_activity'] = $db->fetchAll(
                "SELECT action, table_name, created_at 
                 FROM activity_logs 
                 ORDER BY created_at DESC 
                 LIMIT 10"
            );

            // Format file sizes
            $stats['images']['total_size_formatted'] = formatFileSize($stats['images']['total_size']);

            echo apiResponse($stats, 'Statistics retrieved successfully');
            break;

        default:
            echo apiError('Method not allowed for stats endpoint', 405);
    }
}
