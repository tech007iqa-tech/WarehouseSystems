<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IQA Warehouse Systems | Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0a0a0c;
            --card-bg: rgba(255, 255, 255, 0.03);
            --accent-green: #8cc63f;
            --accent-blue: #00a8ff;
            --text-main: #f0f0f2;
            --text-dim: #a0a0a5;
            --glass-border: rgba(255, 255, 255, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            height: -webkit-fill-available;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: calc(40px + env(safe-area-inset-top)) 20px calc(40px + env(safe-area-inset-bottom));
            background: radial-gradient(circle at 50% 50%, #1a1a20 0%, #0a0a0c 100%);
            -webkit-tap-highlight-color: transparent;
            -webkit-font-smoothing: antialiased;
            -webkit-text-size-adjust: 100%;
        }

        .background-blob {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(140, 198, 63, 0.1) 0%, transparent 70%);
            z-index: -1;
            filter: blur(50px);
            animation: move 20s infinite alternate;
        }

        @keyframes move {
            from { transform: translate(-50%, -50%); }
            to { transform: translate(50%, 50%); }
        }

        .portal-header {
            text-align: center;
            margin-bottom: 50px;
            animation: fadeInDown 0.8s ease-out;
            width: 100%;
            max-width: 600px;
        }

        .portal-header h1 {
            font-size: 3.5rem;
            font-weight: 800;
            letter-spacing: -1px;
            margin-bottom: 10px;
            background: linear-gradient(to bottom, #fff, #888);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .portal-header p {
            color: var(--text-dim);
            font-size: 1.2rem;
            font-weight: 300;
        }

        .module-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            width: 100%;
            max-width: 900px;
            padding: 20px;
            animation: fadeInUp 0.8s ease-out;
        }

        .module-card {
            background: var(--card-bg);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 40px;
            text-decoration: none;
            color: inherit;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .module-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.05), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }

        .module-card:hover::before {
            transform: translateX(100%);
        }

        .module-card:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            background: rgba(255, 255, 255, 0.05);
        }

        .icon-box {
            font-size: 4rem;
            margin-bottom: 25px;
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.3));
        }

        .module-card h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: #fff;
        }

        .module-card p {
            color: var(--text-dim);
            font-size: 1rem;
            line-height: 1.6;
        }

        .badge {
            margin-top: 25px;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .badge-labels { background: rgba(140, 198, 63, 0.1); color: var(--accent-green); border: 1px solid rgba(140, 198, 63, 0.2); }
        .badge-orders { background: rgba(0, 168, 255, 0.1); color: var(--accent-blue); border: 1px solid rgba(0, 168, 255, 0.2); }

        .footer-note {
            margin-top: 40px;
            color: var(--text-dim);
            font-size: 0.85rem;
            font-weight: 300;
            opacity: 0.5;
            text-align: center;
        }

        @media (min-height: 800px) {
            .footer-note {
                position: fixed;
                bottom: calc(30px + env(safe-area-inset-bottom));
            }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .module-grid { 
                grid-template-columns: 1fr; 
                gap: 20px;
            }
            .portal-header h1 { font-size: 2.5rem; }
            .portal-header p { font-size: 1rem; }
            .module-card { padding: 30px 20px; }
            .icon-box { font-size: 3rem; margin-bottom: 15px; }
            body { justify-content: flex-start; }
        }
    </style>
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
    </main>

    <footer class="footer-note">
        IQA Metal Inventory System &copy; 2026 | Powered by AI-Optimized Structural Surgery
    </footer>

</body>
</html>
