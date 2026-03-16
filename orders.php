<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Fetch all purchase orders (newest first)
$orders = [];
try {
    $stmt = $pdo_orders->query("
        SELECT * FROM purchase_orders ORDER BY order_date DESC
    ");
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    // Graceful fail — table may not be initialized yet
}

// Build a customer lookup map from rolodex (separate DB, can't JOIN directly)
$customer_map = [];
try {
    $stmt_c = $pdo_rolodex->query("SELECT customer_id, company_name, contact_person FROM customers");
    foreach ($stmt_c->fetchAll() as $c) {
        $customer_map[$c['customer_id']] = $c;
    }
} catch (PDOException $e) {
    // Graceful fail
}
?>

<div class="panel flex-between">
    <div>
        <h1>🧾 Purchase Orders</h1>
        <p>All generated B2B purchase forms. Click a row to download its <code>.ots</code> file.</p>
    </div>
    <a href="new_order.php" class="btn btn-success">🛒 Create New Order</a>
</div>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Contact</th>
                <th>Date</th>
                <th>Total Items</th>
                <th>Total Value</th>
                <th>Document</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="7" class="text-center" style="padding: 40px; font-style: italic; color: var(--text-secondary);">
                        No purchase orders yet.<br>
                        <a href="new_order.php" style="color: var(--accent-color);">Create your first B2B order →</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php
                        $cid      = $order['customer_id'];
                        $company  = $customer_map[$cid]['company_name']   ?? 'Unknown Customer';
                        $contact  = $customer_map[$cid]['contact_person'] ?? '—';
                        $order_num_pad = 'ORD-' . str_pad($order['order_number'], 6, '0', STR_PAD_LEFT);
                        $doc_path = $order['document_path'] ?? '';
                        $file_exists = $doc_path && file_exists(__DIR__ . '/' . $doc_path);
                    ?>
                    <tr>
                        <td style="font-weight: bold;">
                            <a href="order_view.php?id=<?= (int)$order['order_number'] ?>" style="color:var(--accent-color); text-decoration:none;">
                                <?= htmlspecialchars($order_num_pad) ?>
                            </a>
                        </td>

                        <td style="font-weight: bold;">
                            <?= htmlspecialchars($company) ?>
                        </td>

                        <td style="color: var(--text-secondary); font-size: 0.9rem;">
                            <?= htmlspecialchars($contact) ?>
                        </td>

                        <td style="font-size: 0.9rem; color: var(--text-secondary);">
                            <?= format_date($order['order_date']) ?>
                        </td>

                        <td style="text-align: center;">
                            <span style="background: var(--bg-page); border: 1px solid var(--border-color); padding: 2px 10px; border-radius: 20px; font-size: 0.9rem;">
                                <?= (int)$order['total_qty'] ?> units
                            </span>
                        </td>

                        <td style="font-weight: bold; color: var(--btn-success-bg);">
                            <?= format_currency($order['total_price']) ?>
                        </td>

                        <td>
                            <?php if ($file_exists): ?>
                                <a href="<?= htmlspecialchars($doc_path) ?>"
                                   class="btn btn-primary"
                                   style="font-size: 0.8rem; padding: 6px 12px;"
                                   download>
                                    ⬇ Download .ots
                                </a>
                            <?php elseif ($doc_path): ?>
                                <span style="color: var(--btn-danger-bg); font-size: 0.85rem;">⚠ File missing</span>
                            <?php else: ?>
                                <span style="color: var(--text-secondary); font-size: 0.85rem;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-orders').classList.add('active');
    });
</script>

<?php require_once 'includes/footer.php'; ?>
