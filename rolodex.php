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
        <p>Manage your B2B customers, leads, and vendors. Click Edit to update a record inline.</p>
    </div>
    <a href="new_customer.php" class="btn btn-primary">➕ Add New Contact</a>
</div>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Company Name</th>
                <th>Contact Person</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Record</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="rolodexTableBody">
            <?php if (empty($contacts)): ?>
                <tr>
                    <td colspan="8" class="text-center" style="padding: 30px; font-style: italic; color: var(--text-secondary);">
                        Your Rolodex is empty.<br>
                        <a href="new_customer.php" style="color: var(--accent-color);">Add your first contact →</a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($contacts as $contact): ?>
                    <?php
                        $status_color = $contact['lead_status'] === 'Active Customer' ? 'var(--btn-success-bg)'
                                      : ($contact['lead_status'] === 'New Lead'       ? '#f39c12'
                                      : 'var(--text-secondary)');
                    ?>
                    <tr data-cid="<?= (int)$contact['customer_id'] ?>">

                        <td style="font-weight:bold;">
                            <a href="customer_view.php?id=<?= (int)$contact['customer_id'] ?>" style="color:var(--accent-color); text-decoration:none;">
                                <?= htmlspecialchars($contact['company_name'] ?: 'N/A') ?>
                            </a>
                        </td>

                        <td><?= htmlspecialchars($contact['contact_person']) ?></td>

                        <td>
                            <?php if ($contact['email']): ?>
                                <a href="mailto:<?= htmlspecialchars($contact['email']) ?>"
                                   style="font-size:0.9rem;"><?= htmlspecialchars($contact['email']) ?></a>
                            <?php else: ?>
                                <span style="color:var(--text-secondary);font-size:0.9rem;">-</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($contact['phone']): ?>
                                <span style="font-size:0.9rem;"><?= htmlspecialchars($contact['phone']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-secondary);font-size:0.9rem;">-</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span style="background:<?= $status_color ?>;color:#fff;padding:2px 8px;border-radius:4px;font-size:0.8rem;font-weight:bold;">
                                <?= htmlspecialchars($contact['lead_status']) ?>
                            </span>
                        </td>

                        <td style="max-width:200px;font-size:0.85rem;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= htmlspecialchars($contact['notes'] ?: '-') ?>
                        </td>

                        <td style="font-size:0.85rem;color:var(--text-secondary);">
                            <?= format_date($contact['created_at']) ?>
                        </td>

                        <td style="white-space:nowrap;">
                            <button class="btn edit-customer-btn"
                                    data-id="<?= (int)$contact['customer_id'] ?>"
                                    style="font-size:0.75rem;padding:5px 10px;background:var(--bg-page);border:1px solid var(--border-color);color:var(--text-main);margin-right:4px;">
                                ✏️ Edit
                            </button>
                            <button class="btn btn-danger delete-customer-btn"
                                    data-id="<?= (int)$contact['customer_id'] ?>"
                                    data-label="<?= htmlspecialchars($contact['company_name'] ?: $contact['contact_person']) ?>"
                                    style="font-size:0.75rem;padding:5px 10px;">
                                🗑 Del
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="assets/js/rolodex.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-rolodex').classList.add('active');
    });
</script>

<?php require_once 'includes/footer.php'; ?>
