<?php
// api/delete_order.php
// POST endpoint: Deletes a purchase order and returns items to warehouse inventory.

header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

    if ($order_id <= 0) {
        throw new Exception('Invalid Order ID.');
    }

    // 1. Fetch order details (to get the file path for deletion)
    $stmt = $pdo_orders->prepare("SELECT document_path FROM purchase_orders WHERE order_number = :id");
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('Order not found.');
    }

    // 2. Delete the physical .ots file if it exists
    if ($order['document_path']) {
        $full_path = realpath(__DIR__ . '/../' . $order['document_path']);
        if ($full_path && file_exists($full_path)) {
            unlink($full_path);
        }
    }

    // 3. Delete order line items (orders.sqlite)
    $stmt_lines = $pdo_orders->prepare("DELETE FROM order_items WHERE order_number = :id");
    $stmt_lines->execute([':id' => $order_id]);

    // 4. Delete the order record (orders.sqlite)
    $stmt_del = $pdo_orders->prepare("DELETE FROM purchase_orders WHERE order_number = :id");
    $stmt_del->execute([':id' => $order_id]);

    send_json_response(true, ['message' => 'Order successfully rolled back. Items returned to inventory.']);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
