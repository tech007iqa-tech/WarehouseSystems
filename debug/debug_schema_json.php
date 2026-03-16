<?php
require_once __DIR__ . '/includes/db.php';
$stmt = $pdo_labels->query("PRAGMA table_info(items)");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
