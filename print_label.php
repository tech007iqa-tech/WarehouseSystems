<?php
/**
 * print_label.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Renders a hardware label as a high-quality print page.
 * - No files are written to disk.
 * - Exact dimensions match the physical label template (3" × 1.74" landscape).
 * - Two pages: Label A (Branding) + Label B (Specs).
 * - Triggers window.print() automatically on load.
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
$battery  = (int)($item['battery'] ?? 0) === 1 ? 'YES' : 'NO';
$bios     = htmlspecialchars($item['bios_state'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
$location = htmlspecialchars($item['warehouse_location'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8');
$cond     = htmlspecialchars($item['description'] ?? 'Untested', ENT_QUOTES, 'UTF-8');
$serial   = htmlspecialchars($item['serial_number'] ?? 'N/A',   ENT_QUOTES, 'UTF-8');

// CPU spec line — matches ODT style: "i7-11850H (8 Cores @ 2.50GHz)"
$cpu_detail = trim(implode(' @ ', array_filter([$cpu_cores ? $cpu_cores . ' Cores' : '', $cpu_speed])));
$cpu_line  = $cpu_specs . ($cpu_detail ? ' (' . $cpu_detail . ')' : '');
if (!$cpu_specs && !$cpu_detail) $cpu_line = '—';

// Page title for branding label
$label_title = trim("$brand $model $series");
$gen_display = $cpu_gen ?: 'Gen Unknown';
?>
<style>
    :root {
        --label-width: <?= $l_width ?> !important;
        --label-height: <?= $l_height ?> !important;
    }
</style>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Label — <?= $label_title ?></title>
    <!-- Thermal Printer Engine & UI -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="label-preview-body">

    <!-- ── Screen-only toolbar ── -->
    <div class="toolbar" id="printToolbar">
        <h2>🖨️ Label Preview — <?= $label_title ?></h2>
        <span class="label-id">#<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></span>
        <button class="btn-close" onclick="window.close()">✕ Close</button>
        <button class="btn-print" onclick="window.print()">🖨️ Print</button>
    </div>

    <!-- ── Label preview cards ── -->
    <div class="preview-wrap" id="labelWrap">

        <!-- Label A: Branding -->
        <div class="label-card-outer" id="cardA">
            <span class="label-tag">Label A — Branding</span>
            <div class="label label-a">
                <div class="brand-name"><?= $label_title ?></div>
                <div style="position:absolute; bottom:3pt; right:3pt; font-size:6pt; font-weight:700; color:#888;">#<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></div>
            </div>
        </div>

        <!-- Label B: Technical Specs -->
        <div class="label-card-outer" id="cardB">
            <span class="label-tag">Label B — Specifications</span>
            <div class="label label-b">
                <div class="spec-line head">Technical Specifications (<?= $gen_display ?>)</div>
                <div class="spec-line">CPU: <?= $cpu_line ?></div>
                <div class="spec-line">RAM: <?= $ram ?> &nbsp;|&nbsp; Storage: <?= $storage ?></div>
                <div class="spec-line">Battery: <?= $battery ?> &nbsp;|&nbsp; BIOS: <?= $bios ?></div>
                <div class="spec-line">Loc: <?= $location ?> &nbsp;|&nbsp; Cond: <?= $cond ?></div>
                <div class="spec-line" style="font-weight:700; border-top:1px solid rgba(0,0,0,0.05); margin-top:1pt; padding-top:1pt;">S/N: <?= $serial ?></div>
                <div style="position:absolute; bottom:2pt; right:3pt; font-size:5pt; font-weight:700; color:#ccc;">#<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></div>
            </div>
        </div>

    </div>

    <script>
        // Auto-trigger print dialog when page loads
        window.addEventListener('load', () => {
            // Small delay so the browser has time to render the labels fully
            setTimeout(() => window.print(), 400);
        });

        // After printing/cancelling, close the tab automatically
        window.addEventListener('afterprint', () => {
            window.close();
        });
    </script>

</body>
</html>
