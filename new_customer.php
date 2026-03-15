<?php 
require_once 'includes/header.php'; 
?>

<div class="panel">
    <h1>➕ Add B2B Contact</h1>
    <p>Add a new company or point of contact to your Rolodex. This data will be used later when creating Purchase Forms.</p>
</div>

<div class="panel">
    <form id="newCustomerForm" class="form-grid">
        <!-- Column 1 -->
        <div>
            <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 15px;">Key Info</h3>
            
            <div class="form-group">
                <label for="company_name">Company / Organization name</label>
                <input type="text" id="company_name" name="company_name" placeholder="e.g., NRU Metals">
            </div>

            <div class="form-group">
                <label for="contact_person">Contact Person *</label>
                <input type="text" id="contact_person" name="contact_person" required placeholder="e.g., John Doe">
            </div>

            <div class="form-group">
                <label for="lead_status">Current Status *</label>
                <select id="lead_status" name="lead_status" required>
                    <option value="New Lead" selected>New Lead 🔶</option>
                    <option value="Active Customer">Active Customer ✅</option>
                    <option value="Inactive">Inactive 🚫</option>
                </select>
            </div>
        </div>

        <!-- Column 2 -->
        <div>
            <h3 style="border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 15px;">Contact Details</h3>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="text" id="email" name="email" placeholder="email@company.com">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" placeholder="(555) 123-4567">
            </div>

            <div class="form-group">
                <label for="notes">Internal Notes & Context</label>
                <textarea id="notes" name="notes" rows="3" placeholder="Met at convention, looking for 8th Gen laptops..."></textarea>
            </div>
        </div>

        <!-- Action -->
        <div style="grid-column: 1 / -1; margin-top: 20px; text-align: right; border-top: 1px solid var(--border-color); padding-top: 20px;">
            <button type="submit" class="btn btn-success" id="submitCustomerBtn" style="font-size: 1.1rem; padding: 12px 24px;">
                💾 Save to Rolodex
            </button>
        </div>
    </form>
</div>

<!-- Add the dynamic JS controls -->
<script src="assets/js/forms.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById('nav-new-customer').classList.add('active');
    });
</script>

<?php require_once 'includes/footer.php'; ?>
