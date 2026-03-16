<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order = null;

if ($order_id > 0) {
    try {
        // 1. Fetch Order Metadata
        $stmt = $pdo_orders->prepare("SELECT * FROM purchase_orders WHERE order_number = :id");
        $stmt->execute([':id' => $order_id]);
        $order = $stmt->fetch();
        
        if ($order) {
            // 2. Fetch Customer Details
            $stmt_cust = $pdo_rolodex->prepare("SELECT * FROM customers WHERE customer_id = :cid");
            $stmt_cust->execute([':cid' => $order['customer_id']]);
            $customer = $stmt_cust->fetch();

            // 3. Fetch Sold Items from order_items
            $stmt_items = $pdo_orders->prepare("SELECT * FROM order_items WHERE order_number = :oid");
            $stmt_items->execute([':oid' => $order_id]);
            $items = $stmt_items->fetchAll();
        }
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

if (!$order) {
    echo '<div class="panel text-center"><h1>404</h1><p>Purchase Order not found.</p><a href="orders.php" class="btn btn-primary">Back to History</a></div>';
    require_once 'includes/footer.php';
    exit;
}

$order_num_pad = 'ORD-' . str_pad($order['order_number'], 6, '0', STR_PAD_LEFT);
?>

<div class="panel flex-between" style="border-left: 5px solid var(--btn-success-bg);">
    <div>
        <h1 style="margin:0; font-size: 2rem; color: var(--btn-success-bg);"><?= $order_num_pad ?></h1>
        <p style="margin:5px 0 0; color: var(--text-secondary);">Purchased on <?= format_date($order['order_date']) ?></p>
    </div>
    <div style="text-align: right;">
        <?php if ($order['document_path']): ?>
            <a href="<?= htmlspecialchars($order['document_path']) ?>" download class="btn btn-success" style="font-size: 1.1rem; padding: 12px 24px;">
                ⬇ Download Purchase Form (.ots)
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 20px;">
    
    <!-- Customer Details Card -->
    <div class="panel card">
        <h2 style="margin:0 0 15px; font-size: 1.2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">👤 Bill To</h2>
        <?php if ($customer): ?>
            <div style="font-size: 1.1rem; font-weight: bold; margin-bottom: 5px;">
                <a href="customer_view.php?id=<?= $customer['customer_id'] ?>" style="color: var(--accent-color); text-decoration: none;">
                    <?= htmlspecialchars($customer['company_name'] ?: 'N/A') ?>
                </a>
            </div>
            <div style="color: var(--text-main);"><?= htmlspecialchars($customer['contact_person']) ?></div>
            <div style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 10px;">
                📧 <?= htmlspecialchars($customer['email'] ?: 'No email') ?><br>
                📞 <?= htmlspecialchars($customer['phone'] ?: 'No phone') ?>
            </div>
        <?php else: ?>
            <p style="color: var(--btn-danger-bg); font-style: italic;">Customer record deleted or missing.</p>
        <?php endif; ?>
    </div>

    <!-- Order Summary Statistics -->
    <div class="panel card">
        <h2 style="margin:0 0 15px; font-size: 1.2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">📊 Order Summary</h2>
        <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="panel text-center" style="background: var(--bg-page); padding: 15px;">
                <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase;">Total Units Sold</div>
                <div style="font-size: 2rem; font-weight: 800;"><?= (int)$order['total_qty'] ?></div>
            </div>
            <div class="panel text-center" style="background: var(--bg-page); padding: 15px;">
                <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase;">Total Value</div>
                <div style="font-size: 2rem; font-weight: 800; color: var(--btn-success-bg);"><?= format_currency($order['total_price']) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Item Breakdown -->
<div class="panel">
    <h2 style="margin:0 0 15px; font-size: 1.2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">📦 Sold Items breakdown</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Brand & Model</th>
                <th>Configuration Specs (at time of sale)</th>
                <th style="text-align:center;">Qty</th>
                <th style="text-align:right;">Unit Price</th>
                <th style="text-align:right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td style="font-weight: bold;">
                    <?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>
                </td>
                <td style="font-size: 0.85rem; color: var(--text-secondary);">
                    <?= htmlspecialchars($item['specs_blob']) ?>
                </td>
                <td style="text-align:center; font-weight:bold;">
                    <?= (int)$item['qty'] ?>
                </td>
                <td style="text-align:right;">
                    <?= format_currency($item['unit_price']) ?>
                </td>
                <td style="text-align:right; font-weight:bold; color: var(--btn-success-bg);">
                    <?= format_currency($item['total_price']) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div style="margin-top: 30px; display: flex; gap: 10px;">
    <a href="orders.php" class="btn" style="background: var(--bg-page); border: 1px solid var(--border-color); color: var(--text-secondary);">← Back to History</a>
    <button class="btn btn-danger" onclick="rollbackOrder(<?= $order_id ?>)" style="margin-left: auto;">🧨 Rollback Order (Delete & Return to Stock)</button>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-orders').classList.add('active');
    });

    async function rollbackOrder(id) {
        if (!confirm("⚠️ DANGER: This will delete the order record and move all items back into 'In Warehouse' stock.\n\nProceed?")) return;
        
        try {
            const formData = new FormData();
            formData.append('order_id', id);

            const response = await fetch('api/delete_order.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert("✅ Order rolled back successfully. Items are back in stock.");
                window.location.href = 'orders.php';
            } else {
                alert("❌ Error: " + result.error);
            }
        } catch (err) {
            console.error(err);
            alert("❌ Network error attempting to delete order.");
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>
