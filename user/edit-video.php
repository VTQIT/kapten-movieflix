<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$videoId = intval($_GET['id'] ?? 0);
$video = db()->fetch("SELECT * FROM videos WHERE id = ?", [$videoId]);

if (!$video || ($video['user_id'] != $_SESSION['user_id'] && !isAdmin())) {
    setFlash('error', 'Video not found or access denied.');
    header('Location: ' . SITE_URL . '/user/my-videos.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $action = $_POST['form_action'] ?? 'update';

        if ($action === 'delete') {
            @unlink(UPLOAD_DIR . $video['filename']);
            @unlink(THUMBNAIL_DIR . $video['thumbnail']);
            db()->delete("DELETE FROM videos WHERE id = ?", [$videoId]);
            db()->update("UPDATE users SET storage_used = GREATEST(0, storage_used - ?) WHERE id = ?", [$video['file_size'], $video['user_id']]);
            setFlash('success', 'Video deleted.');
            header('Location: ' . SITE_URL . '/user/my-videos.php');
            exit;
        }

        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $categoryId = intval($_POST['category_id'] ?? 0);
        $tags = sanitizeInput($_POST['tags'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['public', 'private', 'unlisted']) ? $_POST['status'] : 'public';
        $allowComments = isset($_POST['allow_comments']) ? 1 : 0;

        if (empty($title)) $errors[] = 'Title is required.';

        $thumbnailName = $video['thumbnail'];
        if (!empty($_FILES['thumbnail']['name'])) {
            $thumbFile = $_FILES['thumbnail'];
            $thumbExt = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));
            if (in_array($thumbExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']) && $thumbFile['error'] === UPLOAD_ERR_OK) {
                @unlink(THUMBNAIL_DIR . $thumbnailName);
                $thumbnailName = generateUniqueFilename($thumbExt);
                move_uploaded_file($thumbFile['tmp_name'], THUMBNAIL_DIR . $thumbnailName);
                if (function_exists('gd_info')) {
                    resizeImage(THUMBNAIL_DIR . $thumbnailName, THUMBNAIL_WIDTH, THUMBNAIL_HEIGHT);
                }
            }
        }

        if (empty($errors)) {
            db()->update(
                "UPDATE videos SET title = ?, description = ?, category_id = ?, tags = ?, status = ?, allow_comments = ?, thumbnail = ? WHERE id = ?",
                [$title, $description, $categoryId ?: null, $tags, $status, $allowComments, $thumbnailName, $videoId]
            );
            setFlash('success', 'Video updated successfully.');
            header('Location: ' . SITE_URL . '/watch.php?v=' . $videoId);
            exit;
        }
    }
}

$categories = db()->fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");

$pageTitle = 'Edit Video';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
    <div class="upload-page">
        <h1><i data-lucide="edit-2"></i> Edit Video</h1>

        <?php if (!empty($errors)): ?>
        <div class="flash-message flash-error">
            <i data-lucide="x-circle"></i>
            <ul><?php foreach ($errors as $err) echo "<li>$err</li>"; ?></ul>
        </div>
        <?php endif; ?>

        <div class="video-preview" style="margin-bottom:24px;">
            <video controls poster="<?php echo getThumbnailUrl($video['thumbnail']); ?>">
                <source src="<?php echo getVideoUrl($video['filename']); ?>" type="<?php echo $video['file_type']; ?>">
            </video>
        </div>

        <form method="POST" enctype="multipart/form-data" class="upload-form">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="update">

            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($video['title']); ?>" maxlength="255" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4"><?php echo htmlspecialchars($video['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id">
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $video['category_id'] == $cat['id'] ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Visibility</label>
                    <select name="status">
                        <option value="public" <?php echo $video['status'] === 'public' ? 'selected' : ''; ?>>Public</option>
                        <option value="unlisted" <?php echo $video['status'] === 'unlisted' ? 'selected' : ''; ?>>Unlisted</option>
                        <option value="private" <?php echo $video['status'] === 'private' ? 'selected' : ''; ?>>Private</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Tags</label>
                <input type="text" name="tags" value="<?php echo htmlspecialchars($video['tags'] ?? ''); ?>" placeholder="gaming, tutorial, fun">
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="allow_comments" <?php echo $video['allow_comments'] ? 'checked' : ''; ?>>
                    <span>Allow comments</span>
                </label>
            </div>

            <div class="form-group">
                <label>Change Thumbnail</label>
                <input type="file" name="thumbnail" accept="image/*">
                <span class="form-hint">Leave empty to keep current thumbnail</span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Changes</button>
                <a href="watch.php?v=<?php echo $videoId; ?>" class="btn btn-ghost">Cancel</a>
            </div>
        </form>

        <hr style="border-color:var(--border);margin:40px 0;">

        <form method="POST" onsubmit="return confirm('Permanently delete this video?')">
            <?php echo csrfField(); ?>
            <input type="hidden" name="form_action" value="delete">
            <button type="submit" class="btn btn-danger btn-block"><i data-lucide="trash-2"></i> Delete Video</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
