<?php

/**
 * Sun Trading Company - Admin Utility Functions
 * Common functions for the admin panel
 */

/**
 * Sanitize input data
 */
function sanitize($data)
{
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Format file size
 */
function formatFileSize($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
}

/**
 * Generate random filename
 */
function generateFileName($originalName)
{
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . strtolower($extension);
}

/**
 * Validate image file
 */
function validateImage($file)
{
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowedTypes)) {
        return 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.';
    }

    if ($file['size'] > $maxSize) {
        return 'File size too large. Maximum 5MB allowed.';
    }

    return true;
}

/**
 * Create thumbnail
 */
function createThumbnail($source, $destination, $width = 150, $height = 150)
{
    $imageInfo = getimagesize($source);
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $sourceType = $imageInfo[2];

    // Create source image resource
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($source);
            break;
        default:
            return false;
    }

    // Calculate aspect ratio
    $sourceRatio = $sourceWidth / $sourceHeight;
    $thumbRatio = $width / $height;

    if ($sourceRatio > $thumbRatio) {
        $thumbWidth = (int)$width;
        $thumbHeight = (int)($width / $sourceRatio);
    } else {
        $thumbHeight = (int)$height;
        $thumbWidth = (int)($height * $sourceRatio);
    }

    // Create thumbnail
    $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);

    // Preserve transparency for PNG and GIF
    if ($sourceType == IMAGETYPE_PNG || $sourceType == IMAGETYPE_GIF) {
        imagealphablending($thumbImage, false);
        imagesavealpha($thumbImage, true);
        $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
        imagefilledrectangle($thumbImage, 0, 0, $thumbWidth, $thumbHeight, $transparent);
    }

    imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight);

    // Save thumbnail
    $result = false;
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($thumbImage, $destination, 80);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($thumbImage, $destination, 8);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($thumbImage, $destination);
            break;
    }

    // Clean up
    imagedestroy($sourceImage);
    imagedestroy($thumbImage);

    return $result;
}

/**
 * Upload file with validation
 */
function uploadFile($file, $uploadDir, $allowedTypes = null, $maxSize = null)
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed with error code: ' . $file['error']];
    }

    // Default settings
    $allowedTypes = $allowedTypes ?? ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = $maxSize ?? 5 * 1024 * 1024; // 5MB

    // Validate file type
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type.'];
    }

    // Validate file size
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File size too large.'];
    }

    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $fileName = generateFileName($file['name']);
    $filePath = $uploadDir . '/' . $fileName;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return [
            'success' => true,
            'filename' => $fileName,
            'filepath' => $filePath,
            'size' => $file['size'],
            'type' => $file['type']
        ];
    } else {
        return ['success' => false, 'error' => 'Failed to move uploaded file.'];
    }
}

/**
 * Delete file safely
 */
function deleteFile($filePath)
{
    if (file_exists($filePath) && is_file($filePath)) {
        return unlink($filePath);
    }
    return false;
}

/**
 * Generate pagination
 */
function generatePagination($currentPage, $totalPages, $baseUrl, $params = [])
{
    $pagination = '';
    $range = 3; // Number of pages to show on each side of current page

    if ($totalPages <= 1) {
        return $pagination;
    }

    $pagination .= '<nav aria-label="Page navigation">';
    $pagination .= '<ul class="pagination justify-content-center">';

    // Previous button
    if ($currentPage > 1) {
        $params['page'] = $currentPage - 1;
        $url = $baseUrl . '?' . http_build_query($params);
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $url . '">Previous</a></li>';
    }

    // Page numbers
    $start = max(1, $currentPage - $range);
    $end = min($totalPages, $currentPage + $range);

    if ($start > 1) {
        $params['page'] = 1;
        $url = $baseUrl . '?' . http_build_query($params);
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $url . '">1</a></li>';
        if ($start > 2) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $params['page'] = $i;
        $url = $baseUrl . '?' . http_build_query($params);
        $activeClass = ($i == $currentPage) ? ' active' : '';
        $pagination .= '<li class="page-item' . $activeClass . '"><a class="page-link" href="' . $url . '">' . $i . '</a></li>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $params['page'] = $totalPages;
        $url = $baseUrl . '?' . http_build_query($params);
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $url . '">' . $totalPages . '</a></li>';
    }

    // Next button
    if ($currentPage < $totalPages) {
        $params['page'] = $currentPage + 1;
        $url = $baseUrl . '?' . http_build_query($params);
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $url . '">Next</a></li>';
    }

    $pagination .= '</ul>';
    $pagination .= '</nav>';

    return $pagination;
}

/**
 * Flash message system
 */
function setFlashMessage($type, $message)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

function getFlashMessages()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Time ago function
 */
function timeAgo($datetime)
{
    $time = time() - strtotime($datetime);

    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . ' minutes ago';
    if ($time < 86400) return floor($time / 3600) . ' hours ago';
    if ($time < 2592000) return floor($time / 86400) . ' days ago';
    if ($time < 31536000) return floor($time / 2592000) . ' months ago';
    return floor($time / 31536000) . ' years ago';
}

/**
 * Truncate text
 */
function truncateText($text, $length = 100, $suffix = '...')
{
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}
