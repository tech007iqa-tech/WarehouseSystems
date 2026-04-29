<?php
/**
 * Model Templates Module - Master Content Library
 */

// Handle Actions
$action = $_GET['action'] ?? null;

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $model_name = trim($_POST['model_name'] ?? '');
    $category = $_POST['category'] ?? 'Laptop';
    $base_specs = trim($_POST['base_specs'] ?? '');
    $marketing_copy = trim($_POST['marketing_copy'] ?? '');

    if (!empty($model_name)) {
        try {
            $stmt = $marketingDb->prepare("INSERT INTO model_templates (model_name, category, base_specs, marketing_copy) VALUES (?, ?, ?, ?)");
            $stmt->execute([$model_name, $category, $base_specs, $marketing_copy]);
            
            $newId = $marketingDb->lastInsertId();
            log_marketing_audit($marketingDb, 'Template', $newId, 'CREATED', "Created marketing template for: $model_name");
            
            header("Location: ?page=model_templates&success=1");
            exit;
        } catch (Exception $e) {
            $error = "Failed to create template: " . $e->getMessage();
        }
    } else {
        $error = "Model Name is required.";
    }
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $model_name = trim($_POST['model_name'] ?? '');
    $category = $_POST['category'] ?? 'Laptop';
    $base_specs = trim($_POST['base_specs'] ?? '');
    $marketing_copy = trim($_POST['marketing_copy'] ?? '');

    try {
        $stmt = $marketingDb->prepare("UPDATE model_templates SET model_name = ?, category = ?, base_specs = ?, marketing_copy = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$model_name, $category, $base_specs, $marketing_copy, $id]);
        
        log_marketing_audit($marketingDb, 'Template', $id, 'UPDATED', "Updated marketing template for: $model_name");
        
        header("Location: ?page=model_templates&success=2");
        exit;
    } catch (Exception $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

$editTmpl = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $marketingDb->prepare("SELECT * FROM model_templates WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $editTmpl = $stmt->fetch();
}
?>

<header class="page-header">
    <h1>Model Template Library</h1>
    <p>Create master marketing copy and specs for high-volume inventory.</p>
</header>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php echo ($_GET['success'] == '1') ? 'Template created successfully!' : 'Template updated successfully!'; ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<div class="dashboard-grid">
    <!-- NEW TEMPLATE FORM -->
    <section class="card">
        <h2><?php echo $editTmpl ? 'Edit Template' : 'Create New Template'; ?></h2>
        <form action="?page=model_templates&action=<?php echo $editTmpl ? 'update' : 'add'; ?>" method="POST" class="standard-form">
            <?php if ($editTmpl): ?>
                <input type="hidden" name="id" value="<?php echo $editTmpl['id']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="model_name">Model Name</label>
                <?php 
                    $prefill = $_GET['prefill_model'] ?? ($editTmpl['model_name'] ?? ''); 
                ?>
                <input type="text" name="model_name" id="model_name" required value="<?php echo htmlspecialchars($prefill); ?>" placeholder="e.g. Dell Latitude 5490">
            </div>
            <div class="form-group">
                <label for="category">Category</label>
                <select name="category" id="category">
                    <?php 
                    $cats = ['Laptop', 'Desktop', 'Server', 'Part'];
                    foreach($cats as $cat):
                        $sel = (isset($editTmpl) && $editTmpl['category'] === $cat) ? 'selected' : '';
                        echo "<option value=\"$cat\" $sel>$cat</option>";
                    endforeach;
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="base_specs">Standard Specifications</label>
                <?php $preSpecs = $_GET['prefill_specs'] ?? ($editTmpl['base_specs'] ?? ''); ?>
                <textarea name="base_specs" id="base_specs" rows="4" placeholder="i5-8350U, 8GB RAM, 256GB SSD..."><?php echo htmlspecialchars($preSpecs); ?></textarea>
            </div>
            <div class="form-group">
                <label for="marketing_copy">Marketing Copy (The Pitch)</label>
                <?php $preCopy = $_GET['prefill_copy'] ?? ($editTmpl['marketing_copy'] ?? ''); ?>
                <textarea name="marketing_copy" id="marketing_copy" rows="6" placeholder="Write the ad description here..."><?php echo htmlspecialchars($preCopy); ?></textarea>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn-action"><?php echo $editTmpl ? 'Update Template' : 'Save Template'; ?></button>
                <?php if ($editTmpl): ?>
                    <a href="?page=model_templates" class="btn-small" style="line-height: 48px;">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- TEMPLATE LIST -->
    <section class="card template-library-list">
        <h2>Existing Templates</h2>
        <div class="template-list">
            <?php
            $templates = $marketingDb->query("SELECT * FROM model_templates ORDER BY model_name ASC")->fetchAll();
            if (empty($templates)):
            ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-dim);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📚</div>
                    <p>Your library is empty. Templates help you generate ads instantly from warehouse stock.</p>
                </div>
            <?php else: ?>
                <div class="template-grid">
                    <?php foreach ($templates as $tmpl): 
                        // Photo Bank Check
                        $photoStmt = $marketingDb->prepare("SELECT category FROM photos WHERE model_name = ?");
                        $photoStmt->execute([$tmpl['model_name']]);
                        $foundPhotos = $photoStmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        $hasBulk = in_array('Bulk Stock', $foundPhotos);
                        $hasLaptop = in_array('Laptop', $foundPhotos) || in_array('Workstation', $foundPhotos);
                        $hasOther = count($foundPhotos) > 0;
                        $photoCount = count($foundPhotos);
                    ?>
                        <div class="template-card">
                            <div class="tmpl-header">
                                <h3><?php echo htmlspecialchars($tmpl['model_name']); ?></h3>
                                <span class="tmpl-badge"><?php echo $tmpl['category']; ?></span>
                            </div>
                            
                            <!-- PHOTO BANK PREVIEW -->
                            <div class="photo-bank-preview">
                                <div class="photo-slot <?php echo $hasBulk ? 'filled' : ''; ?>" title="Bulk/Pallet Shot">📦</div>
                                <div class="photo-slot <?php echo $hasLaptop ? 'filled' : ''; ?>" title="Detail Shot">✨</div>
                                <div class="photo-slot <?php echo $hasOther ? 'filled' : ''; ?>" title="Other Assets">🖼️</div>
                                <span class="photo-status"><?php echo $photoCount; ?> Assets</span>
                            </div>

                            <div class="tmpl-body">
                                <p class="tmpl-specs"><strong>Specs:</strong> <?php echo htmlspecialchars(mb_strimwidth($tmpl['base_specs'], 0, 100, "...")); ?></p>
                            </div>
                            <div class="tmpl-footer">
                                <a href="?page=model_templates&action=edit&id=<?php echo $tmpl['id']; ?>" class="btn-small">Edit</a>
                                <a href="?page=ad_generator&model=<?php echo urlencode($tmpl['model_name']); ?>" class="btn-small btn-highlight">Create Ad</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>


