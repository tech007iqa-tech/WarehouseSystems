<?php
/**
 * Tech Dashboard Database Manager
 * Handles connections to tech.db (Logs & Inventory) and users.db (Authentication)
 */

class Database {
    private static $instances = [];
    private static $db_dir = __DIR__ . '/../../db';

    public static function getConnection($db_name) {
        if (!isset(self::$instances[$db_name])) {
            $db_path = self::$db_dir . '/' . $db_name . '.db';

            if (!is_dir(self::$db_dir)) {
                mkdir(self::$db_dir, 0755, true);
            }

            try {
                $conn = new PDO("sqlite:" . $db_path);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Concurrency optimizations
                $conn->exec("PRAGMA journal_mode = WAL;");
                $conn->exec("PRAGMA busy_timeout = 5000;");
                $conn->exec("PRAGMA synchronous = NORMAL;");
                $conn->exec("PRAGMA foreign_keys = ON;");

                // Initialize Schema for tech database
                if ($db_name === 'tech') {
                    self::initTechSchema($conn);
                }

                self::$instances[$db_name] = $conn;
            } catch (PDOException $e) {
                die("Database Connection Error (" . $db_name . "): " . $e->getMessage());
            }
        }
        return self::$instances[$db_name];
    }

    public static function users() { return self::getConnection('users'); }
    public static function tech() { return self::getConnection('tech'); }

    private static function initTechSchema(PDO $conn) {
        // Logs Table (Good and Bad logs distinguished by status)
        $conn->exec("CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tech_id TEXT NOT NULL,
            status TEXT NOT NULL, -- 'Good' or 'Bad'
            qty INTEGER DEFAULT 1,
            make TEXT,
            model TEXT,
            series TEXT,
            cpu TEXT,
            gpu TEXT,
            ram TEXT,
            storage TEXT,
            battery TEXT,
            bios_state TEXT,
            os TEXT,
            notes TEXT,
            ready_for_warehouse INTEGER DEFAULT 0,
            edited INTEGER DEFAULT 0,
            delete_requested INTEGER DEFAULT 0,
            status_change_requested TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Migration: add columns if older DB
        $cols = $conn->query("PRAGMA table_info(logs)")->fetchAll(PDO::FETCH_ASSOC);
        $col_names = array_column($cols, 'name');

        if (!in_array('edited', $col_names)) {
            $conn->exec("ALTER TABLE logs ADD COLUMN edited INTEGER DEFAULT 0");
        }
        if (!in_array('delete_requested', $col_names)) {
            $conn->exec("ALTER TABLE logs ADD COLUMN delete_requested INTEGER DEFAULT 0");
        }
        if (!in_array('status_change_requested', $col_names)) {
            $conn->exec("ALTER TABLE logs ADD COLUMN status_change_requested TEXT DEFAULT ''");
        }
        if (!in_array('os', $col_names)) {
            $conn->exec("ALTER TABLE logs ADD COLUMN os TEXT");
        }

        // daily_status_changes table to track Good-to-Bad limit of 5 per day per tech
        $conn->exec("CREATE TABLE IF NOT EXISTS daily_status_changes (
            tech_id TEXT NOT NULL,
            change_date DATE DEFAULT (date('now', 'localtime')),
            change_count INTEGER DEFAULT 0,
            PRIMARY KEY (tech_id, change_date)
        )");

        // Parts Inventory Table
        $conn->exec("CREATE TABLE IF NOT EXISTS parts_inventory (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            part_name TEXT NOT NULL,
            category TEXT,
            quantity INTEGER DEFAULT 0,
            low_stock_threshold INTEGER DEFAULT 5,
            notes TEXT,
            last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Seed default parts if table is empty
        $stmt = $conn->query("SELECT COUNT(*) FROM parts_inventory");
        if ($stmt->fetchColumn() == 0) {
            $default_parts = [
                ['8GB DDR4 RAM', 'Memory', 20, 5],
                ['16GB DDR4 RAM', 'Memory', 20, 5],
                ['256GB SSD', 'Storage', 15, 5],
                ['512GB SSD', 'Storage', 10, 3],
                ['Thermal Paste', 'Consumable', 5, 2]
            ];
            $stmt_ins = $conn->prepare("INSERT INTO parts_inventory (part_name, category, quantity, low_stock_threshold) VALUES (?, ?, ?, ?)");
            foreach ($default_parts as $part) {
                $stmt_ins->execute($part);
            }
        }
    }
}
?>
