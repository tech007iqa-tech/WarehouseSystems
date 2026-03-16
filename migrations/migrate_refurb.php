<?php
require_once 'includes/db.php';
$cols = ['screen_res','webcam','backlit_kb','os_version','cosmetic_grade','work_notes'];
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
