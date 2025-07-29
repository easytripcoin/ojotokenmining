<?php
// admin/withdrawals.php - Complete tabbed withdrawals management
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin('../login.php');

// 1. Handle status parameter
$current_status = $_GET['status'] ?? 'all';
$valid_statuses = ['all', 'pending', 'approved', 'rejected', 'completed'];
$current_status = in_array($current_status, $valid_statuses) ? $current_status : 'all';

// 2. Build query based on status
$where_conditions = [];
$params = [];

if ($current_status !== 'all') {
    $where_conditions[] = "wr.status = ?";
    $params[] = $current_status;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// 3. Get counts for badges
$status_counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'completed' => 0];
$error = '';

try {
    $pdo = getConnection();

    foreach ($statuses = ['pending', 'approved', 'rejected', 'completed'] as $status) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawal_requests WHERE status = ?");
        $stmt->execute([$status]);
        $status_counts[$status] = $stmt->fetchColumn();
    }

    // 4. Handle approval/rejection
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            redirectWithMessage('withdrawals.php', 'Invalid security token.', 'error');
        }

        $request_id = intval($_POST['request_id']);
        $action = $_POST['action'] ?? '';
        $admin_notes = trim($_POST['admin_notes'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();

        if (!$request) {
            redirectWithMessage('withdrawals.php', 'Request not found.', 'error');
        }

        if ($action === 'approve') {
            // Update withdrawal request status
            $stmt = $pdo->prepare("
                UPDATE withdrawal_requests 
                SET status = 'completed', admin_notes = ?, processed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$admin_notes, $request_id]);

            // Find and complete the pending withdrawal transaction
            $stmt = $pdo->prepare("
                UPDATE ewallet_transactions 
                SET status = 'completed', description = CONCAT(description, ' - Completed') 
                WHERE reference_id = ? AND type = 'withdrawal' AND status = 'pending'
            ");
            $stmt->execute([$request_id]);

            redirectWithMessage('withdrawals.php', 'Withdrawal approved and marked as completed.', 'success');

        } elseif ($action === 'reject') {
            // Update withdrawal request status
            $stmt = $pdo->prepare("
                UPDATE withdrawal_requests 
                SET status = 'rejected', admin_notes = ?, processed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$admin_notes, $request_id]);

            // Find and reverse the pending withdrawal
            $stmt = $pdo->query("SELECT user_id, amount FROM withdrawal_requests WHERE id = $request_id");
            $withdrawal = $stmt->fetch();

            if ($withdrawal) {
                // Refund the amount
                processEwalletTransaction(
                    $withdrawal['user_id'],
                    'refund',
                    $withdrawal['amount'],
                    'Withdrawal rejected - refund',
                    $request_id
                );

                // Cancel the original pending transaction
                $stmt = $pdo->prepare("
                    UPDATE ewallet_transactions 
                    SET status = 'failed', description = CONCAT(description, ' - Rejected') 
                    WHERE reference_id = ? AND type = 'withdrawal' AND status = 'pending'
                ");
                $stmt->execute([$request_id]);
            }

            redirectWithMessage('withdrawals.php', 'Withdrawal rejected and balance refunded.', 'success');
        }
    }

    // 5. Get filtered withdrawals
    $withdrawals = $pdo->prepare("
        SELECT wr.*, u.username, u.email, w.balance as user_balance
        FROM withdrawal_requests wr
        JOIN users u ON wr.user_id = u.id
        JOIN ewallet w ON u.id = w.user_id
        $where_clause
        ORDER BY 
            CASE wr.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                WHEN 'rejected' THEN 3 
                ELSE 4 
            END,
            wr.created_at DESC
    ");
    $withdrawals->execute($params);
    $withdrawals = $withdrawals->fetchAll();

} catch (Exception $e) {
    $error = "Failed to load withdrawals: " . $e->getMessage();
    $withdrawals = [];
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

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?= $current_status === 'all' ? 'active' : '' ?>" href="withdrawals.php">All
                        Requests</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_status === 'pending' ? 'active' : '' ?>"
                        href="withdrawals.php?status=pending">
                        Pending
                        <?php if ($status_counts['pending'] > 0): ?>
                            <span class="badge bg-warning text-dark ms-1"><?= $status_counts['pending'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_status === 'approved' ? 'active' : '' ?>"
                        href="withdrawals.php?status=approved">
                        Approved
                        <?php if ($status_counts['approved'] > 0): ?>
                            <span class="badge bg-success ms-1"><?= $status_counts['approved'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_status === 'rejected' ? 'active' : '' ?>"
                        href="withdrawals.php?status=rejected">
                        Rejected
                        <?php if ($status_counts['rejected'] > 0): ?>
                            <span class="badge bg-danger ms-1"><?= $status_counts['rejected'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_status === 'completed' ? 'active' : '' ?>"
                        href="withdrawals.php?status=completed">
                        Completed
                        <?php if ($status_counts['completed'] > 0): ?>
                            <span class="badge bg-secondary ms-1"><?= $status_counts['completed'] ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>

            <!-- Withdrawals Table -->
            <div class="card">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php elseif (empty($withdrawals)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-arrow-down fa-3x text-muted mb-3"></i>
                            <p class="text-muted">
                                <?php if ($current_status !== 'all'): ?>
                                    No <?= $current_status ?> withdrawals found
                                <?php else: ?>
                                    No withdrawal requests found
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
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
                                                <small
                                                    class="text-muted d-block"><?= htmlspecialchars($withdrawal['email']) ?></small>
                                            </td>
                                            <td><?= formatCurrency($withdrawal['amount']) ?></td>
                                            <td>
                                                <code><?= htmlspecialchars(substr($withdrawal['wallet_address'], 0, 10)) ?>...</code>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?=
                                                    $withdrawal['status'] === 'pending' ? 'warning' :
                                                    ($withdrawal['status'] === 'approved' ? 'info' :
                                                        ($withdrawal['status'] === 'rejected' ? 'danger' : 'success'))
                                                    ?>">
                                                    <?= ucfirst($withdrawal['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= timeAgo($withdrawal['created_at']) ?></td>
                                            <td>
                                                <?php if ($withdrawal['status'] === 'pending'): ?>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal"
                                                            data-bs-target="#approveModal<?= $withdrawal['id'] ?>">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                                            data-bs-target="#rejectModal<?= $withdrawal['id'] ?>">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                <?php elseif ($withdrawal['admin_notes']): ?>
                                                    <small
                                                        class="text-muted"><?= htmlspecialchars($withdrawal['admin_notes']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>

                                        <!-- Approve Modal -->
                                        <div class="modal fade" id="approveModal<?= $withdrawal['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Approve Withdrawal</h5>
                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <p>Approve withdrawal of
                                                                <?= formatCurrency($withdrawal['amount']) ?> for
                                                                <?= htmlspecialchars($withdrawal['username']) ?>?</p>
                                                            <p><strong>Wallet:</strong>
                                                                <?= htmlspecialchars($withdrawal['wallet_address']) ?></p>
                                                            <input type="hidden" name="csrf_token"
                                                                value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="request_id"
                                                                value="<?= $withdrawal['id'] ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <div class="mb-3">
                                                                <label>Admin Notes (optional)</label>
                                                                <textarea class="form-control" name="admin_notes"
                                                                    rows="2"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-success">Approve</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Reject Modal -->
                                        <div class="modal fade" id="rejectModal<?= $withdrawal['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Reject Withdrawal</h5>
                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <p>Reject withdrawal of <?= formatCurrency($withdrawal['amount']) ?>
                                                                for <?= htmlspecialchars($withdrawal['username']) ?>?</p>
                                                            <input type="hidden" name="csrf_token"
                                                                value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="request_id"
                                                                value="<?= $withdrawal['id'] ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                            <div class="mb-3">
                                                                <label>Reason (optional)</label>
                                                                <textarea class="form-control" name="admin_notes"
                                                                    rows="2"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-danger">Reject &
                                                                Refund</button>
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
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>