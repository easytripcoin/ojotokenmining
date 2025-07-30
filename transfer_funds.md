To add a "Transfer Funds" button in `user/ewallet.php` and implement the logic for transferring funds with a transfer charge, follow these steps:

### Step-by-Step Implementation

#### 1. Update `ewallet.php` to Include Transfer Button
Add a new button for transferring funds and a form to handle the transfer.

#### File: `ewallet.php`
```php
<!-- user/ewallet.php -->

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-arrow-up"></i> Transfer Funds</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="transfer.php" id="transferForm">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="mb-3">
                        <label for="recipient_username" class="form-label">Recipient Username</label>
                        <input type="text" class="form-control" id="recipient_username" name="recipient_username" required>
                    </div>

                    <div class="mb-3">
                        <label for="transfer_amount" class="form-label">Amount to Transfer (USDT)</label>
                        <input type="number" class="form-control" id="transfer_amount" name="transfer_amount" min="1" step="0.01" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane"></i> Transfer Funds
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
```

#### 2. Create `transfer.php` to Handle Transfer Logic
Create a new file `transfer.php` to handle the transfer logic, including the transfer charge.

#### File: `transfer.php`
```php
<?php
// user/transfer.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$balance = getEwalletBalance($user_id);
$transfer_charge_percentage = 0.05; // 5% transfer charge

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token.';
    } else {
        $recipient_username = trim($_POST['recipient_username']);
        $transfer_amount = floatval($_POST['transfer_amount']);

        if ($transfer_amount <= 0) {
            $errors['transfer_amount'] = 'Transfer amount must be greater than 0.';
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
                $errors['general'] = 'Failed to process transfer.';
            } else {
                // Add amount to recipient's ewallet (non-withdrawable)
                if (!addEwalletTransaction($recipient_user['id'], 'transfer', $actual_transfer_amount, "Received transfer from $user_id", null, false)) {
                    $errors['general'] = 'Failed to process transfer.';
                } else {
                    // Add transfer charge to admin's ewallet (withdrawable)
                    if (!addEwalletTransaction(1, 'transfer_charge', $transfer_charge, "Transfer charge from $user_id", null, true)) {
                        $errors['general'] = 'Failed to process transfer.';
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

            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
            <?php endif; ?>

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
                    <input type="number" class="form-control" id="transfer_amount" name="transfer_amount" min="1" step="0.01" required>
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
```

#### 3. Update `functions.php` to Include `getUserByUsername` Function
Ensure you have a function to get a user by username.

#### File: `functions.php`
```php
// includes/functions.php

/**
 * Get user by username
 * @param string $username Username
 * @return array|false User data or false if not found
 */
function getUserByUsername($username)
{
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    } catch (Exception $e) {
        logEvent("Get user by username error: " . $e->getMessage(), 'error');
        return false;
    }
}
```

### Summary
By following these steps, you add a "Transfer Funds" button to `ewallet.php`, create a `transfer.php` file to handle the transfer logic, and ensure that the transfer charge is deducted from the amount being transferred. The recipient receives the transferred amount as non-withdrawable funds, while the admin earns the transfer charge as withdrawable funds.