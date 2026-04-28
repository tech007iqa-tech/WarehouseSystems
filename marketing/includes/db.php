<?php
/**
 * Database Connection Handler for Marketing Module
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/schema_guard.php';

function get_marketing_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("sqlite:" . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            marketing_schema_guard($pdo);
        } catch (PDOException $e) {
            die("Marketing DB connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

function get_labels_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            if (!file_exists(LABELS_DB_PATH)) {
                return null; // Labels DB not found
            }
            $pdo = new PDO("sqlite:" . LABELS_DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Labels DB connection failed: " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

// Global Audit Logger helper (aligning with project standards)
function log_marketing_audit($pdo, $entity_type, $entity_id, $action, $summary = '', $old_value = '', $new_value = '') {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (entity_type, entity_id, action, summary, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$entity_type, $entity_id, $action, $summary, $old_value, $new_value]);
}
?>
