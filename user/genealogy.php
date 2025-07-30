<?php
// user/genealogy.php - ApexTree-powered genealogy visualization
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$user = getUserById($user_id);

/* ------------------------------------------------------------------
   Build the real downline tree for the logged-in user (from referrals.php)
   ------------------------------------------------------------------ */
function buildDownline($pdo, $ancestor, $maxDepth = 5, $currentDepth = 1, &$downline = [])
{
    if ($currentDepth > $maxDepth)
        return;

    $stmt = $pdo->prepare("SELECT id, username, email, created_at, sponsor_id
                           FROM users
                           WHERE sponsor_id = ? ORDER BY created_at ASC");
    $stmt->execute([$ancestor]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['level'] = $currentDepth;

        // Has any active package?
        $pkgStmt = $pdo->prepare("SELECT id FROM user_packages WHERE user_id = ? AND status = 'active' LIMIT 1");
        $pkgStmt->execute([$row['id']]);
        $row['has_active_package'] = (bool) $pkgStmt->fetchColumn();

        // Calculate referral bonus amount
        $row['bonus_amount'] = 0;
        if ($row['has_active_package']) {
            // Get referral percentage for this level
            $percentageStmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_name = ?");
            $percentageStmt->execute(["referral_level_{$currentDepth}_percentage"]);
            $percentage = $percentageStmt->fetchColumn();
            if (!$percentage) {
                // Fallback percentages
                $percentage = ($currentDepth === 1 ? 10 : ($currentDepth === 2 ? 5 : 1));
            }

            // Get package value (assuming there's a package_value field or similar)
            $packageStmt = $pdo->prepare("SELECT p.price FROM user_packages up 
                                         JOIN packages p ON up.package_id = p.id 
                                         WHERE up.user_id = ? AND up.status = 'active' LIMIT 1");
            $packageStmt->execute([$row['id']]);
            $packageValue = $packageStmt->fetchColumn() ?: 0;

            $row['bonus_amount'] = ($packageValue * $percentage) / 100;
        }

        $downline[] = $row;
        buildDownline($pdo, $row['id'], $maxDepth, $currentDepth + 1, $downline);
    }
    return $downline;
}

// Convert flat downline array to hierarchical tree structure for ApexTree
function buildHierarchicalTree($downline, $rootUserId, $rootUser)
{
    // Create a map for quick lookup
    $userMap = [];

    // Add all users to the map first
    foreach ($downline as $person) {
        $userMap[$person['id']] = $person;
    }

    // Add root user to map
    $userMap[$rootUserId] = [
        'id' => $rootUserId,
        'username' => $rootUser['username'],
        'email' => $rootUser['email'],
        'created_at' => $rootUser['created_at'] ?? date('Y-m-d H:i:s'),
        'level' => 0,
        'sponsor_id' => null,
        'has_active_package' => true,
        'bonus_amount' => 0 // Root doesn't earn from self
    ];

    // Build the tree structure
    function buildTreeRecursive($parentId, $userMap, $downline)
    {
        $parent = $userMap[$parentId];
        $level = $parent['level'];

        $node = [
            'id' => 'user_' . $parentId,
            'data' => [
                'name' => $parent['username'],
                'level' => $level,
                'joined' => $parent['created_at'],
                'hasPackage' => $parent['has_active_package'],
                'bonusAmount' => $parent['bonus_amount'] ?? 0,
                'isRoot' => $level == 0
            ],
            'options' => [
                'nodeBGColor' => '#ff6b6b',
                'nodeBGColorHover' => '#ff5252'
            ],
            'children' => []
        ];

        // Find direct children
        foreach ($downline as $person) {
            if ($person['sponsor_id'] == $parentId) {
                $childNode = buildTreeRecursive($person['id'], $userMap, $downline);
                $node['children'][] = $childNode;
            }
        }

        return $node;
    }

    return buildTreeRecursive($rootUserId, $userMap, $downline);
}

try {
    $pdo = getConnection();
    $fullDownline = buildDownline($pdo, $user_id);
    $treeData = buildHierarchicalTree($fullDownline, $user_id, $user);

    // Calculate stats
    $totalReferrals = count($fullDownline);
    $maxDepth = 0;
    $levelCounts = array_fill(1, 5, 0);
    $totalEarnings = 0;

    foreach ($fullDownline as $person) {
        $maxDepth = max($maxDepth, $person['level']);
        if ($person['level'] <= 5) {
            $levelCounts[$person['level']]++;
        }
        $totalEarnings += $person['bonus_amount'] ?? 0;
    }

} catch (Exception $e) {
    error_log("Genealogy error: " . $e->getMessage());
    $treeData = [
        'id' => 'user_' . $user_id,
        'data' => [
            'name' => $user['username'],
            'level' => 0,
            'joined' => $user['created_at'] ?? date('Y-m-d H:i:s'),
            'isRoot' => true,
            'hasPackage' => true,
            'bonusAmount' => 0
        ],
        'options' => [
            'nodeBGColor' => '#ff6b6b',
            'nodeBGColorHover' => '#ff5252'
        ],
        'children' => []
    ];
    $totalReferrals = 0;
    $maxDepth = 0;
    $levelCounts = array_fill(1, 5, 0);
    $totalEarnings = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Genealogy Tree - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        /* === Container & Controls === */
        #genealogy-container {
            position: relative;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            min-height: 600px;
            overflow: hidden;
            width: 100%;
            height: 100%;
        }

        #genealogy-tree {
            width: 100%;
            height: 100%;
            position: relative;
        }

        .tree-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 100;
            display: flex;
            gap: 6px;
        }

        .tree-btn {
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 50%;
            background: rgba(255, 255, 255, .9);
            box-shadow: 0 2px 6px rgba(0, 0, 0, .15);
            cursor: pointer;
            transition: transform .2s;
        }

        .tree-btn:hover {
            transform: scale(1.15);
        }

        /* === NODE === */
        .tree-node {
            position: absolute;
            width: 120px;
            padding: 8px;
            border-radius: 8px;
            color: #fff;
            font-size: 12px;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .15);
            transition: transform .2s;
            cursor: default;
        }

        .tree-node .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin: 0 auto 4px;
            background: #fff3;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
            color: #fff;
        }

        .tree-node .level-badge {
            font-size: 8px;
            background: rgba(255, 255, 255, .25);
            padding: 1px 4px;
            border-radius: 6px;
            margin-top: 2px;
        }

        .tree-node .bonus {
            font-size: 10px;
            font-weight: bold;
            color: #ffd700;
            margin-top: 1px;
        }

        .tree-node .toggle {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 12px;
            /* Reduced width */
            height: 12px;
            /* Reduced height */
            border-radius: 50%;
            background: rgba(255, 255, 255, .25);
            font-size: 8px;
            /* Reduced font size */
            line-height: 12px;
            /* Adjusted line height */
            text-align: center;
            cursor: pointer;
            user-select: none;
        }

        .tree-node.hidden-children .children {
            display: none;
        }

        /* === CONNECTION LINES === */
        .tree-canvas {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        /* === LEGEND (unchanged) === */
        .tree-legend {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            background: rgba(255, 255, 255, .9);
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
        }

        .legend-circle {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        #genealogy-tree {
            cursor: grab;
        }

        #genealogy-tree:active {
            cursor: grabbing;
        }
    </style>
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
            <li class="active"><a href="genealogy.php"><i class="fas fa-sitemap"></i> Genealogy</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <main class="main-content">
        <div class="container-fluid">
            <h2 class="mb-4">
                <i class="fas fa-sitemap me-2"></i>Referral Genealogy Tree
            </h2>

            <!-- Stats Row -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <h4><?= $totalReferrals ?></h4>
                            <small>Total Referrals</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-layer-group fa-2x mb-2"></i>
                            <h4><?= $maxDepth ?></h4>
                            <small>Max Depth</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card earnings-card">
                        <div class="card-body text-center">
                            <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                            <h4><?= function_exists('formatCurrency') ? formatCurrency($totalEarnings) : '$' . number_format($totalEarnings, 2) ?>
                            </h4>
                            <small>Total Earnings</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-crown fa-2x mb-2"></i>
                            <h4>YOU</h4>
                            <small>Root Node</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-sitemap me-2"></i>Your Referral Network</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="refreshTree()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="tree-legend">
                                <div class="legend-item">
                                    <div class="legend-circle" style="background: #ff6b6b;"></div>
                                    <span>You (Root)</span>
                                </div>
                                <?php if ($totalReferrals > 0): ?>
                                    <div class="legend-item">
                                        <div class="legend-circle" style="background: #4ecdc4;"></div>
                                        <span>Level 1 (Direct)</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-circle" style="background: #45b7d1;"></div>
                                        <span>Level 2</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-circle" style="background: #96ceb4;"></div>
                                        <span>Level 3</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-circle" style="background: #feca57;"></div>
                                        <span>Level 4</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-circle" style="background: #ff9ff3;"></div>
                                        <span>Level 5+</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div id="genealogy-container">
                                <div id="genealogy-tree">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/apextree.min.js"></script>
    <script>
        // Function to generate a random hash
        function generateRandomHash() {
            return Math.floor(Math.random() * 0xFFFFFFFF).toString(16); // Generate a random 32-bit hex value
        }

        // Function to generate a random color
        function getRandomColor() {
            const r = Math.floor(Math.random() * 256);
            const g = Math.floor(Math.random() * 256);
            const b = Math.floor(Math.random() * 256);
            return `rgb(${r}, ${g}, ${b})`;
        }

        // Function to generate a canvas with a random Identicon-like pattern
        function generateIdenticon(size = 50) {
            const canvas = document.createElement('canvas');
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');

            // Fill background with light gray
            ctx.fillStyle = '#f0f0f0';
            ctx.fillRect(0, 0, size, size);

            // Use a 5x5 grid
            const gridSize = 5;
            const cellSize = size / gridSize;

            // Generate a random hash for the pattern
            const hash = generateRandomHash();

            // Generate a random color for the pattern
            const patternColor = getRandomColor();

            // Calculate the offset to center the pattern
            const offsetX = (size - (gridSize * cellSize)) / 2;
            const offsetY = (size - (gridSize * cellSize)) / 2;

            // Generate pattern based on the random hash
            for (let i = 0; i < gridSize; i++) {
                for (let j = 0; j < gridSize; j++) {
                    // Use the hash to determine if the cell should be filled
                    const index = (i * gridSize + j) % 32; // Use lower bits of hash
                    if ((parseInt(hash, 16) >> index) & 1) { // Convert hash to integer and check the bit
                        ctx.fillStyle = patternColor; // Use the random color
                        ctx.fillRect(offsetX + j * cellSize, offsetY + i * cellSize, cellSize, cellSize);
                    }
                }
            }

            canvas.className = 'avatar-canvas'; // Apply CSS class for styling
            return canvas.toDataURL(); // Return as data URL for the image source
        }

        // Function to recursively add random colors to nodes and generate avatars
        function addRandomColorsAndAvatars(node) {
            node.options = node.options || {};
            node.options.nodeBGColor = getRandomColor();
            node.options.nodeBGColorHover = node.options.nodeBGColor;

            if (node.data) {
                node.data.imageURL = generateIdenticon(); // Generate a random identicon as data URL
            }

            if (node.children && node.children.length > 0) {
                node.children.forEach(child => {
                    addRandomColorsAndAvatars(child);
                });
            }

            return node;
        }

        // Original data structure
        const treeData = <?= json_encode($treeData) ?>;

        // Apply random colors and generate avatars
        const updatedData = addRandomColorsAndAvatars(treeData);

        const options = {
            contentKey: 'data',
            width: 800,
            height: 600,
            nodeWidth: 150,
            nodeHeight: 100,
            fontColor: '#fff',
            borderColor: '#333',
            childrenSpacing: 50,
            siblingSpacing: 20,
            direction: 'top',
            enableExpandCollapse: true,
            nodeTemplate: (content) =>
                `<div style='display: flex;flex-direction: column;gap: 10px;justify-content: center;align-items: center;height: 100%;'>
                    <img style='width: 50px;height: 50px;' src='${content.imageURL}' alt='' class='avatar-canvas' />
                    <div style="font-weight: bold; font-family: Arial; font-size: 14px">${content.name}</div>
                </div>`,
            canvasStyle: 'border: 1px solid black;background: #f6f6f6;',
            enableToolbar: true,
        };

        document.addEventListener('DOMContentLoaded', () => {
            const tree = new ApexTree(document.getElementById('genealogy-tree'), options);
            tree.render(updatedData);
        });
    </script>
</body>

</html>