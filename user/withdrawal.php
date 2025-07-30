<?php
// user/withdrawal.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
// $balance = getEwalletBalance($user_id);
$balance = getWithdrawableBalance($user_id);
$errors = [];
$success = '';

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token.';
    } else {
        // Validate form data
        $validation = validateFormData($_POST, [
            'amount' => ['required' => true, 'numeric' => true, 'min_value' => 10],
            'wallet_address' => ['required' => true, 'regex' => '/^T[A-Za-z0-9]{33}$/']
        ]);

        if ($validation['valid']) {
            $amount = floatval($_POST['amount']);
            $wallet_address = trim($_POST['wallet_address']);
            $usdt_amount = $amount; // 1:1 conversion for now

            // Check minimum withdrawal
            if ($amount < 10) {
                $errors['amount'] = 'Minimum withdrawal amount is 10 USDT';
            } elseif ($amount > $balance) {
                $errors['amount'] = 'Insufficient withdrawable balance';
            } elseif (strlen($wallet_address) < 25 || !preg_match('/^T[A-Za-z0-9]{33}$/', $wallet_address)) {
                $errors['wallet_address'] = 'Invalid USDT wallet address';
            } else {
                // Process withdrawal request
                try {
                    $pdo = getConnection();
                    $stmt = $pdo->prepare("
                        INSERT INTO withdrawal_requests (user_id, amount, usdt_amount, wallet_address) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$user_id, $amount, $usdt_amount, $wallet_address]);

                    $withdrawal_id = $pdo->lastInsertId();

                    // 2. Then use processEwalletTransaction with the reference
                    if (
                        processEwalletTransaction(
                            $user_id,
                            'withdrawal',
                            -$amount,
                            'Withdrawal request pending approval',
                            $withdrawal_id
                        )
                    ) {
                        $success = 'Withdrawal request submitted successfully.';
                    } else {
                        // Rollback withdrawal if ewallet transaction fails
                        $pdo->prepare("DELETE FROM withdrawal_requests WHERE id = ?")->execute([$withdrawal_id]);
                        $errors['general'] = 'Failed to process withdrawal.';
                    }
                } catch (Exception $e) {
                    // $errors['general'] = 'An error occurred. Please try again.';
                    $errors['general'] = $e->getMessage();
                }
            }
        } else {
            $errors = $validation['errors'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Withdraw Funds - <?= SITE_NAME ?></title>
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
            <h2 class="mb-4">Withdraw Funds</h2>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-arrow-down"></i> Withdrawal Request</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                            <?php endif; ?>

                            <?php if (isset($errors['general'])): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
                            <?php endif; ?>

                            <?php
                            if (isset($errors['general'])) {
                                // Debug information
                                echo "<pre>";
                                echo "Withdrawal attempt failed\n";
                                echo "CSRF Status: " . (!verifyCSRFToken($_POST['csrf_token'] ?? '') ? 'INVALID' : 'VALID') . "\n";
                                echo "Amount: " . htmlspecialchars($_POST['amount']) . "\n";
                                echo "Wallet Address Status: " . ((strlen($_POST['wallet_address']) < 25 || !preg_match('/^T[A-Za-z0-9]{33}$/', $_POST['wallet_address'])) ? "INVALID" : "VALID") . "\n";
                                echo "</pre>";
                            }
                            ?>

                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                                <div class="mb-3">
                                    <label class="form-label">Current Balance</label>
                                    <div class="alert alert-info">
                                        <i class="fas fa-wallet"></i> <?= formatCurrency($balance) ?>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="amount" class="form-label">Amount to Withdraw (USDT)</label>
                                    <input type="number"
                                        class="form-control <?= isset($errors['amount']) ? 'is-invalid' : '' ?>"
                                        id="amount" name="amount" min="10" max="<?= $balance ?>" step="0.01" required>
                                    <small class="text-muted">Minimum: 10 USDT</small>
                                    <?php if (isset($errors['amount'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['amount']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="wallet_address" class="form-label">USDT Wallet Address (TRC20)</label>
                                    <input type="text"
                                        class="form-control <?= isset($errors['wallet_address']) ? 'is-invalid' : '' ?>"
                                        id="wallet_address" name="wallet_address" placeholder="T... (34 characters)"
                                        maxlength="34" required>
                                    <small class="text-muted">TRC20 format: starts with 'T'</small>
                                    <?php if (isset($errors['wallet_address'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['wallet_address']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="fas fa-paper-plane"></i> Submit Withdrawal Request
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-info-circle"></i> Withdrawal Information</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Minimum withdrawal: 10 USDT</li>
                                <li><i class="fas fa-check text-success me-2"></i> Processing time: 1-24 hours</li>
                                <li><i class="fas fa-check text-success me-2"></i> Network: TRC20 (TRON)</li>
                                <li><i class="fas fa-check text-success me-2"></i> No withdrawal fees</li>
                            </ul>

                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Ensure your wallet address is correct. Incorrect addresses may result in loss of funds.
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