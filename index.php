<?php 
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php'; 

// Fetch basic stats (This will error if init_db isn't run, handle gracefully)
$total_inventory = 0;
$total_sales = "$0.00";
$total_leads = 0;

// Health Check
require_once 'includes/status_functions.php';
$health = get_system_health($pdo_labels, $pdo_orders, $pdo_rolodex);

try {
    // Inventory Count
    $stmt = $pdo_labels->query("SELECT COUNT(id) FROM items WHERE status = 'In Warehouse'");
    $total_inventory = $stmt->fetchColumn();

    // Sales Output
    $stmt = $pdo_orders->query("SELECT SUM(total_price) FROM purchase_orders");
    if($sum = $stmt->fetchColumn()) {
        $total_sales = format_currency($sum);
    }

    // Lead Counts
    $stmt = $pdo_rolodex->query("SELECT COUNT(customer_id) FROM customers WHERE lead_status != 'Inactive'");
    $total_leads = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Silent
}
?>

<div class="panel" style="margin-bottom: 30px;">
    <h1 style="font-size: 1.5rem; margin-bottom: 5px;">Worker Dashboard</h1>
    <p style="color: var(--text-secondary); font-size: 0.9rem;">Quick tools for inventory intake and location tracking.</p>

    <?php if ($health['status'] === 'Critical'): ?>
        <div style="background: rgba(220,53,69,0.05); border: 2px solid #ef4444; padding: 15px; border-radius: 12px; margin-top: 15px;">
            <h3 style="color: #ef4444; margin: 0 0 5px 0; font-size: 1rem;">🔴 System Alert</h3>
            <p style="margin: 0; font-weight: 600; font-size: 0.85rem; color: var(--text-main);">
                Database issues detected. Contact Admin or check Settings.
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- PHASE 1: ACTION TOOLS (PRIMARY) -->
<div class="action-grid" style="margin-bottom: 25px;">
    <!-- Search Widget -->
    <div class="panel" style="border-left: 5px solid var(--accent-color);">
        <h2 style="font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.3rem;">🔍</span> Quick Locate
        </h2>
        <p style="margin-bottom: 15px; font-size: 0.85rem; color: var(--text-secondary);">Find a laptop's location by ID or Brand.</p>
        <form id="quickSearchForm" class="flex-between">
            <input type="text" id="quickSearchId" placeholder="ID, Brand, Model..." required style="flex: 1; margin-right: 10px;">
            <button type="submit" class="btn btn-primary" style="padding: 0 25px;">Find</button>
        </form>
        <div id="quickSearchResult" style="margin-top: 15px;"></div>
    </div>
    
    <!-- Action Widget -->
    <div class="panel" style="border-left: 5px solid var(--text-main);">
        <h2 style="font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1.3rem;">⚡</span> Quick Actions
        </h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;">
            <a href="new_label.php" class="btn btn-success" style="flex-direction: column; height: auto; padding: 15px; font-size: 0.85rem;">
                <span style="font-size: 1.5rem; margin-bottom: 5px;">🏷️</span>
                <span>New Label</span>
            </a>
            <a href="new_order.php" class="btn btn-primary" style="flex-direction: column; height: auto; padding: 15px; font-size: 0.85rem; background: var(--text-main);">
                <span style="font-size: 1.5rem; margin-bottom: 5px;">🛒</span>
                <span>B2B Form</span>
            </a>
        </div>
    </div>
</div>

<!-- PHASE 2: SYSTEM STATS (SECONDARY) -->
<div class="stat-grid" style="margin-bottom: 30px;">
    <!-- Hardware -->
    <div class="panel text-center" style="display: flex; flex-direction: column; justify-content: center; padding: 15px;">
        <span style="font-size: 0.7rem; text-transform: uppercase; font-weight: 800; color: var(--text-secondary);">Warehouse</span>
        <p style="font-size: 1.8rem; font-weight: 800; margin: 5px 0;"><?= $total_inventory ?></p>
        <a href="labels.php" style="font-size: 0.75rem; font-weight: 700; color: var(--accent-color);">View All ➔</a>
    </div>

    <!-- Finances -->
    <div class="panel text-center" style="display: flex; flex-direction: column; justify-content: center; padding: 15px;">
        <span style="font-size: 0.7rem; text-transform: uppercase; font-weight: 800; color: var(--text-secondary);">Sales</span>
        <p style="font-size: 1.8rem; font-weight: 800; margin: 5px 0;"><?= $total_sales ?></p>
        <a href="orders.php" style="font-size: 0.75rem; font-weight: 700; color: var(--accent-color);">View All ➔</a>
    </div>

    <!-- CRM -->
    <div class="panel text-center" style="display: flex; flex-direction: column; justify-content: center; padding: 15px;">
        <span style="font-size: 0.7rem; text-transform: uppercase; font-weight: 800; color: var(--text-secondary);">Leads</span>
        <p style="font-size: 1.8rem; font-weight: 800; margin: 5px 0;"><?= $total_leads ?></p>
        <a href="rolodex.php" style="font-size: 0.75rem; font-weight: 700; color: var(--accent-color);">View All ➔</a>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-dashboard').classList.add('active');

        const quickInput  = document.getElementById('quickSearchId');
        const resultDiv   = document.getElementById('quickSearchResult');
        let searchTimer   = null;

        async function performSearch() {
            const query = quickInput.value.trim();

            if (!query) {
                resultDiv.innerHTML = '';
                return;
            }

            resultDiv.innerHTML = '<span style="color:var(--text-secondary); font-size:0.85rem;">🔍 Searching…</span>';

            try {
                const res  = await fetch('api/search_item.php?id=' + encodeURIComponent(query));
                const json = await res.json();

                if (!json.success) {
                    resultDiv.innerHTML = `<div style="padding:15px; background:rgba(220,53,69,0.05); border-radius:8px; color:var(--btn-danger-bg); font-size:0.9rem;">⚠ ${json.error}</div>`;
                    return;
                }

                const results    = json.data.results;
                const orderInfo  = json.data.order_info;
                const isSingle   = json.data.is_single;

                let html = '';

                results.forEach((item, index) => {
                    const statusColor = item.status === 'Sold'    ? 'var(--btn-danger-bg)'
                                      : item.status === 'Pending' ? '#f39c12'
                                      : 'var(--btn-success-bg)';

                    const condColor   = item.description === 'For Parts'    ? 'var(--btn-danger-bg)'
                                      : item.description === 'Refurbished'  ? 'var(--btn-success-bg)'
                                      : '#f39c12';

                    let orderHtml = '';
                    // Only show detailed order info for the first/main result
                    if (index === 0 && orderInfo) {
                        const docLink = orderInfo.document_path
                            ? `<button onclick="launchFile('${orderInfo.document_path}')" class="btn" style="background:var(--accent-color); color:#fff; font-weight:bold; margin-top:5px; font-size:0.75rem; padding:5px 10px;">🚀 Open Order Form</button>`
                            : '';
                        orderHtml = `
                            <div style="margin-top:10px;padding:12px;background:rgba(0,0,0,0.03);border-radius:6px;font-size:0.85rem;color:var(--text-secondary);">
                                🤝 Sold on <strong style="color:var(--text-main);">${orderInfo.order_number}</strong>
                                to <strong style="color:var(--accent-color);">${orderInfo.company_name}</strong>
                                (${orderInfo.contact_person}) &nbsp;·&nbsp; ${orderInfo.order_date.substring(0,10)}
                                <div style="margin-top:5px;">${docLink}</div>
                            </div>`;
                    }

                    html += `
                        <div style="background:var(--bg-page); border:1px solid var(--border-color); border-radius:var(--border-radius); padding:14px 16px; margin-bottom:10px; transition: all 0.2s; ${index > 0 ? 'opacity:0.7; transform:scale(0.98);' : ''}">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                <span style="font-weight:bold;font-size:${index === 0 ? '1.1rem' : '1rem'};color:var(--accent-color);">
                                    #${String(item.id).padStart(5,'0')} — ${item.brand} ${item.model} ${item.series ?? ''}
                                </span>
                                <span style="background:${statusColor};color:#fff;padding:2px 8px;border-radius:4px;font-size:0.75rem;font-weight:bold;text-transform:uppercase;">
                                    ${item.status}
                                </span>
                            </div>
                            <div style="display:flex;gap:15px;flex-wrap:wrap;font-size:0.85rem;color:var(--text-secondary);">
                                <span title="CPU">🧠 ${item.cpu_gen ?? '—'}</span>
                                <span title="RAM/Storage">💾 ${item.ram ?? 'No RAM'} / ${item.storage ?? 'No Storage'}</span>
                                <span title="Location">📍 <strong style="color:var(--text-main);">${item.warehouse_location ?? 'Unassigned'}</strong></span>
                                <span style="background:${condColor};color:#fff;padding:1px 6px;border-radius:3px;font-size:0.75rem;font-weight:bold;">${item.description ?? '—'}</span>
                            </div>
                            ${orderHtml}
                        </div>`;
                });

                if (!isSingle) {
                    html = `<div style="margin-bottom:10px;font-size:0.8rem;color:var(--text-secondary);font-style:italic;">Top ${results.length} results matching your search:</div>` + html;
                }

                resultDiv.innerHTML = html;

            } catch (err) {
                resultDiv.innerHTML = '<span style="color:var(--btn-danger-bg); font-size:0.85rem;">⚠ Network error. Check server connection.</span>';
            }
        }

        // Live search on input
        quickInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(performSearch, 300);
        });

        // Submit also triggers search immediately
        document.getElementById('quickSearchForm').addEventListener('submit', (e) => {
            e.preventDefault();
            clearTimeout(searchTimer);
            performSearch();
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>