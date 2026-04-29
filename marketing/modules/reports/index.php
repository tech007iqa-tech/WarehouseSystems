<?php
/**
 * Reports Module - Strategic Insights & Performance Tracking
 */

// 1. Fetch Stats
$totalLeads = $marketingDb->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$newLeads = $marketingDb->query("SELECT COUNT(*) FROM leads WHERE status = 'New'")->fetchColumn();
$convertedLeads = $marketingDb->query("SELECT COUNT(*) FROM leads WHERE status = 'Customer' OR status = 'ACTIVE CUSTOMER'")->fetchColumn();

// 2. Fetch Warehouse Coverage
$totalWarehouseModels = 0;
$marketedModels = 0;
if ($labelsDb) {
    $totalWarehouseModels = $labelsDb->query("SELECT COUNT(DISTINCT model) FROM items WHERE status = 'In Warehouse'")->fetchColumn();
    
    $marketedModels = $marketingDb->query("SELECT COUNT(DISTINCT model_name) FROM model_templates")->fetchColumn();
}

// 3. Sync Health
$crmSynced = $marketingDb->query("SELECT COUNT(*) FROM leads WHERE customer_id IS NOT NULL")->fetchColumn();

// Calculate percentages
$funnelWidth = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100) : 0;
$coveragePct = $totalWarehouseModels > 0 ? round(($marketedModels / $totalWarehouseModels) * 100) : 0;
?>

<header class="page-header">
    <h1>Marketing Insights</h1>
    <p>Automated reporting on inventory coverage, lead conversion, and CRM synchronization.</p>
</header>

<div class="dashboard-grid">
    <!-- LEAD FUNNEL -->
    <section class="card">
        <h2>📈 Conversion Funnel</h2>
        <div style="margin-top: 1.5rem;">
            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem;">
                    <span>Leads-to-Customer Rate</span>
                    <strong><?php echo $funnelWidth; ?>%</strong>
                </div>
                <div style="height: 12px; background: #f1f5f9; border-radius: 6px; overflow: hidden;">
                    <div style="width: <?php echo $funnelWidth; ?>%; height: 100%; background: var(--accent-primary);"></div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="stat-box">
                    <div class="label">Total Leads</div>
                    <div class="stat" style="font-size: 1.5rem;"><?php echo $totalLeads; ?></div>
                </div>
                <div class="stat-box">
                    <div class="label">Converted</div>
                    <div class="stat" style="font-size: 1.5rem; color: var(--accent-primary);"><?php echo $convertedLeads; ?></div>
                </div>
            </div>
        </div>
    </section>

    <!-- INVENTORY COVERAGE -->
    <section class="card">
        <h2>📦 Warehouse Coverage</h2>
        <div style="margin-top: 1.5rem;">
            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.9rem;">
                    <span>Models in Marketing Library</span>
                    <strong><?php echo $coveragePct; ?>%</strong>
                </div>
                <div style="height: 12px; background: #f1f5f9; border-radius: 6px; overflow: hidden;">
                    <div style="width: <?php echo $coveragePct; ?>%; height: 100%; background: var(--accent-tertiary);"></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="stat-box">
                    <div class="label">Whse Models</div>
                    <div class="stat" style="font-size: 1.5rem;"><?php echo $totalWarehouseModels; ?></div>
                </div>
                <div class="stat-box">
                    <div class="label">Branded</div>
                    <div class="stat" style="font-size: 1.5rem; color: var(--accent-tertiary);"><?php echo $marketedModels; ?></div>
                </div>
            </div>
        </div>
    </section>

    <!-- CRM SYNC HEALTH -->
    <section class="card">
        <h2>🔄 CRM Sync Health</h2>
        <div style="text-align: center; padding: 1rem 0;">
            <div style="font-size: 3rem; font-weight: 800; color: var(--accent-primary);"><?php echo $crmSynced; ?> / <?php echo $totalLeads; ?></div>
            <p style="color: var(--text-secondary); font-size: 0.9rem;">Leads currently synced with Master CRM</p>
            
            <div style="margin-top: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 8px; font-size: 0.8rem; color: var(--text-dim);">
                <?php if ($crmSynced == $totalLeads): ?>
                    ✅ Your marketing database is 100% in sync with the Master CRM.
                <?php else: ?>
                    ⚠️ <?php echo ($totalLeads - $crmSynced); ?> leads are local-only. Use the "Sync CRM" button on the Leads page to push them.
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<style>
.stat-box {
    padding: 1rem;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid var(--border-color);
}
.stat-box .label {
    font-size: 0.7rem;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--text-dim);
    margin-bottom: 5px;
}
</style>
