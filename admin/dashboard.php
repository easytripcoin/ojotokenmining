<?php
// admin/dashboard.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

// Get admin stats
try {
    $pdo = getConnection();

    // Total stats
    $total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $total_packages = $pdo->query("SELECT COUNT(*) FROM user_packages WHERE status = 'active'")->fetchColumn();
    $total_earnings = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM ewallet_transactions WHERE type IN ('purchase', 'bonus', 'referral')")->fetchColumn();

    // Pending requests
    $pending_withdrawals = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'")->fetchColumn();
    $pending_refills = $pdo->query("SELECT COUNT(*) FROM refill_requests WHERE status = 'pending'")->fetchColumn();

    // Recent activity
    $recent_users = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $recent_transactions = $pdo->query("SELECT et.*, u.username FROM ewallet_transactions et JOIN users u ON et.user_id = u.id ORDER BY et.created_at DESC LIMIT 5")->fetchAll();

} catch (Exception $e) {
    $error = "Failed to load admin data";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - <?= SITE_NAME ?></title>
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
            <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="withdrawals.php"><i class="fas fa-arrow-down"></i> Withdrawals</a></li>
            <li><a href="refills.php"><i class="fas fa-arrow-up"></i> Refills</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">Admin Dashboard</h2>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <i class="fas fa-users stats-icon"></i>
                            <h5><?= number_format($total_users) ?></h5>
                            <p>Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <i class="fas fa-box stats-icon"></i>
                            <h5><?= number_format($total_packages) ?></h5>
                            <p>Active Packages</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <i class="fas fa-money-bill-wave stats-icon"></i>
                            <h5><?= formatCurrency($total_earnings * (-1)) ?></h5>
                            <p>Total Earnings</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <i class="fas fa-exclamation-triangle stats-icon"></i>
                            <h5><?= $pending_withdrawals + $pending_refills ?></h5>
                            <p>Pending Requests</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Requests -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-arrow-down"></i> Pending Withdrawals</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($pending_withdrawals > 0): ?>
                                <a href="withdrawals.php?status=pending" class="btn btn-warning">View
                                    <?= $pending_withdrawals ?> pending
                                    withdrawals</a>
                            <?php else: ?>
                                <p class="text-muted">No pending withdrawals</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-arrow-up"></i> Pending Refills</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($pending_refills > 0): ?>
                                <a href="refills.php?status=pending" class="btn btn-info">View <?= $pending_refills ?>
                                    pending refills</a>
                            <?php else: ?>
                                <p class="text-muted">No pending refills</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-users"></i> Recent Users</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Registered</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_users as $user): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($user['username']) ?></td>
                                                <td><?= timeAgo($user['created_at']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history"></i> Recent Transactions</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Amount</th>
                                            <th>Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_transactions as $tx): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($tx['username']) ?></td>
                                                <td class="<?= $tx['amount'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                    <?= $tx['amount'] > 0 ? '+' : '' ?>     <?= formatCurrency($tx['amount']) ?>
                                                </td>
                                                <td><?= ucfirst($tx['type']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
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