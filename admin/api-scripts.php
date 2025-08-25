<?php

/**
 * Sun Trading Company - Web API for Database Operations
 * Provides web interface access to backup scripts
 */

session_start();
require_once 'includes/auth.php';
require_once 'config/database.php';

// Check authentication and admin status
$auth = new Auth();
if (!$auth->isAuthenticated() || !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'run_backup':
            $result = runBackupScript();
            break;
        case 'run_seeder':
            $script = $_POST['script'] ?? '';
            $result = runSeederScript($script);
            break;
        case 'run_migration':
            $script = $_POST['script'] ?? '';
            $result = runMigrationScript($script);
            break;
        default:
            $result = ['success' => false, 'message' => 'Invalid action'];
    }

    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function runBackupScript()
{
    $scriptPath = __DIR__ . '/../scripts/backup-database.php';

    if (!file_exists($scriptPath)) {
        return ['success' => false, 'message' => 'Backup script not found'];
    }

    // Set environment variable to indicate web execution
    $_ENV['WEB_EXECUTION'] = '1';

    // Capture output
    ob_start();
    $success = true;

    try {
        // Include the script instead of executing via shell for better control
        include $scriptPath;
    } catch (Exception $e) {
        $success = false;
        $error = $e->getMessage();
    }

    $output = ob_get_clean();

    if ($success && strpos($output, '✅ Database backup created successfully!') !== false) {
        // Extract backup information from output
        preg_match('/File: (.+?)\.sql/', $output, $fileMatch);
        preg_match('/Size: (.+?) KB/', $output, $sizeMatch);
        preg_match('/Tables: (\d+)/', $output, $tablesMatch);
        preg_match('/Total Records: (\d+)/', $output, $recordsMatch);

        return [
            'success' => true,
            'message' => 'Backup created successfully!',
            'output' => $output,
            'details' => [
                'size' => $sizeMatch[1] ?? 'Unknown',
                'tables' => $tablesMatch[1] ?? 'Unknown',
                'records' => $recordsMatch[1] ?? 'Unknown'
            ]
        ];
    }

    return [
        'success' => false,
        'message' => 'Backup failed',
        'output' => $output
    ];
}

function runSeederScript($scriptName)
{
    $allowedScripts = [
        'seed-products.php',
        'populate-content.php',
        'add-contact-content.php',
        'add-contact-settings.php'
    ];

    if (!in_array($scriptName, $allowedScripts)) {
        return ['success' => false, 'message' => 'Invalid script name'];
    }

    $scriptPath = __DIR__ . "/../scripts/$scriptName";

    if (!file_exists($scriptPath)) {
        return ['success' => false, 'message' => 'Script not found'];
    }

    // Capture output
    ob_start();
    $success = true;

    try {
        include $scriptPath;
    } catch (Exception $e) {
        $success = false;
        $error = $e->getMessage();
    }

    $output = ob_get_clean();

    return [
        'success' => $success,
        'message' => $success ? 'Script executed successfully' : ($error ?? 'Script execution failed'),
        'output' => $output
    ];
}

function runMigrationScript($scriptName)
{
    $allowedScripts = [
        'add-primary-image-column.php'
    ];

    if (!in_array($scriptName, $allowedScripts)) {
        return ['success' => false, 'message' => 'Invalid migration script'];
    }

    $scriptPath = __DIR__ . "/../scripts/$scriptName";

    if (!file_exists($scriptPath)) {
        return ['success' => false, 'message' => 'Migration script not found'];
    }

    // Capture output
    ob_start();
    $success = true;

    try {
        include $scriptPath;
    } catch (Exception $e) {
        $success = false;
        $error = $e->getMessage();
    }

    $output = ob_get_clean();

    return [
        'success' => $success,
        'message' => $success ? 'Migration executed successfully' : ($error ?? 'Migration failed'),
        'output' => $output
    ];
}
