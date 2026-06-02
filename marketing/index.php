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
$inventoryCount = 0;
if ($labelsDb) {
    $inventoryCount = $labelsDb->query("SELECT COUNT(DISTINCT model) FROM items WHERE status = 'In Warehouse'")->fetchColumn() ?: 0;
}

include_once INCLUDES_PATH . '/header.php';

// Simple Router
$page = $_GET['page'] ?? 'dashboard';
$module_path = MODULES_PATH . '/' . $page . '/index.php';

echo '<main id="main-content">';

if ($page === 'dashboard') {
    ?>
    <header class="page-header">
        <h1>Welcome to <?php echo APP_NAME; ?></h1>
        <p>Your modular marketing command center.</p>
    </header>

    <div class="dashboard-grid">
        <?php
        echo UI::stat_card("Lead Statistics", "$leadCount Leads Tracked", "lead-summary");
        echo UI::stat_card("Active Campaigns", "$campaignCount Active", "campaign-summary");
        echo UI::stat_card("Marketable Stock", "$inventoryCount Models in Bulk", "inventory-summary");
        echo UI::stat_card("Photo Assets", "$photoCount In Bucket", "photo-summary");
        ?>

        <!-- SMART OPPORTUNITIES (IDEAS ENGINE) -->
        <section class="card smart-opportunities">
            <h2 style="color: var(--accent-primary);">💡 Smart Opportunities</h2>
            <div class="opportunities-grid">
                <?php
                $opportunities = [];
                if ($labelsDb) {
                    $topStock = $labelsDb->query("SELECT brand, model, cpu_gen, ram, storage, warehouse_location, COUNT(*) as qty FROM items WHERE status = 'In Warehouse' GROUP BY brand, model HAVING qty > 0 ORDER BY qty DESC LIMIT 5")->fetchAll();

                    foreach ($topStock as $item) {
                        $specString = "({$item['cpu_gen']} | {$item['ram']} | {$item['storage']})";
                        $location = !empty($item['warehouse_location']) ? $item['warehouse_location'] : 'UNSPECIFIED';

                        $stmt = $marketingDb->prepare("SELECT id, model_name FROM model_templates WHERE model_name = ?");
                        $stmt->execute([$item['model']]);
                        $template = $stmt->fetch();

                        if (!$template) {
                            $prefill_specs = "CPU: {$item['cpu_gen']}\nRAM: {$item['ram']}\nSTORAGE: {$item['storage']}\nOS: Windows 10/11 Pro Ready";
                            $isWorkstation = (stripos($item['model'], 'Precision') !== false || stripos($item['model'], 'ZBook') !== false);
                            $pitch = "Looking for reliable bulk inventory? This {$item['model']} is a powerhouse designed for " . ($isWorkstation ? "heavy-duty engineering and design work." : "efficient business workflows.") . "\n\n✅ Fully Tested & Audited\n✅ Bulk Quantities Available\n✅ Grade A Warehouse Stock";

                            $opportunities[] = [
                                'type' => 'NEED_TEMPLATE',
                                'title' => 'Missing Content',
                                'desc' => "You have <strong>{$item['qty']}x {$item['model']}</strong> at " . UI::badge("📍 $location", "customer") . " <br><span style='font-size:0.8rem; color:var(--text-secondary);'>{$specString}</span>",
                                'action' => '?page=model_templates&prefill_model=' . urlencode($item['model']) . '&prefill_specs=' . urlencode($prefill_specs) . '&prefill_copy=' . urlencode($pitch),
                                'btn' => 'Create Template'
                            ];
                        } else {
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

                if (empty($opportunities)) {
                    echo "<p style='color: var(--text-dim);'>No immediate opportunities found. Keep scanning inventory!</p>";
                } else {
                    foreach ($opportunities as $opp) {
                        echo UI::opportunity_card($opp['title'], $opp['desc'], $opp['action'], $opp['btn'], $opp['type']);
                    }
                }
                ?>
            </div>
        </section>

        <!-- QUICK ACTIONS HUB -->
        <section class="card quick-actions">
            <h2>⚡ Quick Actions</h2>
            <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem;">
                <?php
                echo UI::action_button("Add New Lead", "?page=leads&action=add", "➕");
                echo UI::action_button("Create Ad", "?page=ad_generator", "📢", "background: var(--accent-primary);");
                echo UI::action_button("Photo Bucket", "?page=photo_bucket", "🖼️", "background: var(--accent-gradient);");
                echo UI::action_button("Update Specs", "?page=model_templates", "📚", "background: var(--text-main);");
                ?>
            </div>
        </section>

        <!-- RECENT ACTIVITY FEED -->
        <section class="card activity-feed">
            <h2>🕒 Recent Marketing Activity</h2>
            <div class="activity-list" style="margin-top: 1rem;">
                <?php
                try {
                    $logs = $marketingDb->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 6")->fetchAll();
                    if (empty($logs)) {
                        echo "<p style='color: var(--text-dim); text-align: center; padding: 2rem;'>No recent activity recorded.</p>";
                    } else {
                        echo "<div style='display: flex; flex-direction: column; gap: 1rem;'>";
                        foreach ($logs as $log) {
                            $icon = '📝';
                            if ($log['action'] === 'CREATED') $icon = '✨';
                            if ($log['action'] === 'SYNCED') $icon = '🔄';
                            if ($log['action'] === 'GENERATED') $icon = '🔥';

                            echo UI::activity_item($icon, $log['summary'], $log['entity_type'] . " • " . date('M j, g:i a', strtotime($log['timestamp'])));
                        }
                        echo "</div>";
                    }
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
