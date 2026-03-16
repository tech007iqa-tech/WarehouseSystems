<?php
/**
 * DATABASE MIGRATION SCRIPT
 * Run this by visiting migrate_rolodex.php in the browser to update existing tables.
 */

header('Content-Type: text/plain');
require_once 'includes/db.php';

echo "Running Migration for Rolodex...\n\n";

try {
    // Check if columns exist before adding them (standard SQLite pattern for migrations)
    $stmt = $pdo_rolodex->query("PRAGMA table_info(customers)");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

    if (!in_array('address', $columns)) {
        echo "Adding 'address' column...\n";
        $pdo_rolodex->exec("ALTER TABLE customers ADD COLUMN address TEXT");
    }
    if (!in_array('tax_id', $columns)) {
        echo "Adding 'tax_id' column...\n";
        $pdo_rolodex->exec("ALTER TABLE customers ADD COLUMN tax_id TEXT");
    }
    if (!in_array('website', $columns)) {
        echo "Adding 'website' column...\n";
        $pdo_rolodex->exec("ALTER TABLE customers ADD COLUMN website TEXT");
    }

    echo "\n✅ Migration Complete. Your Rolodex database now supports detailed card data.";

} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    die();
}
?>
