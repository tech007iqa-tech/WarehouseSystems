<?php 
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php'; 

// Fetch current inventory from labels.sqlite
$inventory = [];
try {
    $stmt = $pdo_labels->query("
        SELECT * FROM items 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $inventory = $stmt->fetchAll();
} catch (PDOException $e) {
    // Graceful fail
}
?>

<div class="panel flex-between">
    <div>
        <h1>📦 Warehouse Tracking</h1>
        <p>A live view of all hardware cataloged via the Label Printer tool.</p>
    </div>
    <a href="new_label.php" class="btn btn-primary">➕ Print New Label</a>
</div>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Item ID</th>
                <th>Brand & Model</th>
                <th>Processor</th>
                <th>RAM / Storage</th>
                <th>Location</th>
                <th>Condition</th>
                <th>Status</th>
                <th>Added</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($inventory)): ?>
                <tr>
                    <td colspan="8" class="text-center" style="padding: 30px; font-style: italic; color: var(--text-secondary);">
                        No items found in the warehouse.<br>Try printing a label first!
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($inventory as $item): ?>
                    <tr style="<?= $item['status'] === 'Sold' ? 'opacity: 0.5;' : '' ?>">
                        <td style="font-weight: bold; color: var(--accent-color);">#<?= str_pad($item['id'], 5, '0', STR_PAD_LEFT) ?></td>
                        
                        <!-- Core info -->
                        <td>
                            <strong><?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?></strong>
                            <div style="font-size: 0.8rem; color: var(--text-secondary);"><?= htmlspecialchars($item['series'] ?? '') ?></div>
                        </td>
                        
                        <!-- Internals -->
                        <td style="font-size: 0.9rem;"><?= htmlspecialchars($item['cpu_gen'] ?? 'Unknown') ?></td>
                        
                        <td style="font-size: 0.9rem;">
                            <?= htmlspecialchars($item['ram'] ?? 'None') ?> / 
                            <?= htmlspecialchars($item['storage'] ?? 'None') ?>
                        </td>

                        <!-- Tracking details -->
                        <td><?= htmlspecialchars($item['warehouse_location'] ?? 'Unassigned') ?></td>
                        
                        <td>
                            <?php if ($item['description'] === 'For Parts'): ?>
                                <span style="background: var(--btn-danger-bg); color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">Parts</span>
                            <?php else: ?>
                                <?= htmlspecialchars($item['description'] ?? 'Untested') ?>
                            <?php endif; ?>
                        </td>
                        
                        <td><?= htmlspecialchars($item['status']) ?></td>
                        
                        <!-- Meta -->
                        <td style="font-size: 0.85rem; color: var(--text-secondary);">
                            <?= format_date($item['created_at']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-labels').classList.add('active');
    });
</script>

<?php require_once 'includes/footer.php'; ?>
