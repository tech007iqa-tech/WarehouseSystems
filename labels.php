<?php
// labels.php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/hardware_mapping.php';
require_once 'includes/header.php';

// Initial server-side load (no filter) — JS takes over on filter/search
$inventory = [];
try {
    $stmt = $pdo_labels->query("SELECT * FROM items ORDER BY created_at DESC LIMIT 200");
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error in labels.php: " . $e->getMessage());
}
?>

<!-- Page Header + Filter Bar -->
<div class="panel mb-spacing">
    <div class="flex-between mb-15">
        <div>
            <h1>📦 Labeled Inventory</h1>
            <p>Master list of hardware configurations. Reuse these for printing labels or building orders.</p>
        </div>
        <a href="new_label.php" class="btn btn-primary">➕ Create New Label Profile</a>
    </div>

    <!-- Filter Controls -->
    <div class="filter-controls">
        <input type="text" id="filterSearch"
               placeholder="Search brand, model, series, location, S/N, ID…"
               class="filter-search-input">

        <select id="filterStatus" class="filter-select" style="padding:10px; border-radius:8px; border:1px solid var(--border-color); color:var(--text-main);">
            <option value="In Warehouse">📦 In Warehouse</option>
            <option value="all">🌐 View All</option>
        </select>

        <button id="clearFilterBtn" class="btn btn-secondary-outline">
            ✕ Clear
        </button>
    </div>

    <div id="filterMsg" class="filter-message"></div>
</div>

<!-- Inventory Table -->
<div class="panel">
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Brand &amp; Model</th>
                    <th>CPU</th>
                    <th>RAM / Storage</th>
                    <th>Location</th>
                    <th>Condition</th>
                    <th>Added</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="inventoryTableBody">
                <?php if (empty($inventory)): ?>
                    <tr>
                        <td colspan="9" class="text-center empty-table-message">
                            No items found. <a href="new_label.php" class="btn btn-link">Print your first label →</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inventory as $item): ?>
                        <tr data-id="<?= (int)$item['id'] ?>">
                            <td data-label="Model">
                                <?php
                                    $linkDesc = $item[HW_FIELDS['DESCRIPTION']] ?? '';
                                    $linkColorClass = ($linkDesc === 'Refurbished') ? 'text-accent' : '';
                                ?>
                                <a href="hardware_view.php?id=<?= (int)$item['id'] ?>" class="tpl-link font-bold text-lg no-underline <?= $linkColorClass ?>">
                                    <?= htmlspecialchars($item[HW_FIELDS['BRAND']] . ' ' . $item[HW_FIELDS['MODEL']]) ?>
                                </a>
                                <div class="tpl-series text-sm text-secondary"><?= htmlspecialchars($item[HW_FIELDS['SERIES']] ?? '') ?></div>
                                <div class="tpl-sn text-xs" style="margin-top:4px; font-family:monospace; color:var(--text-secondary);">
                                    <?= ($item[HW_FIELDS['SERIAL_NUMBER']]) ? 'S/N: ' . htmlspecialchars($item[HW_FIELDS['SERIAL_NUMBER']]) : '<span style="opacity:0.5;">No Serial</span>' ?>
                                </div>
                            </td>

                            <td data-label="CPU" class="text-sm">
                                <div class="tpl-cpu-gen"><?= htmlspecialchars($item[HW_FIELDS['CPU_GEN']] ?? '—') ?></div>
                                <div class="tpl-cpu-specs text-xs text-secondary"><?= htmlspecialchars($item[HW_FIELDS['CPU_SPECS']] ?? '') ?></div>
                            </td>

                            <td data-label="RAM/HDD" class="text-sm">
                                <span class="tpl-ram"><?= htmlspecialchars($item[HW_FIELDS['RAM']] ?? 'None') ?></span> /
                                <span class="tpl-storage"><?= htmlspecialchars($item[HW_FIELDS['STORAGE']] ?? 'None') ?></span>
                            </td>

                            <td data-label="Location">
                                <div class="tpl-location-box">
                                    <span class="tpl-location font-bold text-main"><?= htmlspecialchars($item[HW_FIELDS['LOCATION']] ?? 'Unassigned') ?></span>
                                </div>
                            </td>

                            <td data-label="Status">
                                <div class="tpl-status-box">
                                    <?php
                                        $desc  = $item[HW_FIELDS['DESCRIPTION']] ?? 'Untested';
                                        $statusClass = '';
                                        if ($desc === 'For Parts') $statusClass = 'status-for-parts';
                                        elseif ($desc === 'Refurbished') $statusClass = 'status-refurbished';
                                        else $statusClass = 'status-untested';
                                    ?>
                                    <span class="tpl-badge status-badge <?= $statusClass ?>">
                                        <?= htmlspecialchars($desc) ?>
                                    </span>
                                </div>
                            </td>

                            <td data-label="Added" class="tpl-added text-xs text-secondary">
                                <?= format_date($item['created_at']) ?>
                            </td>

                            <td class="whitespace-nowrap">
                                <div class="action-strip">
                                    <button class="btn reprint-btn" data-id="<?= (int)$item['id'] ?>" title="Reprint Label">🖨️ Print</button>
                                    <button class="btn open-label-btn" 
                                            data-id="<?= (int)$item['id'] ?>" 
                                            data-brand="<?= htmlspecialchars($item[HW_FIELDS['BRAND']] ?? '') ?>"
                                            data-model="<?= htmlspecialchars($item[HW_FIELDS['MODEL']] ?? '') ?>"
                                            title="Open Folder/File">📂 Open</button>
                                    <button class="btn edit-btn" data-id="<?= (int)$item['id'] ?>">✏️ Edit</button>
                                    <button class="btn btn-danger delete-btn" 
                                            data-id="<?= (int)$item['id'] ?>"
                                            data-label="<?= htmlspecialchars($item[HW_FIELDS['BRAND']] . ' ' . $item[HW_FIELDS['MODEL']]) ?>">🗑 Del</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- TEMPLATES FOR DYNAMIC UI -->
<!-- Template A: Display Row (Used by buildRow in labels.js) -->
<template id="inventoryRowTemplate">
    <tr data-id="">
        <td data-label="Model">
            <a href="#" class="tpl-link font-bold text-lg no-underline text-main">BRAND MODEL</a>
            <div class="tpl-series text-sm text-secondary">SERIES</div>
            <div class="tpl-sn text-xs" style="margin-top:4px; font-family:monospace; color:var(--text-secondary);">S/N</div>
        </td>
        <td data-label="CPU" class="text-sm">
            <div class="tpl-cpu-gen">GEN</div>
            <div class="tpl-cpu-specs text-xs text-secondary">SPECS</div>
        </td>
        <td data-label="RAM/HDD" class="text-sm">
            <span class="tpl-ram">RAM</span> / <span class="tpl-storage">STORAGE</span>
        </td>
        <td data-label="Location">
            <div class="tpl-location-box">
                <span class="tpl-location font-bold text-main">LOCATION</span>
            </div>
        </td>
        <td data-label="Status">
            <div class="tpl-status-box">
                <span class="tpl-badge status-badge">CONDITION</span>
            </div>
        </td>
        <td data-label="Added" class="tpl-added text-xs text-secondary">DATE</td>
        <td class="whitespace-nowrap">
            <div class="action-strip">
                <button class="btn reprint-btn" data-id="" title="Reprint Label">🖨️ Print</button>
                <button class="btn open-label-btn" data-id="" data-brand="" data-model="" title="Open Folder/File">📂 Open</button>
                <button class="btn edit-btn" data-id="">✏️ Edit</button>
                <button class="btn btn-danger delete-btn" data-id="" data-label="">🗑 Del</button>
            </div>
        </td>
    </tr>
</template>

<!-- Template B: Inline Edit Row (Used by openEditRow in labels.js) -->
<template id="editRowTemplate">
    <tr class="edit-mode-row">
        <td style="vertical-align:top;" class="tpl-edit-cell-main">
            <input type="hidden" name="id">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['BRAND'] ?>" placeholder="Brand" style="width:90px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['MODEL'] ?>" placeholder="Model" style="width:100px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['SERIES'] ?>" placeholder="Series" style="width:85px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['SERIAL_NUMBER'] ?>" placeholder="Serial S/N" style="width:85px;padding:6px;font-family:monospace;font-size:0.75rem;">
        </td>
        <td style="vertical-align:top;">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['CPU_GEN'] ?>" placeholder="Gen" style="width:110px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['CPU_SPECS'] ?>" placeholder="Specs" style="width:110px;margin-bottom:4px;padding:6px;">
            <div style="display:flex;gap:4px;">
                <input type="text" class="edit-field" name="<?= HW_FIELDS['CPU_CORES'] ?>" placeholder="Cores" style="width:53px;padding:6px;font-size:0.75rem;">
                <input type="text" class="edit-field" name="<?= HW_FIELDS['CPU_SPEED'] ?>" placeholder="Speed" style="width:53px;padding:6px;font-size:0.75rem;">
            </div>
        </td>
        <td style="vertical-align:top;">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['RAM'] ?>" placeholder="RAM" style="width:65px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['STORAGE'] ?>" placeholder="Storage" style="width:100px;padding:6px;">
        </td>
        <td style="vertical-align:top;">
            <input type="text" class="edit-field" name="<?= HW_FIELDS['LOCATION'] ?>" placeholder="Location" style="width:95px;padding:6px;">
        </td>
        <td style="vertical-align:top;">
            <select class="edit-field" name="<?= HW_FIELDS['DESCRIPTION'] ?>" style="padding:6px;width:110px;">
                <option value="Untested">Untested</option>
                <option value="Refurbished">Refurbished</option>
                <option value="For Parts">For Parts</option>
            </select>
        </td>
        <td class="tpl-edit-added text-xs text-secondary" style="vertical-align:top; padding-top:12px;">DATE</td>
        <td class="whitespace-nowrap" style="vertical-align:top;">
            <div style="display:flex; flex-direction:column; gap:6px;">
                <button class="btn btn-success save-edit-btn" data-id="" style="font-size:0.75rem;padding:5px 10px;">💾 Save</button>
                <button class="btn cancel-edit-btn" style="font-size:0.75rem;padding:5px 10px;background:var(--bg-page);border:1px solid var(--border-color);color:var(--text-main);">✕ Cancel</button>
            </div>
        </td>
    </tr>
</template>

<script src="assets/js/labels.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-labels').classList.add('active');

        // Clear filter button
        document.getElementById('clearFilterBtn').addEventListener('click', () => {
            const search = document.getElementById('filterSearch');
            search.value = '';
            search.dispatchEvent(new Event('input'));
        });
    });
</script>

<style>
    .mb-spacing { margin-bottom: var(--spacing); }
    .mb-15 { margin-bottom: 15px; }
    .filter-controls { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .filter-search-input { flex: 1; min-width: 240px; }
    .btn-secondary-outline { background: var(--bg-page); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 10px 14px; }
    .filter-message { margin-top: 10px; font-size: 0.85rem; color: var(--text-secondary); min-height: 18px; }
    .data-table .text-center { text-align: center; }
    .empty-table-message { padding: 30px; font-style: italic; color: var(--text-secondary); }
    .empty-table-message .btn-link { color: var(--accent-color); text-decoration: underline; }
    .font-bold { font-weight: bold; }
    .text-lg { font-size: 1.1rem; }
    .text-sm { font-size: 0.9rem; }
    .text-xs { font-size: 0.85rem; }
    .text-secondary { color: var(--text-secondary); }
    .text-main { color: var(--text-main); }
    .text-accent { color: var(--accent-color); }
    .no-underline { text-decoration: none; }
    .whitespace-nowrap { white-space: nowrap; }
    .status-badge { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #fff; display: inline-block; }
    .status-for-parts { background: #ef4444; }
    .status-refurbished { background: var(--accent-color); }
    .status-untested { background: #f39c12; }
    .edit-mode-row { background: var(--bg-surface-2) !important; }
</style>

<?php require_once 'includes/footer.php'; ?>

