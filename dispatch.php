<?php
// dispatch.php
// The "Dispatch Desk" - specialized view for Sold items waiting for shipping/archival.
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/hardware_mapping.php';
require_once 'includes/header.php';

// Initial server-side load (Order items, within last 90 days)
$inventory = [];
try {
    // 1. Fetch Customers mapping for Buyer names
    $stmt_c = $pdo_rolodex->query("SELECT customer_id, company_name FROM customers");
    $customers = [];
    foreach ($stmt_c->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $customers[$c['customer_id']] = $c['company_name'];
    }

    // 2. Query Orders DB
    $stmt = $pdo_orders->query("
        SELECT 
            i.line_id as id,
            i.order_number,
            i.item_id as original_item_id,
            i.brand,
            i.model,
            i.specs_blob,
            i.unit_price as sale_price,
            o.customer_id,
            o.order_date as updated_at,
            o.invoice_status
        FROM order_items i
        JOIN purchase_orders o ON i.order_number = o.order_number
        WHERE o.invoice_status != 'Canceled'
          AND o.order_date >= datetime('now', '-90 days')
        ORDER BY o.order_date DESC
        LIMIT 200
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Post-process to match the expected format for labels.js/dispatch.js
    foreach ($rows as $row) {
        $cid = $row['customer_id'];
        $row['buyer_name'] = $customers[$cid] ?? 'Unknown Buyer';
        $row['buyer_order_num'] = 'ORD-' . str_pad($row['order_number'], 6, '0', STR_PAD_LEFT);
        
        // Parse specs_blob back into individual fields
        $parts = explode(' | ', $row['specs_blob']);
        $row['series']      = $parts[0] ?? '';
        $row['cpu_gen']     = $parts[1] ?? '';
        $row['ram']         = $parts[2] ?? '';
        $row['storage']     = $parts[3] ?? '';
        $row['description'] = $parts[4] ?? '';
        
        $inventory[] = $row;
    }
} catch (PDOException $e) {
    error_log("Database error in dispatch.php: " . $e->getMessage());
}
?>

<!-- Page Header + Filter Bar -->
<div class="panel mb-spacing">
    <div class="flex-between mb-15">
        <div>
            <h1>🚚 Dispatch Desk</h1>
            <p>Managing recently sold items and shipping history. (Showing last 90 days by default)</p>
        </div>
        <div class="flex-gap">
            <button id="viewArchiveBtn" class="btn btn-secondary-outline">📦 View Full Archive</button>
        </div>
    </div>

    <!-- Filter Controls -->
    <div class="filter-controls">
        <input type="text" id="filterSearch"
               placeholder="Search Buyer, Order #, Model, Specs, or Status (e.g. Paid)…"
               class="filter-search-input">
        
        <select id="filterStatus" class="filter-select">
            <option value="">All Statuses</option>
            <option value="Active">Active 🚀</option>
            <option value="Paid">Paid ✅</option>
            <option value="Dispatched">Dispatched 🚚</option>
            <option value="Pending">Pending ⏳</option>
        </select>

        <div id="filterMsg" class="filter-message" style="margin-top:0;"></div>
    </div>
</div>

<!-- Dispatch Table -->
<div class="panel">
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sold Item</th>
                    <th>Buyer & Order</th>
                    <th>CPU</th>
                    <th>RAM / Storage</th>
                    <th>Price</th>
                    <th>Sold Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="inventoryTableBody">
                <!-- Data Hydrated by dispatch.js -->
                <tr>
                    <td colspan="6" class="text-center empty-table-message" style="padding: 50px;">
                        <div class="loader-spinner" style="margin-bottom:10px;">⏳</div>
                        Loading dispatch records...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Inject Initial Data for JS Hydration -->
<script>
    window.INITIAL_INVENTORY = <?= json_encode($inventory) ?>;
    window.DISPATCH_MODE = true;
</script>

<!-- TEMPLATES -->
<template id="inventoryRowTemplate">
    <tr data-id="">
        <td data-label="Model">
            <a href="#" class="tpl-link font-bold text-lg no-underline text-accent">BRAND MODEL</a>
            <div class="tpl-series text-sm text-secondary">SERIES</div>
            <div class="tpl-sn text-xs" style="margin-top:4px; font-family:monospace; color:var(--text-secondary);">
                <span class="tpl-sn-text">S/N</span>
                <span class="tpl-sn-empty" style="opacity:0.5; display:none;">No Serial</span>
            </div>
        </td>
        <td data-label="Buyer">
            <div class="tpl-buyer-box">
                <div class="tpl-buyer-name font-bold text-main" style="font-size:0.95rem;">BUYER</div>
                <div class="flex-between" style="margin-top:2px;">
                    <div class="tpl-order-num text-xs text-secondary">ORDER #</div>
                    <div class="tpl-status-badge">STATUS</div>
                </div>
            </div>
        </td>
        <td data-label="CPU" class="text-sm">
            <div class="tpl-cpu-gen">GEN</div>
            <div class="tpl-cpu-specs text-xs text-secondary">SPECS</div>
        </td>
        <td data-label="RAM/HDD" class="text-sm">
            <span class="tpl-ram">RAM</span> / <span class="tpl-storage">STORAGE</span>
        </td>
        <td data-label="Price" class="tpl-price font-bold" style="color: var(--btn-success-bg);">
            $0.00
        </td>
        <td data-label="Sold" class="tpl-added text-xs text-secondary">DATE</td>
        <td class="whitespace-nowrap">
            <div class="action-strip">
                <button class="btn reprint-btn" data-id="" title="Reprint Shipping Label">🖨️ Label</button>
                <button class="btn open-label-btn" data-id="" data-brand="" data-model="" title="Open Folder">📂 Open</button>
                <button class="btn btn-danger delete-btn" data-id="" data-label="" title="Delete Record">🗑 Del</button>
            </div>
        </td>
    </tr>
</template>

<script src="assets/js/labels.js"></script>
<script src="assets/js/dispatch.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-dispatch').classList.add('active');
    });
</script>

<style>
    .flex-gap { display: flex; gap: 10px; }
    .text-accent { color: var(--accent-color); }
    .mb-spacing { margin-bottom: var(--spacing); }
    .mb-15 { margin-bottom: 15px; }
    .filter-controls { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .filter-search-input { flex: 1; min-width: 240px; }
    .btn-secondary-outline { background: var(--bg-page); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 10px 14px; }
    .filter-message { margin-top: 10px; font-size: 0.85rem; color: var(--text-secondary); min-height: 18px; }
    .data-table .text-center { text-align: center; }
    .empty-table-message { padding: 30px; font-style: italic; color: var(--text-secondary); }
    .font-bold { font-weight: bold; }
    .text-lg { font-size: 1.1rem; }
    .text-sm { font-size: 0.9rem; }
    .text-xs { font-size: 0.85rem; }
    .text-secondary { color: var(--text-secondary); }
    .text-main { color: var(--text-main); }
    .no-underline { text-decoration: none; }
    .whitespace-nowrap { white-space: nowrap; }
</style>

<?php require_once 'includes/footer.php'; ?>
