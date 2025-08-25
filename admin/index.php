<?php
/**
 * Sun Trading Company - Admin Panel Index
 * Redirects to dashboard or login depending on authentication status
 */

require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();

if ($auth->isAuthenticated()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;