<?php
// user/transfer.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$balance = getEwalletBalance($user_id);

// Fetch transfer settings
$transfer_charge_percentage = floatval(getAdminSetting('transfer_charge_percentage') ?? 0.05); // Default to 5%
$transfer_minimum_amount = floatval(getAdminSetting('transfer_minimum_amount') ?? 1.00); // Default to 1 USDT
$transfer_maximum_amount = floatval(getAdminSetting('transfer_maximum_amount') ?? 10000.00); // Default to 10000 USDT

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token.';
    } else {
        $recipient_username = trim($_POST['recipient_username']);
        $transfer_amount = floatval($_POST['transfer_amount']);

        if ($transfer_amount < $transfer_minimum_amount) {
            $errors['transfer_amount'] = "Transfer amount must be at least " . formatCurrency($transfer_minimum_amount) . ".";
        }

        if ($transfer_amount > $transfer_maximum_amount) {
            $errors['transfer_amount'] = "Transfer amount cannot exceed " . formatCurrency($transfer_maximum_amount) . ".";
        }

        if ($transfer_amount > $balance) {
            $errors['transfer_amount'] = 'Insufficient balance.';
        }

        $recipient_user = getUserByUsername($recipient_username);
        if (!$recipient_user) {
            $errors['recipient_username'] = 'Recipient username not found.';
        }

        if (empty($errors)) {
            $transfer_charge = $transfer_amount * $transfer_charge_percentage;
            $actual_transfer_amount = $transfer_amount - $transfer_charge;

            // Deduct amount from sender's ewallet
            if (!processEwalletTransaction($user_id, 'transfer', -$transfer_amount, "Transfer to $recipient_username")) {
                $errors['general'] = 'Failed to deduct amount from sender\'s e-wallet.';
            } else {
                // Add amount to recipient's ewallet (non-withdrawable)
                if (!addEwalletTransaction($recipient_user['id'], 'transfer', $actual_transfer_amount, "Received transfer from $user_id", null, 0)) {
                    $errors['general'] = 'Failed to add amount to recipient\'s e-wallet.';
                } else {
                    // Add transfer charge to admin's ewallet (withdrawable)
                    if (!addEwalletTransaction(1, 'transfer_charge', $transfer_charge, "Transfer charge from $user_id", null, 1)) {
                        $errors['general'] = 'Failed to add transfer charge to admin\'s e-wallet.';
                    } else {
                        $success = 'Funds transferred successfully.';
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Transfer Funds - <?= SITE_NAME ?></title>
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
            <li><a href="ewallet.php"><i class="fas fa-wallet"></i> E-Wallet</a></li>
            <li><a href="referrals.php"><i class="fas fa-users"></i> My Referrals</a></li>
            <li><a href="genealogy.php"><i class="fas fa-sitemap"></i> Genealogy</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">Transfer Funds</h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error_key => $error_message): ?>
                            <li><?= htmlspecialchars($error_message) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="alert alert-info">
                <i class="fas fa-wallet"></i> Your current e-wallet balance: <?= formatCurrency($balance) ?>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Transfer Information:
                <ul>
                    <li>Minimum Transfer Amount: <?= formatCurrency($transfer_minimum_amount) ?></li>
                    <li>Maximum Transfer Amount: <?= formatCurrency($transfer_maximum_amount) ?></li>
                    <li>Transfer Charge: <?= $transfer_charge_percentage * 100 ?>%</li>
                </ul>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div class="mb-3">
                    <label for="recipient_username" class="form-label">Recipient Username</label>
                    <input type="text" class="form-control" id="recipient_username" name="recipient_username" required>
                    <?php if (isset($errors['recipient_username'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['recipient_username']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="transfer_amount" class="form-label">Amount to Transfer (USDT)</label>
                    <input type="number" class="form-control" id="transfer_amount" name="transfer_amount"
                        min="<?= $transfer_minimum_amount ?>" max="<?= $transfer_maximum_amount ?>" step="0.01"
                        required>
                    <?php if (isset($errors['transfer_amount'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['transfer_amount']) ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-paper-plane"></i> Transfer Funds
                </button>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>