<?php
/**
 * Global Configuration for Marketing App
 */

// Database Configuration
define('DB_PATH', __DIR__ . '/data/marketing.db');
define('LABELS_DB_PATH', __DIR__ . '/../labels/db/labels.sqlite');

// App Paths
define('BASE_URL', '/app/marketing');
define('INCLUDES_PATH', __DIR__ . '/includes');
define('MODULES_PATH', __DIR__ . '/modules');

// App Settings
define('APP_NAME', 'Marketing Hub');
define('VERSION', '1.0.0');

// Error Reporting (Development)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
