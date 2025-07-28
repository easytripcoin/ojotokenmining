<?php
// user/packages.php
require_once '../config/config.php';      // Must load first
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$packages = getAllPackages();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Packages - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>

<body>
    <!-- Sidebar (same as dashboard) -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-coins me-2"></i><?= SITE_NAME ?></h4>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="active"><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
            <li><a href="ewallet.php"><i class="fas fa-wallet"></i> E-Wallet</a></li>
            <li><a href="genealogy.php"><i class="fas fa-sitemap"></i> Genealogy</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">Choose Your Package</h2>

            <div class="row">
                <?php foreach ($packages as $package): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 package-card">
                            <div class="card-header text-center">
                                <h4><?= htmlspecialchars($package['name']) ?></h4>
                                <div class="price"><?= formatCurrency($package['price']) ?></div>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i> <?= BONUS_MONTHS ?> months 50% bonus
                                    </li>
                                    <li><i class="fas fa-check text-success me-2"></i> Multi-level referral bonuses</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Instant activation</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Dashboard access</li>
                                </ul>
                            </div>
                            <div class="card-footer">
                                <form method="POST" action="checkout.php" class="d-grid">
                                    <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-shopping-cart me-2"></i> Buy Now
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>