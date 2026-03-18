
/**
 * assets/js/labels.js
 * Drives the labels.php warehouse inventory page.
 *
 * Features:
 *  - Filter bar: debounced live search + status dropdown → fetches api/get_labels.php
 *  - Inline Edit: clicking Edit transforms a row into editable input fields
 *  - Delete: confirm prompt → fetch api/delete_label.php → removes row from DOM
 */
'use strict';

// ─── CONFIGURATION & STATE ───────────────────────────────────────────────────

const CONFIG = {
    DEBOUNCE_DELAY: 300,
    API_ENDPOINTS: {
        GET_LABELS: 'api/get_labels.php',
        SEARCH_ITEM: 'api/search_item.php',
        EDIT_LABEL: 'api/edit_label.php',
        DELETE_LABEL: 'api/delete_label.php',
        PRINT_LABEL: 'print_label.php'
    }
};

const DOM = {
    filterSearch: document.getElementById('filterSearch'),
    filterStatus: document.getElementById('filterStatus'),
    filterMsg: document.getElementById('filterMsg'),
    tbody: document.getElementById('inventoryTableBody')
};

let filterTimer = null;

// ─── FILTER BAR ─────────────────────────────────────────────────────────────

/**
 * Executes the filter search by fetching data from the API.
 */
async function runFilter() {
    const query  = DOM.filterSearch.value.trim();
    const status = DOM.filterStatus ? DOM.filterStatus.value : 'In Warehouse';
    DOM.filterMsg.textContent = 'Searching…';

    try {
        const url = `${CONFIG.API_ENDPOINTS.GET_LABELS}?q=${encodeURIComponent(query)}&status=${encodeURIComponent(status)}`;
        const response = await fetch(url);
        const json = await response.json();

        if (!json.success) {
            DOM.filterMsg.textContent = `Error: ${json.error || 'Unknown'}`;
            return;
        }

        DOM.tbody.innerHTML = '';

        if (json.data.length === 0) {
            DOM.filterMsg.textContent = 'No configurations match your filter.';
            DOM.tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center empty-table-message">
                        No matching label profiles found.
                    </td>
                </tr>`;
        } else {
            DOM.filterMsg.textContent = `${json.data.length} result(s)`;
            const fragment = document.createDocumentFragment();
            json.data.forEach(item => fragment.appendChild(buildRow(item)));
            DOM.tbody.appendChild(fragment);
        }
    } catch (error) {
        console.error('Filter error:', error);
        DOM.filterMsg.textContent = 'Network error.';
    }
}

DOM.filterSearch.addEventListener('input', () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(runFilter, CONFIG.DEBOUNCE_DELAY);
});

if (DOM.filterStatus) {
    DOM.filterStatus.addEventListener('change', runFilter);
}

// ─── ROW BUILDER ─────────────────────────────────────────────────────────────

/**
 * Builds a display <tr> element from an item data object.
 */
function buildRow(item) {
    const tr = document.createElement('tr');
    tr.dataset.id = item.id;

    const nameStr = `${esc(item.brand)} ${esc(item.model)}`;
    const linkColorClass = (item.description === 'Refurbished') ? 'text-accent' : 'text-main';
    const isSold = (item.status === 'Sold');

    // Handle Sold status display
    let statusEntry = conditionBadge(item.description);
    if (isSold) {
        statusEntry = `
            <div style="margin-bottom:4px;">${conditionBadge(item.description)}</div>
            <span class="status-badge" style="background:#4b5563; font-size:10px;">🚚 SOLD</span>
        `;
    }

    // Handle Location / Buyer display
    let locationEntry = `<span class="font-bold text-main">${esc(item.warehouse_location || 'Unassigned')}</span>`;
    if (isSold && item.buyer_name) {
        locationEntry = `
            <div class="text-xs text-secondary" style="margin-bottom:2px;">Sold to:</div>
            <div class="font-bold text-accent" style="font-size:0.85rem;">${esc(item.buyer_name)}</div>
            <div class="text-xs" style="color:var(--text-secondary); opacity:0.8;">${esc(item.buyer_order_num)}</div>
        `;
    }

    tr.innerHTML = `
        <td data-label="Model">
            <a href="hardware_view.php?id=${item.id}" class="font-bold text-lg no-underline ${linkColorClass}">
                ${nameStr}
            </a>
            <div class="text-sm text-secondary">${esc(item.series || '')}</div>
            <div class="text-xs" style="margin-top:4px; font-family:monospace; color:var(--text-secondary);">
                ${item.serial_number ? 'S/N: ' + esc(item.serial_number) : '<span style="opacity:0.5;">No Serial</span>'}
            </div>
        </td>
        <td data-label="CPU" class="text-sm">
            <div>${esc(item.cpu_gen || '—')}</div>
            <div class="text-xs text-secondary">${esc(item.cpu_specs || '')}</div>
        </td>
        <td data-label="RAM/HDD" class="text-sm">
            ${esc(item.ram || 'None')} / ${esc(item.storage || 'None')}
        </td>
        <td data-label="Location">
            ${locationEntry}
        </td>
        <td data-label="Status">
            ${statusEntry}
        </td>
        <td data-label="Added" class="text-xs text-secondary">
            ${fmtDate(item.created_at)}
        </td>
        <td class="whitespace-nowrap">
            <div class="action-strip">
                <button class="btn reprint-btn" data-id="${item.id}" title="Reprint Label">🖨️ Print</button>
                <button class="btn open-label-btn" 
                        data-id="${item.id}" 
                        data-brand="${esc(item.brand)}" 
                        data-model="${esc(item.model)}"
                        title="Open Folder/File">📂 Open</button>
                <button class="btn edit-btn" data-id="${item.id}">✏️ Edit</button>
                <button class="btn btn-danger delete-btn" data-id="${item.id}"
                        data-label="${esc(item.brand + ' ' + item.model)}">🗑 Del</button>
            </div>
        </td>
    `;

    return tr;
}

// ─── EVENT DELEGATION ────────────────────────────────────────────────────────

/**
 * Uses event delegation to handle clicks on action buttons within the table.
 */
DOM.tbody.addEventListener('click', (e) => {
    const target = e.target;
    const btn = target.closest('.btn');
    if (!btn) return;

    const id = btn.dataset.id;

    if (btn.classList.contains('edit-btn')) {
        onEditClick(id, btn.closest('tr'));
    } else if (btn.classList.contains('delete-btn')) {
        onDeleteClick(id, btn.dataset.label);
    } else if (btn.classList.contains('reprint-btn')) {
        onReprintClick(id);
    } else if (btn.classList.contains('open-label-btn')) {
        onOpenClick(btn);
    } else if (btn.classList.contains('save-edit-btn')) {
        saveEdit(btn.closest('tr'), id);
    } else if (btn.classList.contains('cancel-edit-btn')) {
        cancelEdit(btn.closest('tr'));
    }
});

// ─── EDIT ────────────────────────────────────────────────────────────────────

/**
 * Handles the edit button click by fetching fresh data and opening the edit row.
 */
async function onEditClick(id, tr) {
    try {
        const response = await fetch(`${CONFIG.API_ENDPOINTS.SEARCH_ITEM}?id=${id}`);
        const json = await response.json();

        if (!json.success) {
            alert(`Could not load item: ${json.error}`);
            return;
        }

        const itemToEdit = json.data.results[0];
        openEditRow(tr, itemToEdit);
    } catch (error) {
        console.error('Edit load error:', error);
        alert('Network error while loading item data.');
    }
}

/**
 * Transforms a display row into an editable form.
 */
function openEditRow(tr, item) {
    // Store original HTML to allow cancellation
    tr.dataset.originalHtml = tr.innerHTML;

    tr.innerHTML = `
        <td style="vertical-align:top;">
            <input type="hidden" name="id" value="${item.id}">
            <input type="text" class="edit-field" name="brand" value="${esc(item.brand || '')}" placeholder="Brand" style="width:90px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="model" value="${esc(item.model || '')}" placeholder="Model" style="width:100px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="series" value="${esc(item.series || '')}" placeholder="Series" style="width:85px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="serial_number" value="${esc(item.serial_number || '')}" placeholder="Serial S/N" style="width:85px;padding:6px;font-family:monospace;font-size:0.75rem;">
        </td>
        <td style="vertical-align:top;">
            <input type="text" class="edit-field" name="cpu_gen" value="${esc(item.cpu_gen || '')}" placeholder="Gen" style="width:110px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="cpu_specs" value="${esc(item.cpu_specs || '')}" placeholder="Specs" style="width:110px;margin-bottom:4px;padding:6px;">
            <div style="display:flex;gap:4px;">
                <input type="text" class="edit-field" name="cpu_cores" value="${esc(item.cpu_cores || '')}" placeholder="Cores" style="width:53px;padding:6px;font-size:0.75rem;">
                <input type="text" class="edit-field" name="cpu_speed" value="${esc(item.cpu_speed || '')}" placeholder="Speed" style="width:53px;padding:6px;font-size:0.75rem;">
            </div>
        </td>
        <td>
            <input type="text" class="edit-field" name="ram" value="${esc(item.ram || '')}" placeholder="RAM" style="width:65px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="storage" value="${esc(item.storage || '')}" placeholder="Storage" style="width:100px;padding:6px;">
        </td>
        <td>
            <input type="text" class="edit-field" name="warehouse_location" value="${esc(item.warehouse_location || '')}" placeholder="Location" style="width:95px;padding:6px;">
        </td>
        <td>
            <select class="edit-field" name="description" style="padding:6px;width:110px;">
                <option value="Untested" ${item.description === 'Untested' ? 'selected' : ''}>Untested</option>
                <option value="Refurbished" ${item.description === 'Refurbished' ? 'selected' : ''}>Refurbished</option>
                <option value="For Parts" ${item.description === 'For Parts' ? 'selected' : ''}>For Parts</option>
            </select>
        </td>
        <td class="text-xs text-secondary">${fmtDate(item.created_at)}</td>
        <td class="whitespace-nowrap">
            <button class="btn btn-success save-edit-btn" data-id="${item.id}" style="font-size:0.75rem;padding:5px 10px;margin-right:4px;">💾 Save</button>
            <button class="btn cancel-edit-btn" style="font-size:0.75rem;padding:5px 10px;background:var(--bg-page);border:1px solid var(--border-color);color:var(--text-main);">✕ Cancel</button>
        </td>
    `;

    // Add hidden fields for data not directly editable in the row
    const firstCell = tr.querySelector('td');
    ['battery', 'bios_state', 'cpu_details'].forEach(field => {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = field;
        hidden.value = item[field] ?? '';
        firstCell.appendChild(hidden);
    });
}

/**
 * Cancels the edit mode and restores the original row content.
 */
function cancelEdit(tr) {
    if (tr.dataset.originalHtml) {
        tr.innerHTML = tr.dataset.originalHtml;
        delete tr.dataset.originalHtml;
    }
}

/**
 * Saves the edited data by sending it to the API.
 */
async function saveEdit(tr, id) {
    const saveBtn = tr.querySelector('.save-edit-btn');
    const originalBtnText = saveBtn.textContent;
    
    saveBtn.disabled = true;
    saveBtn.textContent = '⏳…';

    const formData = new FormData();
    formData.append('id', id);
    tr.querySelectorAll('.edit-field, input[type="hidden"]').forEach(field => {
        if (field.name) formData.append(field.name, field.value);
    });

    try {
        const response = await fetch(CONFIG.API_ENDPOINTS.EDIT_LABEL, { method: 'POST', body: formData });
        const json = await response.json();

        if (!json.success) {
            alert(`Save failed: ${json.error || 'Unknown error'}`);
            saveBtn.disabled = false;
            saveBtn.textContent = originalBtnText;
            return;
        }

        const newTr = buildRow(json.data.item);
        tr.replaceWith(newTr);
    } catch (error) {
        console.error('Save error:', error);
        alert('Network error — changes were not saved.');
        saveBtn.disabled = false;
        saveBtn.textContent = originalBtnText;
    }
}

// ─── DELETE ──────────────────────────────────────────────────────────────────

/**
 * Handles the delete button click with a confirmation prompt.
 */
async function onDeleteClick(id, label) {
    if (!confirm(`Delete "${label}" (#${pad(id, 5)}) from the warehouse?\n\nThis cannot be undone.`)) return;

    const formData = new FormData();
    formData.append('id', id);

    try {
        const response = await fetch(CONFIG.API_ENDPOINTS.DELETE_LABEL, { method: 'POST', body: formData });
        const json = await response.json();

        if (!json.success) {
            alert(`Delete failed: ${json.error || 'Unknown error'}`);
            return;
        }

        const tr = document.querySelector(`tr[data-id="${id}"]`);
        if (tr) {
            tr.style.transition = 'opacity 0.3s, transform 0.3s';
            tr.style.opacity = '0';
            tr.style.transform = 'translateX(20px)';
            setTimeout(() => tr.remove(), 300);
        }
    } catch (error) {
        console.error('Delete error:', error);
        alert('Network error — item was not deleted.');
    }
}

// ─── REPRINT & OPEN ──────────────────────────────────────────────────────────

function onReprintClick(id) {
    window.open(`${CONFIG.API_ENDPOINTS.PRINT_LABEL}?id=${id}`, '_blank');
}

async function onOpenClick(btn) {
    const { id, brand, model } = btn.dataset;
    if (typeof flashOpenLabel === 'function') {
        await flashOpenLabel(id, brand, model, btn);
    } else {
        console.error('flashOpenLabel function not found.');
    }
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────

function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function pad(n, len) { 
    return String(n).padStart(len, '0'); 
}

function fmtDate(ts) {
    if (!ts) return '—';
    const d = new Date(ts);
    return isNaN(d.getTime()) ? '—' : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function conditionBadge(desc) {
    if (!desc) return '—';
    
    let statusClass = 'status-untested';
    if (desc === 'For Parts') {
        statusClass = 'status-for-parts';
    } else if (desc === 'Refurbished') {
        statusClass = 'status-refurbished';
    }

    return `<span class="status-badge ${statusClass}">${esc(desc)}</span>`;
}
