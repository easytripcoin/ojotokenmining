<?php
// user/dashboard.php - Complete dashboard with monthly bonus tracking
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$user = getUserById($user_id);
$stats = getUserStats($user_id);
$active_packages = getUserActivePackages($user_id);
$recent_transactions = getTransactionHistory($user_id, 5);

// Get monthly bonus data
try {
    $pdo = getConnection();

    // Get bonus packages
    $stmt = $pdo->prepare("
        SELECT up.*, p.name, p.price,
               (p.price * ? / 100) as monthly_bonus,
               CASE 
                   WHEN up.current_cycle > ? THEN 'withdraw_remine'
                   WHEN up.current_cycle = ? THEN 'last_month'
                   ELSE 'earning'
               END as bonus_status
        FROM user_packages up
        JOIN packages p ON up.package_id = p.id
        WHERE up.user_id = ? AND up.status = 'active'
        ORDER BY up.created_at DESC
    ");
    $stmt->execute([MONTHLY_BONUS_PERCENTAGE, BONUS_MONTHS, BONUS_MONTHS, $user_id]);
    $bonus_packages = $stmt->fetchAll();

    // Get recent bonuses
    $stmt = $pdo->prepare("
        SELECT mb.*, p.name as package_name
        FROM monthly_bonuses mb
        JOIN packages p ON mb.package_id = p.id
        WHERE mb.user_id = ?
        ORDER BY mb.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_bonuses = $stmt->fetchAll();

} catch (Exception $e) {
    $bonus_packages = [];
    $recent_bonuses = [];
}
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
    <style>
        .bonus-card {
            transition: transform 0.2s;
        }

        .bonus-card:hover {
            transform: translateY(-2px);
        }

        .bonus-status {
            font-size: 0.8rem;
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
            <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
            <li><a href="ewallet.php"><i class="fas fa-wallet"></i> E-Wallet</a></li>
            <li><a href="genealogy.php"><i class="fas fa-sitemap"></i> Genealogy</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">Welcome back, <?= htmlspecialchars($user['username']) ?>!</h2>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-wallet stats-icon"></i>
                            <h3 class="text-primary"><?= formatCurrency($stats['ewallet_balance']) ?></h3>
                            <p>E-Wallet Balance</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-users stats-icon"></i>
                            <h3 class="text-primary"><?= $stats['total_referrals'] ?></h3>
                            <p>Referrals</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-box stats-icon"></i>
                            <h3 class="text-primary"><?= $stats['active_packages'] ?></h3>
                            <p>Active Packages</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-gift stats-icon"></i>
                            <h3 class="text-primary"><?= formatCurrency($stats['total_bonuses']) ?></h3>
                            <p>Total Bonuses</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Bonus Section -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-gift"></i> Monthly Bonus Tracking</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($bonus_packages)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-gift fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No active packages for monthly bonus</p>
                                    <a href="packages.php" class="btn btn-primary">Buy Package</a>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($bonus_packages as $pkg): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card bonus-card">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?= htmlspecialchars($pkg['name']) ?></h6>
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-muted">Cycle</span>
                                                        <span
                                                            class="badge bg-primary"><?= $pkg['current_cycle'] ?>/<?= $pkg['total_cycles'] ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mt-2">
                                                        <span class="text-muted">Monthly Bonus</span>
                                                        <strong
                                                            class="text-success"><?= formatCurrency($pkg['monthly_bonus']) ?></strong>
                                                    </div>

                                                    <?php if ($pkg['bonus_status'] === 'earning'): ?>
                                                        <div class="mt-3">
                                                            <span class="badge bg-success">Earning Bonus</span>
                                                            <small class="text-muted d-block">Next bonus:
                                                                <?= date('M j', strtotime('+1 month', strtotime($pkg['purchase_date']))) ?></small>
                                                        </div>
                                                    <?php elseif ($pkg['bonus_status'] === 'last_month'): ?>
                                                        <div class="mt-3">
                                                            <span class="badge bg-warning">Last Month</span>
                                                            <small class="text-muted d-block">Final bonus payment</small>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="mt-3">
                                                            <span class="badge bg-info">Ready</span>
                                                            <div class="btn-group btn-group-sm mt-2">
                                                                <a href="package_action.php?id=<?= $pkg['id'] ?>&action=withdraw"
                                                                    class="btn btn-sm btn-success">
                                                                    <i class="fas fa-arrow-down"></i> Withdraw
                                                                </a>
                                                                <a href="package_action.php?id=<?= $pkg['id'] ?>&action=remine"
                                                                    class="btn btn-sm btn-primary">
                                                                    <i class="fas fa-redo"></i> Remine
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Referral Stats -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-users"></i> Referral Statistics</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $referral_stats = getUserReferralStats($user_id);
                            ?>
                            <div class="row">
                                <div class="col-6">
                                    <h6>Total Referrals</h6>
                                    <h3><?= $referral_stats['total_referrals'] ?></h3>
                                </div>
                                <div class="col-6">
                                    <h6>Total Bonus</h6>
                                    <h3><?= formatCurrency(getUserReferralBonus($user_id)) ?></h3>
                                </div>
                            </div>

                            <?php foreach ($referral_stats['level_stats'] as $level => $stats): ?>
                                <?php if ($stats['count'] > 0): ?>
                                    <div class="mt-3">
                                        <strong>Level <?= $level ?>:</strong>
                                        <span class="float-end"><?= $stats['count'] ?> users -
                                            <?= formatCurrency($stats['bonus']) ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <hr>
                            <a href="genealogy.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-sitemap"></i> View Genealogy
                            </a>
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

                <!-- Recent Bonuses -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history"></i> Recent Bonuses</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_bonuses)): ?>
                                <p class="text-muted">No bonuses yet</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_bonuses as $bonus): ?>
                                        <div class="list-group-item d-flex justify-content-between">
                                            <span><?= htmlspecialchars($bonus['package_name']) ?></span>
                                            <span class="text-success">+<?= formatCurrency($bonus['amount']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history"></i> Recent Transactions</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_transactions)): ?>
                                <p class="text-muted">No transactions yet</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Type</th>
                                                <th>Description</th>
                                                <th>Amount</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_transactions as $tx): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-<?=
                                                            $tx['type'] === 'deposit' ? 'success' :
                                                            ($tx['type'] === 'withdrawal' ? 'danger' : 'info')
                                                            ?>">
                                                            <?= ucfirst($tx['type']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($tx['description']) ?></td>
                                                    <td class="<?= $tx['amount'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                        <?= $tx['amount'] > 0 ? '+' : '' ?>         <?= formatCurrency($tx['amount']) ?>
                                                    </td>
                                                    <td><?= timeAgo($tx['created_at']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="packages.php" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart"></i> Buy Package
                                </a>
                                <a href="ewallet.php" class="btn btn-info">
                                    <i class="fas fa-wallet"></i> E-Wallet
                                </a>
                                <a href="genealogy.php" class="btn btn-secondary">
                                    <i class="fas fa-sitemap"></i> View Genealogy
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh bonus data every 30 seconds
        setInterval(() => {
            // You can add AJAX refresh here later
        }, 30000);

        // Show success messages
        <?php if (isset($_SESSION['message'])): ?>
            const toast = document.createElement('div');
            toast.className = 'alert alert-success position-fixed';
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
            toast.textContent = '<?= addslashes($_SESSION['message']) ?>';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
    </script>
</body>

</html>