<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Technician Access Control Layer
 * Checks if a session is authenticated and has the Technician role.
 */
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: core/login.php");
    exit();
}

$user_role = $_SESSION['role'] ?? '';
$has_access = false;

// Admins and Technicians have access. 
// Using strpos to support multiple roles like 'Operator,Technician'
if ($user_role === 'Admin' || strpos($user_role, 'Technician') !== false) {
    $has_access = true;
}

if (!$has_access) {
    die("Access Denied: You do not have Technician privileges. Please contact an Administrator.");
}
?>
