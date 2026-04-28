<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Management Portal</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="main-nav">
        <div class="nav-container">
            <a href="<?php echo BASE_URL; ?>" class="brand"><?php echo APP_NAME; ?></a>
            <ul class="nav-links">
                <li><a href="?page=dashboard">Dashboard</a></li>
                <li><a href="?page=leads">Leads</a></li>
                <li><a href="?page=campaigns">Campaigns</a></li>
                <li><a href="?page=docs">Docs</a></li>
            </ul>
        </div>
    </nav>
