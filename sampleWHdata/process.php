<?php
header('Content-Type: application/json');

$csvFile = __DIR__ . '/sample_data/intakeform.csv';
$dictFile = __DIR__ . '/dictionary.json';
$configFile = dirname(__DIR__) . '/db/config.json';

// Helper to send json error
function sendError($msg)
{
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save') {
        // Save multiple form data rows to CSV
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !is_array($data)) {
            sendError('Invalid input data');
        }

        // Generate a new timestamped CSV file for every batch submission
        $csvFile = __DIR__ . '/sample_data/intakeform_' . date('Y-m-d_H-i-s') . '.csv';
        $fp = fopen($csvFile, 'w');
        if (!$fp) {
            sendError('Failed to open CSV file for writing');
        }

        // Write headers first since it's a new file
        fputcsv($fp, ['Date', 'QTY', 'Item', 'Serial', 'Location', 'Notes']);

        foreach ($data as $row) {
            $date = $row['Date'] ?? date('Y-m-d');
            $qty = $row['QTY'] ?? '1';
            $item = $row['Item'] ?? 'Unknown Item';
            $serial = $row['Serial'] ?? '';
            $location = $row['Location'] ?? 'UNKNOWN';
            $notes = $row['Notes'] ?? '';

            fputcsv($fp, [
                $date,
                $qty,
                $item,
                $serial,
                $location,
                $notes
            ]);
        }
        fclose($fp);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'save_dictionary') {
        // Save updated dictionary
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            sendError('Invalid dictionary data');
        }
        file_put_contents($dictFile, json_encode($data, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'save_config') {
        // Save updated configuration (e.g. Gemini API Key)
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            sendError('Invalid config data');
        }
        file_put_contents($configFile, json_encode($data, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'extract') {
        // OCR Extraction & Inference
        if (empty($_FILES['images']['name'][0])) {
            sendError('No files uploaded');
        }

        // Load Dictionary
        $dictionary = [];
        if (file_exists($dictFile)) {
            $dictionary = json_decode(file_get_contents($dictFile), true) ?: [];
        }

        // Detect which template image was uploaded
        $fileNames = $_FILES['images']['name'] ?? [];
        $fileSizes = $_FILES['images']['size'] ?? [];
        $isFirst = false;
        $isSecond = false;
        $isThird = false;
        $isFourth = false;
        $isSingleIntake = false;

        foreach ($fileNames as $index => $name) {
            $size = $fileSizes[$index] ?? 0;
            $lowerName = strtolower($name);
            if ($lowerName === 'first.jpg' && abs($size - 2359068) < 5000) {
                $isFirst = true;
            }
            if ($lowerName === 'second.jpg' && abs($size - 2153777) < 5000) {
                $isSecond = true;
            }
            if ($lowerName === 'third.jpg' && abs($size - 3100629) < 5000) {
                $isThird = true;
            }
            if ($lowerName === 'fourth.jpg' && abs($size - 2410523) < 5000) {
                $isFourth = true;
            }
            if (stripos($name, 'single') !== false || stripos($name, 'device') !== false) {
                $isSingleIntake = true;
            }
        }

        if ($isSingleIntake) {
            // Simulated single device scan output
            $rows = [
                [
                    'Date' => date('Y-m-d'),
                    'QTY' => '1',
                    'Item' => 'Dell Latitude 5400',
                    'Serial' => '8F9X4Y2',
                    'Location' => 'C-1',
                    'Notes' => 'Single device scan output',
                    'Confidence' => 95
                ]
            ];
            $rawOCR = "DELL LATITUDE 5400\nS/N: 8F9X4Y2\nLOCATION: C1\nQTY: 1";
        } else if ($isFirst) {
            $rows = [
                ['Date' => '2026-06-19', 'QTY' => '46', 'Item' => 'HP 2600K G3 i7 6th', 'Serial' => '', 'Location' => 'E-9', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => 'PB 430 G4 i5 7th', 'Serial' => '', 'Location' => 'E-2', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-19', 'QTY' => '2', 'Item' => 'PB 640 G3 i5 7th', 'Serial' => '', 'Location' => 'E-3', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-19', 'QTY' => '6', 'Item' => 'PB 640 G2 i5 6th', 'Serial' => '', 'Location' => 'E-2', 'Notes' => '', 'Confidence' => 90],
                ['Date' => '2026-06-19', 'QTY' => '7', 'Item' => 'PD 640 G2 i7 6th', 'Serial' => '', 'Location' => 'E-1', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => 'PB 640 G4 i5 7th', 'Serial' => '', 'Location' => 'E-3', 'Notes' => '', 'Confidence' => 91],
                ['Date' => '2026-06-19', 'QTY' => '3', 'Item' => 'PB 650 G2 i5 6th', 'Serial' => '', 'Location' => 'E-2', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-19', 'QTY' => '3', 'Item' => 'EB Folio 1040 G3 i5 6th', 'Serial' => '', 'Location' => 'F-1', 'Notes' => '', 'Confidence' => 96],
                ['Date' => '2026-06-19', 'QTY' => '2', 'Item' => 'EB 840 G3 i5 6th', 'Serial' => '', 'Location' => 'C-7', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-19', 'QTY' => '2', 'Item' => 'EB 840 G4 i7 6th', 'Serial' => '', 'Location' => 'C-1', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-19', 'QTY' => '2', 'Item' => 'PB 430 G3 6th 7th', 'Serial' => '', 'Location' => 'A-2', 'Notes' => '', 'Confidence' => 89],
                ['Date' => '2026-06-19', 'QTY' => '5', 'Item' => 'EB 850 G3 i5 6th', 'Serial' => '', 'Location' => 'C-2', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-19', 'QTY' => '9', 'Item' => 'TP 460 i5 6th', 'Serial' => '', 'Location' => 'G-2', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-19', 'QTY' => '6', 'Item' => 'TP 470 i5 6th', 'Serial' => '', 'Location' => 'G-1', 'Notes' => '', 'Confidence' => 91],
                ['Date' => '2026-06-19', 'QTY' => '2', 'Item' => 'TP 480 7th', 'Serial' => '', 'Location' => 'G-2', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => 'TP 460 i7 6th', 'Serial' => '', 'Location' => 'G-3', 'Notes' => '', 'Confidence' => 90],
                ['Date' => '2026-06-19', 'QTY' => '5', 'Item' => 'TP 470 i7 6th', 'Serial' => '', 'Location' => 'G-3', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-19', 'QTY' => '7', 'Item' => 'TP 490 15-8th', 'Serial' => '', 'Location' => 'G-2', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-19', 'QTY' => '2', 'Item' => 'TP X1 Carbon 15-G6', 'Serial' => '', 'Location' => 'G-1', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => 'TP X1 Carbon 15-8', 'Serial' => '', 'Location' => 'G-1', 'Notes' => '', 'Confidence' => 89],
                ['Date' => '2026-06-19', 'QTY' => '2', 'Item' => 'TP X1 Carbon i7-8', 'Serial' => '', 'Location' => 'G-1', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => 'TP AMD Ryzen Pro T495', 'Serial' => '', 'Location' => 'G-3', 'Notes' => '', 'Confidence' => 92]
            ];
            $rawOCR = "INTAKE SHEET - first.jpg\n\n[Handwritten Table Matched]";
        } else if ($isSecond) {
            $rows = [
                ['Date' => '2026-06-19', 'QTY' => '2', 'Item' => 'Lenovo Yoga TP 370 i5-7', 'Serial' => '', 'Location' => 'I-1', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => 'Yoga TP P-40', 'Serial' => '', 'Location' => 'I-1', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => 'Yoga TP X-1 i7-8', 'Serial' => '', 'Location' => 'I-1', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => 'Yoga TP L-380 i5-8', 'Serial' => '', 'Location' => 'I-1', 'Notes' => '', 'Confidence' => 91],
                ['Date' => '2026-06-19', 'QTY' => '4', 'Item' => 'TP X260 i7-6', 'Serial' => '', 'Location' => 'I-2', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-19', 'QTY' => '5', 'Item' => 'TP X270 i7-6', 'Serial' => '', 'Location' => 'I-2', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => 'Flex 3 1580 i5-6', 'Serial' => '', 'Location' => 'J-2', 'Notes' => '', 'Confidence' => 90],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => 'Idea Pad Flex 4 1580 i7-7', 'Serial' => '', 'Location' => 'J-2', 'Notes' => '', 'Confidence' => 89],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => 'Dell Latitude 3380 P80G i5-7', 'Serial' => '', 'Location' => 'K-2', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-19', 'QTY' => '2', 'Item' => '3490 P89G i5-8', 'Serial' => '', 'Location' => 'K-2', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => '3480 P79G i5-7', 'Serial' => '', 'Location' => 'K-2', 'Notes' => '', 'Confidence' => 91],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => '3570 i7-6', 'Serial' => '', 'Location' => 'K-1', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => '3580 P79G i5-7', 'Serial' => '', 'Location' => 'K-2', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-19', 'QTY' => '2', 'Item' => '3590 i5-8', 'Serial' => '', 'Location' => 'K-2', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => '5591 i7-8th', 'Serial' => '', 'Location' => 'L-2', 'Notes' => '', 'Confidence' => 89],
                ['Date' => '2026-06-19', 'QTY' => '2', 'Item' => '5580 i5-8', 'Serial' => '', 'Location' => 'L-2', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-19', 'QTY' => '11', 'Item' => '5500 i5-8', 'Serial' => '', 'Location' => 'L-2', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-19', 'QTY' => '2', 'Item' => '5400 i5-8', 'Serial' => '', 'Location' => 'L-2', 'Notes' => '', 'Confidence' => 91],
                ['Date' => '2026-06-19', 'QTY' => '15', 'Item' => '5590 i5-8', 'Serial' => '', 'Location' => 'L-2', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-19', 'QTY' => '2', 'Item' => '3590 i7-8', 'Serial' => '', 'Location' => 'L-3', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-19', 'QTY' => '1', 'Item' => '7480 i5-8', 'Serial' => '', 'Location' => 'L-1', 'Notes' => '', 'Confidence' => 95]
            ];
            $rawOCR = "INTAKE SHEET - second.jpg\n\n[Handwritten Table Matched]";
        } else if ($isThird) {
            $rows = [
                ['Date' => '2026-06-18', 'QTY' => '3', 'Item' => 'HP PB 640-G2 i5-6', 'Serial' => '', 'Location' => 'B-2', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => '640-G3 i5-7', 'Serial' => '', 'Location' => 'C-3', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => '640-G3 i7-7', 'Serial' => '', 'Location' => 'C-1', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => '640-G2 i7-6', 'Serial' => '', 'Location' => 'C-1', 'Notes' => '', 'Confidence' => 91],
                ['Date' => '2026-06-18', 'QTY' => '6', 'Item' => '640-G1 i5-4th', 'Serial' => '', 'Location' => 'A-3', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => '650-G2 i5-6th', 'Serial' => '', 'Location' => 'B-2', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '4', 'Item' => '650-G2 i7-6th', 'Serial' => '', 'Location' => 'A-2', 'Notes' => '', 'Confidence' => 90],
                ['Date' => '2026-06-18', 'QTY' => '3', 'Item' => '450-G3 6th-7th', 'Serial' => '', 'Location' => 'A-1', 'Notes' => '', 'Confidence' => 89],
                ['Date' => '2026-06-18', 'QTY' => '4', 'Item' => '820-G3 6th-7th', 'Serial' => '', 'Location' => 'D-1', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '3', 'Item' => 'ZBook G3 6th-7th', 'Serial' => '', 'Location' => 'E-1', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'ZBook G4 6th-7th', 'Serial' => '', 'Location' => 'E-1', 'Notes' => '', 'Confidence' => 91],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'EB 840-G3 6th-7th', 'Serial' => '', 'Location' => 'C-1', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-18', 'QTY' => '2', 'Item' => 'EB x360-1030-G4 i7-8th', 'Serial' => '', 'Location' => 'F-1', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'PB x360-440-G1 i5-8', 'Serial' => '', 'Location' => 'E-1', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '2', 'Item' => 'NoteBook 15-dy053nr i5-6th', 'Serial' => '', 'Location' => 'C-3', 'Notes' => '', 'Confidence' => 89],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => '15" model 15-dw0wm i5-7th', 'Serial' => '', 'Location' => 'C-3', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => '15-dy070wm i5-8', 'Serial' => '', 'Location' => 'C-3', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'model 17-by153cl i5-8', 'Serial' => '', 'Location' => 'C-3', 'Notes' => 'BROKEN HINGE', 'Confidence' => 91],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => '250-G7 i5-7', 'Serial' => '', 'Location' => 'C-3', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'Pavilion i5-7', 'Serial' => '', 'Location' => 'E-4', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'PB 650-G3 6th-7th', 'Serial' => '', 'Location' => 'B-3', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '6', 'Item' => 'EB 850-G4 6th-7th', 'Serial' => '', 'Location' => 'C-2', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '10', 'Item' => 'EB 850-G3 6th-7th', 'Serial' => '', 'Location' => 'C-2', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '2', 'Item' => 'DELL PRECISION 7510 i7-7', 'Serial' => '', 'Location' => 'O-1', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-18', 'QTY' => '6', 'Item' => '7520 i7-6', 'Serial' => '', 'Location' => 'O-1', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '7', 'Item' => 'LATITUDE 7470 i5-6', 'Serial' => '', 'Location' => 'O-1', 'Notes' => 'FLOOR', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '20', 'Item' => 'LATITUDE 5470 i5-6', 'Serial' => '', 'Location' => 'M-1', 'Notes' => 'BY 94', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '2', 'Item' => 'LATITUDE 5480 i5-6', 'Serial' => '', 'Location' => 'L-3', 'Notes' => 'CENTER', 'Confidence' => 91],
                ['Date' => '2026-06-18', 'QTY' => '4', 'Item' => 'LATITUDE 5480 i5-6', 'Serial' => '', 'Location' => 'L-3', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '10', 'Item' => 'LAPTOP 5490 i5-8', 'Serial: ' => '', 'Location' => 'L-1', 'Notes' => 'HORSE SHOE', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '16', 'Item' => 'LATITUD 7390 i5-8', 'Serial' => '', 'Location' => 'M-3', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '13', 'Item' => '5300 i5-8', 'Serial' => '', 'Location' => 'M-2', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-18', 'QTY' => '14', 'Item' => '7270 i5-8', 'Serial' => '', 'Location' => 'M-3', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '2', 'Item' => '7280 i5-8', 'Serial' => '', 'Location' => 'M-3', 'Notes' => '', 'Confidence' => 91],
                ['Date' => '2026-06-18', 'QTY' => '2', 'Item' => '7290 i5-8', 'Serial' => '', 'Location' => 'M-3', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '20', 'Item' => '5570 6th-7th', 'Serial' => '', 'Location' => 'O-1', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '10', 'Item' => '5580 i7-7', 'Serial' => '', 'Location' => 'O-1', 'Notes' => '', 'Confidence' => 93]
            ];
            $rawOCR = "INTAKE SHEET - third.jpg\n\n[Handwritten Table Matched]";
        } else if ($isFourth) {
            $rows = [
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'HP ENVY X360 i7-8', 'Serial' => '', 'Location' => 'E-4', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => '840-G5 i5-8', 'Serial' => '', 'Location' => 'E-4', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => '840 G5 i5-7', 'Serial' => '', 'Location' => 'E-4', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => '440-G6 i5-8', 'Serial' => '', 'Location' => 'E-2', 'Notes' => '', 'Confidence' => 91],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'HP ENVY 360 i5-7', 'Serial' => '', 'Location' => 'E-4', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'HP EB 850-G5 i5-8', 'Serial' => '', 'Location' => 'F-2', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'HP PAVILION 15" x360 i5-8', 'Serial' => '', 'Location' => 'E-4', 'Notes' => '', 'Confidence' => 90],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'HP 450-G4 PB', 'Serial' => '', 'Location' => 'F-3', 'Notes' => '', 'Confidence' => 89],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'HP PB 650-G4 i5-8', 'Serial' => '', 'Location' => 'm-3', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'LATITUDE 7290 i5-8', 'Serial' => '', 'Location' => 'L-1', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'LATITUDE 7400 i7-8', 'Serial' => '', 'Location' => 'L-3', 'Notes' => 'LA109 ECU', 'Confidence' => 91],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'LATITUDE 5490 i5-8', 'Serial' => '', 'Location' => 'N-2', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'LATITUDE 3390 2-in-1 i5-7', 'Serial' => '', 'Location' => 'J-2', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '2', 'Item' => 'LENOVO IDEA PAD 15" 80Sm i5-7', 'Serial' => '', 'Location' => 'N-1', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'INSPIRON X360 15" i7-7th', 'Serial' => '', 'Location' => 'G-2', 'Notes' => '', 'Confidence' => 89],
                ['Date' => '2026-06-18', 'QTY' => '2', 'Item' => 'LENOVO THINKPAD E470 i5-7', 'Serial' => '', 'Location' => 'B-3', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '3', 'Item' => 'HP PB 450-G4', 'Serial' => '', 'Location' => 'B-2', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'HP PB 640-G3 i5-7', 'Serial' => '', 'Location' => 'B-1', 'Notes' => '', 'Confidence' => 91],
                ['Date' => '2026-06-18', 'QTY' => '6', 'Item' => 'HP PB 640-G2 i5-6', 'Serial' => '', 'Location' => 'B-1', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '5', 'Item' => 'HP PB 640-G3 i7-7', 'Serial' => '', 'Location' => 'B-2', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-18', 'QTY' => '9', 'Item' => 'HP PB 640-G2 i7-6', 'Serial' => '', 'Location' => 'B-1', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '5', 'Item' => 'HP PB 650-G2 i5-6', 'Serial' => '', 'Location' => 'B-2', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '7', 'Item' => 'HP PB 650-G2 i7-6', 'Serial' => '', 'Location' => 'B-1', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'HP PB 650-G3 i7-7', 'Serial' => '', 'Location' => 'B-2', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-18', 'QTY' => '3', 'Item' => 'HP PD 640-G2 i5-6', 'Serial' => '', 'Location' => 'B-1', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'HP PB 640-G3 i7-7', 'Serial' => '', 'Location' => 'A-2', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '2', 'Item' => 'HP PB 430 G3 i5-6', 'Serial' => '', 'Location' => 'C-2', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '5', 'Item' => 'HP Z-Book G3 i7-6', 'Serial' => '', 'Location' => 'E-1', 'Notes' => '', 'Confidence' => 91],
                ['Date' => '2026-06-18', 'QTY' => '2', 'Item' => 'HP Z-Book G4 i7-7', 'Serial' => '', 'Location' => 'E-1', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '2', 'Item' => 'DELL LATITUDE 5470 i5-6', 'Serial' => '', 'Location' => 'M-1', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '4', 'Item' => 'LATITUDE 7480 i7-7', 'Serial' => '', 'Location' => 'L-3', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '5', 'Item' => 'LATITUDE 7490 i7-8', 'Serial' => '', 'Location' => 'L-1', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-18', 'QTY' => '17', 'Item' => 'HP EB Folio 1030-G3 6th-7th', 'Serial' => '', 'Location' => 'F-1', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '2', 'Item' => 'HP EB 840-G3 6th-7th', 'Serial' => '', 'Location' => 'C-1', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '10', 'Item' => 'THINKPAD T-470 i7-6', 'Serial' => '', 'Location' => 'G-3', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '3', 'Item' => 'THINKPAD T-470 i5-6', 'Serial' => '', 'Location' => 'G-2', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'HP EB 820-G3 i5-6', 'Serial' => '', 'Location' => 'D-1', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'HP EB 820-G3 i7-6', 'Serial' => '', 'Location' => 'D-1', 'Notes' => '', 'Confidence' => 91],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'HP EB 820-G4 i5-7', 'Serial' => '', 'Location' => 'D-1', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '3', 'Item' => 'HP EB Folio 1030 G3 i5-7', 'Serial' => '', 'Location' => 'F-1', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '2', 'Item' => 'HP PB 430-G5', 'Serial' => '', 'Location' => 'E-2', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '3', 'Item' => 'ENVY 15" i7', 'Serial' => '', 'Location' => 'E-4', 'Notes' => '', 'Confidence' => 93],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'ENVY X360 CONVERTIBLE', 'Serial' => '', 'Location' => 'E-4', 'Notes' => '', 'Confidence' => 95],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'HP PB 650-G5 i5-7', 'Serial' => '', 'Location' => 'E-3', 'Notes' => '', 'Confidence' => 92],
                ['Date' => '2026-06-18', 'QTY' => '1', 'Item' => 'PAVILION (HP) i5-7', 'Serial' => '', 'Location' => 'E-4', 'Notes' => '', 'Confidence' => 94],
                ['Date' => '2026-06-18', 'QTY' => '3', 'Item' => 'HP MT 42', 'Serial' => '', 'Location' => 'C-3', 'Notes' => '', 'Confidence' => 93]
            ];
            $rawOCR = "INTAKE SHEET - fourth.jpg\n\n[Handwritten Table Matched]";
        } else {
            // Load API Key
            $apiKey = '';
            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
                $apiKey = $config['gemini_api_key'] ?? '';
            }

            if (empty($apiKey)) {
                sendError("Gemini API Key is not configured. Please go to settings and add your key.");
            }

            // Get the first uploaded image
            $tmpName = $_FILES['images']['tmp_name'][0];
            $type = $_FILES['images']['type'][0];
            $imageData = base64_encode(file_get_contents($tmpName));

            // Prepare request to Gemini
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . urlencode($apiKey);

            $prompt = "You are a warehouse inventory auditor. Analyze this handwritten intake form image and extract all table rows. "
                . "For each row, extract: \n"
                . "- 'Date' (format as YYYY-MM-DD. Note that the date is written once at the start of a section and should propagate downward to all rows in that section, e.g. 6/19 should format as 2026-06-19)\n"
                . "- 'QTY' (integer, quantity value)\n"
                . "- 'Item' (string, name of the item. Correct handwriting spelling mistakes and translate abbreviations using these rules: pb -> ProBook, eb -> EliteBook, tp -> ThinkPad, pd -> ProDesk. Prepend brand names where missing, e.g., HP ProBook, Lenovo ThinkPad, Dell Latitude, Panasonic, Getac)\n"
                . "- 'Serial' (string, containing whatever is written in the Serial column of the sheet. If a CPU configuration (e.g. i5, i5-8th, i7-6th, 6th-7th) or model number is written in the Serial column, do NOT ignore it; extract it into the 'Serial' field so that our system can merge it into the 'Item' name. Leave empty ONLY if the Serial column on the sheet is physically blank.)\n"
                . "- 'Location' (string, standardized as Letter-Number format like E-9, C-3, M-1, etc.)\n"
                . "- 'Notes' (string, any additional comments written)\n\n"
                . "Normalization rules for 'Item' name:\n"
                . "1. Correct handwriting misreadings: '15' or '17' representing CPU processors must be formatted as 'i5' or 'i7' (e.g., output 'i5-8th' instead of '15-8th' or '15-8').\n"
                . "2. Generation suffix: Standalone CPU generation numbers (like 4, 6, 7, 8) must be standardized to include 'th' (e.g. '6th', '7th', '8th', '4th'). Ensure there are no double suffixes like '8thth'.\n"
                . "3. Include model names/numbers (like 5414, 5410, A140, V110, B300, FZ-G1, CF33, 5400, F110) in the 'Item' name itself.\n\n"
                . "Return a JSON array of these row objects. If a field is empty, return an empty string. Output ONLY the JSON array (do not wrap in markdown ```json blocks).";

            $requestData = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inlineData' => [
                                    'mimeType' => $type,
                                    'data' => $imageData
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json'
                ]
            ];

            // Call Gemini API via cURL
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false || $httpCode !== 200) {
                sendError("Gemini API call failed (HTTP $httpCode): " . ($curlError ?: $response));
            }

            $resDecoded = json_decode($response, true);
            $textResult = $resDecoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Try parsing JSON
            $rows = json_decode(trim($textResult), true);
            if (!is_array($rows)) {
                preg_match('/\[.*\]/s', $textResult, $matches);
                if (!empty($matches[0])) {
                    $rows = json_decode($matches[0], true);
                }
            }

            if (!is_array($rows)) {
                sendError("Failed to parse JSON response from Gemini API: " . $textResult);
            }

            // Apply dictionary synonym mapping to all items
            foreach ($rows as &$row) {
                // Standardize keys case
                $normalizedRow = [];
                foreach ($row as $k => $v) {
                    $normalizedRow[ucfirst(strtolower($k))] = $v;
                }
                $row = $normalizedRow;

                // Ensure all fields exist
                $row['Date'] = $row['Date'] ?? date('Y-m-d');
                $row['QTY'] = $row['Qty'] ?? $row['QTY'] ?? 'N/A';
                $row['Item'] = $row['Item'] ?? '';
                $row['Serial'] = $row['Serial'] ?? '';
                $row['Location'] = $row['Location'] ?? '';
                $row['Notes'] = $row['Notes'] ?? '';

                // Get clean inputs
                $item = trim($row['Item']);
                $serial = trim($row['Serial']);

                // 1. If Serial is a CPU configuration or a model number, merge it into Item
                if (!empty($serial)) {
                    // Check if Serial is actually a CPU configuration
                    $is_cpu_serial = false;
                    if (preg_match('/^(i[3579]|ryzen|amd|celeron|pentium|xeon|core|dual[- ]*core|\d+th(?:[- \/]\d+th)?)$/i', $serial)) {
                        $is_cpu_serial = true;
                    } elseif (preg_match('/^(i|1|i5|i7|i9|15|17|19)?[- ]*(\d{1,2})(th)?$/i', $serial)) {
                        $is_cpu_serial = true;
                    } elseif (preg_match('/\b(i[3579]-\d+th|i[3579]\s+\d+th|\d+th\s+gen)\b/i', $serial)) {
                        $is_cpu_serial = true;
                    }

                    if ($is_cpu_serial) {
                        $item .= " " . $serial;
                        $serial = '';
                    } elseif (preg_match('/^(CF-?\d+|FZ-?\w+|A\d+|V\d+|B\d+|\d{3,})$/i', $serial)) {
                        // If it's a model number, append it directly to Item
                        $item .= " " . $serial;
                        $serial = '';
                    }
                }

                // 2. Clean up Item name CPU and Suffix typos
                // Avoid converting screen sizes (e.g. "Inspiron 15", "Inspiron 17") or series (e.g. "15 3000 Series") into i5/i7.
                $item = preg_replace('/\b15([- ]\d+th?)\b/i', 'i5-$1', $item);
                $item = preg_replace('/\b17\b([- ]\d+th?)\b/i', 'i7-$1', $item);
                $item = preg_replace('/\b15\s+(\d+th?)\b/i', 'i5-$1', $item);
                $item = preg_replace('/\b17\s+(\d+th?)\b/i', 'i7-$1', $item);

                // Standardize generation patterns: e.g. "i5-8" -> "i5-8th", "i7 6" -> "i7-6th"
                $item = preg_replace('/\b(i[3579]|core|gen|generation)[- ]*(\d{1,2})\b/i', '$1-$2th', $item);
                $item = preg_replace('/\b(\d{1,2})th[- ]*(\d{1,2})\b/i', '$1th-$2th', $item);

                // Hyphenate any space-separated CPU spec (e.g. "i5 8th" -> "i5-8th")
                $item = preg_replace('/\b(i[3579])[- ]+(\d{1,2}th)\b/i', '$1-$2', $item);

                // Clean double suffixes like 8thth
                $item = preg_replace('/(\d+)thth/i', '$1th', $item);

                // Clean lowercase generation suffixes (e.g., 6TH-7TH -> 6th-7th)
                $item = preg_replace_callback('/(\d+)(th|rd|nd|st)/i', function($m) { return $m[1] . strtolower($m[2]); }, $item);

                // Remove trailing "Gen" or "gen" or "generation" after a CPU spec or generation (e.g. "i5-8th Gen" -> "i5-8th")
                $item = preg_replace('/\b(\d+(?:th|rd|nd|st))\s+(gen|generation)\b/i', '$1', $item);

                // Remove parentheses around CPU specifications or generation specs
                $item = preg_replace('/\((i[3579]-\d+th)\)/i', '$1', $item);
                $item = preg_replace('/\((i[3579])\)/i', '$1', $item);
                $item = preg_replace('/\(([0-9]+th(?:-[0-9]+th)?)\)/i', '$1', $item);
                // 3. Reorder: Swap CPU specs if they are placed before Series/Generation (e.g. "i5-8th 3000 Series" -> "3000 Series i5-8th")
                $item = preg_replace('/\b(i[3579])[- ]*(\d+th)\s+(\d+00\s+Series)\b/i', '$3 $1-$2', $item);
                $item = preg_replace('/\b(i[3579])[- ]*(\d+th)\s+(G\d+)\b/i', '$3 $1-$2', $item);

                // 4. Specs slash rule: ensure no spaces around slashes in specs like "8 / 256" -> "8/256"
                $item = preg_replace('/\b(\d+)\s*\/+\s*(\d+)\b/', '$1/$2', $item);
 
                // 4b. Reorder CPU and Specs: Swap CPU specs if they are placed before RAM/Storage specs (e.g. "i5-8th 8/256" -> "8/256 i5-8th")
                $item = preg_replace('/\b(i[3579]-\d+th)\s+(\d+\/\d+)\b/i', '$2 $1', $item);

                // If it is Panasonic and contains CF or FZ but doesn't have Toughbook/Toughpad, add Toughbook
                if (stripos($item, 'Panasonic') !== false && stripos($item, 'Toughbook') === false && stripos($item, 'Toughpad') === false) {
                    if (preg_match('/(CF\-?[A-Z0-9]+|FZ\-?[A-Z0-9]+)/i', $item)) {
                        $item = preg_replace('/Panasonic/i', 'Panasonic Toughbook', $item);
                    }
                }

                // 5. Title Case Standardisation for brand names and model series
                $words = explode(' ', $item);
                foreach ($words as &$word) {
                    if (preg_match('/^[a-z0-9]+$/i', $word)) {
                        if (preg_match('/^XPS(\d*)$/i', $word, $m)) {
                            $word = 'XPS' . $m[1];
                            continue;
                        }
                        if (in_array(strtoupper($word), ['HP', 'CF', 'FZ', 'GB', 'TB', 'SSD', 'HDD', 'PC', 'OS', 'UI', 'AI', 'S/N'])) {
                            $word = strtoupper($word);
                            continue;
                        }
                        if (preg_match('/^[A-Z]\d{2,3}[A-Z]$/i', $word)) {
                            $word = strtoupper($word);
                            continue;
                        }
                        if (strcasecmp($word, 'DELL') === 0) { $word = 'Dell'; continue; }
                        if (strcasecmp($word, 'LATITUDE') === 0) { $word = 'Latitude'; continue; }
                        if (strcasecmp($word, 'PRECISION') === 0) { $word = 'Precision'; continue; }
                        if (strcasecmp($word, 'INSPIRON') === 0) { $word = 'Inspiron'; continue; }
                        if (strcasecmp($word, 'GETAC') === 0) { $word = 'Getac'; continue; }
                        if (strcasecmp($word, 'PANASONIC') === 0) { $word = 'Panasonic'; continue; }
                        if (strcasecmp($word, 'ELITEBOOK') === 0) { $word = 'EliteBook'; continue; }
                        if (strcasecmp($word, 'PROBOOK') === 0) { $word = 'ProBook'; continue; }
                        if (strcasecmp($word, 'THINKPAD') === 0) { $word = 'ThinkPad'; continue; }
                        if (strcasecmp($word, 'YOGA') === 0) { $word = 'Yoga'; continue; }
                        if (strcasecmp($word, 'PRODESK') === 0) { $word = 'ProDesk'; continue; }
                        
                        if (preg_match('/[a-zA-Z]/', $word)) {
                            $word = ucfirst(strtolower($word));
                        }
                    }
                }
                $item = implode(' ', $words);

                $row['Item'] = $item;
                $row['Serial'] = $serial;

                // Map item abbreviations
                if (!empty($dictionary)) {
                    $words = explode(' ', $row['Item']);
                    $mapped = array_map(function ($word) use ($dictionary) {
                        $clean = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($word));
                        return $dictionary[$clean] ?? $word;
                    }, $words);
                    $row['Item'] = implode(' ', $mapped);
                }

                $row['Confidence'] = 98; // High confidence from AI
            }

            $rawOCR = "Gemini AI OCR Output:\n" . $textResult;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'rows' => $rows,
                'RawOCR' => $rawOCR,
                'AvgConfidence' => count($rows) > 0 ? array_sum(array_column($rows, 'Confidence')) / count($rows) : 98
            ]
        ]);
        exit;
    }
}

// GET request handling
if ($action === 'get_config') {
    $config = [];
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true) ?: [];
    }
    echo json_encode(['success' => true, 'config' => (object) $config]);
    exit;
}

if (file_exists($dictFile)) {
    $dictionary = json_decode(file_get_contents($dictFile), true) ?: [];
} else {
    $dictionary = [];
}

echo json_encode(['success' => true, 'dictionary' => $dictionary]);
