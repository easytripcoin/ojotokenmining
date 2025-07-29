<?php
// user/genealogy.php - Improved vertical genealogy tree
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$user = getUserById($user_id);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Genealogy Tree - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <style>
        #genealogy-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            min-height: 500px;
            overflow: auto;
        }

        #genealogy-tree svg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .node rect {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .node rect:hover {
            filter: brightness(1.2);
            transform: scale(1.05);
        }

        .node text {
            font-size: 11px;
            font-weight: bold;
            fill: white;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
            pointer-events: none;
        }

        .node .username-text {
            font-size: 12px;
            font-weight: bold;
            fill: white;
        }

        .node .level-text {
            font-size: 10px;
            fill: rgba(255, 255, 255, 0.8);
        }

        .link {
            fill: none;
            stroke: rgba(255, 255, 255, 0.8);
            stroke-width: 3px;
            stroke-dasharray: 5, 5;
            animation: dash 20s linear infinite;
        }

        @keyframes dash {
            to {
                stroke-dashoffset: -100;
            }
        }

        .level-0 rect {
            fill: #ff6b6b;
        }

        .level-1 rect {
            fill: #4ecdc4;
        }

        .level-2 rect {
            fill: #45b7d1;
        }

        .level-3 rect {
            fill: #96ceb4;
        }

        .level-4 rect {
            fill: #feca57;
        }

        .level-5 rect {
            fill: #ff9ff3;
        }

        .level-6-plus rect {
            fill: #a8e6cf;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
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
        }

        .legend-circle {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        #genealogy-tree {
            position: relative;
        }

        .zoom-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }

        .zoom-btn {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            margin: 2px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .zoom-btn:hover {
            background: white;
            transform: scale(1.1);
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
            <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="packages.php"><i class="fas fa-box"></i> Packages</a></li>
            <li><a href="ewallet.php"><i class="fas fa-wallet"></i> E-Wallet</a></li>
            <li><a href="genealogy.php"><i class="fas fa-sitemap"></i> Genealogy</a></li>
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
                            <h4 id="total-referrals">0</h4>
                            <small>Total Referrals</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-layer-group fa-2x mb-2"></i>
                            <h4 id="max-depth">0</h4>
                            <small>Max Depth</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line fa-2x mb-2"></i>
                            <h4 id="active-nodes">1</h4>
                            <small>Active Nodes</small>
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
                            <button class="btn btn-sm btn-outline-primary" onclick="loadGenealogyTree()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="tree-legend">
                                <div class="legend-item">
                                    <div class="legend-circle" style="background: #ff6b6b;"></div>
                                    <span>You (Root)</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-circle" style="background: #4ecdc4;"></div>
                                    <span>Level 1 (Direct)</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-circle" style="background: #45b7d1;"></div>
                                    <span>Level 2 (10%)</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-circle" style="background: #96ceb4;"></div>
                                    <span>Level 3 (1%)</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-circle" style="background: #feca57;"></div>
                                    <span>Level 4 (1%)</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-circle" style="background: #ff9ff3;"></div>
                                    <span>Level 5+ (1%)</span>
                                </div>
                            </div>

                            <div id="genealogy-container">
                                <div id="genealogy-tree">
                                    <div class="zoom-controls">
                                        <button class="zoom-btn" onclick="zoomIn()" title="Zoom In">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <button class="zoom-btn" onclick="zoomOut()" title="Zoom Out">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <button class="zoom-btn" onclick="resetZoom()" title="Reset">
                                            <i class="fas fa-home"></i>
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
        let svg, g, zoom, root;
        let currentScale = 1;

        document.addEventListener('DOMContentLoaded', function () {
            loadGenealogyTree();
        });

        function loadGenealogyTree() {
            const container = document.getElementById('genealogy-tree');
            container.innerHTML = `
                <div class="zoom-controls">
                    <button class="zoom-btn" onclick="zoomIn()" title="Zoom In">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="zoom-btn" onclick="zoomOut()" title="Zoom Out">
                        <i class="fas fa-minus"></i>
                    </button>
                    <button class="zoom-btn" onclick="resetZoom()" title="Reset">
                        <i class="fas fa-home"></i>
                    </button>
                </div>
                <div class="text-center py-5">
                    <div class="spinner-border loading-spinner" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h6 class="mt-3">Loading your genealogy tree...</h6>
                </div>
            `;

            fetch('../api/referral_bonus.php?action=tree')
                .then(response => response.json())
                .then(data => {
                    let treeData;
                    if (data && data.name) {
                        treeData = data;
                    } else {
                        // Create user node even with no referrals
                        treeData = {
                            name: "<?= htmlspecialchars($user['username']) ?>",
                            email: "<?= htmlspecialchars($user['email']) ?>",
                            created_at: "<?= htmlspecialchars($user['created_at'] ?? 'Now') ?>",
                            children: []
                        };
                    }
                    renderVerticalTree(treeData);
                    updateStats(treeData);
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Show user node on error
                    const userData = {
                        name: "<?= htmlspecialchars($user['username']) ?>",
                        email: "<?= htmlspecialchars($user['email']) ?>",
                        created_at: "<?= htmlspecialchars($user['created_at'] ?? 'Now') ?>",
                        children: []
                    };
                    renderVerticalTree(userData);
                    updateStats(userData);
                });
        }

        function updateStats(data) {
            // Fetch detailed stats from API
            fetch('../api/referral_bonus.php?action=stats')
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        const stats = result.stats;
                        document.getElementById('total-referrals').textContent = stats.total_referrals || 0;
                        document.getElementById('max-depth').textContent = stats.max_depth || 0;
                        document.getElementById('active-nodes').textContent = (stats.total_referrals || 0) + 1; // Include root
                    }
                })
                .catch(error => {
                    console.error('Stats error:', error);
                    // Fallback to basic calculation
                    const basicStats = calculateTreeStats(data);
                    document.getElementById('total-referrals').textContent = basicStats.totalNodes - 1;
                    document.getElementById('max-depth').textContent = basicStats.maxDepth;
                    document.getElementById('active-nodes').textContent = basicStats.totalNodes;
                });
        }

        function calculateTreeStats(node, depth = 0) {
            let totalNodes = 1;
            let maxDepth = depth;

            if (node.children && node.children.length > 0) {
                node.children.forEach(child => {
                    const childStats = calculateTreeStats(child, depth + 1);
                    totalNodes += childStats.totalNodes;
                    maxDepth = Math.max(maxDepth, childStats.maxDepth);
                });
            }

            return { totalNodes, maxDepth };
        }

        function renderVerticalTree(data) {
            const container = document.getElementById('genealogy-tree');
            const containerRect = container.getBoundingClientRect();

            // Clear previous content but keep zoom controls
            const zoomControls = container.querySelector('.zoom-controls');
            container.innerHTML = '';
            if (zoomControls) {
                container.appendChild(zoomControls);
            }

            const width = Math.max(800, containerRect.width || 800);
            const height = Math.max(600, calculateTreeHeight(data) * 120 + 200);

            svg = d3.select(container)
                .append("svg")
                .attr("width", width)
                .attr("height", height);

            // Add zoom behavior
            zoom = d3.zoom()
                .scaleExtent([0.1, 3])
                .on('zoom', (event) => {
                    currentScale = event.transform.k;
                    g.attr('transform', event.transform);
                });

            svg.call(zoom);

            g = svg.append("g");

            const margin = { top: 60, right: 60, bottom: 60, left: 60 };
            const innerWidth = width - margin.left - margin.right;
            const innerHeight = height - margin.top - margin.bottom;

            // Create hierarchy
            root = d3.hierarchy(data);

            // Create tree layout - VERTICAL
            const treeLayout = d3.tree()
                .size([innerWidth, innerHeight])
                .separation((a, b) => (a.parent === b.parent ? 1 : 2) / a.depth);

            treeLayout(root);

            // Position the tree in the center
            g.attr("transform", `translate(${margin.left},${margin.top})`);

            // Create links with curved paths
            const links = g.selectAll(".link")
                .data(root.links())
                .enter()
                .append("path")
                .attr("class", "link")
                .attr("d", d => {
                    return `M${d.source.x},${d.source.y}
                            C${d.source.x},${(d.source.y + d.target.y) / 2}
                             ${d.target.x},${(d.source.y + d.target.y) / 2}
                             ${d.target.x},${d.target.y}`;
                });

            // Create node groups
            const nodes = g.selectAll(".node")
                .data(root.descendants())
                .enter()
                .append("g")
                .attr("class", d => `node level-${d.depth > 5 ? '6-plus' : d.depth}`)
                .attr("transform", d => `translate(${d.x},${d.y})`);

            // Calculate text width for rectangle sizing
            function getTextWidth(text, fontSize = 12) {
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                context.font = `${fontSize}px Arial`;
                return context.measureText(text).width;
            }

            // Add rounded rectangles for nodes
            nodes.append("rect")
                .attr("width", d => {
                    const name = d.data.name || 'Unknown';
                    const textWidth = getTextWidth(name, d.depth === 0 ? 14 : 12);
                    return Math.max(textWidth + 20, d.depth === 0 ? 120 : 100);
                })
                .attr("height", d => d.depth === 0 ? 50 : 40)
                .attr("x", d => {
                    const name = d.data.name || 'Unknown';
                    const textWidth = getTextWidth(name, d.depth === 0 ? 14 : 12);
                    const width = Math.max(textWidth + 20, d.depth === 0 ? 120 : 100);
                    return -width / 2;
                })
                .attr("y", d => d.depth === 0 ? -25 : -20)
                .attr("rx", 8)
                .attr("ry", 8)
                .style("stroke", "#fff")
                .style("stroke-width", 2)
                .style("filter", "drop-shadow(2px 2px 4px rgba(0,0,0,0.3))");

            // Add username text in rectangles
            nodes.append("text")
                .attr("class", "username-text")
                .attr("text-anchor", "start")
                .attr("x", d => {
                    const name = d.data.name || 'Unknown';
                    const textWidth = getTextWidth(name, d.depth === 0 ? 14 : 12);
                    const width = Math.max(textWidth + 20, d.depth === 0 ? 120 : 100);
                    return -width / 2 + 28;
                })
                .attr("dy", d => d.depth === 0 ? 2 : 4)
                .style("font-size", d => d.depth === 0 ? "14px" : "12px")
                .text(d => {
                    const name = d.data.name || 'Unknown';
                    const maxLength = d.depth === 0 ? 15 : 12;
                    return name.length > maxLength ? name.substring(0, maxLength) + '...' : name;
                });

            // Add click interaction for node details
            nodes.on("click", function (event, d) {
                if (d.depth > 0) {
                    showNodeDetails(d);
                }
            });

            // Center the tree
            const bbox = g.node().getBBox();
            const fullWidth = bbox.width;
            const fullHeight = bbox.height;
            const centerX = width / 2 - fullWidth / 2;
            const centerY = height / 2 - fullHeight / 2;

            svg.call(zoom.transform,
                d3.zoomIdentity.translate(centerX, centerY).scale(0.8)
            );
            currentScale = 0.8;
        }

        function calculateTreeHeight(node, depth = 0) {
            if (!node.children || node.children.length === 0) {
                return depth + 1;
            }

            let maxChildHeight = 0;
            node.children.forEach(child => {
                const childHeight = calculateTreeHeight(child, depth + 1);
                maxChildHeight = Math.max(maxChildHeight, childHeight);
            });

            return maxChildHeight;
        }

        function zoomIn() {
            if (svg && currentScale < 3) {
                svg.transition().duration(300).call(
                    zoom.scaleBy, 1.2
                );
            }
        }

        function zoomOut() {
            if (svg && currentScale > 0.1) {
                svg.transition().duration(300).call(
                    zoom.scaleBy, 1 / 1.2
                );
            }
        }

        function resetZoom() {
            if (svg) {
                const width = parseInt(svg.attr("width"));
                const height = parseInt(svg.attr("height"));
                const bbox = g.node().getBBox();
                const centerX = width / 2 - bbox.width / 2;
                const centerY = height / 2 - bbox.height / 2;

                svg.transition().duration(500).call(
                    zoom.transform,
                    d3.zoomIdentity.translate(centerX, centerY).scale(0.8)
                );
                currentScale = 0.8;
            }
        }

        function showNodeDetails(nodeData) {
            const d = nodeData.data;
            const level = nodeData.depth;
            const bonusRate = level === 1 ? 'Direct (No bonus)' :
                level === 2 ? '10% bonus' :
                    level >= 3 ? '1% bonus' : 'No bonus';

            const joinDate = d.created_at ? new Date(d.created_at).toLocaleDateString() : 'Unknown';

            const details = `
                <div class="alert alert-info">
                    <h6><i class="fas fa-user"></i> ${d.name || 'Unknown'}</h6>
                    <p><strong>Email:</strong> ${d.email || 'Not provided'}</p>
                    <p><strong>Level:</strong> ${level} (${bonusRate})</p>
                    <p><strong>Joined:</strong> ${joinDate}</p>
                </div>
            `;

            // You can show this in a modal or update a details panel
            console.log('Node details:', details);
        }

        // Handle window resize
        window.addEventListener('resize', function () {
            setTimeout(loadGenealogyTree, 300);
        });
    </script>
</body>

</html>