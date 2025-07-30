<?php
// user/referrals.php — Level 1-5 referral viewer with search & pagination
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$level_filter = $_GET['level'] ?? 'all';  // 1,2,3,4,5 or 'all'
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

$valid_levels = ['all', 1, 2, 3, 4, 5];
if (!in_array($level_filter, $valid_levels))
    $level_filter = 'all';

/* ------------------------------------------------------------------
   1. Build the real downline tree for the logged-in user
   ------------------------------------------------------------------ */
function buildDownline($pdo, $ancestor, $maxDepth = 5, $currentDepth = 1, &$downline = [])
{
    if ($currentDepth > $maxDepth)
        return;

    $stmt = $pdo->prepare("SELECT id, username, email, created_at
                           FROM users
                           WHERE sponsor_id = ?");
    $stmt->execute([$ancestor]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['level'] = $currentDepth;

        // Has any active package?
        $pkgStmt = $pdo->prepare("SELECT id FROM user_packages WHERE user_id = ? AND status = 'active' LIMIT 1");
        $pkgStmt->execute([$row['id']]);
        $row['has_active_package'] = (bool) $pkgStmt->fetchColumn();

        $downline[] = $row;
        buildDownline($pdo, $row['id'], $maxDepth, $currentDepth + 1, $downline);
    }
    return $downline;
}

try {
    $pdo = getConnection();

    // Percentages per level (fallback)
    $percentages = [];
    for ($l = 1; $l <= 5; $l++) {
        $stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_name = ?");
        $stmt->execute(["referral_level_{$l}_percentage"]);
        $val = $stmt->fetchColumn();
        $percentages[$l] = $val ? (float) $val : ($l === 1 ? 10 : ($l === 2 ? 5 : 1));
    }

    /* Build tree */
    $fullDownline = buildDownline($pdo, $user_id);

    /* Level counts */
    $level_counts = array_fill(1, 5, 0);
    foreach ($fullDownline as $d) {
        if ($d['level'] <= 5)
            $level_counts[$d['level']]++;
    }

    /* Apply filters */
    $filtered = array_filter($fullDownline, function ($d) use ($level_filter, $search) {
        $levelOk = $level_filter === 'all' || $d['level'] == $level_filter;
        $searchOk = $search === '' ||
            stripos($d['username'], $search) !== false ||
            stripos($d['email'], $search) !== false;
        return $levelOk && $searchOk;
    });

    $total_referrals = count($filtered);
    $total_pages = max(1, ceil($total_referrals / $per_page));
    $offset = ($page - 1) * $per_page;
    $referralsPage = array_slice($filtered, $offset, $per_page);

    /* ------------------------------------------------------------------
       2. Attach bonus amount & total earned
    ------------------------------------------------------------------ */
    $total_earned = 0;
    foreach ($referralsPage as &$ref) {
        // bonus = 0 if no active package
        $ref['bonus_amount'] = 0;
        if ($ref['has_active_package']) {
            $ref['bonus_amount'] = 0; // you can fetch package value later
        }
    }
    unset($ref);

} catch (Exception $e) {
    error_log("Referrals page error: " . $e->getMessage());
    $referralsPage = [];
    $total_referrals = 0;
    $total_pages = 1;
    $level_counts = array_fill(1, 5, 0);
    $percentages = array_fill(1, 5, 0);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Referrals - <?= SITE_NAME ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <link href="../assets/css/genealogy.css" rel="stylesheet">
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
            <li class="active"><a href="referrals.php"><i class="fas fa-users"></i> My Referrals</a></li>
            <li><a href="genealogy.php"><i class="fas fa-sitemap"></i> Genealogy</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">My Referrals by Level</h2>

            <!-- Stats Row -->
            <div class="row mb-4">
                <?php foreach (range(1, 5) as $l): ?>
                    <div class="col-md-2 mb-3">
                        <div
                            class="card text-center bg-gradient-<?= ['primary', 'success', 'info', 'warning', 'danger'][$l - 1] ?> text-white">
                            <div class="card-body">
                                <h5 class="card-title">Level <?= $l ?></h5>
                                <h4 class="mb-1"><?= $level_counts[$l] ?? 0 ?></h4>
                                <small class="opacity-75"><?= $percentages[$l] ?? 0 ?>% Bonus</small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="col-md-2 mb-3">
                    <div class="card text-center bg-dark text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total</h5>
                            <h4 class="mb-1"><?= array_sum($level_counts) ?></h4>
                            <small class="opacity-75">All Levels</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?= $level_filter == 'all' ? 'active' : '' ?>"
                        href="referrals.php?level=all&search=<?= urlencode($search) ?>">
                        All Levels <span class="badge bg-secondary ms-1"><?= array_sum($level_counts) ?></span>
                    </a>
                </li>
                <?php foreach (range(1, 5) as $l): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $level_filter == $l ? 'active' : '' ?>"
                            href="referrals.php?level=<?= $l ?>&search=<?= urlencode($search) ?>">
                            Level <?= $l ?> <span class="badge bg-secondary ms-1"><?= $level_counts[$l] ?? 0 ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="level" value="<?= htmlspecialchars($level_filter) ?>">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="search"
                                placeholder="Search by username or email" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                            <?php if ($search): ?>
                                <a href="referrals.php?level=<?= $level_filter ?>" class="btn btn-outline-secondary ms-2">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3 text-end">
                            <span class="text-muted"><?= $total_referrals ?>
                                referral<?= $total_referrals != 1 ? 's' : '' ?> found</span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($referralsPage)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Referrals Found</h5>
                            <a href="packages.php" class="btn btn-primary">
                                <i class="fas fa-share"></i> Get Your Referral Link
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Level</th>
                                        <th>Bonus Amount</th>
                                        <th>Percentage</th>
                                        <th>Package Status</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($referralsPage as $index => $ref): ?>
                                        <tr>
                                            <td><?= ($page - 1) * $per_page + $index + 1 ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary rounded-circle me-2 d-flex align-items-center justify-content-center"
                                                        style="width:32px;height:32px;font-size:14px;color:white;">
                                                        <?= strtoupper(substr($ref['username'], 0, 1)) ?>
                                                    </div>
                                                    <span class="fw-medium"><?= htmlspecialchars($ref['username']) ?></span>
                                                </div>
                                            </td>
                                            <td class="text-muted"><?= htmlspecialchars($ref['email']) ?></td>
                                            <td>
                                                <span
                                                    class="badge bg-<?= ['primary', 'success', 'info', 'warning', 'danger'][$ref['level'] - 1] ?>">
                                                    Level <?= $ref['level'] ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold <?= $ref['bonus_amount'] > 0 ? 'text-success' : 'text-muted' ?>">
                                                <?= function_exists('formatCurrency') ? formatCurrency($ref['bonus_amount']) : '$' . number_format($ref['bonus_amount'], 2) ?>
                                            </td>
                                            <td><?= $percentages[$ref['level']] ?>%</td>
                                            <td>
                                                <?php if ($ref['has_active_package']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted"><?= date('M j, Y', strtotime($ref['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Referrals pagination">
                                <ul class="pagination justify-content-center mt-4">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="referrals.php?level=<?= $level_filter ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">
                                                <i class="fas fa-chevron-left"></i> Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <?php
                                    $start = max(1, $page - 2);
                                    $end = min($total_pages, $page + 2);
                                    if ($start > 1): ?>
                                        <li class="page-item"><a class="page-link"
                                                href="?level=<?= $level_filter ?>&search=<?= urlencode($search) ?>&page=1">1</a>
                                        </li>
                                        <?php if ($start > 2): ?>
                                            <li class="disabled page-item"><span class="page-link">...</span></li><?php endif; ?>
                                    <?php endif; ?>
                                    <?php for ($i = $start; $i <= $end; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link"
                                                href="?level=<?= $level_filter ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <?php if ($end < $total_pages): ?>
                                        <?php if ($end < $total_pages - 1): ?>
                                            <li class="disabled page-item"><span class="page-link">...</span></li><?php endif; ?>
                                        <li class="page-item"><a class="page-link"
                                                href="?level=<?= $level_filter ?>&search=<?= urlencode($search) ?>&page=<?= $total_pages ?>"><?= $total_pages ?></a>
                                        </li>
                                    <?php endif; ?>
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link"
                                                href="referrals.php?level=<?= $level_filter ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">
                                                Next <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <div class="text-center text-muted mt-2">
                                Showing <?= ($page - 1) * $per_page + 1 ?> to <?= min($page * $per_page, $total_referrals) ?>
                                of <?= $total_referrals ?> referral<?= $total_referrals != 1 ? 's' : '' ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-info-circle text-primary"></i> How Referral Levels
                                Work</h6>
                            <ul class="list-unstyled small mb-0">
                                <?php foreach (range(1, 5) as $l): ?>
                                    <li><strong>Level <?= $l ?>:</strong>
                                        <?= $l == 1 ? 'Direct referrals' : 'People invited by your Level ' . ($l - 1) . ' referrals' ?>
                                        (<?= $percentages[$l] ?>% bonus)</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-chart-line"></i> Referral Summary</h6>
                            <div class="row text-center">
                                <div class="col-6">
                                    <h4><?= array_sum($level_counts) ?></h4><small>Total Referrals</small>
                                </div>
                                <div class="col-6">
                                    <h4><?php
                                    $totalEarned = 0;
                                    foreach ($fullDownline as $d) {
                                        if ($d['has_active_package']) {
                                            // placeholder: multiply package value by percentage
                                            $totalEarned += 0; // implement later
                                        }
                                    }
                                    echo function_exists('formatCurrency') ? formatCurrency($totalEarned) : '$' . number_format($totalEarned, 2);
                                    ?></h4><small>Total Earned</small>
                                </div>
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