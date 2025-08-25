<?php

/**
 * Sun Trading Company - User Profile Management
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$auth = new Auth();
$auth->requireAuth();

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

$success = '';
$error = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['update_profile'])) {
        $data = [
            'username' => sanitize($_POST['username']),
            'email' => sanitize($_POST['email']),
            'full_name' => sanitize($_POST['full_name'])
        ];

        // Validate required fields
        if (empty($data['username']) || empty($data['email']) || empty($data['full_name'])) {
            $error = 'Please fill all required fields.';
        } else {
            // Check for unique constraints (excluding current user)
            $existing = $db->fetchOne(
                "SELECT id FROM admin_users WHERE (username = :username OR email = :email) AND id != :id",
                ['username' => $data['username'], 'email' => $data['email'], 'id' => $currentUser['id']]
            );

            if ($existing) {
                $error = 'Username or email already exists.';
            } else {
                if ($db->update('admin_users', $data, 'id = :id', ['id' => $currentUser['id']])) {
                    $success = 'Profile updated successfully!';
                    // Refresh current user data
                    $currentUser = $auth->getCurrentUser();
                } else {
                    $error = 'Failed to update profile.';
                }
            }
        }
    }

    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Validate passwords
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Please fill all password fields.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } elseif (!password_verify($currentPassword, $currentUser['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            if ($db->update('admin_users', ['password_hash' => $newPasswordHash], 'id = :id', ['id' => $currentUser['id']])) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to change password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Sun Trading Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">My Profile</h1>
                    <p class="text-muted">Manage your account settings and preferences</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Information -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Profile Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="username" name="username"
                                                value="<?php echo htmlspecialchars($currentUser['username']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name"
                                        value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <input type="text" class="form-control"
                                                value="<?php echo ucfirst($currentUser['role']); ?>" readonly>
                                            <small class="text-muted">Contact an administrator to change your role.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Account Status</label>
                                            <div class="form-control d-flex align-items-center" style="background: #f8f9fa;">
                                                <span class="badge bg-<?php echo $currentUser['is_active'] ? 'success' : 'danger'; ?> me-2">
                                                    <?php echo $currentUser['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                                <small class="text-muted">
                                                    Member since <?php echo date('F Y', strtotime($currentUser['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Profile Statistics -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Account Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                                    <i class="fas fa-user text-white fa-2x"></i>
                                </div>
                                <h5><?php echo htmlspecialchars($currentUser['full_name']); ?></h5>
                                <span class="badge bg-<?php echo $currentUser['role'] === 'admin' ? 'danger' : 'info'; ?>">
                                    <?php echo ucfirst($currentUser['role']); ?>
                                </span>
                            </div>

                            <div class="mb-3">
                                <strong>Last Login:</strong><br>
                                <small class="text-muted">
                                    <?php echo $currentUser['last_login'] ? date('M j, Y g:i A', strtotime($currentUser['last_login'])) : 'Never'; ?>
                                </small>
                            </div>

                            <div class="mb-3">
                                <strong>Account Created:</strong><br>
                                <small class="text-muted">
                                    <?php echo date('M j, Y g:i A', strtotime($currentUser['created_at'])); ?>
                                </small>
                            </div>

                            <div class="mb-3">
                                <strong>Total Sessions:</strong><br>
                                <small class="text-muted">
                                    <?php
                                    // Mock data - you can implement actual session tracking
                                    echo rand(5, 50) . ' sessions';
                                    ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Password Section -->
            <div class="row mt-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="passwordForm">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="new_password" name="new_password"
                                                minlength="6" required>
                                            <small class="text-muted">At least 6 characters</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                                minlength="6" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="change_password" class="btn btn-warning">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Security Tips -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Security Tips</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-shield-alt text-success me-2"></i>
                                    Use a strong, unique password
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-lock text-info me-2"></i>
                                    Change your password regularly
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-eye text-warning me-2"></i>
                                    Never share your login credentials
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-sign-out-alt text-danger me-2"></i>
                                    Always log out when finished
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
        });

        // Real-time password confirmation check
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;

            if (confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>

</html>