<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$contacts = [];
try {
    $stmt = $pdo_rolodex->query("SELECT * FROM customers ORDER BY created_at DESC");
    $contacts = $stmt->fetchAll();
} catch (PDOException $e) {
    // Graceful fail
}
?>

<div class="panel flex-between">
    <div>
        <h1>📇 Customer Rolodex</h1>
        <p>Manage your B2B <?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$contacts = [];
try {
    $stmt = $pdo_rolodex->query("SELECT * FROM customers ORDER BY created_at DESC");
    $contacts = $stmt->fetchAll();
} catch (PDOException $e) {
    // Log the error for debugging purposes
    error_log("Database error in rolodex.php: " . $e->getMessage());
    // Ensure $contacts remains empty for graceful failure
}
?>

<!-- Page Header -->
<div class="panel flex-between mb-spacing">
    <div>
        <h1>📇 Customer Rolodex</h1>
        <p>Manage your B2B customers, leads, and vendors. Click Edit to update a record inline.</p>
    </div>
    <a href="new_customer.php" class="btn btn-primary">➕ Add New Contact</a>
</div>

<!-- Customer Table -->
<div class="panel">
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Company Name</th>
                    <th>Contact Person</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Tier</th>
                    <th>Notes</th>
                    <th>Record</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="rolodexTableBody">
                <?php if (empty($contacts)): ?>
                    <tr>
                        <td colspan="8" class="text-center empty-table-message">
                            Your Rolodex is empty.<br>
                            <a href="new_customer.php" class="btn btn-link">Add your first contact →</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($contacts as $contact): ?>
                        <?php
                            $status = $contact['lead_status'] ?? 'Inactive';
                            $statusClass = '';
                            if ($status === 'Active Customer') {
                                $statusClass = 'status-active';
                            } elseif ($status === 'New Lead') {
                                $statusClass = 'status-lead';
                            } else {
                                $statusClass = 'status-inactive';
                            }
                        ?>
                        <tr data-cid="<?= (int)$contact['customer_id'] ?>">
                            <td class="font-bold">
                                <a href="customer_view.php?id=<?= (int)$contact['customer_id'] ?>" class="text-accent no-underline">
                                    <?= htmlspecialchars($contact['company_name'] ?: 'N/A') ?>
                                </a>
                            </td>

                            <td><?= htmlspecialchars($contact['contact_person'] ?? '') ?></td>

                            <td>
                                <?php if (!empty($contact['email'])): ?>
                                    <a href="mailto:<?= htmlspecialchars($contact['email']) ?>" class="text-sm">
                                        <?= htmlspecialchars($contact['email']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-secondary text-sm">-</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($contact['phone'])): ?>
                                    <span class="text-sm"><?= htmlspecialchars($contact['phone']) ?></span>
                                <?php else: ?>
                                    <span class="text-secondary text-sm">-</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="status-badge <?= $statusClass ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>
    
                            <td>
                                <?php
                                    $tier = $contact['tier'] ?? 'Bronze';
                                    $tColor = ($tier === 'Gold') ? '#ffd700' : (($tier === 'Silver') ? '#c0c0c0' : '#cd7f32');
                                ?>
                                <span class="status-badge" style="background:<?= $tColor ?>; color:#000;">
                                    <?= htmlspecialchars($tier) ?>
                                </span>
                            </td>

                            <td class="text-xs text-secondary truncate-notes">
                                <?= htmlspecialchars($contact['notes'] ?: '-') ?>
                            </td>

                            <td class="text-xs text-secondary">
                                <?= format_date($contact['created_at']) ?>
                            </td>

                            <td class="whitespace-nowrap">
                                <div class="action-strip">
                                    <button class="btn edit-customer-btn"
                                            data-id="<?= (int)$contact['customer_id'] ?>">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger delete-customer-btn"
                                            data-id="<?= (int)$contact['customer_id'] ?>"
                                            data-label="<?= htmlspecialchars($contact['company_name'] ?: $contact['contact_person']) ?>">
                                        🗑 Del
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="assets/js/rolodex.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const navItem = document.getElementById('nav-rolodex');
        if (navItem) navItem.classList.add('active');
    });
</script>

<?php require_once 'includes/footer.php'; ?>

<!-- New CSS for improved styling -->
<style>
    /* Utility classes */
    .mb-spacing { margin-bottom: var(--spacing); }
    .font-bold { font-weight: bold; }
    .text-accent { color: var(--accent-color); }
    .text-secondary { color: var(--text-secondary); }
    .text-sm { font-size: 0.9rem; }
    .text-xs { font-size: 0.85rem; }
    .no-underline { text-decoration: none; }
    .whitespace-nowrap { white-space: nowrap; }
    .text-center { text-align: center; }

    /* Table styling */
    .empty-table-message {
        padding: 30px;
        font-style: italic;
        color: var(--text-secondary);
    }

    .empty-table-message .btn-link {
        color: var(--accent-color);
        text-decoration: underline;
    }

    .truncate-notes {
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Status badges */
    .status-badge {
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: bold;
        color: #fff;
        display: inline-block;
    }

    .status-active {
        background: var(--btn-success-bg);
    }

    .status-lead {
        background: #f39c12; /* Orange */
    }

    .status-inactive {
        background: var(--text-secondary);
    }

    /* Action buttons */
    .edit-customer-btn {
        font-size: 0.75rem;
        padding: 5px 10px;
        background: var(--bg-page);
        border: 1px solid var(--border-color);
        color: var(--text-main);
        margin-right: 4px;
    }

    .delete-customer-btn {
        font-size: 0.75rem;
        padding: 5px 10px;
    }

    .action-strip {
        display: flex;
        align-items: center;
    }
</style>

<?php require_once 'includes/footer.php'; ?>
