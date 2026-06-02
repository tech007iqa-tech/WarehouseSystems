<?php
// Secure Account Settings Fragment
$db_file = 'assets/db/users.db';
$message = '';
$error = '';

try {
    $conn_u = Database::users();

    // 1. Handle Password Change (All Users)
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $old_pass = $_POST['old_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        $user_id = $_SESSION['username'];

        $stmt = $conn_u->prepare("SELECT password FROM users WHERE username = ?");
        $stmt->execute([$user_id]);
        $current_hash = $stmt->fetchColumn();

        if ($current_hash && password_verify($old_pass, $current_hash)) {
            if ($new_pass === $confirm_pass) {
                if (strlen($new_pass) >= 3) {
                    $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
                    $stmt_u = $conn_u->prepare("UPDATE users SET password = ? WHERE username = ?");
                    $stmt_u->execute([$new_hash, $user_id]);
                    $message = "Password updated successfully!";
                } else {
                    $error = "New password must be at least 3 characters.";
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

                if (strlen($np) >= 3) {
                    $hash = password_hash($np, PASSWORD_BCRYPT);
                    try {
                        $auth_add = $conn_u->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                        $auth_add->execute([$nu, $hash, $nr]);
                        $message = "New user '{$nu}' ({$nr}) added successfully!";
                    } catch(Exception $e) { $error = "Error: Username might already exist."; }
                } else { $error = "User password must be at least 3 characters."; }
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
            $db_cust_file = realpath('assets/db/customers.db');
            $db_orders_file = realpath('assets/db/orders.db');
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
    <?php if ($message): ?>
        <div class="status-msg msg-success" style="width:100%; max-width:500px; margin-top:20px;"><?= $message ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="status-msg msg-error" style="width:100%; max-width:500px; margin-top:20px;"><?= $error ?></div>
    <?php endif; ?>

    <!-- 0. APPEARANCE CARD -->
    <div class="settings-card">
        <div class="settings-header">
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

    <!-- 1. PERSONAL SECURITY CARD -->
    <div class="settings-card">
        <div class="settings-header" style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <h1>Account Security</h1>
                <p class="subtitle">Update your password to keep your account secure.</p>
            </div>
            <a href="core/logout.php" style="text-decoration: none; background: #fef2f2; color: #991b1b; padding: 10px 16px; border-radius: 10px; font-size: 0.8rem; font-weight: 800; border: 1px solid #fee2e2;">
                🚪 Sign Out
            </a>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="old_password">Current Password</label>
                <input type="password" id="old_password" name="old_password" placeholder="••••••••" required>
            </div>
            <div style="border-top: 1px dashed var(--border-color); padding-top: 20px; margin-top: 20px;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Min 3 characters" required>
                </div>
                <div class="form-group" style="margin-bottom: 30px;">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                </div>
            </div>
            <button type="submit" class="btn-main" style="width: 100%; padding: 16px; border-radius: 12px; background: var(--text-main); color: white; border: none; font-weight: 800; cursor: pointer;">
                💾 Update Password
            </button>
        </form>
    </div>

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
                        <option value="Admin">Administrator</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 18px;">
                <label for="staff_password">Assign Password</label>
                <input type="password" id="staff_password" name="new_password" placeholder="Min 3 characters" required>
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

                        // Role Toggle Button
                        $next_role = ($user_role === 'Admin' ? 'Operator' : 'Admin');
                        $btn_text = ($user_role === 'Admin' ? 'Demote' : 'Promote');
                        $btn_style = ($user_role === 'Admin' ? 'background:#e2e8f0; color:#475569;' : 'background:#dcfce7; color:#166534;');

                        echo "<form method='POST' style='display:inline;'>
                                <input type='hidden' name='action' value='change_role'>
                                <input type='hidden' name='target_user' value='" . htmlspecialchars($u['username']) . "'>
                                <input type='hidden' name='target_role' value='{$next_role}'>
                                <button type='submit' class='btn-delete-small' style='{$btn_style}'>{$btn_text}</button>
                              </form>";

                        // Revoke Access
                        echo "<form method='POST' style='display:inline;' onsubmit=\"return confirm('Remove access for this user?');\">
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
                    $path = "assets/db/{$db}.db";
                    $size = file_exists($path) ? round(filesize($path) / 1024, 2) . ' KB' : 'Not Created';
                    echo "<div style='display:flex; justify-content:space-between; padding:12px 15px; background:#f8fafc; border-radius:10px; border:1px solid #e2e8f0;'>
                            <span style='font-size:0.8rem; font-weight:800; color:var(--text-secondary); text-transform:uppercase;'>{$db}.db</span>
                            <span style='font-size:0.85rem; font-weight:700; color:var(--text-main);'>{$size}</span>
                          </div>";
                }
                ?>
            </div>

            <form method="POST">
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
