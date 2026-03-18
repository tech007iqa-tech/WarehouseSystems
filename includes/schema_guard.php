<?php
// includes/schema_guard.php
// This utility ensures that if a database file is missing or corrupted,
// the system can automatically rebuild the tables to prevent a total crash.

function check_and_rebuild_schemas($pdo_labels, $pdo_orders, $pdo_rolodex) {
    try {
        // 1. Check Labels
        $pdo_labels->exec("CREATE TABLE IF NOT EXISTS items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT DEFAULT 'Laptop',
            brand TEXT NOT NULL,
            model TEXT NOT NULL,
            series TEXT,
            cpu_gen TEXT,
            cpu_specs TEXT,
            cpu_cores TEXT,
            cpu_speed TEXT,
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
            serial_number TEXT,
            order_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 1.5. Automated Migration: Add serial_number if table was already present
        $stmt_info = $pdo_labels->query("PRAGMA table_info(items)");
        $columns = $stmt_info->fetchAll(PDO::FETCH_ASSOC);
        $has_sn = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'serial_number') {
                $has_sn = true;
                break;
            }
        }
        if (!$has_sn) {
            $pdo_labels->exec("ALTER TABLE items ADD COLUMN serial_number TEXT");
        }

        // 2. Check Orders
        $pdo_orders->exec("CREATE TABLE IF NOT EXISTS purchase_orders (
            order_number INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            total_qty INTEGER,
            total_price NUMERIC,
            document_path TEXT
        )");
        $pdo_orders->exec("CREATE TABLE IF NOT EXISTS order_items (
            line_id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            brand TEXT,
            model TEXT,
            specs_blob TEXT,
            qty INTEGER DEFAULT 1,
            unit_price NUMERIC,
            total_price NUMERIC
        )");
        $pdo_orders->exec("CREATE TABLE IF NOT EXISTS sold_history (
            history_id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number INTEGER NOT NULL,
            item_id INTEGER NOT NULL,
            serial_number TEXT,
            customer_id INTEGER NOT NULL,
            brand_model TEXT,
            sale_price NUMERIC,
            sale_date DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 3. Check Rolodex
        $pdo_rolodex->exec("CREATE TABLE IF NOT EXISTS customers (
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
        )");

        return true;
    } catch (Exception $e) {
        error_log("Schema Guard Error: " . $e->getMessage());
        return false;
    }
}
?>
