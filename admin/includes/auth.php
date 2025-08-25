<?php

/**
 * Sun Trading Company - Admin Authentication System
 * Handles user login, logout, and session management
 */

require_once __DIR__ . '/../config/database.php';

class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->startSession();
    }

    private function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Authenticate user with username/email and password
     */
    public function login($identifier, $password)
    {
        // Check if identifier is email or username
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $sql = "SELECT * FROM admin_users WHERE {$field} = :identifier AND is_active = 1";
        $user = $this->db->fetchOne($sql, ['identifier' => $identifier]);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session data
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_full_name'] = $user['full_name'];
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['admin_login_time'] = time();

            // Update last login
            $this->updateLastLogin($user['id']);

            // Log login activity
            $this->logActivity($user['id'], 'login', 'admin_users', $user['id']);

            return true;
        }

        return false;
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated()
    {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    /**
     * Check if user has specific role
     */
    public function hasRole($role)
    {
        return $this->isAuthenticated() && $_SESSION['admin_role'] === $role;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    /**
     * Get current user data
     */
    public function getCurrentUser()
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return [
            'id' => $_SESSION['admin_user_id'],
            'username' => $_SESSION['admin_username'],
            'full_name' => $_SESSION['admin_full_name'],
            'role' => $_SESSION['admin_role'],
            'login_time' => $_SESSION['admin_login_time']
        ];
    }

    /**
     * Logout user
     */
    public function logout()
    {
        if ($this->isAuthenticated()) {
            $this->logActivity($_SESSION['admin_user_id'], 'logout', 'admin_users', $_SESSION['admin_user_id']);
        }

        // Clear session data
        $_SESSION = [];

        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Require authentication (redirect if not logged in)
     */
    public function requireAuth()
    {
        if (!$this->isAuthenticated()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Require admin role
     */
    public function requireAdmin()
    {
        $this->requireAuth();
        if (!$this->isAdmin()) {
            header('Location: unauthorized.php');
            exit;
        }
    }

    /**
     * Update user's last login timestamp
     */
    private function updateLastLogin($userId)
    {
        $sql = "UPDATE admin_users SET last_login = NOW() WHERE id = :id";
        $this->db->query($sql, ['id' => $userId]);
    }

    /**
     * Log user activity
     */
    private function logActivity($userId, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null)
    {
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        $this->db->insert('activity_logs', $data);
    }

    /**
     * Change user password
     */
    public function changePassword($userId, $newPassword)
    {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $updated = $this->db->update(
            'admin_users',
            ['password_hash' => $passwordHash],
            'id = :id',
            ['id' => $userId]
        );

        if ($updated) {
            $this->logActivity($userId, 'password_change', 'admin_users', $userId);
        }

        return $updated;
    }

    /**
     * Create new admin user
     */
    public function createUser($data)
    {
        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']);

        $userId = $this->db->insert('admin_users', $data);

        if ($userId) {
            $this->logActivity($_SESSION['admin_user_id'], 'user_create', 'admin_users', $userId, null, $data);
        }

        return $userId;
    }

    /**
     * Generate CSRF token
     */
    public function generateCSRFToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
