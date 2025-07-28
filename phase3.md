# 🚀 **Phase 3: E-Wallet System**
> Complete wallet management with withdrawal, refill, and transaction history

---

## 📋 **Phase 3 Files Overview**

| File                    | Purpose                              | Status       |
| ----------------------- | ------------------------------------ | ------------ |
| `user/ewallet.php`      | Wallet balance & transaction history | **New**      |
| `user/withdrawal.php`   | USDT withdrawal requests             | **New**      |
| `user/refill.php`       | USDT refill requests                 | **New**      |
| Updated `functions.php` | E-wallet transaction processing      | **Enhanced** |

---

## 📁 **File 1: `user/ewallet.php`**

```php
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
                        <?= $tx['amount'] > 0 ? '+' : '' ?><?= formatCurrency($tx['amount']) ?>
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
```

---

## 📁 **File 2: `user/withdrawal.php`**

```php
<?php
// user/withdrawal.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$balance = getEwalletBalance($user_id);
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
                $errors['amount'] = 'Insufficient balance';
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

                    // Deduct from balance (pending approval)
                    if (processEwalletTransaction($user_id, 'withdrawal_pending', -$amount, 
                        'Withdrawal request pending approval', $pdo->lastInsertId())) {
                        
                        $success = 'Withdrawal request submitted successfully. You will receive confirmation once processed.';
                    } else {
                        $errors['general'] = 'Failed to process withdrawal. Please try again.';
                    }
                } catch (Exception $e) {
                    $errors['general'] = 'An error occurred. Please try again.';
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
                         id="amount" 
                         name="amount" 
                         min="10" 
                         max="<?= $balance ?>"
                         step="0.01"
                         required>
                  <small class="text-muted">Minimum: 10 USDT</small>
                  <?php if (isset($errors['amount'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['amount']) ?></div>
                  <?php endif; ?>
                </div>

                <div class="mb-3">
                  <label for="wallet_address" class="form-label">USDT Wallet Address (TRC20)</label>
                  <input type="text" 
                         class="form-control <?= isset($errors['wallet_address']) ? 'is-invalid' : '' ?>"
                         id="wallet_address" 
                         name="wallet_address"
                         placeholder="T... (34 characters)"
                         maxlength="34"
                         required>
                  <small class="text-muted">TRC20 format: starts with 'T'</small>
                  <?php if (isset($errors['wallet_address'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['wallet_address']) ?></div>
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
```

---

## 📁 **File 3: `user/refill.php`**

```php
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
                         id="amount" 
                         name="amount" 
                         min="20"
                         step="0.01"
                         required>
                  <small class="text-muted">Minimum: 20 USDT</small>
                  <?php if (isset($errors['amount'])): ?>
                    <div class="invalid-feedback"><?= htmlspecialchars($errors['amount']) ?></div>
                  <?php endif; ?>
                </div>

                <div class="mb-3">
                  <label for="transaction_hash" class="form-label">Transaction Hash (Optional)</label>
                  <input type="text" 
                         class="form-control"
                         id="transaction_hash" 
                         name="transaction_hash"
                         placeholder="0x... (transaction ID)">
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
                  <button class="btn btn-sm btn-outline-primary float-end" onclick="copyToClipboard('<?= htmlspecialchars($admin_wallet) ?>')">
                    <i class="fas fa-copy"></i>
                  </button>
                </div>
              </div>

              <ol class="list-unstyled">
                <li><i class="fas fa-check text-success me-2"></i> Send exact USDT amount</li>
                <li><i class="fas fa-check text-success me-2"></i> Use TRC20/TRON network only</li>
                <li><i class="fas fa-check text-success me-2"></i> Submit form with transaction hash</li>
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
```

---

## 🔧 **Updated Functions for E-Wallet (Add to `functions.php`)**

```php
// Add these functions to includes/functions.php

/**
 * Get withdrawal requests for user
 * @param int $user_id User ID
 * @return array Withdrawal requests
 */
function getUserWithdrawalRequests($user_id) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM withdrawal_requests 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get withdrawal requests error: " . $e->getMessage(), 'error');
        return [];
    }
}

/**
 * Get refill requests for user
 * @param int $user_id User ID
 * @return array Refill requests
 */
function getUserRefillRequests($user_id) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM refill_requests 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        logEvent("Get refill requests error: " . $e->getMessage(), 'error');
        return [];
    }
}
```

---

## ✅ **Phase 3 Complete - Ready for Testing**

### **Test the E-Wallet System:**

1. **E-Wallet Page** (`user/ewallet.php`)
   - View balance and transaction history
   - Quick access to withdraw/refill

2. **Withdrawal Flow** (`user/withdrawal.php`)
   - Test minimum amount validation (10 USDT)
   - Test USDT address format validation
   - Test insufficient balance handling

3. **Refill Flow** (`user/refill.php`)
   - Display admin USDT wallet address
   - Submit refill requests
   - Optional transaction hash field

### **Next Steps:**
- **Phase 4: Admin Panel** - Manage withdrawal/refill requests
- **Phase 5: Monthly Bonus System** - Automated bonus processing

Ready to proceed with **Phase 4: Admin Panel** when you are!