<?php
/**
 * ONE-TIME SETUP SCRIPT
 * Run this by visiting init_db.php in the browser to build the SQLite tables.
 *
 * Once the tables are built successfully according to ARCHITECTURE.md,
 * you can safely delete this file.
 */

header('Content-Type: text/plain');
require_once 'includes/db.php';

echo "Initializing Databases...\n\n";

try {
    // 1. Labels Database: `items`
    echo "Creating labels_db schema...\n";
    $pdo_labels->exec("
        CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT DEFAULT 'Laptop',
            brand TEXT NOT NULL,
            model TEXT NOT NULL,
            series TEXT,
            cpu_gen TEXT,
            cpu_details TEXT,
            ram TEXT,
            storage TEXT,
            battery BOOLEAN,
            battery_specs TEXT,
            gpu TEXT,
            screen_res TEXT,
            webcam TEXT,
            backlit_kb TEXT,
            os_version TEXT,
            cosmetic_grade TEXT,
            work_notes TEXT,
            bios_state TEXT,
            description TEXT,
            status TEXT DEFAULT 'In Warehouse',
            warehouse_location TEXT,
            order_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✔ Labels database schema verified.\n\n";

    // 2. Orders Database: `purchase_orders` & `order_items`
    echo "Creating orders_db schema...\n";
    $pdo_orders->exec("
        CREATE TABLE IF NOT EXISTS purchase_orders (
            order_number INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            total_qty INTEGER,
            total_price NUMERIC,
            document_path TEXT
        );

        CREATE TABLE IF NOT EXISTS order_items (
            line_id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            brand TEXT,
            model TEXT,
            specs_blob TEXT,
            qty INTEGER DEFAULT 1,
            unit_price NUMERIC,
            total_price NUMERIC
        );
    ");
    echo "✔ Orders database schema verified.\n\n";

    // 3. Rolodex Database: `customers`
    echo "Creating rolodex_db schema...\n";
    $pdo_rolodex->exec("
        CREATE TABLE IF NOT EXISTS customers (
            customer_id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_name TEXT,
            contact_person TEXT NOT NULL,
            email TEXT,
            phone TEXT,
            lead_status TEXT DEFAULT 'New Lead',
            address TEXT,
            tax_id TEXT,
            website TEXT,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✔ Rolodex database schema verified.\n\n";

    echo "✅ Setup Complete. The 3 SQLite files have been created in the /db/ directory and tables are configured.";

} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    die();
}
?>
