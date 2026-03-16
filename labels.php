<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Initial server-side load (no filter) — JS takes over on filter/search
$inventory = [];
try {
    $stmt = $pdo_labels->query("SELECT * FROM items ORDER BY created_at DESC LIMIT 200");
    $inventory = $stmt->fetchAll();
} catch (PDOException $e) {
    // Graceful fail
}
?>

<!-- Page Header + Filter Bar -->
<div class="panel" style="margin-bottom: var(--spacing);">
    <div class="flex-between" style="margin-bottom: 15px;">
        <div>
            <h1>📦 Labeled Inventory</h1>
            <p>Master list of hardware configurations. Reuse these for printing labels or building orders.</p>
        </div>
        <a href="new_label.php" class="btn btn-primary">➕ Create New Label Profile</a>
    </div>

    <!-- Filter Controls -->
    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <input type="text" id="filterSearch"
               placeholder="Search brand, model, series, location, ID…"
               style="flex: 1; min-width: 240px;">

        <button id="clearFilterBtn" class="btn"
                style="background:var(--bg-page);border:1px solid var(--border-color);color:var(--text-secondary);padding:10px 14px;">
            ✕ Clear
        </button>
    </div>

    <div id="filterMsg" style="margin-top: 10px; font-size: 0.85rem; color: var(--text-secondary); min-height: 18px;"></div>
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
                        <td colspan="9" class="text-center" style="padding: 30px; font-style: italic; color: var(--text-secondary);">
                            No items found. <a href="new_label.php">Print your first label →</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inventory as $item): ?>
                        <tr data-id="<?= (int)$item['id'] ?>">
                            <td data-label="Model">
                                <?php if (($item['description'] ?? '') === 'Refurbished'): ?>
                                    <a href="refurbished_view.php?id=<?= (int)$item['id'] ?>" style="color:var(--accent-color); text-decoration:none; font-weight:bold;">
                                        <?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>
                                    </a>
                                <?php else: ?>
                                    <strong style="font-size: 1.1rem;"><?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?></strong>
                                <?php endif; ?>
                                <div style="font-size:0.8rem;color:var(--text-secondary);"><?= htmlspecialchars($item['series'] ?? '') ?></div>
                            </td>

                            <td data-label="CPU" style="font-size:0.9rem;">
                                <?= htmlspecialchars($item['cpu_gen'] ?? '—') ?>
                            </td>

                            <td data-label="RAM/HDD" style="font-size:0.9rem;">
                                <?= htmlspecialchars($item['ram'] ?? 'None') ?> /
                                <?= htmlspecialchars($item['storage'] ?? 'None') ?>
                            </td>

                            <td data-label="Location">
                                <span style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($item['warehouse_location'] ?? 'Unassigned') ?></span>
                            </td>

                            <td data-label="Status">
                                <?php
                                    $desc  = $item['description'] ?? 'Untested';
                                    $color = $desc === 'For Parts' ? '#ef4444'
                                           : ($desc === 'Refurbished' ? 'var(--accent-color)' : '#f39c12');
                                ?>
                                <span style="background:<?= $color ?>;color:#fff;padding:2px 8px;border-radius:6px;font-size:0.75rem;font-weight:800;text-transform:uppercase;">
                                    <?= htmlspecialchars($desc) ?>
                                </span>
                            </td>

                            <td data-label="Added" style="font-size:0.85rem;color:var(--text-secondary);">
                                <?= format_date($item['created_at']) ?>
                            </td>

                            <td style="white-space:nowrap;">
                                <div style="display: flex; gap: 5px; justify-content: flex-end;">
                                    <button class="btn reprint-btn" 
                                            data-id="<?= (int)$item['id'] ?>" 
                                            title="Reprint Label"
                                            style="font-size:0.75rem; min-height: 38px; padding: 0 12px; background:var(--bg-page); border:1px solid var(--border-color); color:var(--text-main);">
                                        🖨️ Print
                                    </button>
                                    <button class="btn edit-btn"
                                            data-id="<?= (int)$item['id'] ?>"
                                            style="font-size:0.75rem; min-height: 38px; padding: 0 12px; background:var(--bg-page); border:1px solid var(--border-color); color:var(--text-main);">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger delete-btn"
                                            data-id="<?= (int)$item['id'] ?>"
                                            data-label="<?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>"
                                            style="font-size:0.75rem; min-height: 38px; padding: 0 12px;">
                                        🗑 Del
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="assets/js/labels.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-labels').classList.add('active');

        // Clear filter button
        document.getElementById('clearFilterBtn').addEventListener('click', () => {
            const search = document.getElementById('filterSearch');
            search.value = '';
            // Trigger the input event to run the filter logic (flexible widening)
            search.dispatchEvent(new Event('input'));
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
