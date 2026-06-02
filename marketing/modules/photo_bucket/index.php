<?php
/**
 * Photo Bucket Module - Marketing Hub
 * Handles management of hardware and marketing images.
 */

$marketingDb = get_marketing_db();
$labelsDb = get_labels_db();

require_once __DIR__ . '/../../includes/photo_processor.php';
$processor = new PhotoProcessor($marketingDb);

require_once __DIR__ . '/functions.php';

// Handle any POST/GET actions
handle_photo_bucket_actions($marketingDb, $processor, $labelsDb);

// Fetch Data
$photos = $marketingDb->query("SELECT * FROM photos ORDER BY created_at DESC")->fetchAll();
$models = get_photo_bucket_models($marketingDb, $labelsDb);

?>

<!-- Module Specific Styles -->
<link rel="stylesheet" href="assets/css/modules/photo_bucket.css">

<header class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1>🖼️ Photo Bucket</h1>
            <p>Manage your marketing assets and hardware photography.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <?php if (extension_loaded('gd')): ?>
                <a href="?page=photo_bucket&regenerate_thumbnails=1" class="btn-action" style="background: var(--accent-secondary); color: var(--text-main); text-decoration: none; display: flex; align-items: center; justify-content: center; min-width: 180px;">⚙️ Regenerate All</a>
            <?php endif; ?>
            <button onclick="document.getElementById('upload-modal').style.display='flex'" class="btn-action" style="min-width: 180px;">Upload New Photo</button>
        </div>
    </div>
</header>

<?php if (!extension_loaded('gd')): ?>
    <div class="alert alert-danger" style="background: #fff1f2; color: #9f1239; border-color: #fda4af;">
        <strong>⚠️ Performance Warning:</strong> The PHP 'GD' library is not enabled on your server. High-resolution photos will be used directly, which may slow down the gallery. Enable 'gd' in your php.ini for automatic thumbnail optimization.
    </div>
    <?php
    $marketingDb->exec("UPDATE photos SET status = 'Ready' WHERE status = 'Processing'");
    ?>
<?php endif; ?>

<div class="photo-grid">
    <?php if (empty($photos)): ?>
        <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 4rem;">
            <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">📸</div>
            <h2 style="margin-bottom: 0.5rem;">No Photos Found</h2>
            <p style="color: var(--text-dim);">Start by uploading some hardware photos for your marketing campaigns.</p>
        </div>
    <?php else: ?>
        <?php foreach ($photos as $photo): ?>
            <div class="photo-card-compact">
                <div class="photo-thumb-container">
                    <?php
                    $displayImg = (!empty($photo['thumbnail_path']) && file_exists(__DIR__ . '/../../' . $photo['thumbnail_path']))
                                  ? $photo['thumbnail_path']
                                  : $photo['file_path'];
                    ?>
                    <img src="<?php echo $displayImg; ?>" alt="<?php echo htmlspecialchars($photo['original_name']); ?>"
                         style="<?php echo ($photo['status'] === 'Processing') ? 'filter: blur(8px);' : ''; ?>">

                    <?php if ($photo['status'] === 'Processing'): ?>
                        <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.4); font-size: 0.6rem; font-weight: 800; color: var(--accent-primary);">
                            ⚙️
                        </div>
                    <?php endif; ?>

                    <div class="photo-actions-overlay">
                        <a href="?page=photo_bucket&delete_photo=<?php echo $photo['id']; ?>"
                           onclick="return confirm('Delete this photo?')"
                           class="action-icon-small delete" title="Delete">🗑️</a>

                        <?php
                        $fullViewPath = (!empty($photo['optimized_path']) && file_exists(__DIR__ . '/../../' . $photo['optimized_path']))
                                        ? $photo['optimized_path']
                                        : $photo['file_path'];
                        ?>
                        <a href="<?php echo $fullViewPath; ?>" target="_blank" class="action-icon-small view" title="View Optimized">👁️</a>
                        <a href="<?php echo $photo['file_path']; ?>" download class="action-icon-small download" title="Download Raw">📥</a>
                    </div>
                </div>

                <div class="photo-meta-compact">
                    <h3 title="<?php echo htmlspecialchars($photo['model_name'] ?: 'General'); ?>">
                        <?php echo htmlspecialchars($photo['model_name'] ?: 'General'); ?>
                    </h3>
                    <div class="category-row">
                        <span><?php echo htmlspecialchars($photo['category']); ?></span>
                        <button onclick="copyToClipboard('<?php echo $fullViewPath; ?>', 'Path')" style="background: none; border: none; cursor: pointer; font-size: 0.6rem; padding: 2px; opacity: 0.5;" title="Copy Path">📋</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- UPLOAD MODAL -->
<div id="upload-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="card" style="width: 100%; max-width: 500px; animation: modalIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 style="margin: 0;">Upload Marketing Photo</h2>
            <button onclick="document.getElementById('upload-modal').style.display='none'" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-dim);">×</button>
        </div>

        <form action="?page=photo_bucket" method="POST" enctype="multipart/form-data" class="standard-form">
            <div class="form-group">
                <label for="photo">Select Photo</label>
                <input type="file" name="photo" accept="image/*" required>
            </div>

            <div class="form-group">
                <label for="model_name">Hardware Model (Optional)</label>
                <input type="text" name="model_name" list="model_list" placeholder="Start typing or enter custom model...">
                <datalist id="model_list">
                    <?php foreach ($models as $model): ?>
                        <option value="<?php echo htmlspecialchars($model); ?>">
                    <?php endforeach; ?>
                </datalist>
                <small style="font-size: 0.7rem; color: var(--text-dim); display: block; margin-top: 4px;">Suggestions include Active Templates and Warehouse Inventory.</small>
            </div>

            <div class="form-group">
                <label for="category">Category</label>
                <select name="category">
                    <option value="Laptop">Laptop</option>
                    <option value="Workstation">Workstation</option>
                    <option value="Monitor">Monitor</option>
                    <option value="Parts">Parts</option>
                    <option value="Bulk Stock">Bulk Stock</option>
                    <option value="Marketing Banner">Marketing Banner</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="submit" name="upload_photo" class="btn-action" style="flex: 2;">Upload to Bucket</button>
                <button type="button" onclick="document.getElementById('upload-modal').style.display='none'" class="btn-action" style="flex: 1; background: var(--text-dim);">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
    @keyframes modalIn {
        from { opacity: 0; transform: scale(0.9) translateY(20px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
</style>
