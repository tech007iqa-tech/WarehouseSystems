<?php
// api/update_order_status.php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
    $status   = sanitize_text($_POST['status'] ?? '');

    if ($order_id <= 0 || !$status) {
        throw new Exception('Invalid order ID or status.');
    }

    // 1. Fetch the Order details
    $stmt_order = $pdo_orders->prepare("SELECT order_number FROM purchase_orders WHERE order_number = :id");
    $stmt_order->execute([':id' => $order_id]);
    $order = $stmt_order->fetch();

    if (!$order) {
        throw new Exception('Order not found.');
    }

    $order_num_pad = 'ORD-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);

    // 2. Update the Order status
    $stmt_update = $pdo_orders->prepare("UPDATE purchase_orders SET invoice_status = :status WHERE order_number = :id");
    $stmt_update->execute([':status' => $status, ':id' => $order_id]);

    // 3. LOGISTICAL SYNC (DISABLED)
    // Inventory items are now treated as master templates and remain in the warehouse library.
    // Financial status is tracked exclusively in the Orders database.

    send_json_response(true, ['message' => "Order $order_num_pad updated to $status."]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
