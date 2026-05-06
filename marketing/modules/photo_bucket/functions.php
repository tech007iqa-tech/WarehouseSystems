<?php
/**
 * Photo Bucket Module - Business Logic
 */

function handle_photo_bucket_actions($marketingDb, $processor, $labelsDb) {
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
                    $stmt = $marketingDb->prepare("INSERT INTO photos (filename, original_name, model_name, category, file_path, file_size, mime_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$filename, $original_name, $model_name, $category, 'assets/photo_bucket/' . $filename, $file_size, $mime_type, 'Processing']);
                    
                    $photoId = $marketingDb->lastInsertId();
                    log_marketing_audit($marketingDb, 'PHOTO', $photoId, 'UPLOADED', "Uploaded photo: $original_name for $model_name");
                    
                    // Trigger Processing
                    $processor->process($photoId);
                    
                    $_SESSION['notify'] = ['message' => "Photo uploaded and processed!", 'type' => 'success'];
                } catch (Exception $e) {
                    $_SESSION['notify'] = ['message' => "DB Error: " . $e->getMessage(), 'type' => 'error'];
                }
            } else {
                $_SESSION['notify'] = ['message' => "Failed to move uploaded file.", 'type' => 'error'];
            }
        } else {
            $_SESSION['notify'] = ['message' => "Upload error or no file selected.", 'type' => 'error'];
        }
        header("Location: index.php?page=photo_bucket");
        exit;
    }

    // 2. Handle Delete
    if (isset($_GET['delete_photo'])) {
        $photo_id = (int)$_GET['delete_photo'];
        
        try {
            $stmt = $marketingDb->prepare("SELECT * FROM photos WHERE id = ?");
            $stmt->execute([$photo_id]);
            $photo = $stmt->fetch();
            
            if ($photo) {
                // Delete physical files
                $filesToDelete = [$photo['file_path'], $photo['thumbnail_path'], $photo['optimized_path']];
                foreach ($filesToDelete as $f) {
                    if (!empty($f)) {
                        $p = __DIR__ . '/../../' . $f;
                        if (file_exists($p)) unlink($p);
                    }
                }
                
                $stmt = $marketingDb->prepare("DELETE FROM photos WHERE id = ?");
                $stmt->execute([$photo_id]);
                
                log_marketing_audit($marketingDb, 'PHOTO', $photo_id, 'DELETED', "Deleted photo: " . $photo['filename']);
                $_SESSION['notify'] = ['message' => "Photo deleted successfully.", 'type' => 'success'];
            }
        } catch (Exception $e) {
            $_SESSION['notify'] = ['message' => "Delete failed: " . $e->getMessage(), 'type' => 'error'];
        }
        header("Location: index.php?page=photo_bucket");
        exit;
    }

    // 3. Handle Bulk Regeneration
    if (isset($_GET['regenerate_thumbnails'])) {
        $count = 0;
        $all_photos = $marketingDb->query("SELECT id FROM photos")->fetchAll();
        foreach ($all_photos as $photo) {
            if ($processor->process($photo['id'])) {
                $count++;
            }
        }
        $_SESSION['notify'] = ['message' => "Successfully processed $count photos.", 'type' => 'info'];
        header("Location: index.php?page=photo_bucket");
        exit;
    }
}

function get_photo_bucket_models($marketingDb, $labelsDb) {
    $models = [];
    $local_models = $marketingDb->query("SELECT DISTINCT model_name FROM model_templates ORDER BY model_name ASC")->fetchAll(PDO::FETCH_COLUMN);
    $models = array_merge($models, $local_models);

    if ($labelsDb) {
        $warehouse_models = $labelsDb->query("SELECT DISTINCT model FROM items ORDER BY model ASC")->fetchAll(PDO::FETCH_COLUMN);
        $models = array_merge($models, $warehouse_models);
    }

    $models = array_unique(array_filter($models));
    sort($models);
    return $models;
}
