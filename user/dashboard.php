<?php
// user/dashboard.php
require_once '../config/config.php';      // Must load first
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$user = getUserById($user_id);
$stats = getUserStats($user_id);
$active_packages = getUserActivePackages($user_id);
$recent_transactions = getTransactionHistory($user_id, 5);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?= SITE_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>

<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-coins me-2"></i><?= SITE_NAME ?></h4>
        </div>
        <ul class="sidebar-menu">
            <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
            <li><a href="ewallet.php"><i class="fas fa-wallet"></i> E-Wallet</a></li>
            <li><a href="genealogy.php"><i class="fas fa-sitemap"></i> Genealogy</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">Welcome back, <?= htmlspecialchars($user['username']) ?>!</h2>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <i class="fas fa-wallet stats-icon"></i>
                            <h5><?= formatCurrency($stats['ewallet_balance']) ?></h5>
                            <p>E-Wallet Balance</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <i class="fas fa-users stats-icon"></i>
                            <h5><?= $stats['total_referrals'] ?></h5>
                            <p>Referrals</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <i class="fas fa-box stats-icon"></i>
                            <h5><?= $stats['active_packages'] ?></h5>
                            <p>Active Packages</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <i class="fas fa-gift stats-icon"></i>
                            <h5><?= formatCurrency($stats['total_bonuses']) ?></h5>
                            <p>Total Bonuses</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Packages -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-box"></i> Active Packages</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($active_packages)): ?>
                                <p class="text-muted">No active packages. <a href="packages.php"
                                        class="btn btn-primary btn-sm">Buy a Package</a></p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($active_packages as $pkg): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between">
                                                <div>
                                                    <h6><?= htmlspecialchars($pkg['name']) ?></h6>
                                                    <small class="text-muted">Purchased:
                                                        <?= formatDate($pkg['created_at']) ?></small>
                                                </div>
                                                <span class="badge bg-success">Cycle
                                                    <?= $pkg['current_cycle'] ?>/<?= $pkg['total_cycles'] ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history"></i> Recent Transactions</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_transactions)): ?>
                                <p class="text-muted">No transactions yet</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_transactions as $tx): ?>
                                        <div class="list-group-item d-flex justify-content-between">
                                            <span><?= htmlspecialchars($tx['description']) ?></span>
                                            <span class="<?= $tx['amount'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= $tx['amount'] > 0 ? '+' : '' ?>         <?= formatCurrency($tx['amount']) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>

</html>