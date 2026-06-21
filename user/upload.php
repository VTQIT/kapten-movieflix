<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $categoryId = intval($_POST['category_id'] ?? 0);
        $tags = sanitizeInput($_POST['tags'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['public', 'private', 'unlisted']) ? $_POST['status'] : 'public';
        $allowComments = isset($_POST['allow_comments']) ? 1 : 0;

        if (empty($title)) $errors[] = 'Title is required.';
        if (strlen($title) > 255) $errors[] = 'Title must be under 255 characters.';

        // Video upload
        if (empty($_FILES['video']['name'])) {
            $errors[] = 'Please select a video file.';
        } else {
            $videoFile = $_FILES['video'];
            $videoExt = strtolower(pathinfo($videoFile['name'], PATHINFO_EXTENSION));

            if (!in_array($videoExt, ALLOWED_VIDEO_EXTS)) {
                $errors[] = 'Invalid video format. Allowed: ' . implode(', ', ALLOWED_VIDEO_EXTS);
            }
            if ($videoFile['size'] > MAX_UPLOAD_SIZE) {
                $errors[] = 'Video file too large. Max: ' . formatFileSize(MAX_UPLOAD_SIZE);
            }
            if ($videoFile['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Upload error code: ' . $videoFile['error'];
            }
        }

        // Thumbnail upload (optional)
        $thumbnailName = 'default-thumb.jpg';
        if (!empty($_FILES['thumbnail']['name'])) {
            $thumbFile = $_FILES['thumbnail'];
            $thumbExt = strtolower(pathinfo($thumbFile['name'], PATHINFO_EXTENSION));
            $allowedImageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($thumbExt, $allowedImageExts)) {
                $errors[] = 'Invalid thumbnail format. Allowed: ' . implode(', ', $allowedImageExts);
            } elseif ($thumbFile['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Thumbnail too large. Max: 5MB';
            } elseif ($thumbFile['error'] === UPLOAD_ERR_OK) {
                $thumbnailName = generateUniqueFilename($thumbExt);
                move_uploaded_file($thumbFile['tmp_name'], THUMBNAIL_DIR . $thumbnailName);

                // Resize thumbnail
                if (function_exists('gd_info')) {
                    resizeImage(THUMBNAIL_DIR . $thumbnailName, THUMBNAIL_WIDTH, THUMBNAIL_HEIGHT);
                }
            }
        }

        if (empty($errors)) {
            $videoName = generateUniqueFilename($videoExt);

            if (move_uploaded_file($videoFile['tmp_name'], UPLOAD_DIR . $videoName)) {
                // Get video duration if possible
                $duration = 0;
                if (function_exists('shell_exec')) {
                    $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg(UPLOAD_DIR . $videoName) . " 2>/dev/null";
                    $output = shell_exec($cmd);
                    if ($output) $duration = round(floatval(trim($output)));
                }

                $fileSize = filesize(UPLOAD_DIR . $videoName);
                $fileType = $videoFile['type'];

                $videoId = db()->insert(
                    "INSERT INTO videos (user_id, title, description, filename, thumbnail, duration, file_size, file_type, category_id, tags, status, allow_comments, processing_status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')",
                    [$_SESSION['user_id'], $title, $description, $videoName, $thumbnailName, $duration, $fileSize, $fileType, $categoryId ?: null, $tags, $status, $allowComments]
                );

                if ($videoId) {
                    // Update user storage
                    db()->update("UPDATE users SET storage_used = storage_used + ? WHERE id = ?", [$fileSize, $_SESSION['user_id']]);

                    setFlash('success', 'Video uploaded successfully!');
                    header('Location: ' . SITE_URL . '/watch.php?v=' . $videoId);
                    exit;
                } else {
                    $errors[] = 'Failed to save video to database.';
                }
            } else {
                $errors[] = 'Failed to move uploaded file.';
            }
        }
    }
}

$categories = db()->fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");

$pageTitle = 'Upload Video';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
    <div class="upload-page">
        <h1><i data-lucide="upload-cloud"></i> Upload Video</h1>

        <?php if (!empty($errors)): ?>
        <div class="flash-message flash-error">
            <i data-lucide="x-circle"></i>
            <ul><?php foreach ($errors as $err) echo "<li>$err</li>"; ?></ul>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="upload-form">
            <?php echo csrfField(); ?>

            <div class="upload-dropzone" id="dropzone">
                <i data-lucide="video"></i>
                <p>Drag & drop your video here or click to browse</p>
                <span class="upload-hint">MP4, WebM, MOV up to <?php echo formatFileSize(MAX_UPLOAD_SIZE); ?></span>
                <input type="file" name="video" id="videoInput" accept="video/mp4,video/webm,video/quicktime" required>
            </div>

            <div class="upload-details">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" maxlength="255" required placeholder="Give your video a catchy title">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4" placeholder="Tell viewers about your video..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">Select category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Visibility</label>
                        <select name="status">
                            <option value="public">Public</option>
                            <option value="unlisted">Unlisted</option>
                            <option value="private">Private</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Tags (comma separated)</label>
                    <input type="text" name="tags" placeholder="gaming, tutorial, fun">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="allow_comments" checked>
                        <span>Allow comments</span>
                    </label>
                </div>

                <div class="form-group">
                    <label>Thumbnail (optional)</label>
                    <input type="file" name="thumbnail" accept="image/*">
                    <span class="form-hint">JPG, PNG, GIF, WebP. Recommended: 640x360</span>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block">
                    <i data-lucide="upload"></i> Upload Video
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const dropzone = document.getElementById('dropzone');
const videoInput = document.getElementById('videoInput');

dropzone.addEventListener('click', () => videoInput.click());

dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('dragover');
});

dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('dragover');
});

dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        videoInput.files = e.dataTransfer.files;
        updateDropzone(e.dataTransfer.files[0].name);
    }
});

videoInput.addEventListener('change', () => {
    if (videoInput.files.length) {
        updateDropzone(videoInput.files[0].name);
    }
});

function updateDropzone(filename) {
    dropzone.innerHTML = '<i data-lucide="check-circle"></i><p><strong>' + filename + '</strong></p><span class="upload-hint">Click to change file</span>';
    dropzone.appendChild(videoInput);
    if (typeof lucide !== 'undefined') lucide.createIcons();
}
</script>

<?php
function resizeImage($path, $maxW, $maxH) {
    list($w, $h, $type) = getimagesize($path);
    $ratio = min($maxW / $w, $maxH / $h);
    $newW = round($w * $ratio);
    $newH = round($h * $ratio);

    $src = match($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($path),
        IMAGETYPE_PNG => imagecreatefrompng($path),
        IMAGETYPE_GIF => imagecreatefromgif($path),
        default => null
    };
    if (!$src) return;

    $dst = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

    match($type) {
        IMAGETYPE_JPEG => imagejpeg($dst, $path, 90),
        IMAGETYPE_PNG => imagepng($dst, $path, 6),
        IMAGETYPE_GIF => imagegif($dst, $path),
        default => null
    };

    imagedestroy($src);
    imagedestroy($dst);
}
require_once __DIR__ . '/../includes/footer.php';
?>
