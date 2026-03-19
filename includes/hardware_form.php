<?php
/**
 * includes/hardware_form.php
 * A unified, reusable hardware specification form.
 * 
 * Used by:
 *  - new_label.php (Add mode)
 *  - refurbished_view.php (Edit mode)
 * 
 * Variables expected:
 *  - $item: (Array|null) Existing item data.
 *  - $formType: (String) 'add' or 'edit'.
 */
$item = $item ?? [];
$isEdit = ($formType ?? 'add') === 'edit';
$condition = $item['description'] ?? 'Untested';
?>

<div class="form-grid">
    <!-- SECTION 1: Hardware Identity -->
    <div>
        <h3 class="form-section-header">Hardware Identity</h3>
        
        <div class="form-group">
            <label for="brand">Brand *</label>
            <select id="brand" name="brand" required>
                <option value="" disabled <?= empty($item['brand']) ? 'selected' : '' ?>>Select Brand...</option>
                <?php 
                $brands = ['HP', 'Dell', 'Lenovo', 'Apple', 'Asus', 'Acer', 'Microsoft', 'Other'];
                foreach ($brands as $b): 
                    $selected = ($item['brand'] ?? '') === $b ? 'selected' : '';
                ?>
                    <option value="<?= $b ?>" <?= $selected ?>><?= $b ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="model">Main Model (e.g., EliteBook) *</label>
            <input type="text" id="model" name="model" required value="<?= htmlspecialchars($item['model'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="series">Series (e.g., 840 G3)</label>
            <input type="text" id="series" name="series" value="<?= htmlspecialchars($item['series'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="serial_number">Serial Number / Asset Tag</label>
            <input type="text" id="serial_number" name="serial_number" value="<?= htmlspecialchars($item['serial_number'] ?? '') ?>" placeholder="S/N: ...">
        </div>

        <div class="form-group" style="position: relative;">
            <label for="cpu_gen">CPU / Generation</label>
            <input type="text" id="cpu_gen" name="cpu_gen" value="<?= htmlspecialchars($item['cpu_gen'] ?? '') ?>" autocomplete="off">
            <div id="cpuSearchWrapper" class="search-suggestions" style="display:none; position: absolute; z-index: 1000; width: 100%; max-height: 250px; overflow-y: auto; background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;">
                <!-- JS will inject options here -->
            </div>
        </div>

        <div class="form-group">
            <label for="cpu_specs_main">Processor Specs</label>
            <div class="input-group-prefix" id="cpu_specs_group">
                <span id="cpu_prefix_display"><?= str_contains($item['cpu_specs'] ?? '', '-') ? explode('-', $item['cpu_specs'])[0] . '-' : '' ?></span>
                <input type="text" id="cpu_specs_main" value="<?= str_contains($item['cpu_specs'] ?? '', '-') ? explode('-', $item['cpu_specs'])[1] : ($item['cpu_specs'] ?? '') ?>">
            </div>
            <!-- Hidden field for actual database submission -->
            <input type="hidden" id="cpu_specs" name="cpu_specs" value="<?= htmlspecialchars($item['cpu_specs'] ?? '') ?>">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div class="form-group">
                <label for="cpu_cores">CPU Cores</label>
                <input type="text" id="cpu_cores" name="cpu_cores" value="<?= htmlspecialchars($item['cpu_cores'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="cpu_speed">CPU Speed</label>
                <input type="text" id="cpu_speed" name="cpu_speed" value="<?= htmlspecialchars($item['cpu_speed'] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- SECTION 2: Internals & Inventory -->
    <div>
        <h3 class="form-section-header">Internals & Status</h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div class="form-group">
                <label for="ram">RAM Capacity</label>
                <input type="text" id="ram" name="ram" value="<?= htmlspecialchars($item['ram'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="storage">Storage Capacity</label>
                <input type="text" id="storage" name="storage" value="<?= htmlspecialchars($item['storage'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="description">Condition / Internal Note *</label>
            <select id="description" name="description" required>
                <option value="Untested"    <?= $condition === 'Untested'    ? 'selected' : '' ?>>Untested (Intake)</option>
                <option value="Refurbished" <?= $condition === 'Refurbished' ? 'selected' : '' ?>>Refurbished (Ready)</option>
                <option value="For Parts"   <?= $condition === 'For Parts'   ? 'selected' : '' ?>>For Parts (Defective)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="In Warehouse" <?= (($item['status'] ?? 'In Warehouse') === 'In Warehouse') ? 'selected' : '' ?>>📦 In Warehouse (Active)</option>
                <option value="Grade A"      <?= ($item['status'] ?? '') === 'Grade A'      ? 'selected' : '' ?>>Grade A</option>
                <option value="Grade B"      <?= ($item['status'] ?? '') === 'Grade B'      ? 'selected' : '' ?>>Grade B</option>
                <option value="Grade C"      <?= ($item['status'] ?? '') === 'Grade C'      ? 'selected' : '' ?>>Grade C</option>
                <option value="Tested"       <?= ($item['status'] ?? '') === 'Tested'       ? 'selected' : '' ?>>Tested</option>
                <option value="No Post"      <?= ($item['status'] ?? '') === 'No Post'      ? 'selected' : '' ?>>No Post</option>
                <option value="No Power"     <?= ($item['status'] ?? '') === 'No Power'     ? 'selected' : '' ?>>No Power</option>
                <option value="Sold"         <?= ($item['status'] ?? '') === 'Sold'         ? 'selected' : '' ?>>🚚 Sold Records</option>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div class="form-group">
                <label for="bios_state">BIOS Status</label>
                <select id="bios_state" name="bios_state">
                    <option value="Unknown"  <?= ($item['bios_state'] ?? '') === 'Unknown'  ? 'selected' : '' ?>>Unknown</option>
                    <option value="Unlocked" <?= ($item['bios_state'] ?? '') === 'Unlocked' ? 'selected' : '' ?>>Unlocked</option>
                    <option value="Locked"   <?= ($item['bios_state'] ?? '') === 'Locked'   ? 'selected' : '' ?>>Locked</option>
                </select>
            </div>
            <div class="form-group">
                <label for="battery">Battery Mode</label>
                <select id="battery" name="battery">
                    <option value="1" <?= ($item['battery'] ?? 0) == 1 ? 'selected' : '' ?>>Included</option>
                    <option value="0" <?= ($item['battery'] ?? 0) == 0 ? 'selected' : '' ?>>N/A</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:5px;">
                <label for="warehouse_location" style="margin-bottom:0;">Warehouse Bin / Location</label>
                <?php if (!$isEdit): ?>
                <label for="pin_location" style="font-size:0.75rem; color:var(--text-secondary); cursor:pointer; display:flex; align-items:center; gap:4px;">
                    <input type="checkbox" id="pin_location" title="Keep this location after saving"> 📌 Pin
                </label>
                <?php endif; ?>
            </div>
            <input type="text" id="warehouse_location" name="warehouse_location" value="<?= htmlspecialchars($item['warehouse_location'] ?? '') ?>">
        </div>
    </div>

    <!-- SECTION 3: Deep Technical Specs (Conditionally Hidden via CSS/JS) -->
    <div id="technicalSpecsSection" style="grid-column: 1 / -1; margin-top: 10px; <?= $condition !== 'Refurbished' ? 'display:none;' : '' ?>">
        <h3 class="form-section-header" style="color: var(--accent-color);">Deep Technical Sheet</h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label for="gpu">GPU (Video Card)</label>
                <input type="text" id="gpu" name="gpu" value="<?= htmlspecialchars($item['gpu'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="screen_res">Resolution / Screen</label>
                <input type="text" id="screen_res" name="screen_res" value="<?= htmlspecialchars($item['screen_res'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="battery_specs">Health / Cycles</label>
                <input type="text" id="battery_specs" name="battery_specs" value="<?= htmlspecialchars($item['battery_specs'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="webcam">Webcam Specs</label>
                <input type="text" id="webcam" name="webcam" value="<?= htmlspecialchars($item['webcam'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="backlit_kb">Backlit KB?</label>
                <select name="backlit_kb" id="backlit_kb">
                    <option value="">— Select —</option>
                    <option value="Yes" <?= ($item['backlit_kb'] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
                    <option value="No"  <?= ($item['backlit_kb'] ?? '') === 'No'  ? 'selected' : '' ?>>No</option>
                </select>
            </div>
            <div class="form-group">
                <label for="os_version">OS Version</label>
                <input type="text" id="os_version" name="os_version" value="<?= htmlspecialchars($item['os_version'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="cosmetic_grade">Cosmetic Grade</label>
                <select name="cosmetic_grade" id="cosmetic_grade">
                    <option value="">— Select —</option>
                    <option value="A" <?= ($item['cosmetic_grade'] ?? '') === 'A' ? 'selected' : '' ?>>Grade A (Mint)</option>
                    <option value="B" <?= ($item['cosmetic_grade'] ?? '') === 'B' ? 'selected' : '' ?>>Grade B (Clean)</option>
                    <option value="C" <?= ($item['cosmetic_grade'] ?? '') === 'C' ? 'selected' : '' ?>>Grade C (Wear)</option>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-top: 15px;">
            <label for="work_notes">Repair / Technical Work Notes</label>
            <textarea id="work_notes" name="work_notes" rows="4" style="width: 100%; font-family: inherit;" placeholder="Document repairs, thermal paste, cleaning..."><?= htmlspecialchars($item['work_notes'] ?? '') ?></textarea>
        </div>
    </div>
</div>

<style>
.form-section-header {
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 8px;
    margin-bottom: 20px;
    font-size: 1.1rem;
    color: var(--text-main);
}
.search-suggestions {
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
}
</style>
