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
        $levelColors = [
            0 => '#ff6b6b',
            1 => '#4ecdc4',
            2 => '#45b7d1',
            3 => '#96ceb4',
            4 => '#feca57',
            5 => '#ff9ff3'
        ];

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
                'nodeBGColor' => $levelColors[$level] ?? '#a8e6cf',
                'nodeBGColorHover' => $levelColors[$level] ?? '#a8e6cf'
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
        #genealogy-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            min-height: 600px;
            overflow: hidden;
            position: relative;
        }

        #genealogy-tree {
            width: 100%;
            height: 600px;
            position: relative;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .earnings-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            transition: transform 0.3s ease;
        }

        .earnings-card:hover {
            transform: translateY(-5px);
        }

        .loading-spinner {
            color: #667eea;
        }

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
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .legend-circle {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .tree-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            display: flex;
            gap: 5px;
        }

        .tree-btn {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tree-btn:hover {
            background: white;
            transform: scale(1.1);
        }

        /* Custom avatar styling for ApexTree nodes */
        .avatar-canvas {
            border-radius: 50%;
            width: 50px;
            height: 50px;
            object-fit: cover;
            border: 2px solid #fff;
            box-sizing: border-box;
        }

        /* ApexTree canvas styling */
        .apex-tree-canvas {
            border: 1px solid #ddd !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-radius: 8px;
        }

        /* Fallback tree styles for when ApexTree fails */
        .fallback-tree {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px;
        }

        .tree-node {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 10px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            min-width: 180px;
        }

        .tree-level {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }

        .level-0 .tree-node {
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
        }

        .level-1 .tree-node {
            background: linear-gradient(135deg, #4ecdc4, #26a69a);
        }

        .level-2 .tree-node {
            background: linear-gradient(135deg, #45b7d1, #2196f3);
        }

        .level-3 .tree-node {
            background: linear-gradient(135deg, #96ceb4, #4caf50);
        }

        .level-4 .tree-node {
            background: linear-gradient(135deg, #feca57, #ff9800);
        }

        .level-5 .tree-node {
            background: linear-gradient(135deg, #ff9ff3, #e91e63);
        }

        .bonus-amount {
            font-weight: bold;
            color: #ffd700;
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
                                    <div class="tree-controls">
                                        <button class="tree-btn" onclick="expandAll()" title="Expand All">
                                            <i class="fas fa-expand-arrows-alt"></i>
                                        </button>
                                        <button class="tree-btn" onclick="collapseAll()" title="Collapse All">
                                            <i class="fas fa-compress-arrows-alt"></i>
                                        </button>
                                        <button class="tree-btn" onclick="centerTree()" title="Center">
                                            <i class="fas fa-crosshairs"></i>
                                        </button>
                                    </div>
                                    <div class="text-center py-5">
                                        <div class="spinner-border loading-spinner" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <h6 class="mt-3">Loading your genealogy tree...</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let tree;

        // Tree data from PHP
        const treeData = <?= json_encode($treeData) ?>;

        console.log('Tree Data:', treeData); // Debug output

        document.addEventListener('DOMContentLoaded', function () {
            // Try to load ApexTree, fallback to simple tree if it fails
            loadApexTreeScript().then(() => {
                initializeApexTree();
            }).catch(() => {
                console.warn('ApexTree failed to load, using fallback');
                initializeFallbackTree();
            });
        });

        function loadApexTreeScript() {
            return new Promise((resolve, reject) => {
                // Try multiple CDN sources for ApexTree
                const sources = [
                    'https://cdn.jsdelivr.net/npm/apextree@1.0.0/dist/apextree.min.js',
                    'https://unpkg.com/apextree@1.0.0/dist/apextree.min.js',
                    '../assets/js/apextree.min.js' // Local fallback
                ];

                let currentIndex = 0;

                function tryLoadScript() {
                    if (currentIndex >= sources.length) {
                        reject(new Error('All ApexTree sources failed'));
                        return;
                    }

                    const script = document.createElement('script');
                    script.src = sources[currentIndex];
                    script.onload = () => {
                        if (typeof ApexTree !== 'undefined') {
                            resolve();
                        } else {
                            currentIndex++;
                            tryLoadScript();
                        }
                    };
                    script.onerror = () => {
                        currentIndex++;
                        tryLoadScript();
                    };
                    document.head.appendChild(script);
                }

                tryLoadScript();
            });
        }

        function generateAvatar(name, level = 0) {
            const canvas = document.createElement('canvas');
            canvas.width = 50;
            canvas.height = 50;
            const ctx = canvas.getContext('2d');

            // Background colors based on level
            const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#feca57', '#ff9ff3'];
            const bgColor = colors[level] || '#a8e6cf';

            // Fill background
            ctx.fillStyle = bgColor;
            ctx.beginPath();
            ctx.arc(25, 25, 25, 0, 2 * Math.PI);
            ctx.fill();

            // Add text (first letter of name)
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 20px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            const initial = name ? name.charAt(0).toUpperCase() : '?';
            ctx.fillText(initial, 25, 25);

            return canvas.toDataURL();
        }

        function addAvatarsToTree(node) {
            if (node.data) {
                node.data.imageURL = generateAvatar(node.data.name, node.data.level);
            }

            if (node.children && node.children.length > 0) {
                node.children.forEach(child => {
                    addAvatarsToTree(child);
                });
            }

            return node;
        }

        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2
            }).format(amount);
        }

        function initializeApexTree() {
            const container = document.getElementById('genealogy-tree');

            // Clear loading content
            container.innerHTML = `
                <div class="tree-controls">
                    <button class="tree-btn" onclick="expandAll()" title="Expand All">
                        <i class="fas fa-expand-arrows-alt"></i>
                    </button>
                    <button class="tree-btn" onclick="collapseAll()" title="Collapse All">
                        <i class="fas fa-compress-arrows-alt"></i>
                    </button>
                    <button class="tree-btn" onclick="centerTree()" title="Center">
                        <i class="fas fa-crosshairs"></i>
                    </button>
                </div>
            `;

            // Add avatars to tree data
            const dataWithAvatars = addAvatarsToTree(JSON.parse(JSON.stringify(treeData)));

            const options = {
                contentKey: 'data',
                width: container.offsetWidth,
                height: 600,
                nodeWidth: 160,
                nodeHeight: 130,
                fontColor: '#fff',
                borderColor: '#333',
                childrenSpacing: 60,
                siblingSpacing: 30,
                direction: 'top',
                enableExpandCollapse: true,
                enableConnections: true, // Enable connecting lines
                connectionColor: '#ffffff',
                connectionWidth: 2,
                nodeTemplate: (content) => {
                    const statusBadge = content.hasPackage ?
                        '<span style="background: #28a745; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px;">ACTIVE</span>' :
                        '<span style="background: #6c757d; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px;">INACTIVE</span>';

                    const levelBadge = content.isRoot ?
                        '<span style="background: #dc3545; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px;">ROOT</span>' :
                        `<span style="background: #007bff; color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px;">L${content.level}</span>`;

                    const bonusDisplay = content.isRoot ?
                        '<div style="font-size: 11px; color: #ffd700; font-weight: bold;">Network Leader</div>' :
                        `<div style="font-size: 11px; color: #ffd700; font-weight: bold;">Bonus: ${formatCurrency(content.bonusAmount || 0)}</div>`;

                    return `
                        <div style='display: flex; flex-direction: column; gap: 6px; justify-content: center; align-items: center; height: 100%; padding: 8px;'>
                            <img style='width: 50px; height: 50px;' src='${content.imageURL}' alt='' class='avatar-canvas' />
                            <div style="font-weight: bold; font-family: Arial; font-size: 12px; text-align: center;">${content.name}</div>
                            <div style="display: flex; gap: 4px; flex-wrap: wrap; justify-content: center;">
                                ${levelBadge}
                                ${!content.isRoot ? statusBadge : ''}
                            </div>
                            ${bonusDisplay}
                            ${content.isRoot ? '' : `<div style="font-size: 10px; opacity: 0.8;">${new Date(content.joined).toLocaleDateString()}</div>`}
                        </div>
                    `;
                },
                canvasStyle: 'border: 1px solid #ddd; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px;',
                enableToolbar: true,
                toolbarOptions: {
                    zoom: true,
                    fit: true,
                    expandCollapse: true,
                    fullscreen: true
                }
            };

            try {
                tree = new ApexTree(container, options);
                tree.render(dataWithAvatars);
            } catch (error) {
                console.error('ApexTree initialization failed:', error);
                initializeFallbackTree();
            }
        }

        function initializeFallbackTree() {
            const container = document.getElementById('genealogy-tree');

            // Render simple fallback tree
            function renderFallbackNode(node, level = 0) {
                const hasChildren = node.children && node.children.length > 0;
                const bonusDisplay = node.data.isRoot ?
                    'Network Leader' :
                    `Bonus: ${formatCurrency(node.data.bonusAmount || 0)}`;

                let html = `
                    <div class="tree-level level-${level}">
                        <div class="tree-node">
                            <div style="font-weight: bold; margin-bottom: 5px;">${node.data.name}</div>
                            <div class="bonus-amount" style="font-size: 12px; margin-bottom: 5px;">${bonusDisplay}</div>
                            <div style="font-size: 10px; margin-top: 5px;">
                                ${node.data.isRoot ? 'ROOT' : `Level ${node.data.level}`}
                                ${node.data.hasPackage ? ' • ACTIVE' : ' • INACTIVE'}
                            </div>
                        </div>
                    </div>
                `;

                if (hasChildren) {
                    node.children.forEach(child => {
                        html += renderFallbackNode(child, level + 1);
                    });
                }

                return html;
            }

            container.innerHTML = `
                <div class="fallback-tree">
                    ${renderFallbackNode(treeData)}
                </div>
            `;
        }

        function refreshTree() {
            window.location.reload();
        }

        function expandAll() {
            if (tree && tree.expandAll) {
                tree.expandAll();
            }
        }

        function collapseAll() {
            if (tree && tree.collapseAll) {
                tree.collapseAll();
            }
        }

        function centerTree() {
            if (tree && tree.fit) {
                tree.fit();
            }
        }

        // Handle window resize
        window.addEventListener('resize', function () {
            setTimeout(() => {
                if (tree && tree.fit) {
                    tree.fit();
                }
            }, 300);
        });
    </script>
</body>

</html>