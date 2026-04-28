/**
 * Filters the global batch list based on search input
 */
function filterOrders() {
    const input = document.getElementById('order-search');
    if (!input) return;

    const filter = input.value.toLowerCase();
    const rows = document.getElementsByClassName('order-row');
    let hasResults = false;

    for (let i = 0; i < rows.length; i++) {
        const searchBlob = rows[i].getAttribute('data-search') || "";
        if (searchBlob.includes(filter)) {
            rows[i].style.display = "";
            hasResults = true;
        } else {
            rows[i].style.display = "none";
        }
    }

    // Handle empty state during search
    let emptyState = document.querySelector('.orders-empty-state');
    const tbody = document.getElementById('orders-list');

    if (!hasResults) {
        if (!emptyState && tbody) {
            emptyState = document.createElement('tr');
            emptyState.className = 'orders-empty-state';
            const td = document.createElement('td');
            td.colSpan = 5;
            td.style.cssText = 'padding: 60px; text-align: center; color: #94a3b8; font-weight: 600;';
            emptyState.appendChild(td);
            tbody.appendChild(emptyState);
        }
        
        if (emptyState) {
            emptyState.style.display = '';
            emptyState.querySelector('td').innerText = `No batches found matching "${input.value}"`;
        }
    } else if (emptyState) {
        emptyState.style.display = 'none';
    }
}

/**
 * Sorts the orders table based on column index
 * @param {number} n 
 */
function sortOrdersTable(n) {
    const table = document.querySelector(".orders-table");
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.querySelectorAll("tr.order-row"));
    
    // Determine sort direction
    const currentDir = table.getAttribute("data-sort-dir") === "asc" ? "desc" : "asc";
    table.setAttribute("data-sort-dir", currentDir);
    const multiplier = currentDir === "asc" ? 1 : -1;

    rows.sort((a, b) => {
        const x = a.getElementsByTagName("TD")[n].innerText.trim().toLowerCase();
        const y = b.getElementsByTagName("TD")[n].innerText.trim().toLowerCase();

        // Special handling for dates (column index 2)
        if (n === 2) {
            const dateX = new Date(x);
            const dateY = new Date(y);
            return (dateX - dateY) * multiplier;
        }

        if (x < y) return -1 * multiplier;
        if (x > y) return 1 * multiplier;
        return 0;
    });

    // Re-append rows in sorted order
    rows.forEach(row => tbody.appendChild(row));
}

/**
 * Updates the order status via AJAX
 * @param {HTMLSelectElement} select 
 * @param {string} orderId 
 */
async function updateOrderStatus(select, orderId) {
    const newStatus = select.value;
    const originalValue = select.getAttribute('data-original-value');
    const badge = select.closest('.order-row').querySelector('.order-badge');

    try {
        select.disabled = true;
        if (badge) badge.style.opacity = '0.5';

        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('new_status', newStatus);

        const response = await fetch('api/update_order_status.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.status === 'success') {
            // Update UI
            select.setAttribute('data-original-value', newStatus);
            if (badge) {
                // Remove old status classes
                badge.className = 'order-badge status-' + newStatus.toLowerCase();
                badge.innerText = newStatus;
                badge.style.opacity = '1';
            }
        } else {
            throw new Error(data.error || 'Update failed');
        }
    } catch (err) {
        console.error("Status update failed", err);
        alert('Failed to update status: ' + err.message);
        select.value = originalValue; // Revert
        if (badge) badge.style.opacity = '1';
    } finally {
        select.disabled = false;
    }
}

/**
 * Transfers an order to a new customer via AJAX
 */
async function transferOrder(event) {
    event.preventDefault();
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const origText = submitBtn.innerText;

    try {
        submitBtn.disabled = true;
        submitBtn.innerText = 'Transferring...';

        const formData = new FormData(form);
        const response = await fetch('api/transfer_order.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.status === 'success') {
            alert('Order transferred successfully!');
            location.reload(); // Reload to reflect changes in the list
        } else {
            throw new Error(data.error || 'Transfer failed');
        }
    } catch (err) {
        console.error("Transfer failed", err);
        alert('Failed to transfer order: ' + err.message);
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = origText;
    }
}
