<?php

/**
 * Sun Trading Company - Images API
 * Provides image data from the database to the frontend
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';

    switch ($method) {
        case 'POST':
            switch ($action) {
                case 'update_metadata':
                    // Update image metadata (name, category)
                    $input = json_decode(file_get_contents('php://input'), true);
                    $id = (int)($input['id'] ?? 0);

                    if ($id <= 0) {
                        throw new Exception('Invalid image ID');
                    }

                    $image = $db->fetchOne("SELECT * FROM file_uploads WHERE id = :id", ['id' => $id]);
                    if (!$image) {
                        throw new Exception('Image not found');
                    }

                    $updateData = [];
                    if (isset($input['name']) && !empty($input['name'])) {
                        $updateData['original_name'] = $input['name'];
                    }
                    if (isset($input['category'])) {
                        $updateData['upload_category'] = $input['category'];
                    }

                    if (!empty($updateData)) {
                        $db->update('file_uploads', $updateData, 'id = :id', ['id' => $id]);
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Image metadata updated successfully'
                    ]);
                    break;

                default:
                    throw new Exception('Invalid action for POST method');
            }
            break;

        case 'GET':
            switch ($action) {
                case 'list':
                    // Get all images with optional filtering
                    $category = $_GET['category'] ?? null;
                    $limit = (int)($_GET['limit'] ?? 50);
                    $offset = (int)($_GET['offset'] ?? 0);

                    $whereConditions = [];
                    $params = [];

                    if ($category) {
                        $whereConditions[] = "upload_category = :category";
                        $params['category'] = $category;
                    }

                    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

                    // Get total count
                    $countSql = "SELECT COUNT(*) as total FROM file_uploads {$whereClause}";
                    $totalCount = $db->fetchOne($countSql, $params)['total'] ?? 0;

                    // Get images
                    $sql = "SELECT fu.*, au.full_name as uploaded_by_name 
                            FROM file_uploads fu 
                            LEFT JOIN admin_users au ON fu.uploaded_by = au.id 
                            {$whereClause} 
                            ORDER BY fu.created_at DESC 
                            LIMIT {$limit} OFFSET {$offset}";

                    $images = $db->fetchAll($sql, $params);

                    // Format image data
                    $formattedImages = array_map(function ($image) {
                        return [
                            'id' => $image['id'],
                            'name' => $image['original_name'],
                            'filename' => $image['file_name'],
                            'path' => $image['file_path'],
                            'url' => '/' . $image['file_path'],
                            'thumbnail_url' => '/uploads/thumbnails/' . $image['file_name'],
                            'type' => $image['file_type'],
                            'size' => (int)$image['file_size'],
                            'category' => $image['upload_category'],
                            'uploaded_by' => $image['uploaded_by_name'],
                            'created_at' => $image['created_at']
                        ];
                    }, $images);

                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'images' => $formattedImages,
                            'total' => (int)$totalCount,
                            'limit' => $limit,
                            'offset' => $offset
                        ]
                    ]);
                    break;

                case 'categories':
                    // Get available categories
                    $categories = $db->fetchAll("SELECT DISTINCT upload_category as category, COUNT(*) as count FROM file_uploads GROUP BY upload_category ORDER BY upload_category");

                    echo json_encode([
                        'success' => true,
                        'data' => $categories
                    ]);
                    break;

                case 'by_category':
                    // Get images grouped by category for website sections
                    $categories = ['general', 'product', 'logo', 'content'];
                    $result = [];

                    foreach ($categories as $category) {
                        $images = $db->fetchAll(
                            "SELECT * FROM file_uploads WHERE upload_category = :category ORDER BY created_at DESC",
                            ['category' => $category]
                        );

                        $result[$category] = array_map(function ($image) {
                            return [
                                'id' => $image['id'],
                                'name' => $image['original_name'],
                                'filename' => $image['file_name'],
                                'path' => $image['file_path'],
                                'url' => '/' . $image['file_path'],
                                'thumbnail_url' => '/uploads/thumbnails/' . $image['file_name']
                            ];
                        }, $images);
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                    break;

                case 'get':
                    // Get single image by ID
                    $id = (int)($_GET['id'] ?? 0);
                    if ($id <= 0) {
                        throw new Exception('Invalid image ID');
                    }

                    $image = $db->fetchOne(
                        "SELECT fu.*, au.full_name as uploaded_by_name 
                         FROM file_uploads fu 
                         LEFT JOIN admin_users au ON fu.uploaded_by = au.id 
                         WHERE fu.id = :id",
                        ['id' => $id]
                    );

                    if (!$image) {
                        throw new Exception('Image not found');
                    }

                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'id' => $image['id'],
                            'name' => $image['original_name'],
                            'filename' => $image['file_name'],
                            'path' => $image['file_path'],
                            'url' => '/' . $image['file_path'],
                            'thumbnail_url' => '/uploads/thumbnails/' . $image['file_name'],
                            'type' => $image['file_type'],
                            'size' => (int)$image['file_size'],
                            'category' => $image['upload_category'],
                            'uploaded_by' => $image['uploaded_by_name'],
                            'created_at' => $image['created_at']
                        ]
                    ]);
                    break;

                case 'search':
                    // Search images by name or category
                    $query = $_GET['q'] ?? '';
                    $category = $_GET['category'] ?? null;
                    $limit = (int)($_GET['limit'] ?? 20);

                    if (empty($query)) {
                        throw new Exception('Search query is required');
                    }

                    $whereConditions = ["(original_name LIKE :query OR file_name LIKE :query)"];
                    $params = ['query' => "%{$query}%"];

                    if ($category) {
                        $whereConditions[] = "upload_category = :category";
                        $params['category'] = $category;
                    }

                    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

                    $images = $db->fetchAll(
                        "SELECT * FROM file_uploads {$whereClause} ORDER BY created_at DESC LIMIT {$limit}",
                        $params
                    );

                    $formattedImages = array_map(function ($image) {
                        return [
                            'id' => $image['id'],
                            'name' => $image['original_name'],
                            'filename' => $image['file_name'],
                            'path' => $image['file_path'],
                            'url' => '/' . $image['file_path'],
                            'thumbnail_url' => '/uploads/thumbnails/' . $image['file_name'],
                            'category' => $image['upload_category']
                        ];
                    }, $images);

                    echo json_encode([
                        'success' => true,
                        'data' => $formattedImages
                    ]);
                    break;

                default:
                    throw new Exception('Invalid action');
            }
            break;

        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
