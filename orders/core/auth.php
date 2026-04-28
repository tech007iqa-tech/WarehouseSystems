<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Access Control Layer
 * Checks if a session is authenticated. If not, redirect to login page.
 */
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: core/login.php");
    exit();
}
?>
