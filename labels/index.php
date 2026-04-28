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

<div class="panel" style="margin-bottom: 30px; text-align: center; background: transparent; border: none; box-shadow: none;">
    <h1 style="font-size: 2.5rem; font-weight: 900; margin-bottom: 10px; color: var(--text-main);">Warehouse Control</h1>
    <p style="color: var(--text-secondary); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">Streamlined management for hardware inventory and professional label generation.</p>
</div>

<!-- MAIN NAVIGATION GRID -->
<div class="action-grid" style="margin-bottom: 40px; max-width: 1000px; margin-left: auto; margin-right: auto;">
    
    <!-- 1. INVENTORY TRACKER -->
    <a href="labels.php" class="panel hover-scale" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; border-top: 6px solid var(--text-main); transition: transform 0.2s, box-shadow 0.2s;">
        <div style="font-size: 4rem; margin-bottom: 20px;">📦</div>
        <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 10px;">Inventory Tracker</h2>
        <p style="color: var(--text-secondary); text-align: center; font-size: 0.95rem;">Manage, search, and edit all hardware currently in the warehouse.</p>
        <div style="margin-top: 20px; background: var(--text-main); color: white; padding: 8px 20px; border-radius: 30px; font-weight: 700; font-size: 0.85rem;">
            <?= $total_inventory ?> Units Active
        </div>
    </a>

    <!-- 2. PRINT HARDWARE LABEL -->
    <a href="new_label.php" class="panel hover-scale" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; border-top: 6px solid var(--accent-color); transition: transform 0.2s, box-shadow 0.2s;">
        <div style="font-size: 4rem; margin-bottom: 20px;">🏷️</div>
        <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 10px;">Print Hardware Label</h2>
        <p style="color: var(--text-secondary); text-align: center; font-size: 0.95rem;">Rapid intake and professional .odt thermal label generation.</p>
        <div style="margin-top: 20px; background: var(--accent-color); color: white; padding: 8px 20px; border-radius: 30px; font-weight: 700; font-size: 0.85rem;">
            Generate Label
        </div>
    </a>
</div>

<!-- QUICK LOCATE (MINIMAL) -->
<div class="panel" style="max-width: 1000px; margin: 0 auto; border-radius: 20px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
        <div>
            <h3 style="font-size: 1.1rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.3rem;">🔍</span> Quick Locate
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-secondary);">Find a device's physical location instantly.</p>
        </div>
        <form id="quickSearchForm" style="display: flex; gap: 10px; flex: 1; max-width: 500px;">
            <input type="text" id="quickSearchId" placeholder="Enter ID, Brand, or Model..." required style="flex: 1; height: 48px; border-radius: 12px;">
            <button type="submit" class="btn btn-primary" style="padding: 0 25px; height: 48px; border-radius: 12px; font-weight: 700;">Find</button>
        </form>
    </div>
    <div id="quickSearchResult" style="margin-top: 20px;"></div>
</div>

<style>
    .hover-scale:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
    }
</style>

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

                const results = json.data.results;
                let html = '';

                results.forEach((item, index) => {
                    html += `
                        <div style="background:var(--bg-page); border:1px solid var(--border-color); border-radius:12px; padding:15px; margin-bottom:10px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: 800; color: var(--text-main);">#${String(item.id).padStart(5,'0')} — ${item.brand} ${item.model}</div>
                                <div style="font-size: 0.85rem; color: var(--text-secondary);">
                                    📍 <strong>${item.warehouse_location ?? 'Unassigned'}</strong> | 🧠 ${item.cpu_gen ?? '—'}
                                </div>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <a href="hardware_view.php?id=${item.id}" class="btn" style="padding: 5px 15px; font-size: 0.8rem;">View Sheet</a>
                                <button onclick="flashOpenLabel(${item.id}, '${item.brand}', '${item.model}', this)" class="btn" style="padding: 5px 15px; font-size: 0.8rem; background: var(--text-main); color: white;">📂 Open ODT</button>
                            </div>
                        </div>`;
                });

                resultDiv.innerHTML = html;

            } catch (err) {
                resultDiv.innerHTML = '<span style="color:var(--btn-danger-bg); font-size:0.85rem;">⚠ Network error.</span>';
            }
        }

        quickInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(performSearch, 300);
        });

        document.getElementById('quickSearchForm').addEventListener('submit', (e) => {
            e.preventDefault();
            clearTimeout(searchTimer);
            performSearch();
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>