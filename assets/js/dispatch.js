/**
 * assets/js/dispatch.js
 * Specialized engine for the Dispatch Desk view.
 * Extends the core labels logic but focuses on Sold item attributes.
 */
'use strict';

(function() {
    // ─── CONFIGURATION ────────────────────────────────────────────────────────
    const CONFIG = {
        API_ENDPOINTS: {
            GET_LABELS: 'api/get_dispatch_data.php',
            DELETE_LABEL: 'api/delete_label.php',
            PRINT_LABEL: 'print_label.php'
        }
    };

    const DOM = {
        filterSearch: document.getElementById('filterSearch'),
        filterStatus: document.getElementById('filterStatus'),
        filterMsg: document.getElementById('filterMsg'),
        tbody: document.getElementById('inventoryTableBody'),
        viewArchiveBtn: document.getElementById('viewArchiveBtn')
    };

    let filterTimer = null;
    let archiveMode = 90;

    /**
     * Builds a Dispatch-focused <tr> element.
     */
    function buildDispatchRow(item) {
        const template = document.getElementById('inventoryRowTemplate');
        const clone = document.importNode(template.content, true);
        const tr = clone.querySelector('tr');
        tr.dataset.id = item.id;

        const brand = item.brand || '';
        const model = item.model || '';
        const series = item.series || '';
        const sn = item.serial_number || '';

        // Item Header
        const link = tr.querySelector('.tpl-link');
        // If the original item is gone, we land on a 404 or warning, but usually we link to the template
        link.href = `hardware_view.php?id=${item.original_item_id}`;
        link.textContent = `${brand} ${model}`;
        tr.querySelector('.tpl-series').textContent = series;

        // Serial Number
        const snText = tr.querySelector('.tpl-sn-text');
        const snEmpty = tr.querySelector('.tpl-sn-empty');
        if (sn) {
            snText.textContent = `S/N: ${sn}`;
        } else {
            snText.style.display = 'none';
            snEmpty.style.display = 'inline';
        }

        // Buyer & Order
        tr.querySelector('.tpl-buyer-name').textContent = item.buyer_name || 'Anonymous Buyer';
        tr.querySelector('.tpl-order-num').textContent = item.buyer_order_num || 'Direct Sale';

        // Status Badge Styling
        const badge = tr.querySelector('.tpl-status-badge');
        const status = item.invoice_status || 'Active';
        badge.textContent = status;
        badge.className = 'tpl-status-badge status-tag';
        
        switch(status.toLowerCase()) {
            case 'paid': badge.classList.add('status-paid'); break;
            case 'dispatched': badge.classList.add('status-dispatched'); break;
            case 'canceled': badge.classList.add('status-canceled'); break;
            case 'active': badge.classList.add('status-active'); break;
            case 'pending': badge.classList.add('status-pending'); break;
        }

        // CPU & Specs (From snapshot)
        tr.querySelector('.tpl-cpu-gen').textContent = item.cpu_gen || '—';
        tr.querySelector('.tpl-cpu-specs').textContent = item.description || '';
        tr.querySelector('.tpl-ram').textContent = item.ram || '—';
        tr.querySelector('.tpl-storage').textContent = item.storage || '—';

        // Price
        const price = parseFloat(item.sale_price || 0);
        tr.querySelector('.tpl-price').textContent = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(price);

        // Sold Date (from updated_at)
        tr.querySelector('.tpl-added').textContent = fmtDateLocal(item.updated_at);

        // Action Data - Use original_item_id for reprinting the master template label if needed
        tr.querySelector('.reprint-btn').dataset.id = item.original_item_id;
        const openBtn = tr.querySelector('.open-label-btn');
        openBtn.dataset.id = item.original_item_id;
        openBtn.dataset.brand = brand;
        openBtn.dataset.model = model;

        const delBtn = tr.querySelector('.delete-btn');
        delBtn.dataset.id = item.id; // Deleting from dispatch uses the line_id
        delBtn.dataset.label = `${brand} ${model} (#${item.buyer_order_num})`;

        return tr;
    }

    /**
     * Specialized Filter Logic for Sold Items.
     */
    async function runDispatchFilter() {
        const query = DOM.filterSearch.value.trim();
        DOM.filterMsg.textContent = 'Searching Dispatch Records…';

        try {
            const status = DOM.filterStatus ? DOM.filterStatus.value : '';
            const url = `${CONFIG.API_ENDPOINTS.GET_LABELS}?q=${encodeURIComponent(query)}&archive=${archiveMode}&status=${encodeURIComponent(status)}`;
            const response = await fetch(url);
            const json = await response.json();

            if (!json.success) return;

            DOM.tbody.innerHTML = '';

            if (json.data.length === 0) {
                DOM.tbody.innerHTML = `<tr><td colspan="6" class="text-center empty-table-message">No sold records found.</td></tr>`;
            } else {
                DOM.filterMsg.textContent = `${json.data.length} records found`;
                const fragment = document.createDocumentFragment();
                json.data.forEach(item => fragment.appendChild(buildDispatchRow(item)));
                DOM.tbody.appendChild(fragment);
            }
        } catch (e) {
            console.error(e);
        }
    }

    // Event Listeners
    DOM.filterSearch.addEventListener('input', () => {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(runDispatchFilter, 300);
    });
    
    if (DOM.filterStatus) {
        DOM.filterStatus.addEventListener('change', runDispatchFilter);
    }

    DOM.viewArchiveBtn.addEventListener('click', () => {
        if (archiveMode === 90) {
            archiveMode = 0;
            DOM.viewArchiveBtn.textContent = '⏱ View Last 90 Days';
            DOM.viewArchiveBtn.classList.add('active');
        } else {
            archiveMode = 90;
            DOM.viewArchiveBtn.textContent = '📦 View Full Archive';
            DOM.viewArchiveBtn.classList.remove('active');
        }
        runDispatchFilter();
    });

    // Delegate Actions
    DOM.tbody.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn');
        if (!btn) return;
        const id = btn.dataset.id;
        const tr = btn.closest('tr');

        if (btn.classList.contains('reprint-btn')) {
            window.open(`${CONFIG.API_ENDPOINTS.PRINT_LABEL}?id=${id}`, '_blank');
        } else if (btn.classList.contains('open-label-btn')) {
            // Re-using global flashOpenLabel if exists
            if (window.flashOpenLabel) window.flashOpenLabel(id, btn.dataset.brand, btn.dataset.model, btn);
        } else if (btn.classList.contains('delete-btn')) {
            onDeleteRecord(id, btn.dataset.label, tr);
        }
    });

    /**
     * Confirm removal of a record from dispatch.
     */
    async function onDeleteRecord(id, label, tr) {
        if (!confirm(`Permanently remove record for "${label}"? This will not affect the Sales History DB.`)) return;

        const formData = new FormData();
        formData.append('id', id);

        try {
            const res = await fetch(CONFIG.API_ENDPOINTS.DELETE_LABEL, { method: 'POST', body: formData });
            const json = await res.json();
            if (json.success) {
                tr.style.opacity = '0';
                tr.style.transform = 'translateX(10px)';
                setTimeout(() => tr.remove(), 250);
            }
        } catch (e) { console.error(e); }
    }

    // Hydrate Initial Data
    (function init() {
        if (window.INITIAL_INVENTORY && Array.isArray(window.INITIAL_INVENTORY)) {
            DOM.tbody.innerHTML = '';
            const fragment = document.createDocumentFragment();
            window.INITIAL_INVENTORY.forEach(item => fragment.appendChild(buildDispatchRow(item)));
            DOM.tbody.appendChild(fragment);
            DOM.filterMsg.textContent = `${window.INITIAL_INVENTORY.length} items loaded`;
        }
    })();

    // Helper: Local Date Format
    function fmtDateLocal(ts) {
        if (!ts) return '—';
        const d = new Date(ts);
        return isNaN(d.getTime()) ? ts : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

})();
