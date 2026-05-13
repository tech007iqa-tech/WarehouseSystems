/**
 * IQA Metal — Customer Registry Logic
 * Handled safely with modern ES6+ standards.
 */

/**
 * @typedef {Object} Order
 * @property {string} order_id
 * @property {string} created_at
 * @property {string} status
 */

/**
 * @typedef {Object} Customer
 * @property {string} customer_id
 * @property {string} company_name
 * @property {string} website
 * @property {string} contact_person
 * @property {string} address
 * @property {string} email
 * @property {string} phone
 * @property {string} shipping_address
 * @property {string} internal_notes
 * @property {string} callback_date
 * @property {string} message_date
 * @property {Order[]} orders_list
 */

/**
 * Displays customer details in the sidebar
 * @param {HTMLElement} el
 */
function showDetails(el) {
    const cards = document.getElementsByClassName('cust-card');
    for (let c of cards) {
        c.classList.remove('active');
    }
    el.classList.add('active');

    const rawData = el.getAttribute('data-customer');
    if (!rawData) return;

    try {
        const data = JSON.parse(rawData);
        renderDetailView(data);
    } catch (e) {
        console.error("Failed to parse customer data", e);
    }
}

/**
 * Escapes HTML to prevent XSS
 * @param {string|null|undefined} str
 * @returns {string}
 */
const escapeHTML = (str) => {
    if (!str) return '—';
    return str.toString().replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[m]));
};

/**
 * Renders the detail view in the sidebar
 * @param {Customer} data
 */
function renderDetailView(data) {
    const side = document.getElementById('side-details');
    if (!side) return;

    const historyStatuses = ['finalized', 'paid', 'dispatched', 'canceled'];
    const drafts = (data.orders_list || []).filter(o => !historyStatuses.includes(o.status.toLowerCase()));
    const history = (data.orders_list || []).filter(o => historyStatuses.includes(o.status.toLowerCase()));

    const currencyFormatter = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

    side.innerHTML = `
        <div class="detail-box">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 25px;">
                <div class="detail-item" style="margin:0;">
                    <div class="detail-label">Billing Account</div>
                    <div class="detail-value text-main" style="font-size: 1.4rem; letter-spacing: -0.02em;">${escapeHTML(data.company_name)}</div>
                    <div style="margin-top: 6px; font-size: 0.75rem; font-family: monospace; background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 8px; display: inline-block; font-weight: 700; letter-spacing: 0.05em;">${escapeHTML(data.customer_id)}</div>
                </div>
                <button onclick='handleEditClick(${JSON.stringify(data).replace(/'/g, "&apos;")})' class="btn-view-cust" title="Edit Account" style="width: 44px; height: 44px; font-size: 1.2rem;">✎</button>
            </div>

            <!-- Stats Row -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px;">
                <div class="stat-chip">
                    <span class="stat-value">${currencyFormatter.format(data.lifetime_value || 0)}</span>
                    <span class="stat-label">Lifetime Value</span>
                </div>
                <div class="stat-chip">
                    <span class="stat-value">${data.completed_count || 0}</span>
                    <span class="stat-label">Completed</span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; border-bottom: 1px dashed var(--border-color); padding-bottom: 20px; margin-bottom: 25px;">
                <div class="detail-item" style="margin:0;">
                    <div class="detail-label" style="display: flex; align-items: center; gap: 5px;">📅 Next Callback</div>
                    <div class="detail-value" style="font-weight: 800; color: var(--accent-color);">${data.callback_date ? escapeHTML(data.callback_date) : 'Not Set'}</div>
                </div>
                <div class="detail-item" style="margin:0;">
                    <div class="detail-label" style="display: flex; align-items: center; gap: 5px;">✉️ Last Contact</div>
                    <div class="detail-value" style="font-weight: 800;">${data.message_date ? escapeHTML(data.message_date) : 'Not Set'}</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 20px; margin-bottom: 10px;">
                <div class="detail-item">
                    <div class="detail-label">Primary Contact</div>
                    <div class="detail-value" style="font-size: 0.9rem;">${escapeHTML(data.contact_person)}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Website</div>
                    <div class="detail-value">${data.website ? `<a href="${escapeHTML(data.website)}" target="_blank" style="color: var(--accent-color); text-decoration:none; display: flex; align-items: center; gap: 4px;">Visit ↗</a>` : '—'}</div>
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-label">Active Batch Pipeline</div>
                <div id="side-drafts" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 25px;">
                    ${drafts.length > 0 ? drafts.map(o => `
                        <div style="display:flex; align-items:center; gap:10px;">
                            <a href="index.php?customer_id=${encodeURIComponent(data.customer_id)}&order_id=${encodeURIComponent(o.order_id)}"
                               class="order-row-link" style="flex:1;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">${o.order_id}</div>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px;">${o.created_at}</div>
                                </div>
                                <div style="text-align: right; display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
                                    <span class="badge status-active" style="font-size: 0.65rem;">${o.total_qty || 0} Items</span>
                                    <div style="font-weight: 800; font-size: 0.9rem;">${currencyFormatter.format(o.total_value || 0)}</div>
                                </div>
                            </a>
                            <form method="POST" onsubmit="return confirm('Delete this batch permanently?')" style="margin:0;">
                                <input type="hidden" name="action" value="delete_order">
                                <input type="hidden" name="order_id" value="${o.order_id}">
                                <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:1.2rem; opacity:0.3; transition:opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.3">🗑️</button>
                            </form>
                        </div>
                    `).join('') : '<div class="empty-state" style="padding: 20px; font-size: 0.8rem; border-radius: 12px; background: #f8fafc; border: 1px dashed #e2e8f0; color: #94a3b8;">No active batches.</div>'}
                </div>
            </div>

            <div class="detail-item">
                <div class="detail-label">Fulfillment History</div>
                <div id="side-completed" style="display: flex; flex-direction: column; gap: 10px;">
                    ${history.length > 0 ? history.map(o => `
                        <div style="display:flex; align-items:center; gap:10px;">
                            <a href="checkout.php?customer_id=${encodeURIComponent(data.customer_id)}&order_id=${encodeURIComponent(o.order_id)}"
                               class="order-row-link completed" style="flex:1;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 700; font-size: 0.9rem; color: #64748b;">${o.order_id}</div>
                                    <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">${o.created_at}</div>
                                </div>
                                <div style="text-align: right; display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
                                    <span class="badge badge-completed" style="font-size: 0.65rem; opacity: 0.8;">${o.total_qty || 0} Items</span>
                                    <div style="font-weight: 700; font-size: 0.9rem; color: #64748b;">${currencyFormatter.format(o.total_value || 0)}</div>
                                </div>
                            </a>
                            <form method="POST" onsubmit="return confirm('Delete this completed order permanently?')" style="margin:0;">
                                <input type="hidden" name="action" value="delete_order">
                                <input type="hidden" name="order_id" value="${o.order_id}">
                                <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:1.2rem; opacity:0.1; transition:opacity 0.2s;" onmouseover="this.style.opacity=0.6" onmouseout="this.style.opacity=0.1">🗑️</button>
                            </form>
                        </div>
                    `).join('') : '<div class="empty-state" style="padding: 20px; font-size: 0.8rem; border-radius: 12px; background: #f8fafc; border: 1px dashed #e2e8f0; color: #94a3b8;">No completion history.</div>'}
                </div>
            </div>

            <div class="detail-item" style="background: #f8fafc; padding: 16px; border-radius: 12px; margin-top: 15px; border: 1px solid var(--border-color);">
                <div class="detail-label" style="display: flex; align-items: center; gap: 5px; color: var(--text-main); font-size: 0.65rem; opacity: 0.8;">📜 Internal CRM Notes</div>
                <div class="detail-value" style="font-size: 0.9rem; white-space: pre-wrap; color: var(--text-secondary); line-height: 1.5; font-weight: 500; margin-top: 6px;">${data.internal_notes ? escapeHTML(data.internal_notes) : '<i style="opacity:0.4;">No internal notes recorded.</i>'}</div>
            </div>

            <div style="padding-top: 20px; padding-bottom: 20px; display: flex; flex-direction: column; gap: 10px;">
                <a href="index.php?customer_id=${encodeURIComponent(data.customer_id)}&action=create_new_order" class="btn-register" style="display:flex; align-items:center; justify-content:center; gap: 10px; height: 54px; font-size: 1rem; margin: 0;">
                    <span>+</span> Create New Fresh Batch
                </a>
                <button type="button" onclick="openImportModal('${escapeHTML(data.customer_id)}')" class="btn-register" style="display:flex; align-items:center; justify-content:center; gap: 10px; height: 54px; font-size: 1rem; background: #f8fafc; color: var(--text-main); border: 1px dashed #cbd5e1; margin: 0;">
                    <span>📋</span> Import from Clipboard
                </button>
            </div>
        </div>
    `;
}

/**
 * Intermediary to avoid nested inline JSON issues
 * @param {Customer} data
 */
function handleEditClick(data) {
    renderEditView(data);
}

/**
 * Renders the edit view in the sidebar
 * @param {Customer} data
 */
function renderEditView(data) {
    const side = document.getElementById('side-details');
    if (!side) return;

    side.innerHTML = `
        <form method="POST" class="detail-box">
            <input type="hidden" name="action" value="edit_customer">
            <input type="hidden" name="customer_id" value="${data.customer_id}">

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h3 style="font-size:0.8rem; text-transform:uppercase; color:var(--text-secondary); letter-spacing:0.1em; font-weight:800;">Edit Account Details</h3>
                <button type="button" onclick='handleCancelEdit(${JSON.stringify(data).replace(/'/g, "&apos;")})' class="btn-view-cust">✖</button>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label for="edit-company-name">Company Name</label>
                <input type="text" id="edit-company-name" name="company_name" value="${escapeHTML(data.company_name)}" style="height:38px; font-size:0.85rem;" required>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom:12px;">
                <div class="form-group">
                    <label for="edit-contact">Contact Person</label>
                    <input type="text" id="edit-contact" name="contact_person" value="${escapeHTML(data.contact_person)}" style="height:38px; font-size:0.85rem;">
                </div>
                <div class="form-group">
                    <label for="edit-website">Website</label>
                    <input type="text" id="edit-website" name="website" value="${escapeHTML(data.website)}" style="height:38px; font-size:0.85rem;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom:12px;">
                <div class="form-group">
                    <label for="edit-email">Email</label>
                    <input type="email" id="edit-email" name="email" value="${escapeHTML(data.email)}" style="height:38px; font-size:0.85rem;">
                </div>
                <div class="form-group">
                    <label for="edit-phone">Phone</label>
                    <input type="text" id="edit-phone" name="phone" value="${escapeHTML(data.phone)}" style="height:38px; font-size:0.85rem;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom:12px;">
                <div class="form-group">
                    <label for="edit-callback">Next Callback</label>
                    <input type="date" id="edit-callback" name="callback_date" value="${escapeHTML(data.callback_date)}" style="height:38px; font-size:0.85rem; padding-right:10px;">
                </div>
                <div class="form-group">
                    <label for="edit-msg-date">Last Message Date</label>
                    <input type="date" id="edit-msg-date" name="message_date" value="${escapeHTML(data.message_date)}" style="height:38px; font-size:0.85rem; padding-right:10px;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label for="edit-address">Business Address</label>
                <input type="text" id="edit-address" name="address" value="${escapeHTML(data.address)}" style="height:38px; font-size:0.85rem;">
            </div>

            <div class="form-group" style="margin-bottom:12px;">
                <label for="edit-ship-addr">Shipping Address</label>
                <input type="text" id="edit-ship-addr" name="shipping_address" value="${escapeHTML(data.shipping_address)}" style="height:38px; font-size:0.85rem;">
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label for="edit-notes">Internal Notes</label>
                <textarea id="edit-notes" name="internal_notes" class="detail-notes" style="width:100%; min-height:80px;">${escapeHTML(data.internal_notes)}</textarea>
            </div>

            <button type="submit" class="btn-main" style="width:100%; padding:14px; border-radius:12px; background:var(--text-main); color:white; font-weight:800; border:none; cursor:pointer;">💾 Save Account Changes</button>
        </form>

        <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #fee2e2;">
            <form method="POST" onsubmit="return confirm('⚠️ DANGER ZONE: This will permanently delete this customer and ALL their order history. This cannot be undone. Proceed?')">
                <input type="hidden" name="action" value="delete_customer">
                <input type="hidden" name="customer_id" value="${data.customer_id}">
                <button type="submit" style="width:100%; padding:12px; border-radius:12px; background:#fef2f2; color:#b91c1c; font-weight:700; border:1px solid #fecdd3; cursor:pointer; font-size:0.85rem; transition: all 0.2s;" onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                    🗑️ Delete Account Permanently
                </button>
            </form>
        </div>
    `;
}

/**
 * Handles cancelling the edit view
 * @param {Customer} data
 */
function handleCancelEdit(data) {
    renderDetailView(data);
}

/**
 * Filters the customer list based on search input
 */
function filterCustomers() {
    const input = document.getElementById('cust-search');
    if (!input) return;

    const filter = input.value.toLowerCase();
    const cards = document.getElementsByClassName('cust-card');

    for (let i = 0; i < cards.length; i++) {
        const search = cards[i].getAttribute('data-search')?.toLowerCase() || "";
        cards[i].style.display = search.includes(filter) ? "" : "none";
    }
}

/**
 * Shows the customer profile modal
 * @param {Event} event
 * @param {Customer} data
 */
function showProfile(event, data) {
    if (event) event.stopPropagation();

    const modal = document.getElementById('profile-modal');
    const content = document.getElementById('profile-content');
    if (!modal || !content) return;

    const initial = data.company_name.charAt(0).toUpperCase();

    content.innerHTML = `
        <div class="profile-header">
            <div class="profile-avatar">${initial}</div>
            <div class="profile-info">
                <h1>${escapeHTML(data.company_name)}</h1>
                <span class="profile-id">${escapeHTML(data.customer_id)}</span>
            </div>
        </div>

        <div class="profile-grid">
            <div class="profile-field">
                <div class="profile-field-label">Contact Person</div>
                <div class="profile-field-value">${escapeHTML(data.contact_person)}</div>
            </div>
            <div class="profile-field">
                <div class="profile-field-label">Phone Number</div>
                <div class="profile-field-value">${escapeHTML(data.phone)}</div>
            </div>
            <div class="profile-field">
                <div class="profile-field-label">Email Address</div>
                <div class="profile-field-value">${escapeHTML(data.email)}</div>
            </div>
            <div class="profile-field">
                <div class="profile-field-label">Website</div>
                <div class="profile-field-value">${data.website ? `<a href="${escapeHTML(data.website)}" target="_blank" style="color:var(--accent-color);">${escapeHTML(data.website)}</a>` : '—'}</div>
            </div>
        </div>

        <div class="profile-field" style="margin-bottom: 20px;">
            <div class="profile-field-label">Primary Office Address</div>
            <div class="profile-field-value" style="font-size: 0.9rem;">${escapeHTML(data.address)}</div>
        </div>

        <div class="profile-field-label">Internal CRM Briefing</div>
        <div class="profile-notes">
            ${data.internal_notes ? escapeHTML(data.internal_notes) : '<i>No special notes recorded for this account.</i>'}
        </div>
    `;

    modal.classList.add('active');
    document.body.style.overflow = 'hidden'; // Lock background scroll
}

/**
 * Closes the customer profile modal
 */
function closeProfile() {
    const modal = document.getElementById('profile-modal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}
/**
 * Auto-select customer from localStorage on load (Bridge from Batch Registry)
 */
window.addEventListener('load', () => {
    const targetId = localStorage.getItem('active_customer_id');
    
    if (targetId) {
        localStorage.removeItem('active_customer_id');
        const cards = document.getElementsByClassName('cust-card');
        for (let card of cards) {
            const rawData = card.getAttribute('data-customer');
            if (rawData) {
                try {
                    const data = JSON.parse(rawData);
                    if (data.customer_id === targetId) {
                        showDetails(card);
                        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        break;
                    }
                } catch(e) {}
            }
        }
    }
});

/**
 * Import Modal Logic
 */
let activeImportCustomerId = null;

function openImportModal(customerId) {
    activeImportCustomerId = customerId;
    const modal = document.getElementById('import-modal');
    const area = document.getElementById('import-paste-area');
    if (modal) {
        modal.classList.add('active');
        if (area) {
            area.value = '';
            area.focus();
        }
    }
}

function closeImportModal() {
    const modal = document.getElementById('import-modal');
    if (modal) modal.classList.remove('active');
    activeImportCustomerId = null;
}

// Listen for paste to show preview
document.getElementById('import-paste-area')?.addEventListener('input', function() {
    const text = this.value;
    const rows = text.trim().split('\n');
    const preview = document.getElementById('import-preview');
    const table = document.getElementById('import-preview-table');
    const count = document.getElementById('import-row-count');

    if (!text.trim()) {
        preview.style.display = 'none';
        return;
    }

    preview.style.display = 'block';
    count.innerText = rows.length;

    let html = `<thead><tr style="background:#f1f5f9; text-align:left; position:sticky; top:0; z-index:1; box-shadow:0 1px 0 #e2e8f0;"><th style="padding:8px 10px; width:25%;">Brand</th><th style="padding:8px 10px; width:45%;">Model</th><th style="padding:8px 10px; width:15%;">Qty</th><th style="padding:8px 10px; width:15%;">Price</th></tr></thead><tbody>`;
    
    rows.slice(0, 50).forEach(row => {
        const cols = row.split('\t');
        if (cols.length >= 2) {
            // Format: Type Brand Model Series CPU Description Price QTY
            const brand = cols[1] || '—';
            const model = cols[2] || '—';
            const price = cols[6] || '0';
            const qty = cols[7] || '1';
            html += `<tr><td style="padding:6px 10px; border-top:1px solid #eee; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHTML(brand)}</td><td style="padding:6px 10px; border-top:1px solid #eee; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHTML(model)}</td><td style="padding:6px 10px; border-top:1px solid #eee;">${escapeHTML(qty)}</td><td style="padding:6px 10px; border-top:1px solid #eee; font-weight:700; color:var(--accent-color);">$${escapeHTML(price)}</td></tr>`;
        }
    });
    if (rows.length > 50) html += `<tr><td colspan="4" style="text-align:center; padding:10px; color:#94a3b8; font-style:italic; background:white;">... and ${rows.length - 50} more rows</td></tr>`;
    html += '</tbody>';
    table.innerHTML = html;
});

async function processImport() {
    const area = document.getElementById('import-paste-area');
    const btn = document.getElementById('btn-submit-import');
    if (!area || !area.value.trim() || !activeImportCustomerId) return;

    const originalBtnText = btn.innerHTML;
    btn.innerHTML = '⏳ Processing...';
    btn.disabled = true;

    // Get CSRF Token
    const stateEl = document.getElementById('crm-state');
    const csrfToken = stateEl ? JSON.parse(stateEl.textContent).csrf_token : '';

    const rows = area.value.trim().split('\n').map(r => r.split('\t'));
    
    try {
        const response = await fetch('api/bulk_update_orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'bulk_import',
                csrf_token: csrfToken,
                customer_id: activeImportCustomerId,
                rows: rows
            })
        });

        const result = await response.json();
        if (result.success) {
            btn.innerHTML = '✅ Success!';
            setTimeout(() => {
                window.location.href = `index.php?customer_id=${encodeURIComponent(activeImportCustomerId)}&order_id=${encodeURIComponent(result.order_id)}`;
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
