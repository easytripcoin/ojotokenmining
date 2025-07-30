<?php
// user/profile.php — User profile + update
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$user = getUserById($user_id);
$errors = [];
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'update_profile':
                $email = trim($_POST['email'] ?? '');
                $validation = validateFormData(['email' => $email], [
                    'email' => ['required' => true, 'email' => true]
                ]);

                if ($validation['valid']) {
                    if (updateUserProfile($user_id, ['email' => $email])) {
                        $success = 'Profile updated successfully!';
                        $user = getUserById($user_id); // Refresh data
                    } else {
                        $errors['email'] = 'Email already in use.';
                    }
                } else {
                    $errors = array_merge($errors, $validation['errors']);
                }
                break;

            case 'change_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $errors['password'] = 'All password fields are required.';
                } elseif ($new_password !== $confirm_password) {
                    $errors['password'] = 'Passwords do not match.';
                } elseif (strlen($new_password) < 6) {
                    $errors['password'] = 'Password must be at least 6 characters.';
                } else {
                    // Verify current password
                    if (password_verify($current_password, $user['password'])) {
                        if (updateUserPassword($user_id, password_hash($new_password, PASSWORD_DEFAULT))) {
                            $success = 'Password changed successfully!';
                        } else {
                            $errors['password'] = 'Failed to change password.';
                        }
                    } else {
                        $errors['password'] = 'Current password is incorrect.';
                    }
                }
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Profile - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .avatar-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }

        .avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #667eea;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-coins me-2"></i><?= SITE_NAME ?></h4>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
            <li><a href="ewallet.php"><i class="fas fa-wallet"></i> E-Wallet</a></li>
            <li class="active"><a href="referrals.php"><i class="fas fa-users"></i> My Referrals</a></li>
            <li><a href="genealogy.php"><i class="fas fa-sitemap"></i> Genealogy</a></li>
            <li class="active"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">My Profile</h2>

            <!-- Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
            <?php endif; ?>

            <!-- Profile Card -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-user-circle me-2"></i>Profile Details</h5>
                        </div>
                        <div class="card-body">


                            <!-- Update Profile Form -->
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="update_profile">

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control"
                                            value="<?= htmlspecialchars($user['username']) ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email"
                                            class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                            name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password Card -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5><i class="fas fa-key me-2"></i>Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="change_password">

                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password"
                                        class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                        name="current_password" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password"
                                        class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                        name="new_password" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password"
                                        class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                        name="confirm_password" required>
                                    <?php if (isset($errors['password'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Stats Sidebar -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-bar me-2"></i>Account Stats</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Username</span>
                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Email</span>
                                    <strong><?= htmlspecialchars($user['email']) ?></strong>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Role</span>
                                    <strong><?= ucfirst($user['role']) ?></strong>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Status</span>
                                    <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($user['status']) ?>
                                    </span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Joined</span>
                                    <strong><?= date('M j, Y', strtotime($user['created_at'])) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>