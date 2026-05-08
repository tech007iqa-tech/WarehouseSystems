<?php
/**
 * IQA Database Manager
 * Centralized singleton-style class for managing SQLite PDO connections.
 */

class Database {
    private static $instances = [];
    private static $db_dir = __DIR__ . '/../assets/db';

    /**
     * Get a PDO connection to a specific database file.
     * 
     * @param string $db_name The name of the database (e.g., 'customers', 'orders', 'warehouse', 'users')
     * @return PDO
     */
    public static function getConnection($db_name) {
        if (!isset(self::$instances[$db_name])) {
            $db_path = self::$db_dir . '/' . $db_name . '.db';
            
            // Ensure directory exists
            if (!is_dir(self::$db_dir)) {
                mkdir(self::$db_dir, 0777, true);
            }

            try {
                $conn = new PDO("sqlite:" . $db_path);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // --- Concurrency Optimizations ---
                // Enable Write-Ahead Logging for better multi-user performance
                $conn->exec("PRAGMA journal_mode = WAL;");
                // Set a 5-second timeout to wait for locks to clear before failing
                $conn->exec("PRAGMA busy_timeout = 5000;");
                // Synchronous NORMAL is safer and faster with WAL
                $conn->exec("PRAGMA synchronous = NORMAL;");
                
                // Enable Foreign Keys for SQLite
                $conn->exec("PRAGMA foreign_keys = ON;");
                
                self::$instances[$db_name] = $conn;
            } catch (PDOException $e) {
                die("Database Connection Error (" . $db_name . "): " . $e->getMessage());
            }
        }
        return self::$instances[$db_name];
    }

    // Convenience methods
    public static function customers() { return self::getConnection('customers'); }
    public static function orders() { return self::getConnection('orders'); }
    public static function warehouse() { return self::getConnection('warehouse'); }
    public static function users() { return self::getConnection('users'); }
    public static function calendar() { return self::getConnection('calendar'); }

    /**
     * Attaches another database to the current connection.
     * Useful for cross-database joins.
     * 
     * @param PDO $conn The primary connection
     * @param string $db_to_attach The name of the DB to attach (e.g., 'customers')
     * @param string $alias The alias to use for the attached DB (e.g., 'cust')
     */
    public static function attach(PDO $conn, $db_to_attach, $alias) {
        $db_path = self::$db_dir . '/' . $db_to_attach . '.db';
        $conn->exec("ATTACH DATABASE '{$db_path}' AS {$alias}");
    }
}
?>
