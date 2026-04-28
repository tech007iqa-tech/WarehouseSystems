/**
 * IQA Metal — Warehouse Control Logic
 */

let __warehouseState = null;
function getWarehouseState() {
    if (__warehouseState) return __warehouseState;
    const el = document.getElementById('warehouse-state');
    __warehouseState = el ? JSON.parse(el.textContent) : {};
    return __warehouseState;
}

document.addEventListener('DOMContentLoaded', () => {
    initWarehouseDatalists();
    initCpuGenChips();

    // Auto-hide success messages
    const msgBanner = document.getElementById('wh-msg-banner');
    if (msgBanner) {
        setTimeout(() => {
            msgBanner.style.opacity = '0';
            msgBanner.style.transform = 'translateY(-10px)';
            setTimeout(() => msgBanner.remove(), 500);

            // Clean URL without refresh
            const url = new URL(window.location);
            if (url.searchParams.has('msg')) {
                url.searchParams.delete('msg');
                window.history.replaceState({}, '', url);
            }
        }, 1500); // Snappy dismissal (1.5s)
    }

    // Save form data to localStorage on submit
    const whForm = document.getElementById('wh-main-form');
    if (whForm) {
        whForm.addEventListener('submit', () => {
            const formData = new FormData(whForm);
            const data = {};
            formData.forEach((value, key) => {
                // Don't save IDs or actions
                if (key !== 'item_id' && key !== 'action') {
                    data[key] = value;
                }
            });
            localStorage.setItem('wh_last_entry', JSON.stringify(data));
        });
    }

    // Hide clone button if no data
    const cloneBtn = document.getElementById('btn-clone-last');
    if (cloneBtn && !localStorage.getItem('wh_last_entry')) {
        cloneBtn.style.display = 'none';
    }
    // Restore sort preference
    const savedSort = localStorage.getItem('wh_gate_sort');
    const sortDropdown = document.getElementById('gate-loc-sort');
    if (savedSort && sortDropdown) {
        sortDropdown.value = savedSort;
        sortGateLocations(); // Apply it immediately
    }
});

/**
 * Fills the registration form with data from the last submission
 */
function fillLastEnteredData() {
    const raw = localStorage.getItem('wh_last_entry');
    if (!raw) return;

    const data = JSON.parse(raw);
    const form = document.getElementById('wh-main-form');
    if (!form) return;

    // Direct mapping for common fields
    const fields = ['brand', 'model', 'quantity', 'condition', 'notes', 'cpu', 'gpu', 'ram', 'storage', 'battery', 'windows', 'series', 'gen', 'cpu_gen', 'gaming_category'];

    fields.forEach(f => {
        if (form[f] && data[f] !== undefined) {
            form[f].value = data[f];
        }
    });

    // Trigger UI updates for specific sectors (like Gaming category toggle)
    if (typeof toggleGamingFields === 'function') toggleGamingFields();

    // Success micro-feedback on the button
    const btn = document.getElementById('btn-clone-last');
    if (btn) {
        const orig = btn.innerText;
        btn.innerText = '✅ Cloned';
        setTimeout(() => btn.innerText = orig, 1000);
    }

    // Sync chips after cloning
    syncCpuGenChips();
}

/**
 * Initializes CPU/Gen chips click events
 */
function initCpuGenChips() {
    const chips = document.querySelectorAll('#cpu-gen-chips .chip-item');
    const input = document.getElementById('wh-spec-cpu-gen');
    if (!chips.length || !input) return;

    chips.forEach(chip => {
        chip.addEventListener('click', () => {
            input.value = chip.getAttribute('data-value');
            syncCpuGenChips();
            // Optional: Trigger input event if other listeners depend on it
            input.dispatchEvent(new Event('input'));
        });
    });

    // Also sync when the user types manually
    input.addEventListener('input', () => {
        syncCpuGenChips();
    });
}

/**
 * Syncs the active state of chips with the hidden input value
 */
function syncCpuGenChips() {
    const input = document.getElementById('wh-spec-cpu-gen');
    const chips = document.querySelectorAll('#cpu-gen-chips .chip-item');
    if (!input || !chips.length) return;

    chips.forEach(c => {
        if (c.getAttribute('data-value') === input.value) {
            c.classList.add('active');
        } else {
            c.classList.remove('active');
        }
    });
}

/**
 * Initializes the brand/model/series datalists using the shared inventory data.
 * Contextually switches between laptops and gaming.
 */
function initWarehouseDatalists() {
    const brandIn = document.getElementById('wh-brand');
    const modelIn = document.getElementById('wh-model');
    const modelDl = document.getElementById('model-options');
    const brandDl = document.getElementById('brand-options');
    const seriesDl = document.getElementById('series-options');

    // Determine target inventory based on active sector
    const state = getWarehouseState();
    let targetInventory = IQA_LaptopInventory;
    if (state.activeSector === 'Gaming') targetInventory = IQA_GamingInventory;
    if (state.activeSector === 'Desktops') targetInventory = IQA_DesktopInventory;
    // For general/electronics we might still use a fall-back or merged list if needed
    // but for now, we follow the split.

    // Populate Brands
    if (brandDl) {
        brandDl.innerHTML = Object.keys(targetInventory).map(b => `<option value="${b}">`).join('');
    }

    if (brandIn) {
        brandIn.addEventListener('change', (e) => {
            const selectedBrand = e.target.value;
            const data = targetInventory[selectedBrand];

            if (modelIn) modelIn.value = '';
            if (modelDl) modelDl.innerHTML = '';
            if (seriesDl) seriesDl.innerHTML = '';

            if (data) {
                // Smart Handling for Desktops: User usually types everything in 'Model'
                if (state.activeSector === 'Desktops' && modelDl) {
                    const allOptions = [...(data.models || []), ...(data.series || [])];
                    modelDl.innerHTML = allOptions.map(m => `<option value="${m}">`).join('');
                } else {
                    // Standard Split (Laptops/Gaming)
                    if (modelDl) modelDl.innerHTML = data.models.map(m => `<option value="${m}">`).join('');
                    if (seriesDl) seriesDl.innerHTML = (data.series || []).map(s => `<option value="${s}">`).join('');
                }
            }
        });
    }

    if (modelIn) {
        modelIn.addEventListener('input', (e) => {
            if (brandIn && brandIn.value === '') {
                const val = e.target.value.toLowerCase();
                if (val.length < 3) return;

                for (const [brand, data] of Object.entries(targetInventory)) {
                    const found = (data.models || []).some(m => m.toLowerCase() === val) ||
                        (data.series || []).some(s => s.toLowerCase() === val);

                    if (found) {
                        brandIn.value = brand;
                        brandIn.dispatchEvent(new Event('change'));
                        break;
                    }
                }
            }
        });
    }
}

/**
 * Toggles visibility and labels of gaming-specific fields based on category
 */
function toggleGamingFields() {
    const cat = document.getElementById('wh-gaming-cat');
    const pcFields = document.getElementById('wh-gaming-pc-fields');
    const specLabel = document.getElementById('wh-gaming-spec-label');
    const seriesIn = document.getElementById('wh-series');
    const ramIn = document.getElementById('wh-ram');
    const storageIn = document.getElementById('wh-storage');

    if (!cat) return;

    const val = cat.value;

    // 1. Handle PC vs Others visibility
    if (pcFields) pcFields.style.display = (val === 'PC' ? 'block' : 'none');

    // 2. Dynamic Labeling for Specs
    if (!specLabel || !seriesIn || !ramIn || !storageIn) return;

    // Reset defaults
    ramIn.style.display = 'block';
    storageIn.style.display = 'block';
    seriesIn.placeholder = 'Series / Edition';
    specLabel.innerText = 'Specs / Series';

    if (val === 'Consoles') {
        specLabel.innerText = 'Series / Edition';
        seriesIn.placeholder = 'e.g. Slim / Pro / Disc / Digital';
        ramIn.placeholder = 'Color / Region';
        storageIn.placeholder = 'Capacity (1TB/512GB)';
    } else if (val === 'Controllers') {
        specLabel.innerText = 'Controller Specs';
        seriesIn.placeholder = 'e.g. DualSense / Elite';
        ramIn.placeholder = 'Color (Midnight Black)';
        storageIn.style.display = 'none'; // Controllers don't have storage
    } else if (val === 'Games') {
        specLabel.innerText = 'Game Edition';
        seriesIn.placeholder = 'e.g. Deluxe / Steelbook';
        ramIn.style.display = 'none'; // Games don't have RAM
        storageIn.style.display = 'none'; // Games don't have Storage
    } else if (val === 'PC') {
        specLabel.innerText = 'Additional Specs';
    }
}

/**
 * Filters the warehouse inventory list based on search input
 */
function filterWarehouse() {
    const searchInput = document.getElementById('wh-search');
    if (!searchInput) return;

    const filter = searchInput.value.toLowerCase();
    const cards = document.getElementsByClassName('inventory-card');

    let visibleQtyTotal = 0;

    for (let i = 0; i < cards.length; i++) {
        const text = cards[i].getAttribute('data-search') || "";
        if (text.toLowerCase().includes(filter)) {
            cards[i].style.display = "";
            const qtyPill = cards[i].querySelector('.qty-pill');
            if (qtyPill) {
                visibleQtyTotal += parseInt(qtyPill.innerText, 10) || 0;
            }
        } else {
            cards[i].style.display = "none";
        }
    }

    // Update the total qty row if it exists
    const totalQtyElem = document.getElementById('table-total-qty');
    if (totalQtyElem) {
        totalQtyElem.innerText = visibleQtyTotal.toLocaleString();
    }
}

/**
 * Filters the locations on the Gate page in real-time
 */
function filterGateLocations() {
    const input = document.getElementById('gate-loc-search');
    const grid = document.getElementById('gate-loc-grid');
    const noResults = document.getElementById('gate-no-results');
    if (!input || !grid) return;

    const filter = input.value.toLowerCase();
    const items = grid.getElementsByClassName('gate-loc-item');
    let found = 0;

    for (let i = 0; i < items.length; i++) {
        const item = items[i];
        const wrapper = item.closest('.loc-item-wrapper') || item;
        const locName = item.getAttribute('data-loc-name') || "";

        if (locName.includes(filter)) {
            wrapper.style.display = "";
            found++;
        } else {
            wrapper.style.display = "none";
        }
    }

    if (noResults) {
        noResults.style.display = (found === 0 ? "block" : "none");
    }
}

/**
 * Sorts the locations on the Gate page (A-Z or Z-A)
 */
function sortGateLocations() {
    const sortVal = document.getElementById('gate-loc-sort').value;
    const grid = document.getElementById('gate-loc-grid');
    if (!grid) return;

    // Persist preference
    localStorage.setItem('wh_gate_sort', sortVal);

    // Add visual feedback
    grid.classList.add('sorting');

    setTimeout(() => {
        // Get all children that are zone items or their wrappers
        const items = Array.from(grid.children);
    const zoneItems = items.filter(el => el.classList.contains('loc-item-wrapper'));
    const newLocItem = items.find(el => el.classList.contains('new_loc') || el.classList.contains('new-loc'));

    const statusPriority = {
        'working': 1,
        'audit': 2,
        'shipping': 3,
        'in-review': 4,
        'warehoused': 5,
        'idle': 6
    };

    zoneItems.sort((a, b) => {
        const itemA = a.querySelector('.gate-loc-item');
        const itemB = b.querySelector('.gate-loc-item');
        if (!itemA || !itemB) return 0;

        const nameA = itemA.getAttribute('data-loc-name') || "";
        const nameB = itemB.getAttribute('data-loc-name') || "";
        const countA = parseInt(itemA.getAttribute('data-count') || "0", 10);
        const countB = parseInt(itemB.getAttribute('data-count') || "0", 10);
        const statusA = itemA.getAttribute('data-status') || "idle";
        const statusB = itemB.getAttribute('data-status') || "idle";

        if (sortVal === 'asc') return nameA.localeCompare(nameB, undefined, { numeric: true, sensitivity: 'base' });
        if (sortVal === 'desc') return nameB.localeCompare(nameA, undefined, { numeric: true, sensitivity: 'base' });
        
        if (sortVal === 'count-desc') return countB - countA || nameA.localeCompare(nameB);
        if (sortVal === 'count-asc') return countA - countB || nameA.localeCompare(nameB);
        
        if (sortVal === 'status') {
            const prioA = statusPriority[statusA] || 99;
            const prioB = statusPriority[statusB] || 99;
            return prioA - prioB || nameA.localeCompare(nameB);
        }
        
        return 0;
    });

        // Re-append in order
        zoneItems.forEach(el => grid.appendChild(el));
        if (newLocItem) grid.appendChild(newLocItem);
        
        grid.classList.remove('sorting');
    }, 300);
}

/**
 * Handles editing an existing warehouse item
 * Pre-fills the form and switches to update mode
 */
function editWarehouseItem(item) {
    const form = document.getElementById('wh-main-form');
    const title = document.getElementById('wh-form-title');
    const action = document.getElementById('wh-form-action');
    const editId = document.getElementById('wh-edit-id');
    const submitBtn = document.getElementById('wh-submit-btn');
    const cancelBtn = document.getElementById('wh-cancel-edit');

    if (!form || !title || !action || !editId || !submitBtn || !cancelBtn) return;

    // 1. Switch Form Mode
    title.innerText = '📝 Update Inventory';
    action.value = 'edit_inventory';
    editId.value = item.id;
    const lastUpdatedInput = document.getElementById('wh-last-updated');
    if (lastUpdatedInput) lastUpdatedInput.value = item.updated_at;
    
    submitBtn.innerText = '💾 Save Changes';
    cancelBtn.style.display = 'block';

    // 2. Pre-fill Common Fields
    form.brand.value = item.brand;
    form.model.value = item.model;
    form.quantity.value = item.quantity;

    // 3. Pre-fill Specs (parsing JSON)
    const specs = JSON.parse(item.specs_json || '{}');
    if (form.condition) form.condition.value = specs.condition || 'Used';
    if (form.notes) form.notes.value = specs.notes || '';

    // Sector Specifics
    if (item.sector === 'Laptops') {
        if (form.cpu) form.cpu.value = specs.cpu || '';
        if (form.gpu) form.gpu.value = specs.gpu || '';
        if (form.ram) form.ram.value = specs.ram || '';
        if (form.storage) form.storage.value = specs.storage || '';
        if (form.battery) form.battery.value = specs.battery || '';
        if (form.windows) form.windows.value = specs.windows || '';
        if (form.gen) form.gen.value = specs.gen || '';
        if (form.series) form.series.value = specs.series || '';
    } else if (item.sector === 'Gaming') {
        if (form.gaming_category) {
            form.gaming_category.value = specs.category || 'PC';
            toggleGamingFields(); // Trigger visibility
        }
        if (form.series) form.series.value = specs.series || '';
        if (form.ram) form.ram.value = specs.ram || '';
        if (form.storage) form.storage.value = specs.storage || '';
        if (form.cpu) form.cpu.value = specs.cpu || '';
        if (form.gpu) form.gpu.value = specs.gpu || '';
    } else if (item.sector === 'Desktops') {
        if (form.cpu_gen) {
            form.cpu_gen.value = specs.cpu_gen || '';
            syncCpuGenChips();
        }
    }

    // Scroll to form for mobile UX
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * Resets the warehouse form back to 'Add' mode
 */
function resetWarehouseForm() {
    const form = document.getElementById('wh-main-form');
    const title = document.getElementById('wh-form-title');
    const action = document.getElementById('wh-form-action');
    const editId = document.getElementById('wh-edit-id');
    const submitBtn = document.getElementById('wh-submit-btn');
    const cancelBtn = document.getElementById('wh-cancel-edit');

    if (!form) return;

    form.reset();
    title.innerText = '📥 Register Stock';
    action.value = 'add_inventory';
    editId.value = '';
    const lastUpdatedInput = document.getElementById('wh-last-updated');
    if (lastUpdatedInput) lastUpdatedInput.value = '';

    submitBtn.innerText = '📥 Add to Stock';
    cancelBtn.style.display = 'none';

    // Trigger UI cleanup
    if (typeof toggleGamingFields === 'function') toggleGamingFields();
    syncCpuGenChips();
}

/**
 * Generates and downloads a CSV of the visible warehouse inventory with separated spec columns
 */
function downloadWarehouseCSV() {
    const cards = document.querySelectorAll('.inventory-card');
    const activeLocElem = document.querySelector('.loc-text');
    const activeLoc = activeLocElem ? activeLocElem.innerText.trim() : 'Warehouse';
    const isGlobal = activeLoc === 'GLOBAL';

    // CSV Meta Header
    let csv = `"Active Location","${activeLoc} 📍",,,,,,,\n\n`;

    // Updated to match the specified B2B structure
    const headers = ["Type", "Brand", "Model", "Series", "CPU / Gen", "Description", "Price", "QTY", "Total"];
    if (isGlobal) headers.unshift("Location");

    csv += headers.map(h => `"${h}"`).join(",") + "\n";

    const sanitize = (val) => `"${(val || "").toString().trim().replace(/"/g, '""')}"`;
    let count = 0;

    cards.forEach(card => {
        // Only export visible items (respects search filter)
        if (card.style.display !== 'none') {
            const specs = JSON.parse(card.getAttribute('data-specs') || '{}');

            const brand = card.getAttribute('data-brand') || '';
            const model = card.getAttribute('data-model') || '';
            const qtyElement = card.querySelector('.qty-pill');
            const qty = qtyElement ? qtyElement.innerText.trim() : '0';

            const locTag = card.querySelector('.location-tag');
            const itemLoc = locTag ? locTag.innerText.trim() : '';

            // Map Warehouse specs to the simplified B2B columns
            let cpuGen = (specs.cpu || "") + (specs.gen ? " (" + specs.gen + ")" : "");
            if (card.getAttribute('data-sector-theme') === 'Desktops') {
                cpuGen = specs.cpu_gen || '';
            }
            const sectorTheme = card.getAttribute('data-sector-theme') || 'Laptops';
            
            // Build a richer description for the CSV (includes requested battery info)
            let specHighlights = "";
            if (sectorTheme === 'Laptops') {
                if (specs.ram) specHighlights += ` | RAM: ${specs.ram}`;
                if (specs.storage) specHighlights += ` | STO: ${specs.storage}`;
                // Explicit battery status as requested
                specHighlights += ` | Battery: ${specs.battery || "No Battery/Unchecked"}`;
            } else if (sectorTheme === 'Gaming') {
                if (specs.gpu) specHighlights += ` | GPU: ${specs.gpu}`;
                if (specs.ram) specHighlights += ` | RAM: ${specs.ram}`;
            }

            const fullDesc = (specs.condition || "") + specHighlights + (specs.notes ? " - " + specs.notes : "");

            let itemType = "Laptop";
            if (sectorTheme === 'Desktops') itemType = "Desktop";
            else if (sectorTheme === 'Gaming') itemType = "Gaming";
            else if (sectorTheme === 'Electronics') itemType = "Electronics";

            const rowData = [
                sanitize(itemType),              // Type
                sanitize(brand),                 // Brand
                sanitize(model),                 // Model
                sanitize(specs.series || ""),    // Series
                sanitize(cpuGen),                // CPU / Gen
                sanitize(fullDesc),              // Description
                "0.00",                          // Price (Not stored in warehouse)
                sanitize(qty),                   // QTY
                "0.00"                           // Total
            ];

            if (isGlobal) rowData.unshift(sanitize(itemLoc));

            csv += rowData.join(",") + "\n";
            count++;
        }
    });

    if (count === 0) {
        alert("No visible items to export.");
        return;
    }

    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    const dateStamp = new Date().toISOString().slice(0, 10);
    const state = getWarehouseState();
    const sector = (state.activeSector || "Warehouse").replace(/\s+/g, '_');

    link.href = url;
    link.download = `IQA_Inventory_${sector}_${dateStamp}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
