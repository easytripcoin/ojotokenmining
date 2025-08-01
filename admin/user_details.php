<?php
// admin/user_details.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

$userId = (int) ($_GET['id'] ?? 0);
$user = getUserById($userId);

if (!$user) {
    redirectWithMessage('users.php', 'User not found.', 'error');
}

$packages = getUserPackageHistory($userId);
$balance = getEwalletBalance($userId);
$withdrawals = getUserWithdrawalRequests($userId);
$refills = getUserRefillRequests($userId);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Details - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>

<body>

    <!-- Admin Sidebar (copy from other admin pages) -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-cogs me-2"></i>Admin Panel</h4>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="active"><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="withdrawals.php"><i class="fas fa-arrow-down"></i> Withdrawals</a></li>
            <li><a href="refills.php"><i class="fas fa-arrow-up"></i> Refills</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">User Details</h2>

            <!-- Basic Info -->
            <div class="card mb-4">
                <div class="card-header">Account</div>
                <div class="card-body">
                    <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                    <p><strong>Status:</strong>
                        <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'danger' ?>">
                            <?= ucfirst($user['status']) ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Packages -->
            <div class="card mb-4">
                <div class="card-header">Packages</div>
                <div class="card-body">
                    <?php if (!$packages): ?>
                        <p class="text-muted">No packages purchased.</p>
                    <?php else: ?>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Package</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Purchased</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($packages as $p): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($p['name']) ?></td>
                                        <td><?= formatCurrency($p['price']) ?></td>
                                        <td><span class="badge bg-info"><?= ucfirst($p['status']) ?></span></td>
                                        <td><?= $p['purchase_date'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Wallet & Requests -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">Balance</div>
                        <div class="card-body">
                            <h4><?= formatCurrency($balance) ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">Withdrawals</div>
                        <div class="card-body">
                            <?= count($withdrawals) ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">Refills</div>
                        <div class="card-body">
                            <?= count($refills) ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>