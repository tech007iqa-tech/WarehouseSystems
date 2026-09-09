<?php
/**
 * Global Configuration for Marketing App
 */

// Session & Security Initialization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_PATH', __DIR__ . '/data/marketing.db');
define('MASTER_CRM_DB_PATH', __DIR__ . '/../db/customers.db');
define('LABELS_DB_PATH', __DIR__ . '/../labels/db/labels.sqlite');
define('WAREHOUSE_DB_PATH', __DIR__ . '/../db/warehouse.db');

// App Paths
define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/marketing'), '/\\'));
define('INCLUDES_PATH', __DIR__ . '/includes');
define('MODULES_PATH', __DIR__ . '/modules');

// App Settings
define('APP_NAME', 'Marketing Hub');
define('VERSION', '1.1.0');

// Global Core UI & Security Helpers
require_once __DIR__ . '/../core/UI.php';
require_once __DIR__ . '/../core/Security.php';
Security::init();

/**
 * Universal HTML escape helper for XSS prevention
 */
if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Role-Based Access Control
$user_role = $_SESSION['role'] ?? 'Marketing';
$allowed_roles = ['Admin', 'Manager', 'Sales', 'Marketing'];
$has_access = in_array($user_role, $allowed_roles) || strpos($user_role, 'Admin') !== false || strpos($user_role, 'Manager') !== false;

if (!$has_access) {
    http_response_code(403);
    die("<!DOCTYPE html><html><body style='font-family: sans-serif; text-align: center; padding: 4rem; background: #0f172a; color: #f8fafc;'><h2>403 - Access Denied</h2><p>Your role (<strong>" . h($user_role) . "</strong>) does not have access to the Marketing Hub.</p><a href='../index.php' style='color: #38bdf8;'>Return to Portal</a></body></html>");
}

// Error Reporting (Development)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
