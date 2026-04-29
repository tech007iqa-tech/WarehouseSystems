<?php
/**
 * Ad Generator Module - The "Manifest Script"
 * Cross-references Warehouse stock with Marketing templates.
 */

// 1. Fetch template names first to use as a filter
$templateModels = $marketingDb->query("SELECT model_name FROM model_templates")->fetchAll(PDO::FETCH_COLUMN);

// 2. Fetch available models from Labels DB that MATCH templates
$modelsInStock = [];
if ($labelsDb && !empty($templateModels)) {
    // We create a placeholder string (?,?,?) for the IN clause
    $placeholders = implode(',', array_fill(0, count($templateModels), '?'));
    $stmt = $labelsDb->prepare("
        SELECT brand, model, COUNT(*) as qty 
        FROM items 
        WHERE status = 'In Warehouse' 
        AND model IN ($placeholders)
        GROUP BY brand, model 
        ORDER BY qty DESC
    ");
    $stmt->execute($templateModels);
    $modelsInStock = $stmt->fetchAll();
}

// 2. Handle Ad Generation Logic
$selectedModel = $_GET['model'] ?? null;
$tone = $_GET['tone'] ?? 'manifest'; // manifest, urgency, social
$generatedAd = null;
$matchingTemplate = null;

if ($selectedModel) {
    // Find template in Marketing DB
    $stmt = $marketingDb->prepare("SELECT * FROM model_templates WHERE model_name = ?");
    $stmt->execute([$selectedModel]);
    $matchingTemplate = $stmt->fetch();

    if ($matchingTemplate) {
        // Calculate QTY again for the specific ad
        $qty = 0;
        foreach($modelsInStock as $m) {
            if ($m['model'] === $selectedModel) {
                $qty = $m['qty'];
                break;
            }
        }

        // TONE-BASED GENERATION
        if ($tone === 'urgency') {
            $generatedAd = "⚡ FLASH SALE: {$qty}x " . strtoupper($matchingTemplate['model_name']) . " ⚡\n\n";
            $generatedAd .= "We need to clear space! " . $qty . " units ready for IMMEDIATE palletized shipping.\n\n";
            $generatedAd .= "🔥 KEY SPECS:\n" . $matchingTemplate['base_specs'] . "\n\n";
            $generatedAd .= "FIRST COME, FIRST SERVED. Reply now for special bulk pricing. 📉";
        } elseif ($tone === 'social') {
            $generatedAd = "✨ Looking for quality " . $matchingTemplate['category'] . "s in bulk? ✨\n\n";
            $generatedAd .= "The " . $matchingTemplate['model_name'] . " is back in stock (" . $qty . " units available).\n\n";
            $generatedAd .= $matchingTemplate['marketing_copy'] . "\n\n";
            $generatedAd .= "#RefurbishedTech #IQA #BulkInventory #ITAD";
        } else {
            // Standard Manifest
            $generatedAd = "🔥 INVENTORY ALERT: " . strtoupper($matchingTemplate['model_name']) . " 🔥\n\n";
            $generatedAd .= "We have just processed a batch of " . $qty . " units, now ready for immediate fulfillment!\n\n";
            $generatedAd .= "📍 SPECIFICATIONS:\n" . $matchingTemplate['base_specs'] . "\n\n";
            $generatedAd .= "📝 OVERVIEW:\n" . $matchingTemplate['marketing_copy'] . "\n\n";
            $generatedAd .= "DM for pricing and bulk manifest.";
        }
        
        log_marketing_audit($marketingDb, 'AdGenerator', $selectedModel, 'GENERATED', "Generated $tone ad for $selectedModel (Qty: $qty)");
    }
}
?>

<header class="page-header">
    <h1>Inventory-to-Ad Generator</h1>
    <p>Convert real-time warehouse stock into high-performance marketing copy.</p>
</header>

<div class="dashboard-grid">
    <!-- STOCK SELECTOR -->
    <section class="card">
        <h2>1. Select Stock from Warehouse</h2>
        <div class="stock-list">
            <?php if (empty($modelsInStock)): ?>
                <p style="color: var(--text-dim);">No stock found in Labels database.</p>
            <?php else: ?>
                <div class="stock-grid">
                    <?php foreach ($modelsInStock as $stock): ?>
                        <a href="?page=ad_generator&model=<?php echo urlencode($stock['model']); ?>" 
                           class="stock-item <?php echo ($selectedModel === $stock['model']) ? 'active' : ''; ?>">
                            <div class="stock-qty"><?php echo $stock['qty']; ?> Units</div>
                            <div class="stock-name"><?php echo htmlspecialchars($stock['brand'] . ' ' . $stock['model']); ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- AD PREVIEW -->
    <section class="card" style="grid-column: span 2;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2>2. Generated Ad Manifest</h2>
            <?php if ($selectedModel): ?>
            <div class="tone-selector">
                <a href="?page=ad_generator&model=<?php echo urlencode($selectedModel); ?>&tone=manifest" class="btn-small <?php echo $tone==='manifest'?'active':''; ?>">Standard</a>
                <a href="?page=ad_generator&model=<?php echo urlencode($selectedModel); ?>&tone=urgency" class="btn-small <?php echo $tone==='urgency'?'active':''; ?>">Urgent</a>
                <a href="?page=ad_generator&model=<?php echo urlencode($selectedModel); ?>&tone=social" class="btn-small <?php echo $tone==='social'?'active':''; ?>">Social</a>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($selectedModel && !$matchingTemplate): ?>
            <div class="alert alert-danger">
                No marketing template found for <strong><?php echo htmlspecialchars($selectedModel); ?></strong>. 
                <a href="?page=model_templates&prefill_model=<?php echo urlencode($selectedModel); ?>" style="color: white; text-decoration: underline;">Create one here</a> to generate an ad.
            </div>
        <?php elseif ($generatedAd): ?>
            <div class="ad-preview-container">
                <div style="display: grid; grid-template-columns: 1fr 200px; gap: 1.5rem;">
                    <textarea id="adOutput" readonly><?php echo htmlspecialchars($generatedAd); ?></textarea>
                    
                    <!-- PHOTO BANK INTEGRATION -->
                    <div class="photo-preview-sidebar">
                        <h4 style="font-size: 0.7rem; text-transform: uppercase; margin-bottom: 10px;">Asset Verification</h4>
                        <?php
                        $photoDir = "assets/img/models/" . str_replace(' ', '_', $selectedModel) . "/";
                        $assets = ['pallet.jpg', 'unit.jpg', 'bios.jpg'];
                        foreach($assets as $img):
                            $exists = file_exists($photoDir . $img);
                        ?>
                            <div class="asset-thumb <?php echo $exists ? 'exists' : ''; ?>">
                                <?php if ($exists): ?>
                                    <img src="<?php echo $photoDir . $img; ?>" alt="<?php echo $img; ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <span>No <?php echo str_replace('.jpg', '', $img); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="ad-actions">
                    <button onclick="copyAdToClipboard()" class="btn-action">📋 Copy to Clipboard</button>
                    <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 1rem;">
                        This ad is optimized for <?php echo strtoupper($tone); ?> outreach.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 4rem; color: var(--text-dim);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">⚡</div>
                <p>Select a model from the left to generate its marketing manifest.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<script>
function copyAdToClipboard() {
    const copyText = document.getElementById("adOutput");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    
    const btn = document.querySelector('.btn-action');
    const originalText = btn.innerHTML;
    btn.innerHTML = "✅ Copied!";
    btn.style.background = "var(--accent-blue)";
    
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.style.background = "";
    }, 2000);
}
</script>

<style>
.stock-grid {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.stock-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 0.75rem;
    text-decoration: none;
    color: var(--text-main);
    transition: all 0.2s;
}
.stock-item:hover {
    border-color: var(--accent-primary);
    background: var(--accent-tertiary);
}
.stock-item.active {
    border-color: var(--accent-primary);
    background: var(--accent-tertiary);
}
.stock-qty {
    font-weight: 800;
    font-size: 0.7rem;
    background: var(--accent-primary);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 2rem;
}
.stock-name {
    font-weight: 700;
    font-size: 0.9rem;
}
.ad-preview-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
#adOutput {
    width: 100%;
    height: 400px;
    background: #f8fafc;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 1.5rem;
    color: var(--text-main);
    font-family: 'Inter', sans-serif;
    font-size: 0.95rem;
    line-height: 1.6;
    resize: none;
    outline: none;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
}
.tone-selector {
    display: flex;
    gap: 0.5rem;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 10px;
}
.tone-selector .btn-small {
    padding: 6px 12px;
    border-radius: 8px;
    background: transparent;
    color: var(--text-dim);
    border: none;
}
.tone-selector .btn-small.active {
    background: white;
    color: var(--accent-primary);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.photo-preview-sidebar {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.asset-thumb {
    height: 120px;
    background: #f1f5f9;
    border: 2px dashed #cbd5e1;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.65rem;
    color: #94a3b8;
    text-transform: uppercase;
    font-weight: 800;
}
.asset-thumb.exists {
    border: 2px solid var(--accent-primary);
    background: white;
}
</style>
