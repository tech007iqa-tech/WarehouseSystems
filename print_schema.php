<?php
require_once 'includes/db.php';
$stmt = $pdo_labels->query("PRAGMA table_info(items)");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['name'] . " - " . $c['type'] . "\n";
}
