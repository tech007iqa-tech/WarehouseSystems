<?php 
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php'; 

// Fetch current customers/leads from rolodex.sqlite
$contacts = [];
try {
    $stmt = $pdo_rolodex->query("
        SELECT * FROM customers 
        ORDER BY created_at DESC
    ");
    $contacts = $stmt->fetchAll();
} catch (PDOException $e) {
    // Graceful fail if not initialized
}
?>

<div class="panel flex-between">
    <div>
        <h1>📇 Customer Rolodex</h1>
        <p>Manage your B2B customers, leads, and vendors.</p>
    </div>
    <a href="new_customer.php" class="btn btn-primary">➕ Add New Contact</a>
</div>

<div class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Company Name</th>
                <th>Contact Person</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Added</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($contacts)): ?>
                <tr>
                    <td colspan="8" class="text-center" style="padding: 30px; font-style: italic; color: var(--text-secondary);">
                        Your Rolodex is empty.<br>Start adding leads to build your B2B network!
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($contacts as $contact): ?>
                    <tr>
                        <td style="font-weight: bold; color: var(--text-secondary);">C-<?= str_pad($contact['customer_id'], 4, '0', STR_PAD_LEFT) ?></td>
                        
                        <td style="font-weight: bold; color: var(--accent-color);">
                            <?= htmlspecialchars($contact['company_name'] ?: 'N/A') ?>
                        </td>
                        
                        <td><?= htmlspecialchars($contact['contact_person']) ?></td>
                        
                        <td>
                            <?php if($contact['email']): ?>
                                <a href="mailto:<?= htmlspecialchars($contact['email']) ?>" style="font-size: 0.9rem;"><?= htmlspecialchars($contact['email']) ?></a>
                            <?php else: ?>
                                <span style="color: var(--text-secondary); font-size: 0.9rem;">-</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if($contact['phone']): ?>
                                <span style="font-size: 0.9rem;"><?= htmlspecialchars($contact['phone']) ?></span>
                            <?php else: ?>
                                <span style="color: var(--text-secondary); font-size: 0.9rem;">-</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php 
                                $status_color = 'var(--text-secondary)';
                                if($contact['lead_status'] === 'Active Customer') $status_color = 'var(--btn-success-bg)';
                                if($contact['lead_status'] === 'New Lead') $status_color = '#f39c12';
                            ?>
                            <span style="background: <?= $status_color ?>; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                                <?= htmlspecialchars($contact['lead_status']) ?>
                            </span>
                        </td>
                        
                        <td style="max-width: 250px; font-size: 0.85rem; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= htmlspecialchars($contact['notes'] ?: '-') ?>
                        </td>
                        
                        <td style="font-size: 0.85rem; color: var(--text-secondary);">
                            <?= format_date($contact['created_at']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-rolodex').classList.add('active');
    });
</script>

<?php require_once 'includes/footer.php'; ?>
