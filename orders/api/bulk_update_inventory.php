<?php
// orders/api/bulk_update_inventory.php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/warehouse_db.php';
require_once __DIR__ . '/../../core/Security.php';

$input = json_decode(file_get_contents('php://input'), true);

try {
    // 1. Security Check
    if (!Security::validate($input['csrf_token'] ?? '')) {
        throw new Exception("Security Error: Invalid token.");
    }

    $ids = $input['ids'] ?? [];
    $location = $input['location'] ?? null;
    $price = $input['price'] ?? null;

    if (empty($ids)) {
        throw new Exception("No items selected.");
    }

    if (!$location && !$price) {
        throw new Exception("No changes specified.");
    }

    $updates = [];
    $params = [];

    if ($location) {
        $updates[] = "location_code = ?";
        $params[] = $location;
    }

    if ($price) {
        $updates[] = "price = ?";
        $params[] = $price;
    }

    $updateStr = implode(', ', $updates);
    
    // Add IDs to params for the IN clause
    foreach($ids as $id) $params[] = $id;

    $sql = "UPDATE inventory SET {$updateStr}, updated_at = CURRENT_TIMESTAMP WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")";

    $stmt = $conn_wh->prepare($sql);
    $stmt->execute($params);

    // If location changed, ensure location entry exists in 'locations' table
    if ($location) {
        $stmt_loc = $conn_wh->prepare("INSERT OR IGNORE INTO locations (location_code, status) VALUES (?, 'Idle')");
        $stmt_loc->execute([$location]);
    }

    echo json_encode([
        'success' => true,
        'count' => count($ids)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
