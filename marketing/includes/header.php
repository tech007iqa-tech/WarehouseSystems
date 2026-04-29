<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Management Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="main-nav">
        <div class="nav-container">
            <a href="index.php" class="brand"><?php echo APP_NAME; ?></a>
            <ul class="nav-links">
                <li><a href="?page=dashboard">Dashboard</a></li>
                <li><a href="?page=leads">Leads</a></li>
                <li><a href="?page=model_templates">Templates</a></li>
                <li><a href="?page=ad_generator">Ad Generator</a></li>
                <li><a href="?page=campaigns">Campaigns</a></li>
                <li><a href="?page=photo_bucket">Photo Bucket</a></li>
                <li><a href="?page=reports">Reports</a></li>
                <li><a href="?page=docs">Docs</a></li>
            </ul>
        </div>
    </nav>
