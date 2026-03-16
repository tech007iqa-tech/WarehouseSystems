<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Load all customers for the dropdown
$customers = [];
try {
    $stmt = $pdo_rolodex->query("
        SELECT customer_id, company_name, contact_person
        FROM customers
        ORDER BY company_name ASC
    ");
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    // Graceful fail
}
?>

<!-- ===== PAGE HEADER ===== -->
<div class="panel flex-between">
    <div>
        <h1>🛒 Create B2B Purchase Order</h1>
        <p>Select a customer, search the warehouse, build your cart, then generate the <code>.ots</code> purchase form.</p>
    </div>
    <a href="orders.php" class="btn btn-primary">📋 View Order History</a>
</div>

<!-- ===== STEP 1: CUSTOMER SELECTION ===== -->
<div class="panel">
    <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 15px;">
        Step 1 — Select Customer
    </h3>
    <div class="form-group" style="max-width: 500px;">
        <label for="customerSelect">Bill To (from Rolodex)</label>
        <select id="customerSelect">
            <option value="" disabled selected>— Choose a customer —</option>
            <?php if (empty($customers)): ?>
                <option disabled>No customers in Rolodex yet.</option>
            <?php else: ?>
                <?php foreach ($customers as $c): ?>
                    <option value="<?= (int)$c['customer_id'] ?>"
                            data-company="<?= htmlspecialchars($c['company_name'] ?? '') ?>"
                            data-contact="<?= htmlspecialchars($c['contact_person']) ?>">
                        <?= htmlspecialchars(($c['company_name'] ?? '') . ' — ' . $c['contact_person']) ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>
    <div id="customerPreview" style="display:none; margin-top:10px; padding: 10px 14px; background: var(--bg-page); border-radius: var(--border-radius); border-left: 3px solid var(--accent-color); font-size: 0.9rem; color: var(--text-secondary);">
        Billing to: <strong id="customerPreviewText" style="color: var(--text-main);"></strong>
    </div>
</div>

<!-- ===== STEP 2: WAREHOUSE SEARCH ===== -->
<div class="panel">
    <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 15px;">
        Step 2 — Search Warehouse Inventory
    </h3>

    <div class="form-group" style="max-width: 500px; margin-bottom: 15px;">
        <label for="searchInput">Search by Brand, Model, Series, CPU, or Condition</label>
        <input type="text" id="searchInput" placeholder="e.g. HP EliteBook, i5, Untested..." autocomplete="off">
    </div>

    <div id="searchStatus" style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 10px; min-height: 20px;"></div>

    <div id="searchResultsWrapper" style="display:none;">
        <table class="data-table" id="searchResultsTable">
            <thead>
                <tr>
                    <th>Brand &amp; Model</th>
                    <th>CPU</th>
                    <th>RAM / Storage</th>
                    <th>Condition</th>
                    <th>Location</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="searchResultsBody">
            </tbody>
        </table>
    </div>
</div>

<!-- ===== STEP 3: ORDER CART ===== -->
<div class="panel">
    <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 15px;">
        Step 3 — Order Cart
    </h3>

    <div id="cartEmpty" style="text-align:center; padding: 30px; color: var(--text-secondary); font-style: italic;">
        Your cart is empty. Search for items above and click "Add to Order".
    </div>

    <div id="cartWrapper" style="display:none;">
        <table class="data-table" id="cartTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Brand &amp; Model</th>
                    <th>CPU</th>
                    <th>RAM / Storage</th>
                    <th>Condition</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="width:160px;">Unit Price (USD)</th>
                    <th style="text-align:right;">Subtotal</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="cartBody">
            </tbody>
            <tfoot>
                <tr style="border-top: 2px solid var(--border-color);">
                    <td colspan="5" style="text-align:right; color: var(--text-secondary); font-size:0.9rem; padding-top: 15px;">Order Totals:</td>
                    <td style="text-align:center; font-weight:bold; padding-top:15px;" id="cartTotalQty">0</td>
                    <td></td>
                    <td style="text-align:right; font-weight:bold; color: var(--btn-success-bg); font-size:1.1rem; padding-top:15px;" id="cartTotalPrice">$0.00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- ===== STEP 4: GENERATE ORDER ===== -->
<div class="panel" style="text-align: right;">
    <div id="generateResult" style="display:none; text-align:left; margin-bottom: 15px;"></div>
    <button id="generateBtn" class="btn btn-success" style="font-size: 1.1rem; padding: 14px 32px;">
        ⚡ Generate Purchase Order (.ots)
    </button>
</div>

<script src="assets/js/new_order.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-new-order').classList.add('active');
    });
</script>

<?php require_once 'includes/footer.php'; ?>
