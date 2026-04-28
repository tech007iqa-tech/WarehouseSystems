require_once 'config.php';
require_once INCLUDES_PATH . '/db.php';

$marketingDb = get_marketing_db();
$labelsDb = get_labels_db();

// Fetch real stats
$leadCount = $marketingDb->query("SELECT COUNT(*) FROM leads")->fetchColumn() ?: 0;
$campaignCount = $marketingDb->query("SELECT COUNT(*) FROM campaigns WHERE status = 'Active'")->fetchColumn() ?: 0;

// Fetch inventory for summary (items with qty > 10)
$inventoryCount = 0;
if ($labelsDb) {
    $inventoryCount = $labelsDb->query("SELECT COUNT(DISTINCT model) FROM items WHERE status = 'In Warehouse'")->fetchColumn() ?: 0;
}

include_once INCLUDES_PATH . '/header.php';
?>

<main id="main-content">
    <header class="page-header">
        <h1>Welcome to <?php echo APP_NAME; ?></h1>
        <p>Your modular marketing command center.</p>
    </header>

    <div class="dashboard-grid">
        <section class="card lead-summary">
            <h2>Lead Statistics</h2>
            <div class="stat"><?php echo $leadCount; ?> Leads Tracked</div>
        </section>

        <section class="card campaign-summary">
            <h2>Active Campaigns</h2>
            <div class="stat"><?php echo $campaignCount; ?> Active</div>
        </section>

        <section class="card inventory-summary">
            <h2>Marketable Stock</h2>
            <div class="stat"><?php echo $inventoryCount; ?> Models in Bulk</div>
        </section>
    </div>
</main>

<?php
include_once INCLUDES_PATH . '/footer.php';
?>
