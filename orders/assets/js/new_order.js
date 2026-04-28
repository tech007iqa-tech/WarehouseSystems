/**
 * IQA Metal — Batch Builder Logic
 * Merged & Modernized into a single external module.
 */

/* ============================================================
   1. Data Definitions
   ============================================================ */

/* ============================================================
   2. Main Initialization
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
    initFormDatalists();
    initSummarySearch();
});

function initFormDatalists() {
    const brandSelect = document.getElementById('brand');
    const modelDatalist = document.getElementById('model-options');
    const seriesDatalist = document.getElementById('series-options');
    const cpuDatalist = document.getElementById('cpu-options');
    const modelInput = document.getElementById('models');
    const seriesInput = document.getElementById('series');

    // Populate CPU list once
    if (cpuDatalist) {
        cpuDatalist.innerHTML = cpuGenerations.map(cpu => `<option value="${cpu}">`).join('');
    }

    if (brandSelect) {
        brandSelect.addEventListener('change', (e) => {
            const selectedBrand = e.target.value;
            const data = IQA_Inventory[selectedBrand];

            if (modelInput) modelInput.value = '';
            if (seriesInput) seriesInput.value = '';

            if (modelDatalist) modelDatalist.innerHTML = '';
            if (seriesDatalist) seriesDatalist.innerHTML = '';

            if (data) {
                if (seriesDatalist) seriesDatalist.innerHTML = data.series.map(s => `<option value="${s}">`).join('');
                if (modelDatalist) modelDatalist.innerHTML = data.models.map(m => `<option value="${m}">`).join('');
            }
        });
    }
}

function initSummarySearch() {
    const searchInput = document.getElementById('summary-search');
    if (searchInput) {
        searchInput.addEventListener('keyup', filterSummary);
    }
}

/* ============================================================
   3. Summary & Item Actions
   ============================================================ */

/**
 * Copies item description to clipboard
 * @param {HTMLElement} btn
 */
function copyEntry(btn) {
    const container = btn.closest('.col-desc');
    const textNode = container ? container.querySelector('.copyable-text') : null;
    if (!textNode) return;

    const text = textNode.innerText.trim();

    const performCopy = (textToCopy) => {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(textToCopy);
        } else {
            const textArea = document.createElement("textarea");
            textArea.value = textToCopy;
            Object.assign(textArea.style, { position: "fixed", left: "-9999px", top: "0" });
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try { document.execCommand('copy'); } catch (err) {}
            document.body.removeChild(textArea);
            return Promise.resolve();
        }
    };

    performCopy(text).then(() => {
        const originalText = btn.innerHTML;
        btn.innerHTML = '✅';
        btn.style.opacity = '1';
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.opacity = '0.3';
        }, 1500);
    });
}

/**
 * Toggles inline editing for quantity and price
 * @param {HTMLElement} btn
 */
function toggleInlineEdit(btn) {
    const row = btn.closest('tr');
    if (!row) return;

    const staticView = row.querySelector('.static-view');
    const editView = row.querySelector('.edit-view');

    if (staticView && editView) {
        const isEditing = staticView.style.display === 'none';
        staticView.style.display = isEditing ? 'flex' : 'none';
        editView.style.display = isEditing ? 'none' : 'block';
        btn.style.opacity = isEditing ? '0.3' : '1';
    }
}

/**
 * Filters the current order summary table
 */
function filterSummary() {
    const input = document.getElementById('summary-search');
    if (!input) return;

    const filter = input.value.toLowerCase();
    const rows = document.getElementsByClassName('summary-item-row');

    for (let i = 0; i < rows.length; i++) {
        const text = rows[i].innerText.toLowerCase();
        rows[i].style.display = text.includes(filter) ? "" : "none";
    }
}

/* ============================================================
   4. Warehouse Integration
   ============================================================ */

function openWarehouseModal() {
    const modal = document.getElementById('wh-modal');
    if (modal) {
        modal.style.display = 'flex';
        const searchInput = document.getElementById('wh-modal-search');
        if (searchInput) searchInput.focus();
    }
}

function closeWarehouseModal() {
    const modal = document.getElementById('wh-modal');
    if (modal) modal.style.display = 'none';
}

async function searchWarehouseItems() {
    const qInput = document.getElementById('wh-modal-search');
    const sSelect = document.getElementById('wh-modal-sector');
    const resultsDiv = document.getElementById('wh-results');

    if (!qInput || !sSelect || !resultsDiv) return;

    const q = qInput.value;
    const sector = sSelect.value;

    if (q.length < 2 && q.length > 0) return;

    try {
        const response = await fetch(`api/get_warehouse_stock.php?q=${encodeURIComponent(q)}&sector=${encodeURIComponent(sector)}`);
        const items = await response.json();

        resultsDiv.innerHTML = '';
        if (!items || items.length === 0) {
            resultsDiv.innerHTML = '<div style="grid-column:1/-1; padding:40px; text-align:center; color:#94a3b8;">No matching stock found.</div>';
            return;
        }

        items.forEach(item => {
            const card = document.createElement('div');
            card.className = 'wh-result-card'; // Added class for easier styling if needed
            Object.assign(card.style, {
                background: '#f8fafc',
                border: '1px solid #e2e8f0',
                borderOrigin: 'padding-box',
                borderRadius: '16px',
                padding: '15px',
                cursor: 'pointer',
                transition: 'all 0.2s'
            });

            card.onmouseover = () => card.style.background = '#f1f5f9';
            card.onmouseout = () => card.style.background = '#f8fafc';
            card.onclick = () => selectWarehouseItem(item);

            const specsStr = Object.entries(item.specs || {}).map(([k,v]) => `${k.toUpperCase()}: ${v}`).join(' | ');

            card.innerHTML = `
                <div style="font-size:0.65rem; color:#64748b; font-weight:800; text-transform:uppercase;">LOC: ${item.location_code}</div>
                <div style="font-weight:800; font-size:1.1rem; color:#0f172a; margin:4px 0;">${item.brand} ${item.model}</div>
                <div style="font-size:0.75rem; color:#475569;">${specsStr}</div>
                <div style="margin-top:10px; font-weight:700; color:var(--accent-color);">In Stock: ${item.quantity}</div>
            `;
            resultsDiv.appendChild(card);
        });
    } catch (e) {
        resultsDiv.innerHTML = '<div style="grid-column:1/-1; padding:40px; text-align:center; color:#ef4444;">Error searching stock.</div>';
    }
}

/**
 * Maps warehouse item data back to the order form
 * @param {Object} item
 */
function selectWarehouseItem(item) {
    const brandEl = document.getElementById('brand');
    const modelsEl = document.getElementById('models');
    const seriesEl = document.getElementById('series');
    const cpuEl = document.getElementById('cpu');
    const descEl = document.getElementById('description');

    if (brandEl) {
        // Modernized Selection: Find matching option case-insensitively
        const options = Array.from(brandEl.options);
        const match = options.find(opt => opt.value.toLowerCase() === (item.brand || "").toLowerCase());

        if (match) {
            brandEl.value = match.value;
        } else {
            // Check for partial matches (e.g., "Microsoft Gaming" -> "Microsoft", "HP Laptop" -> "HP")
            const partialMatch = options.find(opt => opt.value.length >= 2 && (item.brand || "").toLowerCase().includes(opt.value.toLowerCase()));
            brandEl.value = partialMatch ? partialMatch.value : "Other";
        }

        // IMPORTANT: Manually trigger the change event to populate datalists (models/series options)
        // This will clear the model/series inputs but that's fine as we set them immediately after.
        brandEl.dispatchEvent(new Event('change'));
    }

    if (modelsEl) modelsEl.value = item.model || "";

    const specs = item.specs || {};
    if (seriesEl && specs.series) seriesEl.value = specs.series;

    if (cpuEl) {
        let cpuVal = "";
        if (specs.cpu) {
            cpuVal = specs.cpu + (specs.gen ? " " + specs.gen : "");
        } else if (specs.gpu) {
            cpuVal = specs.gpu;
        } else if (specs.cpu_gen) {
            cpuVal = specs.cpu_gen;
        }
        cpuEl.value = cpuVal;
    }

    if (descEl) {
        if (specs.type) {
            descEl.value = specs.type;
        } else {
            // Build a descriptive spec string for Gaming/Desktops
            const parts = [];
            if (specs.ram) parts.push(specs.ram + " RAM");
            if (specs.storage) parts.push(specs.storage);
            if (specs.psu) parts.push(specs.psu);
            if (specs.refresh_rate) parts.push(specs.refresh_rate);

            descEl.value = parts.join(" | ") || (item.sector + " Unit");
        }
    }

    closeWarehouseModal();

    // Highlight effect
    ['brand', 'models', 'series', 'cpu', 'description'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.style.borderColor = 'var(--accent-color)';
            el.style.boxShadow = '0 0 0 4px rgba(140, 198, 63, 0.2)';
            setTimeout(() => {
                el.style.borderColor = '';
                el.style.boxShadow = '';
            }, 2000);
        }
    });
}

