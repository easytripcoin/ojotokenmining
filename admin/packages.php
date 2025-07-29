<?php
// admin/packages.php - Complete with active/inactive tabs
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

// 1. Handle status parameter
$current_status = $_GET['status'] ?? 'active';
$valid_statuses = ['active', 'inactive'];
$current_status = in_array($current_status, $valid_statuses) ? $current_status : 'active';

// 2. Handle package updates
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
            $description = trim($_POST['description'] ?? '');
            $features = trim($_POST['features'] ?? '');
            
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
            $description = trim($_POST['description'] ?? '');
            $features = trim($_POST['features'] ?? '');
            
            $stmt = $pdo->prepare("
                INSERT INTO packages (name, price, status, description, features) 
                VALUES (?, ?, 'active', ?, ?)
            ");
            $stmt->execute([$name, $price, $description, $features]);
            
            redirectWithMessage('packages.php', 'Package added successfully.', 'success');
            
        } elseif ($action === 'toggle') {
            $package_id = intval($_POST['package_id']);
            $new_status = $_POST['new_status'];
            
            $stmt = $pdo->prepare("UPDATE packages SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $package_id]);
            
            redirectWithMessage('packages.php', 'Package status updated.', 'success');
        }
        
    } catch (Exception $e) {
        redirectWithMessage('packages.php', 'Error processing request.', 'error');
    }
}

// 3. Get packages with status filter
$where_clause = $current_status !== 'all' ? "WHERE status = ?" : "";
$params = $current_status !== 'all' ? [$current_status] : [];

$status_counts = ['active' => 0, 'inactive' => 0];
$error = '';

try {
    $pdo = getConnection();
    
    foreach (['active', 'inactive'] as $status) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM packages WHERE status = ?");
        $stmt->execute([$status]);
        $status_counts[$status] = $stmt->fetchColumn();
    }

    $packages = $pdo->prepare("
        SELECT * FROM packages 
        $where_clause
        ORDER BY price ASC
    ");
    $packages->execute($params);
    $packages = $packages->fetchAll();

} catch (Exception $e) {
    $error = "Failed to load packages: " . $e->getMessage();
    $packages = [];
}
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

      <!-- Navigation Tabs -->
      <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $current_status === 'active' ? 'active' : '' ?>" 
               href="packages.php?status=active">
                Active
                <?php if ($status_counts['active'] > 0): ?>
                    <span class="badge bg-success ms-1"><?= $status_counts['active'] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_status === 'inactive' ? 'active' : '' ?>" 
               href="packages.php?status=inactive">
                Inactive
                <?php if ($status_counts['inactive'] > 0): ?>
                    <span class="badge bg-secondary ms-1"><?= $status_counts['inactive'] ?></span>
                <?php endif; ?>
            </a>
        </li>
      </ul>

      <!-- Packages Table -->
      <div class="card">
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php elseif (empty($packages)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-box fa-3x text-muted mb-3"></i>
                    <p class="text-muted">
                        <?php if ($current_status !== 'all'): ?>
                            No <?= $current_status ?> packages found
                        <?php else: ?>
                            No packages found
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
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
                                    <span class="badge bg-<?= $package['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($package['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                data-bs-target="#editModal<?= $package['id'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="packages.php" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="new_status" value="<?= $package['status'] === 'active' ? 'inactive' : 'active' ?>">
                                            <button type="submit" class="btn btn-sm btn-warning">
                                                <i class="fas fa-toggle-<?= $package['status'] === 'active' ? 'on' : 'off' ?>"></i>
                                            </button>
                                        </form>
                                    </div>
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
            <?php endif; ?>
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
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>