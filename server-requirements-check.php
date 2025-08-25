<?php

/**
 * Server Requirements Checker for Sun Trading Company Website
 * 
 * Upload this file to your server and run it to check if your server
 * meets all the requirements for the Sun Trading website.
 * 
 * Developed by Ahmed Elnakieb
 * Email: ahmedelnakieb95@gmail.com
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Requirements Check - Sun Trading</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: #E9A319;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .requirement {
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }

        .pass {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .fail {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .status {
            font-weight: bold;
            float: right;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>🌞 Sun Trading Company - Server Requirements Check</h1>
        <p>Checking if your server meets all requirements...</p>
    </div>

    <?php
    $allPassed = true;

    function checkRequirement($name, $condition, $required = true)
    {
        global $allPassed;
        $status = $condition ? 'PASS' : 'FAIL';
        $class = $condition ? 'pass' : ($required ? 'fail' : 'warning');

        if (!$condition && $required) {
            $allPassed = false;
        }

        echo "<div class='requirement $class'>";
        echo "<strong>$name</strong>";
        echo "<span class='status'>$status</span>";
        echo "</div>";

        return $condition;
    }

    function checkExtension($extension, $required = true)
    {
        return checkRequirement(
            "PHP Extension: $extension",
            extension_loaded($extension),
            $required
        );
    }

    // PHP Version Check
    $phpVersion = PHP_VERSION;
    $phpOk = version_compare($phpVersion, '7.4.0', '>=');
    checkRequirement("PHP Version: $phpVersion (Required: 7.4+)", $phpOk);

    // PHP Extensions
    echo "<div class='info'><strong>PHP Extensions Check:</strong></div>";
    checkExtension('pdo');
    checkExtension('pdo_mysql');
    checkExtension('json');
    checkExtension('mbstring');
    checkExtension('fileinfo');
    checkExtension('gd');
    checkExtension('zip', false); // Optional for backup features
    checkExtension('curl', false); // Optional for external APIs

    // Memory Limit
    $memoryLimit = ini_get('memory_limit');
    $memoryOk = (int)$memoryLimit >= 128 || $memoryLimit == -1;
    checkRequirement("Memory Limit: $memoryLimit (Recommended: 128M+)", $memoryOk, false);

    // Upload Limits
    $maxFileSize = ini_get('upload_max_filesize');
    $maxPostSize = ini_get('post_max_size');
    $uploadOk = (int)$maxFileSize >= 10 && (int)$maxPostSize >= 10;
    checkRequirement("File Upload Limit: $maxFileSize / Post: $maxPostSize (Recommended: 10M+)", $uploadOk, false);

    // Directory Permissions
    echo "<div class='info'><strong>Directory Permissions Check:</strong></div>";

    $dirsToCheck = ['uploads', 'backups', 'admin/assets'];
    foreach ($dirsToCheck as $dir) {
        if (is_dir($dir)) {
            $writable = is_writable($dir);
            checkRequirement("Directory '$dir' writable", $writable);
        } else {
            checkRequirement("Directory '$dir' exists", false, false);
        }
    }

    // Database Test (if config exists)
    echo "<div class='info'><strong>Database Connection Test:</strong></div>";

    if (file_exists('admin/config/database.php')) {
        try {
            require_once 'admin/config/database.php';
            $db = Database::getInstance();
            $connection = $db->getConnection();
            checkRequirement("Database Connection", true);

            // Test a simple query
            $version = $connection->query('SELECT VERSION() as version')->fetch();
            $mysqlVersion = $version['version'];
            $mysqlOk = version_compare($mysqlVersion, '5.7.0', '>=');
            checkRequirement("MySQL Version: $mysqlVersion (Required: 5.7+)", $mysqlOk);
        } catch (Exception $e) {
            checkRequirement("Database Connection: " . $e->getMessage(), false);
        }
    } else {
        checkRequirement("Database Config File", false, false);
        echo "<div class='warning'>Database configuration file not found. Please configure database settings first.</div>";
    }

    // Security Checks
    echo "<div class='info'><strong>Security Checks:</strong></div>";

    $allowUrlFopen = ini_get('allow_url_fopen');
    checkRequirement("allow_url_fopen disabled (Security)", !$allowUrlFopen, false);

    $exposePhp = ini_get('expose_php');
    checkRequirement("expose_php disabled (Security)", !$exposePhp, false);

    // Final Result
    echo "<div class='info'><strong>Overall Status:</strong></div>";

    if ($allPassed) {
        echo "<div class='pass'><strong>✅ ALL REQUIREMENTS PASSED!</strong><br>";
        echo "Your server is ready for the Sun Trading Company website.</div>";
    } else {
        echo "<div class='fail'><strong>❌ SOME REQUIREMENTS FAILED!</strong><br>";
        echo "Please fix the failed requirements before proceeding with deployment.</div>";
    }

    // Additional Information
    echo "<div class='info'><strong>Server Information:</strong></div>";
    echo "<div class='requirement info'>";
    echo "<strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
    echo "<strong>PHP SAPI:</strong> " . php_sapi_name() . "<br>";
    echo "<strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "<br>";
    echo "<strong>Current Directory:</strong> " . getcwd() . "<br>";
    echo "<strong>Date/Time:</strong> " . date('Y-m-d H:i:s T') . "<br>";
    echo "</div>";

    ?>

    <div class="footer">
        <p><strong>Sun Trading Company Website</strong></p>
        <p>Developed by Ahmed Elnakieb | <a href="mailto:ahmedelnakieb95@gmail.com">ahmedelnakieb95@gmail.com</a></p>
        <p>© 2024 All Rights Reserved</p>
    </div>

    <div class="info" style="margin-top: 20px;">
        <strong>Next Steps:</strong>
        <ol>
            <li>Fix any failed requirements above</li>
            <li>Upload all project files to your server</li>
            <li>Configure database settings in <code>/admin/config/database.php</code></li>
            <li>Import database from backup files</li>
            <li>Access admin panel and change default password</li>
            <li>Delete this requirements checker file</li>
        </ol>
    </div>
</body>

</html>