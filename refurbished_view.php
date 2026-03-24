<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = null;

if ($id > 0) {
    try {
        $stmt = $pdo_labels->prepare("SELECT * FROM items WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();
    } catch (PDOException $e) {
        // Error handling
    }
}

// Redirect if not found or not refurbished
if (!$item || ($item['description'] ?? '') !== 'Refurbished') {
    echo "<div class='panel'><h1>404 Not Found</h1><p>This item is not marked as Refurbished or does not exist.</p><a href='labels.php' class='btn btn-primary'>Back to Inventory</a></div>";
    require_once 'includes/footer.php';
    exit;
}
?>

<div class="panel flex-between" style="margin-bottom: var(--spacing);">
    <div>
        <h1 style="color: var(--btn-success-bg);">🛠️ Refurbished Technical Sheet</h1>
        <p>Detailed specifications for <strong><?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?></strong></p>
    </div>
    <a href="labels.php" class="btn" style="background:var(--bg-page); border:1px solid var(--border-color); color:var(--text-secondary);">← Back to Inventory</a>
</div>

<!-- Main Content Grid -->
<form id="refurbForm">
    <div style="display: grid; grid-template-columns: 1fr 350px; gap: var(--spacing); align-items: start;">

        <!-- LEFT: Technical Specs & Baseline Form -->
        <div class="panel">
            <h3 style="margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                Detailed Hardware Profile
            </h3>

            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

            <!-- SECTION 1: Baseline Hardware (Previously Read-Only) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px; background: rgba(0,0,0,0.02); padding: 15px; border-radius: 8px;">
                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" name="brand" value="<?= htmlspecialchars($item['brand']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="model" value="<?= htmlspecialchars($item['model']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Series</label>
                    <input type="text" name="series" value="<?= htmlspecialchars($item['series'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>CPU Gen/Model</label>
                    <input type="text" name="cpu_gen" value="<?= htmlspecialchars($item['cpu_gen'] ?? '') ?>" placeholder="e.g. i5-8th Gen">
                </div>
                <div class="form-group">
                    <label>RAM</label>
                    <input type="text" name="ram" value="<?= htmlspecialchars($item['ram'] ?? '') ?>" placeholder="e.g. 16GB DDR4">
                </div>
                <div class="form-group">
                    <label>Storage</label>
                    <input type="text" name="storage" value="<?= htmlspecialchars($item['storage'] ?? '') ?>" placeholder="e.g. 512GB NVMe">
                </div>
            </div>

            <!-- SECTION 2: Advanced Technical Details -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label>GPU (Dedicated/Integrated)</label>
                    <input type="text" name="gpu" value="<?= htmlspecialchars($item['gpu'] ?? '') ?>" placeholder="e.g. NVIDIA RTX 3050, Intel Iris Xe...">
                </div>
                <div class="form-group">
                    <label>Screen Resolution / Screen Size</label>
                    <input type="text" name="screen_res" value="<?= htmlspecialchars($item['screen_res'] ?? '') ?>" placeholder="e.g. 1920x1080 (FHD)...">
                </div>
                <div class="form-group">
                    <label>Battery Status</label>
                    <select name="battery">
                        <option value="1" <?= ($item['battery'] ?? 0) == 1 ? 'selected' : '' ?>>Battery Included</option>
                        <option value="0" <?= ($item['battery'] ?? 0) == 0 ? 'selected' : '' ?>>No Battery / Dead</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Battery Health / cycles</label>
                    <input type="text" name="battery_specs" value="<?= htmlspecialchars($item['battery_specs'] ?? '') ?>" placeholder="e.g. 85% Health, 120 Cycles...">
                </div>
                <div class="form-group">
                    <label>WebCam</label>
                    <input type="text" name="webcam" value="<?= htmlspecialchars($item['webcam'] ?? '') ?>" placeholder="e.g. 720p HD, No Cam...">
                </div>
                <div class="form-group">
                    <label>Backlit Keyboard</label>
                    <select name="backlit_kb">
                        <option value="">— Select —</option>
                        <option value="Yes" <?= ($item['backlit_kb'] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
                        <option value="No"  <?= ($item['backlit_kb'] ?? '') === 'No'  ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>OS Version</label>
                    <input type="text" name="os_version" value="<?= htmlspecialchars($item['os_version'] ?? '') ?>" placeholder="e.g. Win 11 Pro...">
                </div>
                <div class="form-group">
                    <label>Cosmetic Grade</label>
                    <select name="cosmetic_grade">
                        <option value="">— Select —</option>
                        <option value="A" <?= ($item['cosmetic_grade'] ?? '') === 'A' ? 'selected' : '' ?>>Grade A (Mint)</option>
                        <option value="B" <?= ($item['cosmetic_grade'] ?? '') === 'B' ? 'selected' : '' ?>>Grade B (Standard)</option>
                        <option value="C" <?= ($item['cosmetic_grade'] ?? '') === 'C' ? 'selected' : '' ?>>Grade C (Heavy Wear)</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label>Technical / Work Notes</label>
                <textarea name="work_notes" rows="5" placeholder="Document any repairs, upgrades, or specific findings here..." style="width: 100%; font-family: inherit;"><?= htmlspecialchars($item['work_notes'] ?? '') ?></textarea>
            </div>

            <hr style="border:0; border-top:1px solid var(--border-color); margin: 25px 0;">

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <div id="saveStatus" style="margin-right: auto; line-height: 40px; font-size: 0.9rem;"></div>
                <button type="button" id="saveRefurbBtn" class="btn btn-success" style="padding: 10px 30px; font-weight: bold;">
                    💾 Update Hardware Profile
                </button>
            </div>

            <!-- Pass along non-technical fields -->
            <input type="hidden" name="cpu_details" value="<?= htmlspecialchars($item['cpu_details'] ?? '') ?>">
            <input type="hidden" name="bios_state" value="<?= htmlspecialchars($item['bios_state'] ?? '') ?>">
            <input type="hidden" name="description" value="Refurbished">
            <input type="hidden" name="warehouse_location" value="<?= htmlspecialchars($item['warehouse_location'] ?? '') ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($item['status'] ?? 'In Warehouse') ?>">
        </div>

        <!-- RIGHT: Summary Sidebar (Context) -->
        <div class="panel" style="background: var(--bg-page); border: 2px dashed var(--border-color);">
            <h3 style="margin-bottom: 15px; font-size: 1rem; color: var(--text-secondary);">Inventory Info</h3>
            <div style="font-size: 0.9rem; line-height: 1.8;">
                <div class="flex-between"><span>Added Date:</span> <strong><?= format_date($item['created_at']) ?></strong></div>
                <div class="flex-between"><span>Current Status:</span> <strong><?= htmlspecialchars($item['status'] ?? '—') ?></strong></div>
                <div class="flex-between"><span>Warehouse Loc:</span> <strong><?= htmlspecialchars($item['warehouse_location'] ?? '—') ?></strong></div>
            </div>

            <div style="margin-top: 25px; padding: 15px; background: rgba(140, 198, 63, 0.1); border-radius: 8px; color: var(--accent-hover); font-size: 0.85rem;">
                💡 <strong>Sales Note:</strong> The technical sheets are the primary source for buyers. Keeping CPU, GPU and Battery health updated here will push accurate data to the Purchase Order.
            </div>
        </div>

    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const refurbForm    = document.getElementById('refurbForm');
    const saveBtn       = document.getElementById('saveRefurbBtn');
    const statusMsg     = document.getElementById('saveStatus');

    saveBtn.addEventListener('click', () => {
        saveBtn.disabled = true;
        saveBtn.textContent = '⏳ Saving...';
        statusMsg.textContent = '';
        statusMsg.style.color = 'var(--text-secondary)';

        const formData = new FormData(refurbForm);

        fetch('api/edit_label.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                statusMsg.textContent = '✅ Technical sheet updated successfully!';
                statusMsg.style.color = 'var(--btn-success-bg)';
                setTimeout(() => { statusMsg.textContent = ''; }, 3000);
            } else {
                statusMsg.textContent = '❌ Error: ' + (json.error || 'Unknown error');
                statusMsg.style.color = 'var(--btn-danger-bg)';
            }
        })
        .catch(err => {
            statusMsg.textContent = '❌ Network error.';
            statusMsg.style.color = 'var(--btn-danger-bg)';
        })
        .finally(() => {
            saveBtn.disabled = false;
            saveBtn.textContent = '💾 Update Technical Sheet';
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
