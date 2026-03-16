<?php
require_once 'includes/db.php';
$cols = ['battery_specs', 'gpu'];
foreach($cols as $c) {
    try { 
        $pdo_labels->exec("ALTER TABLE items ADD COLUMN $c TEXT"); 
        echo "Added column: $c\n";
    } catch(Exception $e) {
        echo "Column $c already exists or failed: " . $e->getMessage() . "\n";
    }
}
echo "Migration complete.";
?>
