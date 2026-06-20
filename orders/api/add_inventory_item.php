<?php
// orders/api/add_inventory_item.php
header('Content-Type: application/json');
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/Security.php';
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!Security::validate($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

try {
    $conn_wh = Database::warehouse();
    $current_user = $_SESSION['username'] ?? 'System';

    $sector = $_POST['sector'] ?? 'Laptops';
    $loc = $_POST['location_code'] ?? '';
    $brand = $_POST['brand'] ?? '';
    $model = $_POST['model'] ?? '';
    $qty = (int)($_POST['quantity'] ?? 1);
    $price = (float)($_POST['price'] ?? 0.00);

    if (empty($brand) || empty($model) || empty($loc)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields (brand, model, location_code).']);
        exit;
    }

    // Dynamic Specs mapping based on sector
    $specs = [];
    if ($sector === 'Laptops') {
        $specs = [
            'cpu' => $_POST['cpu'] ?? '',
            'gpu' => $_POST['gpu'] ?? '',
            'ram' => $_POST['ram'] ?? '',
            'storage' => $_POST['storage'] ?? '',
            'battery' => $_POST['battery'] ?? '',
            'windows' => $_POST['windows'] ?? '',
            'series' => $_POST['series'] ?? '',
            'gen' => $_POST['gen'] ?? '',
            'bios' => $_POST['bios'] ?? '',
            'condition' => $_POST['condition'] ?? 'Used',
            'notes' => $_POST['notes'] ?? ''
        ];
    } elseif ($sector === 'Gaming') {
        $specs = [
            'category' => $_POST['gaming_category'] ?? 'PC',
            'series' => $_POST['series'] ?? '',
            'condition' => $_POST['condition'] ?? 'Used',
            'notes' => $_POST['notes'] ?? '',
            'ram' => $_POST['ram'] ?? '',
            'storage' => $_POST['storage'] ?? '',
            'cpu' => $_POST['cpu'] ?? '',
            'gpu' => $_POST['gpu'] ?? ''
        ];
    } elseif ($sector === 'Desktops') {
        $specs = [
            'cpu_gen' => $_POST['cpu_gen'] ?? '',
            'condition' => $_POST['condition'] ?? 'Used',
            'notes' => $_POST['notes'] ?? ''
        ];
    } else {
        $specs = [
            'type' => $_POST['type'] ?? '',
            'voltage' => $_POST['voltage'] ?? '',
            'condition' => $_POST['condition'] ?? 'Used',
            'notes' => $_POST['notes'] ?? ''
        ];
    }

    $specs_json = json_encode($specs);

    $stmt = $conn_wh->prepare("INSERT INTO inventory (user_owner, sector, location_code, brand, model, specs_json, quantity, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$current_user, $sector, $loc, $brand, $model, $specs_json, $qty, $price])) {
        $new_id = $conn_wh->lastInsertId();

        // Fetch new total for this sector & location
        $stmt_total = $conn_wh->prepare("SELECT SUM(quantity) FROM inventory WHERE sector = ? AND location_code = ?");
        $stmt_total->execute([$sector, $loc]);
        $new_total = $stmt_total->fetchColumn() ?: 0;

        echo json_encode([
            'success' => true,
            'new_id' => $new_id,
            'new_total' => $new_total
        ]);
    } else {
        throw new Exception("Failed to insert inventory item.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
