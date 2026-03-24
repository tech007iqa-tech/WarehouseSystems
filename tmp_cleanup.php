<?php
// cleanup_sold_labels.php
require_once 'includes/db.php';
$pdo_labels->exec("UPDATE items SET status = 'In Warehouse', buyer_name = NULL, buyer_order_num = NULL, sale_price = NULL");
echo "Done. All labels reset to In Warehouse.";
?>
