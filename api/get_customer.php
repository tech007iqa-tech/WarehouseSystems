<?php
// api/get_customer.php
// GET ?id=N : Returns a single customer record from rolodex.sqlite.
// Used by rolodex.js to pre-fill the inline edit form.
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) throw new Exception('A valid customer ID is required.');

    $stmt = $pdo_rolodex->prepare("SELECT * FROM customers WHERE customer_id = :id");
    $stmt->execute([':id' => $id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) throw new Exception('Customer #' . $id . ' not found.');

    send_json_response(true, ['customer' => $customer]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
