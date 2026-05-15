<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../core/Security.php';
require_once __DIR__ . '/Audit.php';
Security::init();

/**
 * Access Control Layer
 * Checks if a session is authenticated. If not, redirect to login page.
 */
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: core/login.php");
    exit();
}
?>
