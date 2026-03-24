<?php
require_once 'includes/db.php';
$stmt = $pdo_orders->query("SELECT COUNT(*) FROM purchase_orders WHERE invoice_status != 'Canceled'");
echo "Orders (Not Canceled): " . $stmt->fetchColumn() . "\n";
$stmt = $pdo_orders->query("SELECT SUM(total_qty) FROM purchase_orders WHERE invoice_status != 'Canceled'");
echo "Units (Not Canceled): " . ($stmt->fetchColumn() ?: 0) . "\n";
?>
