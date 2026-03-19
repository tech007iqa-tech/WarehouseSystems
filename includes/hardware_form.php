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
require_once __DIR__ . '/hardware_mapping.php';

$item = $item ?? [];
$isEdit = ($formType ?? 'add') === 'edit';
$condition = $item[HW_FIELDS['DESCRIPTION']] ?? 'Untested';
?>

<div class="form-grid">
    <!-- SECTION 1: Hardware Identity -->
    <div>
        <h3 class="form-section-header">Hardware Identity</h3>
        
        <div class="form-group">
            <label for="<?= HW_FIELDS['BRAND'] ?>">Brand *</label>
            <select id="<?= HW_FIELDS['BRAND'] ?>" name="<?= HW_FIELDS['BRAND'] ?>" required>
                <option value="" disabled <?= empty($item[HW_FIELDS['BRAND']]) ? 'selected' : '' ?>>Select Brand...</option>
                <?php 
                $brands = ['HP', 'Dell', 'Lenovo', 'Apple', 'Asus', 'Acer', 'Microsoft', 'Other'];
                foreach ($brands as $b): 
                    $selected = ($item[HW_FIELDS['BRAND']] ?? '') === $b ? 'selected' : '';
                ?>
                    <option value="<?= $b ?>" <?= $selected ?>><?= $b ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="<?= HW_FIELDS['MODEL'] ?>">Main Model (e.g., EliteBook) *</label>
            <input type="text" id="<?= HW_FIELDS['MODEL'] ?>" name="<?= HW_FIELDS['MODEL'] ?>" required value="<?= htmlspecialchars($item[HW_FIELDS['MODEL']] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="<?= HW_FIELDS['SERIES'] ?>">Series (e.g., 840 G3)</label>
            <input type="text" id="<?= HW_FIELDS['SERIES'] ?>" name="<?= HW_FIELDS['SERIES'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['SERIES']] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="<?= HW_FIELDS['SERIAL_NUMBER'] ?>">Serial Number / Asset Tag</label>
            <input type="text" id="<?= HW_FIELDS['SERIAL_NUMBER'] ?>" name="<?= HW_FIELDS['SERIAL_NUMBER'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['SERIAL_NUMBER']] ?? '') ?>" placeholder="S/N: ...">
        </div>

        <div class="form-group" style="position: relative;">
            <label for="<?= HW_FIELDS['CPU_GEN'] ?>">CPU / Generation</label>
            <input type="text" id="<?= HW_FIELDS['CPU_GEN'] ?>" name="<?= HW_FIELDS['CPU_GEN'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['CPU_GEN']] ?? '') ?>" autocomplete="off">
            <div id="cpuSearchWrapper" class="search-suggestions" style="display:none; position: absolute; z-index: 1000; width: 100%; max-height: 250px; overflow-y: auto; background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;">
                <!-- JS will inject options here -->
            </div>
        </div>

        <div class="form-group">
            <label for="cpu_specs_main">Processor Specs</label>
            <div class="input-group-prefix" id="cpu_specs_group">
                <span id="cpu_prefix_display"><?= str_contains($item[HW_FIELDS['CPU_SPECS']] ?? '', '-') ? explode('-', $item[HW_FIELDS['CPU_SPECS']])[0] . '-' : '' ?></span>
                <input type="text" id="cpu_specs_main" value="<?= str_contains($item[HW_FIELDS['CPU_SPECS']] ?? '', '-') ? explode('-', $item[HW_FIELDS['CPU_SPECS']])[1] : ($item[HW_FIELDS['CPU_SPECS']] ?? '') ?>">
            </div>
            <!-- Hidden field for actual database submission -->
            <input type="hidden" id="<?= HW_FIELDS['CPU_SPECS'] ?>" name="<?= HW_FIELDS['CPU_SPECS'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['CPU_SPECS']] ?? '') ?>">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div class="form-group">
                <label for="<?= HW_FIELDS['CPU_CORES'] ?>">CPU Cores</label>
                <input type="text" id="<?= HW_FIELDS['CPU_CORES'] ?>" name="<?= HW_FIELDS['CPU_CORES'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['CPU_CORES']] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['CPU_SPEED'] ?>">CPU Speed</label>
                <input type="text" id="<?= HW_FIELDS['CPU_SPEED'] ?>" name="<?= HW_FIELDS['CPU_SPEED'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['CPU_SPEED']] ?? '') ?>">
            </div>
        </div>
    </div>

    <!-- SECTION 2: Internals & Inventory -->
    <div>
        <h3 class="form-section-header">Internals & Status</h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div class="form-group">
                <label for="<?= HW_FIELDS['RAM'] ?>">RAM Capacity</label>
                <input type="text" id="<?= HW_FIELDS['RAM'] ?>" name="<?= HW_FIELDS['RAM'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['RAM']] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['STORAGE'] ?>">Storage Capacity</label>
                <input type="text" id="<?= HW_FIELDS['STORAGE'] ?>" name="<?= HW_FIELDS['STORAGE'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['STORAGE']] ?? '') ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="<?= HW_FIELDS['DESCRIPTION'] ?>">Condition / Internal Note *</label>
            <select id="<?= HW_FIELDS['DESCRIPTION'] ?>" name="<?= HW_FIELDS['DESCRIPTION'] ?>" required>
                <option value="Untested"    <?= $condition === 'Untested'    ? 'selected' : '' ?>>Untested (Intake)</option>
                <option value="Refurbished" <?= $condition === 'Refurbished' ? 'selected' : '' ?>>Refurbished (Ready)</option>
                <option value="For Parts"   <?= $condition === 'For Parts'   ? 'selected' : '' ?>>For Parts (Defective)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="<?= HW_FIELDS['STATUS'] ?>">Status</label>
            <select id="<?= HW_FIELDS['STATUS'] ?>" name="<?= HW_FIELDS['STATUS'] ?>">
                <option value="In Warehouse" <?= (($item[HW_FIELDS['STATUS']] ?? 'In Warehouse') === 'In Warehouse') ? 'selected' : '' ?>>📦 In Warehouse (Active)</option>
                <option value="Grade A"      <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Grade A'      ? 'selected' : '' ?>>Grade A</option>
                <option value="Grade B"      <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Grade B'      ? 'selected' : '' ?>>Grade B</option>
                <option value="Grade C"      <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Grade C'      ? 'selected' : '' ?>>Grade C</option>
                <option value="Tested"       <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Tested'       ? 'selected' : '' ?>>Tested</option>
                <option value="No Post"      <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'No Post'      ? 'selected' : '' ?>>No Post</option>
                <option value="No Power"     <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'No Power'     ? 'selected' : '' ?>>No Power</option>
                <option value="Sold"         <?= ($item[HW_FIELDS['STATUS']] ?? '') === 'Sold'         ? 'selected' : '' ?>>🚚 Sold Records</option>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div class="form-group">
                <label for="<?= HW_FIELDS['BIOS_STATE'] ?>">BIOS Status</label>
                <select id="<?= HW_FIELDS['BIOS_STATE'] ?>" name="<?= HW_FIELDS['BIOS_STATE'] ?>">
                    <option value="Unknown"  <?= ($item[HW_FIELDS['BIOS_STATE']] ?? '') === 'Unknown'  ? 'selected' : '' ?>>Unknown</option>
                    <option value="Unlocked" <?= ($item[HW_FIELDS['BIOS_STATE']] ?? '') === 'Unlocked' ? 'selected' : '' ?>>Unlocked</option>
                    <option value="Locked"   <?= ($item[HW_FIELDS['BIOS_STATE']] ?? '') === 'Locked'   ? 'selected' : '' ?>>Locked</option>
                </select>
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['BATTERY'] ?>">Battery Mode</label>
                <select id="<?= HW_FIELDS['BATTERY'] ?>" name="<?= HW_FIELDS['BATTERY'] ?>">
                    <option value="1" <?= ($item[HW_FIELDS['BATTERY']] ?? 0) == 1 ? 'selected' : '' ?>>Included</option>
                    <option value="0" <?= ($item[HW_FIELDS['BATTERY']] ?? 0) == 0 ? 'selected' : '' ?>>N/A</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:5px;">
                <label for="<?= HW_FIELDS['LOCATION'] ?>" style="margin-bottom:0;">Warehouse Bin / Location</label>
                <?php if (!$isEdit): ?>
                <label for="pin_location" style="font-size:0.75rem; color:var(--text-secondary); cursor:pointer; display:flex; align-items:center; gap:4px;">
                    <input type="checkbox" id="pin_location" title="Keep this location after saving"> 📌 Pin
                </label>
                <?php endif; ?>
            </div>
            <input type="text" id="<?= HW_FIELDS['LOCATION'] ?>" name="<?= HW_FIELDS['LOCATION'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['LOCATION']] ?? '') ?>">
        </div>
    </div>

    <!-- SECTION 3: Deep Technical Specs (Conditionally Hidden via CSS/JS) -->
    <div id="technicalSpecsSection" style="grid-column: 1 / -1; margin-top: 10px; <?= $condition !== 'Refurbished' ? 'display:none;' : '' ?>">
        <h3 class="form-section-header" style="color: var(--accent-color);">Deep Technical Sheet</h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label for="<?= HW_FIELDS['GPU'] ?>">GPU (Video Card)</label>
                <input type="text" id="<?= HW_FIELDS['GPU'] ?>" name="<?= HW_FIELDS['GPU'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['GPU']] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['SCREEN_RES'] ?>">Resolution / Screen</label>
                <input type="text" id="<?= HW_FIELDS['SCREEN_RES'] ?>" name="<?= HW_FIELDS['SCREEN_RES'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['SCREEN_RES']] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['BATTERY_SPECS'] ?>">Health / Cycles</label>
                <input type="text" id="<?= HW_FIELDS['BATTERY_SPECS'] ?>" name="<?= HW_FIELDS['BATTERY_SPECS'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['BATTERY_SPECS']] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['WEBCAM'] ?>">Webcam Specs</label>
                <input type="text" id="<?= HW_FIELDS['WEBCAM'] ?>" name="<?= HW_FIELDS['WEBCAM'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['WEBCAM']] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['BACKLIT_KB'] ?>">Backlit KB?</label>
                <select name="<?= HW_FIELDS['BACKLIT_KB'] ?>" id="<?= HW_FIELDS['BACKLIT_KB'] ?>">
                    <option value="">— Select —</option>
                    <option value="Yes" <?= ($item[HW_FIELDS['BACKLIT_KB']] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
                    <option value="No"  <?= ($item[HW_FIELDS['BACKLIT_KB']] ?? '') === 'No'  ? 'selected' : '' ?>>No</option>
                </select>
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['OS_VERSION'] ?>">OS Version</label>
                <input type="text" id="<?= HW_FIELDS['OS_VERSION'] ?>" name="<?= HW_FIELDS['OS_VERSION'] ?>" value="<?= htmlspecialchars($item[HW_FIELDS['OS_VERSION']] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="<?= HW_FIELDS['COSMETIC_GRADE'] ?>">Cosmetic Grade</label>
                <select name="<?= HW_FIELDS['COSMETIC_GRADE'] ?>" id="<?= HW_FIELDS['COSMETIC_GRADE'] ?>">
                    <option value="">— Select —</option>
                    <option value="A" <?= ($item[HW_FIELDS['COSMETIC_GRADE']] ?? '') === 'A' ? 'selected' : '' ?>>Grade A (Mint)</option>
                    <option value="B" <?= ($item[HW_FIELDS['COSMETIC_GRADE']] ?? '') === 'B' ? 'selected' : '' ?>>Grade B (Clean)</option>
                    <option value="C" <?= ($item[HW_FIELDS['COSMETIC_GRADE']] ?? '') === 'C' ? 'selected' : '' ?>>Grade C (Wear)</option>
                </select>
            </div>
        </div>

        <div class="form-group" style="margin-top: 15px;">
            <label for="<?= HW_FIELDS['WORK_NOTES'] ?>">Repair / Technical Work Notes</label>
            <textarea id="<?= HW_FIELDS['WORK_NOTES'] ?>" name="<?= HW_FIELDS['WORK_NOTES'] ?>" rows="4" style="width: 100%; font-family: inherit;" placeholder="Document repairs, thermal paste, cleaning..."><?= htmlspecialchars($item[HW_FIELDS['WORK_NOTES']] ?? '') ?></textarea>
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

