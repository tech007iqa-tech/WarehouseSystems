<?php
/**
 * print_label.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Renders a hardware label as a high-quality print page.
 * - Optimized for 2" x 1" and 4" x 2" surfaces.
 * - Single-page unified branding + specs layout.
 * ─────────────────────────────────────────────────────────────────────────────
 */

require_once 'includes/db.php';
require_once 'includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    die('<p style="font-family:sans-serif;color:red;padding:20px;">Invalid label ID.</p>');
}

$item = null;
try {
    $stmt = $pdo_labels->prepare("SELECT * FROM items WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if (!$item) {
    http_response_code(404);
    die('<p style="font-family:sans-serif;color:red;padding:20px;">Item #' . $id . ' not found.</p>');
}

// ─── Label Dimensions Setting ──────────────────────────────────────────────
$requested_size = preg_replace('/[^0-9x]/', '', $_GET['size'] ?? '2x1');
$dim = explode('x', $requested_size);
$l_width  = (isset($dim[0]) && is_numeric($dim[0])) ? $dim[0] . 'in' : '2in';
$l_height = (isset($dim[1]) && is_numeric($dim[1])) ? $dim[1] . 'in' : '1in';

// ─── Build display values ───────────────────────────────────────────────────
$brand    = htmlspecialchars($item['brand']   ?? '',           ENT_QUOTES, 'UTF-8');
$model    = htmlspecialchars($item['model']   ?? '',           ENT_QUOTES, 'UTF-8');
$series   = htmlspecialchars($item['series']  ?? '',           ENT_QUOTES, 'UTF-8');
$cpu_gen  = htmlspecialchars($item['cpu_gen'] ?? '',           ENT_QUOTES, 'UTF-8');
$cpu_specs= htmlspecialchars($item['cpu_specs'] ?? ($item['cpu_details'] ?? ''), ENT_QUOTES, 'UTF-8');
$cpu_cores= htmlspecialchars($item['cpu_cores'] ?? '',         ENT_QUOTES, 'UTF-8');
$cpu_speed= htmlspecialchars($item['cpu_speed'] ?? '',         ENT_QUOTES, 'UTF-8');
$ram      = htmlspecialchars($item['ram']     ?? 'None',       ENT_QUOTES, 'UTF-8');
$storage  = htmlspecialchars($item['storage'] ?? 'None',       ENT_QUOTES, 'UTF-8');
$battery  = (!isset($item['battery']) || $item['battery'] === null || $item['battery'] === '') ? 'N/A' : ((int)$item['battery'] === 1 ? 'YES' : 'NO');
$bios     = htmlspecialchars($item['bios_state'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
$location = htmlspecialchars($item['warehouse_location'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8');
$cond     = htmlspecialchars($item['description'] ?? 'Untested', ENT_QUOTES, 'UTF-8');
$serial   = htmlspecialchars($item['serial_number'] ?? 'N/A',   ENT_QUOTES, 'UTF-8');

// CPU spec line — matches ODF style: "4 Core <br>@ 2.50GHz"
$clean_cores = trim(str_ireplace('Cores', '', $cpu_cores));
$cpu_detail = trim(implode('<br>@ ', array_filter([$clean_cores ? $clean_cores . ' Core' : '', $cpu_speed])));
$cpu_line  = $cpu_specs . ($cpu_detail ? ' (' . $cpu_detail . ')' : '');
if (!$cpu_specs && !$cpu_detail) $cpu_line = '—';

// Page title for branding label (Now includes CPU for accurate PDF naming)
$cpu_tag = $cpu_specs ? " ($cpu_specs)" : "";
$label_title = trim("$brand $model $series" . $cpu_tag);
$gen_display = $cpu_gen ?: 'Gen Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $label_title ?></title>
    <!-- Thermal Printer Engine & UI -->
    <style>
        :root {
            /* Precise physical mapping for label surface */
            --label-width: <?= $l_width ?> !important;
            --label-height: <?= $l_height ?> !important;
            --font-brand: <?= ($l_width === '2in') ? '11pt' : '20pt' ?>;
            --font-spec: <?= ($l_width === '2in') ? '7pt' : '9.5pt' ?>;
        }

        /* ── Base Reset ── */
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif !important;
            background: #e8eaed !important;
            padding: 20px !important;
            display: flex;
            justify-content: center;
        }

        /* ── The Physical Surface ── */
        .compact-label {
            width: var(--label-width);
            height: var(--label-height);
            background: #fff;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            padding: 0.05in;
            border: 1px solid #eee;
            position: relative;
            line-height: 1.05;
        }

        /* ── Branding Section ── */
        .header-section {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 1pt;
            margin-bottom: 2pt;
            width: 100%;
        }

        .brand-name {
            font-size: var(--font-brand);
            font-weight: 900;
            text-transform: uppercase;
            display: block;
            line-height: 1;
        }

        .cpu-title {
            font-size: calc(var(--font-brand) * 0.65);
            font-weight: 700;
            color: #333;
            display: block;
            margin-top: 1pt;
        }

        /* ── Specifications Grid ── */
        .spec-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            gap: 1pt 4pt;
        }

        .spec-item {
            font-size: var(--font-spec);
            font-weight: 600;
            color: #000;
            white-space: normal; /* We let it wrap if it has to */
            word-break: break-all;
        }

        /* ── Footer / ID ── */
        .footer-section {
            margin-top: auto;
            border-top: 1px solid #ccc;
            padding-top: 1pt;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: calc(var(--font-spec) * 0.9);
            font-weight: 800;
        }

        .visual-id {
            position: absolute;
            bottom: 3pt;
            right: 3pt;
            background: rgba(0,0,0,0.05);
            color: #888;
            padding: 0 3px;
            border-radius: 2px;
            font-size: 6pt;
            font-weight: 900;
            line-height: 1;
        }

        /* ── Screen UI ── */
        .toolbar {
            position: fixed; top:0; left:0; right:0; background:#333; color:#fff;
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 20px; z-index: 1000; font-family: sans-serif;
        }
        .btn-print { background: #8cc63f; border: none; padding: 8px 15px; border-radius: 4px; color: #fff; cursor: pointer; font-weight: 700; }

        @media print {
            body { background: #fff !important; padding: 0 !important; }
            .toolbar, .btn-print { display: none !important; }
            .compact-label { border: none !important; }
            @page { size: var(--label-width) var(--label-height) landscape; margin: 0; }
        }
    </style>
</head>
<body class="label-preview-body">

    <div class="toolbar no-print">
        <h2 style="font-size:1rem;">🖨️ Label Preview — #<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></h2>
        <button class="btn-print" onclick="window.print()">🖨️ Print Label</button>
    </div>

    <!-- The Consolidated Label -->
    <div style="margin-top: 60px;">
        <div class="compact-label" id="mainLabel">

            <div class="header-section">
                <div class="brand-name fit-text"><?= $brand ?> <?= $model ?></div>
                <div class="cpu-title fit-text"><?= $series ?> <?= $cpu_specs ?></div>
            </div>

            <div class="spec-grid">
                <div class="spec-item">CPU: <?= $cpu_detail ?: '—' ?></div>
                <div class="spec-item">RAM: <?= $ram ?></div>
                <div class="spec-item">SSD: <?= $storage ?></div>
                <div class="spec-item">BATT: <?= $battery ?></div>
                <div class="spec-item">BIOS: <?= $bios ?></div>
                <div class="spec-item">LOC: <?= $location ?></div>

                <!-- Deep Spec Row if present -->
                <?php if ($item['gpu'] || $item['os_version']): ?>
                <div class="spec-item" style="grid-column: span 2; font-size: calc(var(--font-spec) * 0.9);">
                    GPU: <?= htmlspecialchars($item['gpu'] ?? 'Intg') ?> | OS: <?= htmlspecialchars($item['os_version'] ?? '—') ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="footer-section">
                <div class="footer-item">S/N: <?= $serial ?></div>
                <div class="footer-item"><?= $cond ?></div>
            </div>

            <div class="visual-id" style="position: absolute; top: 2pt; left: 2pt; font-size: 5pt; background: none; color: #aaa;"><?= $id ?></div>
        </div>
    </div>
<script>
        /**
         * Scaling Engine: Shrinks text until it fits its container.
         * Handles wrapping height as well as horizontal overflows.
         */
        function scaleLabels() {
            const elements = document.querySelectorAll('.fit-text');
            elements.forEach(el => {
                let size = parseFloat(window.getComputedStyle(el).fontSize);
                // Lower floor to 4pt for extreme data cases
                while (el.scrollHeight > (el.offsetHeight + 1) && size > 4) {
                    size -= 0.5;
                    el.style.fontSize = size + 'pt';
                }
            });
        }

        window.addEventListener('load', () => {
            scaleLabels();
            setTimeout(() => window.print(), 500);
        });

        window.addEventListener('afterprint', () => { window.close(); });
    </script>

</body>
</html>
