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

<div class="panel">
    <h1>Dashboard Overview</h1>
    <p>Welcome to the IQA Metal internal label & inventory tracking system.</p>

    <?php if ($health['status'] === 'Critical'): ?>
        <div style="background: rgba(220,53,69,0.1); border: 2px solid var(--btn-danger-bg); padding: 15px; border-radius: 8px; margin-top: 20px;">
            <h3 style="color: var(--btn-danger-bg); margin: 0 0 10px 0;">🔴 Critical System Error</h3>
            <p style="margin: 0; font-weight: bold; font-size: 0.9rem;">
                The system has detected a problem with the database files:
            </p>
            <ul style="margin: 10px 0; font-size: 0.85rem; color: var(--text-secondary);">
                <?php foreach ($health['alerts'] as $alert): ?>
                    <li><?= htmlspecialchars($alert) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<div class="form-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
    <!-- Stat Box 1: Hardware -->
    <div class="panel text-center" style="border-top: 4px solid var(--accent-color);">
        <h3 style="color: var(--text-secondary); font-size: 1rem; text-transform: uppercase;">In Warehouse</h3>
        <p style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;"><?= $total_inventory ?></p>
        <a href="labels.php" class="btn btn-primary" style="font-size: 0.8rem; padding: 6px 12px;">View Stock</a>
    </div>

    <!-- Stat Box 2: Finances -->
    <div class="panel text-center" style="border-top: 4px solid var(--btn-success-bg);">
        <h3 style="color: var(--text-secondary); font-size: 1rem; text-transform: uppercase;">Total Sales</h3>
        <p style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;"><?= $total_sales ?></p>
        <a href="orders.php" class="btn btn-primary" style="font-size: 0.8rem; padding: 6px 12px;">View Orders</a>
    </div>

    <!-- Stat Box 3: Leads -->
    <div class="panel text-center" style="border-top: 4px solid #f39c12;">
        <h3 style="color: var(--text-secondary); font-size: 1rem; text-transform: uppercase;">Active CRM</h3>
        <p style="font-size: 2.5rem; font-weight: bold; margin: 10px 0;"><?= $total_leads ?></p>
        <a href="rolodex.php" class="btn btn-primary" style="font-size: 0.8rem; padding: 6px 12px;">View Contacts</a>
    </div>
</div>

<div class="form-grid">
    <!-- Quick Search Widget -->
    <div class="panel">
        <h2>🔍 Quick Locate</h2>
        <p style="margin-bottom: 15px; font-size: 0.9rem;">Scan a physical label barcode or type an ID to find a laptop's exact location.</p>
        <form id="quickSearchForm" class="flex-between">
            <input type="text" id="quickSearchId" placeholder="Search ID, Brand, Model, Spec…" required style="margin-right: 10px;">
            <button type="submit" class="btn btn-primary">Find</button>
        </form>
        <div id="quickSearchResult" style="margin-top: 15px;"></div>
    </div>
    
    <!-- Action Widget -->
    <div class="panel">
        <h2>⚡ Quick Actions</h2>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <a href="new_label.php" class="btn btn-success" 
               style="display: flex; align-items: center; justify-content: flex-start; gap: 10px; padding: 18px; font-weight: 600;">
                <span style="font-size: 1.4rem;">🏷️</span> 
                <span>Print New Hardware Label (.odt)</span>
            </a>
            
            <a href="new_order.php" class="btn btn-primary" 
               style="display: flex; align-items: center; justify-content: flex-start; gap: 10px; padding: 18px; font-weight: 600;">
                <span style="font-size: 1.4rem;">🛒</span>
                <span>Basket Items into Purchase Form (.ots)</span>
            </a>
        </div>
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
                            ? `<a href="${orderInfo.document_path}" download style="color:var(--accent-color);font-weight:bold;">⬇ Download ${orderInfo.order_number}.ots</a>`
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