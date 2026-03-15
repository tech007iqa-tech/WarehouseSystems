<?php 
require_once 'includes/header.php'; 
?>

<div class="panel">
    <h1>🏷️ Print Hardware Label</h1>
    <p>Enter the specifications to accurately record this unit into the warehouse and generate its physical `.odt` label.</p>
</div>

<div class="panel">
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

            <div class="form-group">
                <label for="cpu_gen">CPU / Generation</label>
                <input type="text" id="cpu_gen" name="cpu_gen" placeholder="i5 8th Gen">
            </div>

            <div class="form-group">
                <label for="cpu_details">CPU Cores / Speed</label>
                <input type="text" id="cpu_details" name="cpu_details" placeholder="2 Cores @ 2.40GHz">
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
                    <input type="checkbox" id="battery" name="battery" value="1"> Battery Included & functional?
                </label>
            </div>

            <div class="form-group">
                <label for="bios_state">BIOS Status</label>
                <select id="bios_state" name="bios_state">
                    <option value="Unlocked" selected>Unlocked</option>
                    <option value="Locked">Locked (Computrace/Admin)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="description">Condition / Internal Note</label>
                <select id="description" name="description">
                    <option value="Untested">Untested</option>
                    <option value="Refurbished">Refurbished / Good</option>
                    <option value="For Parts">For Parts / Repair</option>
                </select>
            </div>

            <div class="form-group">
                <label for="warehouse_location">Warehouse Bin / Shelf Location</label>
                <input type="text" id="warehouse_location" name="warehouse_location" placeholder="e.g., Shelf A2">
            </div>

        </div>

        <!-- Full Width Action -->
        <div style="grid-column: 1 / -1; margin-top: 20px; text-align: right; border-top: 1px solid var(--border-color); padding-top: 20px;">
            <button type="submit" class="btn btn-success" id="submitLabelBtn" style="font-size: 1.1rem; padding: 12px 24px;">
                ➕ Save to Warehouse & Generate Label (.odt)
            </button>
        </div>
    </form>
</div>

<!-- Add the dynamic JS controls -->
<script src="assets/js/forms.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-new-label').classList.add('active');
    });
</script>

<?php require_once 'includes/footer.php'; ?>
