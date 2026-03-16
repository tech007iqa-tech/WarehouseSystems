/**
 * assets/js/new_order.js
 * Drives the entire new_order.php cart interface.
 *
 * STATE:
 *   cart[]      - array of cart groups (grouped by spec fingerprint)
 *   cartItemIds - Set of all individual item IDs currently in the cart
 *
 * A cart group object looks like:
 * {
 *   fingerprint: 'HP|EliteBook|840 G3|i5-8th|8GB|256GB SSD|Untested',
 *   item_ids:    [3, 7, 11, ...],          // individual DB ids
 *   brand, model, series, cpu_gen,
 *   ram, storage, description,             // display fields
 *   unit_price: 0,                         // user-entered price
 * }
 */

'use strict';

// ─── STATE ──────────────────────────────────────────────────────────────────
let cart        = [];
let cartItemIds = new Set();
let searchTimer = null;

// ─── DOM REFS ───────────────────────────────────────────────────────────────
const customerSelect      = document.getElementById('customerSelect');
const customerPreview     = document.getElementById('customerPreview');
const customerPreviewText = document.getElementById('customerPreviewText');
const searchInput         = document.getElementById('searchInput');
const searchStatus        = document.getElementById('searchStatus');
const searchResultsWrapper= document.getElementById('searchResultsWrapper');
const searchResultsBody   = document.getElementById('searchResultsBody');
const cartEmpty           = document.getElementById('cartEmpty');
const cartWrapper         = document.getElementById('cartWrapper');
const cartBody            = document.getElementById('cartBody');
const cartTotalQty        = document.getElementById('cartTotalQty');
const cartTotalPrice      = document.getElementById('cartTotalPrice');
const generateBtn         = document.getElementById('generateBtn');
const generateResult      = document.getElementById('generateResult');

// ─── CUSTOMER SELECTOR ──────────────────────────────────────────────────────
customerSelect.addEventListener('change', () => {
    const opt     = customerSelect.selectedOptions[0];
    const company = opt.dataset.company || opt.text;
    const contact = opt.dataset.contact || '';
    customerPreviewText.textContent = company + (contact ? ' (' + contact + ')' : '');
    customerPreview.style.display = 'block';
});

// ─── WAREHOUSE SEARCH ───────────────────────────────────────────────────────
searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    const q = searchInput.value.trim();

    if (q.length === 0) {
        searchResultsWrapper.style.display = 'none';
        searchStatus.textContent = '';
        return;
    }

    searchStatus.textContent = 'Searching…';
    searchTimer = setTimeout(() => fetchInventory(q), 300);
});

async function fetchInventory(q) {
    try {
        const res  = await fetch('api/search_inventory.php?q=' + encodeURIComponent(q));
        const json = await res.json();

        if (!json.success) {
            searchStatus.textContent = 'Error: ' + (json.error || 'Unknown error');
            return;
        }

        const items = json.data;
        searchStatus.textContent = items.length > 0
            ? items.length + ' result(s) found:'
            : 'No available items match "' + q + '".';

        renderSearchResults(items);
    } catch (err) {
        searchStatus.textContent = 'Network error during search.';
        console.error(err);
    }
}

function renderSearchResults(items) {
    searchResultsBody.innerHTML = '';

    if (items.length === 0) {
        searchResultsWrapper.style.display = 'none';
        return;
    }

    items.forEach(item => {
        const inCart   = cartItemIds.has(item.id);
        const tr       = document.createElement('tr');

        tr.innerHTML = `
            <td>
                <strong>${esc(item.brand)} ${esc(item.model)}</strong>
                <div style="font-size:0.8rem;color:var(--text-secondary);">${esc(item.series || '')}</div>
            </td>
            <td style="font-size:0.9rem;">${esc(item.cpu_gen || '—')}</td>
            <td style="font-size:0.9rem;">${esc(item.ram || 'None')} / ${esc(item.storage || 'None')}</td>
            <td>${conditionBadge(item.description)}</td>
            <td style="font-size:0.85rem;color:var(--text-secondary);">${esc(item.warehouse_location || '—')}</td>
            <td>
                <button class="btn btn-success add-btn"
                        style="font-size:0.8rem; padding:6px 12px; ${inCart ? 'opacity:0.7;' : ''}"
                        data-id="${item.id}"
                        data-brand="${esc(item.brand)}"
                        data-model="${esc(item.model)}"
                        data-series="${esc(item.series || '')}"
                        data-cpu_gen="${esc(item.cpu_gen || '')}"
                        data-ram="${esc(item.ram || 'None')}"
                        data-storage="${esc(item.storage || 'None')}"
                        data-description="${esc(item.description || '')}">
                    ${inCart ? '+ Add More' : '+ Add to Order'}
                </button>
            </td>
        `;
        searchResultsBody.appendChild(tr);
    });

    // Attach add-to-cart listeners
    searchResultsBody.querySelectorAll('.add-btn').forEach(btn => {
        btn.addEventListener('click', () => addToCart(btn));
    });

    searchResultsWrapper.style.display = 'block';
}

// ─── CART LOGIC ─────────────────────────────────────────────────────────────

function makeFingerprint(data) {
    return [data.brand, data.model, data.series, data.cpu_gen,
            data.ram, data.storage, data.description].join('|');
}

function addToCart(btn) {
    const data = {
        id:          parseInt(btn.dataset.id),
        brand:       btn.dataset.brand,
        model:       btn.dataset.model,
        series:      btn.dataset.series,
        cpu_gen:     btn.dataset.cpu_gen,
        ram:         btn.dataset.ram,
        storage:     btn.dataset.storage,
        description: btn.dataset.description,
    };

    const fp = makeFingerprint(data);

    // Find existing group by item_id (SKU)
    let group = cart.find(g => g.item_id === data.id);
    if (group) {
        group.qty++;
    } else {
        cart.push({
            item_id:      data.id,
            brand:        data.brand,
            model:        data.model,
            series:       data.series,
            cpu_gen:      data.cpu_gen,
            ram:          data.ram,
            storage:      data.storage,
            description:  data.description,
            qty:          1,
            unit_price:   '',
            fingerprint:  fp
        });
    }

    cartItemIds.add(data.id);

    // Update button visual
    btn.textContent = '+ Add More';
    btn.style.opacity = '0.7';

    renderCart();
}

function removeFromCart(itemId) {
    cart = cart.filter(g => g.item_id !== itemId);
    cartItemIds.delete(itemId);
    renderCart();

    // Re-render search results to update buttons
    const q = searchInput.value.trim();
    if (q.length > 0) fetchInventory(q);
}

function renderCart() {
    if (cart.length === 0) {
        cartEmpty.style.display  = 'block';
        cartWrapper.style.display = 'none';
        updateTotals();
        return;
    }

    cartEmpty.style.display   = 'none';
    cartWrapper.style.display = 'block';
    cartBody.innerHTML = '';

    cart.forEach((group, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="color:var(--text-secondary);font-size:0.9rem;">${idx + 1}</td>
            <td>
                <strong>${esc(group.brand)} ${esc(group.model)}</strong>
                <div style="font-size:0.8rem;color:var(--text-secondary);">${esc(group.series)}</div>
            </td>
            <td style="font-size:0.9rem;">${esc(group.cpu_gen || '—')}</td>
            <td style="font-size:0.9rem;">${esc(group.ram)} / ${esc(group.storage)}</td>
            <td>${conditionBadge(group.description)}</td>
            <td style="text-align:center;">
                <input type="number" class="qty-input" min="1" value="${group.qty}" data-id="${group.item_id}"
                       style="width:65px;padding:6px;text-align:center;font-weight:bold;border-radius:20px;">
            </td>
            <td>
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="color:var(--text-secondary);">$</span>
                    <input type="number" class="price-input" min="0" step="0.01" placeholder="0.00"
                           value="${group.unit_price}" data-id="${group.item_id}"
                           style="width:105px;padding:8px;">
                </div>
            </td>
            <td style="text-align:right;font-weight:bold;color:var(--btn-success-bg);" class="subtotal-cell">
                ${calcSubtotal(group)}
            </td>
            <td>
                <button class="btn btn-danger remove-btn" data-id="${group.item_id}"
                        style="font-size:0.75rem;padding:5px 10px;">✕</button>
            </td>
        `;
        cartBody.appendChild(tr);
    });

    // Listeners for Quantity and Price
    cartBody.querySelectorAll('.qty-input, .price-input').forEach(inp => {
        inp.addEventListener('input', () => {
            const id    = parseInt(inp.dataset.id);
            const group = cart.find(g => g.item_id === id);
            if (group) {
                if (inp.classList.contains('qty-input')) {
                    group.qty = Math.max(1, parseInt(inp.value) || 1);
                } else {
                    group.unit_price = parseFloat(inp.value) || 0;
                }
                const subtotalCell = inp.closest('tr').querySelector('.subtotal-cell');
                subtotalCell.textContent = calcSubtotal(group);
                updateTotals();
            }
        });
    });

    // Remove listener
    cartBody.querySelectorAll('.remove-btn').forEach(btn => {
        btn.addEventListener('click', () => removeFromCart(parseInt(btn.dataset.id)));
    });

    updateTotals();
}

function calcSubtotal(group) {
    const price = parseFloat(group.unit_price) || 0;
    const qty   = parseInt(group.qty) || 0;
    return '$' + (price * qty).toFixed(2);
}

function updateTotals() {
    const totalQty   = cart.reduce((sum, g) => sum + (parseInt(g.qty) || 0), 0);
    const totalPrice = cart.reduce((sum, g) => {
        return sum + ((parseFloat(g.unit_price) || 0) * (parseInt(g.qty) || 0));
    }, 0);

    cartTotalQty.textContent   = totalQty;
    cartTotalPrice.textContent = '$' + totalPrice.toFixed(2);
}

// ─── GENERATE ORDER ─────────────────────────────────────────────────────────
generateBtn.addEventListener('click', async () => {
    generateResult.style.display = 'none';

    const customerId = parseInt(customerSelect.value) || 0;
    if (!customerId) {
        showGenerateMessage('error', '⚠ Please select a customer first (Step 1).');
        return;
    }
    if (cart.length === 0) {
        showGenerateMessage('error', '⚠ The cart is empty. Add items from the warehouse first (Step 2).');
        return;
    }
    const unpricedGroup = cart.find(g => !(parseFloat(g.unit_price) > 0));
    if (unpricedGroup) {
        showGenerateMessage('error', `⚠ Every line item needs a unit price. Please set a price for "${unpricedGroup.brand} ${unpricedGroup.model}" in the cart.`);
        return;
    }

    generateBtn.disabled    = true;
    generateBtn.textContent = '⏳ Generating… Please wait';

    const payload = {
        customer_id: customerId,
        cart: cart.map(group => ({
            item_ids:    [group.item_id], // Still send as array for backend compatibility, but it only contains the template ID
            brand:       group.brand,
            model:       group.model,
            series:       group.series,
            cpu_gen:     group.cpu_gen,
            ram:         group.ram,
            storage:     group.storage,
            description: group.description,
            qty:         parseInt(group.qty),
            unit_price:  parseFloat(group.unit_price) || 0,
        }))
    };

    try {
        const res  = await fetch('api/orders_api.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload)
        });
        const json = await res.json();

        if (json.success) {
            const d = json.data;
            showGenerateMessage('success', `
                ✅ <strong>${d.order_number}</strong> created successfully!<br>
                ${d.total_qty} units sold &nbsp;·&nbsp; ${d.total_price} total<br><br>
                <a href="${d.file_path}" class="btn btn-success" download style="margin-top:8px;">
                    ⬇ Download ${d.file_name}
                </a>
            `);

            // Reset cart state after success
            cart        = [];
            cartItemIds = new Set();
            renderCart();
            searchResultsBody.innerHTML = '';
            searchResultsWrapper.style.display = 'none';
            searchInput.value  = '';
            searchStatus.textContent = '';
            customerSelect.value       = '';
            customerPreview.style.display = 'none';
        } else {
            showGenerateMessage('error', '❌ ' + (json.error || 'An unknown error occurred.'));
        }

    } catch (err) {
        showGenerateMessage('error', '❌ Network error. Is XAMPP running?');
        console.error(err);
    } finally {
        generateBtn.disabled    = false;
        generateBtn.textContent = '⚡ Generate Purchase Order (.ots)';
    }
});

function showGenerateMessage(type, html) {
    const isSuccess = (type === 'success');
    generateResult.style.display      = 'block';
    generateResult.style.padding      = '16px 20px';
    generateResult.style.borderRadius = '8px';
    generateResult.style.border       = '1px solid ' + (isSuccess ? 'var(--btn-success-bg)' : 'var(--btn-danger-bg)');
    generateResult.style.background   = isSuccess  ? 'rgba(40,167,69,0.1)' : 'rgba(220,53,69,0.1)';
    generateResult.style.color        = isSuccess ? 'var(--btn-success-bg)' : 'var(--btn-danger-bg)';
    generateResult.innerHTML          = html;
    generateResult.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function conditionBadge(desc) {
    if (!desc) return '—';
    let color = 'var(--text-secondary)';
    if (desc === 'For Parts')    color = 'var(--btn-danger-bg)';
    if (desc === 'Refurbished')  color = 'var(--btn-success-bg)';
    if (desc === 'Untested')     color = '#f39c12';
    return `<span style="background:${color};color:#fff;padding:2px 7px;border-radius:4px;font-size:0.78rem;font-weight:bold;">${esc(desc)}</span>`;
}

