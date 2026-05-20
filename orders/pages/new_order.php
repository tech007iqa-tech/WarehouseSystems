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

// Fetch customer details
$customer_info = null;
if ($current_customer) {
    try {
        $conn_c = Database::customers();
        $stmt_c = $conn_c->prepare("SELECT company_name, contact_person AS contact_name FROM customers WHERE customer_id = ?");
        $stmt_c->execute([$current_customer]);
        $customer_info = $stmt_c->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback silently if query fails
    }
}

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
                <?php if ($customer_info): ?>
                    <?php if (!empty(trim($customer_info['company_name'] ?? ''))): ?>
                        <div class="meta-item">
                            <span class="label">Company Name:</span>
                            <span class="value"><?= htmlspecialchars($customer_info['company_name']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty(trim($customer_info['contact_name'] ?? ''))): ?>
                        <div class="meta-item">
                            <span class="label">Contact Name:</span>
                            <span class="value"><?= htmlspecialchars($customer_info['contact_name']) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
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
                        <input type="text" id="brand" name="brand" list="brand-options" placeholder="Dell, HP..." required>
                    </div>
                    <div class="form-group">
                        <label>Model</label>
                        <div style="position: relative; display: flex; align-items: center;">
                            <span id="apple-prefix" style="display: none; position: absolute; left: 12px; color: var(--text-main); font-weight: 700; pointer-events: none;">A-</span>
                            <input type="text" id="models" name="model" list="model-options" placeholder="A1465..." required style="width: 100%; transition: padding 0.2s;">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Series / Project</label>
                        <input type="text" id="series" name="series" list="series-options" placeholder="IQA-2024-001">
                        <datalist id="series-options"></datalist>
                    </div>
                    <div class="form-group">
                        <label>CPU / Gen</label>
                        <input type="text" id="cpu" name="cpu" list="cpu-options" placeholder="i5-8350U">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description / Condition</label>
                    <textarea id="description" name="description" placeholder="Used, No major defects..."></textarea>
                    <!-- Premium Interactive Keyword Chips -->
                    <div class="keyword-chips-container">
                        <span class="keyword-chip" onclick="toggleDescriptionKeyword('Tested')" data-keyword="Tested">Tested</span>
                        <span class="keyword-chip" onclick="toggleDescriptionKeyword('Untested')" data-keyword="Untested">Untested</span>
                        <span class="keyword-chip" onclick="toggleDescriptionKeyword('Working')" data-keyword="Working">Working</span>
                        <span class="keyword-chip" onclick="toggleDescriptionKeyword('Parts')" data-keyword="Parts">Parts</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" id="qty" name="quantity" value="1" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Unit Price ($)</label>
                        <input type="number" id="price" name="unit_price" value="0.00" step="0.01">
                    </div>
                </div>

                <div class="form-actions" style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-add" style="flex: 2;">Add to Batch</button>
                    <button type="button" class="btn-repeat" onclick="openImportModal('<?= htmlspecialchars($current_customer) ?>', '<?= htmlspecialchars($current_order) ?>')" title="Import from Clipboard" style="flex: 1; background: var(--bg-card); border: 1px solid var(--border-color); cursor: pointer; border-radius: 8px; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; gap: 6px;">📋 Import</button>
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
                <div style="display:flex; gap:10px; align-items:center;">
                    <select id="summary-sort" onchange="sortSummary()" style="height: 34px; font-size: 0.8rem; padding: 0 10px; border-radius: 8px; border: 1px solid var(--border-color); outline: none;">
                        <option value="newest">Newest Added</option>
                        <option value="desc_asc">Description</option>
                        <option value="qty_desc">Quantity (High-Low)</option>
                        <option value="price_desc">Price (High-Low)</option>
                    </select>
                    <div class="search-box" style="max-width: 160px; width: 100%;">
                        <input type="text" id="summary-search" placeholder="Filter items..." onkeyup="filterSummary()" style="height: 34px; font-size: 0.8rem; padding: 0 10px; border-radius: 8px; width: 100%;">
                    </div>
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
                                <tr class="summary-row" data-id="<?= $item['id'] ?>" data-desc="<?= htmlspecialchars($item['description']) ?>" data-qty="<?= $item['quantity'] ?>" data-price="<?= $item['unit_price'] ?>" data-search="<?= htmlspecialchars(strtolower($item['brand'] . ' ' . $item['model'] . ' ' . $item['series'])) ?>">
                                    <td>
                                        <div class="item-primary"><?= htmlspecialchars($item['brand']) ?> <?= htmlspecialchars($item['model']) ?></div>
                                        <div class="item-secondary"><?= htmlspecialchars($item['series']) ?> | <?= htmlspecialchars($item['cpu']) ?></div>
                                        <?php if(!empty(trim($item['description']))): ?>
                                            <div class="item-description" style="font-size: 0.75rem; color: #64748b; margin-top: 4px;"><?= nl2br(htmlspecialchars($item['description'])) ?></div>
                                        <?php endif; ?>
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
function toggleDescriptionKeyword(keyword) {
    const descArea = document.getElementById('description');
    if (!descArea) return;

    let val = descArea.value.trim();
    if (val) {
        if (val.endsWith(',')) {
            descArea.value = val + ' ' + keyword;
        } else {
            descArea.value = val + ', ' + keyword;
        }
    } else {
        descArea.value = keyword;
    }
    descArea.focus();
    descArea.dispatchEvent(new Event('input'));
}
window.toggleDescriptionKeyword = toggleDescriptionKeyword;

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('ajax-batch-form');
    if (!form) return;
    
    const brandInput = form.querySelector('[name="brand"]');
    const modelInput = form.querySelector('[name="model"]');
    const seriesInput = form.querySelector('[name="series"]');
    const cpuInput = form.querySelector('[name="cpu"]');
    const applePrefix = document.getElementById('apple-prefix');

    const updateAppleUI = () => {
        if (brandInput.value.trim().toLowerCase() === 'apple') {
            if (applePrefix) applePrefix.style.display = 'block';
            if (modelInput) modelInput.style.paddingLeft = '32px';
            
            if (!seriesInput.value) seriesInput.value = '-';
            if (!cpuInput.value) cpuInput.value = '-';
            
            // Clean up any existing 'A' or 'A-' they might have typed
            if (modelInput && modelInput.value.toUpperCase().startsWith('A-')) {
                modelInput.value = modelInput.value.substring(2);
            } else if (modelInput && modelInput.value.toUpperCase().startsWith('A')) {
                modelInput.value = modelInput.value.substring(1);
            }
        } else {
            if (applePrefix) applePrefix.style.display = 'none';
            if (modelInput) modelInput.style.paddingLeft = '';
        }
    };

    if (brandInput) {
        brandInput.addEventListener('change', updateAppleUI);
        brandInput.addEventListener('input', updateAppleUI);
    }
});

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

function sortSummary() {
    const sortBy = document.getElementById('summary-sort').value;
    const tbody = document.getElementById('summary-list');
    const rows = Array.from(tbody.querySelectorAll('.summary-row'));

    rows.sort((a, b) => {
        if (sortBy === 'newest') {
            return parseInt(b.getAttribute('data-id')) - parseInt(a.getAttribute('data-id'));
        } else if (sortBy === 'desc_asc') {
            const descA = a.getAttribute('data-desc').toLowerCase();
            const descB = b.getAttribute('data-desc').toLowerCase();
            const cmp = descB.localeCompare(descA);
            if (cmp !== 0) {
                return cmp;
            }
            // Secondary sort: lower prices first (ascending)
            return parseFloat(a.getAttribute('data-price')) - parseFloat(b.getAttribute('data-price'));
        } else if (sortBy === 'qty_desc') {
            return parseInt(b.getAttribute('data-qty')) - parseInt(a.getAttribute('data-qty'));
        } else if (sortBy === 'price_desc') {
            return parseFloat(b.getAttribute('data-price')) - parseFloat(a.getAttribute('data-price'));
        }
        return 0;
    });

    // Re-append rows in sorted order
    rows.forEach(row => tbody.appendChild(row));
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
    
    // Ensure the model actually gets the A- prefix if brand is Apple
    if (formData.get('brand').trim().toLowerCase() === 'apple') {
        let currentModel = formData.get('model').trim();
        if (!currentModel.toUpperCase().startsWith('A-')) {
            if (currentModel.toUpperCase().startsWith('A')) {
                currentModel = 'A-' + currentModel.substring(1);
            } else {
                currentModel = 'A-' + currentModel;
            }
        }
        formData.set('model', currentModel);
    }

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
            const descEl = form.querySelector('[name="description"]');
            if (descEl) {
                descEl.value = '';
                descEl.dispatchEvent(new Event('input'));
            }
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

<!-- Import Modal -->
<div id="import-modal" class="modal-overlay" style="display: none; overflow-y: auto; align-items: flex-start; padding: 20px 10px;" onclick="closeImportModal()">
    <div class="modal-card" onclick="event.stopPropagation()" style="max-width: 800px; width: 95%; margin: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="font-weight: 900; margin: 0; font-size: 1.5rem; text-align: left;">📋 Import Batch from Clipboard</h2>
            <button class="btn-repeat" onclick="closeImportModal()" style="border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: 900;">✖</button>
        </div>
        <div style="padding: 0;">
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px; text-align: left;">
                Paste your data below. Delimiter format is auto-detected. Excel/Sheets and CSV lists are fully supported.
            </p>
            
            <textarea id="import-paste-area" placeholder="Paste rows here..." style="width: 100%; height: 250px; border-radius: 12px; border: 2px solid #e2e8f0; padding: 15px; font-family: monospace; font-size: 0.85rem; resize: none; margin-bottom: 20px; outline: none; transition: border-color 0.2s; background: #f8fafc;" onfocus="this.style.borderColor='var(--accent-color)'" onblur="this.style.borderColor='#e2e8f0'"></textarea>
            
            <div id="import-preview" style="margin-bottom: 20px; display: none;">
                <div id="import-mapping-info"></div>
                <h3 style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; margin-bottom: 10px; text-align: left;">Preview: <span id="import-row-count">0</span> rows detected</h3>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.7rem; background: #f8fafc;">
                    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;" id="import-preview-table">
                        <!-- Populated by JS -->
                    </table>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="processImport()" id="btn-submit-import" class="btn-save" style="flex: 2; height: 50px; font-weight: 800; cursor: pointer; border: none; border-radius: 12px;">🚀 Start Bulk Import</button>
                <button type="button" onclick="closeImportModal()" class="btn-cancel" style="flex: 1; height: 50px; font-weight: 700; cursor: pointer; border: none; border-radius: 12px;">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
let activeImportCustomerId = null;
let activeImportOrderId = null;

function openImportModal(customerId, orderId) {
    activeImportCustomerId = customerId;
    activeImportOrderId = orderId;
    const modal = document.getElementById('import-modal');
    const area = document.getElementById('import-paste-area');
    if (modal) {
        modal.style.display = 'flex';
        if (area) {
            area.value = '';
            area.focus();
        }
    }
}

function closeImportModal() {
    const modal = document.getElementById('import-modal');
    if (modal) modal.style.display = 'none';
    activeImportCustomerId = null;
    activeImportOrderId = null;
}

function escapeHTML(str) {
    if (!str) return '—';
    return str.toString().replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[m]));
}

// Smart paste parsing with delimiter detection & dynamic header matching
function parsePastedText(text) {
    if (!text.trim()) return { items: [], mapping: { brand: -1, model: -1, series: -1, cpu: -1, description: -1, price: -1, qty: -1, hasHeader: false, delimiterName: 'Tab' } };

    const lines = text.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
    if (lines.length === 0) return { items: [], mapping: { brand: -1, model: -1, series: -1, cpu: -1, description: -1, price: -1, qty: -1, hasHeader: false, delimiterName: 'Tab' } };

    let tabCount = 0;
    let commaCount = 0;
    let semiCount = 0;
    const testLimit = Math.min(lines.length, 5);
    for (let i = 0; i < testLimit; i++) {
        tabCount += (lines[i].match(/\t/g) || []).length;
        commaCount += (lines[i].match(/,/g) || []).length;
        semiCount += (lines[i].match(/;/g) || []).length;
    }

    let delimiter = '\t';
    if (commaCount > tabCount && commaCount > semiCount) delimiter = ',';
    else if (semiCount > tabCount && semiCount > commaCount) delimiter = ';';

    const splitLine = (line, delim) => {
        if (delim === '\t' || delim === ';') {
            return line.split(delim).map(v => {
                let s = v.trim();
                if (s.startsWith('"') && s.endsWith('"')) s = s.slice(1, -1);
                return s;
            });
        }
        const result = [];
        let cur = '';
        let inQuotes = false;
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            if (char === '"') {
                inQuotes = !inQuotes;
            } else if (char === delim && !inQuotes) {
                result.push(cur.trim());
                cur = '';
            } else {
                cur += char;
            }
        }
        result.push(cur.trim());
        return result.map(s => {
            if (s.startsWith('"') && s.endsWith('"')) s = s.slice(1, -1);
            return s;
        });
    };

    const parsedRows = lines.map(line => splitLine(line, delimiter));

    let brandIdx = -1;
    let modelIdx = -1;
    let seriesIdx = -1;
    let cpuIdx = -1;
    let descIdx = -1;
    let priceIdx = -1;
    let qtyIdx = -1;
    let hasHeader = false;

    if (parsedRows.length > 0) {
        const firstRow = parsedRows[0];
        firstRow.forEach((col, idx) => {
            const colLower = col.toLowerCase().trim();
            if (colLower.includes('brand')) { brandIdx = idx; hasHeader = true; }
            else if (colLower.includes('model')) { modelIdx = idx; hasHeader = true; }
            else if (colLower.includes('series')) { seriesIdx = idx; hasHeader = true; }
            else if (colLower.includes('cpu') || colLower.includes('processor')) { cpuIdx = idx; hasHeader = true; }
            else if (colLower.includes('desc') || colLower.includes('description') || colLower.includes('spec')) { descIdx = idx; hasHeader = true; }
            else if (colLower.includes('price') || colLower.includes('value') || colLower.includes('cost') || colLower.includes('unit_price')) { priceIdx = idx; hasHeader = true; }
            else if (colLower.includes('qty') || colLower.includes('quantity') || colLower.includes('count') || colLower.includes('units')) { qtyIdx = idx; hasHeader = true; }
        });
    }

    const dataRows = hasHeader ? parsedRows.slice(1) : parsedRows;

    if (!hasHeader && parsedRows.length > 0) {
        const colCount = parsedRows[0].length;
        if (colCount >= 8) {
            brandIdx = 1;
            modelIdx = 2;
            seriesIdx = 3;
            cpuIdx = 4;
            descIdx = 5;
            priceIdx = 6;
            qtyIdx = 7;
        } else if (colCount === 7) {
            brandIdx = 0;
            modelIdx = 1;
            seriesIdx = 2;
            cpuIdx = 3;
            descIdx = 4;
            priceIdx = 5;
            qtyIdx = 6;
        } else if (colCount === 6) {
            brandIdx = 0;
            modelIdx = 1;
            seriesIdx = 2;
            cpuIdx = 3;
            priceIdx = 4;
            qtyIdx = 5;
        } else if (colCount === 5) {
            brandIdx = 0;
            modelIdx = 1;
            seriesIdx = 2;
            priceIdx = 3;
            qtyIdx = 4;
        } else if (colCount === 4) {
            brandIdx = 0;
            modelIdx = 1;
            priceIdx = 2;
            qtyIdx = 3;
        } else if (colCount === 3) {
            brandIdx = 0;
            modelIdx = 1;
            qtyIdx = 2;
        } else if (colCount === 2) {
            brandIdx = 0;
            modelIdx = 1;
        }
    }

    const items = [];
    dataRows.forEach(cols => {
        if (cols.length < 2) return;

        const brand = brandIdx !== -1 ? (cols[brandIdx] || '').trim() : 'Generic';
        const model = modelIdx !== -1 ? (cols[modelIdx] || '').trim() : 'Bulk Item';
        const series = seriesIdx !== -1 ? (cols[seriesIdx] || '').trim() : 'N/A';
        const cpu = cpuIdx !== -1 ? (cols[cpuIdx] || '').trim() : '';
        const description = descIdx !== -1 ? (cols[descIdx] || '').trim() : '';
        
        let price = 0;
        if (priceIdx !== -1 && cols[priceIdx]) {
            const parsedPrice = parseFloat(cols[priceIdx].toString().replace(/[^-0-9.]/g, ''));
            if (!isNaN(parsedPrice)) price = parsedPrice;
        }

        let qty = 1;
        if (qtyIdx !== -1 && cols[qtyIdx]) {
            const parsedQty = parseInt(cols[qtyIdx].toString().replace(/[^-0-9]/g, ''));
            if (!isNaN(parsedQty)) qty = parsedQty;
        }

        if (!brand && !model) return;

        items.push({
            brand: brand || 'Generic',
            model: model || 'Bulk Item',
            series: series || 'N/A',
            cpu: cpu || '',
            description: description || '',
            quantity: qty,
            unit_price: price
        });
    });

    const mapping = {
        brand: brandIdx,
        model: modelIdx,
        series: seriesIdx,
        cpu: cpuIdx,
        description: descIdx,
        price: priceIdx,
        qty: qtyIdx,
        hasHeader,
        delimiterName: delimiter === '\t' ? 'Tab (Excel/Sheets)' : delimiter === ',' ? 'CSV (Comma)' : 'Semicolon'
    };

    return { items, mapping };
}

async function processImport() {
    const area = document.getElementById('import-paste-area');
    const btn = document.getElementById('btn-submit-import');
    if (!area || !area.value.trim() || !activeImportCustomerId || !activeImportOrderId) return;

    const originalBtnText = btn.innerHTML;
    btn.innerHTML = '⏳ Processing...';
    btn.disabled = true;

    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    const { items } = parsePastedText(area.value);
    if (items.length === 0) {
        alert("No valid items detected to import.");
        btn.innerHTML = originalBtnText;
        btn.disabled = false;
        return;
    }
    
    try {
        const response = await fetch('api/bulk_update_orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'bulk_import',
                csrf_token: csrfToken,
                customer_id: activeImportCustomerId,
                order_id: activeImportOrderId,
                items: items
            })
        });

        const result = await response.json();
        if (result.success) {
            btn.innerHTML = '✅ Success!';
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            alert("Import failed: " + (result.error || "Unknown error"));
            btn.innerHTML = originalBtnText;
            btn.disabled = false;
        }
    } catch (e) {
        console.error(e);
        alert("Network error during import.");
        btn.innerHTML = originalBtnText;
        btn.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const area = document.getElementById('import-paste-area');
    if (area) {
        area.addEventListener('input', function() {
            const text = this.value;
            const preview = document.getElementById('import-preview');
            const table = document.getElementById('import-preview-table');
            const count = document.getElementById('import-row-count');
            const mappingInfo = document.getElementById('import-mapping-info');

            if (!text.trim()) {
                preview.style.display = 'none';
