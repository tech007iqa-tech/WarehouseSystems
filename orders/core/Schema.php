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
                company_name TEXT NOT NULL,
                contact_person TEXT,
                website TEXT,
                email TEXT,
                phone TEXT,
                address TEXT,
                shipping_address TEXT,
                internal_notes TEXT,
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
                order_id TEXT NOT NULL DEFAULT 'ORD-DEFAULT',
                customer_id TEXT NOT NULL,
                brand TEXT NOT NULL,
                model TEXT NOT NULL,
                series TEXT NOT NULL,
                cpu TEXT DEFAULT '',
                description TEXT NOT NULL,
                quantity INTEGER NOT NULL,
                unit_price REAL DEFAULT 0.00,
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
                status TEXT DEFAULT '',
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
                display_name TEXT DEFAULT '',
                ppp_sequence_key TEXT DEFAULT '',
                ppp_row_index INTEGER DEFAULT 0,
                ppp_password_len INTEGER DEFAULT 55
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
                title TEXT NOT NULL,
                description TEXT,
                event_date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                color TEXT DEFAULT '#38bdf8',
                customer_id TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ]
    ];

    /**
     * Ensures all tables for a specific database exist and are up to date.
     * Migrations always run (they are idempotent), bypassing the session cache
     * so a stale session never silently skips a column addition.
     *
     * @param PDO $conn The connection to the database
     * @param string $db_name The name of the database (e.g., 'orders')
     */
    public static function ensure($conn, $db_name) {
        if (!isset(self::$blueprints[$db_name])) return;

        foreach (self::$blueprints[$db_name] as $table => $sql) {
            // Always CREATE TABLE IF NOT EXISTS (safe no-op when table exists)
            $conn->exec($sql);

            // Always run migrations — idempotent PRAGMA checks mean no harm.
            // This MUST NOT be gated by the session cache so a stale session
            // cannot silently skip adding a new column.
            self::runMigrations($conn, $db_name, $table);

            if (!Database::isSchemaVerified($db_name, $table)) {
                // --- Initial Data Seeding (once per session) ---
                self::seed($conn, $db_name, $table);
                Database::markSchemaVerified($db_name, $table);
            }
        }
    }

    /**
     * Forces a full schema repair across every database.
     * Called by the Settings integrity button. Clears the session schema cache
     * so every table is re-checked on the next request.
     *
     * @return array  ['fixed' => [...], 'errors' => [...]]
     */
    public static function repairAll() {
        // Wipe session cache so ensure() re-inspects every table
        if (session_status() !== PHP_SESSION_NONE) {
            unset($_SESSION['verified_schemas']);
        }

        $report = ['fixed' => [], 'errors' => []];
        $db_names = array_keys(self::$blueprints);

        foreach ($db_names as $db_name) {
            try {
                $conn = Database::getConnection($db_name);
                foreach (self::$blueprints[$db_name] as $table => $sql) {
                    try {
                        $conn->exec($sql);
                        self::runMigrations($conn, $db_name, $table);
                        $report['fixed'][] = "{$db_name}.{$table}";
                    } catch (Exception $e) {
                        $report['errors'][] = "{$db_name}.{$table}: " . $e->getMessage();
                    }
                }
            } catch (Exception $e) {
                $report['errors'][] = "{$db_name}: " . $e->getMessage();
            }
        }
        return $report;
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
        if ($db_name === 'warehouse' && $table === 'location_statuses') {
            $count = $conn->query("SELECT COUNT(*) FROM location_statuses")->fetchColumn();
            if ($count == 0) {
                $statuses = [
                    ['Working', '#10b981'],
                    ['Audit', '#f59e0b'],
                    ['Shipping', '#3b82f6'],
                    ['In-Review', '#8b5cf6'],
                    ['Warehoused', '#6366f1'],
                    ['Idle', '#64748b']
                ];
                $stmt = $conn->prepare("INSERT INTO location_statuses (name, color) VALUES (?, ?)");
                foreach ($statuses as $st) $stmt->execute($st);
            }
        }
    }

    /**
     * Handles specific column additions and index creation for existing tables.
     */
    private static function runMigrations($conn, $db_name, $table) {
        // --- Customers Schema Evolution ---
        // Handles DBs created from the old stale blueprint that lacked several columns.
        if ($db_name === 'customers' && $table === 'customers') {
            $cols = array_column(
                $conn->query("PRAGMA table_info(customers)")->fetchAll(PDO::FETCH_ASSOC),
                'name'
            );
            $migrations = [
                'website'          => "ALTER TABLE customers ADD COLUMN website TEXT DEFAULT ''",
                'contact_person'   => "ALTER TABLE customers ADD COLUMN contact_person TEXT DEFAULT ''",
                'shipping_address' => "ALTER TABLE customers ADD COLUMN shipping_address TEXT DEFAULT ''",
                'internal_notes'   => "ALTER TABLE customers ADD COLUMN internal_notes TEXT DEFAULT ''",
            ];
            foreach ($migrations as $col => $sql) {
                if (!in_array($col, $cols)) {
                    $conn->exec($sql);
                }
            }
        }

        // --- Order & Item Indexes ---
        if ($db_name === 'orders' && $table === 'items') {
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_items_order ON items(order_id)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_items_customer ON items(customer_id)");
        }

        // --- Warehouse Indexes ---
        if ($db_name === 'warehouse' && $table === 'inventory') {
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_inv_sector ON inventory(sector)");
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_inv_brand ON inventory(brand)");
            $conn->exec("UPDATE inventory SET status = '' WHERE status = 'stocked'");

            $cols = $conn->query("PRAGMA table_info(inventory)")->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array('price', array_column($cols, 'name'))) {
                $conn->exec("ALTER TABLE inventory ADD COLUMN price REAL DEFAULT 0");
            }
        }

        // --- Audit & User Indexes ---
        if ($db_name === 'users' && $table === 'audit_log') {
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_audit_timestamp ON audit_log(timestamp)");
        }

        if ($db_name === 'orders' && $table === 'orders') {
            $cols = $conn->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array('updated_at', array_column($cols, 'name'))) {
                $conn->exec("ALTER TABLE orders ADD COLUMN updated_at DATETIME");
                $conn->exec("UPDATE orders SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL");
            }
        }

        if ($db_name === 'users' && $table === 'users') {
            $cols = $conn->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
            $col_names = array_column($cols, 'name');
            if (!in_array('display_name', $col_names)) {
                $conn->exec("ALTER TABLE users ADD COLUMN display_name TEXT DEFAULT ''");
            }
            if (!in_array('ppp_sequence_key', $col_names)) {
                $conn->exec("ALTER TABLE users ADD COLUMN ppp_sequence_key TEXT DEFAULT ''");
            }
            if (!in_array('ppp_row_index', $col_names)) {
                $conn->exec("ALTER TABLE users ADD COLUMN ppp_row_index INTEGER DEFAULT 0");
            }
            if (!in_array('ppp_password_len', $col_names)) {
                $conn->exec("ALTER TABLE users ADD COLUMN ppp_password_len INTEGER DEFAULT 55");
            }
        }

        // --- Calendar Schema Evolution ---
        if ($db_name === 'calendar' && $table === 'events') {
            $cols = array_column(
                $conn->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_ASSOC),
                'name'
            );
            $migrations = [
                'event_date'  => "ALTER TABLE events ADD COLUMN event_date DATE DEFAULT ''",
                'color'        => "ALTER TABLE events ADD COLUMN color TEXT DEFAULT '#38bdf8'",
                'customer_id'  => "ALTER TABLE events ADD COLUMN customer_id TEXT DEFAULT ''",
            ];
            foreach ($migrations as $col => $sql) {
                if (!in_array($col, $cols)) {
                    $conn->exec($sql);
                }
            }
        }
    }
}
