<?php
// migrations/migrate_cpu_split.php
require_once __DIR__ . '/../includes/db.php';

$cols = ['cpu_specs', 'cpu_cores', 'cpu_speed'];

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
