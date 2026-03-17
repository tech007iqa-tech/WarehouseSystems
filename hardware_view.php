<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = null;

if ($id > 0) {
    try {
        $stmt = $pdo_labels->prepare("SELECT * FROM items WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();
    } catch (PDOException $e) {
        // Error handling
    }
}

// Redirect if not found
if (!$item) {
    echo "<div class='panel'><h1>404 Not Found</h1><p>This item does not exist.</p><a href='labels.php' class='btn btn-primary'>Back to Inventory</a></div>";
    require_once 'includes/footer.php';
    exit;
}

$desc  = $item['description'] ?? 'Untested';
$color = $desc === 'For Parts' ? 'var(--btn-danger-bg)'
       : ($desc === 'Refurbished' ? 'var(--btn-success-bg)' : '#f39c12');
?>

<div class="panel flex-between" style="margin-bottom: var(--spacing);">
    <div>
        <h1 style="color: <?= $color ?>;">🛠️ <?= htmlspecialchars($desc) ?> Technical Sheet</h1>
        <p>Detailed specifications for <strong><?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?></strong></p>
    </div>
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <button id="btnPrint" class="btn btn-success"
                data-id="<?= (int)$item['id'] ?>"
                style="padding:8px 18px;">
            🖨️ Print
        </button>
        <button id="btnOpen" class="btn"
                data-id="<?= (int)$item['id'] ?>"
                data-brand="<?= htmlspecialchars($item['brand']) ?>"
                data-model="<?= htmlspecialchars($item['model']) ?>"
                style="padding:8px 18px;">
            📂 Open
        </button>
        <button id="btnDelete" class="btn btn-danger"
                data-id="<?= (int)$item['id'] ?>"
                data-label="<?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>"
                style="padding:8px 18px;">
            🗑️ Delete
        </button>
        <a href="labels.php" class="btn" style="background:var(--bg-page); border:1px solid var(--border-color); color:var(--text-secondary); padding:8px 18px;">← Back</a>
    </div>
</div>

<!-- Main Content Grid -->
<form id="refurbForm">
    <div style="display: grid; grid-template-columns: 1fr 350px; gap: var(--spacing); align-items: start;">
        
        <!-- LEFT: Technical Specs & Baseline Form -->
        <div class="panel">
            <h3 style="margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                Detailed Hardware Profile
            </h3>
            
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($item['status'] ?? 'In Warehouse') ?>">

            <?php 
                $formType = 'edit';
                include 'includes/hardware_form.php'; 
            ?>

            <hr style="border:0; border-top:1px solid var(--border-color); margin: 25px 0;">

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <div id="saveStatus" style="margin-right: auto; line-height: 40px; font-size: 0.9rem;"></div>
                <button type="button" id="saveRefurbBtn" class="btn btn-success" style="padding: 10px 30px; font-weight: bold;">
                    💾 Update Hardware Profile
                </button>
            </div>
        </div>

        <!-- RIGHT: Summary Sidebar (Context) -->
        <div class="panel" style="background: var(--bg-page); border: 2px dashed var(--border-color);">
            <h3 style="margin-bottom: 15px; font-size: 1rem; color: var(--text-secondary);">Inventory Info</h3>
            <div style="font-size: 0.9rem; line-height: 1.8;">
                <div class="flex-between"><span>Added Date:</span> <strong><?= format_date($item['created_at']) ?></strong></div>
                <div class="flex-between"><span>Current Status:</span> <strong><?= htmlspecialchars($item['status'] ?? '—') ?></strong></div>
                <div class="flex-between"><span>Warehouse Loc:</span> <strong><?= htmlspecialchars($item['warehouse_location'] ?? '—') ?></strong></div>
            </div>
            
            <div style="margin-top: 25px; padding: 15px; background: rgba(140, 198, 63, 0.1); border-radius: 8px; color: var(--accent-hover); font-size: 0.85rem;">
                💡 <strong>Sales Note:</strong> The technical sheets are the primary source for buyers. Keeping CPU, GPU and Battery health updated here will push accurate data to the Purchase Order.
            </div>
        </div>

    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const refurbForm = document.getElementById('refurbForm');
    const saveBtn    = document.getElementById('saveRefurbBtn');
    const statusMsg  = document.getElementById('saveStatus');

    // ── SAVE ────────────────────────────────────────────────────────────────
    saveBtn.addEventListener('click', () => {
        saveBtn.disabled = true;
        saveBtn.textContent = '⏳ Saving...';
        statusMsg.textContent = '';
        statusMsg.style.color = 'var(--text-secondary)';

        const formData = new FormData(refurbForm);

        fetch('api/edit_label.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                statusMsg.textContent = '✅ Technical sheet updated successfully!';
                statusMsg.style.color = 'var(--btn-success-bg)';
                setTimeout(() => { statusMsg.textContent = ''; }, 3000);
            } else {
                statusMsg.textContent = '❌ Error: ' + (json.error || 'Unknown error');
                statusMsg.style.color = 'var(--btn-danger-bg)';
            }
        })
        .catch(() => {
            statusMsg.textContent = '❌ Network error.';
            statusMsg.style.color = 'var(--btn-danger-bg)';
        })
        .finally(() => {
            saveBtn.disabled = false;
            saveBtn.textContent = '💾 Update Technical Sheet';
        });
    });

    // ── PRINT ───────────────────────────────────────────────────────────────
    document.getElementById('btnPrint').addEventListener('click', () => {
        const id = document.getElementById('btnPrint').dataset.id;
        if (window.openPrintConfig) {
            window.openPrintConfig(id);
        } else {
            window.open('print_label.php?id=' + id, '_blank');
        }
    });

    // ── OPEN (Flash Launch ODT in LibreOffice) ───────────────────────────────
    document.getElementById('btnOpen').addEventListener('click', async function() {
        const btn   = this;
        const id    = btn.dataset.id;
        const brand = btn.dataset.brand;
        const model = btn.dataset.model;
        await flashOpenLabel(id, brand, model, btn);
    });

    // ── DELETE ───────────────────────────────────────────────────────────────
    document.getElementById('btnDelete').addEventListener('click', function() {
        const id    = this.dataset.id;
        const label = this.dataset.label;

        if (!confirm(`Delete "${label}" from the warehouse?\n\nThis cannot be undone.`)) return;

        const formData = new FormData();
        formData.append('id', id);

        fetch('api/delete_label.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                window.location.href = 'labels.php';
            } else {
                alert('Delete failed: ' + (json.error || 'Unknown error'));
            }
        })
        .catch(() => alert('Network error — item was not deleted.'));
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
