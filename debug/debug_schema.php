<?php
require_once __DIR__ . '/includes/db.php';
$stmt = $pdo_labels->query("PRAGMA table_info(items)");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['name'] . "\n";
}
?>
