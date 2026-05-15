<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Create connection to SQLite database
    $conn = Database::orders();

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
            $qty = Security::sanitize_int($_POST['update_qty'] ?? 1);
            $price = Security::sanitize_float($_POST['update_price'] ?? 0.00);
            $brand = $_POST['update_brand'] ?? '';
            $model = $_POST['update_model'] ?? '';
            $series = $_POST['update_series'] ?? '';
            $cpu = $_POST['update_cpu'] ?? '';
            $desc = $_POST['update_desc'] ?? '';
            
            $stmt = $conn->prepare("UPDATE items SET brand=?, model=?, series=?, cpu=?, description=?, quantity=?, unit_price=? WHERE id=?");
            if ($stmt->execute([$brand, $model, $series, $cpu, $desc, (int)$qty, (float)$price, (int)$update_id])) {
                $_SESSION['message'] = "<div class='alert success'>Item details updated.</div>";
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
            <form id="ajax-batch-form" class="batch-form" onsubmit="handleBatchSubmit(event)">
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

                <div class="form-actions" style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-add" style="flex: 2;">Add to Batch</button>
                    <?php if (isset($_SESSION['last_entry'])): ?>
                        <button type="button" class="btn-repeat" onclick="repeatLastEntry()" title="Fill with last entry" style="flex: 1; background: var(--bg-card); border: 1px solid var(--border-color); cursor: pointer; border-radius: 8px; font-size: 0.9rem;">✨ Repeat Last</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <!-- Inject Last Entry State -->
        <script type="application/json" id="lastEntryState">
            <?= json_encode($_SESSION['last_entry'] ?? null) ?>
        </script>

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

function repeatLastEntry() {
    const stateEl = document.getElementById('lastEntryState');
    if (!stateEl) return;
    
    const lastEntry = JSON.parse(stateEl.textContent);
    if (!lastEntry) return;

    const form = document.querySelector('.batch-form');
    if (!form) return;

    // Map keys to form names
    const fields = {
        'brand': lastEntry.brand,
        'model': lastEntry.model,
        'series': lastEntry.series,
        'cpu': lastEntry.cpu
    };

    Object.keys(fields).forEach(key => {
        const input = form.querySelector(`[name="${key}"]`);
        if (input) {
            input.value = fields[key];
            // Visual feedback
            input.style.backgroundColor = 'rgba(59, 130, 246, 0.1)';
            setTimeout(() => { input.style.backgroundColor = ''; }, 600);
        }
    });
}

async function handleBatchSubmit(event) {
    event.preventDefault();
    const form = event.target;
    const btn = form.querySelector('.btn-add');
    const originalText = btn.textContent;

    // Loading State
    btn.disabled = true;
    btn.textContent = 'Adding...';

    const formData = new FormData(form);

    try {
        const response = await fetch('api/add_order_item.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            // 1. Update Table
            const tbody = document.getElementById('summary-list');
            const emptyRow = tbody.querySelector('.empty-row');
            if (emptyRow) emptyRow.remove();

            tbody.insertAdjacentHTML('afterbegin', result.row_html);

            // 2. Update Sidebar Total
            const counter = document.getElementById('sidebar-total-qty');
            if (counter) {
                counter.textContent = result.new_total;
                counter.classList.add('pulse');
                setTimeout(() => counter.classList.remove('pulse'), 500);
            }

            // 3. Update "Repeat Last" state
            const stateEl = document.getElementById('lastEntryState');
            if (stateEl) stateEl.textContent = JSON.stringify(result.last_entry);
            
            // Show the repeat button if it was hidden
            const repeatBtn = document.querySelector('.btn-repeat');
            if (!repeatBtn) {
                // If it's the first item, we might need to refresh or dynamically inject the button
                // For simplicity, we assume the button container handles this or user refreshes.
                // But let's be thorough:
                location.hash = '#batch-builder-top'; // Jump to top
            }

            // 4. Reset Form (except for fields we might want to keep)
            form.querySelector('[name="model"]').value = '';
            form.querySelector('[name="cpu"]').value = '';
            form.querySelector('[name="description"]').value = '';
            form.querySelector('[name="quantity"]').value = '1';
            
            // Focus brand for next entry
            form.querySelector('[name="brand"]').focus();

        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    } catch (err) {
        console.error(err);
        alert('Critical error saving item.');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}
</script>
