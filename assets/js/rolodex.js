/**
 * assets/js/rolodex.js
 * Drives edit and delete functionality on rolodex.php.
 *
 * Features:
 *  - Inline Edit: transforms a customer row into editable input fields
 *  - Delete: confirm prompt → fetch api/delete_customer.php → removes row
 */
'use strict';

function attachRolodexListeners() {
    document.querySelectorAll('.edit-customer-btn').forEach(btn => {
        btn.removeEventListener('click', onCustomerEditClick);
        btn.addEventListener('click', onCustomerEditClick);
    });
    document.querySelectorAll('.delete-customer-btn').forEach(btn => {
        btn.removeEventListener('click', onCustomerDeleteClick);
        btn.addEventListener('click', onCustomerDeleteClick);
    });
}

attachRolodexListeners();

// ─── EDIT ────────────────────────────────────────────────────────────────────

function onCustomerEditClick(e) {
    const id = parseInt(e.target.dataset.id);
    const tr = e.target.closest('tr');

    // Fetch fresh data for the edit form
    fetch('api/get_customer.php?id=' + id)
        .then(r => r.json())
        .then(json => {
            if (!json.success) { alert('Could not load customer: ' + json.error); return; }
            openCustomerEditRow(tr, json.data.customer);
        })
        .catch(() => alert('Network error.'));
}

function openCustomerEditRow(tr, c) {
    const originalHTML = tr.innerHTML;

    const statusOptions = ['Active Customer', 'New Lead', 'Inactive']
        .map(s => `<option value="${s}" ${c.lead_status === s ? 'selected' : ''}>${s}</option>`)
        .join('');

    tr.innerHTML = `
        <td>
            <input type="text" class="edit-cust-field" name="company_name"
                   value="${esc(c.company_name || '')}" placeholder="Company Name"
                   style="width:130px;padding:6px;">
        </td>
        <td>
            <input type="text" class="edit-cust-field" name="contact_person"
                   value="${esc(c.contact_person || '')}" placeholder="Contact Person"
                   style="width:120px;padding:6px;">
        </td>
        <td>
            <input type="email" class="edit-cust-field" name="email"
                   value="${esc(c.email || '')}" placeholder="Email"
                   style="width:150px;padding:6px;">
        </td>
        <td>
            <input type="text" class="edit-cust-field" name="phone"
                   value="${esc(c.phone || '')}" placeholder="Phone"
                   style="width:110px;padding:6px;">
        </td>
        <td>
            <select class="edit-cust-field" name="lead_status" style="padding:6px;width:140px;">
                ${statusOptions}
            </select>
        </td>
        <td>
            <input type="text" class="edit-cust-field" name="notes"
                   value="${esc(c.notes || '')}" placeholder="Notes"
                   style="width:140px;padding:6px;">
        </td>
        <td style="font-size:0.85rem;color:var(--text-secondary);">${fmtDate(c.created_at)}</td>
        <td style="white-space:nowrap;">
            <button class="btn btn-success save-cust-btn" data-id="${c.customer_id}"
                    style="font-size:0.75rem;padding:5px 10px;margin-right:4px;">💾 Save</button>
            <button class="btn cancel-cust-btn"
                    style="font-size:0.75rem;padding:5px 10px;background:var(--bg-page);border:1px solid var(--border-color);color:var(--text-main);">✕ Cancel</button>
        </td>
    `;

    tr.querySelector('.save-cust-btn').addEventListener('click', () => saveCustomer(tr, c.customer_id));
    tr.querySelector('.cancel-cust-btn').addEventListener('click', () => {
        tr.innerHTML = originalHTML;
        attachRolodexListeners();
    });
}

function saveCustomer(tr, id) {
    const saveBtn       = tr.querySelector('.save-cust-btn');
    saveBtn.disabled    = true;
    saveBtn.textContent = '⏳…';

    const formData = new FormData();
    formData.append('customer_id', id);
    tr.querySelectorAll('.edit-cust-field').forEach(f => {
        if (f.name) formData.append(f.name, f.value);
    });

    fetch('api/edit_customer.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(json => {
            if (!json.success) {
                alert('Save failed: ' + (json.error || 'Unknown error'));
                saveBtn.disabled    = false;
                saveBtn.textContent = '💾 Save';
                return;
            }
            const newTr = buildCustomerRow(json.data.customer);
            tr.replaceWith(newTr);
            attachRolodexListeners();
        })
        .catch(() => {
            alert('Network error — changes were not saved.');
            saveBtn.disabled    = false;
            saveBtn.textContent = '💾 Save';
        });
}

// ─── DELETE ──────────────────────────────────────────────────────────────────

function onCustomerDeleteClick(e) {
    const id    = parseInt(e.target.dataset.id);
    const label = e.target.dataset.label;

    if (!confirm(`Remove "${label}" from the Rolodex?\n\nNote: Customers with existing orders cannot be deleted.`)) return;

    const formData = new FormData();
    formData.append('customer_id', id);

    fetch('api/delete_customer.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(json => {
            if (!json.success) {
                alert('Delete failed: ' + (json.error || 'Unknown error'));
                return;
            }
            const tr = document.querySelector(`tr[data-cid="${id}"]`);
            if (tr) {
                tr.style.transition = 'opacity 0.3s';
                tr.style.opacity    = '0';
                setTimeout(() => tr.remove(), 300);
            }
        })
        .catch(() => alert('Network error — contact was not deleted.'));
}

// ─── ROW BUILDER (for post-save re-render) ───────────────────────────────────

function buildCustomerRow(c) {
    const tr = document.createElement('tr');
    tr.dataset.cid = c.customer_id;

    const statusColor = c.lead_status === 'Active Customer' ? 'var(--btn-success-bg)'
                      : c.lead_status === 'New Lead'        ? '#f39c12'
                      : 'var(--text-secondary)';

    tr.innerHTML = `
        <td style="font-weight:bold;">
            <a href="customer_view.php?id=${c.customer_id}" style="color:var(--accent-color); text-decoration:none;">
                ${esc(c.company_name || 'N/A')}
            </a>
        </td>
        <td>${esc(c.contact_person)}</td>
        <td>${c.email ? `<a href="mailto:${esc(c.email)}" style="font-size:0.9rem;">${esc(c.email)}</a>` : '<span style="color:var(--text-secondary);font-size:0.9rem;">-</span>'}</td>
        <td>${c.phone ? `<span style="font-size:0.9rem;">${esc(c.phone)}</span>` : '<span style="color:var(--text-secondary);font-size:0.9rem;">-</span>'}</td>
        <td><span style="background:${statusColor};color:#fff;padding:2px 8px;border-radius:4px;font-size:0.8rem;font-weight:bold;">${esc(c.lead_status)}</span></td>
        <td style="max-width:200px;font-size:0.85rem;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(c.notes || '-')}</td>
        <td style="font-size:0.85rem;color:var(--text-secondary);">${fmtDate(c.created_at)}</td>
        <td style="white-space:nowrap;">
            <button class="btn edit-customer-btn" data-id="${c.customer_id}"
                    style="font-size:0.75rem;padding:5px 10px;background:var(--bg-page);border:1px solid var(--border-color);color:var(--text-main);margin-right:4px;">✏️ Edit</button>
            <button class="btn btn-danger delete-customer-btn" data-id="${c.customer_id}"
                    data-label="${esc(c.company_name || c.contact_person)}"
                    style="font-size:0.75rem;padding:5px 10px;">🗑 Del</button>
        </td>
    `;
    return tr;
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────

function esc(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function pad(n, len) { return String(n).padStart(len, '0'); }
function fmtDate(ts) {
    if (!ts) return '—';
    const d = new Date(ts);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
