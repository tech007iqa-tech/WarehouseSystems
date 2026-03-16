<?php
// api/search_inventory.php
// GET endpoint: searches labels.sqlite for available warehouse items.
// Used by the new_order.php cart JS to populate search results.
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';

$response = ['success' => false, 'data' => null, 'error' => null];

try {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';

    // Build a safe search term
    $search_term = '%' . $q . '%';

    $stmt = $pdo_labels->prepare("
        SELECT id, brand, model, series, cpu_gen, cpu_details, ram, storage,
               battery, bios_state, description, warehouse_location, status
        FROM items
        WHERE (
            brand       LIKE :q1 OR
            model       LIKE :q2 OR
            series      LIKE :q3 OR
            cpu_gen     LIKE :q4 OR
            description LIKE :q5
          )
        ORDER BY id DESC
        LIMIT 50
    ");

    $stmt->execute([
        ':q1' => $search_term,
        ':q2' => $search_term,
        ':q3' => $search_term,
        ':q4' => $search_term,
        ':q5' => $search_term,
    ]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['data']    = $items;

} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;
