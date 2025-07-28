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
            if (
                processEwalletTransaction(
                    $request['user_id'],
                    'deposit',
                    $request['amount'],
                    "Refill approved",
                    $request_id
                )
            ) {
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
                                            <small
                                                class="text-muted d-block"><?= htmlspecialchars($refill['email']) ?></small>
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
                                                <button class="btn btn-sm btn-success"
                                                    onclick="approveRefill(<?= $refill['id'] ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger"
                                                    onclick="rejectRefill(<?= $refill['id'] ?>)">
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