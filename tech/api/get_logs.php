<?php
require_once '../core/database.php';
require_once '../core/auth.php';

header('Content-Type: application/json');

try {
    $conn = Database::tech();
    
    // Fetch logs from today, ordered by newest first
    $stmt = $conn->prepare("SELECT * FROM logs WHERE date(created_at) = date('now', 'localtime') ORDER BY created_at DESC LIMIT 50");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $logs]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
