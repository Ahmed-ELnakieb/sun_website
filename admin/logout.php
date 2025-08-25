<?php
/**
 * Sun Trading Company - Admin Logout
 */

require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();
$auth->logout();

// Redirect to login page
header('Location: login.php');
exit;