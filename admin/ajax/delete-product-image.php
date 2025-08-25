<?php

/**
 * Sun Trading Company - Delete Product Image AJAX Endpoint
 * Handles deletion of product images with proper authentication and file cleanup
 */

// Start session and include required files
session_start();
require_once '../includes/auth.php';
require_once '../config/database.php';

// Initialize authentication and database
$auth = new Auth();
$db = Database::getInstance();

// Check authentication
$auth->requireAuth();

// Only accept GET or POST requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    die('Method not allowed');
}

// Get parameters
$imageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

// Validate parameters
if ($imageId <= 0) {
    http_response_code(400);
    die('Invalid image ID');
}

try {
    // Get image details before deletion
    $image = $db->fetchOne(
        "SELECT * FROM product_images WHERE id = :id",
        ['id' => $imageId]
    );

    if (!$image) {
        http_response_code(404);
        die('Image not found');
    }

    // Verify the product_id matches (security check)
    if ($productId > 0 && $image['product_id'] != $productId) {
        http_response_code(400);
        die('Product ID mismatch');
    }

    // Start transaction
    $db->getConnection()->beginTransaction();

    // Delete from database first
    $deleted = $db->delete('product_images', 'id = :id', ['id' => $imageId]);

    if (!$deleted) {
        $db->getConnection()->rollBack();
        http_response_code(500);
        die('Failed to delete image from database');
    }

    // Delete physical files
    $mainImagePath = '../' . $image['image_path'];
    $thumbnailPath = '../uploads/thumbnails/' . basename($image['image_path']);

    // Delete main image file
    if (file_exists($mainImagePath)) {
        unlink($mainImagePath);
    }

    // Delete thumbnail if it exists
    if (file_exists($thumbnailPath)) {
        unlink($thumbnailPath);
    }

    // If this was a primary image, set another image as primary
    if ($image['is_primary']) {
        $nextImage = $db->fetchOne(
            "SELECT id FROM product_images WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC LIMIT 1",
            ['product_id' => $image['product_id']]
        );

        if ($nextImage) {
            $db->update(
                'product_images',
                ['is_primary' => 1],
                'id = :id',
                ['id' => $nextImage['id']]
            );
        }
    }

    // Log the activity
    $currentUser = $auth->getCurrentUser();
    if ($currentUser) {
        $db->insert('activity_logs', [
            'user_id' => $currentUser['id'],
            'action' => 'delete_product_image',
            'table_name' => 'product_images',
            'record_id' => $imageId,
            'old_values' => json_encode($image),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    // Commit transaction
    $db->getConnection()->commit();

    // Check if this is an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    if ($isAjax) {
        // Return JSON response for AJAX requests
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Image deleted successfully',
            'image_id' => $imageId
        ]);
    } else {
        // Redirect back to product page for non-AJAX requests
        $redirectUrl = '../products.php';
        if ($productId > 0) {
            $redirectUrl .= '?action=edit&id=' . $productId . '&success=image_deleted';
        } else {
            $redirectUrl .= '?success=image_deleted';
        }

        header('Location: ' . $redirectUrl);
    }
    exit;
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->getConnection()->inTransaction()) {
        $db->getConnection()->rollBack();
    }

    // Log error
    error_log("Delete product image error: " . $e->getMessage());

    // Return error response
    http_response_code(500);

    // If this is an AJAX request, return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Failed to delete image: ' . $e->getMessage()
        ]);
    } else {
        // Redirect with error message
        $redirectUrl = '../products.php';
        if ($productId > 0) {
            $redirectUrl .= '?action=edit&id=' . $productId . '&error=delete_failed';
        } else {
            $redirectUrl .= '?error=delete_failed';
        }
        header('Location: ' . $redirectUrl);
    }
    exit;
}
