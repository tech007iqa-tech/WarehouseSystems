<?php
// api/edit_customer.php
// POST: Updates a customer/lead record in rolodex.sqlite.
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    $id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    if ($id <= 0) {
        throw new Exception('A valid customer ID is required.');
    }

    // Verify customer exists
    $stmt_check = $pdo_rolodex->prepare("SELECT customer_id FROM customers WHERE customer_id = :id");
    $stmt_check->execute([':id' => $id]);
    if (!$stmt_check->fetch()) {
        throw new Exception('Customer #' . $id . ' not found.');
    }

    $contact_person = sanitize_text($_POST['contact_person'] ?? null);
    if (!$contact_person) {
        throw new Exception('Contact person name is required.');
    }

    $company_name = sanitize_text($_POST['company_name'] ?? null);
    $email        = sanitize_text($_POST['email']        ?? null);
    $phone        = sanitize_text($_POST['phone']        ?? null);
    $website      = sanitize_text($_POST['website']      ?? null);
    $address      = sanitize_text($_POST['address']      ?? null);
    $tax_id       = sanitize_text($_POST['tax_id']       ?? null);
    $lead_status  = sanitize_text($_POST['lead_status']  ?? 'New Lead');
    $notes        = sanitize_text($_POST['notes']        ?? null);

    $stmt = $pdo_rolodex->prepare("
        UPDATE customers SET
            company_name   = :company_name,
            contact_person = :contact_person,
            email          = :email,
            phone          = :phone,
            website        = :website,
            address        = :address,
            tax_id         = :tax_id,
            lead_status    = :lead_status,
            notes          = :notes
        WHERE customer_id = :id
    ");

    $stmt->execute([
        ':company_name'   => $company_name,
        ':contact_person' => $contact_person,
        ':email'          => $email,
        ':phone'          => $phone,
        ':website'        => $website,
        ':address'        => $address,
        ':tax_id'         => $tax_id,
        ':lead_status'    => $lead_status,
        ':notes'          => $notes,
        ':id'             => $id,
    ]);

    // Return the updated row for JS re-render
    $stmt_fetch = $pdo_rolodex->prepare("SELECT * FROM customers WHERE customer_id = :id");
    $stmt_fetch->execute([':id' => $id]);
    $updated = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

    send_json_response(true, ['customer' => $updated]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
