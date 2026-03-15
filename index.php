<?php 
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php'; 

// Fetch basic stats (This will error if init_db isn't run, handle gracefully)
$total_inventory = 0;
$total_sales = "$0.00";
$total_leads = 0;

try {
    // Inventory Count
    $stmt = $pdo_labels->query("SELECT COUNT(id) FROM items WHERE status = 'In Warehouse'");
    $total_inventory = $stmt->fetchColumn();

    // Sales Output
    $stmt = $pdo_orders->query("SELECT SUM(total_price) FROM purchase_orders");
    if($sum = $stmt->fetchColumn()) {
        $total_sales = format_currency($sum);
    }

    // Lead Counts
    $stmt = $pdo_rolodex->query("SELECT COUNT(customer_id) FROM customers WHERE lead_status != 'Inactive'");
    $total_leads = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Silently continue if databases aren't initialized yet
}
?>

<div class="panel">
    <h1>Dashboard Overview</h1>
    <p>Welcome to the IQA Metal internal label & inventory tracking system.</p>
</div>

<div class="form-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
    <!-- Stat Box 1: Hardware -->
    <div class="panel text-center" style="border-top: 4px solid var(--accent-color);">
        <h3 style="color: var(--text-secondary); font-size: 1rem; text-transform: uppercase;">In Warehouse</h3>
        <p style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;"><?= $total_inventory ?></p>
        <a href="labels.php" class="btn btn-primary" style="font-size: 0.8rem; padding: 6px 12px;">View Stock</a>
    </div>

    <!-- Stat Box 2: Finances -->
    <div class="panel text-center" style="border-top: 4px solid var(--btn-success-bg);">
        <h3 style="color: var(--text-secondary); font-size: 1rem; text-transform: uppercase;">Total Sales</h3>
        <p style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;"><?= $total_sales ?></p>
        <a href="orders.php" class="btn btn-primary" style="font-size: 0.8rem; padding: 6px 12px;">View Orders</a>
    </div>

    <!-- Stat Box 3: Leads -->
    <div class="panel text-center" style="border-top: 4px solid #f39c12;">
        <h3 style="color: var(--text-secondary); font-size: 1rem; text-transform: uppercase;">Active CRM</h3>
        <p style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;"><?= $total_leads ?></p>
        <a href="rolodex.php" class="btn btn-primary" style="font-size: 0.8rem; padding: 6px 12px;">View Contacts</a>
    </div>
</div>

<div class="form-grid">
    <!-- Quick Search Widget -->
    <div class="panel">
        <h2>🔍 Quick Locate</h2>
        <p style="margin-bottom: 15px; font-size: 0.9rem;">Scan a physical label barcode or type an ID to find a laptop's exact location.</p>
        <form id="quickSearchForm" class="flex-between">
            <input type="number" id="quickSearchId" placeholder="Scan Barcode / ID..." required style="margin-right: 10px;">
            <button type="submit" class="btn btn-primary">Find</button>
        </form>
        <div id="quickSearchResult" style="margin-top: 15px;"></div>
    </div>
    
    <!-- Action Widget -->
    <div class="panel">
        <h2>⚡ Quick Actions</h2>
        <ul style="list-style: none;">
            <li style="margin-bottom: 10px;">
                <a href="new_label.php" class="btn btn-success" style="width: 100%; text-align: left;">➕ Print New Hardware Label (.odt)</a>
            </li>
            <li>
                <a href="new_order.php" class="btn btn-primary" style="width: 100%; text-align: left;">🛒 Basket Items into Purchase Form (.ots)</a>
            </li>
        </ul>
    </div>
</div>

<!-- Highlight active menu item via JS -->
<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-dashboard').classList.add('active');
        
        // Boilerplate logic for the Quick Search API fetch pattern
        document.getElementById('quickSearchForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const resultDiv = document.getElementById('quickSearchResult');
            resultDiv.innerHTML = '<span style="color: var(--accent-color);">Searching...</span>';
            // In Phase 3, this will hit /api/search.php via fetch() and return the layout location.
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>