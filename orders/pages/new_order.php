<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Create connection to SQLite database
    $conn = Database::orders();

    if (!Database::isSchemaVerified('orders', 'items')) {
        // NEW! Ensure orders table exists for tracking multiple batches
        $conn->exec("CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id TEXT NOT NULL UNIQUE,
            customer_id TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Ensure items table supports grouping by order_id
        $conn->exec("CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id TEXT NOT NULL DEFAULT 'ORD-DEFAULT',
            customer_id TEXT NOT NULL,
            brand TEXT NOT NULL,
            model TEXT NOT NULL,
            series TEXT NOT NULL,
            description TEXT NOT NULL,
            quantity INTEGER NOT NULL,
            unit_price REAL DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Migration: Check if we need to add order_id, cpu, etc.
        $columns = $conn->query("PRAGMA table_info(items)")->fetchAll(PDO::FETCH_ASSOC);
        $has_order_id = false;
        $has_cpu = false;
        foreach($columns as $col) {
            if ($col['name'] === 'order_id') $has_order_id = true;
            if ($col['name'] === 'cpu') $has_cpu = true;
        }

        if (!$has_order_id) {
            $conn->exec("ALTER TABLE items ADD COLUMN order_id TEXT NOT NULL DEFAULT 'ORD-DEFAULT'");
        }
        if (!$has_cpu) {
            $conn->exec("ALTER TABLE items ADD COLUMN cpu TEXT DEFAULT ''");
        }

        Database::markSchemaVerified('orders', 'items');
    }

    // Handle Form Submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (!Security::validate($_POST['csrf_token'] ?? '')) {
            die("Security Error: CSRF Token Invalid.");
        }

        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $delete_id = $_POST['delete_id'] ?? 0;
            $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                $_SESSION['message'] = "<div class='alert success'>Item removed from order.</div>";
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'update_item') {
            $update_id = $_POST['update_id'] ?? 0;
            $qty = $_POST['update_qty'] ?? 1;
            $price = $_POST['update_price'] ?? 0.00;
            $brand = $_POST['update_brand'] ?? '';
            $model = $_POST['update_model'] ?? '';
            $series = $_POST['update_series'] ?? '';
            $cpu = $_POST['update_cpu'] ?? '';
            $desc = $_POST['update_desc'] ?? '';
            
            $stmt = $conn->prepare("UPDATE items SET brand=?, model=?, series=?, cpu=?, description=?, quantity=?, unit_price=? WHERE id=?");
            if ($stmt->execute([$brand, $model, $series, $cpu, $desc, (int)$qty, (float)$price, (int)$update_id])) {
                $_SESSION['message'] = "<div class='alert success'>Item details updated.</div>";
            }
        } else {
            // Standard Add Logic
            $customer_id = $_POST['customer_id'];
            $order_id = $_POST['order_id'];
            $brand = $_POST['brand'];
            $model = $_POST['model'];
            $series = $_POST['series'];
            $cpu = $_POST['cpu'] ?? '';
            $desc = $_POST['description'];
            $qty = (int)$_POST['quantity'];
            $price = (float)($_POST['unit_price'] ?? 0.00);

            $stmt = $conn->prepare("INSERT INTO items (order_id, customer_id, brand, model, series, cpu, description, quantity, unit_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$order_id, $customer_id, $brand, $model, $series, $cpu, $desc, $qty, $price])) {
                // Return to top of batch builder with success message
                header("Location: index.php?customer_id=" . urlencode($customer_id) . "&order_id=" . urlencode($order_id) . "&msg=added#batch-builder-top");
                exit();
            }
        }
    }
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}

$current_customer = $_GET['customer_id'] ?? null;
$current_order = $_GET['order_id'] ?? 'ORD-DEFAULT';

// Fetch current order items
$stmt = $conn->prepare("SELECT * FROM items WHERE order_id = ? AND customer_id = ? ORDER BY id DESC");
$stmt->execute([$current_order, $current_customer]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total units for this batch
$total_units = 0;
foreach($items as $item) $total_units += $item['quantity'];

$message = $_SESSION['message'] ?? "";
unset($_SESSION['message']);
?>

<div class="new-order-layout">
    <!-- Sidebar: Customer & Batch Info -->
    <aside class="order-sidebar">
        <div class="sidebar-card">
            <h2 id="batch-builder-top">Batch Builder</h2>
            <div class="batch-meta">
                <div class="meta-item">
                    <span class="label">Order ID:</span>
                    <span class="value"><?= htmlspecialchars($current_order) ?></span>
                </div>
                <div class="meta-item">
                    <span class="label">Total Units:</span>
                    <span class="value counter" id="sidebar-total-qty"><?= $total_units ?></span>
                </div>
            </div>
            
            <a href="checkout.php?customer_id=<?= urlencode($current_customer) ?>&order_id=<?= urlencode($current_order) ?>" class="btn-finalize">
                Finalize & Checkout →
            </a>
        </div>
    </aside>

    <!-- Main Content: Entry Form & Summary -->
    <main class="order-main">
        <?php echo $message; ?>

        <section class="entry-form-section card">
            <h3>Add Items to Batch</h3>
            <form method="POST" class="batch-form">
                <input type="hidden" name="customer_id" value="<?= htmlspecialchars($current_customer) ?>">
                <input type="hidden" name="order_id" value="<?= htmlspecialchars($current_order) ?>">
                <?= UI::csrf_field() ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Brand</label>
                        <input type="text" name="brand" list="brand-options" placeholder="Dell, HP..." required>
                    </div>
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="model" list="model-options" placeholder="Latitude 5400..." required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Series / Project</label>
                        <input type="text" name="series" placeholder="IQA-2024-001">
                    </div>
                    <div class="form-group">
                        <label>CPU / Gen</label>
                        <input type="text" name="cpu" list="cpu-options" placeholder="i5-8350U">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description / Condition</label>
                    <textarea name="description" placeholder="Used, No major defects..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" value="1" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Unit Price ($)</label>
                        <input type="number" name="unit_price" value="0.00" step="0.01">
                    </div>
                </div>

                <button type="submit" class="btn-add">Add to Batch</button>
            </form>
        </section>

        <section class="summary-section card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h3>Current Batch Summary</h3>
                <div class="search-box">
                    <input type="text" id="summary-search" placeholder="Filter items..." onkeyup="filterSummary()">
                </div>
            </div>

            <div class="summary-table-wrapper">
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Item Details</th>
                            <th style="text-align:center;">Qty</th>
                            <th style="text-align:right;">Price</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="summary-list">
                        <?php if (empty($items)): ?>
                            <tr class="empty-row">
                                <td colspan="4" style="text-align:center; padding: 40px; color: #94a3b8;">No items added yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr class="summary-row" data-search="<?= htmlspecialchars(strtolower($item['brand'] . ' ' . $item['model'] . ' ' . $item['series'])) ?>">
                                    <td>
                                        <div class="item-primary"><?= htmlspecialchars($item['brand']) ?> <?= htmlspecialchars($item['model']) ?></div>
                                        <div class="item-secondary"><?= htmlspecialchars($item['series']) ?> | <?= htmlspecialchars($item['cpu']) ?></div>
                                    </td>
                                    <td style="text-align:center; font-weight:700;"><?= $item['quantity'] ?></td>
                                    <td style="text-align:right;">$<?= number_format($item['unit_price'], 2) ?></td>
                                    <td style="text-align:right;">
                                        <div class="action-buttons">
                                            <button type="button" class="btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($item)) ?>)">✎</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this item?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                                                <?= UI::csrf_field() ?>
                                                <button type="submit" class="btn-delete">🗑</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-overlay" style="display:none;" onclick="if(event.target === this) closeEditModal()">
    <div class="modal-card">
        <h3>Edit Item</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_item">
            <input type="hidden" name="update_id" id="edit-id">
            <?= UI::csrf_field() ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" name="update_brand" id="edit-brand" required>
                </div>
                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="update_model" id="edit-model" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Series</label>
                    <input type="text" name="update_series" id="edit-series">
                </div>
                <div class="form-group">
                    <label>CPU</label>
                    <input type="text" name="update_cpu" id="edit-cpu">
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="update_desc" id="edit-desc"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="update_qty" id="edit-qty" min="1" required>
                </div>
                <div class="form-group">
                    <label>Price</label>
                    <input type="number" name="update_price" id="edit-price" step="0.01">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(item) {
    document.getElementById('edit-id').value = item.id;
    document.getElementById('edit-brand').value = item.brand;
    document.getElementById('edit-model').value = item.model;
    document.getElementById('edit-series').value = item.series;
    document.getElementById('edit-cpu').value = item.cpu;
    document.getElementById('edit-desc').value = item.description;
    document.getElementById('edit-qty').value = item.quantity;
    document.getElementById('edit-price').value = item.unit_price;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function filterSummary() {
    const query = document.getElementById('summary-search').value.toLowerCase();
    const rows = document.querySelectorAll('.summary-row');
    rows.forEach(row => {
        const text = row.getAttribute('data-search');
        row.style.display = text.includes(query) ? '' : 'none';
    });
}
</script>
