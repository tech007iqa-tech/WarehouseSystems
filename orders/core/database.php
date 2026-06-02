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
                $conn->exec("PRAGMA journal_mode = WAL;");
                $conn->exec("PRAGMA busy_timeout = 5000;");
                $conn->exec("PRAGMA synchronous = NORMAL;");
                $conn->exec("PRAGMA foreign_keys = ON;");

                // --- Self-Healing Schema Integration ---
                require_once __DIR__ . '/Schema.php';
                Schema::ensure($conn, $db_name);

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

    /**
     * Executes a query on a primary database while automatically attaching
     * multiple supporting databases for cross-DB joins.
     *
     * @param string $primary_db The name of the primary DB (e.g., 'orders')
     * @param array $attachments Key-value pairs of [alias => db_name]
     * @param string $sql The SQL query to execute
     * @param array $params Optional positional parameters
     * @return PDOStatement
     */
    /**
     * Executes a query on a primary database while automatically attaching
     * multiple supporting databases for cross-DB joins.
     */
    public static function queryIntegrated($primary_db, $attachments, $sql, $params = []) {
        $conn = self::getConnection($primary_db);
        foreach ($attachments as $alias => $name) {
            try {
                self::attach($conn, $name, $alias);
            } catch (Exception $e) { }
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Schema Caching: Checks if a table/schema has been verified in this session.
     */
    public static function isSchemaVerified($db, $table) {
        if (session_status() === PHP_SESSION_NONE) return false;
        return isset($_SESSION['verified_schemas'][$db][$table]);
    }

    /**
     * Schema Caching: Marks a table/schema as verified.
     */
    public static function markSchemaVerified($db, $table) {
        if (session_status() === PHP_SESSION_NONE) return;
        $_SESSION['verified_schemas'][$db][$table] = true;
    }
}
?>
