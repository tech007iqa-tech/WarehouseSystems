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
        <form id="newLabelForm" class="form-grid">
            <!-- Column 1: Core Specs -->
            <div>
                <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 15px;">Hardware Details</h3>
                
                <div class="form-group">
                    <label for="brand">Brand *</label>
                    <select id="brand" name="brand" required>
                        <option value="" disabled selected>Select Brand...</option>
                        <option value="HP">HP</option>
                        <option value="Dell">Dell</option>
                        <option value="Lenovo">Lenovo</option>
                        <option value="Apple">Apple</option>
                        <option value="Other">Other (Specify in Notes)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="model">Main Model (e.g., EliteBook) *</label>
                    <input type="text" id="model" name="model" required placeholder="EliteBook">
                </div>

                <div class="form-group">
                    <label for="series">Series (e.g., 840 G3)</label>
                    <input type="text" id="series" name="series" placeholder="840 G3">
                </div>

                <div class="form-group" style="position: relative;">
                    <label for="cpu_gen">CPU / Generation</label>
                    <input type="text" id="cpu_gen" name="cpu_gen" placeholder="Type to search (e.g. 12)..." autocomplete="off">
                    <div id="cpuSearchWrapper" class="search-suggestions" style="display:none; position: absolute; z-index: 1000; width: 100%; max-height: 250px; overflow-y: auto; background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;">
                        <!-- JS will inject options here -->
                    </div>
                </div>

                <div class="form-group">
                    <label for="cpu_specs">Processor Specs (e.g. i5-11850H)</label>
                    <input type="text" id="cpu_specs" name="cpu_specs" placeholder="i7-11850H">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group">
                        <label for="cpu_cores">CPU Cores</label>
                        <input type="text" id="cpu_cores" name="cpu_cores" placeholder="8 Cores">
                    </div>
                    <div class="form-group">
                        <label for="cpu_speed">CPU Speed</label>
                        <input type="text" id="cpu_speed" name="cpu_speed" placeholder="2.40GHz">
                    </div>
                </div>
            </div>

            <!-- Column 2: Memory & Status -->
            <div>
                <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 15px;">Internals & Status</h3>

                <div class="form-group form-flex">
                    <label>
                        <input type="checkbox" id="has_ram" name="has_ram" value="1"> Includes RAM?
                    </label>
                    <select id="ram" name="ram" disabled style="margin-top: 5px;">
                        <option value="" disabled selected>Select Capacity...</option>
                        <option value="4GB">4 GB</option>
                        <option value="8GB">8 GB</option>
                        <option value="16GB">16 GB</option>
                        <option value="32GB">32 GB</option>
                        <option value="64GB">64 GB</option>
                    </select>
                </div>

                <div class="form-group form-flex">
                    <label>
                        <input type="checkbox" id="has_storage" name="has_storage" value="1"> Includes Storage?
                    </label>
                    <input type="text" id="storage" name="storage" disabled placeholder="e.g., 256GB NVMe" style="margin-top: 5px;">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="battery" name="battery" value="1"> Battery Included?
                    </label>
                </div>

                <div class="form-group">
                    <label for="bios_state">BIOS Status</label>
                    <select id="bios_state" name="bios_state">
                        <option value="Unknown" selected>Unknown (Untested)</option>
                        <option value="Unlocked">Unlocked / Clean</option>
                        <option value="Locked">Locked (Computrace/Admin)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Condition / Internal Note *</label>
                    <select id="description" name="description" required>
                        <option value="Untested" selected>Untested</option>
                        <option value="Refurbished">Refurbished / Good</option>
                        <option value="For Parts">For Parts / Repair</option>
                    </select>
                </div>

                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:5px;">
                        <label for="warehouse_location" style="margin-bottom:0;">Warehouse Bin / Shelf Location</label>
                        <label style="font-size:0.75rem; color:var(--text-secondary); cursor:pointer; display:flex; align-items:center; gap:4px;">
                            <input type="checkbox" id="pin_location" title="Keep this location after saving"> 📌 Pin
                        </label>
                    </div>
                    <input type="text" id="warehouse_location" name="warehouse_location" placeholder="e.g., Shelf A2">
                </div>

            </div>

            <!-- Full Width Action -->
            <div style="grid-column: 1 / -1; margin-top: 20px; text-align: right; border-top: 1px solid var(--border-color); padding-top: 20px;">
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
                <button id="btnAgain" class="btn btn-primary" style="padding:12px 20px;">🔄 Print Another (Same Profile)</button>
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
                    <div style="padding: 10px; background: var(--bg-panel); border-radius: 6px; font-size: 0.85rem; border: 1px solid var(--border-color);">
                        <div style="font-weight: bold;"><?= htmlspecialchars($rl['brand'] . ' ' . $rl['model']) ?></div>
                        <div style="color: var(--text-secondary); font-size: 0.75rem;">
                            <?= htmlspecialchars($rl['cpu_gen']) ?> | <?= htmlspecialchars($rl['ram']) ?>
                        </div>
                        <div style="margin-top: 5px;">
                            <span style="font-size: 0.7rem; color: var(--accent-color);">ID: #<?= str_pad($rl['id'], 5, '0', STR_PAD_LEFT) ?></span>
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
