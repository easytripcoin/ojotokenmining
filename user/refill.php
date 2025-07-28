<?php
// user/refill.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$errors = [];
$success = '';

// Get admin USDT wallet
$admin_wallet = getAdminSetting('admin_usdt_wallet') ?: 'TAdminWalletAddressHere';

// Handle refill request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token.';
    } else {
        $validation = validateFormData($_POST, [
            'amount' => ['required' => true, 'numeric' => true, 'min_value' => 20]
        ]);

        if ($validation['valid']) {
            $amount = floatval($_POST['amount']);
            $transaction_hash = trim($_POST['transaction_hash'] ?? '');

            try {
                $pdo = getConnection();

                // Insert refill request
                $stmt = $pdo->prepare("
                    INSERT INTO refill_requests (user_id, amount, transaction_hash) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$user_id, $amount, $transaction_hash]);

                $success = 'Refill request submitted successfully. Your account will be credited once confirmed.';

            } catch (Exception $e) {
                $errors['general'] = 'Failed to submit refill request. Please try again.';
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
    <title>Add Funds - <?= SITE_NAME ?></title>
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
            <li><a href="genealogy.php"><i class="fas fa-sitemap"></i> Genealogy</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">Add Funds to E-Wallet</h2>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-arrow-up"></i> Refill Request</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($success): ?>
                                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                            <?php endif; ?>

                            <?php if (isset($errors['general'])): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                                <div class="mb-3">
                                    <label for="amount" class="form-label">Amount to Add (USDT)</label>
                                    <input type="number"
                                        class="form-control <?= isset($errors['amount']) ? 'is-invalid' : '' ?>"
                                        id="amount" name="amount" min="20" step="0.01" required>
                                    <small class="text-muted">Minimum: 20 USDT</small>
                                    <?php if (isset($errors['amount'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['amount']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="transaction_hash" class="form-label">Transaction Hash (Optional)</label>
                                    <input type="text" class="form-control" id="transaction_hash"
                                        name="transaction_hash" placeholder="0x... (transaction ID)">
                                    <small class="text-muted">Paste your transaction hash after sending USDT</small>
                                </div>

                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-plus"></i> Submit Refill Request
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-info-circle"></i> Payment Instructions</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <h6>Send USDT (TRC20) to:</h6>
                                <div class="bg-light p-3 rounded">
                                    <code class="fw-bold"><?= htmlspecialchars($admin_wallet) ?></code>
                                    <button class="btn btn-sm btn-outline-primary float-end"
                                        onclick="copyToClipboard('<?= htmlspecialchars($admin_wallet) ?>')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>

                            <ol class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Send exact USDT amount</li>
                                <li><i class="fas fa-check text-success me-2"></i> Use TRC20/TRON network only</li>
                                <li><i class="fas fa-check text-success me-2"></i> Submit form with transaction hash
                                </li>
                                <li><i class="fas fa-check text-success me-2"></i> Processing time: 1-6 hours</li>
                                <li><i class="fas fa-check text-success me-2"></i> No refill fees</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Wallet address copied to clipboard!');
            });
        }
    </script>
</body>

</html>