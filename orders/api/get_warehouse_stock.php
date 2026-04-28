<?php
include '../core/warehouse_db.php';
include '../core/auth.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
$sector = $_GET['sector'] ?? 'Laptops';

try {
    $stmt = $conn_wh->prepare("SELECT * FROM inventory WHERE sector = ? AND (brand LIKE ? OR model LIKE ? OR location_code LIKE ?) LIMIT 20");
    $search = "%$query%";
    $stmt->execute([$sector, $search, $search, $search]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Unpack JSON specs for the frontend
    foreach ($items as &$item) {
        $item['specs'] = json_decode($item['specs_json'], true);
    }

    echo json_encode($items);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
