<?php
/**
 * IQA System Schema Registry
 * Centralized blueprint for all system databases.
 * Maintains the "Self-Healing" nature of the application.
 */

class Schema {
    /**
     * Blueprints for all system databases.
     */
    private static $blueprints = [
        'customers' => [
            'customers' => "CREATE TABLE IF NOT EXISTS customers (
                customer_id TEXT PRIMARY KEY,
                company_name TEXT,
                contact_name TEXT,
                email TEXT,
                phone TEXT,
                address TEXT,
                sector TEXT,
                callback_date TEXT DEFAULT '',
                message_date TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ],
        'orders' => [
            'orders' => "CREATE TABLE IF NOT EXISTS orders (
                order_id TEXT PRIMARY KEY,
                customer_id TEXT,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'items' => "CREATE TABLE IF NOT EXISTS items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id TEXT,
                customer_id TEXT,
                brand TEXT,
                model TEXT,
                series TEXT,
                cpu TEXT,
                description TEXT,
                quantity INTEGER,
                unit_price REAL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ],
        'warehouse' => [
            'sectors' => "CREATE TABLE IF NOT EXISTS sectors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                description TEXT,
                icon TEXT,
                color_theme TEXT
            )",
            'inventory' => "CREATE TABLE IF NOT EXISTS inventory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_owner TEXT NOT NULL,
                sector TEXT NOT NULL,
                location_code TEXT DEFAULT 'ZONE-0', 
                brand TEXT NOT NULL,
                model TEXT NOT NULL,
                specs_json TEXT,
                quantity INTEGER DEFAULT 0,
                status TEXT DEFAULT 'stocked',
                last_updated_by TEXT,
                price REAL DEFAULT 0.00,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'locations' => "CREATE TABLE IF NOT EXISTS locations (
                location_code TEXT PRIMARY KEY,
                status TEXT DEFAULT 'Idle',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            'location_statuses' => "CREATE TABLE IF NOT EXISTS location_statuses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT UNIQUE,
                color TEXT
            )"
        ],
        'users' => [
            'users' => "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE,
                password TEXT,
                role TEXT,
                display_name TEXT DEFAULT ''
            )",
            'audit_log' => "CREATE TABLE IF NOT EXISTS audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                user_id TEXT,
                user_name TEXT,
                module TEXT,
                action TEXT,
                target_id TEXT,
                details TEXT,
                ip_address TEXT
            )"
        ],
        'calendar' => [
            'events' => "CREATE TABLE IF NOT EXISTS events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT,
                start_time DATETIME,
                end_time DATETIME,
                description TEXT,
                type TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )"
        ]
    ];

    /**
     * Ensures all tables for a specific database exist and are up to date.
     * 
     * @param PDO $conn The connection to the database
     * @param string $db_name The name of the database (e.g., 'orders')
     */
    public static function ensure($conn, $db_name) {
        if (!isset(self::$blueprints[$db_name])) return;

        foreach (self::$blueprints[$db_name] as $table => $sql) {
            if (!Database::isSchemaVerified($db_name, $table)) {
                $conn->exec($sql);
                
                // --- Handle Specific Column Evolutions (Migrations) ---
                self::runMigrations($conn, $db_name, $table);
                
                // --- Initial Data Seeding ---
                self::seed($conn, $db_name, $table);

                Database::markSchemaVerified($db_name, $table);
            }
        }
    }

    /**
     * Seeds initial data into empty tables.
     */
    private static function seed($conn, $db_name, $table) {
        if ($db_name === 'warehouse' && $table === 'sectors') {
            $count = $conn->query("SELECT COUNT(*) FROM sectors")->fetchColumn();
            if ($count == 0) {
                $sectors = [
                    ['Laptops', 'Standard portable computing hardware', '💻', '#3b82f6'],
                    ['Gaming', 'High-performance GPUs and gaming rigs', '🎮', '#8b5cf6'],
                    ['Desktops', 'Workstations and office towers', '🖥️', '#6366f1'],
                    ['Electronics', 'Consumer electronics and peripherals', '🔌', '#f59e0b']
                ];
                $stmt = $conn->prepare("INSERT INTO sectors (name, description, icon, color_theme) VALUES (?, ?, ?, ?)");
                foreach ($sectors as $s) $stmt->execute($s);
            }
        }
    }

    /**
     * Handles specific column additions for existing tables.
     */
    private static function runMigrations($conn, $db_name, $table) {
        // Migration: Add updated_at to orders if missing
        if ($db_name === 'orders' && $table === 'orders') {
            $cols = $conn->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array('updated_at', array_column($cols, 'name'))) {
                // SQLite Limitation: Cannot ADD COLUMN with non-constant default (CURRENT_TIMESTAMP)
                $conn->exec("ALTER TABLE orders ADD COLUMN updated_at DATETIME");
                $conn->exec("UPDATE orders SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL");
            }
        }

        // Example: Add display_name to users if missing
        if ($db_name === 'users' && $table === 'users') {
            $cols = $conn->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array('display_name', array_column($cols, 'name'))) {
                $conn->exec("ALTER TABLE users ADD COLUMN display_name TEXT DEFAULT ''");
            }
        }
        
        // Example: Add price to warehouse inventory if missing
        if ($db_name === 'warehouse' && $table === 'inventory') {
            $cols = $conn->query("PRAGMA table_info(inventory)")->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array('price', array_column($cols, 'name'))) {
                $conn->exec("ALTER TABLE inventory ADD COLUMN price REAL DEFAULT 0");
            }
        }
    }
}
