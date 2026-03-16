<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label APP</title>
</head>
<style>
    :root {
    --text-main: #333;
    --text-secondary: #666;
    --link-color: #007bff;
    --spacing: 20px;
    --font-main: Arial, sans-serif;
}

body {
    font-family: var(--font-main);
    margin: var(--spacing);
    line-height: 1.6;
    color: var(--text-secondary);
}

h1 {
    color: var(--text-main);
    margin-bottom: 0.5em;
}

p {
    margin-bottom: 1em;
}

a {
    color: var(--link-color);
    text-decoration: none;
    transition: text-decoration 0.2s ease;
}

a:hover {
    text-decoration: underline;
}

ul {
    list-style: disc;
    padding-left: 1.5em;
}

li {
    margin-bottom: 0.5em;
}



</style>
<body><ul>
    <li>This app needs to record laptop data for individual machines.</li>
    <li>This app will print labels, by using windows printer software.</li>
    <li>The app needs to know the size of the label.</li>
    <li>The app needs to store the content of the each label and keep away duplicate information.</li>
    <li>The app needs to search for created labels.</li>
    </ul>
    <h2>What technology should be used to build this app?</h2>
    <p>PHP, MySQL, HTML, CSS, JavaScript</p>
    <p>The app should be able to run on a local network.</p>
    <p>Each label indicates a physical laptop.</p>
    <p>The app should be able to search for created labels. and keep track of the laptops.
        like their location in the warehouse.
        And when they are sold and no longer in the warehouse.
    </p>
    <p>The app should be able add laptops to a Purchase form.</p>
    <p>The app should print Purchase forms.</p>
    <p>The app should keep track of orders from purchase forms data.</p>
    <p>
    <a href="print.php">Print Labels</a>

</body>
</html>