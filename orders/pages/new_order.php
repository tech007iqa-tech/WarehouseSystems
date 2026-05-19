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
                return;
            }

            const { items, mapping } = parsePastedText(text);

            preview.style.display = 'block';
            count.innerText = items.length;

            const activeMappings = [];
            if (mapping.brand !== -1) activeMappings.push(`<b>Brand</b> (Col ${mapping.brand + 1})`);
            if (mapping.model !== -1) activeMappings.push(`<b>Model</b> (Col ${mapping.model + 1})`);
            if (mapping.series !== -1) activeMappings.push(`<b>Series</b> (Col ${mapping.series + 1})`);
            if (mapping.cpu !== -1) activeMappings.push(`<b>CPU</b> (Col ${mapping.cpu + 1})`);
            if (mapping.description !== -1) activeMappings.push(`<b>Description</b> (Col ${mapping.description + 1})`);
            if (mapping.price !== -1) activeMappings.push(`<b>Price</b> (Col ${mapping.price + 1})`);
            if (mapping.qty !== -1) activeMappings.push(`<b>Qty</b> (Col ${mapping.qty + 1})`);

            const headerMsg = mapping.hasHeader 
                ? `✨ Auto-detected header row in <b>${mapping.delimiterName}</b> format.` 
                : `⚡ No header found. Fallback mapping used in <b>${mapping.delimiterName}</b> format.`;

            if (mappingInfo) {
                mappingInfo.innerHTML = `
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; padding:12px; border-radius:12px; font-size:0.75rem; margin-bottom:15px; line-height:1.5; text-align:left;">
                        <div>${headerMsg}</div>
                        <div style="margin-top:6px; opacity:0.9;">Mapping: ${activeMappings.join(' | ')}</div>
                    </div>
                `;
            }

            let html = `<thead><tr style="background:#f1f5f9; text-align:left; position:sticky; top:0; z-index:1; box-shadow:0 1px 0 #e2e8f0;"><th style="padding:8px 10px; width:20%;">Brand</th><th style="padding:8px 10px; width:30%;">Model</th><th style="padding:8px 10px; width:25%;">Specs</th><th style="padding:8px 10px; width:10%;">Qty</th><th style="padding:8px 10px; width:15%;">Price</th></tr></thead><tbody>`;
            
            items.slice(0, 50).forEach(item => {
                const specs = [item.series, item.cpu].filter(v => v && v !== 'N/A').join(' / ') || item.description || '—';
                html += `<tr>
                    <td style="padding:6px 10px; border-top:1px solid #eee; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align:left;">${escapeHTML(item.brand)}</td>
                    <td style="padding:6px 10px; border-top:1px solid #eee; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align:left;">${escapeHTML(item.model)}</td>
                    <td style="padding:6px 10px; border-top:1px solid #eee; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#64748b; text-align:left;" title="${escapeHTML(specs)}">${escapeHTML(specs)}</td>
                    <td style="padding:6px 10px; border-top:1px solid #eee; text-align:left;">${item.quantity}</td>
                    <td style="padding:6px 10px; border-top:1px solid #eee; font-weight:700; color:var(--accent-color); text-align:left;">$${item.unit_price.toFixed(2)}</td>
                </tr>`;
            });

            if (items.length > 50) {
                html += `<tr><td colspan="5" style="text-align:center; padding:10px; color:#94a3b8; font-style:italic; background:white;">... and ${items.length - 50} more rows</td></tr>`;
            }
            html += '</tbody>';
            table.innerHTML = html;
        });
    }
});
</script>
