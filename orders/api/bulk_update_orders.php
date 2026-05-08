<?php
// orders/api/bulk_update_orders.php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../../core/Security.php';

$input = json_decode(file_get_contents('php://input'), true);

try {
    // 1. Security Check
    if (!Security::validate($input['csrf_token'] ?? '')) {
        throw new Exception("Security Error: Invalid token.");
    }

    $ids = $input['ids'] ?? [];
    $status = $input['status'] ?? null;

    if (empty($ids)) {
        throw new Exception("No orders selected.");
    }

    if (!$status) {
        throw new Exception("No status specified.");
    }

    $conn = Database::orders();
    
    // Build positional parameters for safety
    $params = [$status];
    foreach($ids as $id) $params[] = $id;

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE order_id IN ($placeholders)";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

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
