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

    if (isset($input['action']) && $input['action'] === 'bulk_import') {
        $customer_id = $input['customer_id'] ?? null;
        $rows = $input['rows'] ?? [];

        if (!$customer_id || empty($rows)) {
            throw new Exception("Missing customer data or rows.");
        }

        $conn = Database::orders();
        $conn->beginTransaction();

        try {
            // 1. Create Order
            $order_id = 'ORD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $stmt_o = $conn->prepare("INSERT INTO orders (order_id, customer_id, status) VALUES (?, ?, 'active')");
            $stmt_o->execute([$order_id, $customer_id]);

            // 2. Insert Items
            // Format: Type Brand Model Series CPU Description Price QTY
            $stmt_i = $conn->prepare("INSERT INTO items (order_id, customer_id, brand, model, series, cpu, description, quantity, unit_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($rows as $cols) {
                if (count($cols) < 2) continue; // Skip empty/invalid rows

                // Skip Header Row if pasted
                if (strtolower(trim($cols[0])) === 'type' || strtolower(trim($cols[1])) === 'brand') {
                    continue;
                }

                $brand = trim($cols[1] ?? 'Generic');
                $model = trim($cols[2] ?? 'Bulk Item');
                $series = trim($cols[3] ?? 'N/A');
                $cpu = trim($cols[4] ?? '');
                $description = trim($cols[5] ?? '');
                
                // Sanitize Price: Remove $ and , then convert to float
                $raw_price = trim($cols[6] ?? '0');
                $price = (float)preg_replace('/[^-0-9.]/', '', $raw_price);
                
                $qty = (int)($cols[7] ?? 1);

                if (empty($brand) && empty($model)) continue;

                $stmt_i->execute([
                    $order_id,
                    $customer_id,
                    $brand,
                    $model,
                    $series,
                    $cpu,
                    $description,
                    $qty,
                    $price
                ]);
            }

            $conn->commit();
            echo json_encode([
                'success' => true,
                'order_id' => $order_id
            ]);
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
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
