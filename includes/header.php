<?php
// includes/header.php
// This snippet forms the top half of the HTML document and persistent Sidebar menu.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IQA Metal Inventory</title>

    <!-- Global CSS Variables & Layout Rules -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Optional: A nice system font hook if desired later. Using system-ui fallbacks in CSS for now -->
</head>
<body>

<div class="app-container">

    <!--
       SIDEBAR NAVIGATION
       Persists across all views. Active state can be toggled via JS or PHP logic.
    -->
    <aside class="sidebar">
        <h2 style="margin-top: 10px;">IQA METAL</h2>

        <nav>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link" id="nav-dashboard">📊 Dashboard</a></li>
                <br>

                <!-- LABELS / INVENTORY -->
                <li style="color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase;">Warehouse</li>
                <li><a href="labels.php" class="nav-link" id="nav-labels">📦 Inventory Tracker</a></li>
                <li><a href="new_label.php" class="nav-link" id="nav-new-label">🏷️ Print Hardware Label</a></li>
                <br>

                <!-- ORDERS / PURCHASING -->
                <li style="color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase;">Sales & Forms</li>
                <li><a href="orders.php" class="nav-link" id="nav-orders">🧾 Purchase Orders</a></li>
                <li><a href="new_order.php" class="nav-link" id="nav-new-order">🛒 Create B2B Form</a></li>
                <br>

                <!-- CRM -->
                <li style="color: var(--text-secondary); font-size: 0.8rem; text-transform: uppercase;">Rolodex</li>
                <li><a href="rolodex.php" class="nav-link" id="nav-rolodex">📇 Customers & Leads</a></li>
                <li><a href="new_customer.php" class="nav-link" id="nav-new-customer">➕ Add Contact</a></li>
            </ul>
        </nav>

        <!-- Bottom Settings / Logout push down -->
        <div style="margin-top: auto; padding-top: 20px; border-top: 1px solid var(--border-color);">
            <a href="settings.php" class="nav-link" id="nav-settings" style="font-size: 0.9rem;">⚙️ System Settings</a>
        </div>
    </aside>

    <!--
       MAIN CONTENT CONTAINER
       This opens the flex container that individual pages will pour their HTML into.
       Closed by includes/footer.php
    -->
    <main class="main-content">
