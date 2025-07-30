<?php
// user/ewallet.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$balance = getEwalletBalance($user_id);
$transactions = getTransactionHistory($user_id, 20);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>E-Wallet - <?= SITE_NAME ?></title>
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
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
            <li class="active"><a href="ewallet.php"><i class="fas fa-wallet"></i> E-Wallet</a></li>
            <li><a href="referrals.php"><i class="fas fa-users"></i> My Referrals</a></li>
            <li><a href="genealogy.php"><i class="fas fa-sitemap"></i> Genealogy</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">E-Wallet Management</h2>

            <!-- Balance Card -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-wallet fa-3x text-primary mb-3"></i>
                            <h3 class="text-primary"><?= formatCurrency($balance) ?></h3>
                            <p class="text-muted">Available Balance</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-6">
                            <a href="withdrawal.php" class="btn btn-danger btn-lg w-100 mb-3">
                                <i class="fas fa-arrow-down"></i> Withdraw Funds
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="refill.php" class="btn btn-success btn-lg w-100 mb-3">
                                <i class="fas fa-arrow-up"></i> Add Funds
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="transfer.php" class="btn btn-warning btn-lg w-100 mb-3">
                                <i class="fas fa-arrow-right"></i> Transfer Funds
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Transaction History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($transactions)): ?>
                        <p class="text-muted">No transactions yet</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $tx): ?>
                                        <tr>
                                            <td><?= formatDate($tx['created_at']) ?></td>
                                            <td>
                                                <span class="badge bg-<?=
                                                    $tx['type'] === 'deposit' ? 'success' :
                                                    ($tx['type'] === 'withdrawal' ? 'danger' :
                                                        ($tx['type'] === 'purchase' ? 'warning' : 'info'))
                                                    ?>">
                                                    <?= ucfirst($tx['type']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($tx['description']) ?></td>
                                            <td class="<?= $tx['amount'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= $tx['amount'] > 0 ? '+' : '' ?>         <?= formatCurrency($tx['amount']) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?=
                                                    $tx['status'] === 'completed' ? 'success' :
                                                    ($tx['status'] === 'pending' ? 'warning' : 'danger')
                                                    ?>">
                                                    <?= ucfirst($tx['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>