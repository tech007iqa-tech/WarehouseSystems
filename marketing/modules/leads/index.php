<?php
/**
 * Leads Module - Main View & Logic
 */

// Handle Actions
$action = $_GET['action'] ?? null;

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $contact_person = trim($_POST['name'] ?? '');
    $company_name = trim($_POST['company'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $lead_source = $_POST['source'] ?? 'Manual';

    // CRM requires company_name, so we default it if empty
    if (empty($company_name)) {
        $company_name = "Lead: " . $contact_person;
    }

    if (!empty($contact_person) && !empty($email)) {
        try {
            // Generate Master CRM ID (Matching Order Manager format)
            $customer_id = 'CUST-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            // 1. Save to Master CRM
            $stmtCrm = $crmDb->prepare("INSERT INTO customers (customer_id, contact_person, company_name, email, phone, lead_source, account_status) VALUES (?, ?, ?, ?, ?, ?, 'Lead')");
            $stmtCrm->execute([$customer_id, $contact_person, $company_name, $email, $phone, $lead_source]);

            // 2. Save to Local Marketing DB (The "Sync")
            $stmtLocal = $marketingDb->prepare("INSERT INTO leads (customer_id, name, company, email, phone, source, status) VALUES (?, ?, ?, ?, ?, ?, 'Lead')");
            $stmtLocal->execute([$customer_id, $contact_person, $company_name, $email, $phone, $lead_source]);
            
            // Log to Audit
            log_marketing_audit($marketingDb, 'Lead', $customer_id, 'SYNCED', "Lead synced to both CRM and Local DB: $contact_person ($company_name)");
            
            header("Location: ?page=leads&success=1");
            exit;
        } catch (Exception $e) {
            $error = "Failed to add lead: " . $e->getMessage();
        }
    } else {
        $error = "Name and Email are required.";
    }
}

// Handle Sync Action (Pull from CRM to Local)
if ($action === 'sync') {
    try {
        // Fetch ALL contacts from CRM
        $crmContacts = $crmDb->query("SELECT * FROM customers")->fetchAll();
        $importedCount = 0;

        foreach ($crmContacts as $crmC) {
            // Check if contact already exists in local DB by customer_id or email
            $check = $marketingDb->prepare("SELECT COUNT(*) FROM leads WHERE customer_id = ? OR email = ?");
            $check->execute([$crmC['customer_id'], $crmC['email']]);
            
            if ($check->fetchColumn() == 0) {
                // If it's a 'Customer', we set the local status to 'Customer'
                // If it's a 'Lead', we set it to 'Lead' (previously 'New')
                $localStatus = $crmC['account_status'] ?? 'Lead';
                
                $stmtImport = $marketingDb->prepare("INSERT INTO leads (customer_id, name, company, email, phone, source, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtImport->execute([
                    $crmC['customer_id'],
                    $crmC['contact_person'],
                    $crmC['company_name'],
                    $crmC['email'],
                    $crmC['phone'],
                    $crmC['lead_source'] ?? 'Master CRM',
                    $localStatus
                ]);
                $importedCount++;
            }
        }
        header("Location: ?page=leads&synced=" . $importedCount);
        exit;
    } catch (Exception $e) {
        $error = "Sync failed: " . $e->getMessage();
    }
}

// Handle Update Action (Save Edits back to Local & CRM)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $customer_id = $_POST['customer_id'];
    $name = trim($_POST['name']);
    $company = trim($_POST['company']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $status = $_POST['status'];

    try {
        // 1. Update Local Marketing DB
        $stmtLocal = $marketingDb->prepare("UPDATE leads SET name = ?, company = ?, email = ?, phone = ?, status = ? WHERE id = ?");
        $stmtLocal->execute([$name, $company, $email, $phone, $status, $id]);

        // 2. Update Master CRM (if we have a customer_id)
        if (!empty($customer_id)) {
            $stmtCrm = $crmDb->prepare("UPDATE customers SET contact_person = ?, company_name = ?, email = ?, phone = ?, account_status = ? WHERE customer_id = ?");
            $stmtCrm->execute([$name, $company, $email, $phone, $status, $customer_id]);
        }

        log_marketing_audit($marketingDb, 'Lead', $customer_id ?: $id, 'UPDATED', "Lead updated and synced: $name ($company)");
        header("Location: ?page=leads&success=updated");
        exit;
    } catch (Exception $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Fetch Lead for Editing
$editLead = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $marketingDb->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $editLead = $stmt->fetch();
}
?>

<?php if ($editLead): ?>
    <!-- EDIT LEAD VIEW -->
    <header class="page-header">
        <h1>Edit Contact: <?php echo htmlspecialchars($editLead['name']); ?></h1>
        <p>Updates will be synced to the Master CRM database.</p>
    </header>

    <div class="dashboard-grid">
        <section class="card lead-details-form">
            <h2>Lead Details</h2>
            <form action="?page=leads&action=update" method="POST" class="standard-form">
                <input type="hidden" name="id" value="<?php echo $editLead['id']; ?>">
                <input type="hidden" name="customer_id" value="<?php echo $editLead['customer_id']; ?>">
                
                <div class="form-grid-2col">
                    <div class="form-group">
                        <label>Contact Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($editLead['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Company</label>
                        <input type="text" name="company" value="<?php echo htmlspecialchars($editLead['company']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($editLead['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($editLead['phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Marketing Status</label>
                        <select name="status">
                            <option value="Lead" <?php echo $editLead['status'] === 'Lead' ? 'selected' : ''; ?>>Lead</option>
                            <option value="Customer" <?php echo $editLead['status'] === 'Customer' ? 'selected' : ''; ?>>Customer</option>
                            <option value="Lost" <?php echo $editLead['status'] === 'Lost' ? 'selected' : ''; ?>>Lost / Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn-action">💾 Sync & Save Changes</button>
                    <a href="?page=leads" class="btn-small" style="line-height: 48px; padding: 0 20px;">Cancel</a>
                </div>
            </form>
        </section>
    </div>
<?php else: ?>

<?php if (isset($_GET['synced'])): ?>
    <div class="alert alert-success">Sync complete! Imported <?php echo (int)$_GET['synced']; ?> new leads from Master CRM.</div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Lead synced to Master CRM successfully!</div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>


<header class="page-header">
    <h1>Lead Management</h1>
    <p>Track and manage your B2B prospects synced with the Master CRM.</p>
</header>

<div class="dashboard-grid">
    <!-- NEW LEAD FORM -->
    <section class="card">
        <h2>Capture New Lead</h2>
        <form action="?page=leads&action=add" method="POST" class="standard-form">
            <div class="form-group">
                <label for="name">Contact Name</label>
                <input type="text" name="name" id="name" required placeholder="e.g. John Doe">
            </div>
            <div class="form-group">
                <label for="company">Company</label>
                <input type="text" name="company" id="company" placeholder="e.g. Acme Corp">
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" required placeholder="john@example.com">
            </div>
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" name="phone" id="phone" placeholder="+1 (555) 000-0000">
            </div>
            <div class="form-group">
                <label for="source">Lead Source</label>
                <select name="source" id="source">
                    <option value="Marketing Hub">Marketing Hub</option>
                    <option value="LinkedIn">LinkedIn</option>
                    <option value="Email Outreach">Email Outreach</option>
                    <option value="Referral">Referral</option>
                </select>
            </div>
            <button type="submit" class="btn-action">Add to CRM</button>
        </form>
    </section>

    <section class="card lead-pool-table">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <h2 style="margin: 0;">Lead & Customer Pool</h2>
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" id="leadSearch" placeholder="Search name, company..." style="width: 250px; min-height: 38px; font-size: 0.9rem;">
                <a href="?page=leads&action=sync" class="btn-small" style="background: var(--accent-tertiary); color: var(--accent-primary);">🔄 Sync CRM</a>
            </div>
        </div>

        <!-- Filtering Tabs -->
        <div class="tabs-container" style="margin-bottom: 1.5rem;">
            <button class="tab-btn active" onclick="filterLeads('all')">All Contacts</button>
            <button class="tab-btn" onclick="filterLeads('lead')">Leads Only</button>
            <button class="tab-btn" onclick="filterLeads('customer')">Customers Only</button>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="leadsTable">
                <thead>
                    <tr>
                        <th>Name & ID</th>
                        <th>Company</th>
                        <th>Contact Info</th>
                        <th>Status</th>
                        <th>Added</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $leads = $marketingDb->query("SELECT * FROM leads ORDER BY created_at DESC LIMIT 50")->fetchAll();
                    if (empty($leads)):
                    ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-dim);">No leads found. Click "Sync from CRM" to import data.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leads as $lead): 
                            $rawStatus = strtolower($lead['status']);
                            // Smart Badge Logic: If it contains 'customer', it's a Customer.
                            $isCustomer = (strpos($rawStatus, 'customer') !== false);
                            $badgeClass = $isCustomer ? 'badge-customer' : 'badge-lead';
                            
                            // Display logic: Clean up long strings for the badge
                            $displayStatus = $isCustomer ? 'CUSTOMER' : strtoupper($lead['status']);
                        ?>
                        <tr class="lead-row" data-status="<?php echo $isCustomer ? 'customer' : 'lead'; ?>">
                            <td data-label="Name & ID">
                                <div style="font-size: 0.7rem; color: var(--text-secondary);"><?php echo $lead['customer_id'] ?? 'LOCAL'; ?></div>
                                <strong class="searchable-name"><?php echo htmlspecialchars($lead['name']); ?></strong>
                            </td>
                            <td data-label="Company" class="searchable-company"><?php echo htmlspecialchars($lead['company'] ?? '—'); ?></td>
                            <td data-label="Contact Info">
                                <a href="mailto:<?php echo $lead['email']; ?>" style="display:block; font-size: 0.85rem; color: var(--accent-primary); text-decoration:none;">✉️ <?php echo htmlspecialchars($lead['email']); ?></a>
                                <?php if (!empty($lead['phone'])): ?>
                                    <a href="tel:<?php echo $lead['phone']; ?>" style="display:block; font-size: 0.85rem; color: var(--text-dim); text-decoration:none; margin-top: 4px;">📞 <?php echo htmlspecialchars($lead['phone']); ?></a>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status"><span class="badge <?php echo $badgeClass; ?>"><?php echo $displayStatus; ?></span></td>
                            <td data-label="Added" style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo date('M j, y', strtotime($lead['created_at'])); ?></td>
                            <td style="text-align: right;">
                                <a href="?page=leads&action=edit&id=<?php echo $lead['id']; ?>" class="btn-small">Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php endif; ?>

<script>
function filterLeads(filter) {
    // Update Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if(btn.innerText.toLowerCase().includes(filter)) btn.classList.add('active');
        if(filter === 'all' && btn.innerText.includes('All')) btn.classList.add('active');
    });

    // Filter Rows
    document.querySelectorAll('.lead-row').forEach(row => {
        if (filter === 'all' || row.dataset.status === filter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Search Logic
document.getElementById('leadSearch').addEventListener('keyup', function() {
    const term = this.value.toLowerCase();
    document.querySelectorAll('.lead-row').forEach(row => {
        const name = row.querySelector('.searchable-name').innerText.toLowerCase();
        const company = row.querySelector('.searchable-company').innerText.toLowerCase();
        if (name.includes(term) || company.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});
</script>

<style>
.tabs-container {
    display: flex;
    gap: 0.5rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.5rem;
}
.tab-btn {
    background: none;
    border: none;
    padding: 0.5rem 1rem;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--text-dim);
    border-radius: 6px;
    transition: all 0.2s;
}
.tab-btn:hover {
    background: rgba(0, 0, 0, 0.05);
}
.tab-btn.active {
    background: var(--accent-primary);
    color: white;
}
</style>



