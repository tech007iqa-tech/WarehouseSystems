<?php 
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php'; 

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = null;

if ($id > 0) {
    try {
        $stmt = $pdo_rolodex->prepare("SELECT * FROM customers WHERE customer_id = :id");
        $stmt->execute([':id' => $id]);
        $customer = $stmt->fetch();
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

if (!$customer) {
    echo '<div class="panel text-center"><h1>404</h1><p>Customer not found.</p><a href="rolodex.php" class="btn btn-primary">Back to Rolodex</a></div>';
    require_once 'includes/footer.php';
    exit;
}
?>

<div class="panel flex-between">
    <div>
        <h1>✏️ Edit B2B Contact</h1>
        <p>Updating details for <strong style="color:var(--accent-color);"><?= htmlspecialchars($customer['company_name'] ?: $customer['contact_person']) ?></strong></p>
    </div>
    <a href="customer_view.php?id=<?= $id ?>" class="btn">← Back to Card</a>
</div>

<div class="panel">
    <form id="editCustomerForm" class="form-grid">
        <input type="hidden" name="customer_id" value="<?= $id ?>">
        
        <!-- Column 1 -->
        <div>
            <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 15px;">Key Info</h3>
            
            <div class="form-group">
                <label for="company_name">Company / Organization name</label>
                <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($customer['company_name'] ?? '') ?>" placeholder="e.g., NRU Metals">
            </div>

            <div class="form-group">
                <label for="website">Website / URL</label>
                <input type="text" id="website" name="website" value="<?= htmlspecialchars($customer['website'] ?? '') ?>" placeholder="https://www.company.com">
            </div>

            <div class="form-group">
                <label for="contact_person">Contact Person *</label>
                <input type="text" id="contact_person" name="contact_person" value="<?= htmlspecialchars($customer['contact_person'] ?? '') ?>" required placeholder="e.g., John Doe">
            </div>

            <div class="form-group">
                <label for="tax_id">Address</label>
                <input type="text" id="tax_id" name="tax_id" value="<?= htmlspecialchars($customer['tax_id'] ?? '') ?>" placeholder="e.g. 123 Main St">
            </div>

            <div class="form-group">
                <label for="lead_status">Current Status *</label>
                <select id="lead_status" name="lead_status" required>
                    <option value="New Lead" <?= $customer['lead_status'] === 'New Lead' ? 'selected' : '' ?>>New Lead 🔶</option>
                    <option value="Active Customer" <?= $customer['lead_status'] === 'Active Customer' ? 'selected' : '' ?>>Active Customer ✅</option>
                    <option value="Inactive" <?= $customer['lead_status'] === 'Inactive' ? 'selected' : '' ?>>Inactive 🚫</option>
                </select>
            </div>
        </div>

        <!-- Column 2 -->
        <div>
            <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 15px;">Contact Details</h3>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="text" id="email" name="email" value="<?= htmlspecialchars($customer['email'] ?? '') ?>" placeholder="email@company.com">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" placeholder="(555) 123-4567">
            </div>

            <div class="form-group">
                <label for="address">Shipping Address</label>
                <textarea id="address" name="address" rows="2" placeholder="123 Industrial Way, Suite 100, City, State ZIP"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="notes">Internal Notes & Context</label>
                <textarea id="notes" name="notes" rows="2" placeholder="Met at convention, looking for 8th Gen laptops..."><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Action -->
        <div style="grid-column: 1 / -1; margin-top: 20px; text-align: right; border-top: 1px solid var(--border-color); padding-top: 20px;">
            <button type="submit" class="btn btn-primary" id="submitEditCustomerBtn" style="font-size: 1.1rem; padding: 12px 24px;">
                💾 Save Changes
            </button>
        </div>
    </form>
</div>

<!-- Add the dynamic JS controls -->
<script src="assets/js/forms.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-rolodex').classList.add('active');
    });
</script>

<?php require_once 'includes/footer.php'; ?>
