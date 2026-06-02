<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IQA Warehouse Systems | Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <!-- Global Component Styles -->
    <link rel="stylesheet" href="assets/css/components.css?v=<?= filemtime('assets/css/components.css') ?>">

    <!-- Primary Stylesheet -->
    <link rel="stylesheet" href="assets/css/portal.css?v=<?= filemtime('assets/css/portal.css') ?>">

</head>
<body>

    <div class="background-blob"></div>

    <header class="portal-header">
        <h1>Warehouse Systems </h1><h2>By</h2><h3>IQA Metal</h3>
        <p>Intelligent inventory management & rapid label logistics.</p>
    </header>

    <main class="module-grid">
        <!-- LABELS MODULE -->
        <a href="labels/index.php" class="module-card">
            <div class="icon-box">🏷️</div>
            <h2>Inventory Labels</h2>
            <p>Rapid hardware intake terminal with ODT thermal label generation and technical sheet management.</p>
            <div class="badge badge-labels">Module Active</div>
        </a>

        <!-- ORDERS MODULE -->
        <a href="orders/index.php" class="module-card">
            <div class="icon-box">📊</div>
            <h2>Order Manager</h2>
            <p>Comprehensive CRM, batch fulfillment, and customer registry with advanced warehouse location tracking.</p>
            <div class="badge badge-orders">Module Active</div>
        </a>

        <!-- MARKETING MODULE -->
        <a href="marketing/index.php" class="module-card">
            <div class="icon-box">📣</div>
            <h2>Marketing Hub</h2>
            <p>Lead generation, campaign tracking, and outreach automation for B2B expansion.</p>
            <div class="badge badge-marketing">Module Initialized</div>
        </a>
    </main>

    <footer class="footer-note">
        IQA Metal Inventory System &copy; 2026 | Powered by AI-Optimized Structural Surgery
    </footer>

    <!-- Global Notifications Engine -->
    <script src="assets/js/notifications.js?v=<?= filemtime('assets/js/notifications.js') ?>"></script>
</body>
</html>
