<?php
/**
 * Campaigns Module - Manage marketing initiatives and outreach
 */

// Handle Actions
$action = $_GET['action'] ?? null;

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $type = $_POST['type'] ?? 'Email';
    $status = $_POST['status'] ?? 'Draft';
    $start_date = $_POST['start_date'] ?? date('Y-m-d');

    if (!empty($title)) {
        try {
            $stmt = $marketingDb->prepare("INSERT INTO campaigns (title, type, status, start_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $type, $status, $start_date]);
            
            $newId = $marketingDb->lastInsertId();
            log_marketing_audit($marketingDb, 'Campaign', $newId, 'CREATED', "Created new marketing campaign: $title");
            
            header("Location: ?page=campaigns&success=1");
            exit;
        } catch (Exception $e) {
            $error = "Failed to create campaign: " . $e->getMessage();
        }
    } else {
        $error = "Campaign Title is required.";
    }
}
?>

<header class="page-header">
    <h1>Marketing Campaigns</h1>
    <p>Organize your outreach efforts and track engagement across channels.</p>
</header>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Campaign created successfully!</div>
<?php endif; ?>

<div class="dashboard-grid">
    <!-- NEW CAMPAIGN FORM -->
    <section class="card">
        <h2>Create New Campaign</h2>
        <form action="?page=campaigns&action=add" method="POST" class="standard-form">
            <div class="form-group">
                <label for="title">Campaign Title</label>
                <input type="text" name="title" id="title" required placeholder="e.g. Q2 Laptop Liquidation">
            </div>
            <div class="form-group">
                <label for="type">Outreach Type</label>
                <select name="type" id="type">
                    <option value="Email Blast">Email Blast</option>
                    <option value="LinkedIn Outreach">LinkedIn Outreach</option>
                    <option value="Cold Call Blitz">Cold Call Blitz</option>
                    <option value="Bulk Manifest">Bulk Manifest</option>
                </select>
            </div>
            <div class="form-group">
                <label for="status">Initial Status</label>
                <select name="status" id="status">
                    <option value="Draft">Draft (Planning)</option>
                    <option value="Active">Active (Running)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="start_date">Launch Date</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <button type="submit" class="btn-action">Launch Campaign</button>
        </form>
    </section>

    <!-- CAMPAIGN LIST -->
    <section class="card" style="grid-column: span 2;">
        <h2>Active & Planned Initiatives</h2>
        <div class="campaign-list">
            <?php
            $campaigns = $marketingDb->query("SELECT * FROM campaigns ORDER BY created_at DESC")->fetchAll();
            if (empty($campaigns)):
            ?>
                <div style="text-align: center; padding: 4rem; color: var(--text-dim);">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🎯</div>
                    <p>No campaigns found. Start your first initiative to see progress here.</p>
                </div>
            <?php else: ?>
                <div class="campaign-grid">
                    <?php foreach ($campaigns as $camp): 
                        $statusClass = 'badge-new';
                        if ($camp['status'] === 'Active') $statusClass = 'badge-lead';
                        if ($camp['status'] === 'Completed') $statusClass = 'badge-customer';
                    ?>
                        <div class="campaign-card">
                            <div class="camp-header">
                                <span class="badge <?php echo $statusClass; ?>"><?php echo strtoupper($camp['status']); ?></span>
                                <span style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo $camp['type']; ?></span>
                            </div>
                            <h3><?php echo htmlspecialchars($camp['title']); ?></h3>
                            <div class="camp-meta">
                                📅 Starts: <?php echo date('M j, Y', strtotime($camp['start_date'])); ?>
                            </div>
                            <div class="camp-footer" style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
                                <a href="?page=campaigns&action=manage&id=<?php echo $camp['id']; ?>" class="btn-small">Manage</a>
                                <a href="?page=ad_generator&campaign_id=<?php echo $camp['id']; ?>" class="btn-small btn-highlight">Get Ads</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<style>
.campaign-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}
.campaign-card {
    background: #f8fafc;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    transition: all 0.2s;
}
.campaign-card:hover {
    border-color: var(--accent-primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.camp-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}
.campaign-card h3 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: var(--text-main);
}
.camp-meta {
    font-size: 0.85rem;
    color: var(--text-secondary);
}
</style>
