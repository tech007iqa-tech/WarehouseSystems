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

// CPU spec line — e.g. "i7-11850H (8 Cores · 2.50GHz)" or just "i5-8250U"
$cpu_parts = array_filter([$cpu_specs, $cpu_cores ? $cpu_cores . ' Cores' : '', $cpu_speed]);
$cpu_line  = $cpu_specs;
$cpu_detail = trim(implode(' · ', array_filter([$cpu_cores ? $cpu_cores . ' Cores' : '', $cpu_speed])));
if ($cpu_detail) $cpu_line = $cpu_specs . ' (' . $cpu_detail . ')';
if (!$cpu_line)  $cpu_line = '—';

// Page title for branding label
$label_title = trim("$brand $model $series");
$gen_display = $cpu_gen ?: 'Gen Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Label — <?= $label_title ?></title>
    <style>
        /* ── Reset ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ── Screen: Preview wrapper ── */
        body {
            font-family: 'Arial', 'Liberation Sans', sans-serif;
            background: #e8eaed;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 30px 20px;
            gap: 20px;
        }

        /* ── Top toolbar (screen only) ── */
        .toolbar {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #fff;
            border-radius: 10px;
            padding: 12px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,.12);
            width: 100%;
            max-width: 620px;
        }
        .toolbar h2 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #202124;
            flex: 1;
        }
        .toolbar .label-id {
            font-size: 0.78rem;
            color: #5f6368;
            background: #f1f3f4;
            padding: 3px 8px;
            border-radius: 20px;
        }
        .btn-print {
            background: #8cc63f;
            color: #fff;
            border: none;
            border-radius: 7px;
            padding: 9px 20px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s;
        }
        .btn-print:hover { background: #78b030; }
        .btn-close {
            background: transparent;
            color: #5f6368;
            border: 1px solid #dadce0;
            border-radius: 7px;
            padding: 8px 14px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .btn-close:hover { background: #f1f3f4; }

        /* ── Label card wrapper (screen) ── */
        .preview-wrap {
            display: flex;
            flex-direction: column;
            gap: 18px;
            align-items: center;
        }
        .label-card-outer {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,.18);
            padding: 12px 14px 10px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        .label-tag {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #8cc63f;
            font-weight: 800;
        }

        /* ── The actual label — exact 2" × 1" ── */
        .label {
            width: 2in;
            height: 1in;
            overflow: hidden;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 0.05in;
            background: #fff;
            position: relative;
        }

        /* Label A — Branding */
        .label-a .brand-name {
            font-size: 16pt;
            font-weight: 700;
            line-height: 1.1;
            color: #111;
            word-wrap: break-word;
        }

        /* Label B — Specs */
        .label-b .spec-line {
            font-size: 7.5pt;
            line-height: 1.25;
            color: #111;
            width: 100%;
            text-align: center;
            word-wrap: break-word;
        }
        .label-b .spec-line.head {
            font-size: 8.5pt;
            font-weight: 700;
            margin-bottom: 2pt;
        }

        /* ── Print styles ── */
        @media print {
            /* Hide everything visible on screen that isn't a label */
            body {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
                display: block !important;
            }
            .toolbar, .label-tag {
                display: none !important;
            }
            .label-card-outer {
                display: block !important;
                background: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .preview-wrap {
                display: block !important;
                gap: 0 !important;
            }

            /* Each label gets its own physical page */
            @page {
                size: 2in 1in landscape;
                margin: 0;
            }

            .label {
                width: 2in;
                height: 1in;
                border: none !important;
                border-radius: 0 !important;
                padding: 0.05in;
                page-break-after: always;
                page-break-inside: avoid;
                box-shadow: none !important;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                text-align: center;
                overflow: hidden;
            }

            .label:last-child {
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>

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
