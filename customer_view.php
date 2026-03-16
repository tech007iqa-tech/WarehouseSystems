<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = null;

if ($id > 0) {
    try {
        $stmt = $pdo_rolodex->prepare("SELECT * FROM customers WHERE customer_id = :id");
        $stmt->execute([':id' => $id]);
        $customer = $stmt->fetch();
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

if (!$customer) {
    echo '<div class="panel text-center"><h1>404</h1><p>Customer not found.</p><a href="rolodex.php" class="btn btn-primary">Back to Rolodex</a></div>';
    require_once 'includes/footer.php';
    exit;
}

// Get order history for this customer
$orders = [];
try {
    $stmt_orders = $pdo_orders->prepare("SELECT * FROM purchase_orders WHERE customer_id = :cid ORDER BY order_date DESC");
    $stmt_orders->execute([':cid' => $id]);
    $orders = $stmt_orders->fetchAll();
} catch (PDOException $e) {
    // Graceful fail
}
?>

<div class="panel flex-between" style="background: var(--bg-panel); border-left: 5px solid var(--accent-color);">
    <div>
        <h1 style="margin:0; font-size: 2rem; color: var(--accent-color);"><?= htmlspecialchars($customer['company_name'] ?: $customer['contact_person']) ?></h1>
        <p style="margin:5px 0 0; color: var(--text-secondary);">Customer Card & Account Overview</p>
    </div>
    <div style="text-align: right;">
        <span class="btn" style="background: var(--bg-page); border: 1px solid var(--border-color); color: var(--text-secondary); cursor: default;">
            Record Created: <?= format_date($customer['created_at']) ?>
        </span>
    </div>
</div>

<div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">
    
    <!-- Identity & Contact Information -->
    <div class="panel card" style="height: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 15px;">
            <h2 style="margin:0; font-size: 1.2rem; color: var(--text-main);">🏢 Profile Details</h2>
            <span style="background: var(--accent-color); color: #fff; padding: 2px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold;">
                <?= htmlspecialchars($customer['lead_status']) ?>
            </span>
        </div>

        <div style="display: grid; grid-template-columns: 100px 1fr; gap: 10px; font-size: 0.95rem;">
            <div style="color: var(--text-secondary);">Contact:</div>
            <div style="font-weight: 500;"><?= htmlspecialchars($customer['contact_person']) ?></div>

            <div style="color: var(--text-secondary);">Email:</div>
            <div>
                <?php if ($customer['email']): ?>
                    <a href="mailto:<?= htmlspecialchars($customer['email']) ?>" style="color: var(--accent-color);"><?= htmlspecialchars($customer['email']) ?></a>
                <?php else: ?>
                    <span style="color: var(--text-secondary); font-style: italic;">Not provided</span>
                <?php endif; ?>
            </div>

            <div style="color: var(--text-secondary);">Phone:</div>
            <div><?= htmlspecialchars($customer['phone'] ?: 'N/A') ?></div>

            <div style="color: var(--text-secondary);">Website:</div>
            <div>
                <?php if ($customer['website']): ?>
                    <a href="<?= htmlspecialchars($customer['website']) ?>" target="_blank" style="color: var(--accent-color);"><?= htmlspecialchars($customer['website']) ?></a>
                <?php else: ?>
                    <span style="color: var(--text-secondary); font-style: italic;">Not provided</span>
                <?php endif; ?>
            </div>

            <div style="color: var(--text-secondary);">Address:</div>
            <div style="font-family: inherit;"><?= htmlspecialchars($customer['tax_id'] ?: 'N/A') ?></div>
        </div>

        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed var(--border-color);">
            <div style="color: var(--text-secondary); margin-bottom: 5px; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px;">Internal Notes</div>
            <p style="background: var(--bg-page); padding: 10px; border-radius: 4px; border: 1px solid var(--border-color); font-size: 0.9rem; line-height: 1.5;">
                <?= nl2br(htmlspecialchars($customer['notes'] ?: 'No internal notes for this contact.')) ?>
            </p>
        </div>
    </div>

    <!-- Shipping & Logistics -->
    <div class="panel card" style="height: 100%;">
        <div style="border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 15px;">
            <h2 style="margin:0; font-size: 1.2rem; color: var(--text-main);">📍 Shipping Address</h2>
        </div>
        
        <div style="background: var(--bg-page); padding: 20px; border-radius: 8px; border: 1px solid var(--border-color); position: relative; overflow: hidden;">
            <div style="position: absolute; top:0; right:0; padding: 5px 10px; background: var(--border-color); font-size: 0.7rem; color: var(--text-secondary); font-weight: bold; text-transform: uppercase;">Primary Label</div>
            <p style="font-family: 'Courier New', Courier, monospace; font-size: 1.1rem; color: var(--text-main); line-height: 1.6; margin:0;">
                <?= $customer['address'] ? nl2br(htmlspecialchars($customer['address'])) : '<span style="color: var(--text-secondary); font-style: italic;">No shipping address on file.</span>' ?>
            </p>
        </div>

        <div style="margin-top: 25px;">
             <h2 style="margin:0 0 10px; font-size: 1.2rem; color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">📉 Order Summary</h2>
             <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px;">
                 <div class="panel text-center" style="padding: 10px; background: var(--bg-page);">
                     <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase;">Total Orders</div>
                     <div style="font-size: 1.8rem; font-weight: bold;"><?= count($orders) ?></div>
                 </div>
                 <div class="panel text-center" style="padding: 10px; background: var(--bg-page);">
                     <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase;">Latest Sale</div>
                     <div style="font-size: 0.9rem; margin-top: 5px;">
                        <?= !empty($orders) ? format_date($orders[0]['order_date']) : 'N/A' ?>
                     </div>
                 </div>
             </div>
        </div>
    </div>
</div>

<!-- Order History Table -->
<div class="panel" style="margin-top: 20px;">
    <div class="flex-between" style="border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-bottom: 15px;">
        <h2 style="margin:0; font-size: 1.2rem; color: var(--text-main);">📑 Purchase Order History</h2>
        <a href="new_order.php" class="btn btn-success" style="font-size: 0.8rem;">+ Create New Order</a>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Order Number</th>
                <th>Date</th>
                <th>Quantity</th>
                <th>Total Value</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="5" class="text-center" style="padding: 20px; color: var(--text-secondary); font-style: italic;">
                        No orders recorded for this customer yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td style="font-weight: bold;">
                        <a href="order_view.php?id=<?= (int)$order['order_number'] ?>" style="color:var(--accent-color); text-decoration:none;">
                            ORD-<?= str_pad($order['order_number'], 6, '0', STR_PAD_LEFT) ?>
                        </a>
                    </td>
                    <td><?= format_date($order['order_date']) ?></td>
                    <td><?= htmlspecialchars($order['total_qty']) ?> Units</td>
                    <td><?= format_currency($order['total_price']) ?></td>
                    <td>
                        <?php if ($order['document_path']): ?>
                            <a href="<?= htmlspecialchars($order['document_path']) ?>" download class="btn" style="font-size: 0.75rem; padding: 4px 10px; background: var(--bg-page); border:1px solid var(--border-color); color: var(--text-main);">
                                ⬇ Download .ots
                            </a>
                        <?php else: ?>
                            <span style="font-size: 0.75rem; color: var(--text-secondary);">No file</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top: 30px; display: flex; gap: 10px;">
    <a href="rolodex.php" class="btn" style="background: var(--bg-page); border: 1px solid var(--border-color); color: var(--text-secondary);">← Back to Rolodex</a>
    <a href="edit_customer.php?id=<?= $id ?>" class="btn btn-primary">✏️ Edit Card</a>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-rolodex').classList.add('active');
    });
</script>

<?php require_once 'includes/footer.php'; ?>
