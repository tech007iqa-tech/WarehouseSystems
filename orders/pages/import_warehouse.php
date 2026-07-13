<?php
/**
 * Bulk Warehouse Import
 * Handles CSV uploads for the main Warehouse Inventory system with strict verification.
 */
include 'core/warehouse_db.php';
include 'core/auth.php';

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

$current_user = $_SESSION['username'];
$message = '';
$error = '';
$preview_mode = false;
$rows = [];
$acceptedCount = 0;
$rejectedCount = 0;
$zone_locations_map = [];
$working_zones = [];
$suggested_zone = '';

function mapCpuToMatrixGen($cpu, $gen) {
    $cpu = strtolower($cpu);
    $gen = strtolower($gen);

    if (strpos($gen, '4') !== false || strpos($gen, '5') !== false) {
        return '4th-5th';
    }
    if (strpos($gen, '6') !== false || strpos($gen, '7') !== false) {
        return '6th-7th';
    }

    $gen_num = 0;
    if (preg_match('/(\d+)/', $gen, $m)) {
        $gen_num = (int)$m[1];
    }

    if ($gen_num >= 8 && $gen_num <= 12) {
        $tier = 'i5';
        if (strpos($cpu, 'i7') !== false || strpos($cpu, 'i9') !== false) {
            $tier = 'i7';
        }
        return $tier . '-' . $gen_num . 'th';
    }

    foreach ([8, 9, 10, 11, 12] as $g) {
        if (strpos($cpu, $g . 'th') !== false || strpos($cpu, '-' . $g) !== false) {
            $tier = 'i5';
            if (strpos($cpu, 'i7') !== false || strpos($cpu, 'i9') !== false) {
                $tier = 'i7';
            }
            return $tier . '-' . $g . 'th';
        }
    }

    return '';
}

function parseItemString($itemStr, $notesStr = '', $serialStr = '') {
    $brands = ['Dell', 'HP', 'Lenovo', 'Apple', 'Microsoft', 'Samsung', 'Asus', 'Acer', 'MSI', 'Sony', 'Nintendo', 'Panasonic', 'Getac'];
    $brand = 'Unknown';
    $model = 'Unknown';
    $series = '';
    $cpu = '';
    $gen = '';
    $ram = '';
    $storage = '';
    $battery = '';
    $condition = 'Untested';
    $price = 0.00;

    // Detect Brand
    foreach ($brands as $b) {
        if (stripos($itemStr, $b) !== false) {
            $brand = $b;
            break;
        }
    }

    // Infer Brand if Unknown based on popular model keywords
    if ($brand === 'Unknown') {
        $brandInferenceMap = [
            'Dell' => ["Alienware","Inspiron","Latitude","Precision","Vostro","XPS"],
            'HP' => ["EliteBook","Envy","Omen","Pavilion","ProBook","Spectre","Victus","ZBook","Z-Book"],
            'Lenovo' => ["IdeaPad","LOQ","Legion","ThinkBook","ThinkPad","Yoga","Flex"]
        ];
        foreach ($brandInferenceMap as $inferredBrand => $modelKeywords) {
            foreach ($modelKeywords as $keyword) {
                if (stripos($itemStr, $keyword) !== false) {
                    $brand = $inferredBrand;
                    break 2;
                }
            }
        }
    }

    // Detect Model based on Brand
    $modelsMap = [
        'Dell' => ["Alienware","G-Series","Inspiron","Latitude","Precision","Vostro","XPS"],
        'HP' => ["ChromeBook","Dragonfly","EliteBook","Envy","Notebook","Omen","Pavilion","ProBook","Spectre","Victus","ZBook","mt"],
        'Lenovo' => ["ChromeBook","IdeaPad","LOQ","Legion","ThinkBook","ThinkPad","Yoga","Flex"],
        'Apple' => ["MacBook","MacBook Air","MacBook Pro"],
        'Microsoft' => ["Surface Book","Surface Go","Surface Laptop","Surface Laptop Go","Surface Laptop Studio","Surface Pro"],
        'Samsung' => ["ChromeBook","Galaxy Book","Galaxy Book Flex","Galaxy Book Ion","Notebook"],
        'Asus' => ["ChromeBook","ExpertBook","VivoBook","ZenBook"],
        'Acer' => ["Aspire","ChromeBook","Nitro","Predator","Spin","Swift","TravelMate"],
        'MSI' => ["Modern","Prestige","Stealth","Creator"],
        'Panasonic' => ["Toughbook","Toughpad"],
        'Getac' => ["Rugged","S410","A140","V110","B300","F110"]
    ];

    if ($brand !== 'Unknown' && isset($modelsMap[$brand])) {
        foreach ($modelsMap[$brand] as $m) {
            if (stripos($itemStr, $m) !== false) {
                $model = $m;
                break;
            }
        }
    }

    // Enhance Apple model detection to capture A-number model (e.g. A1466)
    if ($brand === 'Apple') {
        if (preg_match('/\b(A\d{4})\b/i', $itemStr, $matches)) {
            $model = strtoupper($matches[1]);
        }
    }

    // Default generic models if Brand is known but Model is still Unknown
    if ($brand !== 'Unknown' && $model === 'Unknown') {
        if ($brand === 'HP') {
            $model = 'Notebook';
        } elseif ($brand === 'Lenovo') {
            $model = 'ThinkPad';
        } elseif ($brand === 'Dell') {
            $model = 'Latitude';
        } elseif ($brand === 'Panasonic') {
            $model = 'Toughbook';
        } elseif ($brand === 'Getac') {
            $model = 'Rugged';
        }
    }

    // Detect RAM & Storage combined (e.g. 4/32, 16/256, 8-128)
    $combined_specs_matched = false;
    if (preg_match('/\b(\d+)\s*[\/-]\s*(\d+)\b/', $itemStr, $matches)) {
        $rVal = (int)$matches[1];
        $sVal = (int)$matches[2];
        if ($rVal <= 64 && $sVal >= 8) {
            $ram = $rVal . 'GB';
            if ($sVal <= 4) {
                $storage = $sVal . 'TB';
            } else {
                $storage = $sVal . 'GB';
            }
            $combined_specs_matched = true;
        }
    }

    // Detect RAM (if not matched combined)
    if (!$combined_specs_matched) {
        if (preg_match('/(\d+)\s*(?:GB|gb)\s*(?:RAM|ram)?/i', $itemStr, $matches)) {
            $val = (int)$matches[1];
            if (in_array($val, [2, 4, 8, 12, 16, 24, 32, 64, 128])) {
                $ram = $val . 'GB';
            }
        }
    }

    // Detect Storage (if not matched combined)
    if (!$combined_specs_matched) {
        if (preg_match('/(\d+)\s*(?:GB|gb|TB|tb)\s*(?:SSD|HDD|NVMe|Storage)?/i', $itemStr, $matches)) {
            $valStr = strtoupper($matches[0]);
            if (stripos($valStr, 'TB') !== false) {
                $storage = $matches[1] . 'TB';
            } else {
                $val = (int)$matches[1];
                if ($val >= 120 && $val != (int)str_replace('GB', '', $ram)) {
                    $storage = $val . 'GB';
                }
            }
        }
        if (empty($storage) && preg_match('/(\d+)\s*(?:GB|gb|TB|tb)\s*(?:SSD|HDD|NVMe)/i', $notesStr, $matches)) {
             $storage = strtoupper($matches[1] . (stripos($matches[0], 'TB') !== false ? 'TB' : 'GB'));
        }
    }

    // Detect CPU
    if (preg_match('/(i3|i5|i7|i9|Ryzen(?:\s*Pro)?(?:\s*[3579])?|Celeron|Pentium|Xeon|Core\s*2\s*Duo|M1|M2|M3)/i', $itemStr, $matches)) {
        $cpu = trim($matches[1]);
    }
    if (empty($cpu)) {
        if (stripos($itemStr, 'AMD') !== false || stripos($notesStr, 'AMD') !== false) {
            $cpu = 'AMD';
        }
    }

    // Detect Gen
    if (preg_match('/(\d+(?:th|rd|nd|st)(?:\s*[\/-]\s*\d+(?:th|rd|nd|st))?)\s*(?:Gen)?/i', $itemStr, $matches)) {
        $gen = $matches[1];
    }

    // Default CPU to i5 if missing but Gen is present
    if (empty($cpu) && !empty($gen)) {
        $cpu = 'i5';
    }

    // If CPU contains Ryzen, set Gen to AMD
    if (!empty($cpu) && stripos($cpu, 'Ryzen') !== false) {
        $gen = 'AMD';
    }

    // Detect Series
    if (stripos($itemStr, 'zbook') !== false || stripos($itemStr, 'z-book') !== false) {
        if (preg_match('/\b((?:Firefly|Fury|Studio|Power|Create)\s*(?:x360)?\s*(?:\d{2})?[a-z]?\s*[-\/]?\s*G\d{1,2})\b/i', $itemStr, $matches)) {
            $series = ucwords(strtolower($matches[1]));
            $series = preg_replace('/\bg(\d+)\b/i', 'G$1', $series);
        } elseif (preg_match('/\b(Fury)\b/i', $itemStr, $matches)) {
            $series = 'Fury';
        } elseif (preg_match('/\b(\d{2,3}[a-z]?\s*[-\/]?\s*G\d{1,2})\b/i', $itemStr, $matches)) {
            $series = strtoupper($matches[1]);
        } elseif (preg_match('/\b(\d{2,3}[a-z]?)\b/i', $itemStr, $matches)) {
            $series = strtoupper($matches[1]);
        }
    }

    if (empty($series)) {
        if (preg_match('/X1\s+Carbon/i', $itemStr)) {
            $series = 'X1 Carbon';
        } elseif (preg_match('/X1\s+Yoga/i', $itemStr)) {
            $series = 'X1 Yoga';
        } elseif (preg_match('/X1/i', $itemStr)) {
            $series = 'X1';
        } elseif (preg_match('/\b(CF\-?[A-Z0-9]+|FZ\-?[A-Z0-9]+|S410|A140|V110|B300|F110)\b/i', $itemStr, $matches)) {
            $series = strtoupper($matches[1]);
            if (strcasecmp($series, 'CF54') === 0) {
                $series = 'CF-54';
            }
        } elseif (preg_match('/\b(x360\-?\d{3,4}(?:\-?G\d{1,2})?)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('#\b(\d{3,4}\s*[-/]?\s*G\d{1,2})\b#i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b((?:13|14|15|17)\-[a-z0-9]+)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b(G\d{1,2})\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b(P\d{2,3}[A-Z])\b/i', $itemStr, $matches)) {
            $series = strtoupper($matches[1]);
        } elseif (preg_match('/\b(P\-?\d{2,3}[s-z]?)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b(L\-?\d{2,3}[s-z]?)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b(T\-?\d{2,3}[s-z]?)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b(X\-?\d{1,3}[s-z]?)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } elseif (preg_match('/\b(13|14|15|17|15s|14s)\b/i', $itemStr, $matches)) {
            $series = $matches[1];
        } else {
            $tokens = preg_split('/[\s,\-\/]+/', $itemStr);
            foreach ($tokens as $token) {
                $token = trim($token);
                if (empty($token)) continue;
                if (preg_match('/^[A-Z]?\d{3,4}[s-z]?$/i', $token)) {
                    $series = $token;
                }
            }
        }
    }

    // Detect Battery
    if (preg_match('/battery\s*:\s*(Yes|No|Unknown)/i', $notesStr, $matches)) {
        $battery = $matches[1];
    } elseif (stripos($itemStr, 'battery') !== false || stripos($notesStr, 'battery') !== false) {
        if (stripos($itemStr, 'no battery') !== false || stripos($notesStr, 'no battery') !== false || stripos($notesStr, 'missing battery') !== false) {
            $battery = 'No';
        } else {
            $battery = 'Yes';
        }
    }
    if (empty($battery)) {
        $battery = 'Unknown';
    }

    // Detect Condition
    if (preg_match('/(untested)/i', $itemStr . ' ' . $notesStr, $matches)) {
        $condition = 'Untested';
    } elseif (preg_match('/(for parts)/i', $itemStr . ' ' . $notesStr, $matches)) {
        $condition = 'For parts';
    } elseif (preg_match('/([ABC]\s*Grade|Grade\s*[ABC])/i', $itemStr . ' ' . $notesStr, $matches)) {
        $condition = trim($matches[1]);
    }

    // Price
    if (preg_match('/\$(\d+(?:\.\d{2})?)/', $itemStr . ' ' . $notesStr, $matches)) {
        $price = (float)$matches[1];
    }

    if (empty($ram)) {
        $ram = '-';
    }
    if (empty($storage)) {
        $storage = '-';
    }

    // Lookup Price in pricing matrix if not in string
    if ($price == 0.00) {
        global $conn_wh;
        if ($conn_wh) {
            $category = 'Regular';
            $normalized_item = strtolower($itemStr . ' ' . $notesStr);

            if (stripos($brand, 'ram') !== false || stripos($brand, 'memory') !== false || stripos($normalized_item, 'ddr3') !== false || stripos($normalized_item, 'ddr4') !== false || stripos($normalized_item, 'ram') !== false || stripos($normalized_item, 'sodimm') !== false || stripos($normalized_item, 'dimm') !== false) {
                $category = 'RAM';
            } elseif (stripos($brand, 'ssd') !== false || stripos($brand, 'hdd') !== false || stripos($brand, 'storage') !== false || stripos($normalized_item, 'ssd') !== false || stripos($normalized_item, 'hdd') !== false || stripos($normalized_item, 'hard drive') !== false || stripos($normalized_item, 'nvme') !== false) {
                $category = 'Storage';
            } elseif (stripos($normalized_item, 'chromebook') !== false || stripos($brand, 'chromebook') !== false || stripos($model, 'chromebook') !== false) {
                $category = 'Chromebook';
            } elseif (stripos($brand, 'Apple') !== false || stripos($normalized_item, 'macbook') !== false) {
                $category = 'Apple';
            } elseif (stripos($brand, 'Microsoft') !== false || stripos($normalized_item, 'surface') !== false) {
                $category = 'Microsoft';
            } elseif (stripos($brand, 'MSI') !== false || stripos($normalized_item, 'alienware') !== false || stripos($normalized_item, 'gaming') !== false || stripos($normalized_item, 'legion') !== false || stripos($normalized_item, 'omen') !== false || stripos($normalized_item, 'predator') !== false) {
                $category = 'Gaming';
            } elseif (stripos($normalized_item, 'rugged') !== false || stripos($normalized_item, 'toughbook') !== false || stripos($normalized_item, 'durabook') !== false || stripos($normalized_item, 'getac') !== false) {
                $category = 'Rugged';
            }

            $grade_key = 'Parts'; // default fallback
            $normalized_cond = strtolower($condition);
            if (strpos($normalized_cond, 'untested') !== false) {
                $grade_key = 'Untested';
            } elseif (strpos($normalized_cond, 'c grade') !== false || strpos($normalized_cond, 'grade c') !== false) {
                $grade_key = 'C Grade';
            } elseif (strpos($normalized_cond, 'parts') !== false || strpos($normalized_cond, 'part') !== false) {
                $grade_key = 'Parts';
            } elseif (strpos($normalized_cond, 'a grade') !== false || strpos($normalized_cond, 'grade a') !== false) {
                $grade_key = 'Untested';
            } elseif (strpos($normalized_cond, 'b grade') !== false || strpos($normalized_cond, 'grade b') !== false) {
                $grade_key = 'Parts';
            }

            try {
                $query_gen = 'Default';
                if ($category === 'Regular') {
                    $query_gen = mapCpuToMatrixGen($cpu, $gen);
                } elseif ($category === 'RAM') {
                    $ram_gigs = '';
                    if (preg_match('/\b(2|4|8|16|32)\s*(?:GB|gb)\b/i', $normalized_item, $matches)) {
                        $ram_gigs = $matches[1] . 'GB';
                    }
                    $ram_type = 'DDR4'; // default fallback
                    if (stripos($normalized_item, 'ddr3') !== false) {
                        $ram_type = 'DDR3';
                    }
                    if (!empty($ram_gigs)) {
                        $query_gen = $ram_gigs . ' ' . $ram_type;
                    }
                    if ($grade_key === 'Parts') {
                        $grade_key = 'Tested';
                    }
                } elseif ($category === 'Microsoft') {
                    $query_gen = 'Surface Pro 8 (Default)';

                    if (stripos($normalized_item, '1769') !== false) {
                        if (stripos($normalized_item, '7th') !== false || stripos($normalized_item, 'laptop 1') !== false) {
                            $query_gen = 'Surface Laptop 1 (1769)';
                        } else {
                            $query_gen = 'Surface Laptop 2 (1769)';
                        }
                    } elseif (stripos($normalized_item, '1782') !== false) {
                        $query_gen = 'Surface Laptop 2 (1782)';
                    } elseif (stripos($normalized_item, '1867') !== false || stripos($normalized_item, '1868') !== false) {
                        $query_gen = 'Surface Laptop 3 (1867/1868)';
                    } elseif (stripos($normalized_item, '1950') !== false || stripos($normalized_item, '1951') !== false) {
                        if (stripos($normalized_item, '12th') !== false || stripos($normalized_item, 'laptop 5') !== false) {
                            $query_gen = 'Surface Laptop 5 (1950/1951)';
                        } else {
                            $query_gen = 'Surface Laptop 4 (1950/1951)';
                        }
                    } elseif (stripos($normalized_item, '2033') !== false || stripos($normalized_item, '2035') !== false) {
                        $query_gen = 'Surface Laptop 6 (2033/2035)';
                    } elseif (stripos($normalized_item, '1943') !== false) {
                        $query_gen = 'Surface Laptop Go (1943)';
                    } elseif (stripos($normalized_item, '1703') !== false) {
                        $query_gen = 'Surface Book 1 (1703)';
                    } elseif (stripos($normalized_item, '1823') !== false) {
                        $query_gen = 'Surface Book 2 (1823)';
                    } elseif (stripos($normalized_item, '1834') !== false || stripos($normalized_item, '1835') !== false) {
                        $query_gen = 'Surface Book 2 (1834/1835)';
                    } elseif (stripos($normalized_item, '1899') !== false) {
                        if (stripos($normalized_item, '15"') !== false || stripos($normalized_item, '15-inch') !== false || stripos($normalized_item, '15 inch') !== false) {
                            $query_gen = '15" Surface Book 3 (1899)';
                        } else {
                            $query_gen = 'Surface Book 3 (1899)';
                        }
                    } elseif (stripos($normalized_item, '1900') !== false) {
                        $query_gen = 'Surface Book 3 (1900)';
                    } elseif (stripos($normalized_item, '1514') !== false) {
                        $query_gen = 'Surface Pro 1 (1514)';
                    } elseif (stripos($normalized_item, '1601') !== false) {
                        $query_gen = 'Surface Pro 2 (1601)';
                    } elseif (stripos($normalized_item, '1631') !== false) {
                        $query_gen = 'Surface Pro 3 (1631)';
                    } elseif (stripos($normalized_item, '1724') !== false) {
                        $query_gen = 'Surface Pro 4 (1724)';
                    } elseif (stripos($normalized_item, '1807') !== false) {
                        $query_gen = 'Surface Pro 5 (1807)';
                    } elseif (stripos($normalized_item, '1796') !== false) {
                        if (stripos($normalized_item, '8th') !== false || stripos($normalized_item, 'pro 6') !== false) {
                            $query_gen = 'Surface Pro 6 (1796)';
                        } else {
                            $query_gen = 'Surface Pro 5 (1796)';
                        }
                    } elseif (stripos($normalized_item, '1866') !== false) {
                        $query_gen = 'Surface Pro 7 (1866)';
                    } elseif (stripos($normalized_item, '1960') !== false) {
                        $query_gen = 'Surface Pro 7+ (1960)';
                    } elseif (stripos($normalized_item, '1983') !== false) {
                        $query_gen = 'Surface Pro 8 (1983)';
                    } elseif (stripos($normalized_item, '2038') !== false) {
                        $query_gen = 'Surface Pro 9 (2038)';
                    } elseif (stripos($normalized_item, '2079') !== false) {
                        $query_gen = 'Surface Pro 10 (2079)';
                    } else {
                        if (stripos($normalized_item, 'pro 8') !== false) {
                            $query_gen = 'Surface Pro 8 (Default)';
                        } elseif (stripos($normalized_item, 'pro 9') !== false) {
                            $query_gen = 'Surface Pro 9 (Default)';
                        } elseif (stripos($normalized_item, 'pro 10') !== false) {
                            $query_gen = 'Surface Pro 10 (Default)';
                        } elseif (stripos($normalized_item, 'pro 7') !== false) {
                            $query_gen = 'Surface Pro 7 (1866)';
                        } elseif (stripos($normalized_item, 'pro 6') !== false) {
                            $query_gen = 'Surface Pro 6 (1796)';
                        } elseif (stripos($normalized_item, 'pro 5') !== false) {
                            $query_gen = 'Surface Pro 5 (1796)';
                        } elseif (stripos($normalized_item, 'pro 4') !== false) {
                            $query_gen = 'Surface Pro 4 (1724)';
                        } elseif (stripos($normalized_item, 'pro 3') !== false) {
                            $query_gen = 'Surface Pro 3 (1631)';
                        } elseif (stripos($normalized_item, 'book 3') !== false) {
                            $query_gen = 'Surface Book 3 (1899)';
                        } elseif (stripos($normalized_item, 'book 2') !== false) {
                            $query_gen = 'Surface Book 2 (1823)';
                        } elseif (stripos($normalized_item, 'book 1') !== false) {
                            $query_gen = 'Surface Book 1 (1703)';
                        } elseif (stripos($normalized_item, 'laptop 4') !== false) {
                            $query_gen = 'Surface Laptop 4 (1950/1951)';
                        } elseif (stripos($normalized_item, 'laptop 3') !== false) {
                            $query_gen = 'Surface Laptop 3 (1867/1868)';
                        } elseif (stripos($normalized_item, 'laptop 2') !== false) {
                            $query_gen = 'Surface Laptop 2 (1769)';
                        } elseif (stripos($normalized_item, 'laptop 1') !== false || stripos($normalized_item, 'laptop') !== false) {
                            $query_gen = 'Surface Laptop 1 (1769)';
                        }
                    }

                    $is_untested = (strpos($normalized_cond, 'untested') !== false);
                    $is_parts = (stripos($normalized_item, 'parts') !== false || stripos($normalized_item, 'part') !== false || strpos($normalized_cond, 'parts') !== false);

                    if ($is_parts) {
                        $grade_key = 'For Parts';
                    } elseif ($is_untested) {
                        $grade_key = 'Untested';
                    } else {
                        $grade_key = 'Tested';
                    }
                } elseif ($category === 'Chromebook') {
                    $query_gen = 'Dell Chromebook 3180 / HP G5 EE';

                    if (stripos($normalized_item, '3180') !== false || stripos($normalized_item, 'g5') !== false) {
                        $query_gen = 'Dell Chromebook 3180 / HP G5 EE';
                    } elseif (stripos($normalized_item, '11a g6') !== false || stripos($normalized_item, '11a-g6') !== false) {
                        $query_gen = 'HP Chromebook 11A G6 EE';
                    } elseif (stripos($normalized_item, '11 g6') !== false || stripos($normalized_item, '11-g6') !== false) {
                        $query_gen = 'HP Chromebook 11 G6 EE';
                    } elseif (stripos($normalized_item, '11 g7') !== false || stripos($normalized_item, '11-g7') !== false) {
                        $query_gen = 'HP Chromebook 11 G7 EE';
                    } elseif (stripos($normalized_item, '11a g8') !== false || stripos($normalized_item, '11a-g8') !== false) {
                        $query_gen = 'HP Chromebook 11A G8 EE';
                    } elseif (stripos($normalized_item, '11 g8') !== false || stripos($normalized_item, '11-g8') !== false) {
                        $query_gen = 'HP Chromebook 11 G8 EE';
                    } elseif (stripos($normalized_item, '11 g9') !== false || stripos($normalized_item, '11-g9') !== false) {
                        $query_gen = 'HP Chromebook 11 G9 EE';
                    } elseif (stripos($normalized_item, '11 g10') !== false || stripos($normalized_item, '11-g10') !== false) {
                        $query_gen = 'HP Chromebook 11 G10 EE';
                    } elseif (stripos($normalized_item, 'x360 11 g3') !== false || stripos($normalized_item, 'g3 ee') !== false) {
                        $query_gen = 'HP x360 11 G3 EE (Convertible)';
                    } elseif (stripos($normalized_item, 'x360 11 g4') !== false || stripos($normalized_item, 'g4 ee') !== false) {
                        $query_gen = 'HP x360 11 G4 EE (Convertible)';
                    } elseif (stripos($normalized_item, '3100') !== false) {
                        $query_gen = 'Dell 3100 / 3100 2-in-1';
                    } elseif (stripos($normalized_item, '3110') !== false) {
                        $query_gen = 'Dell Chromebook 3110 / 2-in-1';
                    } elseif (stripos($normalized_item, '3120') !== false) {
                        $query_gen = 'Dell Chromebook 3120';
                    } elseif (stripos($normalized_item, '500e') !== false) {
                        $query_gen = 'Lenovo 500e 2nd Gen (Convertible)';
                    } elseif (stripos($normalized_item, 'samsung') !== false && (stripos($normalized_item, 'chromebook 4') !== false || stripos($normalized_item, 'cb4') !== false)) {
                        $query_gen = 'Samsung Chromebook 4 (11")';
                    } elseif (stripos($normalized_item, '100e') !== false || stripos($normalized_item, '300e') !== false) {
                        if (stripos($normalized_item, '3rd') !== false || stripos($normalized_item, '3rd gen') !== false) {
                            $query_gen = 'Lenovo 100e / 300e 3rd Gen';
                        } elseif (stripos($normalized_item, 'intel') !== false || stripos($normalized_item, 'celeron') !== false) {
                            $query_gen = 'Lenovo 100e / 300e 2nd Gen (Intel)';
                        } else {
                            $query_gen = 'Lenovo 100e / 300e 2nd Gen (MTK)';
                        }
                    }

                    $is_untested = (strpos($normalized_cond, 'untested') !== false || stripos($normalized_item, 'untested') !== false);
                    if ($is_untested) {
                        $grade_key = 'Untested Lot';
                    } else {
                        $grade_key = 'Tested - Clean (A/B)';
                    }
                } elseif ($category === 'Apple') {
                    $query_gen = $model;

                    $is_untested = (strpos($normalized_cond, 'untested') !== false);
                    $is_parts = (stripos($normalized_item, 'parts') !== false || stripos($normalized_item, 'part') !== false || strpos($normalized_cond, 'parts') !== false);

                    if ($is_parts) {
                        $grade_key = 'For Parts';
                    } elseif ($is_untested) {
                        $grade_key = 'Untested';
                    } else {
                        $grade_key = 'Tested';
                    }
                } elseif ($category === 'Storage') {
                    $storage_gigs = '';
                    if (preg_match('/\b(128|256|512)\s*(?:GB|gb)\b/i', $normalized_item, $matches)) {
                        $storage_gigs = $matches[1] . 'GB';
                    } elseif (preg_match('/\b([12])\s*(?:TB|tb)\b/i', $normalized_item, $matches)) {
                        $storage_gigs = $matches[1] . 'TB';
                    }
                    if (!empty($storage_gigs)) {
                        $query_gen = $storage_gigs . ' M.2';
                    }
                    if ($grade_key === 'Parts') {
                        $grade_key = 'Tested';
                    }
                } elseif ($category === 'Rugged') {
                    $query_gen = mapCpuToMatrixGen($cpu, $gen);

                    $is_untested = (strpos($normalized_cond, 'untested') !== false);
                    $has_battery_issue = (stripos($normalized_item, 'no battery') !== false || stripos($normalized_item, 'missing battery') !== false);
                    $is_parts = (stripos($normalized_item, 'parts') !== false || stripos($normalized_item, 'part') !== false || strpos($normalized_cond, 'parts') !== false);

                    if ($is_untested) {
                        if ($is_parts || $has_battery_issue) {
                            $grade_key = 'Untested Parts';
                        } else {
                            $grade_key = 'Untested Complete';
                        }
                    } else {
                        if ($has_battery_issue) {
                            $grade_key = 'Tested No Battery';
                        } else {
                            $grade_key = 'Tested Complete';
                        }
                    }
                }

                if (!empty($query_gen)) {
                    $stmt_pr = $conn_wh->prepare("SELECT price FROM pricing_rules WHERE category = ? AND cpu_gen = ? AND grade = ?");
                    $stmt_pr->execute([$category, $query_gen, $grade_key]);
                    $price_db = $stmt_pr->fetchColumn();
                    if ($price_db !== false) {
                        $price = (float)$price_db;
                    }
                }
            } catch (Exception $e) {
                // Keep 0.00
            }
        }
    }

    return [
        'brand' => $brand,
        'model' => $model,
        'series' => $series,
        'cpu' => $cpu,
        'gen' => $gen,
        'ram' => $ram,
        'storage' => $storage,
        'battery' => $battery,
        'condition' => $condition,
        'price' => $price
    ];
}

function getOrCreateLocation($conn, $locCode, $zoneName = null) {
    $locCode = trim($locCode);
    if (empty($locCode)) return;

    $stmt = $conn->prepare("SELECT COUNT(*) FROM locations WHERE location_code = ?");
    $stmt->execute([$locCode]);
    $exists = $stmt->fetchColumn() > 0;

    if (!$exists) {
        if ($zoneName === null || trim($zoneName) === '') {
            $zoneName = 'General';
            if (preg_match('/^([a-zA-Z]+)/u', $locCode, $matches)) {
                $prefix = strtoupper($matches[1]);
                $zoneName = 'Zone ' . $prefix;
            }
        }

        $stmtZone = $conn->prepare("INSERT OR IGNORE INTO working_zones (name) VALUES (?)");
        $stmtZone->execute([$zoneName]);

        $stmtLoc = $conn->prepare("INSERT OR IGNORE INTO locations (location_code, status, working_zone_name) VALUES (?, 'Idle', ?)");
        $stmtLoc->execute([$locCode, $zoneName]);
    } else {
        if ($zoneName !== null && trim($zoneName) !== '') {
            $stmtZone = $conn->prepare("INSERT OR IGNORE INTO working_zones (name) VALUES (?)");
            $stmtZone->execute([$zoneName]);

            $stmtLoc = $conn->prepare("UPDATE locations SET working_zone_name = ? WHERE location_code = ?");
            $stmtLoc->execute([$zoneName, $locCode]);
        }
    }
}

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
                        // Skip empty rows
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
            // In case select is empty but custom text was entered
            $override_loc = trim(strtoupper($_POST['override_location_custom']));
        }

        $conn_wh->beginTransaction();
        try {
            $count = 0;
            foreach ($_SESSION['import_rows'] as $row) {
                if ($row['status'] === 'Accept') {
                    $loc = ($override_loc !== null) ? $override_loc : $row['location'];
                    // Create location if missing
                    getOrCreateLocation($conn_wh, $loc, $override_zone);

                    // Set standard fields
                    $brand = $row['parsed']['brand'];
                    $model = $row['parsed']['model'];
                    $sector = 'Laptops'; // Target sector Laptops as requested
                    $qty = (int)$row['qty'];
                    $price = (float)$row['parsed']['price'];

                    // Prepare specs JSON
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

// Cancel Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_import') {
    unset($_SESSION['import_rows']);
    header("Location: index.php?view=import_warehouse");
    exit();
}
?>

<div class="orders-container" style="animation: fadeInDown 0.4s ease-out; width: 100%; max-width: 1400px; margin: 0 auto; padding: 20px;">
    <header class="orders-header" style="margin-bottom: 40px; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 900; color: var(--text-main); margin-bottom: 5px;">Migrate CSV Manifest to Working Zones</h1>
            <p style="color: var(--text-secondary); font-size: 1rem;">Import inventory sheets, dynamically register new shelves, and sanitize tech specs.</p>
        </div>
        <a href="index.php?view=warehouse&sector=Laptops" class="btn-main" style="background: #f1f5f9; color: #475569; box-shadow: none; border: 1px solid #e2e8f0;">
            ← Back to Warehouse
        </a>
    </header>

    <?php if ($message): ?>
        <div style="background: #ecfdf5; color: #065f46; padding: 20px; border-radius: 16px; margin-bottom: 30px; font-weight: 700; border: 1px solid #d1fae5; display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 1.5rem;">✅</span> <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #fef2f2; color: #991b1b; padding: 20px; border-radius: 16px; margin-bottom: 30px; font-weight: 700; border: 1px solid #fecaca; display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 1.5rem;">⚠️</span> <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if (!$preview_mode && empty($_SESSION['import_rows'])): ?>
        <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 40px;">
            <!-- UPLOAD ZONE -->
            <div style="background: white; padding: 40px; border-radius: 24px; border: 1px solid #e2e8f0; box-shadow: var(--shadow-sm);">
                <form action="index.php?view=import_warehouse" method="POST" enctype="multipart/form-data">
                    <div style="margin-bottom: 30px;">
                        <label for="csv-input" style="display: block; font-weight: 800; font-size: 1.1rem; color: var(--text-main); margin-bottom: 15px;">1. Select CSV Manifest</label>
                        <div id="drop-zone" style="border: 2px dashed #cbd5e1; border-radius: 20px; padding: 60px 20px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.3s ease;">
                            <input type="file" name="inventory_csv" id="csv-input" accept=".csv" required style="display: none;">
                            <div style="font-size: 4rem; margin-bottom: 15px;">📂</div>
                            <div style="font-weight: 800; font-size: 1.2rem; color: #1e293b; margin-bottom: 8px;">Click to Upload CSV</div>
                            <p id="file-name" style="color: #64748b; font-size: 0.95rem;">File must contain columns: Date, QTY, Item, Serial, location, notes</p>
                        </div>
                    </div>

                    <button type="submit" class="btn-main" style="width: 100%; height: 60px; font-size: 1.1rem; border-radius: 16px;">
                        🔍 Validate CSV & Preview Import
                    </button>
                </form>
            </div>

            <!-- GUIDE -->
            <div style="background: #f8fafc; padding: 35px; border-radius: 24px; border: 1px solid #e2e8f0;">
                <h3 style="font-weight: 900; font-size: 1.2rem; color: var(--text-main); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 1.4rem;">📘</span> CSV Data Migration Criteria
                </h3>
                <p style="color: #475569; font-size: 0.95rem; line-height: 1.6; margin-bottom: 25px;">
                    Upload inventory documents containing details about devices. The system will parse the properties out of the fields automatically.
                </p>

                <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                    <div style="background: white; padding: 12px 18px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <code style="font-weight: 800; color: var(--accent-dark);">Date | QTY</code>
                        <span style="font-size: 0.85rem; color: #94a3b8;">Entry date & total quantities</span>
                    </div>
                    <div style="background: white; padding: 12px 18px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <code style="font-weight: 800; color: var(--accent-dark);">Item</code>
                        <span style="font-size: 0.85rem; color: #94a3b8;">Text describing device specifications</span>
                    </div>
                    <div style="background: white; padding: 12px 18px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <code style="font-weight: 800; color: var(--accent-dark);">Serial | Location</code>
                        <span style="font-size: 0.85rem; color: #94a3b8;">Shelf code (A-O or custom) & serial number</span>
                    </div>
                </div>

                <div style="padding: 20px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 16px;">
                    <h4 style="font-weight: 900; color: #92400e; margin-bottom: 5px; font-size: 0.95rem;">💡 Auto-Creating Locations</h4>
                    <p style="color: #b45309; font-size: 0.85rem; line-height: 1.5;">
                        If a location listed in the CSV (e.g. <strong>N4</strong>) doesn't exist, the system will automatically create it and map it to its corresponding working zone (e.g. <strong>Zone N</strong>).
                    </p>
                </div>
            </div>
        </div>
    <?php else:
        $display_rows = $_SESSION['import_rows'] ?? $rows;
        $total = count($display_rows);
        $accepted = 0;
        $rejected = 0;
        foreach($display_rows as $r) {
            if ($r['status'] === 'Accept') $accepted++;
            else $rejected++;
        }
        $working_zones = [];
        $zone_locations_map = [];
        $suggested_zone = '';
        try {
            $stmt_zones = $conn_wh->query("SELECT name FROM working_zones ORDER BY name ASC");
            $working_zones = $stmt_zones->fetchAll(PDO::FETCH_COLUMN);

            $stmt_locs = $conn_wh->query("SELECT location_code, working_zone_name FROM locations ORDER BY location_code ASC");
            while ($row_loc = $stmt_locs->fetch(PDO::FETCH_ASSOC)) {
                $z = $row_loc['working_zone_name'] ?: 'General';
                $zone_locations_map[$z][] = $row_loc['location_code'];
            }

            // Detect suggested zone based on first accepted item's location prefix or code
            $sample_location = '';
            foreach ($display_rows as $row) {
                if ($row['status'] === 'Accept' && !empty($row['location'])) {
                    $sample_location = trim($row['location']);
                    break;
                }
            }
            if ($sample_location !== '') {
                // Check if this location exists and has a zone
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
        } catch (Exception $e) {}
    ?>
        <!-- PREVIEW MODE & SANITIZATION REVIEW -->
        <div style="background: white; border-radius: 24px; border: 1px solid #e2e8f0; padding: 30px; box-shadow: var(--shadow-sm); margin-bottom: 40px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h2 style="font-weight: 900; font-size: 1.4rem; color: var(--text-main);">Verification & Sanitization Report</h2>
                    <p style="color: var(--text-secondary); font-size: 0.95rem;">Please review the parsed results and validation status before importing.</p>
                </div>
                <form action="index.php?view=import_warehouse" method="POST">
                    <input type="hidden" name="action" value="cancel_import">
                    <button type="submit" class="btn-main" style="background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; box-shadow: none; font-size: 0.9rem; padding: 10px 20px; border-radius: 12px;">
                        ❌ Cancel Import
                    </button>
                </form>
            </div>

            <div id="confirm-import-container" style="display: <?= $accepted > 0 ? 'block' : 'none' ?>; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 20px; padding: 25px; margin-bottom: 30px;">
                <form action="index.php?view=import_warehouse" method="POST" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 25px; align-items: end;">
                    <input type="hidden" name="action" value="confirm_import">

                    <!-- Select Target Area / Working Zone -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="override_zone_select" style="font-weight: 800; font-size: 0.9rem; color: #475569;">1. Target Area (Zone)</label>
                        <div style="display: flex; gap: 10px; width: 100%;">
                            <select name="override_zone_select" id="override_zone_select" style="flex: 1; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 12px; font-weight: bold; background: white; font-size: 0.95rem; outline: none;" onchange="onZoneChange()">
                                <option value="__NEW_ZONE__">+ Create New Zone...</option>
                                <option value="" <?= empty($suggested_zone) ? 'selected' : '' ?>>-- Auto-Detect Zone --</option>
                                <?php foreach ($working_zones as $wz): ?>
                                    <option value="<?= htmlspecialchars($wz) ?>" <?= ($suggested_zone === $wz) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($wz) ?><?= ($suggested_zone === $wz) ? ' (Suggested)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="override_zone_custom" id="override_zone_custom" placeholder="New Zone Name" style="display: none; width: 140px; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 12px; font-weight: bold; font-size: 0.95rem; text-transform: uppercase;">
                        </div>
                    </div>

                    <!-- Select Location (Filtered by chosen Working Zone) -->
                    <div id="override-location-wrapper" style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="override_location_select" style="font-weight: 800; font-size: 0.9rem; color: #475569;">2. Shelf / Layer Code</label>
                        <div style="display: flex; gap: 10px; width: 100%;">
                            <select name="override_location_select" id="override_location_select" style="flex: 1; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 12px; font-weight: bold; background: white; font-size: 0.95rem; outline: none;" onchange="toggleCustomLocationInput()">
                                <option value="">📄 Keep Row-Level Locations (Default)</option>
                            </select>
                            <input type="text" name="override_location_custom" id="override_location_custom" placeholder="New Shelf/Box" style="display: none; width: 140px; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 12px; font-weight: bold; font-size: 0.95rem; text-transform: uppercase;">
                        </div>
                    </div>

                    <!-- Submit Import Button -->
                    <div>
                        <button type="submit" class="btn-main" style="background: var(--accent-color); color: white; height: 48px; padding: 0 30px; font-size: 1rem; border-radius: 12px; font-weight: 900; box-shadow: 0 4px 12px rgba(140, 198, 63, 0.25);">
                            🚀 Confirm Import (<?= $accepted ?> items)
                        </button>
                    </div>
                </form>
            </div>

            <!-- Stats Bar -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px; text-align: center;">
                    <div style="font-size: 0.85rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Total Rows</div>
                    <div id="stats-total" style="font-size: 2rem; font-weight: 900; color: #1e293b;"><?= $total ?></div>
                </div>
                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 20px; border-radius: 16px; text-align: center;">
                    <div style="font-size: 0.85rem; font-weight: 800; color: #065f46; text-transform: uppercase;">Passed / Accepted</div>
                    <div id="stats-accepted" style="font-size: 2rem; font-weight: 900; color: #059669;"><?= $accepted ?></div>
                </div>
                <div style="background: #fef2f2; border: 1px solid #fca5a5; padding: 20px; border-radius: 16px; text-align: center;">
                    <div style="font-size: 0.85rem; font-weight: 800; color: #991b1b; text-transform: uppercase;">Rejected / Invalid</div>
                    <div id="stats-rejected" style="font-size: 2rem; font-weight: 900; color: #dc2626;"><?= $rejected ?></div>
                </div>
            </div>

            <!-- Style overrides for warning highlights -->
            <style>
                .cell-input.warning-empty {
                    background-color: #fffbeb !important;
                    border: 1px dashed #f59e0b !important;
                    color: #b45309 !important;
                }
                .cell-input.warning-empty::placeholder {
                    color: #d97706 !important;
                    opacity: 0.6;
                }
                .spreadsheet-table td input.cell-input {
                    background: transparent;
                    border: 1px solid transparent;
                    transition: all 0.2s ease;
                }
                .spreadsheet-table td input.cell-input:hover {
                    border-color: #cbd5e1;
                    background: #fff;
                }
                .spreadsheet-table td input.cell-input:focus {
                    outline: none;
                    background: #fff;
                    border-color: var(--accent-color) !important;
                    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
                }
            </style>

            <!-- Preview Table -->
            <div class="spreadsheet-table-wrapper" style="max-height: 600px; overflow-y: auto;">
                <table class="spreadsheet-table" style="table-layout: auto;">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Status</th>
                            <th>Original Item Info</th>
                            <th style="width: 90px;">Location</th>
                            <th style="width: 100px;">Brand</th>
                            <th style="width: 120px;">Model</th>
                            <th style="width: 90px;">Series</th>
                            <th style="width: 80px; position: relative; cursor: pointer; user-select: none;" onclick="toggleCpuBulkMenu(event)">
                                CPU <span style="font-size: 0.65rem; opacity: 0.6;">▼</span>
                                <div id="cpu-bulk-menu" style="text-transform: none; display: none; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); background: white; border: 1px solid #cbd5e1; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); z-index: 100; min-width: 140px; padding: 6px 0; margin-top: 5px;">
                                    <div style="padding: 6px 12px; font-size: 0.7rem; color: #64748b; font-weight: 800; border-bottom: 1px solid #f1f5f9; text-align: center;">Bulk Default CPU</div>
                                    <a href="#" onclick="bulkUpdateDefaultCpu(event, 'i3')" style="display: block; padding: 8px 12px; color: var(--text-main); font-weight: 700; font-size: 0.85rem; text-decoration: none; text-align: center; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#f1f5f9'" onmouseout="this.style.backgroundColor='transparent'">i3</a>
                                    <a href="#" onclick="bulkUpdateDefaultCpu(event, 'i5')" style="display: block; padding: 8px 12px; color: var(--text-main); font-weight: 700; font-size: 0.85rem; text-decoration: none; text-align: center; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#f1f5f9'" onmouseout="this.style.backgroundColor='transparent'">i5</a>
                                    <a href="#" onclick="bulkUpdateDefaultCpu(event, 'i7')" style="display: block; padding: 8px 12px; color: var(--text-main); font-weight: 700; font-size: 0.85rem; text-decoration: none; text-align: center; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#f1f5f9'" onmouseout="this.style.backgroundColor='transparent'">i7</a>
                                    <a href="#" onclick="bulkUpdateDefaultCpu(event, 'i9')" style="display: block; padding: 8px 12px; color: var(--text-main); font-weight: 700; font-size: 0.85rem; text-decoration: none; text-align: center; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#f1f5f9'" onmouseout="this.style.backgroundColor='transparent'">i9</a>
                                </div>
                            </th>
                            <th style="width: 80px;">Gen</th>
                            <th style="width: 80px;">RAM</th>
                            <th style="width: 90px;">Storage</th>
                            <th style="width: 80px;">Battery</th>
                            <th style="width: 100px;">Condition</th>
                            <th style="width: 80px;">Price</th>
                            <th style="width: 70px;">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($display_rows as $rowIndex => $row):
                            $isAccepted = $row['status'] === 'Accept';
                        ?>
                            <tr style="background-color: <?= $isAccepted ? 'rgba(236, 253, 245, 0.4)' : 'rgba(254, 242, 242, 0.6)' ?>;">
                                <td style="padding: 10px; font-weight: 800; text-align: center;">
                                    <?php if ($isAccepted): ?>
                                        <span style="color: #059669; background: #d1fae5; padding: 4px 8px; border-radius: 8px; font-size: 0.75rem;">Accept</span>
                                    <?php else: ?>
                                        <span style="color: #dc2626; background: #fee2e2; padding: 4px 8px; border-radius: 8px; font-size: 0.75rem;" title="<?= htmlspecialchars(implode(', ', $row['errors'])) ?>">Reject ⚠️</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 10px; font-size: 0.8rem; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <strong><?= htmlspecialchars($row['item']) ?></strong>
                                    <?php if (!empty($row['errors'])): ?>
                                        <div class="row-error-list" style="color: #b91c1c; font-size: 0.7rem; font-weight: 700; margin-top: 4px;">
                                            <?= htmlspecialchars(implode(', ', $row['errors'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="editable-cell" data-field="location" style="padding: 5px;">
                                    <input type="text" class="cell-input <?= empty(trim($row['location'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['location']) ?>" style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; font-weight: bold; text-align: center;">
                                </td>
                                <td class="editable-cell" data-field="brand" style="padding: 5px;">
                                    <input type="text" class="cell-input <?= (empty(trim($row['parsed']['brand'])) || $row['parsed']['brand'] === 'Unknown') ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['brand']) ?>" style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem;">
                                </td>
                                <td class="editable-cell" data-field="model" style="padding: 5px;">
                                    <input type="text" class="cell-input <?= (empty(trim($row['parsed']['model'])) || $row['parsed']['model'] === 'Unknown') ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['model']) ?>" style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem;">
                                </td>
                                <td class="editable-cell" data-field="series" style="padding: 5px;">
                                    <input type="text" class="cell-input <?= empty(trim($row['parsed']['series'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['series']) ?>" placeholder="..." style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem;">
                                </td>
                                <td class="editable-cell" data-field="cpu" style="padding: 5px;">
                                    <input type="text" class="cell-input <?= empty(trim($row['parsed']['cpu'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['cpu']) ?>" placeholder="..." style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: center;">
                                </td>
                                <td class="editable-cell" data-field="gen" style="padding: 5px;">
                                    <input type="text" class="cell-input <?= empty(trim($row['parsed']['gen'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['gen']) ?>" placeholder="..." style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: center;">
                                </td>
                                <td class="editable-cell" data-field="ram" style="padding: 5px;">
                                    <input type="text" class="cell-input <?= empty(trim($row['parsed']['ram'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['ram']) ?>" placeholder="..." style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: center;">
                                </td>
                                <td class="editable-cell" data-field="storage" style="padding: 5px;">
                                    <input type="text" class="cell-input <?= empty(trim($row['parsed']['storage'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['storage']) ?>" placeholder="..." style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: center;">
                                </td>
                                <td class="editable-cell" data-field="battery" style="padding: 5px;">
                                    <input type="text" class="cell-input <?= empty(trim($row['parsed']['battery'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['battery']) ?>" placeholder="..." style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: center;">
                                </td>
                                <td class="editable-cell" data-field="condition" style="padding: 5px;">
                                    <input type="text" class="cell-input <?= empty(trim($row['parsed']['condition'])) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['parsed']['condition']) ?>" style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: center;">
                                </td>
                                <td class="editable-cell" data-field="price" style="padding: 5px;">
                                    <input type="number" step="any" class="cell-input" value="<?= htmlspecialchars($row['parsed']['price']) ?>" style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; text-align: right; font-weight: 700;">
                                </td>
                                <td class="editable-cell" data-field="qty" style="padding: 5px;">
                                    <input type="number" step="1" class="cell-input <?= ((int)$row['qty'] <= 0) ? 'warning-empty' : '' ?>" value="<?= htmlspecialchars($row['qty']) ?>" style="width: 100%; padding: 6px; border-radius: 6px; font-size: 0.85rem; font-weight: bold; text-align: center;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    const dropZone = document.getElementById('drop-zone');
    const csvInput = document.getElementById('csv-input');
    const fileName = document.getElementById('file-name');

    if (dropZone) {
        dropZone.onclick = () => csvInput.click();
    }

    if (csvInput) {
        csvInput.onchange = (e) => {
            if (e.target.files.length > 0) {
                fileName.innerText = e.target.files[0].name;
                fileName.style.color = 'var(--accent-color)';
                fileName.style.fontWeight = '900';
                dropZone.style.borderColor = 'var(--accent-color)';
                dropZone.style.background = '#f0fdf4';
            }
        };
    }

    document.addEventListener('DOMContentLoaded', () => {
        const table = document.querySelector('.spreadsheet-table');
        if (!table) return;

        table.addEventListener('focusout', async (e) => {
            if (e.target && e.target.classList.contains('cell-input')) {
                const input = e.target;
                const cell = input.closest('td');
                const row = input.closest('tr');
                if (!cell || !row) return;

                const rowIndex = row.rowIndex - 1; // 0-based data rows
                const field = cell.getAttribute('data-field');
                const val = input.value.trim();

                try {
                    const response = await fetch('index.php?view=import_warehouse&action=update_import_cell', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            row_index: rowIndex,
                            field: field,
                            val: val
                        })
                    });
                    const res = await response.json();
                    if (res.success) {
                        // Update warnings locally
                        if (val === '' || val === '-' || val === 'Unknown') {
                            input.classList.add('warning-empty');
                        } else {
                            input.classList.remove('warning-empty');
                        }
                        if (field === 'qty') {
                            const qtyVal = parseInt(val, 10);
                            if (isNaN(qtyVal) || qtyVal <= 0) {
                                input.classList.add('warning-empty');
                            } else {
                                input.classList.remove('warning-empty');
                            }
                        }

                        // Update Status badge
                        const statusTd = row.cells[0];
                        const originalTd = row.cells[1];
                        const isAccepted = res.status === 'Accept';

                        row.style.backgroundColor = isAccepted ? 'rgba(236, 253, 245, 0.4)' : 'rgba(254, 242, 242, 0.6)';

                        if (isAccepted) {
                            statusTd.innerHTML = '<span style="color: #059669; background: #d1fae5; padding: 4px 8px; border-radius: 8px; font-size: 0.75rem;">Accept</span>';
                        } else {
                            const errorsText = res.errors.join(', ');
                            statusTd.innerHTML = `<span style="color: #dc2626; background: #fee2e2; padding: 4px 8px; border-radius: 8px; font-size: 0.75rem;" title="${errorsText}">Reject ⚠️</span>`;
                        }

                        // Update error text display
                        let errorDiv = originalTd.querySelector('.row-error-list');
                        if (res.errors.length > 0) {
                            if (!errorDiv) {
                                errorDiv = document.createElement('div');
                                errorDiv.className = 'row-error-list';
                                errorDiv.style.color = '#b91c1c';
                                errorDiv.style.fontSize = '0.7rem';
                                errorDiv.style.fontWeight = '700';
                                errorDiv.style.marginTop = '4px';
                                originalTd.appendChild(errorDiv);
                            }
                            errorDiv.textContent = res.errors.join(', ');
                        } else if (errorDiv) {
                            errorDiv.remove();
                        }

                        // Update stats
                        const totalEl = document.getElementById('stats-total');
                        const acceptedEl = document.getElementById('stats-accepted');
                        const rejectedEl = document.getElementById('stats-rejected');
                        if (totalEl) totalEl.textContent = res.total;
                        if (acceptedEl) acceptedEl.textContent = res.accepted;
                        if (rejectedEl) rejectedEl.textContent = res.rejected;

                        // Update Confirm Import container
                        const confirmContainer = document.getElementById('confirm-import-container');
                        if (confirmContainer) {
                            if (res.accepted > 0) {
                                confirmContainer.style.display = 'block';
                                const btn = confirmContainer.querySelector('button');
                                if (btn) {
                                    btn.textContent = `🚀 Confirm Import (${res.accepted} items)`;
                                }
                            } else {
                                confirmContainer.style.display = 'none';
                            }
                        }

                        // Cell flash feedback
                        cell.style.backgroundColor = 'rgba(140, 198, 63, 0.15)';
                        setTimeout(() => { cell.style.backgroundColor = ''; }, 600);
                    }
                } catch (err) {
                    console.error('AJAX update error:', err);
                }
            }
        });

        // Auto-initialize filtered location list if a suggested zone is preselected
        onZoneChange();
    });
    const zoneLocationsMap = <?= json_encode($zone_locations_map) ?>;

    function onZoneChange() {
        const zoneSelect = document.getElementById('override_zone_select');
        const customZoneInput = document.getElementById('override_zone_custom');
        const locSelect = document.getElementById('override_location_select');
        const customLocInput = document.getElementById('override_location_custom');

        if (!zoneSelect || !locSelect) return;

        // Reset inputs
        customZoneInput.style.display = 'none';
        customZoneInput.required = false;
        customZoneInput.value = '';

        customLocInput.style.display = 'none';
        customLocInput.required = false;
        customLocInput.value = '';

        // Reset Location Dropdown options
        locSelect.innerHTML = '<option value="">📄 Keep Row-Level Locations (Default)</option>';

        if (zoneSelect.value === '__NEW_ZONE__') {
            customZoneInput.style.display = 'inline-block';
            customZoneInput.required = true;
            customZoneInput.focus();

            // Allow custom location creation directly
            const optNew = document.createElement('option');
            optNew.value = '__NEW_LOC__';
            optNew.textContent = '+ Create New Location...';
            locSelect.appendChild(optNew);
            locSelect.value = '__NEW_LOC__';
            toggleCustomLocationInput();
        } else if (zoneSelect.value !== '') {
            const selectedZone = zoneSelect.value;
            const locs = zoneLocationsMap[selectedZone] || [];

            locs.forEach(loc => {
                const opt = document.createElement('option');
                opt.value = loc;
                opt.textContent = loc;
                locSelect.appendChild(opt);
            });

            const optNew = document.createElement('option');
            optNew.value = '__NEW_LOC__';
            optNew.textContent = '+ Create New Location...';
            locSelect.appendChild(optNew);
        }
    }

    function toggleCustomLocationInput() {
        const select = document.getElementById('override_location_select');
        const customInput = document.getElementById('override_location_custom');
        if (select && customInput) {
            if (select.value === '__NEW_LOC__') {
                customInput.style.display = 'inline-block';
                customInput.required = true;
                customInput.focus();
            } else {
                customInput.style.display = 'none';
                customInput.required = false;
                customInput.value = '';
            }
        }
    }

    function toggleCpuBulkMenu(event) {
        event.stopPropagation();
        const menu = document.getElementById('cpu-bulk-menu');
        if (menu) {
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }
    }

    async function bulkUpdateDefaultCpu(event, targetCpu) {
        event.preventDefault();
        event.stopPropagation();
        
        const menu = document.getElementById('cpu-bulk-menu');
        if (menu) menu.style.display = 'none';

        const rows = document.querySelectorAll('.spreadsheet-table tbody tr');
        let updatePromises = [];

        rows.forEach((row, index) => {
            const cpuCell = row.querySelector('td[data-field="cpu"]');
            const genCell = row.querySelector('td[data-field="gen"]');
            if (cpuCell && genCell) {
                const input = cpuCell.querySelector('input.cell-input');
                const genInput = genCell.querySelector('input.cell-input');
                if (input && genInput) {
                    const currentVal = input.value.trim();
                    const genVal = genInput.value.trim();
                    if ((currentVal === 'i5' || currentVal === '') && genVal !== '' && genVal !== '-') {
                        input.value = targetCpu;
                        cpuCell.style.backgroundColor = 'rgba(140, 198, 63, 0.15)';
                        setTimeout(() => { cpuCell.style.backgroundColor = ''; }, 600);

                        const rowIndex = index;
                        const promise = fetch('index.php?view=import_warehouse&action=update_import_cell', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                row_index: rowIndex,
                                field: 'cpu',
                                val: targetCpu
                            })
                        }).then(r => r.json()).then(res => {
                            if (res.success) {
                                const statusTd = row.cells[0];
                                const originalTd = row.cells[1];
                                const isAccepted = res.status === 'Accept';
                                row.style.backgroundColor = isAccepted ? 'rgba(236, 253, 245, 0.4)' : 'rgba(254, 242, 242, 0.6)';
                                
                                if (isAccepted) {
                                    statusTd.innerHTML = '<span style="color: #059669; background: #d1fae5; padding: 4px 8px; border-radius: 8px; font-size: 0.75rem;">Accept</span>';
                                } else {
                                    const errorsText = res.errors.join(', ');
                                    statusTd.innerHTML = `<span style="color: #dc2626; background: #fee2e2; padding: 4px 8px; border-radius: 8px; font-size: 0.75rem;" title="${errorsText}">Reject ⚠️</span>`;
                                }

                                let errorDiv = originalTd.querySelector('.row-error-list');
                                if (res.errors.length > 0) {
                                    if (!errorDiv) {
                                        errorDiv = document.createElement('div');
                                        errorDiv.className = 'row-error-list';
                                        errorDiv.style.color = '#b91c1c';
                                        errorDiv.style.fontSize = '0.7rem';
                                        errorDiv.style.fontWeight = '700';
                                        errorDiv.style.marginTop = '4px';
                                        originalTd.appendChild(errorDiv);
                                    }
                                    errorDiv.textContent = res.errors.join(', ');
                                } else if (errorDiv) {
                                    errorDiv.remove();
                                }

                                const totalEl = document.getElementById('stats-total');
                                const acceptedEl = document.getElementById('stats-accepted');
                                const rejectedEl = document.getElementById('stats-rejected');
                                if (totalEl) totalEl.textContent = res.total;
                                if (acceptedEl) acceptedEl.textContent = res.accepted;
                                if (rejectedEl) rejectedEl.textContent = res.rejected;

                                const confirmContainer = document.getElementById('confirm-import-container');
                                if (confirmContainer) {
                                    if (res.accepted > 0) {
                                        confirmContainer.style.display = 'block';
                                        const btn = confirmContainer.querySelector('button');
                                        if (btn) {
                                            btn.textContent = `🚀 Confirm Import (${res.accepted} items)`;
                                        }
                                    } else {
                                        confirmContainer.style.display = 'none';
                                    }
                                }
                            }
                        });
                        updatePromises.push(promise);
                    }
                }
            }
        });

        await Promise.all(updatePromises);
    }

    document.addEventListener('click', () => {
        const menu = document.getElementById('cpu-bulk-menu');
        if (menu) {
            menu.style.display = 'none';
        }
    });
</script>
