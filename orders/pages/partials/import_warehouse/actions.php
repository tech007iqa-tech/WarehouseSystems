<?php
/**
 * Warehouse Import Controllers & Actions Partial
 * Handles AJAX inline edits, CSV upload validation, bulk database insertion, and cancel requests.
 */

// Phase 0: Handle AJAX cell updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update_import_cell') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $rowIndex = isset($input['row_index']) ? (int)$input['row_index'] : -1;
    $field = $input['field'] ?? '';
    $val = $input['val'] ?? '';

    if ($rowIndex >= 0 && isset($_SESSION['import_rows'][$rowIndex])) {
        if (in_array($field, ['date', 'qty', 'location'])) {
            if ($field === 'qty') {
                $_SESSION['import_rows'][$rowIndex]['qty'] = $val;
            } else {
                $_SESSION['import_rows'][$rowIndex][$field] = $val;
            }
        } else {
            $_SESSION['import_rows'][$rowIndex]['parsed'][$field] = $val;
        }

        $row = $_SESSION['import_rows'][$rowIndex];
        $rowErrors = [];
        if (empty(trim($row['item']))) {
            $rowErrors[] = "Item is empty";
        }
        if (empty(trim($row['location']))) {
            $rowErrors[] = "Location is empty";
        }
        $qtyVal = filter_var($row['qty'], FILTER_VALIDATE_INT);
        if ($qtyVal === false || $qtyVal <= 0) {
            $rowErrors[] = "QTY must be a positive integer";
        }
        if (empty(trim($row['date']))) {
            $rowErrors[] = "Date is empty";
        }

        $_SESSION['import_rows'][$rowIndex]['errors'] = $rowErrors;
        $_SESSION['import_rows'][$rowIndex]['status'] = empty($rowErrors) ? 'Accept' : 'Reject';

        $total = count($_SESSION['import_rows']);
        $accepted = 0;
        $rejected = 0;
        foreach ($_SESSION['import_rows'] as $r) {
            if ($r['status'] === 'Accept') $accepted++;
            else $rejected++;
        }

        echo json_encode([
            'success' => true,
            'status' => $_SESSION['import_rows'][$rowIndex]['status'],
            'errors' => $rowErrors,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'total' => $total
        ]);
        exit();
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid row index or session expired']);
        exit();
    }
}

$current_user = $_SESSION['username'] ?? 'Admin';
$message = '';
$error = '';
$preview_mode = false;
$rows = [];
$acceptedCount = 0;
$rejectedCount = 0;
$zone_locations_map = [];
$working_zones = [];
$suggested_zone = '';

// Phase 1: Handle File Upload & Validation Preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['inventory_csv'])) {
    $file = $_FILES['inventory_csv'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');

        if ($handle !== false) {
            $header = fgetcsv($handle);
            if ($header) {
                // Map headers (case-insensitive)
                $mapping = [];
                foreach ($header as $index => $col) {
                    $mapping[trim(strtolower($col))] = $index;
                }

                // Required headers Date| QTY| Item| Serial| location | notes
                $required = ['date', 'qty', 'item', 'serial', 'location', 'notes'];
                $missing = [];
                foreach ($required as $req) {
                    if (!isset($mapping[$req])) {
                        $missing[] = ucfirst($req);
                    }
                }

                if (!empty($missing)) {
                    $error = "Missing required columns in CSV: " . implode(', ', $missing) . ". Header must contain: Date, QTY, Item, Serial, location, notes.";
                } else {
                    $preview_mode = true;
                    while (($data = fgetcsv($handle)) !== false) {
                        if (count($data) < count($required)) continue;

                        $rawDate = $data[$mapping['date']] ?? '';
                        $rawQty = $data[$mapping['qty']] ?? '';
                        $rawItem = $data[$mapping['item']] ?? '';
                        $rawSerial = $data[$mapping['serial']] ?? '';
                        $rawLoc = $data[$mapping['location']] ?? '';
                        $rawNotes = $data[$mapping['notes']] ?? '';

                        $rowErrors = [];
                        if (empty(trim($rawItem))) {
                            $rowErrors[] = "Item is empty";
                        }
                        if (empty(trim($rawLoc))) {
                            $rowErrors[] = "Location is empty";
                        }
                        $qtyVal = filter_var($rawQty, FILTER_VALIDATE_INT);
                        if ($qtyVal === false || $qtyVal <= 0) {
                            $rowErrors[] = "QTY must be a positive integer";
                        }
                        if (empty(trim($rawDate))) {
                            $rowErrors[] = "Date is empty";
                        }

                        $parsed = parseItemString($rawItem, $rawNotes, $rawSerial);

                        $finalNotes = trim($rawNotes);
                        if (!empty(trim($rawSerial))) {
                            $finalNotes = "SN: " . trim($rawSerial) . ($finalNotes ? " - " . $finalNotes : "");
                        }

                        $status = empty($rowErrors) ? 'Accept' : 'Reject';
                        if ($status === 'Accept') {
                            $acceptedCount++;
                        } else {
                            $rejectedCount++;
                        }

                        $rows[] = [
                            'status' => $status,
                            'errors' => $rowErrors,
                            'date' => $rawDate,
                            'qty' => $qtyVal !== false ? $qtyVal : $rawQty,
                            'item' => $rawItem,
                            'serial' => $rawSerial,
                            'location' => $rawLoc,
                            'notes' => $finalNotes,
                            'parsed' => $parsed
                        ];
                    }
                    $_SESSION['import_rows'] = $rows;
                }
            } else {
                $error = "The uploaded file is empty.";
            }
            fclose($handle);
        }
    } else {
        $error = "File upload error code: " . $file['error'];
    }
}

// Phase 2: Confirm and Save to Database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_import') {
    if (!empty($_SESSION['import_rows'])) {
        $override_zone = null;
        if (!empty($_POST['override_zone_select'])) {
            if ($_POST['override_zone_select'] === '__NEW_ZONE__' && !empty($_POST['override_zone_custom'])) {
                $override_zone = trim($_POST['override_zone_custom']);
            } else if ($_POST['override_zone_select'] !== '__NEW_ZONE__') {
                $override_zone = trim($_POST['override_zone_select']);
            }
        }

        $override_loc = null;
        if (!empty($_POST['override_location_select'])) {
            if ($_POST['override_location_select'] === '__NEW_LOC__' && !empty($_POST['override_location_custom'])) {
                $override_loc = trim(strtoupper($_POST['override_location_custom']));
            } else if ($_POST['override_location_select'] !== '__NEW_LOC__') {
                $override_loc = trim($_POST['override_location_select']);
            }
        } else if (!empty($_POST['override_location_custom'])) {
            $override_loc = trim(strtoupper($_POST['override_location_custom']));
        }

        $conn_wh->beginTransaction();
        try {
            $count = 0;
            foreach ($_SESSION['import_rows'] as $row) {
                if ($row['status'] === 'Accept') {
                    $loc = ($override_loc !== null) ? $override_loc : $row['location'];
                    getOrCreateLocation($conn_wh, $loc, $override_zone);

                    $brand = $row['parsed']['brand'];
                    $model = $row['parsed']['model'];
                    $sector = 'Laptops';
                    $qty = (int)$row['qty'];
                    $price = (float)$row['parsed']['price'];

                    $specs = [
                        'series' => $row['parsed']['series'] ?? '',
                        'cpu' => $row['parsed']['cpu'],
                        'gen' => $row['parsed']['gen'],
                        'ram' => $row['parsed']['ram'],
                        'storage' => $row['parsed']['storage'],
                        'battery' => $row['parsed']['battery'],
                        'condition' => $row['parsed']['condition'],
                        'notes' => $row['notes']
                    ];
                    $specs_json = json_encode($specs);

                    $stmt = $conn_wh->prepare("INSERT INTO inventory (user_owner, sector, location_code, brand, model, specs_json, quantity, price, last_updated_by)
                                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$current_user, $sector, $loc, $brand, $model, $specs_json, $qty, $price, $current_user]);
                    $count++;
                }
            }
            $conn_wh->commit();
            $message = "Successfully imported $count inventory items into the warehouse. New zones/locations were registered automatically.";
            unset($_SESSION['import_rows']);
        } catch (Exception $e) {
            $conn_wh->rollBack();
            $error = "Import failed: " . $e->getMessage();
        }
    } else {
        $error = "No valid data to import.";
    }
}

// Phase 3: Cancel Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_import') {
    unset($_SESSION['import_rows']);
    header("Location: index.php?view=import_warehouse");
    exit();
}

// Pre-fetch working zones and locations map for preview mode
$display_rows = $_SESSION['import_rows'] ?? $rows;
$total = count($display_rows);
$accepted = 0;
$rejected = 0;
foreach ($display_rows as $r) {
    if ($r['status'] === 'Accept') $accepted++;
    else $rejected++;
}

if ($preview_mode || !empty($_SESSION['import_rows'])) {
    try {
        $stmt_zones = $conn_wh->query("SELECT name FROM working_zones ORDER BY name ASC");
        $working_zones = $stmt_zones->fetchAll(PDO::FETCH_COLUMN);

        $stmt_locs = $conn_wh->query("SELECT location_code, working_zone_name FROM locations ORDER BY location_code ASC");
        while ($row_loc = $stmt_locs->fetch(PDO::FETCH_ASSOC)) {
            $z = $row_loc['working_zone_name'] ?: 'General';
            $zone_locations_map[$z][] = $row_loc['location_code'];
        }

        $sample_location = '';
        foreach ($display_rows as $row) {
            if ($row['status'] === 'Accept' && !empty($row['location'])) {
                $sample_location = trim($row['location']);
                break;
            }
        }
        if ($sample_location !== '') {
            $stmt_suggest = $conn_wh->prepare("SELECT working_zone_name FROM locations WHERE location_code = ?");
            $stmt_suggest->execute([$sample_location]);
            $suggested_zone = $stmt_suggest->fetchColumn();

            if (!$suggested_zone) {
                if (preg_match('/^([a-zA-Z]+)/u', $sample_location, $matches)) {
                    $prefix = strtoupper($matches[1]);
                    foreach ($working_zones as $wz) {
                        if (strcasecmp($wz, $prefix) === 0 || strcasecmp($wz, 'Zone ' . $prefix) === 0) {
                            $suggested_zone = $wz;
                            break;
                        }
                    }
                    if (!$suggested_zone) {
                        $suggested_zone = 'Zone ' . $prefix;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Fallback silently
    }
}
