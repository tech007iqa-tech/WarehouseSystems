/**
 * assets/js/rolodex.js
 * Drives edit and delete functionality on rolodex.php.
 *
 * Features:
 *  - Event Delegation: Single listener for all table actions
 *  - Inline Edit: transforms a customer row into editable input fields
 *  - Delete: confirm prompt → fetch api/delete_customer.php → removes row
 *  - Modern JS: Async/Await and improved error handling
 */
'use strict';

// ─── CONFIGURATION & STATE ───────────────────────────────────────────────────

const CONFIG = {
    API_ENDPOINTS: {
        GET_CUSTOMER: 'api/get_customer.php',
        EDIT_CUSTOMER: 'api/edit_customer.php',
        DELETE_CUSTOMER: 'api/delete_customer.php'
    },
    STATUS_OPTIONS: ['Active Customer', 'New Lead', 'Inactive']
};

const DOM = {
    tbody: document.querySelector('#rolodexTableBody') || document.querySelector('.data-table tbody')
};

// ─── EVENT DELEGATION ────────────────────────────────────────────────────────

/**
 * Uses event delegation to handle clicks on action buttons within the table.
 */
if (DOM.tbody) {
    DOM.tbody.addEventListener('click', (e) => {
        const target = e.target;
        const btn = target.closest('.btn');
        if (!btn) return;

        const id = btn.dataset.id;

        if (btn.classList.contains('edit-customer-btn')) {
            onCustomerEditClick(id, btn.closest('tr'));
        } else if (btn.classList.contains('delete-customer-btn')) {
            onCustomerDeleteClick(id, btn.dataset.label);
        } else if (btn.classList.contains('save-cust-btn')) {
            saveCustomer(btn.closest('tr'), id);
        } else if (btn.classList.contains('cancel-cust-btn')) {
            cancelCustomerEdit(btn.closest('tr'));
        }
    });
}

// ─── EDIT ────────────────────────────────────────────────────────────────────

/**
 * Handles the edit button click by fetching fresh data and opening the edit row.
 */
async function onCustomerEditClick(id, tr) {
    try {
        const response = await fetch(`${CONFIG.API_ENDPOINTS.GET_CUSTOMER}?id=${id}`);
        const json = await response.json();

        if (!json.success) {
            alert(`Could not load customer: ${json.error || 'Unknown error'}`);
            return;
        }

        openCustomerEditRow(tr, json.data.customer);
    } catch (error) {
        console.error('Customer load error:', error);
        alert('Network error while loading customer data.');
    }
}

/**
 * Transforms a display row into an editable form.
 */
function openCustomerEditRow(tr, c) {
    // Store original HTML to allow cancellation
    tr.dataset.originalHtml = tr.innerHTML;

    const statusOptionsHtml = CONFIG.STATUS_OPTIONS
        .map(s => `<option value="${s}" ${c.lead_status === s ? 'selected' : ''}>${s}</option>`)
        .join('');

    tr.innerHTML = `
        <td>
            <input type="text" class="edit-cust-field" name="company_name"
                   value="${esc(c.company_name || '')}" placeholder="Company Name"
                   style="width:100%; min-width:130px; padding:6px;">
        </td>
        <td>
            <input type="text" class="edit-cust-field" name="contact_person"
                   value="${esc(c.contact_person || '')}" placeholder="Contact Person"
                   style="width:100%; min-width:120px; padding:6px;">
        </td>
        <td>
            <input type="email" class="edit-cust-field" name="email"
                   value="${esc(c.email || '')}" placeholder="Email"
                   style="width:100%; min-width:150px; padding:6px;">
        </td>
        <td>
            <input type="text" class="edit-cust-field" name="phone"
                   value="${esc(c.phone || '')}" placeholder="Phone"
                   style="width:100%; min-width:110px; padding:6px;">
        </td>
        <td>
            <select class="edit-cust-field" name="lead_status" style="padding:6px; width:100%; min-width:140px;">
                ${statusOptionsHtml}
            </select>
        </td>
        <td>
            <input type="text" class="edit-cust-field" name="notes"
                   value="${esc(c.notes || '')}" placeholder="Notes"
                   style="width:100%; min-width:140px; padding:6px;">
        </td>
        <td class="text-xs text-secondary">${fmtDate(c.created_at)}</td>
        <td class="whitespace-nowrap">
            <button class="btn btn-success save-cust-btn" data-id="${c.customer_id}"
                    style="font-size:0.75rem; padding:5px 10px; margin-right:4px;">💾 Save</button>
            <button class="btn cancel-cust-btn"
                    style="font-size:0.75rem; padding:5px 10px; background:var(--bg-page); border:1px solid var(--border-color); color:var(--text-main);">✕ Cancel</button>
        </td>
    `;
}

/**
 * Cancels the edit mode and restores the original row content.
 */
function cancelCustomerEdit(tr) {
    if (tr.dataset.originalHtml) {
        tr.innerHTML = tr.dataset.originalHtml;
        delete tr.dataset.originalHtml;
    }
}

/**
 * Saves the edited customer data by sending it to the API.
 */
async function saveCustomer(tr, id) {
    const saveBtn = tr.querySelector('.save-cust-btn');
    const originalBtnText = saveBtn.textContent;
    
    saveBtn.disabled = true;
    saveBtn.textContent = '⏳…';

    const formData = new FormData();
    formData.append('customer_id', id);
    tr.querySelectorAll('.edit-cust-field').forEach(f => {
        if (f.name) formData.append(f.name, f.value);
    });

    try {
        const response = await fetch(CONFIG.API_ENDPOINTS.EDIT_CUSTOMER, { method: 'POST', body: formData });
        const json = await response.json();

        if (!json.success) {
            alert(`Save failed: ${json.error || 'Unknown error'}`);
            saveBtn.disabled = false;
            saveBtn.textContent = originalBtnText;
            return;
        }

        const newTr = buildCustomerRow(json.data.customer);
        tr.replaceWith(newTr);
    } catch (error) {
        console.error('Customer save error:', error);
        alert('Network error — changes were not saved.');
        saveBtn.disabled = false;
        saveBtn.textContent = originalBtnText;
    }
}

// ─── DELETE ──────────────────────────────────────────────────────────────────

/**
 * Handles the delete button click with a confirmation prompt.
 */
async function onCustomerDeleteClick(id, label) {
    if (!confirm(`Remove "${label}" from the Rolodex?\n\nNote: Customers with existing orders cannot be deleted.`)) return;

    const formData = new FormData();
    formData.append('customer_id', id);

    try {
        const response = await fetch(CONFIG.API_ENDPOINTS.DELETE_CUSTOMER, { method: 'POST', body: formData });
        const json = await response.json();

        if (!json.success) {
            alert(`Delete failed: ${json.error || 'Unknown error'}`);
            return;
        }

        const tr = document.querySelector(`tr[data-cid="${id}"]`);
        if (tr) {
            tr.style.transition = 'opacity 0.3s, transform 0.3s';
            tr.style.opacity = '0';
            tr.style.transform = 'translateX(20px)';
            setTimeout(() => tr.remove(), 300);
        }
    } catch (error) {
        console.error('Customer delete error:', error);
        alert('Network error — contact was not deleted.');
    }
}

// ─── ROW BUILDER ─────────────────────────────────────────────────────────────

/**
 * Builds a display <tr> element from a customer data object.
 */
function buildCustomerRow(c) {
    const tr = document.createElement('tr');
    tr.dataset.cid = c.customer_id;

    const statusColor = c.lead_status === 'Active Customer' ? 'var(--btn-success-bg)'
                      : c.lead_status === 'New Lead'        ? '#f39c12'
                      : 'var(--text-secondary)';

    tr.innerHTML = `
        <td class="font-bold">
            <a href="customer_view.php?id=${c.customer_id}" class="text-accent no-underline">
                ${esc(c.company_name || 'N/A')}
            </a>
        </td>
        <td>${esc(c.contact_person)}</td>
        <td>${c.email ? `<a href="mailto:${esc(c.email)}" class="text-sm">${esc(c.email)}</a>` : '<span class="text-secondary text-sm">-</span>'}</td>
        <td>${c.phone ? `<span class="text-sm">${esc(c.phone)}</span>` : '<span class="text-secondary text-sm">-</span>'}</td>
        <td><span style="background:${statusColor}; color:#fff; padding:2px 8px; border-radius:4px; font-size:0.8rem; font-weight:bold;">${esc(c.lead_status)}</span></td>
        <td class="text-xs text-secondary whitespace-nowrap overflow-hidden text-ellipsis" style="max-width:200px;">${esc(c.notes || '-')}</td>
        <td class="text-xs text-secondary">${fmtDate(c.created_at)}</td>
        <td class="whitespace-nowrap">
            <button class="btn edit-customer-btn" data-id="${c.customer_id}"
                    style="font-size:0.75rem; padding:5px 10px; background:var(--bg-page); border:1px solid var(--border-color); color:var(--text-main); margin-right:4px;">✏️ Edit</button>
            <button class="btn btn-danger delete-customer-btn" data-id="${c.customer_id}"
                    data-label="${esc(c.company_name || c.contact_person)}"
                    style="font-size:0.75rem; padding:5px 10px;">🗑 Del</button>
        </td>
    `;
    return tr;
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

function fmtDate(ts) {
    if (!ts) return '—';
    const d = new Date(ts);
    return isNaN(d.getTime()) ? '—' : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
