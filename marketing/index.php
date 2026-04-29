<?php
require_once 'config.php';
require_once INCLUDES_PATH . '/db.php';

$marketingDb = get_marketing_db();
$labelsDb = get_labels_db();
$crmDb = get_master_crm_db();

// Fetch real stats from Master CRM
$leadCount = $crmDb->query("SELECT COUNT(*) FROM customers WHERE account_status = 'Lead'")->fetchColumn() ?: 0;
$campaignCount = $marketingDb->query("SELECT COUNT(*) FROM campaigns WHERE status = 'Active'")->fetchColumn() ?: 0;
$photoCount = $marketingDb->query("SELECT COUNT(*) FROM photos")->fetchColumn() ?: 0;

// Fetch inventory for summary (items with qty > 10)
if ($labelsDb) {
    $inventoryCount = $labelsDb->query("SELECT COUNT(DISTINCT model) FROM items WHERE status = 'In Warehouse'")->fetchColumn() ?: 0;
}

include_once INCLUDES_PATH . '/header.php';

// Simple Router
$page = $_GET['page'] ?? 'dashboard';
$module_path = MODULES_PATH . '/' . $page . '/index.php';

echo '<main id="main-content">';

if ($page === 'dashboard') {
    // Load Dashboard Stats (inline for now as the default view)
    ?>
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

        <section class="card photo-summary">
            <h2>Photo Assets</h2>
            <div class="stat"><?php echo $photoCount; ?> In Bucket</div>
        </section>

        <!-- SMART OPPORTUNITIES (IDEAS ENGINE) -->
        <section class="card smart-opportunities">
            <h2 style="color: var(--accent-primary);">💡 Smart Opportunities</h2>
            <div class="opportunities-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <?php
                // Logic to find "Ideas"
                $opportunities = [];
                
                if ($labelsDb) {
                    // Find High Volume Stock with Specs and Location
                    $topStock = $labelsDb->query("SELECT brand, model, cpu_gen, ram, storage, warehouse_location, COUNT(*) as qty FROM items WHERE status = 'In Warehouse' GROUP BY brand, model HAVING qty > 0 ORDER BY qty DESC LIMIT 5")->fetchAll();
                    
                    foreach ($topStock as $item) {
                        $specString = "({$item['cpu_gen']} | {$item['ram']} | {$item['storage']})";
                        $location = !empty($item['warehouse_location']) ? $item['warehouse_location'] : 'UNSPECIFIED';
                        
                        // Check for Template
                        $stmt = $marketingDb->prepare("SELECT id, model_name FROM model_templates WHERE model_name = ?");
                        $stmt->execute([$item['model']]);
                        $template = $stmt->fetch();
                        
                        if (!$template) {
                            // AUTO-GENERATE CONTENT IDEAS
                            $prefill_specs = "CPU: {$item['cpu_gen']}\nRAM: {$item['ram']}\nSTORAGE: {$item['storage']}\nOS: Windows 10/11 Pro Ready";
                            
                            $isWorkstation = (stripos($item['model'], 'Precision') !== false || stripos($item['model'], 'ZBook') !== false);
                            $isBusiness = (stripos($item['model'], 'Latitude') !== false || stripos($item['model'], 'EliteBook') !== false);
                            
                            $pitch = "Looking for reliable bulk inventory? This {$item['model']} is a powerhouse designed for " . ($isWorkstation ? "heavy-duty engineering and design work." : "efficient business workflows.") . "\n\n✅ Fully Tested & Audited\n✅ Bulk Quantities Available\n✅ Grade A Warehouse Stock";

                            $opportunities[] = [
                                'type' => 'NEED_TEMPLATE',
                                'title' => 'Missing Content',
                                'desc' => "You have <strong>{$item['qty']}x {$item['model']}</strong> at <span class='badge-customer' style='font-size:0.7rem; padding: 2px 6px;'>📍 {$location}</span> <br><span style='font-size:0.8rem; color:var(--text-secondary);'>{$specString}</span>",
                                'action' => '?page=model_templates&prefill_model=' . urlencode($item['model']) . '&prefill_specs=' . urlencode($prefill_specs) . '&prefill_copy=' . urlencode($pitch),
                                'btn' => 'Create Template'
                            ];
                        } else {
                            // Check for Photos
                            $photoStmt = $marketingDb->prepare("SELECT COUNT(*) FROM photos WHERE model_name = ?");
                            $photoStmt->execute([$item['model']]);
                            $hasPhoto = $photoStmt->fetchColumn() > 0;

                            if (!$hasPhoto) {
                                $opportunities[] = [
                                    'type' => 'NEED_PHOTO',
                                    'title' => '📸 Photo Needed',
                                    'desc' => "Template exists for <strong>{$item['model']}</strong>, but no photos are in the bucket. Photos increase conversion!",
                                    'action' => '?page=photo_bucket',
                                    'btn' => 'Upload Photo'
                                ];
                            } else {
                                $opportunities[] = [
                                    'type' => 'READY',
                                    'title' => '🚀 Ready to Blast',
                                    'desc' => "Content and Photos are READY for <strong>{$item['model']}</strong>. Generate an ad now!",
                                    'action' => '?page=ad_generator&model=' . urlencode($item['model']),
                                    'btn' => 'Generate Ad'
                                ];
                            }
                        }
                    }
                }

                if (empty($opportunities)):
                ?>
                    <p style="color: var(--text-dim);">No immediate opportunities found. Keep scanning inventory!</p>
                <?php else: ?>
                    <?php foreach ($opportunities as $opp): ?>
                        <div class="opp-card" style="background: white; padding: 1.25rem; border-radius: 12px; border: 1px solid var(--border-color); display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <h3 style="font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.5rem; color: var(--accent-primary);"><?php echo $opp['title']; ?></h3>
                                <p style="font-size: 0.95rem; margin-bottom: 1.25rem; line-height: 1.4;"><?php echo $opp['desc']; ?></p>
                            </div>
                            <a href="<?php echo $opp['action']; ?>" class="btn-small" style="text-align: center; background: var(--accent-primary); color: white; border: none;"><?php echo $opp['btn']; ?></a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- QUICK ACTIONS HUB -->
        <section class="card quick-actions">
            <h2>⚡ Quick Actions</h2>
            <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem;">
                <a href="?page=leads&action=add" class="btn-action" style="text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px;">➕ Add New Lead</a>
                <a href="?page=ad_generator" class="btn-action" style="background: var(--accent-primary); text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px;">📢 Create Ad</a>
                <a href="?page=photo_bucket" class="btn-action" style="background: var(--accent-gradient); text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px;">🖼️ Photo Bucket</a>
                <a href="?page=model_templates" class="btn-action" style="background: var(--text-main); text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px;">📚 Update Specs</a>
            </div>
        </section>

        <!-- RECENT ACTIVITY FEED -->
        <section class="card activity-feed">
            <h2>🕒 Recent Marketing Activity</h2>
            <div class="activity-list" style="margin-top: 1rem;">
                <?php
                try {
                    $logs = $marketingDb->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 6")->fetchAll();
                    if (empty($logs)):
                ?>
                    <p style="color: var(--text-dim); text-align: center; padding: 2rem;">No recent activity recorded.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($logs as $log): 
                            $icon = '📝';
                            if ($log['action'] === 'CREATED') $icon = '✨';
                            if ($log['action'] === 'SYNCED') $icon = '🔄';
                            if ($log['action'] === 'GENERATED') $icon = '🔥';
                        ?>
                            <div style="display: flex; gap: 1rem; align-items: flex-start; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
                                <div style="font-size: 1.2rem;"><?php echo $icon; ?></div>
                                <div>
                                    <div style="font-weight: 700; font-size: 0.9rem;"><?php echo htmlspecialchars($log['summary']); ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px;">
                                        <?php echo $log['entity_type']; ?> • <?php echo date('M j, g:i a', strtotime($log['timestamp'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; 
                } catch (Exception $e) {
                    echo "<p>Activity feed temporarily unavailable.</p>";
                }
                ?>
            </div>
        </section>
    </div>
    <?php
} elseif (file_exists($module_path)) {
    include_once $module_path;
} else {
    echo '<section class="card"><h2>404</h2><p>Module "' . htmlspecialchars($page) . '" not found.</p></section>';
}

echo '</main>';

include_once INCLUDES_PATH . '/footer.php';
?>
