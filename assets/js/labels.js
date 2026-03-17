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

// ─── FILTER BAR ─────────────────────────────────────────────────────────────

const filterSearch  = document.getElementById('filterSearch');
const filterMsg     = document.getElementById('filterMsg');
const tbody         = document.getElementById('inventoryTableBody');

let filterTimer = null;

function runFilter() {
    const q      = filterSearch.value.trim();

    // Reset visibility if we are back to empty, but we must re-fetch 
    // to restore the full list if the tbody was previously cleared/replaced.
    filterMsg.textContent = 'Searching…';

    fetch('api/get_labels.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(json => {
            if (!json.success) {
                filterMsg.textContent = 'Error: ' + (json.error || 'Unknown');
                return;
            }
            tbody.innerHTML = '';
            if (json.data.length === 0) {
                filterMsg.textContent = 'No configurations match your filter.';
                tbody.innerHTML = `<tr><td colspan="7" class="text-center"
                    style="padding:30px;color:var(--text-secondary);font-style:italic;">
                    No matching label profiles found.</td></tr>`;
            } else {
                filterMsg.textContent = json.data.length + ' result(s)';
                json.data.forEach(item => tbody.appendChild(buildRow(item)));
                attachRowListeners();
            }
        })
        .catch(() => { filterMsg.textContent = 'Network error.'; });
}

filterSearch.addEventListener('input', () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(runFilter, 300);
});

// ─── ROW BUILDER ─────────────────────────────────────────────────────────────

/**
 * Builds a display <tr> element from an item data object.
 * Matches the column order in the PHP-rendered table.
 */
function buildRow(item) {
    const tr = document.createElement('tr');
    tr.dataset.id   = item.id;

    const nameStr = `${esc(item.brand)} ${esc(item.model)}`;
    const linkColor = (item.description === 'Refurbished') ? 'var(--accent-color)' : 'var(--text-main)';
    const brandModelHtml = `<a href="hardware_view.php?id=${item.id}" style="color:${linkColor}; text-decoration:none; font-weight:bold; font-size:1.1rem;">${nameStr}</a>`;

    tr.innerHTML = `
        <td>
            ${brandModelHtml}
            <div style="font-size:0.8rem;color:var(--text-secondary);">${esc(item.series || '')}</div>
        </td>
        <td style="font-size:0.9rem;">
            <div>${esc(item.cpu_gen || '—')}</div>
            <div style="font-size:0.75rem;color:var(--text-secondary);">${esc(item.cpu_specs || '')}</div>
        </td>
        <td style="font-size:0.9rem;">${esc(item.ram || 'None')} / ${esc(item.storage || 'None')}</td>
        <td>${esc(item.warehouse_location || 'Unassigned')}</td>
        <td>${conditionBadge(item.description)}</td>
        <td style="font-size:0.85rem;color:var(--text-secondary);">${fmtDate(item.created_at)}</td>
        <td style="white-space:nowrap;">
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

    // Ensure row is fully opaque (removes legacy status-based dimming)
    tr.style.opacity = '1';

    return tr;
}

// ─── ATTACH LISTENERS to all rows (both server-rendered and JS-rendered) ─────

function attachRowListeners() {
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.removeEventListener('click', onEditClick);
        btn.addEventListener('click', onEditClick);
    });
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.removeEventListener('click', onDeleteClick);
        btn.addEventListener('click', onDeleteClick);
    });
    document.querySelectorAll('.reprint-btn').forEach(btn => {
        btn.removeEventListener('click', onReprintClick);
        btn.addEventListener('click', onReprintClick);
    });
    document.querySelectorAll('.open-label-btn').forEach(btn => {
        btn.removeEventListener('click', onOpenClick);
        btn.addEventListener('click', onOpenClick);
    });
}

// Run on first page load for server-rendered rows
attachRowListeners();

// ─── EDIT ────────────────────────────────────────────────────────────────────

function onEditClick(e) {
    const id  = parseInt(e.target.dataset.id);
    const tr  = e.target.closest('tr');

    // Find the item data embedded in the row's cells
    const cells = tr.querySelectorAll('td');

    // Read existing values from DOM cells (simpler than hidden data attributes)
    // We fetch fresh data from the server to pre-fill the form accurately
    fetch('api/search_item.php?id=' + id)
        .then(r => r.json())
        .then(json => {
            if (!json.success) { alert('Could not load item: ' + json.error); return; }
            // The API now returns a 'results' array; take the first match for editing
            const itemToEdit = json.data.results[0];
            openEditRow(tr, itemToEdit);
        });
}

function openEditRow(tr, item) {
    // Save original HTML so Cancel can restore it
    const originalHTML = tr.innerHTML;

    tr.innerHTML = `
        <td style="vertical-align:top;">
            <input type="hidden" name="id" value="${item.id}">
            <input type="text" class="edit-field" name="brand"  value="${esc(item.brand  || '')}" placeholder="Brand"  style="width:90px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="model"  value="${esc(item.model  || '')}" placeholder="Model"  style="width:100px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="series" value="${esc(item.series || '')}" placeholder="Series" style="width:85px;padding:6px;">
        </td>
        <td style="vertical-align:top;">
            <input type="text" class="edit-field" name="cpu_gen"   value="${esc(item.cpu_gen || '')}"   placeholder="Gen"   style="width:110px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="cpu_specs" value="${esc(item.cpu_specs || '')}" placeholder="Specs" style="width:110px;margin-bottom:4px;padding:6px;">
            <div style="display:flex;gap:4px;">
                <input type="text" class="edit-field" name="cpu_cores" value="${esc(item.cpu_cores || '')}" placeholder="Cores" style="width:53px;padding:6px;font-size:0.75rem;">
                <input type="text" class="edit-field" name="cpu_speed" value="${esc(item.cpu_speed || '')}" placeholder="Speed" style="width:53px;padding:6px;font-size:0.75rem;">
            </div>
        </td>
        <td>
            <input type="text" class="edit-field" name="ram"     value="${esc(item.ram     || '')}" placeholder="RAM"     style="width:65px;margin-bottom:4px;padding:6px;">
            <input type="text" class="edit-field" name="storage" value="${esc(item.storage || '')}" placeholder="Storage" style="width:100px;padding:6px;">
        </td>
        <td>
            <input type="text" class="edit-field" name="warehouse_location" value="${esc(item.warehouse_location || '')}" placeholder="Location" style="width:95px;padding:6px;">
        </td>
        <td>
            <select class="edit-field" name="description" style="padding:6px;width:110px;">
                <option value="Untested"    ${item.description === 'Untested'   ? 'selected' : ''}>Untested</option>
                <option value="Refurbished" ${item.description === 'Refurbished'? 'selected' : ''}>Refurbished</option>
                <option value="For Parts"   ${item.description === 'For Parts'  ? 'selected' : ''}>For Parts</option>
            </select>
        </td>
        <td style="font-size:0.85rem;color:var(--text-secondary);">${fmtDate(item.created_at)}</td>
        <td style="white-space:nowrap;">
            <button class="btn btn-success save-edit-btn" data-id="${item.id}"
                    style="font-size:0.75rem;padding:5px 10px;margin-right:4px;">💾 Save</button>
            <button class="btn cancel-edit-btn"
                    style="font-size:0.75rem;padding:5px 10px;background:var(--bg-page);border:1px solid var(--border-color);color:var(--text-main);">✕ Cancel</button>
        </td>
    `;

    // Hidden fields for fields not shown in the edit row
    ['battery', 'bios_state', 'cpu_details'].forEach(field => {
        const hidden = document.createElement('input');
        hidden.type  = 'hidden';
        hidden.name  = field;
        hidden.value = item[field] ?? '';
        tr.querySelector('td').appendChild(hidden);
    });

    // Save
    tr.querySelector('.save-edit-btn').addEventListener('click', () => saveEdit(tr, item.id));

    // Cancel
    tr.querySelector('.cancel-edit-btn').addEventListener('click', () => {
        tr.innerHTML      = originalHTML;
        tr.style.opacity  = '1'; // Always reset opacity to full visibility
        attachRowListeners();
    });
}

function saveEdit(tr, id) {
    const saveBtn      = tr.querySelector('.save-edit-btn');
    saveBtn.disabled   = true;
    saveBtn.textContent = '⏳…';

    const formData = new FormData();
    formData.append('id', id);
    tr.querySelectorAll('.edit-field, input[type="hidden"]').forEach(field => {
        if (field.name) formData.append(field.name, field.value);
    });

    fetch('api/edit_label.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(json => {
            if (!json.success) {
                alert('Save failed: ' + (json.error || 'Unknown error'));
                saveBtn.disabled    = false;
                saveBtn.textContent = '💾 Save';
                return;
            }
            // Replace edit row with fresh display row
            const newTr = buildRow(json.data.item);
            tr.replaceWith(newTr);
            attachRowListeners();
        })
        .catch(() => {
            alert('Network error — changes were not saved.');
            saveBtn.disabled    = false;
            saveBtn.textContent = '💾 Save';
        });
}

// ─── DELETE ──────────────────────────────────────────────────────────────────

function onDeleteClick(e) {
    const id    = parseInt(e.target.dataset.id);
    const label = e.target.dataset.label;

    if (!confirm(`Delete "${label}" (#${pad(id, 5)}) from the warehouse?\n\nThis cannot be undone.`)) return;

    const formData = new FormData();
    formData.append('id', id);

    fetch('api/delete_label.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(json => {
            if (!json.success) {
                alert('Delete failed: ' + (json.error || 'Unknown error'));
                return;
            }
            // Remove the row from the DOM
            const tr = document.querySelector(`tr[data-id="${id}"]`);
            if (tr) {
                tr.style.transition = 'opacity 0.3s';
                tr.style.opacity    = '0';
                setTimeout(() => tr.remove(), 300);
            }
        })
        .catch(() => alert('Network error — item was not deleted.'));
}

// ─── REPRINT ─────────────────────────────────────────────────────────────────

function onReprintClick(e) {
    const id = e.target.closest('.reprint-btn').dataset.id;
    window.open('print_label.php?id=' + id, '_blank');
}

async function onOpenClick(e) {
    const btn   = e.target.closest('.open-label-btn');
    const id    = btn.dataset.id;
    const brand = btn.dataset.brand;
    const model = btn.dataset.model;

    // Use the global Flash Launch engine from actions.js
    await flashOpenLabel(id, brand, model, btn);
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────

function esc(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function pad(n, len) { return String(n).padStart(len, '0'); }
function fmtDate(ts)  {
    if (!ts) return '—';
    const d = new Date(ts);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
function conditionBadge(desc) {
    if (!desc) return '—';
    const map = { 'For Parts': '#ef4444', 'Refurbished': 'var(--accent-color)', 'Untested': '#f39c12' };
    const color = map[desc] || 'var(--text-secondary)';
    return `<span style="background:${color};color:#fff;padding:2px 7px;border-radius:4px;font-size:0.75rem;font-weight:800;text-transform:uppercase;">${esc(desc)}</span>`;
}
