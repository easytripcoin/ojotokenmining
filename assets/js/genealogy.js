// assets/js/genealogy.js - D3.js referral tree
document.addEventListener('DOMContentLoaded', function () {
    loadGenealogyTree();
});

function loadGenealogyTree() {
    fetch('../api/referral_bonus.php?action=tree')
        .then(response => response.json())
        .then(data => {
            renderTree(data);
        })
        .catch(error => {
            console.error('Error loading genealogy:', error);
            document.getElementById('genealogy-tree').innerHTML =
                '<div class="alert alert-danger">Error loading genealogy data</div>';
        });
}

function renderTree(data) {
    const width = document.getElementById('genealogy-tree').clientWidth;
    const height = 600;

    const svg = d3.select("#genealogy-tree")
        .append("svg")
        .attr("width", width)
        .attr("height", height)
        .append("g")
        .attr("transform", "translate(50,50)");

    const root = d3.hierarchy(data);
    const treeLayout = d3.tree().size([width - 100, height - 100]);
    treeLayout(root);

    // Links
    svg.selectAll(".link")
        .data(root.links())
        .enter()
        .append("path")
        .attr("class", "link")
        .attr("d", d3.linkHorizontal()
            .x(d => d.y)
            .y(d => d.x))
        .style("fill", "none")
        .style("stroke", "#ccc")
        .style("stroke-width", 2);

    // Nodes
    const nodes = svg.selectAll(".node")
        .data(root.descendants())
        .enter()
        .append("g")
        .attr("class", "node")
        .attr("transform", d => `translate(${d.y},${d.x})`);

    // Node circles
    nodes.append("circle")
        .attr("r", 20)
        .style("fill", d => d.depth === 0 ? "#667eea" : "#28a745")
        .style("stroke", "#fff")
        .style("stroke-width", 2);

    // Node text
    nodes.append("text")
        .attr("dy", 35)
        .style("text-anchor", "middle")
        .style("font-size", "12px")
        .style("fill", "#333")
        .text(d => d.data.name);

    // Tooltips
    nodes.append("title")
        .text(d => `${d.data.name}\n${d.data.email || ''}\nJoined: ${d.data.created_at || 'Now'}`);

    // Add tooltips
    nodes.on("mouseover", function (event, d) {
        d3.select(this).select("circle")
            .transition()
            .duration(200)
            .attr("r", 25);
    })
        .on("mouseout", function (event, d) {
            d3.select(this).select("circle")
                .transition()
                .duration(200)
                .attr("r", 20);
        });
}

function expandAll() {
    // Implementation for expanding all nodes
    console.log('Expand all functionality');
}

function collapseAll() {
    // Implementation for collapsing all nodes
    console.log('Collapse all functionality');
}