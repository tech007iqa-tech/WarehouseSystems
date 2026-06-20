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
                $_SESSION['notification_msg'] = "Item removed from order. 🗑️";
                $_SESSION['notification_type'] = "success";
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'update_item') {
            $update_id = $_POST['update_id'] ?? 0;
            $qty = Security::sanitize_float($_POST['update_qty'] ?? 1);
            $price = Security::sanitize_float($_POST['update_price'] ?? 0.00);
            $brand = $_POST['update_brand'] ?? '';
            $model = $_POST['update_model'] ?? '';
            $series = $_POST['update_series'] ?? '';
            $cpu = trim(($_POST['edit_cpu_series'] ?? '') . ' ' . ($_POST['edit_cpu_gen'] ?? ''));
            $desc = $_POST['update_desc'] ?? '';

            $stmt = $conn->prepare("UPDATE items SET brand=?, model=?, series=?, cpu=?, description=?, quantity=?, unit_price=? WHERE id=?");
            if ($stmt->execute([$brand, $model, $series, $cpu, $desc, (float) $qty, (float) $price, (int) $update_id])) {
                $_SESSION['notification_msg'] = "Item details updated. 💾";
                $_SESSION['notification_type'] = "success";
            }
        }

        $current_customer = $_GET['customer_id'] ?? null;
        $current_order = $_GET['order_id'] ?? 'ORD-DEFAULT';
        header("Location: index.php?customer_id=" . urlencode($current_customer) . "&order_id=" . urlencode($current_order) . "#summary-list");
        exit();
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
$stmt = $conn->prepare("SELECT * FROM items WHERE order_id = ? AND customer_id = ? ORDER BY id ASC");
$stmt->execute([$current_order, $current_customer]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total units for this batch
$total_units = 0;
foreach ($items as $item)
    $total_units += $item['quantity'];

?>

<div class="new-order-layout spreadsheet-mode">
    <!-- Top Horizontal Summary Banner Card -->
    <header class="order-summary-banner card">
        <div class="banner-left">
            <h2 id="batch-builder-top">Order Batch Builder</h2>
            <div class="customer-info-badges">
                <?php if ($customer_info): ?>
                    <?php if (!empty(trim($customer_info['company_name'] ?? ''))): ?>
                        <span class="info-badge company">🏢 <?= htmlspecialchars($customer_info['company_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty(trim($customer_info['contact_name'] ?? ''))): ?>
                        <span class="info-badge contact">👤 <?= htmlspecialchars($customer_info['contact_name']) ?></span>
                    <?php endif; ?>
                <?php endif; ?>
                <span class="info-badge order-id">📦 ID: <?= htmlspecialchars($current_order) ?></span>
            </div>
        </div>
        <div class="banner-right">
            <div class="total-units-container">
                <span class="label">Total Units:</span>
                <span class="value counter" id="sidebar-total-qty"><?= $total_units ?></span>
            </div>
            <div class="banner-actions">
                <button type="button" class="btn-repeat"
                    onclick="openImportModal('<?= htmlspecialchars($current_customer) ?>', '<?= htmlspecialchars($current_order) ?>')"
                    title="Import from Clipboard">📋 Import Bulk</button>
                <a href="checkout.php?customer_id=<?= urlencode($current_customer) ?>&order_id=<?= urlencode($current_order) ?>"
                    class="btn-finalize">
                    Finalize & Checkout →
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content: Spreadsheet Editable Table -->
    <main class="order-main">
        <section class="summary-section card spreadsheet-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h3>Current Batch Summary</h3>
                <div style="display:flex; gap:10px; align-items:center;">
                    <select id="summary-sort" onchange="sortSummary()"
                        style="height: 34px; font-size: 0.8rem; padding: 0 10px; border-radius: 8px; border: 1px solid var(--border-color); outline: none;">
                        <option value="oldest">Older First</option>
                        <option value="newest">Newest Added</option>
                        <option value="desc_asc">Description</option>
                        <option value="qty_desc">Quantity (High-Low)</option>
                        <option value="price_desc">Price (High-Low)</option>
                    </select>
                    <div class="search-box" style="max-width: 240px; width: 100%;">
                        <input type="text" id="summary-search" placeholder="Filter items..." onkeyup="filterSummary()"
                            style="height: 34px; font-size: 0.8rem; padding: 0 10px; border-radius: 8px; width: 100%;">
                    </div>
                </div>
            </div>

            <!-- Injected csrf token and batch details for JS use -->
            <div id="batch-metadata" data-csrf="<?= htmlspecialchars(Security::getToken()) ?>"
                data-customer-id="<?= htmlspecialchars($current_customer) ?>"
                data-order-id="<?= htmlspecialchars($current_order) ?>" style="display:none;"></div>

            <div class="summary-table-wrapper spreadsheet-table-wrapper">
                <table class="summary-table spreadsheet-table">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Brand</th>
                            <th style="width: 12%;">Model</th>
                            <th style="width: 12%;">Series</th>
                            <th style="width: 12%;">CPU</th>
                            <th style="width: 18%;">Description</th>
                            <th style="width: 11%; text-align:center;">Qty</th>
                            <th style="width: 15%; text-align:right;">Price</th>
                            <th style="width: 8%; text-align:right;"></th>
                        </tr>
                    </thead>
                    <tbody id="summary-list">
                        <?php foreach ($items as $item): ?>
                            <tr class="summary-row" data-id="<?= $item['id'] ?>"
                                data-desc="<?= htmlspecialchars($item['description']) ?>"
                                data-qty="<?= $item['quantity'] ?>" data-price="<?= $item['unit_price'] ?>"
                                data-search="<?= htmlspecialchars(strtolower($item['brand'] . ' ' . $item['model'] . ' ' . $item['series'])) ?>">
                                <td class="editable-cell" data-field="brand">
                                    <input type="text" class="cell-input" value="<?= htmlspecialchars($item['brand']) ?>"
                                        list="brand-options" placeholder="...">
                                </td>
                                <td class="editable-cell" data-field="model">
                                    <input type="text" class="cell-input" value="<?= htmlspecialchars($item['model']) ?>"
                                        placeholder="...">
                                </td>
                                <td class="editable-cell" data-field="series">
                                    <input type="text" class="cell-input" value="<?= htmlspecialchars($item['series']) ?>"
                                        placeholder="...">
                                </td>
                                <td class="editable-cell" data-field="cpu">
                                    <input type="text" class="cell-input" value="<?= htmlspecialchars($item['cpu']) ?>"
                                        placeholder="...">
                                </td>
                                <td class="editable-cell" data-field="description">
                                    <input type="text" class="cell-input"
                                        value="<?= htmlspecialchars($item['description']) ?>" placeholder="...">
                                </td>
                                <td class="editable-cell numeric" data-field="quantity" style="text-align:center;">
                                    <input type="number" step="any" min="0" class="cell-input text-center font-bold"
                                        value="<?= $item['quantity'] ?>">
                                </td>
                                <td class="editable-cell numeric" data-field="unit_price" style="text-align:right;">
                                    <input type="number" step="0.01" class="cell-input text-right"
                                        value="<?= number_format($item['unit_price'], 2, '.', '') ?>">
                                </td>
                                <td style="text-align:right;">
                                    <div class="action-buttons">
                                        <button type="button" class="btn-clone-row" style="background: none; border: none; font-size: 1rem; cursor: pointer; opacity: 0.5; padding: 0 4px;" title="Clone Row">➕</button>
                                        <form method="POST" style="display:inline;"
                                            onsubmit="return confirm('Remove this item?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                                            <?= UI::csrf_field() ?>
                                            <button type="submit" class="btn-delete" title="Delete Row">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Permanent Blank entry row at the bottom for quick appending -->
                        <tr class="summary-row new-blank-row" data-id="new">
                            <td class="editable-cell" data-field="brand">
                                <input type="text" class="cell-input" list="brand-options" placeholder="Brand...">
                            </td>
                            <td class="editable-cell" data-field="model">
                                <input type="text" class="cell-input" placeholder="Model...">
                            </td>
                            <td class="editable-cell" data-field="series">
                                <input type="text" class="cell-input" placeholder="Series...">
                            </td>
                            <td class="editable-cell" data-field="cpu">
                                <input type="text" class="cell-input" placeholder="CPU...">
                            </td>
                            <td class="editable-cell" data-field="description">
                                <input type="text" class="cell-input" placeholder="Desc...">
                            </td>
                            <td class="editable-cell numeric" data-field="quantity" style="text-align:center;">
                                <input type="number" step="any" min="0" class="cell-input text-center font-bold"
                                    placeholder="Qty">
                            </td>
                            <td class="editable-cell numeric" data-field="unit_price" style="text-align:right;">
                                <input type="number" step="0.01" class="cell-input text-right" placeholder="Price">
                            </td>
                            <td style="text-align:right;">
                                <div class="action-buttons">
                                    <button type="button" class="btn-add-row-indicator" style="background: none; border: none; font-size: 1rem; opacity: 0.3;">➕</button>
                                </div>
                            </td>
                        </tr>
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
                    <label for="edit-brand">Brand</label>
                    <input type="text" name="update_brand" id="edit-brand" list="brand-options" required>
                </div>
                <div class="form-group">
                    <label for="edit-model">Model</label>
                    <input type="text" name="update_model" id="edit-model" list="edit-model-options" required>
                    <datalist id="edit-model-options"></datalist>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex: 1.2;">
                    <label for="edit-series">Series</label>
                    <input type="text" name="update_series" id="edit-series" list="edit-series-options">
                    <datalist id="edit-series-options"></datalist>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="edit-cpu-series">CPU</label>
                    <select id="edit-cpu-series" name="edit_cpu_series" style="width: 100%;">
                        <option value="" disabled selected hidden>e.g. i5</option>
                        <option value=""></option>
                        <option value="i3">i3</option>
                        <option value="i5">i5</option>
                        <option value="i7">i7</option>
                        <option value="i9">i9</option>
                        <option value="Ryzen 2">Ryzen 2</option>
                        <option value="Ryzen 3">Ryzen 3</option>
                        <option value="Ryzen 5">Ryzen 5</option>
                        <option value="Ryzen 7">Ryzen 7</option>
                        <option value="Ryzen 9">Ryzen 9</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="edit-cpu-gen">GEN</label>
                    <input type="text" name="edit_cpu_gen" id="edit-cpu-gen" list="cpu-gen-options"
                        placeholder="e.g. 8th">
                </div>
            </div>

            <div class="form-group">
                <label for="edit-desc">Description</label>
                <textarea name="update_desc" id="edit-desc"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="edit-qty">Quantity</label>
                    <input type="number" name="update_qty" id="edit-qty" step="any" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit-price">Price</label>
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
        // Edit Modal Datalist Filtering Logic
        const editBrand = document.getElementById('edit-brand');
        const editModel = document.getElementById('edit-model');
        const editSeries = document.getElementById('edit-series');
        const editModelDl = document.getElementById('edit-model-options');
        const editSeriesDl = document.getElementById('edit-series-options');

        const updateEditSeriesOptions = () => {
            const selectedBrand = editBrand ? editBrand.value : '';
            const selectedModel = editModel ? editModel.value.trim() : '';
            const data = IQA_Inventory[selectedBrand];

            if (editSeriesDl) editSeriesDl.innerHTML = '';

            if (data) {
                let seriesList = [];
                if (selectedModel && data.modelSeries && data.modelSeries[selectedModel]) {
                    seriesList = data.modelSeries[selectedModel];
                } else {
                    seriesList = data.series || [];
                }

                const val = editSeries ? editSeries.value.trim().toLowerCase() : '';
                let filtered = seriesList;
                if (val.length >= 1) {
                    filtered = seriesList.filter(s => s.toLowerCase().startsWith(val));
                }
                if (editSeriesDl) {
                    editSeriesDl.innerHTML = filtered.map(s => `<option value="${s}">`).join('');
                }
            }
        };

        const updateEditModelOptions = () => {
            const selectedBrand = editBrand ? editBrand.value : '';
            const data = IQA_Inventory[selectedBrand];
            if (editModelDl) editModelDl.innerHTML = '';

            if (data && data.models) {
                editModelDl.innerHTML = data.models.map(m => `<option value="${m}">`).join('');
            }
            updateEditSeriesOptions();
        };

        if (editBrand) {
            editBrand.addEventListener('change', updateEditModelOptions);
            editBrand.addEventListener('input', updateEditModelOptions);
        }
        if (editModel) {
            editModel.addEventListener('input', updateEditSeriesOptions);
            editModel.addEventListener('change', updateEditSeriesOptions);
        }
        if (editSeries) {
            editSeries.addEventListener('input', updateEditSeriesOptions);
            editSeries.addEventListener('focus', updateEditSeriesOptions);
        }

        // Expose dynamic sync trigger globally for openEditModal
        window.triggerEditModalDatalistSync = () => {
            updateEditModelOptions();
        };

        const form = document.getElementById('ajax-batch-form');
        if (!form) return;

        const brandInput = form.querySelector('[name="brand"]');
        const modelInput = form.querySelector('[name="model"]');
        const seriesInput = form.querySelector('[name="series"]');
        const cpuGenInput = document.getElementById('cpu_gen');
        const applePrefix = document.getElementById('apple-prefix');

        const updateAppleUI = () => {
            const brandLower = brandInput.value.trim().toLowerCase();
            const keepDash = (brandLower === 'apple' || brandLower === 'other');

            if (brandLower === 'apple') {
                if (applePrefix) applePrefix.style.display = 'block';
                if (modelInput) modelInput.style.paddingLeft = '32px';
            } else {
                if (applePrefix) applePrefix.style.display = 'none';
                if (modelInput) modelInput.style.paddingLeft = '';
            }

            if (keepDash) {
                if (!seriesInput.value || seriesInput.value === '') seriesInput.value = '-';
                if (cpuGenInput && (!cpuGenInput.value || cpuGenInput.value === '')) cpuGenInput.value = '-';
            } else {
                if (seriesInput && seriesInput.value === '-') seriesInput.value = '';
                if (cpuGenInput && cpuGenInput.value === '-') cpuGenInput.value = '';
            }

            // Clean up any existing 'A' or 'A-' they might have typed
            if (brandLower === 'apple' && modelInput) {
                if (modelInput.value.toUpperCase().startsWith('A-')) {
                    modelInput.value = modelInput.value.substring(2);
                } else if (modelInput.value.toUpperCase().startsWith('A')) {
                    modelInput.value = modelInput.value.substring(1);
                }
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

        let cpuSeries = '';
        let cpuGen = '';
        if (item.cpu) {
            const cpuStr = item.cpu.trim();
            const parts = cpuStr.split(/\s+/);
            if (parts.length > 0) {
                if (parts[0].toLowerCase() === 'ryzen' && parts.length > 1) {
                    cpuSeries = parts[0] + ' ' + parts[1];
                    cpuGen = parts.slice(2).join(' ');
                } else {
                    cpuSeries = parts[0];
                    cpuGen = parts.slice(1).join(' ');
                }
            }
        }
        const editCpuSeriesEl = document.getElementById('edit-cpu-series');
        if (editCpuSeriesEl) {
            const optionExists = Array.from(editCpuSeriesEl.options).some(opt => opt.value === cpuSeries);
            if (optionExists) {
                editCpuSeriesEl.value = cpuSeries;
                document.getElementById('edit-cpu-gen').value = cpuGen;
            } else {
                editCpuSeriesEl.value = '';
                document.getElementById('edit-cpu-gen').value = item.cpu || '';
            }
        }

        document.getElementById('edit-desc').value = item.description;
        document.getElementById('edit-qty').value = item.quantity;
        document.getElementById('edit-price').value = item.unit_price;
        document.getElementById('editModal').style.display = 'flex';
        if (window.triggerEditModalDatalistSync) {
            window.triggerEditModalDatalistSync();
        }
        location.hash = '#summary-list'; // Jump to batch-builder-top
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

        const blankRow = rows.find(row => row.classList.contains('new-blank-row'));
        const activeRows = rows.filter(row => !row.classList.contains('new-blank-row'));

        activeRows.sort((a, b) => {
            if (sortBy === 'oldest') {
                return parseInt(a.getAttribute('data-id')) - parseInt(b.getAttribute('data-id'));
            } else if (sortBy === 'newest') {
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

        // Re-append active rows in sorted order
        activeRows.forEach(row => tbody.appendChild(row));

        // Always append blank row at the very bottom
        if (blankRow) {
            tbody.appendChild(blankRow);
        }
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
            'description': lastEntry.description || ''
        };

        Object.keys(fields).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = fields[key];
                // Visual feedback
                input.style.backgroundColor = 'rgba(59, 130, 246, 0.1)';
                setTimeout(() => {
                    input.style.backgroundColor = '';
                }, 600);
                if (key === 'description') {
                    input.dispatchEvent(new Event('input'));
                }
            }
        });

        // Parse and repeat CPU fields
        const cpuVal = lastEntry.cpu || '';
        let cpuSeries = '';
        let cpuGenText = '';
        if (cpuVal.toLowerCase().startsWith('ryzen ')) {
            const parts = cpuVal.split(' ');
            cpuSeries = parts.slice(0, 2).join(' ');
            cpuGenText = parts.slice(2).join(' ');
        } else {
            const parts = cpuVal.split(' ');
            cpuSeries = parts[0] || '';
            cpuGenText = parts.slice(1).join(' ');
        }

        if (form.cpu_series) {
            form.cpu_series.value = cpuSeries;
            form.cpu_series.style.backgroundColor = 'rgba(59, 130, 246, 0.1)';
            setTimeout(() => {
                form.cpu_series.style.backgroundColor = '';
            }, 600);
        }
        if (form.cpu_gen) {
            form.cpu_gen.value = cpuGenText;
            form.cpu_gen.style.backgroundColor = 'rgba(59, 130, 246, 0.1)';
            setTimeout(() => {
                form.cpu_gen.style.backgroundColor = '';
            }, 600);
        }
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

        // Combine CPU Series and CPU Gen
        const cpuSeriesVal = form.cpu_series.value;
        const cpuGenVal = form.cpu_gen.value;
        const combinedCpu = (cpuSeriesVal + ' ' + cpuGenVal).trim();
        formData.set('cpu', combinedCpu);

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
                // 0. Trigger glassmorphic success toast notification
                if (window.IQA_Notify) {
                    window.IQA_Notify.success('New entry successfully added ✨');
                }

                // 1. Update Table
                const tbody = document.getElementById('summary-list');
                const emptyRow = tbody.querySelector('.empty-row');
                if (emptyRow) emptyRow.remove();

                tbody.insertAdjacentHTML('afterbegin', result.row_html);

                // Remove flash-new once animation ends so it can replay next time
                const newRow = tbody.querySelector('.flash-new');
                if (newRow) {
                    newRow.addEventListener('animationend', () => newRow.classList.remove('flash-new'), {
                        once: true
                    });
                }

                // 2. Update Sidebar Total
                const counter = document.getElementById('sidebar-total-qty');
                if (counter) {
                    counter.textContent = result.new_total;
                    counter.classList.add('pulse');
                    setTimeout(() => counter.classList.remove('pulse'), 500);
                }

                // 3. Update "Repeat Last" state
                const stateEl = document.getElementById('lastEntryState');
                if (stateEl) {
                    stateEl.textContent = JSON.stringify(result.last_entry);
                    const repeatBtn = document.getElementById('btn-repeat-last');
                    if (repeatBtn) {
                        repeatBtn.style.display = '';
                    }
                }

                // 4. Fully Reset Form
                form.reset();
                const descEl = form.querySelector('[name="description"]');
                if (descEl) {
                    descEl.value = '';
                    descEl.dispatchEvent(new Event('input'));
                }

                // Trigger brand change event to reset specific UI (like Apple prefix UI)
                const brandEl = form.querySelector('[name="brand"]');
                if (brandEl) {
                    brandEl.dispatchEvent(new Event('change'));
                    brandEl.focus();
                }

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
<div id="import-modal" class="modal-overlay"
    style="display: none; overflow-y: auto; align-items: flex-start; padding: 20px 10px;" onclick="closeImportModal()">
    <div class="modal-card" onclick="event.stopPropagation()" style="max-width: 800px; width: 95%; margin: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="font-weight: 900; margin: 0; font-size: 1.5rem; text-align: left;">📦 Batch Import Center</h2>
            <button class="btn-repeat" onclick="closeImportModal()"
                style="border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: 900;">✖</button>
        </div>

        <!-- Tabs Header -->
        <div class="import-tabs"
            style="display: flex; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; gap: 10px;">
            <button type="button" onclick="switchImportTab('clipboard')" id="tab-btn-clipboard" class="import-tab-btn"
                style="padding: 10px 20px; font-weight: 800; border: none; background: none; border-bottom: 3px solid var(--accent-color); cursor: pointer; color: var(--text-main); font-size: 0.95rem; outline: none;">📋
                Clipboard</button>
            <button type="button" onclick="switchImportTab('warehouse')" id="tab-btn-warehouse" class="import-tab-btn"
                style="padding: 10px 20px; font-weight: 700; border: none; background: none; border-bottom: 3px solid transparent; cursor: pointer; color: #64748b; font-size: 0.95rem; outline: none;">📦
                Warehouse Stock</button>
        </div>

        <div style="padding: 0;">
            <!-- Tab 1: Clipboard -->
            <div id="import-tab-clipboard-content">
                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px; text-align: left;">
                    Paste your data below. Delimiter format is auto-detected. Excel/Sheets and CSV lists are fully
                    supported.
                </p>

                <textarea id="import-paste-area" placeholder="Paste rows here..."
                    style="width: 100%; height: 250px; border-radius: 12px; border: 2px solid #e2e8f0; padding: 15px; font-family: monospace; font-size: 0.85rem; resize: none; margin-bottom: 20px; outline: none; transition: border-color 0.2s; background: #f8fafc;"
                    onfocus="this.style.borderColor='var(--accent-color)'"
                    onblur="this.style.borderColor='#e2e8f0'"></textarea>

                <div id="import-preview" style="margin-bottom: 20px; display: none;">
                    <div id="import-mapping-info"></div>
                    <h3
                        style="font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; margin-bottom: 10px; text-align: left;">
                        Preview: <span id="import-row-count">0</span> rows detected</h3>
                    <div
                        style="max-height: 200px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.7rem; background: #f8fafc;">
                        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;"
                            id="import-preview-table">
                            <!-- Populated by JS -->
                        </table>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="processImport()" id="btn-submit-import" class="btn-save"
                        style="flex: 2; height: 50px; font-weight: 800; cursor: pointer; border: none; border-radius: 12px;">🚀
                        Start Bulk Import</button>
                    <button type="button" onclick="closeImportModal()" class="btn-cancel"
                        style="flex: 1; height: 50px; font-weight: 700; cursor: pointer; border: none; border-radius: 12px;">Cancel</button>
                </div>
            </div>

            <!-- Tab 2: Warehouse Stock -->
            <div id="import-tab-warehouse-content" style="display: none;">
                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 20px; text-align: left;">
                    Search and select items from the warehouse system to copy into this order. Selected items will be
                    flagged in the warehouse system for manual deletion.
                </p>
                <div style="margin-bottom: 20px;">
                    <input type="text" id="wh-import-q" onkeyup="searchWarehouseImport()"
                        placeholder="Search by brand, model, location, or specs..."
                        style="width: 100%; height: 44px; padding: 0 15px; border-radius: 10px; border: 1px solid var(--border-color); outline: none;">
                </div>

                <div
                    style="max-height: 250px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 20px; background: #f8fafc;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.8rem;"
                        id="wh-import-table">
                        <thead>
                            <tr style="background: #f1f5f9; position: sticky; top: 0; z-index: 10;">
                                <th style="padding: 10px; width: 40px; text-align: center;"><input type="checkbox"
                                        id="wh-import-select-all" onchange="toggleAllWarehouseImport(this)"></th>
                                <th style="padding: 10px; width: 120px;">Location</th>
                                <th style="padding: 10px; width: 220px;">Make/Model</th>
                                <th style="padding: 10px; width: 60px; text-align: center;">QTY</th>
                                <th style="padding: 10px; width: 90px; text-align: right;">Price</th>
                                <th style="padding: 10px;">Specs/Notes</th>
                            </tr>
                        </thead>
                        <tbody id="wh-import-list">
                            <tr>
                                <td colspan="6" style="padding: 30px; text-align: center; color: #94a3b8;">Type above to
                                    search warehouse stock.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="submitWarehouseImport()" id="btn-submit-wh-import" class="btn-save"
                        style="flex: 2; height: 50px; font-weight: 800; cursor: pointer; border: none; border-radius: 12px;">🚀
                        Import Selected Stock</button>
                    <button type="button" onclick="closeImportModal()" class="btn-cancel"
                        style="flex: 1; height: 50px; font-weight: 700; cursor: pointer; border: none; border-radius: 12px;">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let activeImportCustomerId = null;
    let activeImportOrderId = null;
    let whImportItems = [];

    function switchImportTab(tab) {
        const isClipboard = tab === 'clipboard';

        document.getElementById('import-tab-clipboard-content').style.display = isClipboard ? 'block' : 'none';
        document.getElementById('import-tab-warehouse-content').style.display = isClipboard ? 'none' : 'block';

        const clipBtn = document.getElementById('tab-btn-clipboard');
        const whBtn = document.getElementById('tab-btn-warehouse');

        if (isClipboard) {
            clipBtn.style.borderBottom = '3px solid var(--accent-color)';
            clipBtn.style.color = 'var(--text-main)';
            clipBtn.style.fontWeight = '800';
            whBtn.style.borderBottom = '3px solid transparent';
            whBtn.style.color = '#64748b';
            whBtn.style.fontWeight = '700';
        } else {
            whBtn.style.borderBottom = '3px solid var(--accent-color)';
            whBtn.style.color = 'var(--text-main)';
            whBtn.style.fontWeight = '800';
            clipBtn.style.borderBottom = '3px solid transparent';
            clipBtn.style.color = '#64748b';
            clipBtn.style.fontWeight = '700';

            // Auto-focus the search field in warehouse tab
            setTimeout(() => {
                const searchField = document.getElementById('wh-import-q');
                if (searchField) {
                    searchField.value = '';
                    searchField.focus();
                    searchWarehouseImport();
                }
            }, 50);
        }
    }

    async function searchWarehouseImport() {
        const q = document.getElementById('wh-import-q').value;
        const list = document.getElementById('wh-import-list');
        const selectAll = document.getElementById('wh-import-select-all');
        if (selectAll) selectAll.checked = false;

        list.innerHTML =
            `<tr><td colspan="6" style="padding: 30px; text-align: center; color: #94a3b8;">Loading warehouse stock...</td></tr>`;

        try {
            const response = await fetch(`api/get_warehouse_stock.php?q=${encodeURIComponent(q)}`);
            if (!response.ok) throw new Error("API error");
            whImportItems = await response.json();

            if (whImportItems.length === 0) {
                list.innerHTML =
                    `<tr><td colspan="6" style="padding: 30px; text-align: center; color: #94a3b8;">No matching warehouse stock found.</td></tr>`;
                return;
            }

            list.innerHTML = whImportItems.map((item, idx) => {
                const specNotes = item.specs?.notes || '';
                const cpu = item.specs?.cpu || '';
                const ram = item.specs?.ram || '';
                const storage = item.specs?.storage || '';
                const specsStr = [cpu, ram, storage, specNotes].filter(Boolean).join(' | ');

                return `
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 10px; text-align: center;"><input type="checkbox" class="wh-import-row-select" data-index="${idx}"></td>
                    <td style="padding: 10px;"><span class="location-tag" style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">${escapeHTML(item.location_code)}</span></td>
                    <td style="padding: 10px; font-weight: 600;">${escapeHTML(item.brand)} ${escapeHTML(item.model)}</td>
                    <td style="padding: 10px; text-align: center; font-weight: 700;">${item.quantity}</td>
                    <td style="padding: 10px; text-align: right;">$${parseFloat(item.price || 0).toFixed(2)}</td>
                    <td style="padding: 10px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;" title="${escapeHTML(specsStr)}">${escapeHTML(specsStr)}</td>
                </tr>
            `;
            }).join('');
        } catch (err) {
            console.error(err);
            list.innerHTML =
                `<tr><td colspan="6" style="padding: 30px; text-align: center; color: #ef4444;">Failed to fetch stock from warehouse.</td></tr>`;
        }
    }

    let lastChecked = null;
    document.addEventListener('DOMContentLoaded', () => {
        const whImportList = document.getElementById('wh-import-list');
        if (whImportList) {
            whImportList.addEventListener('click', function (e) {
                const tr = e.target.closest('tr');
                if (!tr) return;

                const cb = tr.querySelector('.wh-import-row-select');
                if (!cb) return;

                if (e.target === cb) {
                    handleCheckboxClick(cb, e.shiftKey);
                    return;
                }

                cb.checked = !cb.checked;
                handleCheckboxClick(cb, e.shiftKey);
            });
        }
    });

    function handleCheckboxClick(cb, shiftKey) {
        const checkboxes = Array.from(document.querySelectorAll('.wh-import-row-select'));
        if (shiftKey && lastChecked) {
            let start = checkboxes.indexOf(cb);
            let end = checkboxes.indexOf(lastChecked);
            checkboxes.slice(Math.min(start, end), Math.max(start, end) + 1)
                .forEach(c => c.checked = lastChecked.checked);
        }
        lastChecked = cb;
    }

    function toggleAllWarehouseImport(master) {
        const checkboxes = document.querySelectorAll('.wh-import-row-select');
        checkboxes.forEach(cb => cb.checked = master.checked);
    }

    async function submitWarehouseImport() {
        const checkboxes = document.querySelectorAll('.wh-import-row-select:checked');
        if (checkboxes.length === 0) {
            alert("Please select at least one warehouse item to import.");
            return;
        }

        const btn = document.getElementById('btn-submit-wh-import');
        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳ Importing...';
        btn.disabled = true;

        const itemsToImport = [];
        const whIds = [];
        checkboxes.forEach(cb => {
            const idx = parseInt(cb.getAttribute('data-index'));
            const item = whImportItems[idx];
            if (item) {
                whIds.push(item.id);

                // Reconstruct the description
                const specNotes = item.specs?.notes || '';
                const cpu = item.specs?.cpu || '';
                const ram = item.specs?.ram || '';
                const storage = item.specs?.storage || '';
                const battery = item.specs?.battery ? 'Battery: ' + item.specs.battery : '';
                const condition = item.specs?.condition || '';
                const series = item.specs?.series || '';

                let extraDesc = [condition, battery, specNotes].filter(Boolean).join(' | ');
                if (extraDesc) {
                    extraDesc += ' | ';
                }
                extraDesc += `[Warehouse Location: ${item.location_code} - Flagged for Deletion]`;

                itemsToImport.push({
                    brand: item.brand,
                    model: item.model,
                    series: series || 'Warehouse Import',
                    cpu: cpu || '',
                    description: extraDesc,
                    quantity: item.quantity,
                    unit_price: item.price || 0
                });
            }
        });

        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

        try {
            // First, add the items to the order
            const importResponse = await fetch('api/bulk_update_orders.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'bulk_import',
                    csrf_token: csrfToken,
                    customer_id: activeImportCustomerId,
                    order_id: activeImportOrderId,
                    items: itemsToImport
                })
            });

            const importResult = await importResponse.json();
            if (!importResult.success) {
                throw new Error(importResult.error || "Failed to add items to order.");
            }

            // Second, flag the warehouse items for manual deletion (status = 'Pending Delete')
            const flagResponse = await fetch('api/bulk_update_inventory.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    ids: whIds,
                    location: '',
                    price: '',
                    status: 'Pending Delete'
                })
            });

            const flagResult = await flagResponse.json();
            if (!flagResult.success) {
                console.error("Failed to flag warehouse items:", flagResult.error);
            }

            btn.innerHTML = '✅ Success!';
            setTimeout(() => {
                window.location.reload();
            }, 1000);

        } catch (e) {
            console.error(e);
            alert("Import failed: " + e.message);
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    function openImportModal(customerId, orderId) {
        activeImportCustomerId = customerId;
        activeImportOrderId = orderId;
        const modal = document.getElementById('import-modal');
        const area = document.getElementById('import-paste-area');
        if (modal) {
            modal.style.display = 'flex';
            switchImportTab('clipboard'); // default to Clipboard tab on open
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
        if (!text.trim()) return {
            items: [],
            mapping: {
                brand: -1,
                model: -1,
                series: -1,
                cpu: -1,
                description: -1,
                price: -1,
                qty: -1,
                hasHeader: false,
                delimiterName: 'Tab'
            }
        };

        const lines = text.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
        if (lines.length === 0) return {
            items: [],
            mapping: {
                brand: -1,
                model: -1,
                series: -1,
                cpu: -1,
                description: -1,
                price: -1,
                qty: -1,
                hasHeader: false,
                delimiterName: 'Tab'
            }
        };

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
                if (colLower.includes('brand')) {
                    brandIdx = idx;
                    hasHeader = true;
                } else if (colLower.includes('model')) {
                    modelIdx = idx;
                    hasHeader = true;
                } else if (colLower.includes('series')) {
                    seriesIdx = idx;
                    hasHeader = true;
                } else if (colLower.includes('cpu') || colLower.includes('processor')) {
                    cpuIdx = idx;
                    hasHeader = true;
                } else if (colLower.includes('desc') || colLower.includes('description') || colLower.includes(
                    'spec')) {
                    descIdx = idx;
                    hasHeader = true;
                } else if (colLower.includes('price') || colLower.includes('value') || colLower.includes('cost') ||
                    colLower.includes('unit_price')) {
                    priceIdx = idx;
                    hasHeader = true;
                } else if (colLower.includes('qty') || colLower.includes('quantity') || colLower.includes(
                    'count') || colLower.includes('units')) {
                    qtyIdx = idx;
                    hasHeader = true;
                }
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

        return {
            items,
            mapping
        };
    }

    async function processImport() {
        const area = document.getElementById('import-paste-area');
        const btn = document.getElementById('btn-submit-import');
        if (!area || !area.value.trim() || !activeImportCustomerId || !activeImportOrderId) return;

        const originalBtnText = btn.innerHTML;
        btn.innerHTML = '⏳ Processing...';
        btn.disabled = true;

        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

        const {
            items
        } = parsePastedText(area.value);
        if (items.length === 0) {
            alert("No valid items detected to import.");
            btn.innerHTML = originalBtnText;
            btn.disabled = false;
            return;
        }

        try {
            const response = await fetch('api/bulk_update_orders.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
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
                    window.location.href = 'index.php?customer_id=' + encodeURIComponent(
                        activeImportCustomerId) + '&order_id=' + encodeURIComponent(activeImportOrderId) +
                        '#batch-builder-top';
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
            area.addEventListener('input', function () {
                const text = this.value;
                const preview = document.getElementById('import-preview');
                const table = document.getElementById('import-preview-table');
                const count = document.getElementById('import-row-count');
                const mappingInfo = document.getElementById('import-mapping-info');

                if (!text.trim()) {
                    preview.style.display = 'none';
                    return;
                }

                const {
                    items,
                    mapping
                } = parsePastedText(text);

                // Show detected row count
                count.textContent = items.length;

                // Show mapping badge info
                const detectedCols = [];
                if (mapping.brand !== -1) detectedCols.push('Brand');
                if (mapping.model !== -1) detectedCols.push('Model');
                if (mapping.series !== -1) detectedCols.push('Series');
                if (mapping.cpu !== -1) detectedCols.push('CPU');
                if (mapping.description !== -1) detectedCols.push('Desc');
                if (mapping.price !== -1) detectedCols.push('Price');
                if (mapping.qty !== -1) detectedCols.push('Qty');

                mappingInfo.innerHTML = `
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; font-size:0.7rem;">
                    <span style="background:#f1f5f9; padding:3px 10px; border-radius:20px; font-weight:700; color:#475569;">📌 ${escapeHTML(mapping.delimiterName)}</span>
                    ${mapping.hasHeader ? '<span style="background:#dcfce7; padding:3px 10px; border-radius:20px; font-weight:700; color:#16a34a;">✅ Header Detected</span>' : '<span style="background:#fef9c3; padding:3px 10px; border-radius:20px; font-weight:700; color:#854d0e;">⚠️ No Header (Auto-Mapped)</span>'}
                    ${detectedCols.map(c => `<span style="background:#e0f2fe; padding:3px 10px; border-radius:20px; font-weight:600; color:#0369a1;">${escapeHTML(c)}</span>`).join('')}
                </div>`;

                // Build preview table
                if (items.length === 0) {
                    preview.style.display = 'none';
                    return;
                }

                preview.style.display = 'block';

                const headers = ['Brand', 'Model', 'Series', 'CPU', 'Description', 'Price', 'Qty'];
                table.innerHTML = `
                <thead>
                    <tr style="background:#f8fafc;">${headers.map(h => `<th style="padding:8px 10px; font-size:0.65rem; font-weight:800; text-transform:uppercase; color:#94a3b8; text-align:left; border-bottom:1px solid #e2e8f0;">${h}</th>`).join('')}</tr>
                </thead>
                <tbody>
                    ${items.slice(0, 20).map(item => `
                        <tr>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHTML(item.brand)}</td>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHTML(item.model)}</td>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHTML(item.series)}</td>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHTML(item.cpu)}</td>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHTML(item.description)}</td>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; text-align:right;">${item.unit_price > 0 ? '$' + item.unit_price.toFixed(2) : '—'}</td>
                            <td style="padding:7px 10px; border-bottom:1px solid #f1f5f9; text-align:center; font-weight:700;">${item.quantity}</td>
                        </tr>`).join('')}
                    ${items.length > 20 ? `<tr><td colspan="7" style="padding:8px 10px; text-align:center; font-size:0.7rem; color:#94a3b8;">… and ${items.length - 20} more rows</td></tr>` : ''}
                </tbody>`;
            });
        }
    });
</script>
</script>