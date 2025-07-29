<?php
// admin/users.php - Fixed with proper joins
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

// Initialize variables
$users = [];
$total_pages = 1;
$total_users = 0;
$error = '';
$search = trim($_GET['search'] ?? '');

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE clause properly
$where_conditions = ["u.role = 'user'"];
$params = [];

if ($search) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(" AND ", $where_conditions);

// Get users with proper joins
try {
    $pdo = getConnection();

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM users u WHERE $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_users = (int) $stmt->fetchColumn();

    // Get users with correct package data
    $users_sql = "
        SELECT u.*, 
               COALESCE(SUM(CASE WHEN up.status = 'active' THEN p.price ELSE 0 END), 0) as total_spent,
               COUNT(DISTINCT CASE WHEN up.status = 'active' THEN up.id END) as active_packages
        FROM users u
        LEFT JOIN user_packages up ON u.id = up.user_id AND up.status = 'active'
        LEFT JOIN packages p ON up.package_id = p.id
        WHERE $where_clause
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($users_sql);
    $all_params = array_merge($params, [$per_page, $offset]);
    $stmt->execute($all_params);
    $users = $stmt->fetchAll();

    $total_pages = max(1, ceil($total_users / $per_page));

} catch (Exception $e) {
    $error = "Failed to load users: " . $e->getMessage();
    $users = [];
    $total_users = 0;
    $total_pages = 1;
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
    <!-- Admin Sidebar -->
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

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Search Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="search"
                                placeholder="Search by username or email" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <?php if ($search): ?>
                                <a href="users.php" class="btn btn-secondary">Clear</a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3 text-end">
                            <span class="text-muted"><?= number_format($total_users) ?> users found</span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($users) && !$error): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">
                                <?php if ($search): ?>
                                    No users found matching "<?= htmlspecialchars($search) ?>"
                                <?php else: ?>
                                    No users found
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
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
                                                <span
                                                    class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'danger' ?>">
                                                    <?= ucfirst($user['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= formatCurrency($user['total_spent']) ?></td>
                                            <td><?= $user['active_packages'] ?></td>
                                            <td><?= timeAgo($user['created_at']) ?></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <!-- View Button - Exact styling from withdrawals -->
                                                    <a href="user_details.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info"
                                                        title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>

                                                    <!-- Toggle Button - Exact styling from withdrawals -->
                                                    <form method="POST" action="update_user_status.php" class="d-inline">
                                                        <input type="hidden" name="csrf_token"
                                                            value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <input type="hidden" name="status"
                                                            value="<?= $user['status'] === 'active' ? 'suspended' : 'active' ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning"
                                                            title="Toggle Status">
                                                            <i
                                                                class="fas fa-toggle-<?= $user['status'] === 'active' ? 'on' : 'off' ?>"></i>
                                                        </button>
                                                    </form>
                                                </div>
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
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link"
                                                href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>