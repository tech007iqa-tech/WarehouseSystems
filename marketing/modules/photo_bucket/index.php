<?php
/**
 * Photo Bucket Module - Marketing Hub
 * Handles management of hardware and marketing images.
 */

$marketingDb = get_marketing_db();
$labelsDb = get_labels_db();

$message = '';
$messageType = 'success';

// 1. Handle Upload
if (isset($_POST['upload_photo'])) {
    $model_name = $_POST['model_name'] ?? '';
    $category = $_POST['category'] ?? 'General';
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['photo']['tmp_name'];
        $original_name = $_FILES['photo']['name'];
        $file_size = $_FILES['photo']['size'];
        $mime_type = $_FILES['photo']['type'];
        
        $extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $filename = uniqid('img_', true) . '.' . $extension;
        $target_dir = __DIR__ . '/../../assets/photo_bucket/';
        $target_path = $target_dir . $filename;
        
        if (move_uploaded_file($file_tmp, $target_path)) {
            try {
                $stmt = $marketingDb->prepare("INSERT INTO photos (filename, original_name, model_name, category, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$filename, $original_name, $model_name, $category, 'assets/photo_bucket/' . $filename, $file_size, $mime_type]);
                
                log_marketing_audit($marketingDb, 'PHOTO', $marketingDb->lastInsertId(), 'UPLOADED', "Uploaded photo: $original_name for $model_name");
                
                $message = "Photo uploaded successfully!";
            } catch (Exception $e) {
                $message = "DB Error: " . $e->getMessage();
                $messageType = 'danger';
            }
        } else {
            $message = "Failed to move uploaded file.";
            $messageType = 'danger';
        }
    } else {
        $message = "Upload error or no file selected.";
        $messageType = 'danger';
    }
}

// 2. Handle Delete
if (isset($_GET['delete_photo'])) {
    $photo_id = (int)$_GET['delete_photo'];
    
    try {
        $stmt = $marketingDb->prepare("SELECT filename FROM photos WHERE id = ?");
        $stmt->execute([$photo_id]);
        $photo = $stmt->fetch();
        
        if ($photo) {
            $file_path = __DIR__ . '/../../assets/photo_bucket/' . $photo['filename'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            $stmt = $marketingDb->prepare("DELETE FROM photos WHERE id = ?");
            $stmt->execute([$photo_id]);
            
            log_marketing_audit($marketingDb, 'PHOTO', $photo_id, 'DELETED', "Deleted photo: " . $photo['filename']);
            $message = "Photo deleted successfully.";
        }
    } catch (Exception $e) {
        $message = "Delete failed: " . $e->getMessage();
        $messageType = 'danger';
    }
}

// 3. Fetch Photos
$photos = $marketingDb->query("SELECT * FROM photos ORDER BY created_at DESC")->fetchAll();

// 4. Fetch Models for dropdown (from Labels DB if available)
$models = [];
if ($labelsDb) {
    $models = $labelsDb->query("SELECT DISTINCT model FROM items ORDER BY model ASC")->fetchAll(PDO::FETCH_COLUMN);
}

?>

<header class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1>🖼️ Photo Bucket</h1>
            <p>Manage your marketing assets and hardware photography.</p>
        </div>
        <button onclick="document.getElementById('upload-modal').style.display='flex'" class="btn-action" style="min-width: 180px;">Upload New Photo</button>
    </div>
</header>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="photo-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
    <?php if (empty($photos)): ?>
        <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 4rem;">
            <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">📸</div>
            <h2 style="margin-bottom: 0.5rem;">No Photos Found</h2>
            <p style="color: var(--text-dim);">Start by uploading some hardware photos for your marketing campaigns.</p>
        </div>
    <?php else: ?>
        <?php foreach ($photos as $photo): ?>
            <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column;">
                <div style="aspect-ratio: 16/10; overflow: hidden; background: #f1f5f9; position: relative;">
                    <img src="<?php echo $photo['file_path']; ?>" alt="<?php echo htmlspecialchars($photo['original_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <div style="position: absolute; top: 10px; right: 10px;">
                        <a href="?page=photo_bucket&delete_photo=<?php echo $photo['id']; ?>" 
                           onclick="return confirm('Are you sure you want to delete this photo?')"
                           style="background: rgba(255, 255, 255, 0.9); width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; text-decoration: none; color: #ef4444; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                           🗑️
                        </a>
                    </div>
                </div>
                <div style="padding: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <h3 style="font-size: 0.95rem; font-weight: 800; color: var(--text-main); margin: 0;"><?php echo htmlspecialchars($photo['model_name'] ?: 'General Asset'); ?></h3>
                        <span class="badge-customer" style="font-size: 0.7rem;"><?php echo htmlspecialchars($photo['category']); ?></span>
                    </div>
                    <p style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 1rem;"><?php echo htmlspecialchars($photo['original_name']); ?></p>
                    <div style="display: flex; gap: 8px;">
                        <button class="btn-action" style="padding: 0 12px; height: 44px; font-size: 0.85rem; flex: 1;" onclick="copyToClipboard('<?php echo $photo['file_path']; ?>')">Copy Path</button>
                        <a href="<?php echo $photo['file_path']; ?>" target="_blank" class="btn-action" style="padding: 0 12px; height: 44px; font-size: 0.85rem; text-decoration: none; display: flex; align-items: center; justify-content: center; background: var(--text-main);">View Full</a>
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
                <label>Select Photo</label>
                <input type="file" name="photo" accept="image/*" required>
            </div>
            
            <div class="form-group">
                <label>Hardware Model (Optional)</label>
                <select name="model_name">
                    <option value="">-- No Specific Model --</option>
                    <?php foreach ($models as $model): ?>
                        <option value="<?php echo htmlspecialchars($model); ?>"><?php echo htmlspecialchars($model); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Category</label>
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
    
    .photo-grid .card img {
        transition: transform 0.5s ease;
    }
    
    .photo-grid .card:hover img {
        transform: scale(1.05);
    }
</style>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Path copied to clipboard: ' + text);
    });
}
</script>
