<?php
// api/add_customer.php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

try {
    // 1. Validation & Sanitization
    $contact_person = sanitize_text($_POST['contact_person'] ?? null);
    
    // We strictly require at least a contact person according to our schema
    if (empty($contact_person)) {
        throw new Exception("Contact Person is strongly required.");
    }

    $company_name = sanitize_text($_POST['company_name'] ?? null);
    $email = sanitize_text($_POST['email'] ?? null);
    $phone = sanitize_text($_POST['phone'] ?? null);
    $website = sanitize_text($_POST['website'] ?? null);
    $address = sanitize_text($_POST['address'] ?? null);
    $tax_id = sanitize_text($_POST['tax_id'] ?? null);
    $lead_status = sanitize_text($_POST['lead_status'] ?? 'New Lead');
    $tier = sanitize_text($_POST['tier'] ?? 'Bronze');
    $notes = sanitize_text($_POST['notes'] ?? null);

    // 2. Insert into rolodex.sqlite Database using Prepared Statement
    $stmt = $pdo_rolodex->prepare("
        INSERT INTO customers (
            company_name, contact_person, email, phone, website, address, tax_id, lead_status, tier, notes
        ) VALUES (
            :company, :contact, :email, :phone, :website, :address, :tax_id, :status, :tier, :notes
        )
    ");

    $stmt->execute([
        ':company' => $company_name,
        ':contact' => $contact_person,
        ':email' => $email,
        ':phone' => $phone,
        ':website' => $website,
        ':address' => $address,
        ':tax_id' => $tax_id,
        ':status' => $lead_status,
        ':tier' => $tier,
        ':notes' => $notes
    ]);

    $inserted_id = $pdo_rolodex->lastInsertId();

    // 3. Return JSON response for async fetch success
    send_json_response(true, [
        'customer_id' => $inserted_id,
        'message' => 'Customer successfully added to Rolodex.'
    ]);

} catch (Exception $e) {
    send_json_response(false, null, $e->getMessage());
}
?>
