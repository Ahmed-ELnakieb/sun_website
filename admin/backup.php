<?php

/**
 * Sun Trading Company - Database Backup Management
 * Based on Critso Template Structure
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$auth->requireAdmin(); // Backup operations require admin privileges

$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();
$db = Database::getInstance();

$success = '';
$error = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create_backup':
                $result = createDatabaseBackup();
                if (!$result['success']) {
                    error_log('Backup failed: ' . $result['message']);
                }
                echo json_encode($result);
                break;
            case 'restore_backup':
                echo json_encode(restoreDatabaseBackup($_POST['filename'] ?? ''));
                break;
            case 'delete_backup':
                echo json_encode(deleteBackupFile($_POST['filename'] ?? ''));
                break;
            case 'download_backup':
                downloadBackupFile($_POST['filename'] ?? '');
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log('Backup exception: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Backup functions
function createDatabaseBackup()
{
    global $db;

    $backupDir = '../backups';
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            return ['success' => false, 'message' => 'Could not create backup directory'];
        }
    }

    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . "/sun_trading_backup_$timestamp.sql";

    // Try different mysqldump paths for WAMP - use file_exists instead of is_executable
    $mysqldumpPaths = [
        'mysqldump'
    ];

    // Scan for MySQL versions in WAMP
    $wampMysqlDir = 'C:\wamp64\bin\mysql';
    if (is_dir($wampMysqlDir)) {
        $versions = glob($wampMysqlDir . '\mysql*');
        foreach ($versions as $versionDir) {
            $mysqldumpPath = $versionDir . '\bin\mysqldump.exe';
            if (file_exists($mysqldumpPath)) {
                $mysqldumpPaths[] = $mysqldumpPath;
            }
        }
    }

    $mysqldumpCmd = null;
    foreach ($mysqldumpPaths as $path) {
        if (file_exists($path) || $path === 'mysqldump') {
            $mysqldumpCmd = $path;
            break;
        }
    }

    if (!$mysqldumpCmd) {
        // Create both PHP backup and remote-compatible backup
        $phpResult = createPHPBackup($backupFile);
        if ($phpResult['success']) {
            // Also create a remote-compatible version
            $remoteBackupFile = str_replace('.sql', '_remote.sql', $backupFile);
            $remoteResult = createRemoteCompatibleBackup($remoteBackupFile);
            if ($remoteResult['success']) {
                $phpResult['message'] .= ' (Local + Remote versions created)';
                $phpResult['remote_file'] = basename($remoteBackupFile);
            }
        }
        return $phpResult;
    }

    // Build mysqldump command
    $command = sprintf(
        '"%s" --host=%s --user=%s %s--single-transaction --routines --triggers --add-drop-table %s',
        $mysqldumpCmd,
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        DB_PASS ? '--password=' . escapeshellarg(DB_PASS) . ' ' : '',
        escapeshellarg(DB_NAME)
    );

    $output = [];
    $returnCode = 0;
    $fullCommand = $command . ' > ' . escapeshellarg($backupFile) . ' 2>&1';

    exec($fullCommand, $output, $returnCode);

    if ($returnCode === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
        try {
            $tables = $db->fetchAll("SHOW TABLES");
            $totalRecords = 0;
            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                $count = $db->fetchOne("SELECT COUNT(*) as count FROM `$tableName`");
                $totalRecords += $count['count'];
            }

            return [
                'success' => true,
                'message' => 'Backup created successfully!',
                'filename' => basename($backupFile),
                'file_size' => filesize($backupFile),
                'tables' => count($tables),
                'records' => $totalRecords
            ];
        } catch (Exception $e) {
            return [
                'success' => true,
                'message' => 'Backup created successfully!',
                'filename' => basename($backupFile),
                'file_size' => filesize($backupFile),
                'tables' => 'Unknown',
                'records' => 'Unknown'
            ];
        }
    }

    if ($returnCode !== 0 || !file_exists($backupFile) || filesize($backupFile) === 0) {
        if (file_exists($backupFile)) {
            unlink($backupFile);
        }
        return createPHPBackup($backupFile);
    }

    $errorMsg = 'Backup failed';
    if (!empty($output)) {
        $errorMsg .= ': ' . implode(' ', $output);
    }
    if (!file_exists($backupFile)) {
        $errorMsg .= ' (file not created)';
    } else if (filesize($backupFile) === 0) {
        $errorMsg .= ' (empty file created)';
    }

    return ['success' => false, 'message' => $errorMsg];
}

function createPHPBackup($backupFile)
{
    global $db;

    try {
        $sql = "";
        $tables = $db->fetchAll("SHOW TABLES");

        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            $sql .= "DROP TABLE IF EXISTS `$tableName`;\n";
            $createTable = $db->fetchOne("SHOW CREATE TABLE `$tableName`");
            $sql .= $createTable['Create Table'] . ";\n\n";

            $rows = $db->fetchAll("SELECT * FROM `$tableName`");
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $columnsList = '`' . implode('`, `', $columns) . '`';

                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $sql .= "INSERT INTO `$tableName` ($columnsList) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        if (file_put_contents($backupFile, $sql) !== false) {
            $totalRecords = 0;
            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                $count = $db->fetchOne("SELECT COUNT(*) as count FROM `$tableName`");
                $totalRecords += $count['count'];
            }

            return [
                'success' => true,
                'message' => 'Backup created successfully (PHP method)!',
                'filename' => basename($backupFile),
                'file_size' => filesize($backupFile),
                'tables' => count($tables),
                'records' => $totalRecords
            ];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'PHP backup failed: ' . $e->getMessage()];
    }

    return ['success' => false, 'message' => 'PHP backup failed: Could not write file'];
}

function restoreDatabaseBackup($filename)
{
    $backupFile = "../backups/$filename";
    if (!file_exists($backupFile)) {
        return ['success' => false, 'message' => 'Backup file not found'];
    }

    // Try different mysql paths for WAMP
    $mysqlPaths = ['mysql'];

    $wampMysqlDir = 'C:\wamp64\bin\mysql';
    if (is_dir($wampMysqlDir)) {
        $versions = glob($wampMysqlDir . '\mysql*');
        foreach ($versions as $versionDir) {
            $mysqlPath = $versionDir . '\bin\mysql.exe';
            if (file_exists($mysqlPath)) {
                $mysqlPaths[] = $mysqlPath;
            }
        }
    }

    $mysqlCmd = null;
    foreach ($mysqlPaths as $path) {
        if (file_exists($path) || $path === 'mysql') {
            $mysqlCmd = $path;
            break;
        }
    }

    if (!$mysqlCmd) {
        return ['success' => false, 'message' => 'mysql not found. Please check MySQL installation.'];
    }

    $command = sprintf(
        '"%s" --host=%s --user=%s %s%s',
        $mysqlCmd,
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        DB_PASS ? '--password=' . escapeshellarg(DB_PASS) . ' ' : '',
        escapeshellarg(DB_NAME)
    );

    $output = [];
    $returnCode = 0;
    $fullCommand = $command . ' < ' . escapeshellarg($backupFile) . ' 2>&1';

    exec($fullCommand, $output, $returnCode);

    $success = $returnCode === 0;
    $message = $success ? 'Database restored successfully!' : 'Restoration failed';

    if (!$success && !empty($output)) {
        $message .= ': ' . implode(' ', $output);
    }

    return [
        'success' => $success,
        'message' => $message
    ];
}

function deleteBackupFile($filename)
{
    $backupFile = "../backups/$filename";
    if (file_exists($backupFile)) {
        unlink($backupFile);
        return ['success' => true, 'message' => 'Backup deleted successfully'];
    }
    return ['success' => false, 'message' => 'Backup file not found'];
}

function downloadBackupFile($filename)
{
    $backupFile = "../backups/$filename";
    if (!file_exists($backupFile)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Backup file not found']);
        return;
    }

    // Set headers for file download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . filesize($backupFile));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // Output file content
    readfile($backupFile);
    exit;
}

function createRemoteCompatibleBackup($backupFile)
{
    global $db;

    try {
        $sql = "-- Sun Trading Database Backup\n";
        $sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Compatible with Remote MySQL Servers (Namecheap, etc.)\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
        $sql .= "SET AUTOCOMMIT=0;\n";
        $sql .= "START TRANSACTION;\n\n";

        $tables = $db->fetchAll("SHOW TABLES");

        foreach ($tables as $table) {
            $tableName = array_values($table)[0];

            // Add table structure
            $sql .= "-- Structure for table `$tableName`\n";
            $sql .= "DROP TABLE IF EXISTS `$tableName`;\n";
            $createTable = $db->fetchOne("SHOW CREATE TABLE `$tableName`");
            $sql .= $createTable['Create Table'] . ";\n\n";

            // Add table data
            $rows = $db->fetchAll("SELECT * FROM `$tableName`");
            if (!empty($rows)) {
                $sql .= "-- Data for table `$tableName`\n";
                $sql .= "LOCK TABLES `$tableName` WRITE;\n";

                $columns = array_keys($rows[0]);
                $columnsList = '`' . implode('`, `', $columns) . '`';

                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            // Escape special characters for remote compatibility
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $sql .= "INSERT INTO `$tableName` ($columnsList) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "UNLOCK TABLES;\n\n";
            }
        }

        $sql .= "COMMIT;\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        if (file_put_contents($backupFile, $sql) !== false) {
            $totalRecords = 0;
            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                $count = $db->fetchOne("SELECT COUNT(*) as count FROM `$tableName`");
                $totalRecords += $count['count'];
            }

            return [
                'success' => true,
                'message' => 'Remote-compatible backup created successfully!',
                'filename' => basename($backupFile),
                'file_size' => filesize($backupFile),
                'tables' => count($tables),
                'records' => $totalRecords
            ];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Remote backup failed: ' . $e->getMessage()];
    }

    return ['success' => false, 'message' => 'Remote backup failed: Could not write file'];
}

function getExistingBackups()
{
    $backupDir = '../backups';
    $backups = [];

    if (is_dir($backupDir)) {
        $files = glob($backupDir . '/sun_trading_backup_*.sql');
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'file_size' => filesize($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

        usort($backups, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }

    return $backups;
}

$existingBackups = getExistingBackups();

// Get database statistics
try {
    $tables = $db->fetchAll("SHOW TABLES");
    $totalRecords = 0;
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        $count = $db->fetchOne("SELECT COUNT(*) as count FROM `$tableName`");
        $totalRecords += $count['count'];
    }
    $tableCount = count($tables);
} catch (Exception $e) {
    $tableCount = 0;
    $totalRecords = 0;
}

// Get backup statistics
$stats = [];
$stats['total_backups'] = count($existingBackups);
$stats['tables'] = $tableCount;
$stats['records'] = $totalRecords;
$stats['last_backup'] = !empty($existingBackups) ? date('M j', strtotime($existingBackups[0]['created_at'])) : 'Never';

$flashMessages = getFlashMessages();
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US">

<head>
    <meta charset="utf-8">
    <title>Database Backup - Sun Trading Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/animate.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/bootstrap.css">
    <link rel="stylesheet" type="text/css" href="assets/vendor/css/styles.css">
    <link rel="stylesheet" href="assets/vendor/font/fonts.css">
    <link rel="stylesheet" href="assets/vendor/icon/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="shortcut icon" href="assets/vendor/images/favicon.png">
</head>

<body class="counter-scroll">
    <div id="wrapper">
        <div id="page" class="">
            <div class="layout-wrap loader-off">
                <div id="preload" class="preload-container">
                    <div class="preloading"><span></span></div>
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
                                            <div class="icon"><i class="fas fa-tachometer-alt"></i></div>
                                            <div class="text">Dashboard</div>
                                        </a>
                                    </li>
                                    <li class="menu-item has-children">
                                        <a href="javascript:void(0);" class="menu-item-button">
                                            <div class="icon"><i class="fas fa-box"></i></div>
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
                                            <div class="icon"><i class="fas fa-edit"></i></div>
                                            <div class="text">Content</div>
                                        </a>
                                    </li>
                                    <li class="menu-item">
                                        <a href="settings.php" class="menu-item-button">
                                            <div class="icon"><i class="fas fa-cog"></i></div>
                                            <div class="text">Settings</div>
                                        </a>
                                    </li>
                                    <li class="menu-item has-children active">
                                        <a href="javascript:void(0);" class="menu-item-button">
                                            <div class="icon"><i class="fas fa-tools"></i></div>
                                            <div class="text">System</div>
                                        </a>
                                        <ul class="sub-menu">
                                            <li class="sub-menu-item">
                                                <a href="users.php" class="">
                                                    <div class="text">Users</div>
                                                </a>
                                            </li>
                                            <li class="sub-menu-item">
                                                <a href="backup.php" class="active">
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
                                    <li class="menu-item">
                                        <a href="javascript:void(0);" onclick="window.open('../', '_blank');" class="menu-item-button">
                                            <div class="icon"><i class="fas fa-external-link-alt"></i></div>
                                            <div class="text">View Website</div>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="bottom">
                            <div class="image">
                                <i class="fas fa-database" style="font-size: 48px; color: #C0FAA0;"></i>
                            </div>
                            <div class="content">
                                <p class="f12-regular text-White">Database Backup</p>
                                <p class="f12-bold text-White">Developed by <a href="mailto:ahmedelnakieb95@gmail.com" style="color: #C0FAA0; text-decoration: none;">Elnakieb</a></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- section-content-right -->
                <div class="section-content-right">
                    <!-- header-dashboard -->
                    <div class="header-dashboard">
                        <div class="wrap">
                            <div class="header-left">
                                <div class="button-show-hide"><i class="icon-menu"></i></div>
                                <h6>Database Backup</h6>
                            </div>
                            <div class="header-grid">
                                <div class="header-btn">
                                    <div class="popup-wrap noti type-header">
                                        <div class="dropdown">
                                            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <span class="header-item"><i class="icon-notification1"></i></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="line1"></div>
                                <div class="popup-wrap user type-header">
                                    <div class="dropdown">
                                        <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- main-content -->
                    <div class="main-content">
                        <div class="main-content-inner">
                            <?php if ($success): ?>
                                <div class="alert alert-success" style="margin: 20px;"><?php echo $success; ?></div>
                            <?php endif; ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger" style="margin: 20px;"><?php echo $error; ?></div>
                            <?php endif; ?>

                            <div class="main-content-wrap">
                                <div class="tf-container">
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <!-- Page Header -->
                                            <div class="flex items-center flex-wrap justify-between gap20 mb-32">
                                                <div>
                                                    <h3>Database Backup</h3>
                                                    <div class="body-text mt-8">Manage database backups and restoration</div>
                                                </div>
                                                <div class="flex gap16">
                                                    <button class="tf-button style-1 type-fill" onclick="createBackup()">
                                                        <i class="fas fa-download"></i>Create Backup
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Statistics Cards -->
                                            <div class="flex gap16 flex-wrap mb-32">
                                                <div class="wg-card style-1 bg-Primary" style="min-width: 200px; flex: 1; padding: 16px;">
                                                    <div class="flex items-center gap12">
                                                        <div class="icon">
                                                            <i class="fas fa-table" style="font-size: 20px; color: #fff; background: rgba(255,255,255,0.1); padding: 8px; border-radius: 6px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <h6 class="counter text-White mb-0">
                                                                <span class="number"><?php echo $stats['tables']; ?></span>
                                                            </h6>
                                                            <div class="f12-medium text-White">Tables</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="wg-card" style="min-width: 200px; flex: 1; padding: 16px;">
                                                    <div class="flex items-center gap12">
                                                        <div class="icon">
                                                            <i class="fas fa-database" style="font-size: 20px; color: #161326; background: #C0FAA0; padding: 8px; border-radius: 6px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <h6 class="counter mb-0">
                                                                <span class="number"><?php echo number_format($stats['records']); ?></span>
                                                            </h6>
                                                            <div class="f12-medium">Records</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="wg-card" style="min-width: 200px; flex: 1; padding: 16px;">
                                                    <div class="flex items-center gap12">
                                                        <div class="icon">
                                                            <i class="fas fa-archive" style="font-size: 20px; color: #fff; background: #C388F7; padding: 8px; border-radius: 6px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <h6 class="counter mb-0">
                                                                <span class="number"><?php echo $stats['total_backups']; ?></span>
                                                            </h6>
                                                            <div class="f12-medium">Backups</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="wg-card style-1 bg-YellowGreen" style="min-width: 200px; flex: 1; padding: 16px;">
                                                    <div class="flex items-center gap12">
                                                        <div class="icon">
                                                            <i class="fas fa-clock" style="font-size: 20px; color: #fff; background: #161326; padding: 8px; border-radius: 6px;"></i>
                                                        </div>
                                                        <div class="content">
                                                            <h6 class="counter mb-0">
                                                                <span class="number"><?php echo $stats['last_backup']; ?></span>
                                                            </h6>
                                                            <div class="f12-medium">Last Backup</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Namecheap Deployment Info -->
                                            <div class="wg-box mb-32">
                                                <div class="flex items-center justify-between mb-20">
                                                    <div class="body-title">Deploy to Namecheap Server</div>
                                                    <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: #C388F7;"></i>
                                                </div>
                                                <div class="alert alert-info" style="background: rgba(33, 150, 243, 0.1); border-color: #2196F3; color: #1976D2;">
                                                    <h6><i class="fas fa-info-circle"></i> How to Import Backup to Namecheap MySQL:</h6>
                                                    <ol style="margin: 10px 0; padding-left: 20px;">
                                                        <li><strong>Download Backup:</strong> Click the download button next to any backup file</li>
                                                        <li><strong>Access cPanel:</strong> Login to your Namecheap hosting cPanel</li>
                                                        <li><strong>Open phpMyAdmin:</strong> Find and click on phpMyAdmin in the Databases section</li>
                                                        <li><strong>Select Database:</strong> Choose your database from the left sidebar</li>
                                                        <li><strong>Import File:</strong> Click 'Import' tab, choose your downloaded .sql file, and click 'Go'</li>
                                                        <li><strong>Update Config:</strong> Update your database connection details in config/database.php</li>
                                                    </ol>
                                                    <p><strong>Note:</strong> Remote-compatible versions (*_remote.sql) are optimized for hosting servers and include proper transaction handling.</p>
                                                </div>
                                            </div>

                                            <!-- Existing Backups -->
                                            <?php if (!empty($existingBackups)): ?>
                                                <div class="wg-box">
                                                    <div class="flex items-center justify-between mb-20">
                                                        <div class="body-title">Existing Backups</div>
                                                        <div class="body-text">Total: <?php echo count($existingBackups); ?> backups</div>
                                                    </div>
                                                    <div class="wg-table table-all-user">
                                                        <div class="table-responsive">
                                                            <table class="table table-striped table-bordered">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Backup File</th>
                                                                        <th>Created</th>
                                                                        <th>Size</th>
                                                                        <th>Actions</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($existingBackups as $backup): ?>
                                                                        <tr>
                                                                            <td>
                                                                                <div class="flex items-center gap12">
                                                                                    <div class="image">
                                                                                        <i class="fas fa-file-archive" style="font-size: 24px; color: #2196F3;"></i>
                                                                                    </div>
                                                                                    <div class="content">
                                                                                        <div class="body-title"><?php echo htmlspecialchars($backup['filename']); ?></div>
                                                                                        <div class="text-tiny">SQL Database Backup</div>
                                                                                    </div>
                                                                                </div>
                                                                            </td>
                                                                            <td><?php echo date('M j, Y H:i', strtotime($backup['created_at'])); ?></td>
                                                                            <td><?php echo number_format($backup['file_size'] / 1024, 2); ?> KB</td>
                                                                            <td>
                                                                                <div class="list-icon-function">
                                                                                    <button class="item download" onclick="downloadBackup('<?php echo htmlspecialchars($backup['filename']); ?>')" title="Download">
                                                                                        <i class="fas fa-download"></i>
                                                                                    </button>
                                                                                    <button class="item edit" onclick="restoreBackup('<?php echo htmlspecialchars($backup['filename']); ?>')" title="Restore">
                                                                                        <i class="fas fa-upload"></i>
                                                                                    </button>
                                                                                    <button class="item delete" onclick="deleteBackup('<?php echo htmlspecialchars($backup['filename']); ?>')" title="Delete">
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
                                            <?php else: ?>
                                                <div class="wg-box text-center p-40">
                                                    <i class="fas fa-archive" style="font-size: 48px; color: #ddd; margin-bottom: 16px;"></i>
                                                    <h5>No Backups Found</h5>
                                                    <p class="text-muted">Start by creating your first backup.</p>
                                                    <button class="tf-button style-1 type-fill" onclick="createBackup()">
                                                        <i class="fas fa-download"></i>Create First Backup
                                                    </button>
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

    <!-- Delete Backup Modal -->
    <div class="modal fade" id="deleteBackupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this backup? This action cannot be undone.</p>
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This will permanently delete the backup file.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="tf-button style-1 type-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="tf-button style-1 type-fill" style="background: #dc3545; border-color: #dc3545;" onclick="confirmDeleteBackup()">
                        <i class="fas fa-trash"></i>Delete Backup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Backup Modal -->
    <div class="modal fade" id="restoreBackupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Restore Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to restore this backup? This will replace all current database content.</p>
                    <div class="alert alert-danger">
                        <strong>Warning:</strong> This will overwrite all current data. Make sure you have a recent backup before proceeding.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="tf-button style-1 type-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="tf-button style-1 type-fill" style="background: #ff9800; border-color: #ff9800;" onclick="confirmRestoreBackup()">
                        <i class="fas fa-upload"></i>Restore Backup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/vendor/js/jquery.min.js"></script>
    <script src="assets/vendor/js/bootstrap.min.js"></script>
    <script src="assets/vendor/js/main.js"></script>

    <script>
        let currentBackupFile = '';

        function createBackup() {
            // Show creating message
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Creating...';
            button.disabled = true;

            $.post('backup.php', {
                action: 'create_backup'
            }, function(response) {
                // Show created message briefly
                button.innerHTML = '<i class="fas fa-check"></i>Created';
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }, 'json');
        }

        function downloadBackup(filename) {
            // Create a form to submit for file download
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'backup.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'download_backup';

            const filenameInput = document.createElement('input');
            filenameInput.type = 'hidden';
            filenameInput.name = 'filename';
            filenameInput.value = filename;

            form.appendChild(actionInput);
            form.appendChild(filenameInput);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        function restoreBackup(filename) {
            currentBackupFile = filename;
            $('#restoreBackupModal').modal('show');
        }

        function confirmRestoreBackup() {
            $('#restoreBackupModal').modal('hide');
            $.post('backup.php', {
                action: 'restore_backup',
                filename: currentBackupFile
            }, function(response) {
                location.reload();
            }, 'json');
        }

        function deleteBackup(filename) {
            currentBackupFile = filename;
            $('#deleteBackupModal').modal('show');
        }

        function confirmDeleteBackup() {
            $('#deleteBackupModal').modal('hide');
            $.post('backup.php', {
                action: 'delete_backup',
                filename: currentBackupFile
            }, function(response) {
                location.reload();
            }, 'json');
        }
    </script>
</body>

</html>