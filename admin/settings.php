<?php
// admin/settings.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

$errors = [];
$success = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token.';
    } else {
        try {
            $settings = [
                'admin_usdt_wallet' => trim($_POST['admin_usdt_wallet']),
                'usdt_rate' => floatval($_POST['usdt_rate']),
                'monthly_bonus_percentage' => intval($_POST['monthly_bonus_percentage']),
                'referral_level_2_percentage' => intval($_POST['referral_level_2_percentage']),
                'referral_level_3_percentage' => intval($_POST['referral_level_3_percentage']),
                'referral_level_4_percentage' => intval($_POST['referral_level_4_percentage']),
                'referral_level_5_percentage' => intval($_POST['referral_level_5_percentage']),
                'default_sponsor_enabled' => isset($_POST['default_sponsor_enabled']) ? '1' : '0',
                'orphan_prevention' => isset($_POST['orphan_prevention']) ? '1' : '0'
            ];

            foreach ($settings as $key => $value) {
                updateAdminSetting($key, $value);
            }

            $success = 'Settings updated successfully.';

        } catch (Exception $e) {
            $errors['general'] = 'Failed to update settings.';
        }
    }
}

// Get current settings
$settings = [];
$keys = [
    'admin_usdt_wallet',
    'usdt_rate',
    'monthly_bonus_percentage',
    'referral_level_2_percentage',
    'referral_level_3_percentage',
    'referral_level_4_percentage',
    'referral_level_5_percentage',
    'default_sponsor_enabled',
    'orphan_prevention'
];

foreach ($keys as $key) {
    $settings[$key] = getAdminSetting($key) ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>System Settings - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>

<body>
    <!-- Admin Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-cogs me-2"></i>Admin Panel</h4>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="withdrawals.php"><i class="fas fa-arrow-down"></i> Withdrawals</a></li>
            <li><a href="refills.php"><i class="fas fa-arrow-up"></i> Refills</a></li>
            <li class="active"><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">System Settings</h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <!-- Payment Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-wallet"></i> Payment Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Admin USDT Wallet Address</label>
                                    <input type="text" class="form-control" name="admin_usdt_wallet"
                                        value="<?= htmlspecialchars($settings['admin_usdt_wallet']) ?>"
                                        placeholder="TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXxx">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">USDT Conversion Rate</label>
                                    <input type="number" class="form-control" name="usdt_rate"
                                        value="<?= htmlspecialchars($settings['usdt_rate']) ?>" step="0.01" min="0.1">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bonus Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-gift"></i> Bonus Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Monthly Bonus (%)</label>
                                    <input type="number" class="form-control" name="monthly_bonus_percentage"
                                        value="<?= htmlspecialchars($settings['monthly_bonus_percentage']) ?>" min="1"
                                        max="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Level 2 Referral (%)</label>
                                    <input type="number" class="form-control" name="referral_level_2_percentage"
                                        value="<?= htmlspecialchars($settings['referral_level_2_percentage']) ?>"
                                        min="0" max="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Level 3 Referral (%)</label>
                                    <input type="number" class="form-control" name="referral_level_3_percentage"
                                        value="<?= htmlspecialchars($settings['referral_level_3_percentage']) ?>"
                                        min="0" max="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Level 4 Referral (%)</label>
                                    <input type="number" class="form-control" name="referral_level_4_percentage"
                                        value="<?= htmlspecialchars($settings['referral_level_4_percentage']) ?>"
                                        min="0" max="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Level 5 Referral (%)</label>
                                    <input type="number" class="form-control" name="referral_level_5_percentage"
                                        value="<?= htmlspecialchars($settings['referral_level_5_percentage']) ?>"
                                        min="0" max="100">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-cogs"></i> System Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="default_sponsor_enabled" value="1"
                                <?= $settings['default_sponsor_enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label">
                                Enable automatic admin sponsor assignment
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="orphan_prevention" value="1"
                                <?= $settings['orphan_prevention'] ? 'checked' : '' ?>>
                            <label class="form-check-label">
                                Prevent orphaned users (assign to admin)
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>