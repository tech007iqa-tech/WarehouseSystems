<?php
require_once 'core/database.php';
$db = Database::orders();
$result = $db->query("SELECT sql FROM sqlite_master WHERE type='table' OR type='trigger'");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo $row['sql'] . ";\n";
}
