<?php 
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php'; 

// Fetch basic stats
$total_inventory = 0;

try {
    // Inventory Count
    $stmt = $pdo_labels->query("SELECT COUNT(id) FROM items WHERE status = 'In Warehouse'");
    $total_inventory = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Silent
}
?>

<div class="panel" style="margin-bottom: 30px;">
    <h1 style="font-size: 1.5rem; margin-bottom: 5px;">Worker Dashboard</h1>
    <p style="color: var(--text-secondary); font-size: 0.9rem;">Quick tools for inventory intake and location tracking.</p>
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
            <a href="labels.php" class="btn btn-primary" style="flex-direction: column; height: auto; padding: 15px; font-size: 0.85rem; background: var(--text-main);">
                <span style="font-size: 1.5rem; margin-bottom: 5px;">📦</span>
                <span>View Inventory</span>
            </a>
        </div>
    </div>
</div>

<!-- PHASE 2: SYSTEM STATS -->
<div class="stat-grid" style="margin-bottom: 30px;">
    <!-- Hardware -->
    <div class="panel text-center" style="display: flex; flex-direction: column; justify-content: center; padding: 15px;">
        <span style="font-size: 0.7rem; text-transform: uppercase; font-weight: 800; color: var(--text-secondary);">Warehouse</span>
        <p style="font-size: 1.8rem; font-weight: 800; margin: 5px 0;"><?= $total_inventory ?></p>
        <a href="labels.php" style="font-size: 0.75rem; font-weight: 700; color: var(--accent-color);">View All ➔</a>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
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
                const isSingle   = json.data.is_single;

                let html = '';

                results.forEach((item, index) => {
                    const statusColor = item.status === 'Sold'    ? 'var(--btn-danger-bg)'
                                      : item.status === 'Pending' ? '#f39c12'
                                      : 'var(--btn-success-bg)';

                    const condColor   = item.description === 'For Parts'    ? 'var(--btn-danger-bg)'
                                      : item.description === 'Refurbished'  ? 'var(--btn-success-bg)'
                                      : '#f39c12';

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
                            <div style="display:flex;gap:15px;flex-wrap:wrap;font-size:0.85rem;color:var(--text-secondary); margin-bottom:12px;">
                                <span title="CPU">🧠 ${item.cpu_gen ?? '—'}</span>
                                <span title="RAM/Storage">💾 ${item.ram ?? 'No RAM'} / ${item.storage ?? 'No Storage'}</span>
                                <span title="Location">📍 <strong style="color:var(--text-main);">${item.warehouse_location ?? 'Unassigned'}</strong></span>
                                <span style="background:${condColor};color:#fff;padding:1px 6px;border-radius:3px;font-size:0.75rem;font-weight:bold;">${item.description ?? '—'}</span>
                            </div>

                            <div class="action-strip" style="justify-content: flex-start; margin-bottom: 10px;">
                                <button onclick="window.openPrintConfig(${item.id})" class="btn" title="Print/Config Label">🖨️ Print</button>
                                <button onclick="flashOpenLabel(${item.id}, '${item.brand}', '${item.model}', this)" class="btn" title="Open Label">📂 Open</button>
                                <a href="hardware_view.php?id=${item.id}" class="btn">✏️ Edit</a>
                            </div>
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