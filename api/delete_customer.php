<?php
// api/delete_customer.php
// POST customer_id= : Permanently deletes a customer from rolodex.sqlite.
// Blocks deletion if the customer has any linked orders in orders.sqlite.
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    if ($id <= 0) {
        throw new Exception('A valid customer ID is required.');
    }

    // Verify customer exists
    $stmt_check = $pdo_rolodex->prepare("SELECT customer_id, contact_person, company_name FROM customers WHERE customer_id = :id");
    $stmt_check->execute([':id' => $id]);
    $customer = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception('Customer #' . $id . ' not found.');
    }

    // Block deletion if the customer has any linked purchase orders
    $stmt_orders = $pdo_orders->prepare("SELECT COUNT(*) FROM purchase_orders WHERE customer_id = :id");
    $stmt_orders->execute([':id' => $id]);
    $order_count = (int)$stmt_orders->fetchColumn();

    if ($order_count > 0) {
        throw new Exception(
            'Cannot delete "' . $customer['contact_person'] . '" — they have '
            . $order_count . ' linked purchase order(s). Update their status to Inactive instead.'
        );
    }

    $stmt = $pdo_rolodex->prepare("DELETE FROM customers WHERE customer_id = :id");
    $stmt->execute([':id' => $id]);

    send_json_response(true, [
        'deleted_id' => $id,
        'message'    => '"' . ($customer['company_name'] ?: $customer['contact_person']) . '" has been removed from the Rolodex.'
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
