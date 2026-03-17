<?php 
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php'; 

// Fetch recent labels for the sidebar
$recentLabels = [];
try {
    $stmt = $pdo_labels->query("SELECT * FROM items ORDER BY created_at DESC LIMIT 5");
    $recentLabels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<div class="panel">
    <h1>🏷️ Add to Warehouse</h1>
    <p>Enter specifications to record hardware and generate a physical `.odt` label.</p>
</div>

<div class="sidebar-layout">
    
    <!-- MAIN FORM COLUMN -->
    <div class="panel" style="position: relative;">
        <form id="newLabelForm">
            <?php 
                $formType = 'add';
                include 'includes/hardware_form.php'; 
            ?>

            <!-- Full Width Action -->
            <div style="margin-top: 20px; text-align: right; border-top: 1px solid var(--border-color); padding-top: 20px;">
                <button type="submit" class="btn btn-success" id="submitLabelBtn" style="font-size: 1.1rem; padding: 12px 24px; font-weight: bold;">
                    ➕ Save & Print Label
                </button>
            </div>
        </form>

        <!-- SUCCESS OVERLAY (Hidden by default) -->
        <div id="successOverlay" style="display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.95); border-radius: var(--border-radius); z-index:100; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:40px;">
            <div style="background:var(--accent-color); color:white; width:80px; height:80px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:40px; margin-bottom:20px;">✓</div>
            <h2 style="color:var(--text-main);">Label Generated!</h2>
            <p id="successMsg" style="color:var(--text-secondary); margin-bottom:30px;"></p>
            
            <div style="display:flex; gap:15px; flex-wrap:wrap; justify-content:center;">
                <button id="btnAgain" class="btn btn-primary" style="padding:12px 20px;">🔄 Open a New Copy</button>
                <button id="btnReset" class="btn btn-success" style="padding:12px 20px;">✨ Add New Hardware</button>
                <a href="labels.php" class="btn" style="padding:12px 20px; background:var(--bg-page); border:1px solid var(--border-color); color:var(--text-main);">📦 Go to Inventory</a>
            </div>
        </div>
    </div>

    <!-- RECENT LABELS SIDEBAR -->
    <div class="panel" style="background: var(--bg-page); border: 2px dashed var(--border-color);">
        <h3 style="margin-bottom: 15px; font-size: 1rem; color: var(--text-secondary);">Recently Added</h3>
        <?php if (empty($recentLabels)): ?>
            <p style="font-size: 0.85rem; color: var(--text-secondary); font-style: italic;">No recent work found.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($recentLabels as $rl): ?>
                    <div class="recent-card clone-trigger" 
                         style="padding: 10px; background: var(--bg-panel); border-radius: 6px; font-size: 0.85rem; border: 1px solid var(--border-color); cursor: pointer;"
                         data-brand="<?= htmlspecialchars($rl['brand'] ?? '') ?>"
                         data-model="<?= htmlspecialchars($rl['model'] ?? '') ?>"
                         data-series="<?= htmlspecialchars($rl['series'] ?? '') ?>"
                         data-cpu-gen="<?= htmlspecialchars($rl['cpu_gen'] ?? '') ?>"
                         data-cpu-specs="<?= htmlspecialchars($rl['cpu_specs'] ?? '') ?>"
                         data-cpu-cores="<?= htmlspecialchars($rl['cpu_cores'] ?? '') ?>"
                         data-cpu-speed="<?= htmlspecialchars($rl['cpu_speed'] ?? '') ?>"
                         data-ram="<?= htmlspecialchars($rl['ram'] ?? '') ?>"
                         data-storage="<?= htmlspecialchars($rl['storage'] ?? '') ?>"
                         title="Click to clone these specs">
                        <div style="font-weight: bold;"><?= htmlspecialchars($rl['brand'] . ' ' . $rl['model']) ?></div>
                        <div style="color: var(--text-secondary); font-size: 0.75rem;">
                            <?= htmlspecialchars($rl['cpu_gen']) ?> | <?= htmlspecialchars($rl['ram']) ?>
                        </div>
                        <div style="margin-top: 5px;">
                            <span style="font-size: 0.7rem; color: var(--accent-color);">ID: #<?= str_pad($rl['id'], 5, '0', STR_PAD_LEFT) ?></span>
                            <span style="float:right; font-size:0.7rem; color:var(--text-secondary);">📋 Clone</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Add the dynamic JS controls -->
<script src="assets/js/forms.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-new-label').classList.add('active');
    });
</script>

<?php require_once 'includes/footer.php'; ?>
