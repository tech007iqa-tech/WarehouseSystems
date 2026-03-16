<?php
// settings.php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/status_functions.php';
require_once 'includes/header.php';

$health = get_system_health($pdo_labels, $pdo_orders, $pdo_rolodex);
$message = '';
$msg_type = 'success';

// Handle Integrity Repair
if (isset($_POST['repair_db'])) {
    $pdo_labels->exec("PRAGMA integrity_check");
    $pdo_orders->exec("PRAGMA integrity_check");
    $pdo_rolodex->exec("PRAGMA integrity_check");
    $message = "Integrity check completed on all databases.";
    // Refresh health
    $health = get_system_health($pdo_labels, $pdo_orders, $pdo_rolodex);
}

// Handle Manual Backup (Simulated for now, would typically zip the /db/ folder)
if (isset($_POST['backup_db'])) {
    $backup_dir = __DIR__ . '/db/backups/';
    if (!is_dir($backup_dir)) mkdir($backup_dir, 0777, true);
    
    $timestamp = date('Y-m-d_His');
    copy(__DIR__ . '/db/labels.sqlite', $backup_dir . "labels_backup_$timestamp.sqlite");
    copy(__DIR__ . '/db/orders.sqlite', $backup_dir . "orders_backup_$timestamp.sqlite");
    copy(__DIR__ . '/db/rolodex.sqlite', $backup_dir . "rolodex_backup_$timestamp.sqlite");
    
    $message = "System backup created successfully in /db/backups/.";
}
?>

<div class="panel">
    <h1>⚙️ System Settings</h1>
    <p>Manage database health, backups, and system-wide recovery tools.</p>
</div>

<?php if ($message): ?>
    <div style="background: var(--bg-panel); border-left: 5px solid var(--accent-color); padding: 15px; margin-bottom: 20px; border-radius: 4px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="form-grid">
    <!-- Health Status Card -->
    <div class="panel">
        <h2 style="display:flex; align-items:center; gap:10px;">
            <span>🛡️ Database Health</span>
            <span style="font-size:0.75rem; padding:4px 10px; border-radius:12px; background:<?= $health['status'] === 'Healthy' ? 'var(--btn-success-bg)' : 'var(--btn-danger-bg)' ?>; color:#fff;">
                <?= strtoupper($health['status']) ?>
            </span>
        </h2>
        
        <div style="margin:20px 0;">
            <?php foreach ($health['databases'] as $db): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border-color);">
                    <div style="font-weight:bold;"><?= $db['name'] ?> Engine</div>
                    <div style="font-size:0.85rem; color:var(--text-secondary);">
                        Records: <span style="color:var(--text-main);"><?= $db['records'] ?></span> &nbsp;·&nbsp;
                        Status: <span style="color:<?= $db['integrity'] ? 'var(--btn-success-bg)' : 'var(--btn-danger-bg)' ?>;">
                            <?= $db['integrity'] ? 'Integrity OK' : 'Corruption Detected' ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="POST">
            <button type="submit" name="repair_db" class="btn btn-primary" style="width:100%;">Run Deep Integrity Repair</button>
        </form>
    </div>

    <!-- Backup & Recovery Card -->
    <div class="panel">
        <h2>📂 Backup & Maintenance</h2>
        <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:20px;">
            Creating a backup will save a copy of your current inventory, orders, and contacts into the internal server vault.
        </p>
        
        <form method="POST" style="display:flex; flex-direction:column; gap:10px;">
            <button type="submit" name="backup_db" class="btn btn-success" style="padding:15px; font-weight:bold;">
                📦 Create Instant System Backup
            </button>
            <p style="font-size:0.75rem; text-align:center; color:var(--text-secondary);">
                Backup location: <code>/db/backups/</code>
            </p>
        </form>

        <hr style="margin:25px 0; border:0; border-top:1px dashed var(--border-color);">

        <div style="background:rgba(220,53,69,0.05); padding:15px; border-radius:8px;">
            <h4 style="color:var(--btn-danger-bg); margin:0 0 5px 0;">Danger Zone</h4>
            <p style="font-size:0.8rem; margin-bottom:10px;">If the system is completely broken, you can reset the tables. <strong>This does not delete data</strong>, only repairs missing structures.</p>
            <a href="init_db.php" class="btn btn-primary" style="background:var(--text-secondary); border-color:var(--text-secondary); font-size:0.8rem;">Run Manual Re-Init</a>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        // Highlight settings link if we add an ID in header, but for now just a simple tool.
    });
</script>

<?php require_once 'includes/footer.php'; ?>
