<?php
// api/search_inventory.php
// GET endpoint: searches labels.sqlite for available warehouse items.
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/hardware_mapping.php';

$response = ['success' => false, 'data' => null, 'error' => null];

try {
    $F = HW_FIELDS;
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $search_term = '%' . $q . '%';

    $stmt = $pdo_labels->prepare("
        SELECT id, {$F['BRAND']}, {$F['MODEL']}, {$F['SERIES']}, {$F['CPU_GEN']}, {$F['CPU_DETAILS']}, {$F['RAM']}, {$F['STORAGE']},
               {$F['BATTERY']}, {$F['BIOS_STATE']}, {$F['DESCRIPTION']}, {$F['LOCATION']}, {$F['STATUS']}
        FROM items
        WHERE (
            {$F['BRAND']}       LIKE :q1 OR
            {$F['MODEL']}       LIKE :q2 OR
            {$F['SERIES']}      LIKE :q3 OR
            {$F['CPU_GEN']}     LIKE :q4 OR
            {$F['DESCRIPTION']} LIKE :q5
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
?>
