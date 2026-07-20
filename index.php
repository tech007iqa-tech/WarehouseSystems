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
    <link rel="icon" type="image/png" href="./orders/assets/icon/smart-home-sensor-wifi-black-outline-25276_1024.png">
</head>

<body>

    <div class="background-blob"></div>

    <main class="portal-main">
        <div class="module-grid">
            <!-- TECH MODULE -->
            <a href="tech/index.php" class="module-card">
                <div class="icon-box">🔧</div>
                <h2>Technician Dashboard</h2>
                <p>Hardware testing, computer logs, and parts inventory management.</p>
                <div class="badge badge-labels">Module Active</div>
            </a>

            <!-- ORDERS MODULE -->
            <a href="orders/index.php" class="module-card">
                <div class="icon-box">📊</div>
                <h2>Order Manager</h2>
                <p>Comprehensive CRM, batch fulfillment, and customer registry with advanced warehouse location
                    tracking.
                </p>
                <div class="badge badge-orders">Module Active</div>
            </a>

            <!-- MARKETING MODULE -->
            <a href="marketing/index.php" class="module-card">
                <div class="icon-box">📣</div>
                <h2>Marketing Hub</h2>
                <p>Lead generation, campaign tracking, and outreach automation for B2B expansion.</p>
                <div class="badge badge-marketing">Module Initialized</div>
            </a>


            <a href="https://docs.google.com/spreadsheets/d/13X8PYZFg4NdXMYhveHBj4_sqNkGzpo2wmSgZ8aLcz3M/edit?usp=sharing"
                class="module-card">
                <div class="icon-box">🔗</div>
                <h2>Links</h2>
                <p>Sheets file</p>
                <div class="badge badge-marketing">Active</div>
            </a>
        </div>

        <header class="portal-header">
            <h1>Warehouse Systems</h1>
            <p class="tagline">Intelligent inventory management &amp; rapid label logistics.</p>
            <p class="description">
                Welcome to your all-in-one workspace hub! Tailored specifically for the used laptop market, this portal
                is here to keep operations smooth, fast, and users synchronized. [Operators, Front Desk, Technicians and
                Admins]
            </p>
            <p class="description">
                Whether you're operating the <strong>Tech Center</strong> for precise hardware diagnostics, test yield
                auditing, and live parts inventory tracking; leveraging the <strong>Orders Module</strong> for
                AI-powered intake digitization, physical warehouse logistics, and real-time CRM synchronization; or
                driving growth in the <strong>Marketing Hub</strong> via automated lead generation, campaign tracking,
                and performance analytics—this integrated ecosystem unifies every aspect of our workflow.
            </p>
        </header>
    </main>

    <footer class="footer-note">
        <a href="https://iqametal.com" style="color: white; text-decoration: none;" target="_blank">IQA Metal inc</a>.
        Inventory System &copy; <?php echo date('Y'); ?> | Powered by AI-Optimized Structural Surgery by: <a
            href="https://github.com/omar27522" style="color: white; text-decoration: none;">Tenes</a>
    </footer>

    <!-- Global Notifications Engine -->
    <script src="assets/js/notifications.js?v=<?= filemtime('assets/js/notifications.js') ?>"></script>
</body>

</html>