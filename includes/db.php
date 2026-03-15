<?php
// includes/db.php
// Initializes PDO connections to the 3 SQLite files with strict error handling.

$db_dir = __DIR__ . '/../db/';

// Ensure directory exists (useful for initial run before sqlite auto-creates files)
if (!is_dir($db_dir)) {
    mkdir($db_dir, 0777, true);
}

// Database paths
$labels_db_path = $db_dir . 'labels.sqlite';
$orders_db_path = $db_dir . 'orders.sqlite';
$rolodex_db_path = $db_dir . 'rolodex.sqlite';

try {
    // 1. Labels Database
    $pdo_labels = new PDO("sqlite:" . $labels_db_path);
    $pdo_labels->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_labels->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Enable Foreign Keys (which we might use later)
    $pdo_labels->exec('PRAGMA foreign_keys = ON;');

    // 2. Orders Database
    $pdo_orders = new PDO("sqlite:" . $orders_db_path);
    $pdo_orders->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_orders->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // 3. Rolodex Database
    $pdo_rolodex = new PDO("sqlite:" . $rolodex_db_path);
    $pdo_rolodex->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo_rolodex->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Return early if called from an API endpoint expecting JSON (Vibe Code standard)
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
    
    // Fallback for direct HTML view
    die("Database Connection Error: " . $e->getMessage());
}
?>
