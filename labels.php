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
    // Log the error for debugging purposes (e.g., to a file or a monitoring service)
    error_log("Database error in labels.php: " . $e->getMessage());
    // Display a user-friendly message or redirect to an error page
    // For now, we'll just ensure $inventory remains empty
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
            <option value="Sold">🚚 Sold Records</option>
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
                                    $linkDesc = $item['description'] ?? '';
                                    $linkColorClass = '';
                                    if ($linkDesc === 'Refurbished') {
                                        $linkColorClass = 'text-accent';
                                    }
                                ?>
                                <a href="hardware_view.php?id=<?= (int)$item['id'] ?>" class="font-bold text-lg no-underline <?= $linkColorClass ?>">
                                    <?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>
                                </a>
                                <div class="text-sm text-secondary"><?= htmlspecialchars($item['series'] ?? '') ?></div>
                            </td>

                            <td data-label="CPU" class="text-sm">
                                <?= htmlspecialchars($item['cpu_gen'] ?? '—') ?>
                            </td>

                            <td data-label="RAM/HDD" class="text-sm">
                                <?= htmlspecialchars($item['ram'] ?? 'None') ?> /
                                <?= htmlspecialchars($item['storage'] ?? 'None') ?>
                            </td>

                            <td data-label="Location">
                                <span class="font-bold text-main"><?= htmlspecialchars($item['warehouse_location'] ?? 'Unassigned') ?></span>
                            </td>

                            <td data-label="Status">
                                <?php
                                    $desc  = $item['description'] ?? 'Untested';
                                    $statusClass = '';
                                    if ($desc === 'For Parts') {
                                        $statusClass = 'status-for-parts';
                                    } elseif ($desc === 'Refurbished') {
                                        $statusClass = 'status-refurbished';
                                    } else {
                                        $statusClass = 'status-untested';
                                    }
                                ?>
                                <span class="status-badge <?= $statusClass ?>">
                                    <?= htmlspecialchars($desc) ?>
                                </span>
                            </td>

                            <td data-label="Added" class="text-xs text-secondary">
                                <?= format_date($item['created_at']) ?>
                            </td>

                            <td class="whitespace-nowrap">
                                <div class="action-strip">
                                    <button class="btn reprint-btn" 
                                            data-id="<?= (int)$item['id'] ?>" 
                                            title="Reprint Label">
                                        🖨️ Print
                                    </button>
                                    <button class="btn open-label-btn" 
                                            data-id="<?= (int)$item['id'] ?>" 
                                            data-brand="<?= htmlspecialchars($item['brand'] ?? '') ?>"
                                            data-model="<?= htmlspecialchars($item['model'] ?? '') ?>"
                                            title="Open Folder/File">
                                        📂 Open
                                    </button>
                                    <button class="btn edit-btn"
                                            data-id="<?= (int)$item['id'] ?>">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger delete-btn"
                                            data-id="<?= (int)$item['id'] ?>"
                                            data-label="<?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>">
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

<!-- New CSS for improved styling -->
<style>
    /* Utility classes for spacing */
    .mb-spacing { margin-bottom: var(--spacing); }
    .mb-15 { margin-bottom: 15px; }

    /* Filter controls layout */
    .filter-controls {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }

    .filter-search-input {
        flex: 1;
        min-width: 240px;
    }

    .btn-secondary-outline {
        background: var(--bg-page);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        padding: 10px 14px;
    }

    .filter-message {
        margin-top: 10px;
        font-size: 0.85rem;
        color: var(--text-secondary);
        min-height: 18px;
    }

    /* Table styling */
    .data-table .text-center {
        text-align: center;
    }

    .empty-table-message {
        padding: 30px;
        font-style: italic;
        color: var(--text-secondary);
    }

    .empty-table-message .btn-link {
        color: var(--accent-color);
        text-decoration: underline;
    }

    /* Text utilities */
    .font-bold { font-weight: bold; }
    .text-lg { font-size: 1.1rem; }
    .text-sm { font-size: 0.9rem; }
    .text-xs { font-size: 0.85rem; }
    .text-secondary { color: var(--text-secondary); }
    .text-main { color: var(--text-main); }
    .text-accent { color: var(--accent-color); }
    .no-underline { text-decoration: none; }
    .whitespace-nowrap { white-space: nowrap; }

    /* Status badges */
    .status-badge {
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #fff; /* Default text color for badges */
    }

    .status-for-parts {
        background: #ef4444; /* Red */
    }

    .status-refurbished {
        background: var(--accent-color); /* Accent color */
    }

    .status-untested {
        background: #f39c12; /* Orange */
    }
</style>


<?php require_once 'includes/footer.php'; ?>
