<?php
// Secure Account Settings Fragment
$db_file = __DIR__ . '/../../db/users.db';
$message = $_SESSION['settings_success_message'] ?? '';
unset($_SESSION['settings_success_message']);
$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!Security::validate($_POST['csrf_token'] ?? '')) {
        die("Security Error: CSRF token validation failed.");
    }
}

try {
    $conn_u = Database::users();

    $username = $_SESSION['username'];
    $stmt_ppp = $conn_u->prepare("SELECT ppp_sequence_key, ppp_row_index, ppp_password_len FROM users WHERE username = ?");
    $stmt_ppp->execute([$username]);
    $user_row = $stmt_ppp->fetch(PDO::FETCH_ASSOC);
    $seq_key = $user_row['ppp_sequence_key'] ?? '';
    $saved_row_index = (int)($user_row['ppp_row_index'] ?? 0);
    $saved_pass_len = (int)($user_row['ppp_password_len'] ?? ($_SESSION['ppp_password_len'] ?? 30));
    if ($saved_pass_len < 25) {
        $saved_pass_len = 30;
    }

    // Helper to generate PPP passcodes
    // Algorithm designed by Steve Gibson (Gibson Research Corporation)
    // Reference: https://www.grc.com/ppp.htm
    function generate_ppp_passcodes($sequence_key, $cell_len = 4) {
        $alphabet = '!#%+23456789:=?@ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $key_bin = hex2bin($sequence_key);
        $passcodes = [];

        for ($i = 0; $i < 125; $i++) {
            $ciphertext = "";
            $blocks_needed = (int)ceil(($cell_len * 6) / 128.0);
            for ($b = 0; $b < $blocks_needed; $b++) {
                $counter_bin = pack('P', $i) . pack('P', $b);
                $ciphertext .= openssl_encrypt($counter_bin, 'aes-256-ecb', $key_bin, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
            }

            $passcode = "";
            $bit_buffer = 0;
            $bit_count = 0;
            $byte_index = 0;
            $cipher_len = strlen($ciphertext);

            for ($char_idx = 0; $char_idx < $cell_len; $char_idx++) {
                while ($bit_count < 6 && $byte_index < $cipher_len) {
                    $bit_buffer = ($bit_buffer << 8) | ord($ciphertext[$byte_index]);
                    $byte_index++;
                    $bit_count += 8;
                }
                if ($bit_count >= 6) {
                    $shift = $bit_count - 6;
                    $idx = ($bit_buffer >> $shift) & 0x3F;
                    $bit_count = $shift;
                    $passcode .= $alphabet[$idx];
                } else {
                    $passcode .= $alphabet[0];
                }
            }
            $passcodes[] = $passcode;
        }
        return $passcodes;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'ajax_generate_ppp') {
        header('Content-Type: application/json');
        $seq_key = trim($_GET['seq_key'] ?? '');
        $length = (int)($_GET['length'] ?? 30);
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $seq_key)) {
            echo json_encode(['success' => false, 'error' => 'Invalid sequence key']);
            exit();
        }
        $cell_len = (int)ceil($length / 5.0);
        $passcodes = generate_ppp_passcodes($seq_key, $cell_len);
        echo json_encode(['success' => true, 'passcodes' => $passcodes]);
        exit();
    }

    // 1. Handle Password Change (All Users)
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $old_pass = $_POST['old_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        $new_seq_key = trim($_POST['ppp_sequence_key'] ?? '');
        $ppp_row_index = (int)($_POST['ppp_row_index'] ?? 0);
        $user_id = $_SESSION['username'];

        $is_forced = (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true);
        $verified_old = false;

        if ($is_forced) {
            $verified_old = true;
        } else {
            $stmt = $conn_u->prepare("SELECT password, ppp_sequence_key FROM users WHERE username = ?");
            $stmt->execute([$user_id]);
            $user_row = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_hash = $user_row['password'] ?? '';
            $current_seq = $user_row['ppp_sequence_key'] ?? '';

            if ($current_hash) {
                if (!empty($current_seq)) {
                    $verified_old = password_verify($old_pass . $current_seq, $current_hash);
                }
                if (!$verified_old) {
                    $verified_old = password_verify($old_pass, $current_hash);
                }
            }
        }

        if ($verified_old) {
            if ($new_pass === $confirm_pass) {
                $bypass_ppp = isset($_POST['bypass_ppp']) && $_POST['bypass_ppp'] === '1';
                $min_len = $bypass_ppp ? 12 : 25;

                if (Security::validatePassword($new_pass, $error, $min_len)) {
                    if (!$bypass_ppp && !empty($new_seq_key)) {
                        if (!preg_match('/^[a-fA-F0-9]{64}$/', $new_seq_key)) {
                            $error = "Sequence key must be exactly 64 hexadecimal characters.";
                        }
                    }

                    if (!$error) {
                        $hash_password = $new_pass;
                        if ($bypass_ppp) {
                            $stmt_u = $conn_u->prepare("UPDATE users SET password = ?, ppp_sequence_key = '', ppp_row_index = 0, ppp_password_len = 0 WHERE username = ?");
                            $stmt_u->execute([password_hash($hash_password, PASSWORD_BCRYPT), $user_id]);
                        } else {
                            if (!empty($new_seq_key)) {
                                $seq_key = strtoupper($new_seq_key);
                                $hash_password .= $seq_key;
                                $stmt_u = $conn_u->prepare("UPDATE users SET password = ?, ppp_sequence_key = ?, ppp_row_index = ?, ppp_password_len = ? WHERE username = ?");
                                $stmt_u->execute([password_hash($hash_password, PASSWORD_BCRYPT), $seq_key, $ppp_row_index, strlen($new_pass), $user_id]);
                            } else {
                                $stmt_key = $conn_u->prepare("SELECT ppp_sequence_key FROM users WHERE username = ?");
                                $stmt_key->execute([$user_id]);
                                $existing_key = $stmt_key->fetchColumn();
                                if (!empty($existing_key)) {
                                    $hash_password .= $existing_key;
                                }
                                $stmt_u = $conn_u->prepare("UPDATE users SET password = ?, ppp_row_index = ?, ppp_password_len = ? WHERE username = ?");
                                $stmt_u->execute([password_hash($hash_password, PASSWORD_BCRYPT), $ppp_row_index, strlen($new_pass), $user_id]);
                            }
                        }
                        $_SESSION['settings_success_message'] = "Password and security settings updated successfully!";
                        $_SESSION['ppp_password_len'] = $bypass_ppp ? 0 : strlen($new_pass);
                        if (isset($_SESSION['force_password_change'])) {
                            unset($_SESSION['force_password_change']);
                        }
                        header("Location: index.php?view=settings");
                        exit();
                    }
                }
            } else {
                $error = "New passwords do not match.";
            }
        } else {
            $error = "Incorrect current password.";
        }
    }

    // 1b. Handle Signature / Display Name Update (All Users)
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_signature') {
        $display_name = trim($_POST['display_name'] ?? '');
        if ($display_name !== '') {
            $stmt_sig = $conn_u->prepare("UPDATE users SET display_name = ? WHERE username = ?");
            $stmt_sig->execute([$display_name, $_SESSION['username']]);
            $_SESSION['display_name'] = $display_name;
            $message = "Signature updated! Your name will appear on future invoices.";
        } else {
            $error = "Signature name cannot be empty.";
        }
    }

    // 2. Handle User Management (Admin Only)
    if ($_SESSION['username'] === 'admin') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add_user' && !empty($_POST['new_username'])) {
                $nu = trim($_POST['new_username']);
                $np = $_POST['new_password'];
                $nr = $_POST['new_role'] ?? 'Operator';

                if (empty($np) || strlen($np) < 3) {
                    $error = "Password must be at least 3 characters.";
                } else {
                    $hash = password_hash($np, PASSWORD_BCRYPT);
                    try {
                        $auth_add = $conn_u->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                        $auth_add->execute([$nu, $hash, $nr]);
                        $message = "New user '{$nu}' ({$nr}) added successfully!";
                    } catch(Exception $e) { $error = "Error: Username might already exist."; }
                }
            }

            if ($_POST['action'] === 'change_role' && !empty($_POST['target_user'])) {
                $tu = $_POST['target_user'];
                $tr = $_POST['target_role'];
                if ($tu !== 'admin') {
                    $stmt_role = $conn_u->prepare("UPDATE users SET role = ? WHERE username = ?");
                    $stmt_role->execute([$tr, $tu]);
                    $message = "User '{$tu}' permissions updated to {$tr}.";
                }
            }

            if ($_POST['action'] === 'delete_user' && !empty($_POST['del_username'])) {
                $du = $_POST['del_username'];
                if ($du !== 'admin') {
                    $auth_del = $conn_u->prepare("DELETE FROM users WHERE username = ?");
                    $auth_del->execute([$du]);
                    $message = "User '{$du}' removed.";
                }
            }
        }
    }
    // 3. Handle System Maintenance (Admin Only)
    if ($_SESSION['username'] === 'admin' && isset($_POST['action'])) {
        if ($_POST['action'] === 'cleanup_customers') {
            $db_cust_file = realpath(__DIR__ . '/../../db/customers.db');
            $db_orders_file = realpath(__DIR__ . '/../../db/orders.db');
            try {
                if (!$db_cust_file || !$db_orders_file) throw new Exception("Database files not found.");

                $conn_m = new PDO("sqlite:" . $db_cust_file);
                $conn_m->exec("ATTACH DATABASE '" . $db_orders_file . "' AS db_o");

                // Delete customers with no orders
                $sql_clean = "DELETE FROM customers WHERE customer_id NOT IN (SELECT DISTINCT customer_id FROM db_o.orders)";
                $stmt_clean = $conn_m->prepare($sql_clean);
                $stmt_clean->execute();
                $removed = $stmt_clean->rowCount();

                $message = "Cleanup complete! Removed {$removed} customer(s) with 0 orders.";
            } catch (Exception $e) { $error = "Cleanup failed: " . $e->getMessage(); }
        }

        if ($_POST['action'] === 'optimize_db') {
            try {
                $dbs = ['customers', 'orders', 'warehouse', 'users', 'calendar'];
                $optimized = 0;
                foreach ($dbs as $db) {
                    $pdo = Database::getConnection($db);
                    $pdo->exec("VACUUM");
                    $pdo->exec("ANALYZE");
                    $optimized++;
                }
                $message = "System performance optimized! Re-indexed {$optimized} core databases.";
            } catch (Exception $e) { $error = "Optimization failed: " . $e->getMessage(); }
        }

        if ($_POST['action'] === 'integrity_check') {
            require_once __DIR__ . '/../core/Schema.php';
            $report = Schema::repairAll();
            $fixed_count = count($report['fixed']);
            $err_count   = count($report['errors']);
            if ($err_count === 0) {
                $message = "✅ Integrity check complete. {$fixed_count} table(s) verified/repaired. No errors.";
            } else {
                $message = "⚠️ Integrity check done. {$fixed_count} table(s) OK. {$err_count} error(s): " . implode(' | ', $report['errors']);
            }
            $_SESSION['integrity_report'] = $report;
        }
    }
    $is_forced = (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true);
} catch (PDOException $e) { $error = "Database error: " . $e->getMessage(); }

?>

<div class="settings-page-wrapper" style="width: 100%; display: flex; flex-direction: column; align-items: center; gap: 40px; padding-bottom: 60px;">
    <style>
        .settings-card {
            background: white;
            width: 100%;
            max-width: 500px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            margin-top: 20px;
        }
        .settings-header { margin-bottom: 30px; }
        .settings-header h1 { font-size: 1.4rem; margin-bottom: 8px; }
        .status-msg { padding: 12px; border-radius: 12px; font-size: 0.85rem; font-weight: 700; margin-bottom: 20px; text-align: center; }
        .msg-success { background: #f0fdf4; color: #166534; border: 1px solid #dcfce7; }
        .msg-error { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; }

        .user-list { list-style: none; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 20px; }
        .user-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-radius: 8px; background: #f8fafc; margin-bottom: 8px; }
        .user-name { font-weight: 700; font-size: 0.9rem; color: var(--text-main); }
        .btn-delete-small { background: #fee2e2; color: #b91c1c; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 800; }
    </style>

    <!-- Global System Feedback -->
    <?php if (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] === true): ?>
        <div class="status-msg msg-error" style="width:100%; max-width:500px; margin-top:20px; background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5;">
            ⚠️ <strong>Security Warning:</strong> You are using default credentials. You must change your password to secure the system.
        </div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="status-msg msg-success" style="width:100%; max-width:500px; margin-top:20px;"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="status-msg msg-error" style="width:100%; max-width:500px; margin-top:20px;"><?= $error ?></div>
    <?php endif; ?>

    <!-- 0. APPEARANCE CARD -->
    <div class="settings-card">
        <div class="settings-header">
             <a href="core/logout.php" style="float:right;text-decoration: none; background: #fef2f2; color: #991b1b; padding: 10px 16px; border-radius: 10px; font-size: 0.8rem; font-weight: 800; border: 1px solid #fee2e2;">
                    🚪 Sign Out
                </a>
            <h1>Appearance</h1>
            <p class="subtitle">Customize the look and feel of the application.</p>
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 16px 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
            <div>
                <strong style="display: block; color: var(--text-main); margin-bottom: 4px;">Dark Mode</strong>
                <span style="font-size: 0.85rem; color: var(--text-secondary);">Toggle between light and dark themes.</span>
            </div>
            <?= UI::theme_toggle() ?>
        </div>
    </div>

    <!-- SALES DATA IMPORT CARD -->
    <?php if ($_SESSION['role'] === 'Admin'): ?>
    <div class="settings-card">
        <div class="settings-header">
            <h1>📥 Sales Data Importer</h1>
            <p class="subtitle">Migrate sales records from root .xlsx spreadsheets directly into customers and completed orders.</p>
        </div>
        <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 20px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="font-size: 2rem;">📥</div>
                <div>
                    <strong style="display: block; color: var(--text-main); font-size: 0.95rem;">Spreadsheet Data Migration</strong>
                    <span style="font-size: 0.8rem; color: var(--text-secondary); line-height: 1.4; display: block; margin-top: 2px;">Scan, review, and import individual sheets from root sales spreadsheets.</span>
                </div>
            </div>
            <a href="index.php?view=import_sales" class="btn-main" style="background: var(--accent-color); color: white; padding: 12px 20px; font-size: 0.85rem; white-space: nowrap; text-decoration: none; border-radius: 10px; font-weight: 800; display: inline-block;">
                Go to Importer
            </a>
        </div>
        
        <!-- Collapsible Formatting Guide -->
        <div style="margin-top: 20px; border-top: 1px dashed #e2e8f0; padding-top: 15px;">
            <button type="button" onclick="const gd = document.getElementById('import-guide-details'); gd.style.display = gd.style.display === 'none' ? 'block' : 'none';" style="background: none; border: none; color: var(--accent-color); font-weight: 800; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 6px; padding: 0;">
                📖 View Spreadsheet Layout Formatting Guide
            </button>
            <div id="import-guide-details" style="display: none; margin-top: 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; font-size: 0.85rem; line-height: 1.6; color: #475569;">
                <h3 style="margin-top: 0; color: var(--text-main); font-weight: 800; font-size: 0.95rem; display: flex; align-items: center; gap: 6px;">📋 Excel Data Structure Rules</h3>
                <p style="margin-bottom: 15px;">To ensure automatic tab imports succeed, each sheet in the spreadsheet must adhere to the following cell rules:</p>
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 0.8rem; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid #cbd5e1; font-weight: 800; color: var(--text-main);">
                            <th style="padding: 6px 0; width: 120px;">Cell Reference</th>
                            <th style="padding: 6px 0; width: 150px;">Expected Field</th>
                            <th style="padding: 6px 0;">Rule / Validation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 8px 0; font-family: monospace; font-weight: 800; color: var(--accent-color);">B3</td>
                            <td style="padding: 8px 0; font-weight: 700;">Customer / Company</td>
                            <td style="padding: 8px 0;">Must not be empty. Matches existing customer by company name, or creates a new profile.</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 8px 0; font-family: monospace; font-weight: 800; color: var(--accent-color);">B4</td>
                            <td style="padding: 8px 0; font-weight: 700;">Order Date</td>
                            <td style="padding: 8px 0;">Must be a valid Excel numeric date serial value (dates before year 2000 are blocked).</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 8px 0; font-family: monospace; font-weight: 800; color: var(--accent-color);">B5</td>
                            <td style="padding: 8px 0; font-weight: 700;">Order Number</td>
                            <td style="padding: 8px 0;">Must not be empty. Will be prefixed with <code>ORD-</code> to create the system Order ID.</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 8px 0; font-family: monospace; font-weight: 800; color: var(--accent-color);">Row 11+</td>
                            <td style="padding: 8px 0; font-weight: 700;">Inventory Items List</td>
                            <td style="padding: 8px 0;">Items must have a Type (Col A), Brand (Col B), QTY (Col G), and Unit Price (Col F). At least 1 item is required.</td>
                        </tr>
                    </tbody>
                </table>
                <div style="background: #fffbe5; border: 1px solid #fde68a; border-radius: 8px; padding: 12px; font-size: 0.78rem; color: #92400e;">
                    <strong>💡 Validation Note:</strong> Tabs that fail any of the above rules will show a detailed layout error list in the sales importer and will be blocked from import until corrected in the Excel file.
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- UNIFIED PASSWORD UPDATE FORM -->
    <form method="POST" id="password-update-form" style="width: 100%; display: flex; flex-direction: column; align-items: center; gap: 40px;">
        <?= UI::csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="ppp_sequence_key" id="ppp_sequence_key_input" value="<?= htmlspecialchars($seq_key) ?>">
        <input type="hidden" name="ppp_row_index" id="ppp_row_index_input" value="<?= $saved_row_index ?>">

        <!-- Perfect Paper Passwords (PPP) Card (SHOWN FIRST) -->
        <div class="settings-card" id="ppp-card" style="max-width: 600px; width: 100%;">
            <div class="settings-header multi-link-container" style="position: relative;">
                <h1>🔑 Perfect Paper Passwords (PPP)</h1>
                <p class="subtitle">Your offline, ultra-secure one-time passcode system from
                    <span class="linked-text-info" style="color: #4f46e5; text-decoration: underline; font-weight: bold; cursor: pointer;">GRC</span>.
                </p>
                <!-- PPP Information Dialog -->
                <div class="info-dialog" style="max-width: 500px; width: 90%;">
                    <button type="button" class="btn-close-dialog" aria-label="Close dialog">&times;</button>
                    <div style="padding: 10px; text-align: left; line-height: 1.6; font-family: system-ui, -apple-system, sans-serif;">
                        <h2 style="font-size: 1.3rem; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">🔑 How PPP Works</h2>
                        <p style="font-size: 0.9rem; color: #475569; margin-bottom: 12px;">
                            <strong>Perfect Paper Passwords (PPP)</strong> is an offline, paper-based multi-factor authentication (MFA) system designed by Steve Gibson of Gibson Research Corporation (GRC).
                        </p>
                        <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b; margin-bottom: 8px;">Security Instructions:</h3>
                        <ul style="font-size: 0.85rem; color: #475569; padding-left: 20px; margin-bottom: 16px;">
                            <li style="margin-bottom: 6px;"><strong>Print the Card:</strong> Click the "Print Secure Passcard" button below and print a physical copy. Keep it safely in your wallet.</li>
                            <li style="margin-bottom: 6px;"><strong>Passcode Grid:</strong> The card contains a grid of 50 unique passcodes indexed from Row 01 to 10 and Columns A to E.</li>
                            <li style="margin-bottom: 6px;"><strong>Authentication:</strong> When signing in, the system will ask you to enter a passcode from a specific cell (e.g. <code>03-B</code>). Find that cell on your printed card and type the characters.</li>
                            <li style="margin-bottom: 6px;"><strong>One-Time Use:</strong> Once you use a passcode, you are in. (<i>If you log out and do not know your password, please tell the Admin ASAP</i>)</li>
                            <li style="margin-bottom: 6px;"><strong>No Secrets Stored Online:</strong> The server only stores a master Sequence Key, never the passcodes themselves.</li>
                        </ul>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; font-size: 0.8rem; color: #64748b; font-style: italic;">
                            Tip: If your physical card is lost or compromised, change your password and automatically generate a brand new card.<br><br>
                            <strong>Important Note:</strong> To reprint or regenerate your original passcard, you MUST keep a backup of both your 64-character <strong style="color: black">Sequence Key</strong> and the corresponding <strong style="color: black">Password Length</strong> (e.g., 30).
                        </div>
                    </div>
                </div>
            </div>

            <!-- Length Range Controls (Only shown in PPP card if password change is forced) -->
            <?php if ($is_forced): ?>
                <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 24px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px; align-items: center;">
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Password Length Range</label>
                        <input type="number" id="ppp_length_input" name="ppp_length" value="<?= $saved_pass_len ?>" min="25" max="80" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: bold; text-align: center;" onchange="onLengthChange()">
                        <span style="font-size: 0.7rem; color: #64748b; margin-top: 4px; display: block; line-height: 1.3;">
                            Recommended: 25-50. Higher is more secure.
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 24px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Sequence Key</label>
                    <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                        <input type="text" value="<?= htmlspecialchars($seq_key) ?>" placeholder="Generate a key to start..." readonly style="font-family: monospace; font-size: 0.75rem; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px; flex: 1; text-align: center;" id="ppp_display_key">
                        <button type="button" onclick="copySequenceKey()" style="background: #e2e8f0; color: #475569; border: none; padding: 0 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer; height: 34px;">📋</button>
                    </div>
                    <?php if ($is_forced): ?>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <button type="button" class="btn-main" onclick="triggerGenKey()" style="background:#64748b; color:white; white-space:nowrap; padding: 0 16px; font-size:0.85rem; border-radius:10px; border:none; cursor:pointer; height:38px; font-weight:800; display: flex; align-items: center; gap: 6px;">🎲 Gen Key</button>

                            <div class="multi-link-container" style="position: relative;">
                                <button type="button" class="btn-main linked-text-info" style="background:#4f46e5; color:white; white-space:nowrap; padding: 0 16px; font-size:0.85rem; border-radius:10px; border:none; cursor:pointer; height:38px; font-weight:800; display: flex; align-items: center; gap: 6px;">🔍 Verify & Load Key</button>

                                <!-- Input Sequence Key Dialog -->
                                <div class="info-dialog" id="dialog_seq_key" style="max-width: 500px; width: 90%;">
                                    <button type="button" class="btn-close-dialog" aria-label="Close dialog">&times;</button>
                                    <div style="padding: 10px; text-align: left; line-height: 1.6; font-family: system-ui, -apple-system, sans-serif;">
                                        <h2 style="font-size: 1.3rem; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">🔑 Load Sequence Key</h2>
                                        <p style="font-size: 0.9rem; color: #475569; margin-bottom: 12px;">
                                            Enter an existing 64-character hexadecimal Sequence Key to generate and preview its passcard grid.<br><br>
                                            <strong>Note:</strong> You must also match the exact <em>Password Length Range</em> that was configured when generating the key to reprint/regenerate the original passcard cells.
                                        </p>
                                        <div class="form-group" style="margin-bottom: 20px;">
                                            <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Sequence Key</label>
                                            <input type="text" id="manual_seq_key_input_forced" placeholder="e.g. 1DBED7E3..." style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: monospace; font-size: 0.85rem; box-sizing: border-box;" oninput="onManualKeyInput(this.value)">
                                            <span id="manual_key_error_forced" style="font-size: 0.75rem; color: #ef4444; margin-top: 4px; display: none;">Key must be exactly 64 hexadecimal characters.</span>
                                        </div>
                                        <button type="button" class="btn-main" onclick="applyManualKey(true)" style="width: 100%; padding: 12px; border-radius: 8px; background: var(--text-main); color: white; border: none; font-weight: 800; cursor: pointer;">
                                            Load Grid
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="qr-container-wrapper" class="multi-link-container" style="display: <?= empty($seq_key) ? 'none' : 'flex' ?>; flex-direction: column; align-items: center; justify-content: center; background: white; padding: 12px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <span class="linked-text-img" title="Click to enlarge QR Code" id="qr-clickable-zone">
                        <img id="ppp_qr_img" src="<?= !empty($seq_key) ? 'https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=' . urlencode($seq_key) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' ?>" alt="PPP QR Code" style="width: 110px; height: 110px; border-radius: 8px; display: block;padding:10%;">
                    </span>
                    <span style="font-size: 0.65rem; color: #64748b; font-weight: 800; margin-top: 6px; text-transform: uppercase;">Sequence QR Code</span>

                    <!-- Image Dialog Modal -->
                    <div class="image-dialog" id="qr-modal-dialog">
                        <button type="button" class="btn-close-dialog" aria-label="Close dialog">&times;</button>
                        <figure>
                            <img id="ppp_qr_large_img" src="<?= !empty($seq_key) ? 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($seq_key) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' ?>" alt="Enlarged PPP QR Code" style="padding: 10%;">
                            <figcaption id="ppp_qr_caption"><a href="#" onclick="printQRCode(); return false;"><span style="font-family: monospace; font-size: 0.8rem; word-break: break-all; margin-top: 5px; display: block;">Sequence Key:</a><br><span id="ppp_qr_caption_key"><?= htmlspecialchars($seq_key) ?></span></span></figcaption>
                        </figure>
                    </div>
                </div>
            </div>

            <!-- Table and Grid Preview -->
            <div id="ppp-grid-section" style="border-top: 1px dashed var(--border-color); padding-top: 24px; margin-top: 20px; display: <?= empty($seq_key) ? 'none' : 'block' ?>;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                    <h3 style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin: 0;">Live Passcard Grid Preview</h3>

                    <label id="ppp_show_active_container" style="display: none; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; color: var(--text-main); cursor: pointer; user-select: none; margin: 0;">
                        <input type="checkbox" id="ppp_show_active_checkbox" onchange="toggleShowActive()" style="width: 16px; height: 16px;">
                        Show active password in grid preview
                    </label>
                </div>

                <div style="max-height: 250px; overflow: auto; border: 1px solid #e2e8f0; border-radius: 12px; background: white; margin-bottom: 20px; position: relative;">
                    <table style="width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8rem; text-align: center; font-family: monospace; table-layout: fixed;">
                        <thead>
                            <tr>
                                <th style="color: #475569; font-weight: bold; padding: 12px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; width: 60px; position: sticky; top: 0; background: #f1f5f9; z-index: 10;">Row</th>
                                <th style="color: #475569; font-weight: bold; padding: 12px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; position: sticky; top: 0; background: #f1f5f9; z-index: 10;">A</th>
                                <th style="color: #475569; font-weight: bold; padding: 12px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; position: sticky; top: 0; background: #f1f5f9; z-index: 10;">B</th>
                                <th style="color: #475569; font-weight: bold; padding: 12px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; position: sticky; top: 0; background: #f1f5f9; z-index: 10;">C</th>
                                <th style="color: #475569; font-weight: bold; padding: 12px 4px; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; position: sticky; top: 0; background: #f1f5f9; z-index: 10;">D</th>
                                <th style="color: #475569; font-weight: bold; padding: 12px 4px; border-bottom: 2px solid #cbd5e1; position: sticky; top: 0; background: #f1f5f9; z-index: 10;">E</th>
                            </tr>
                        </thead>
                        <tbody id="ppp-grid-tbody">
                            <?php
                            $cell_len = (int)ceil($saved_pass_len / 5.0);
                            $actual_codes = !empty($seq_key) ? generate_ppp_passcodes($seq_key, $cell_len) : [];

                            for ($r = 0; $r < 25; $r++) {
                                $row_num = sprintf('%02d', $r + 1);
                                $is_saved_row = ($saved_row_index === ($r + 1));
                                $bg = $is_saved_row ? '#e0f2fe' : (($r % 2 === 0) ? '#f8fafc' : '#ffffff');
                                $padding_top = ($r === 0) ? '14px' : '10px';

                                echo "<tr data-row-num='" . ($r + 1) . "' style='background: {$bg}; cursor: pointer;' onclick='onRowClick(this, " . ($r + 1) . ")'>";
                                echo "<td style='padding: {$padding_top} 4px 10px 4px; font-weight: bold; color: #64748b; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; width: 60px;'>{$row_num}</td>";

                                for ($c = 0; $c < 5; $c++) {
                                    $code_val = !empty($actual_codes) ? $actual_codes[$r * 5 + $c] : '';
                                    $border_right = ($c < 4) ? 'border-right: 1px solid #e2e8f0;' : '';
                                    echo "<td class='ppp-cell' style='padding: {$padding_top} 4px 10px 4px; font-weight: bold; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; {$border_right} word-break: break-all;'>" . htmlspecialchars($code_val) . "</td>";
                                }
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="button" onclick="printPPPCard()" class="btn-main" style="flex: 1; padding: 14px; border-radius: 12px; background: linear-gradient(135deg, #7c3aed, #4f46e5); color: white; border: none; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; height: 50px;">
                        🖨️ Print Passcard
                    </button>
                    <button type="button" onclick="viewPPPCard()" class="btn-main" style="flex: 1; padding: 14px; border-radius: 12px; background: #64748b; color: white; border: none; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; height: 50px;">
                        📄 View / Save Passcard
                    </button>
                </div>
            </div>
        </div>

        <!-- Account Security Card (SHOWN SECOND) -->
        <div class="settings-card">
            <div class="settings-header" style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <h1>Account Security</h1>
                    <p class="subtitle">Update your password to keep your account secure.</p>
                </div>

            </div>

            <?php if (!$is_forced): ?>
                <!-- Password Length Range & Gen Key relocated here when logged in securely -->
                <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 24px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 16px; align-items: center;">
                    <div style="flex: 1; min-width: 150px;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Password Length Range</label>
                        <input type="number" id="ppp_length_input" name="ppp_length" value="<?= $saved_pass_len ?>" min="25" max="80" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: bold; text-align: center;" onchange="onLengthChange()">
                        <span style="font-size: 0.7rem; color: #64748b; margin-top: 4px; display: block; line-height: 1.3;">
                            Recommended: 25-50. Higher is more secure.
                        </span>
                    </div>
                    <div style="flex: 1; min-width: 150px; display: flex; flex-direction: column; align-items: flex-start; justify-content: center;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Perfect Paper Passcode</label>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <button type="button" class="btn-main" onclick="triggerGenKey()" style="background:#64748b; color:white; white-space:nowrap; padding: 0 16px; font-size:0.85rem; border-radius:10px; border:none; cursor:pointer; height:38px; font-weight:800; display: flex; align-items: center; gap: 6px;">
                                🎲 Gen Key
                            </button>

                            <div class="multi-link-container" style="position: relative;">
                                <button type="button" class="btn-main linked-text-info" style="background:#4f46e5; color:white; white-space:nowrap; padding: 0 16px; font-size:0.85rem; border-radius:10px; border:none; cursor:pointer; height:38px; font-weight:800; display: flex; align-items: center; gap: 6px;">🔍 Verify & Load Key</button>

                                <!-- Input Sequence Key Dialog -->
                                <div class="info-dialog" id="dialog_seq_key_secure" style="max-width: 500px; width: 90%;">
                                    <button type="button" class="btn-close-dialog" aria-label="Close dialog">&times;</button>
                                    <div style="padding: 10px; text-align: left; line-height: 1.6; font-family: system-ui, -apple-system, sans-serif;">
                                        <h2 style="font-size: 1.3rem; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">🔑 Load Sequence Key</h2>
                                        <p style="font-size: 0.9rem; color: #475569; margin-bottom: 12px;">
                                            Enter an existing 64-character hexadecimal Sequence Key to generate and preview its passcard grid.<br><br>
                                            <strong>Note:</strong> You must also match the exact <em>Password Length Range</em> that was configured when generating the key to reprint/regenerate the original passcard cells.
                                        </p>
                                        <div class="form-group" style="margin-bottom: 20px;">
                                            <label style="display: block; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 8px;">Sequence Key</label>
                                            <input type="text" id="manual_seq_key_input_secure" placeholder="e.g. 1DBED7E3..." style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: monospace; font-size: 0.85rem; box-sizing: border-box;" oninput="onManualKeyInput(this.value)">
                                            <span id="manual_key_error_secure" style="font-size: 0.75rem; color: #ef4444; margin-top: 4px; display: none;">Key must be exactly 64 hexadecimal characters.</span>
                                        </div>
                                        <button type="button" class="btn-main" onclick="applyManualKey(false)" style="width: 100%; padding: 12px; border-radius: 8px; background: var(--text-main); color: white; border: none; font-weight: 800; cursor: pointer;">
                                            Load Grid
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="old_password">Current Password</label>
                    <input type="password" id="old_password" name="old_password" placeholder="••••••••" required>
                </div>
            <?php endif; ?>

            <div style="border-top: 1px dashed var(--border-color); padding-top: 20px; margin-top: 20px;">
                <!-- Bypass PPP Checkbox and Warnings -->
                <div style="margin-bottom: 20px; background: #fffbeb; border: 1px solid #fde68a; padding: 15px; border-radius: 12px; box-sizing: border-box;">
                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; user-select: none; text-transform: none; color: #b45309; font-weight: bold; font-size: 0.9rem; line-height: 1.4; margin-bottom: 0;">
                        <input type="checkbox" name="bypass_ppp" id="bypass_ppp" value="1" onchange="toggleBypassPPP(this.checked)" style="width: 18px; height: 18px; margin: 0; margin-top: 2px;" <?= (isset($_POST['bypass_ppp']) && $_POST['bypass_ppp'] === '1') ? 'checked' : '' ?>>
                        <span>Bypass Perfect Paper Passwords (PPP) grid and set a custom password</span>
                    </label>
                    <div id="ppp-bypass-warning" style="display: none; margin-top: 10px; font-size: 0.8rem; color: #b45309; line-height: 1.4; border-top: 1px dashed #fcd34d; padding-top: 8px;">
                        ⚠️ <strong>Security Warning:</strong> Bypassing the PPP system allows you to use a custom password. Custom passwords are significantly more vulnerable to keylogging, guessing, and database leaks than GRC's pseudo-random high-entropy passcodes. Make sure to choose a strong password containing letters, numbers, and symbols.
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Min 24 chars, complex (A-Z, a-z, 0-9, symbol)" <?= $is_forced ? 'readonly' : '' ?> required>
                    <!-- Password Strength Meter -->
                    <div id="strength-meter-container" style="margin-top: 8px; display: none; flex-direction: column; gap: 5px;">
                        <div style="background: #e2e8f0; height: 6px; width: 100%; border-radius: 3px; overflow: hidden;">
                            <div id="strength-meter-bar" style="height: 100%; width: 0%; transition: width 0.3s ease, background-color 0.3s ease;"></div>
                        </div>
                        <span id="strength-meter-text" style="font-size: 0.75rem; font-weight: bold;"></span>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 30px;">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                </div>
            </div>
            <button type="submit" class="btn-main" style="width: 100%; padding: 16px; border-radius: 12px; background: var(--text-main); color: white; border: none; font-weight: 800; cursor: pointer;">
                💾 Update Password
            </button>
        </div>
    </form>

    <script>
    let activeSeqKey = "<?= htmlspecialchars($seq_key) ?>";
    let pendingSeqKey = "";
    let selectedRowIdx = <?= $saved_row_index ?>;

    function onManualKeyInput(val) {
        const isValid = /^[a-fA-F0-9]{64}$/.test(val.trim());
        const errForced = document.getElementById('manual_key_error_forced');
        const errSecure = document.getElementById('manual_key_error_secure');

        if (errForced) {
            errForced.style.display = (val.trim() === '' || isValid) ? 'none' : 'block';
        }
        if (errSecure) {
            errSecure.style.display = (val.trim() === '' || isValid) ? 'none' : 'block';
        }
    }

    function applyManualKey(isForced) {
        const inputId = isForced ? 'manual_seq_key_input_forced' : 'manual_seq_key_input_secure';
        const inputEl = document.getElementById(inputId);
        if (!inputEl) return;

        const rawVal = inputEl.value.trim();
        if (!/^[a-fA-F0-9]{64}$/.test(rawVal)) {
            alert("Please enter a valid 64-character hexadecimal Sequence Key first.");
            return;
        }

        pendingSeqKey = rawVal.toUpperCase();

        // Update display key text field and hidden inputs
        const displayKeyInput = document.getElementById('ppp_display_key');
        if (displayKeyInput) {
            displayKeyInput.value = pendingSeqKey;
        }
        document.getElementById('ppp_sequence_key_input').value = pendingSeqKey;

        // Reset selected row
        selectedRowIdx = 0;
        document.getElementById('ppp_row_index_input').value = '0';

        // Show QR and grid preview if they are hidden
        const qrWrapper = document.getElementById('qr-container-wrapper');
        if (qrWrapper) qrWrapper.style.display = 'flex';
        const gridSection = document.getElementById('ppp-grid-section');
        if (gridSection) gridSection.style.display = 'block';

        // Update QR images
        const encodedKey = encodeURIComponent(pendingSeqKey);
        const qrImg = document.getElementById('ppp_qr_img');
        if (qrImg) {
            qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encodedKey}`;
        }
        const qrLargeImg = document.getElementById('ppp_qr_large_img');
        if (qrLargeImg) {
            qrLargeImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodedKey}`;
        }

        // Uncheck show-active checkbox
        const showActiveCheckbox = document.getElementById('ppp_show_active_checkbox');
        if (showActiveCheckbox) {
            showActiveCheckbox.checked = false;
        }
        const showActiveContainer = document.getElementById('ppp_show_active_container');
        if (showActiveContainer) {
            showActiveContainer.style.display = 'flex';
        }

        // Fetch new grid preview
        fetchGridPreview(pendingSeqKey);

        // Close modal
        if (window.dialogEngine) {
            window.dialogEngine.closeAnyOpenDialogs();
        }

        // Scroll to PPP grid
        const pppCard = document.getElementById('ppp-card');
        if (pppCard) {
            pppCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function generateRandomHexKey() {
        const chars = '0123456789ABCDEF';
        let result = '';
        for (let i = 0; i < 64; i++) {
            result += chars[Math.floor(Math.random() * 16)];
        }
        return result;
    }

    function triggerGenKey() {
        pendingSeqKey = generateRandomHexKey();
        document.getElementById('ppp_display_key').value = pendingSeqKey;
        document.getElementById('ppp_sequence_key_input').value = pendingSeqKey;

        // Show QR container and grid if hidden
        document.getElementById('qr-container-wrapper').style.display = 'flex';
        document.getElementById('ppp-grid-section').style.display = 'block';

        // Update QR images
        const encodedKey = encodeURIComponent(pendingSeqKey);
        document.getElementById('ppp_qr_img').src = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encodedKey}`;
        document.getElementById('ppp_qr_large_img').src = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodedKey}`;

        // When a new key is generated, we reset the selected row so the user must pick one
        selectedRowIdx = 0;
        document.getElementById('ppp_row_index_input').value = '0';

        // Set the checkbox to unchecked (we show the pending key grid by default)
        const showActiveCheckbox = document.getElementById('ppp_show_active_checkbox');
        if (showActiveCheckbox) {
            showActiveCheckbox.checked = false;
        }

        // Show the show-active checkbox container
        const showActiveContainer = document.getElementById('ppp_show_active_container');
        if (showActiveContainer) {
            showActiveContainer.style.display = 'flex';
        }

        // Render grid for the pending key
        fetchGridPreview(pendingSeqKey);

        // Send them to the PPP card view
        const pppCard = document.getElementById('ppp-card');
        if (pppCard) {
            pppCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    async function fetchGridPreview(seqKey) {
        const lengthInput = document.getElementById('ppp_length_input');
        const length = lengthInput ? (parseInt(lengthInput.value) || 30) : 30;

        try {
            const response = await fetch(`index.php?view=settings&action=ajax_generate_ppp&seq_key=${seqKey}&length=${length}`);
            const data = await response.json();
            if (data.success) {
                renderGrid(data.passcodes, seqKey);
            }
        } catch(e) {
            console.error("Failed to load passcodes preview:", e);
        }
    }

    function renderGrid(passcodes, seqKey) {
        const tbody = document.getElementById('ppp-grid-tbody');
        tbody.innerHTML = '';

        const checkbox = document.getElementById('ppp_show_active_checkbox');
        const showActive = checkbox && checkbox.checked;
        const currentSelectedIdx = showActive ? <?= $saved_row_index ?> : selectedRowIdx;

        for (let r = 0; r < 25; r++) {
            const rowNum = r + 1;
            const isSelected = (currentSelectedIdx === rowNum);
            const rowLabel = String(rowNum).padStart(2, '0');

            const tr = document.createElement('tr');
            tr.setAttribute('data-row-num', rowNum);
            tr.style.cursor = 'pointer';
            tr.style.background = isSelected ? '#e0f2fe' : ((r % 2 === 0) ? '#f8fafc' : '#ffffff');
            tr.onclick = function() { onRowClick(this, rowNum); };

            let tdRow = document.createElement('td');
            tdRow.style.padding = (r === 0) ? '14px 4px 10px 4px' : '10px 4px 10px 4px';
            tdRow.style.fontWeight = 'bold';
            tdRow.style.color = '#64748b';
            tdRow.style.borderRight = '1px solid #e2e8f0';
            tdRow.style.borderBottom = '1px solid #e2e8f0';
            tdRow.style.width = '60px';
            tdRow.innerText = rowLabel;
            tr.appendChild(tdRow);

            for (let c = 0; c < 5; c++) {
                let tdCell = document.createElement('td');
                tdCell.className = 'ppp-cell';
                tdCell.style.padding = (r === 0) ? '14px 4px 10px 4px' : '10px 4px 10px 4px';
                tdCell.style.fontWeight = 'bold';
                tdCell.style.letterSpacing = '0.5px';
                tdCell.style.borderBottom = '1px solid #e2e8f0';
                if (c < 4) tdCell.style.borderRight = '1px solid #e2e8f0';
                tdCell.style.wordBreak = 'break-all';

                tdCell.innerText = passcodes[r * 5 + c];
                tr.appendChild(tdCell);
            }
            tbody.appendChild(tr);
        }

        // Update Sequence Key display text and QR codes to match the current grid's key
        const displayKeyInput = document.getElementById('ppp_display_key');
        if (displayKeyInput) {
            displayKeyInput.value = seqKey;
        }
        const encodedKey = encodeURIComponent(seqKey);
        const qrImg = document.getElementById('ppp_qr_img');
        if (qrImg) {
            qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encodedKey}`;
        }
        const qrLargeImg = document.getElementById('ppp_qr_large_img');
        if (qrLargeImg) {
            qrLargeImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodedKey}`;
        }
        const qrCaptionKey = document.getElementById('ppp_qr_caption_key');
        if (qrCaptionKey) {
            qrCaptionKey.innerText = seqKey;
        }

        const footerLengthSpan = document.getElementById('ppp-card-footer-length');
        if (footerLengthSpan) {
            footerLengthSpan.innerText = length;
        }

        updatePrintCardSource(passcodes, seqKey);
    }

    function updatePrintCardSource(passcodes, seqKey) {
        const source = document.getElementById('ppp-printable-card-source');
        if (!source) return;

        const lengthInput = document.getElementById('ppp_length_input');
        const length = lengthInput ? (parseInt(lengthInput.value) || 30) : 30;

        let tableRowsHtml = '';
        for (let r = 0; r < 25; r++) {
            const rowLabel = String(r + 1).padStart(2, '0');
            let cellsHtml = '';
            for (let c = 0; c < 5; c++) {
                cellsHtml += `<td style='padding: 5px 3px; border: 1px solid #ccc; font-weight: bold; letter-spacing: 0.5px; white-space: nowrap;'>${passcodes[r * 5 + c]}</td>`;
            }
            tableRowsHtml += `<tr>
                <td style='padding: 5px 3px; border: 1px solid #ccc; font-weight: bold; background: #fafafa; white-space: nowrap;'>${rowLabel}</td>
                ${cellsHtml}
            </tr>`;
        }

        source.innerHTML = `
            <div style="border: 2px dashed #333; border-radius: 12px; padding: 20px; max-width: 100%; width: 100%; box-sizing: border-box; background: white; color: black; font-family: 'Courier New', Courier, monospace; box-shadow: 0 4px 10px rgba(0,0,0,0.15); margin: 20px auto;">
                <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 15px;">
                    <strong style="font-size: 16px; letter-spacing: 1px;">PERFECT PAPER PASSCARD</strong>
                    <span style="font-size: 14px; font-weight: bold;">User: <?= htmlspecialchars($username) ?></span>
                </div>
                <div style="font-size: 10px; margin-bottom: 15px; word-break: break-all; border: 1px solid #ddd; padding: 8px; background: #f9f9f9; border-radius: 6px;">
                    <strong>SEQUENCE KEY:</strong><br>${seqKey}
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px; text-align: center; table-layout: auto;">
                    <thead>
                        <tr style="border-bottom: 2px solid #000; background: #eee;">
                            <th style="padding: 5px 3px; border: 1px solid #ccc; width: 50px;">Row</th>
                            <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">A</th>
                            <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">B</th>
                            <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">C</th>
                            <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">D</th>
                            <th style="padding: 5px 3px; border: 1px solid #ccc; font-weight: bold;">E</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${tableRowsHtml}
                    </tbody>
                </table>
                <div style="margin-top: 15px; text-align: center; font-size: 9px; color: #666; border-top: 1px solid #eee; padding-top: 8px;">
                    GRC Perfect Paper Passwords &bull; Password Length: ${length} &bull; Keep this card secure and offline.
                </div>
            </div>
        `;
    }

    function printPPPCard() {
        const source = document.getElementById('ppp-printable-card-source');
        if (!source) return;
        const printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>Print PPP Passcard</title></head><body style="margin:20px;">' + source.innerHTML + '</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }

    function printQRCode() {
        const qrImgSrc = document.getElementById('ppp_qr_large_img').src;
        const key = document.getElementById('ppp_qr_caption_key').innerText;
        const printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>Print QR Code</title><style>body{display:flex;flex-direction:column;align-items:center;justify-content:center;height:90vh;margin:0;font-family:monospace;text-align:center;}img{max-width:300px;margin-bottom:20px;}.key{font-size:1.2rem;word-break:break-all;max-width:600px;}</style></head><body><img src="' + qrImgSrc + '" alt="QR Code"><div class="key"><strong>Sequence Key:</strong><br>' + key + '</div></body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }

    function viewPPPCard() {
        const source = document.getElementById('ppp-printable-card-source');
        if (!source) return;
        const viewWindow = window.open('', '_blank');
        viewWindow.document.write('<html><head><title>PPP Passcard</title></head><body style="margin:20px;">' + source.innerHTML + '</body></html>');
        viewWindow.document.close();
        viewWindow.focus();
    }

    async function onRowClick(rowElement, rowNum) {
        const checkbox = document.getElementById('ppp_show_active_checkbox');
        const showActive = checkbox && checkbox.checked;

        let targetKey = showActive ? activeSeqKey : (pendingSeqKey ? pendingSeqKey : activeSeqKey);
        if (!targetKey) {
            alert("Please generate a sequence key first!");
            return;
        }

        const currentSelectedIdx = showActive ? <?= $saved_row_index ?> : selectedRowIdx;

        // If the clicked row is already selected, copy its passcode to clipboard and confirm it
        if (rowNum === currentSelectedIdx) {
            const cells = rowElement.querySelectorAll('.ppp-cell');
            let passcodeStr = '';
            cells.forEach(c => passcodeStr += c.innerText);

            const confirmInput = document.getElementById('confirm_password');
            if (confirmInput) {
                confirmInput.value = passcodeStr;
            }

            navigator.clipboard.writeText(passcodeStr).then(() => {
                alert(`Row ${String(rowNum).padStart(2, '0')} passcode copied to clipboard and confirmed!`);
            }).catch(() => {
                alert(`Row ${String(rowNum).padStart(2, '0')} passcode confirmed!`);
            });
            return;
        }

        const newPasswordInput = document.getElementById('new_password');
        if (newPasswordInput && newPasswordInput.value && newPasswordInput.value.trim() !== '') {
            if (rowNum !== currentSelectedIdx) {
                const proceed = confirm("You have already entered a password. Are you sure you want to change it to the passcodes in Row " + String(rowNum).padStart(2, '0') + "?");
                if (!proceed) {
                    return;
                }
            }
        }

        if (showActive) {
            checkbox.checked = false;
            pendingSeqKey = ""; // Discard pending key since they chose a row from the active grid
            const container = document.getElementById('ppp_show_active_container');
            if (container) container.style.display = 'none';
        } else if (pendingSeqKey) {
            activeSeqKey = pendingSeqKey;
            pendingSeqKey = "";
            const container = document.getElementById('ppp_show_active_container');
            if (container) container.style.display = 'none';
        }

        // Always update the form hidden input with the selected key
        document.getElementById('ppp_sequence_key_input').value = activeSeqKey;

        selectedRowIdx = rowNum;
        document.getElementById('ppp_row_index_input').value = rowNum;

        // Update row highlights locally
        const tbody = document.getElementById('ppp-grid-tbody');
        const rows = tbody.querySelectorAll('tr');
        rows.forEach((r, idx) => {
            const rNum = parseInt(r.getAttribute('data-row-num'));
            if (rNum === selectedRowIdx) {
                r.style.background = '#e0f2fe';
            } else {
                r.style.background = (idx % 2 === 0) ? '#f8fafc' : '#ffffff';
            }
        });

        const updatedRowElement = document.querySelector(`tr[data-row-num='${rowNum}']`);
        if (updatedRowElement) {
            const cells = updatedRowElement.querySelectorAll('.ppp-cell');
            let passcodeStr = '';
            cells.forEach(c => passcodeStr += c.innerText);

            document.getElementById('new_password').value = passcodeStr;
            const confirmInput = document.getElementById('confirm_password');
            if (confirmInput) {
                confirmInput.value = '';
                confirmInput.focus();
            }
        }
    }

    function onLengthChange() {
        const input = document.getElementById('ppp_length_input');
        let val = parseInt(input.value) || 30;
        if (val < 25) val = 25;
        if (val > 80) val = 80;
        input.value = val;

        const checkbox = document.getElementById('ppp_show_active_checkbox');
        const showActive = checkbox && checkbox.checked;
        const targetKey = showActive ? activeSeqKey : (pendingSeqKey ? pendingSeqKey : activeSeqKey);
        if (targetKey) {
            fetchGridPreview(targetKey);
        }
    }

    function toggleShowActive() {
        const checkbox = document.getElementById('ppp_show_active_checkbox');
        if (!checkbox) return;

        if (checkbox.checked) {
            fetchGridPreview(activeSeqKey);
        } else {
            fetchGridPreview(pendingSeqKey || activeSeqKey);
        }
    }

    function copySequenceKey() {
        const input = document.getElementById('ppp_display_key');
        if (!input || !input.value) {
            alert("No sequence key generated yet!");
            return;
        }
        const key = input.value;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(key).then(() => {
                alert('Sequence Key copied!');
            }).catch(err => {
                fallbackCopyText(input);
            });
        } else {
            fallbackCopyText(input);
        }
    }

    function fallbackCopyText(input) {
        try {
            const wasReadOnly = input.readOnly;
            input.readOnly = false;
            input.select();
            input.setSelectionRange(0, 99999);
            const successful = document.execCommand('copy');
            input.readOnly = wasReadOnly;
            if (successful) {
                alert('Sequence Key copied!');
            } else {
                alert('Failed to copy. Please manually copy the text.');
            }
        } catch (err) {
            alert('Failed to copy. Please manually copy the text.');
        }
    }

    function toggleBypassPPP(isChecked) {
        const warning = document.getElementById('ppp-bypass-warning');
        const meter = document.getElementById('strength-meter-container');
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const isForced = <?= $is_forced ? 'true' : 'false' ?>;

        if (warning) {
            warning.style.display = isChecked ? 'block' : 'none';
        }
        if (meter) {
            meter.style.display = isChecked ? 'flex' : 'none';
        }

        if (isChecked) {
            newPasswordInput.removeAttribute('readonly');
            newPasswordInput.placeholder = "Min 12 chars, complex (A-Z, a-z, 0-9, symbol)";
            updatePasswordStrength(newPasswordInput.value);
        } else {
            if (isForced) {
                newPasswordInput.setAttribute('readonly', 'readonly');
            }
            newPasswordInput.placeholder = "Min 24 chars, complex (A-Z, a-z, 0-9, symbol)";
            if (meter) {
                meter.style.display = 'none';
            }
        }
    }

    function updatePasswordStrength(password) {
        const bar = document.getElementById('strength-meter-bar');
        const text = document.getElementById('strength-meter-text');
        if (!bar || !text) return;

        if (!password) {
            bar.style.width = '0%';
            bar.style.backgroundColor = '#e2e8f0';
            text.innerText = '';
            return;
        }

        let score = 0;
        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (password.length >= 16) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        let percentage = (score / 7) * 100;
        let color = '#ef4444'; // Red
        let label = 'Weak';

        if (score >= 6) {
            color = '#10b981'; // Green
            label = 'Strong';
        } else if (score >= 4) {
            color = '#f59e0b'; // Yellow
            label = 'Medium';
        }

        bar.style.width = `${percentage}%`;
        bar.style.backgroundColor = color;
        text.innerText = `Strength: ${label}`;
        text.style.color = color;
    }

    // Initialize on page load
    window.addEventListener('DOMContentLoaded', () => {
        if (activeSeqKey) {
            fetchGridPreview(activeSeqKey);
        }

        // Initialize bypass PPP checkbox state
        const bypassCheckbox = document.getElementById('bypass_ppp');
        if (bypassCheckbox) {
            toggleBypassPPP(bypassCheckbox.checked);
        }

        const newPasswordInput = document.getElementById('new_password');
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', () => {
                const checkbox = document.getElementById('bypass_ppp');
                if (checkbox && checkbox.checked) {
                    updatePasswordStrength(newPasswordInput.value);
                }
            });
        }
    });
    </script>

    <!-- PRINTABLE PASSCARD SOURCE -->
    <div id="ppp-printable-card-source" style="display: none;"></div>

    <!-- 2. SIGNATURE / INVOICE NAME CARD (ALL USERS) -->
    <div class="settings-card">
        <div class="settings-header">
            <h1>Invoice Signature</h1>
            <p class="subtitle">This name appears in the <strong>Approved By</strong> field on all printed manifests.</p>
        </div>

        <?php
            $sig_stmt = $conn_u->prepare("SELECT display_name FROM users WHERE username = ?");
            $sig_stmt->execute([$_SESSION['username']]);
            $current_sig = $sig_stmt->fetchColumn() ?: $_SESSION['username'];
        ?>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; font-size: 0.9rem; color: var(--text-secondary);">
            Current signature: <strong style="color: var(--text-main); font-size: 1rem;"><?= htmlspecialchars($current_sig) ?></strong>
        </div>

        <form method="POST">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="update_signature">
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="display_name">Signature / Approved By Name</label>
                <input type="text" id="display_name" name="display_name" value="<?= htmlspecialchars($current_sig) ?>" placeholder="e.g. John Smith — Operations Manager" required>
            </div>
            <button type="submit" class="btn-main" style="width: 100%; padding: 16px; border-radius: 12px; background: var(--accent-color); color: white; border: none; font-weight: 800; cursor: pointer;">
                ✍️ Save Signature
            </button>
        </form>
    </div>

    <!-- 3. USER MANAGEMENT CARD (ADMIN ONLY) -->
    <?php if ($_SESSION['username'] === 'admin'): ?>
    <div class="settings-card">
        <div class="settings-header">
            <h1>Staff Management</h1>
            <p class="subtitle">Assign additional accounts to help manage inventory batches.</p>
        </div>

        <form method="POST" style="background: #f8fafc; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0;">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="add_user">
            <div style="display: flex; gap: 15px; margin-bottom: 12px;">
                <div class="form-group" style="flex: 2;">
                    <label for="new_username">New Username</label>
                    <input type="text" id="new_username" name="new_username" placeholder="e.g. omar_sales" required>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="new_role">Access Level</label>
                    <select name="new_role" id="new_role" style="width:100%; height:44px; border-radius:10px; border:1px solid #ddd; padding: 0 10px; font-weight:700;">
                        <option value="Operator">Operator</option>
                        <option value="Front Desk">Front Desk</option>
                        <option value="Admin">Administrator</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 18px;">
                <label for="staff_password">Assign Password</label>
                <input type="password" id="staff_password" name="new_password" placeholder="Min 24 chars, complex (A-Z, a-z, 0-9, symbol)" required>
            </div>
            <button type="submit" class="btn-main" style="width: 100%; height: 44px; border-radius: 10px; background: var(--accent-color); color: white; border: none; font-weight: 800; cursor: pointer;">
                ⊕ Add New Staff Member
            </button>
        </form>

        <ul class="user-list">
            <li style="font-size: 0.75rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 10px;">Active Accounts</li>
            <?php
                $users = $conn_u->query("SELECT username, role FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach($users as $u) {
                    $is_admin = ($u['username'] === 'admin');
                    $user_role = $u['role'] ?? 'Operator';

                    echo "<li class='user-item'>
                            <div style='display:flex; flex-direction:column;'>
                                <span class='user-name'>" . htmlspecialchars($u['username']) . ($is_admin ? " <small style='color:var(--accent-color)'>(Root)</small>" : "") . "</span>
                                <span style='font-size: 0.65rem; color: #64748b; font-weight: 800; text-transform: uppercase;'>" . htmlspecialchars($user_role) . "</span>
                            </div>";

                    if (!$is_admin) {
                        echo "<div style='display:flex; gap:8px;'>";

                        // Role Toggle Buttons
                        if ($user_role === 'Operator') {
                            echo "<form method='POST' style='display:inline;'>
                                    " . UI::csrf_field() . "
                                    <input type='hidden' name='action' value='change_role'>
                                    <input type='hidden' name='target_user' value='" . htmlspecialchars($u['username']) . "'>
                                    <input type='hidden' name='target_role' value='Front Desk'>
                                    <button type='submit' class='btn-delete-small' style='background:#dcfce7; color:#166534;'>Promote</button>
                                  </form>";
                        } elseif ($user_role === 'Front Desk') {
                            echo "<form method='POST' style='display:inline;'>
                                    " . UI::csrf_field() . "
                                    <input type='hidden' name='action' value='change_role'>
                                    <input type='hidden' name='target_user' value='" . htmlspecialchars($u['username']) . "'>
                                    <input type='hidden' name='target_role' value='Admin'>
                                    <button type='submit' class='btn-delete-small' style='background:#dcfce7; color:#166534;'>Promote</button>
                                  </form>";
                            echo "<form method='POST' style='display:inline;'>
                                    " . UI::csrf_field() . "
                                    <input type='hidden' name='action' value='change_role'>
                                    <input type='hidden' name='target_user' value='" . htmlspecialchars($u['username']) . "'>
                                    <input type='hidden' name='target_role' value='Operator'>
                                    <button type='submit' class='btn-delete-small' style='background:#e2e8f0; color:#475569;'>Demote</button>
                                  </form>";
                        } elseif ($user_role === 'Admin') {
                            echo "<form method='POST' style='display:inline;'>
                                    " . UI::csrf_field() . "
                                    <input type='hidden' name='action' value='change_role'>
                                    <input type='hidden' name='target_user' value='" . htmlspecialchars($u['username']) . "'>
                                    <input type='hidden' name='target_role' value='Front Desk'>
                                    <button type='submit' class='btn-delete-small' style='background:#e2e8f0; color:#475569;'>Demote</button>
                                  </form>";
                        }

                        // Revoke Access
                        echo "<form method='POST' style='display:inline;' onsubmit=\"return confirm('Remove access for this user?');\">
                                " . UI::csrf_field() . "
                                <input type='hidden' name='action' value='delete_user'>
                                <input type='hidden' name='del_username' value='" . htmlspecialchars($u['username']) . "'>
                                <button type='submit' class='btn-delete-small'>Revoke</button>
                              </form>";

                        echo "</div>";
                    }
                    echo "</li>";
                }
            ?>
        </ul>
    </div>
    <?php endif; ?>
    <!-- 4. SYSTEM MAINTENANCE CARD (ADMIN ONLY) -->
    <?php if ($_SESSION['username'] === 'admin'): ?>
    <div class="settings-card" style="border-top: 4px solid #ef4444;">
        <div class="settings-header">
            <h1 style="color: #991b1b;">System Maintenance</h1>
            <p class="subtitle">Perform administrative cleanup tasks to keep the database tidy.</p>
        </div>

        <form method="POST" onsubmit="return confirm('This will permanently delete all customers who have never placed an order. Are you sure?');">
            <?= UI::csrf_field() ?>
            <input type="hidden" name="action" value="cleanup_customers">
            <div style="background: #fef2f2; border: 1px solid #fee2e2; padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                <h3 style="font-size: 0.9rem; color: #991b1b; margin-bottom: 8px;">Purge Inactive Customers</h3>
                <p style="font-size: 0.8rem; color: #7f1d1d; line-height: 1.4;">Identify and remove customer profiles that haven't been assigned to any orders or batches yet.</p>
            </div>
            <button type="submit" class="btn-main" style="width: 100%; padding: 16px; border-radius: 12px; background: #ef4444; color: white; border: none; font-weight: 800; cursor: pointer;">
                🗑️ Clean Up 0-Order Customers
            </button>
        </form>

        <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid #fecdd3;">
            <h1 style="font-size: 1.2rem; color: var(--text-main); margin-bottom: 6px;">Schema Integrity Check</h1>
            <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 18px;">Use this if the system was deployed fresh and columns are missing from an existing database. This is safe to run at any time — it only <em>adds</em> what's missing, never deletes data.</p>

            <?php
            $integrity_report = $_SESSION['integrity_report'] ?? null;
            unset($_SESSION['integrity_report']);
            if ($integrity_report): ?>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px 20px; margin-bottom: 18px; font-size: 0.78rem; font-family: monospace; color: #166534; max-height: 180px; overflow-y: auto;">
                <strong style="display:block; margin-bottom: 8px; font-size:0.85rem;">Repair Report</strong>
                <?php foreach ($integrity_report['fixed'] as $t): ?>
                    <div style="padding: 2px 0;">✓ <?= htmlspecialchars($t) ?></div>
                <?php endforeach; ?>
                <?php foreach ($integrity_report['errors'] as $e): ?>
                    <div style="color:#b91c1c; padding: 2px 0;">✗ <?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <?= UI::csrf_field() ?>
                <input type="hidden" name="action" value="integrity_check">
                <button type="submit" id="btn-integrity-check" class="btn-main" style="width: 100%; padding: 16px; border-radius: 12px; background: linear-gradient(135deg, #7c3aed, #4f46e5); color: white; border: none; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 0.95rem;">
                    🔧 Run Schema Integrity Check
                </button>
                <p style="font-size: 0.75rem; color: #64748b; text-align: center; margin-top: 10px;">Inspects all tables across every database and applies any missing column migrations.</p>
            </form>
        </div>

        <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid #fecdd3;">
            <h1 style="font-size: 1.2rem; color: var(--text-main); margin-bottom: 15px;">Data Security & Backups</h1>

            <div style="background: #f0f9ff; border: 1px solid #e0f2fe; padding: 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 20px;">
                <div style="font-size: 2rem;">🛡️</div>
                <div>
                    <h3 style="font-size: 0.9rem; color: #0369a1; margin-bottom: 4px;">One-Click System Backup</h3>
                    <p style="font-size: 0.8rem; color: #075985; line-height: 1.4;">Download a compressed ZIP archive containing all customers, orders, warehouse inventory, and system logs.</p>
                </div>
                <a href="api/generate_backup.php" class="btn-main" style="background: #0369a1; color: white; padding: 12px 20px; font-size: 0.85rem; white-space: nowrap;">
                    Download ZIP
                </a>
            </div>

            <h1 style="font-size: 1.2rem; color: var(--text-main); margin-bottom: 15px;">Storage Health</h1>

            <div style="display: grid; gap: 10px; margin-bottom: 25px;">
                <?php
                $dbs = ['customers', 'orders', 'warehouse', 'users', 'calendar'];
                foreach ($dbs as $db) {
                    $path = __DIR__ . "/../../db/{$db}.db";
                    $size = file_exists($path) ? round(filesize($path) / 1024, 2) . ' KB' : 'Not Created';
                    echo "<div style='display:flex; justify-content:space-between; padding:12px 15px; background:#f8fafc; border-radius:10px; border:1px solid #e2e8f0;'>
                            <span style='font-size:0.8rem; font-weight:800; color:var(--text-secondary); text-transform:uppercase;'>{$db}.db</span>
                            <span style='font-size:0.85rem; font-weight:700; color:var(--text-main);'>{$size}</span>
                          </div>";
                }
                ?>
            </div>

            <form method="POST">
                <?= UI::csrf_field() ?>
                <input type="hidden" name="action" value="optimize_db">
                <button type="submit" class="btn-main" style="width: 100%; padding: 16px; border-radius: 12px; background: #0369a1; color: white; border: none; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px;">
                    ⚡ Optimize System Performance
                </button>
                <p style="font-size: 0.75rem; color: #64748b; text-align: center; margin-top: 12px;">This will re-index databases and reclaim unused disk space.</p>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <!-- 5. SYSTEM ACTIVITY LOG (ADMIN ONLY) -->
    <?php if ($_SESSION['username'] === 'admin'): ?>
    <div class="settings-card" style="max-width: 800px; width: 95%;">
        <div class="settings-header">
            <h1>System Activity Log</h1>
            <p class="subtitle">A permanent record of sensitive actions performed by staff members.</p>
        </div>

        <div class="audit-log-container" style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 12px; background: #fafafa;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
                <thead style="position: sticky; top: 0; background: #f1f5f9; z-index: 1;">
                    <tr>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color);">Time</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color);">Staff</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color);">Action</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color);">Target</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $logs = Audit::getRecent(20);
                    if (empty($logs)): ?>
                        <tr><td colspan="4" style="padding: 40px; text-align: center; color: #94a3b8;">No activity recorded yet.</td></tr>
                    <?php else:
                        foreach($logs as $l):
                            $badge_color = strpos($l['action'], 'DELETE') !== false ? '#ef4444' : '#3b82f6';
                    ?>
                        <tr style="border-bottom: 1px solid #eee; background: white;">
                            <td style="padding: 12px; color: #64748b; white-space: nowrap;"><?= date('M d, H:i', strtotime($l['timestamp'])) ?></td>
                            <td style="padding: 12px; font-weight: 700;"><?= htmlspecialchars($l['user_name']) ?></td>
                            <td style="padding: 12px;">
                                <span style="background: <?= $badge_color ?>; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">
                                    <?= htmlspecialchars($l['action']) ?>
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <div style="font-weight: 700;"><?= htmlspecialchars($l['target_id']) ?></div>
                                <div style="font-size: 0.7rem; color: #94a3b8;"><?= htmlspecialchars($l['details']) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <p style="font-size: 0.7rem; color: #94a3b8; margin-top: 15px; text-align: center;">The audit log is read-only and cannot be modified by staff.</p>
    </div>
    <?php endif; ?>
</div>
