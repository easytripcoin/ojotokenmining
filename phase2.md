# 🚀 **Phase 2: User Dashboard Foundation & Package System**
> Building on the solid Phase 1 authentication layer, we now create the core user experience.

---

## 🏗️ **Phase 2 Files Overview**

| File                     | Purpose                                    | Status  |
| ------------------------ | ------------------------------------------ | ------- |
| `user/dashboard.php`     | Main dashboard with sidebar & stats        | **New** |
| `user/packages.php`      | Package selection & purchase               | **New** |
| `user/checkout.php`      | Confirm purchase & insufficient-funds flow | **New** |
| `assets/css/admin.css`   | Sidebar & dashboard styling                | **New** |
| `assets/js/dashboard.js` | Interactivity & AJAX helpers               | **New** |

---

## 💾 **Database Schema Updates (Phase 2)**
Add the following **columns** to existing tables if not already present:

```sql
-- Ensure packages table has all required columns
ALTER TABLE packages 
  ADD COLUMN description TEXT NULL AFTER price,
  ADD COLUMN features TEXT NULL AFTER description,
  ADD COLUMN order_index INT DEFAULT 0 AFTER features;

-- Add package images support
ALTER TABLE packages 
  ADD COLUMN image_path VARCHAR(255) NULL AFTER features;

-- Update existing packages with descriptions
UPDATE packages SET 
  description = 'Perfect for beginners to start earning',
  features = '• 20 USDT minimum\n• 50% monthly bonus\n• 3-month cycle\n• Referral bonuses',
  order_index = 1
WHERE name = 'Starter Plan';

-- Repeat for other packages...
```

---

## 📁 **File 1: `user/dashboard.php`**

```php
<?php
// user/dashboard.php
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
                <p class="text-muted">No active packages. <a href="packages.php" class="btn btn-primary btn-sm">Buy a Package</a></p>
              <?php else: ?>
                <div class="list-group">
                  <?php foreach ($active_packages as $pkg): ?>
                    <div class="list-group-item">
                      <div class="d-flex justify-content-between">
                        <div>
                          <h6><?= htmlspecialchars($pkg['name']) ?></h6>
                          <small class="text-muted">Purchased: <?= formatDate($pkg['created_at']) ?></small>
                        </div>
                        <span class="badge bg-success">Cycle <?= $pkg['current_cycle'] ?>/<?= $pkg['total_cycles'] ?></span>
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
                        <?= $tx['amount'] > 0 ? '+' : '' ?><?= formatCurrency($tx['amount']) ?>
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
```

---

## 📁 **File 2: `user/packages.php`**

```php
<?php
// user/packages.php
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
                  <li><i class="fas fa-check text-success me-2"></i> <?= BONUS_MONTHS ?> months 50% bonus</li>
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
```

---

## 📁 **File 3: `user/checkout.php`**

```php
<?php
// user/checkout.php
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$package_id = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);
$package = $package_id ? getPackageById($package_id) : null;

if (!$package) {
    redirectWithMessage('packages.php', 'Invalid package selected.', 'error');
}

$user_id = getCurrentUserId();
$balance = getEwalletBalance($user_id);

// Handle purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_purchase'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('packages.php', 'Invalid security token.', 'error');
    }

    $result = purchasePackage($user_id, $package['id']);
    
    if ($result['success']) {
        redirectWithMessage('dashboard.php', $result['message'], 'success');
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Checkout - <?= SITE_NAME ?></title>
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
      <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
      <li><a href="ewallet.php"><i class="fas fa-wallet"></i> E-Wallet</a></li>
      <li><a href="genealogy.php"><i class="fas fa-sitemap"></i> Genealogy</a></li>
      <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
      <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </nav>

  <main class="main-content">
    <div class="container-fluid">
      <h2 class="mb-4">Package Checkout</h2>

      <div class="row">
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header">
              <h5><i class="fas fa-box"></i> Package Details</h5>
            </div>
            <div class="card-body">
              <h4><?= htmlspecialchars($package['name']) ?></h4>
              <p class="text-muted">Price: <strong><?= formatCurrency($package['price']) ?></strong></p>
              <p class="text-muted">Your Balance: <strong><?= formatCurrency($balance) ?></strong></p>
              
              <?php if ($balance < $package['price']): ?>
                <div class="alert alert-warning">
                  <i class="fas fa-exclamation-triangle"></i>
                  Insufficient balance. <a href="ewallet.php" class="alert-link">Add funds</a>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card">
            <div class="card-header">
              <h5><i class="fas fa-shopping-cart"></i> Purchase Confirmation</h5>
            </div>
            <div class="card-body">
              <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
              <?php endif; ?>

              <?php if ($balance >= $package['price']): ?>
                <form method="POST" action="">
                  <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                  <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                  <input type="hidden" name="confirm_purchase" value="1">
                  
                  <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg">
                      <i class="fas fa-check"></i> Confirm Purchase
                    </button>
                    <a href="packages.php" class="btn btn-secondary">
                      <i class="fas fa-times"></i> Cancel
                    </a>
                  </div>
                </form>
              <?php else: ?>
                <div class="d-grid">
                  <a href="ewallet.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Funds to E-Wallet
                  </a>
                </div>
              <?php endif; ?>
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

## 📁 **File 4: `assets/css/admin.css`**

```css
/* assets/css/admin.css */
:root {
  --sidebar-width: 250px;
  --sidebar-bg: #2c3e50;
  --sidebar-hover: #34495e;
  --primary-color: #667eea;
  --secondary-color: #764ba2;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  background-color: #f8f9fa;
}

/* Sidebar Navigation */
.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  height: 100vh;
  width: var(--sidebar-width);
  background: var(--sidebar-bg);
  padding-top: 20px;
  z-index: 1000;
  overflow-y: auto;
}

.sidebar-header {
  color: white;
  text-align: center;
  padding: 0 20px 20px;
  border-bottom: 1px solid #3c4b64;
}

.sidebar-menu {
  list-style: none;
  padding: 0;
  margin: 0;
}

.sidebar-menu li {
  margin: 0;
}

.sidebar-menu a {
  display: block;
  color: #ecf0f1;
  padding: 15px 20px;
  text-decoration: none;
  transition: all 0.3s;
  border-left: 3px solid transparent;
}

.sidebar-menu a:hover,
.sidebar-menu li.active a {
  background: var(--sidebar-hover);
  border-left-color: var(--primary-color);
}

.sidebar-menu i {
  width: 20px;
  margin-right: 10px;
}

/* Main Content */
.main-content {
  margin-left: var(--sidebar-width);
  padding: 20px;
  min-height: 100vh;
}

/* Stats Cards */
.stats-card {
  background: white;
  border: none;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  transition: transform 0.2s;
}

.stats-card:hover {
  transform: translateY(-2px);
}

.stats-icon {
  font-size: 2rem;
  color: var(--primary-color);
  margin-bottom: 10px;
}

/* Package Cards */
.package-card {
  transition: transform 0.2s;
  border: none;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.package-card:hover {
  transform: translateY(-2px);
}

.price {
  font-size: 2rem;
  color: var(--primary-color);
  font-weight: bold;
}

/* Responsive */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    transition: transform 0.3s;
  }
  
  .sidebar.show {
    transform: translateX(0);
  }
  
  .main-content {
    margin-left: 0;
  }
}
```

---

## 📁 **File 5: `assets/js/dashboard.js`**

```javascript
// assets/js/dashboard.js
document.addEventListener('DOMContentLoaded', function() {
  // Auto-dismiss flash messages
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    setTimeout(() => {
      const bsAlert = new bootstrap.Alert(alert);
      bsAlert.close();
    }, 5000);
  });

  // Mobile menu toggle (if needed)
  const toggleBtn = document.querySelector('.sidebar-toggle');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function() {
      document.querySelector('.sidebar').classList.toggle('show');
    });
  }

  // Confirm purchase dialog
  const purchaseBtns = document.querySelectorAll('[data-confirm-purchase]');
  purchaseBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      if (!confirm('Are you sure you want to purchase this package?')) {
        e.preventDefault();
      }
    });
  });

  // Balance check before purchase
  const checkoutForm = document.querySelector('#checkout-form');
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', function(e) {
      const balance = parseFloat(this.dataset.balance);
      const price = parseFloat(this.dataset.price);
      
      if (balance < price) {
        e.preventDefault();
        alert('Insufficient funds. Please add funds to your e-wallet first.');
      }
    });
  }
});

// AJAX helper for dynamic content loading
function loadDashboardStats() {
  fetch('../api/user_stats.php')
    .then(response => response.json())
    .then(data => {
      // Update stats dynamically
      document.querySelector('#ewallet-balance').textContent = data.balance;
      document.querySelector('#referrals-count').textContent = data.referrals;
    })
    .catch(console.error);
}
```

---

## ✅ **Phase 2 Deliverables Checklist**

| Component              | Status       | Notes                                     |
| ---------------------- | ------------ | ----------------------------------------- |
| ✅ User Dashboard       | **Complete** | Sidebar nav, stats cards, active packages |
| ✅ Package Display      | **Complete** | 6-tier packages with pricing              |
| ✅ Purchase Flow        | **Complete** | Checkout with balance validation          |
| ✅ Responsive Design    | **Complete** | Mobile-friendly layout                    |
| ✅ Security             | **Complete** | CSRF tokens, validation, auth checks      |
| ✅ E-Wallet Integration | **Complete** | Balance checks & transaction recording    |

---

## 🧪 **Testing Phase 2**

### **Quick Test Plan:**

1. **Login** → Dashboard should show stats
2. **Packages Page** → View all 6 packages
3. **Purchase Flow**:
   - With insufficient funds → "Add funds" prompt
   - With sufficient funds → Successful purchase
   - Check referral bonuses are applied
4. **Responsive Test** → Resize browser to mobile size

### **Ready for Phase 3!**
Once you've tested Phase 2, we can proceed to:
- **E-Wallet Management** (withdrawal/refill requests)
- **Transaction history**
- **Admin approval system**

Would you like me to proceed with Phase 3, or would you prefer to test Phase 2 first?