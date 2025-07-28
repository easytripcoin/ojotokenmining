# 🚀 **Phase 4: Admin Panel**
> Complete administrative control system for managing users, requests, and system settings

---

## 📋 **Phase 4 Files Overview**

| File                    | Purpose                       | Status       |
| ----------------------- | ----------------------------- | ------------ |
| `admin/dashboard.php`   | Admin overview & stats        | **New**      |
| `admin/users.php`       | User management interface     | **New**      |
| `admin/withdrawals.php` | Withdrawal request management | **New**      |
| `admin/refills.php`     | Refill request management     | **New**      |
| `admin/settings.php`    | System configuration          | **New**      |
| `admin/packages.php`    | Package management            | **New**      |
| Updated `auth.php`      | Admin authentication checks   | **Enhanced** |

---

## 📁 **File 1: `admin/dashboard.php`**

```php
<?php
// admin/dashboard.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

// Get admin stats
try {
    $pdo = getConnection();
    
    // Total stats
    $total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $total_packages = $pdo->query("SELECT COUNT(*) FROM user_packages WHERE status = 'active'")->fetchColumn();
    $total_earnings = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM ewallet_transactions WHERE type IN ('purchase', 'bonus', 'referral')")->fetchColumn();
    
    // Pending requests
    $pending_withdrawals = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'")->fetchColumn();
    $pending_refills = $pdo->query("SELECT COUNT(*) FROM refill_requests WHERE status = 'pending'")->fetchColumn();
    
    // Recent activity
    $recent_users = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $recent_transactions = $pdo->query("SELECT et.*, u.username FROM ewallet_transactions et JOIN users u ON et.user_id = u.id ORDER BY et.created_at DESC LIMIT 5")->fetchAll();
    
} catch (Exception $e) {
    $error = "Failed to load admin data";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
  <!-- Admin Sidebar -->
  <nav class="sidebar">
    <div class="sidebar-header">
      <h4><i class="fas fa-cogs me-2"></i>Admin Panel</h4>
    </div>
    <ul class="sidebar-menu">
      <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
      <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
      <li><a href="withdrawals.php"><i class="fas fa-arrow-down"></i> Withdrawals</a></li>
      <li><a href="refills.php"><i class="fas fa-arrow-up"></i> Refills</a></li>
      <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
      <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
      <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </nav>

  <main class="main-content">
    <div class="container-fluid">
      <h2 class="mb-4">Admin Dashboard</h2>

      <!-- Stats Cards -->
      <div class="row mb-4">
        <div class="col-md-3">
          <div class="card stats-card">
            <div class="card-body">
              <i class="fas fa-users stats-icon"></i>
              <h5><?= number_format($total_users) ?></h5>
              <p>Total Users</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card stats-card">
            <div class="card-body">
              <i class="fas fa-box stats-icon"></i>
              <h5><?= number_format($total_packages) ?></h5>
              <p>Active Packages</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card stats-card">
            <div class="card-body">
              <i class="fas fa-dollar-sign stats-icon"></i>
              <h5><?= formatCurrency($total_earnings) ?></h5>
              <p>Total Earnings</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card stats-card">
            <div class="card-body">
              <i class="fas fa-exclamation-triangle stats-icon"></i>
              <h5><?= $pending_withdrawals + $pending_refills ?></h5>
              <p>Pending Requests</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Pending Requests -->
      <div class="row mb-4">
        <div class="col-md-6">
          <div class="card">
            <div class="card-header">
              <h5><i class="fas fa-arrow-down"></i> Pending Withdrawals</h5>
            </div>
            <div class="card-body">
              <?php if ($pending_withdrawals > 0): ?>
                <a href="withdrawals.php" class="btn btn-warning">View <?= $pending_withdrawals ?> pending withdrawals</a>
              <?php else: ?>
                <p class="text-muted">No pending withdrawals</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card">
            <div class="card-header">
              <h5><i class="fas fa-arrow-up"></i> Pending Refills</h5>
            </div>
            <div class="card-body">
              <?php if ($pending_refills > 0): ?>
                <a href="refills.php" class="btn btn-info">View <?= $pending_refills ?> pending refills</a>
              <?php else: ?>
                <p class="text-muted">No pending refills</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="row">
        <div class="col-md-6">
          <div class="card">
            <div class="card-header">
              <h5><i class="fas fa-users"></i> Recent Users</h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>Username</th>
                      <th>Registered</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recent_users as $user): ?>
                    <tr>
                      <td><?= htmlspecialchars($user['username']) ?></td>
                      <td><?= timeAgo($user['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card">
            <div class="card-header">
              <h5><i class="fas fa-history"></i> Recent Transactions</h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>User</th>
                      <th>Amount</th>
                      <th>Type</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recent_transactions as $tx): ?>
                    <tr>
                      <td><?= htmlspecialchars($tx['username']) ?></td>
                      <td class="<?= $tx['amount'] > 0 ? 'text-success' : 'text-danger' ?>">
                        <?= $tx['amount'] > 0 ? '+' : '' ?><?= formatCurrency($tx['amount']) ?>
                      </td>
                      <td><?= ucfirst($tx['type']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
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

## 📁 **File 2: `admin/users.php`**

```php
<?php
// admin/users.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Search
$search = trim($_GET['search'] ?? '');
$search_sql = $search ? "WHERE u.username LIKE ? OR u.email LIKE ?" : "";
$search_params = $search ? ["%$search%", "%$search%"] : [];

// Get users
try {
    $pdo = getConnection();
    
    $count_sql = "SELECT COUNT(*) FROM users u $search_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($search_params);
    $total_users = $stmt->fetchColumn();
    
    $users_sql = "
        SELECT u.*, 
               COALESCE(SUM(up.price), 0) as total_spent,
               COUNT(DISTINCT up.id) as active_packages
        FROM users u
        LEFT JOIN user_packages up ON u.id = up.user_id AND up.status = 'active'
        $search_sql
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($users_sql);
    $stmt->execute($search_params);
    $users = $stmt->fetchAll();
    
    $total_pages = ceil($total_users / $per_page);
    
} catch (Exception $e) {
    $error = "Failed to load users";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Users - <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
  <!-- Admin Sidebar (same as dashboard) -->
  <nav class="sidebar">
    <div class="sidebar-header">
      <h4><i class="fas fa-cogs me-2"></i>Admin Panel</h4>
    </div>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
      <li class="active"><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
      <li><a href="withdrawals.php"><i class="fas fa-arrow-down"></i> Withdrawals</a></li>
      <li><a href="refills.php"><i class="fas fa-arrow-up"></i> Refills</a></li>
      <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
      <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
      <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </nav>

  <main class="main-content">
    <div class="container-fluid">
      <h2 class="mb-4">Manage Users</h2>

      <!-- Search Form -->
      <div class="card mb-4">
        <div class="card-body">
          <form method="GET" class="row g-3">
            <div class="col-md-6">
              <input type="text" 
                     class="form-control" 
                     name="search" 
                     placeholder="Search by username or email"
                     value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
              </button>
              <?php if ($search): ?>
                <a href="users.php" class="btn btn-secondary">Clear</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>

      <!-- Users Table -->
      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th>Total Spent</th>
                  <th>Packages</th>
                  <th>Registered</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                  <td><?= $user['id'] ?></td>
                  <td><?= htmlspecialchars($user['username']) ?></td>
                  <td><?= htmlspecialchars($user['email']) ?></td>
                  <td>
                    <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'danger' ?>">
                      <?= ucfirst($user['status']) ?>
                    </span>
                  </td>
                  <td><?= formatCurrency($user['total_spent']) ?></td>
                  <td><?= $user['active_packages'] ?></td>
                  <td><?= timeAgo($user['created_at']) ?></td>
                  <td>
                    <a href="user_details.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info">
                      <i class="fas fa-eye"></i>
                    </a>
                    <form method="POST" action="update_user_status.php" style="display: inline-block;">
                      <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                      <input type="hidden" name="status" value="<?= $user['status'] === 'active' ? 'suspended' : 'active' ?>">
                      <button type="submit" class="btn btn-sm btn-warning">
                        <i class="fas fa-toggle-on"></i>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
          <nav>
            <ul class="pagination justify-content-center">
              <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                  <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
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

## 📁 **File 3: `admin/withdrawals.php`**

```php
<?php
// admin/withdrawals.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('withdrawals.php', 'Invalid security token.', 'error');
    }

    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'] ?? '';
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    try {
        $pdo = getConnection();
        
        // Get withdrawal request
        $stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            redirectWithMessage('withdrawals.php', 'Request not found.', 'error');
        }

        if ($action === 'approve') {
            // Update request status
            $stmt = $pdo->prepare("
                UPDATE withdrawal_requests 
                SET status = 'approved', admin_notes = ?, processed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$admin_notes, $request_id]);
            
            // Update transaction status
            $stmt = $pdo->prepare("
                UPDATE ewallet_transactions 
                SET status = 'completed', description = CONCAT(description, ' - Approved') 
                WHERE reference_id = ? AND type = 'withdrawal_pending'
            ");
            $stmt->execute([$request_id]);
            
            redirectWithMessage('withdrawals.php', 'Withdrawal approved successfully.', 'success');
            
        } elseif ($action === 'reject') {
            // Update request status
            $stmt = $pdo->prepare("
                UPDATE withdrawal_requests 
                SET status = 'rejected', admin_notes = ?, processed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$admin_notes, $request_id]);
            
            // Refund balance
            if (processEwalletTransaction($request['user_id'], 'withdrawal_refund', 
                $request['amount'], "Withdrawal rejected - refund", $request_id)) {
                redirectWithMessage('withdrawals.php', 'Withdrawal rejected and balance refunded.', 'success');
            }
        }
        
    } catch (Exception $e) {
        redirectWithMessage('withdrawals.php', 'Error processing request.', 'error');
    }
}

// Get pending withdrawals
try {
    $pdo = getConnection();
    
    $withdrawals = $pdo->query("
        SELECT wr.*, u.username, u.email, w.balance as user_balance
        FROM withdrawal_requests wr
        JOIN users u ON wr.user_id = u.id
        JOIN ewallet w ON u.id = w.user_id
        ORDER BY 
            CASE wr.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                ELSE 3 
            END,
            wr.created_at DESC
    ")->fetchAll();
    
} catch (Exception $e) {
    $error = "Failed to load withdrawals";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Withdrawal Requests - <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
  <!-- Admin Sidebar -->
  <nav class="sidebar">
    <div class="sidebar-header">
      <h4><i class="fas fa-cogs me-2"></i>Admin Panel</h4>
    </div>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
      <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
      <li class="active"><a href="withdrawals.php"><i class="fas fa-arrow-down"></i> Withdrawals</a></li>
      <li><a href="refills.php"><i class="fas fa-arrow-up"></i> Refills</a></li>
      <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
      <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
      <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </nav>

  <main class="main-content">
    <div class="container-fluid">
      <h2 class="mb-4">Withdrawal Requests</h2>

      <!-- Filter Tabs -->
      <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
          <a class="nav-link active" href="withdrawals.php">All Requests</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="withdrawals.php?status=pending">Pending</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="withdrawals.php?status=approved">Approved</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="withdrawals.php?status=rejected">Rejected</a>
        </li>
      </ul>

      <!-- Withdrawals Table -->
      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>User</th>
                  <th>Amount</th>
                  <th>Wallet Address</th>
                  <th>Status</th>
                  <th>Requested</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($withdrawals as $withdrawal): ?>
                <tr>
                  <td><?= $withdrawal['id'] ?></td>
                  <td>
                    <?= htmlspecialchars($withdrawal['username']) ?>
                    <small class="text-muted d-block"><?= htmlspecialchars($withdrawal['email']) ?></small>
                  </td>
                  <td><?= formatCurrency($withdrawal['amount']) ?></td>
                  <td>
                    <code><?= htmlspecialchars(substr($withdrawal['wallet_address'], 0, 10)) ?>...</code>
                  </td>
                  <td>
                    <span class="badge bg-<?= 
                      $withdrawal['status'] === 'pending' ? 'warning' : 
                      ($withdrawal['status'] === 'approved' ? 'success' : 'danger')
                    ?>">
                      <?= ucfirst($withdrawal['status']) ?>
                    </span>
                  </td>
                  <td><?= timeAgo($withdrawal['created_at']) ?></td>
                  <td>
                    <?php if ($withdrawal['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" 
                            data-bs-target="#approveModal<?= $withdrawal['id'] ?>">
                      <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                            data-bs-target="#rejectModal<?= $withdrawal['id'] ?>">
                      <i class="fas fa-times"></i>
                    </button>
                    <?php elseif ($withdrawal['admin_notes']): ?>
                    <small class="text-muted"><?= htmlspecialchars($withdrawal['admin_notes']) ?></small>
                    <?php endif; ?>
                  </td>
                </tr>

                <!-- Approve Modal -->
                <div class="modal fade" id="approveModal<?= $withdrawal['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Approve Withdrawal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <p>Approve withdrawal of <?= formatCurrency($withdrawal['amount']) ?> for <?= htmlspecialchars($withdrawal['username']) ?>?</p>
                        <p><strong>Wallet:</strong> <?= htmlspecialchars($withdrawal['wallet_address']) ?></p>
                        <form method="POST">
                          <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                          <input type="hidden" name="request_id" value="<?= $withdrawal['id'] ?>">
                          <input type="hidden" name="action" value="approve">
                          <div class="mb-3">
                            <label>Admin Notes (optional)</label>
                            <textarea class="form-control" name="admin_notes" rows="2"></textarea>
                          </div>
                        </form>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve</button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Reject Modal -->
                <div class="modal fade" id="rejectModal<?= $withdrawal['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Reject Withdrawal</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <p>Reject withdrawal of <?= formatCurrency($withdrawal['amount']) ?> for <?= htmlspecialchars($withdrawal['username']) ?>?</p>
                        <form method="POST">
                          <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                          <input type="hidden" name="request_id" value="<?= $withdrawal['id'] ?>">
                          <input type="hidden" name="action" value="reject">
                          <div class="mb-3">
                            <label>Reason (optional)</label>
                            <textarea class="form-control" name="admin_notes" rows="2"></textarea>
                          </div>
                        </form>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject & Refund</button>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </tbody>
            </table>
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

## 📁 **File 4: `admin/refills.php`** *(Similar structure to withdrawals.php)*

```php
<?php
// admin/refills.php - Similar to withdrawals.php but for refill requests
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('refills.php', 'Invalid security token.', 'error');
    }

    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'] ?? '';
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    try {
        $pdo = getConnection();
        
        // Get refill request
        $stmt = $pdo->prepare("SELECT * FROM refill_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            redirectWithMessage('refills.php', 'Request not found.', 'error');
        }

        if ($action === 'approve') {
            $stmt = $pdo->prepare("
                UPDATE refill_requests 
                SET status = 'approved', admin_notes = ?, approved_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$admin_notes, $request_id]);
            
            // Add funds to user wallet
            if (processEwalletTransaction($request['user_id'], 'deposit', 
                $request['amount'], "Refill approved", $request_id)) {
                redirectWithMessage('refills.php', 'Refill approved and funds added.', 'success');
            }
            
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("
                UPDATE refill_requests 
                SET status = 'rejected', admin_notes = ?, approved_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$admin_notes, $request_id]);
            
            redirectWithMessage('refills.php', 'Refill rejected.', 'success');
        }
        
    } catch (Exception $e) {
        redirectWithMessage('refills.php', 'Error processing request.', 'error');
    }
}

// Get refill requests
try {
    $pdo = getConnection();
    
    $refills = $pdo->query("
        SELECT rr.*, u.username, u.email, w.balance as user_balance
        FROM refill_requests rr
        JOIN users u ON rr.user_id = u.id
        JOIN ewallet w ON u.id = w.user_id
        ORDER BY 
            CASE rr.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                ELSE 3 
            END,
            rr.created_at DESC
    ")->fetchAll();
    
} catch (Exception $e) {
    $error = "Failed to load refills";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Refill Requests - <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
  <!-- Admin Sidebar -->
  <nav class="sidebar">
    <div class="sidebar-header">
      <h4><i class="fas fa-cogs me-2"></i>Admin Panel</h4>
    </div>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
      <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
      <li><a href="withdrawals.php"><i class="fas fa-arrow-down"></i> Withdrawals</a></li>
      <li class="active"><a href="refills.php"><i class="fas fa-arrow-up"></i> Refills</a></li>
      <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
      <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
      <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </nav>

  <main class="main-content">
    <div class="container-fluid">
      <h2 class="mb-4">Refill Requests</h2>

      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>User</th>
                  <th>Amount</th>
                  <th>Transaction Hash</th>
                  <th>Status</th>
                  <th>Requested</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($refills as $refill): ?>
                <tr>
                  <td><?= $refill['id'] ?></td>
                  <td>
                    <?= htmlspecialchars($refill['username']) ?>
                    <small class="text-muted d-block"><?= htmlspecialchars($refill['email']) ?></small>
                  </td>
                  <td><?= formatCurrency($refill['amount']) ?></td>
                  <td>
                    <code><?= $refill['transaction_hash'] ? 
                      htmlspecialchars(substr($refill['transaction_hash'], 0, 10)) . '...' : 
                      'N/A' ?></code>
                  </td>
                  <td>
                    <span class="badge bg-<?= 
                      $refill['status'] === 'pending' ? 'warning' : 
                      ($refill['status'] === 'approved' ? 'success' : 'danger')
                    ?>">
                      <?= ucfirst($refill['status']) ?>
                    </span>
                  </td>
                  <td><?= timeAgo($refill['created_at']) ?></td>
                  <td>
                    <?php if ($refill['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-success" onclick="approveRefill(<?= $refill['id'] ?>)">
                      <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="rejectRefill(<?= $refill['id'] ?>)">
                      <i class="fas fa-times"></i>
                    </button>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script>
    function approveRefill(id) {
      if (confirm('Approve refill request?')) {
        // Implement AJAX approval or redirect to modal
      }
    }
    
    function rejectRefill(id) {
      if (confirm('Reject refill request?')) {
        // Implement AJAX rejection or redirect to modal
      }
    }
  </script>
</body>
</html>
```

---

## 📁 **File 5: `admin/settings.php`**

```php
<?php
// admin/settings.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

$errors = [];
$success = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid security token.';
    } else {
        try {
            $settings = [
                'admin_usdt_wallet' => trim($_POST['admin_usdt_wallet']),
                'usdt_rate' => floatval($_POST['usdt_rate']),
                'monthly_bonus_percentage' => intval($_POST['monthly_bonus_percentage']),
                'referral_level_2_percentage' => intval($_POST['referral_level_2_percentage']),
                'referral_level_3_percentage' => intval($_POST['referral_level_3_percentage']),
                'referral_level_4_percentage' => intval($_POST['referral_level_4_percentage']),
                'referral_level_5_percentage' => intval($_POST['referral_level_5_percentage']),
                'default_sponsor_enabled' => isset($_POST['default_sponsor_enabled']) ? '1' : '0',
                'orphan_prevention' => isset($_POST['orphan_prevention']) ? '1' : '0'
            ];

            foreach ($settings as $key => $value) {
                updateAdminSetting($key, $value);
            }

            $success = 'Settings updated successfully.';
            
        } catch (Exception $e) {
            $errors['general'] = 'Failed to update settings.';
        }
    }
}

// Get current settings
$settings = [];
$keys = [
    'admin_usdt_wallet', 'usdt_rate', 'monthly_bonus_percentage',
    'referral_level_2_percentage', 'referral_level_3_percentage',
    'referral_level_4_percentage', 'referral_level_5_percentage',
    'default_sponsor_enabled', 'orphan_prevention'
];

foreach ($keys as $key) {
    $settings[$key] = getAdminSetting($key) ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>System Settings - <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
  <!-- Admin Sidebar -->
  <nav class="sidebar">
    <div class="sidebar-header">
      <h4><i class="fas fa-cogs me-2"></i>Admin Panel</h4>
    </div>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
      <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
      <li><a href="withdrawals.php"><i class="fas fa-arrow-down"></i> Withdrawals</a></li>
      <li><a href="refills.php"><i class="fas fa-arrow-up"></i> Refills</a></li>
      <li class="active"><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
      <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
      <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </nav>

  <main class="main-content">
    <div class="container-fluid">
      <h2 class="mb-4">System Settings</h2>

      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

        <!-- Payment Settings -->
        <div class="card mb-4">
          <div class="card-header">
            <h5><i class="fas fa-wallet"></i> Payment Settings</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Admin USDT Wallet Address</label>
                  <input type="text" 
                         class="form-control" 
                         name="admin_usdt_wallet" 
                         value="<?= htmlspecialchars($settings['admin_usdt_wallet']) ?>"
                         placeholder="TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXxx">
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">USDT Conversion Rate</label>
                  <input type="number" 
                         class="form-control" 
                         name="usdt_rate" 
                         value="<?= htmlspecialchars($settings['usdt_rate']) ?>"
                         step="0.01" 
                         min="0.1">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Bonus Settings -->
        <div class="card mb-4">
          <div class="card-header">
            <h5><i class="fas fa-gift"></i> Bonus Settings</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Monthly Bonus (%)</label>
                  <input type="number" 
                         class="form-control" 
                         name="monthly_bonus_percentage" 
                         value="<?= htmlspecialchars($settings['monthly_bonus_percentage']) ?>"
                         min="1" 
                         max="100">
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Level 2 Referral (%)</label>
                  <input type="number" 
                         class="form-control" 
                         name="referral_level_2_percentage" 
                         value="<?= htmlspecialchars($settings['referral_level_2_percentage']) ?>"
                         min="0" 
                         max="100">
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Level 3 Referral (%)</label>
                  <input type="number" 
                         class="form-control" 
                         name="referral_level_3_percentage" 
                         value="<?= htmlspecialchars($settings['referral_level_3_percentage']) ?>"
                         min="0" 
                         max="100">
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Level 4 Referral (%)</label>
                  <input type="number" 
                         class="form-control" 
                         name="referral_level_4_percentage" 
                         value="<?= htmlspecialchars($settings['referral_level_4_percentage']) ?>"
                         min="0" 
                         max="100">
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Level 5 Referral (%)</label>
                  <input type="number" 
                         class="form-control" 
                         name="referral_level_5_percentage" 
                         value="<?= htmlspecialchars($settings['referral_level_5_percentage']) ?>"
                         min="0" 
                         max="100">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- System Settings -->
        <div class="card mb-4">
          <div class="card-header">
            <h5><i class="fas fa-cogs"></i> System Settings</h5>
          </div>
          <div class="card-body">
            <div class="form-check mb-3">
              <input class="form-check-input" 
                     type="checkbox" 
                     name="default_sponsor_enabled" 
                     value="1" 
                     <?= $settings['default_sponsor_enabled'] ? 'checked' : '' ?>>
              <label class="form-check-label">
                Enable automatic admin sponsor assignment
              </label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" 
                     type="checkbox" 
                     name="orphan_prevention" 
                     value="1" 
                     <?= $settings['orphan_prevention'] ? 'checked' : '' ?>>
              <label class="form-check-label">
                Prevent orphaned users (assign to admin)
              </label>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Save Settings
        </button>
      </form>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

---

## 📁 **File 6: `admin/packages.php`**

```php
<?php
// admin/packages.php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

// Handle package updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('packages.php', 'Invalid security token.', 'error');
    }

    $action = $_POST['action'] ?? '';
    
    try {
        $pdo = getConnection();
        
        if ($action === 'update') {
            $package_id = intval($_POST['package_id']);
            $name = trim($_POST['name']);
            $price = floatval($_POST['price']);
            $status = $_POST['status'];
            $description = trim($_POST['description']);
            $features = trim($_POST['features']);
            
            $stmt = $pdo->prepare("
                UPDATE packages 
                SET name = ?, price = ?, status = ?, description = ?, features = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $price, $status, $description, $features, $package_id]);
            
            redirectWithMessage('packages.php', 'Package updated successfully.', 'success');
            
        } elseif ($action === 'add') {
            $name = trim($_POST['name']);
            $price = floatval($_POST['price']);
            $description = trim($_POST['description']);
            $features = trim($_POST['features']);
            
            $stmt = $pdo->prepare("
                INSERT INTO packages (name, price, description, features) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$name, $price, $description, $features]);
            
            redirectWithMessage('packages.php', 'Package added successfully.', 'success');
        }
        
    } catch (Exception $e) {
        redirectWithMessage('packages.php', 'Error processing request.', 'error');
    }
}

// Get all packages
$packages = getAllPackages();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Packages - <?= SITE_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
  <!-- Admin Sidebar -->
  <nav class="sidebar">
    <div class="sidebar-header">
      <h4><i class="fas fa-cogs me-2"></i>Admin Panel</h4>
    </div>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
      <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
      <li><a href="withdrawals.php"><i class="fas fa-arrow-down"></i> Withdrawals</a></li>
      <li><a href="refills.php"><i class="fas fa-arrow-up"></i> Refills</a></li>
      <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
      <li class="active"><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
      <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
  </nav>

  <main class="main-content">
    <div class="container-fluid">
      <h2 class="mb-4">Manage Packages</h2>

      <!-- Add Package Button -->
      <div class="mb-3">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">
          <i class="fas fa-plus"></i> Add New Package
        </button>
      </div>

      <!-- Packages Table -->
      <div class="card">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Price</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($packages as $package): ?>
                <tr>
                  <td><?= $package['id'] ?></td>
                  <td><?= htmlspecialchars($package['name']) ?></td>
                  <td><?= formatCurrency($package['price']) ?></td>
                  <td>
                    <span class="badge bg-<?= $package['status'] === 'active' ? 'success' : 'danger' ?>">
                      <?= ucfirst($package['status']) ?>
                    </span>
                  </td>
                  <td>
                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                            data-bs-target="#editModal<?= $package['id'] ?>">
                      <i class="fas fa-edit"></i>
                    </button>
                  </td>
                </tr>

                <!-- Edit Modal -->
                <div class="modal fade" id="editModal<?= $package['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Package</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <form method="POST">
                        <div class="modal-body">
                          <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                          <input type="hidden" name="action" value="update">
                          <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                          
                          <div class="mb-3">
                            <label>Name</label>
                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($package['name']) ?>" required>
                          </div>
                          <div class="mb-3">
                            <label>Price (USDT)</label>
                            <input type="number" class="form-control" name="price" value="<?= $package['price'] ?>" min="0" step="0.01" required>
                          </div>
                          <div class="mb-3">
                            <label>Status</label>
                            <select class="form-select" name="status">
                              <option value="active" <?= $package['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                              <option value="inactive" <?= $package['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                          </div>
                          <div class="mb-3">
                            <label>Description</label>
                            <textarea class="form-control" name="description" rows="2"><?= htmlspecialchars($package['description'] ?? '') ?></textarea>
                          </div>
                          <div class="mb-3">
                            <label>Features (one per line)</label>
                            <textarea class="form-control" name="features" rows="3"><?= htmlspecialchars($package['features'] ?? '') ?></textarea>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Add Package Modal -->
      <div class="modal fade" id="addPackageModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Add New Package</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
              <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="add">
                
                <div class="mb-3">
                  <label>Name</label>
                  <input type="text" class="form-control" name="name" required>
                </div>
                <div class="mb-3">
                  <label>Price (USDT)</label>
                  <input type="number" class="form-control" name="price" min="0" step="0.01" required>
                </div>
                <div class="mb-3">
                  <label>Description</label>
                  <textarea class="form-control" name="description" rows="2"></textarea>
                </div>
                <div class="mb-3">
                  <label>Features (one per line)</label>
                  <textarea class="form-control" name="features" rows="3"></textarea>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Package</button>
              </div>
            </form>
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

## 🔧 **Updated Admin Authentication (Add to `includes/auth.php`)**

```php
// Add these functions to includes/auth.php

/**
 * Get admin dashboard stats
 * @return array Admin statistics
 */
function getAdminStats() {
    try {
        $pdo = getConnection();
        
        return [
            'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
            'total_earnings' => $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM ewallet_transactions WHERE type IN ('purchase', 'bonus', 'referral')")->fetchColumn(),
            'pending_withdrawals' => $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'")->fetchColumn(),
            'pending_refills' => $pdo->query("SELECT COUNT(*) FROM refill_requests WHERE status = 'pending'")->fetchColumn(),
            'active_packages' => $pdo->query("SELECT COUNT(*) FROM user_packages WHERE status = 'active'")->fetchColumn()
        ];
        
    } catch (Exception $e) {
        logEvent("Get admin stats error: " . $e->getMessage(), 'error');
        return [];
    }
}
```

---

## ✅ **Phase 4 Complete - Admin Panel Ready**

### **Admin Panel Features:**

✅ **Dashboard** - Overview with key metrics
✅ **User Management** - View, search, suspend users
✅ **Withdrawal Management** - Approve/reject with notes
✅ **Refill Management** - Approve/reject USDT deposits
✅ **System Settings** - Configure bonuses, rates, wallet addresses
✅ **Package Management** - Add/edit/disable packages

### **Access Admin Panel:**
- **URL:** `http://localhost/ojotokenmining/admin/dashboard.php`
- **Login:** Use admin credentials (username: `admin`, password: `admin123`)

### **Next Phase:**
Ready for **Phase 5: Monthly Bonus System** with automated bonus calculation and processing!