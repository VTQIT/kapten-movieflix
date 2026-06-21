<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$action = $_GET['action'] ?? 'list';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', 'Invalid request token.');
        header('Location: videos.php');
        exit;
    }

    $videoId = intval($_POST['video_id'] ?? 0);

    switch ($_POST['action']) {
        case 'update':
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $status = in_array($_POST['status'], ['public', 'private', 'unlisted', 'blocked']) ? $_POST['status'] : 'public';
            $categoryId = intval($_POST['category_id']) ?: null;
            $featured = isset($_POST['featured']) ? 1 : 0;
            $allowComments = isset($_POST['allow_comments']) ? 1 : 0;

            db()->update(
                "UPDATE videos SET title = ?, description = ?, status = ?, category_id = ?, featured = ?, allow_comments = ? WHERE id = ?",
                [$title, $description, $status, $categoryId, $featured, $allowComments, $videoId]
            );

            setFlash('success', 'Video updated successfully.');
            header('Location: videos.php');
            exit;

        case 'delete':
            $video = db()->fetch("SELECT filename, thumbnail FROM videos WHERE id = ?", [$videoId]);
            if ($video) {
                @unlink(UPLOAD_DIR . $video['filename']);
                @unlink(THUMBNAIL_DIR . $video['thumbnail']);
                db()->delete("DELETE FROM videos WHERE id = ?", [$videoId]);
                setFlash('success', 'Video deleted successfully.');
            }
            header('Location: videos.php');
            exit;
    }
}

// Get videos list
$page = max(1, intval($_GET['page'] ?? 1));
$search = sanitizeInput($_GET['search'] ?? '');
$statusFilter = sanitizeInput($_GET['status'] ?? '');
$categoryFilter = intval($_GET['category'] ?? 0);

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(v.title LIKE ? OR v.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter) {
    $where[] = "v.status = ?";
    $params[] = $statusFilter;
}
if ($categoryFilter) {
    $where[] = "v.category_id = ?";
    $params[] = $categoryFilter;
}

$whereStr = implode(' AND ', $where);

$totalVideos = db()->fetch("SELECT COUNT(*) as count FROM videos v WHERE $whereStr", $params)['count'];
$pagination = getPagination($totalVideos, $page, 20);

$videos = db()->fetchAll(
    "SELECT v.*, u.username, c.name as category_name 
     FROM videos v 
     JOIN users u ON v.user_id = u.id 
     LEFT JOIN categories c ON v.category_id = c.id 
     WHERE $whereStr 
     ORDER BY v.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [$pagination['perPage'], $pagination['offset']])
);

$categories = db()->fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");

$pageTitle = 'Manage Videos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-page">
    <aside class="admin-sidebar">
        <div class="sidebar-header">
            <i data-lucide="shield"></i>
            <span>Admin Panel</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
            <a href="users.php"><i data-lucide="users"></i> Users</a>
            <a href="videos.php" class="active"><i data-lucide="film"></i> Videos</a>
            <a href="comments.php"><i data-lucide="message-square"></i> Comments</a>
            <a href="settings.php"><i data-lucide="settings"></i> Settings</a>
            <a href="../logout.php"><i data-lucide="log-out"></i> Logout</a>
        </nav>
    </aside>

    <div class="admin-content">
        <div class="admin-header">
            <h1>Manage Videos</h1>
        </div>

        <?php if ($action === 'edit' && isset($_GET['id'])): 
            $editVideo = db()->fetch(
                "SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id WHERE v.id = ?", 
                [intval($_GET['id'])]
            );
            if ($editVideo):
        ?>
        <div class="admin-section">
            <h2>Edit Video: <?php echo $editVideo['title']; ?></h2>
            <div class="video-preview">
                <video controls poster="<?php echo getThumbnailUrl($editVideo['thumbnail']); ?>">
                    <source src="<?php echo getVideoUrl($editVideo['filename']); ?>" type="<?php echo $editVideo['file_type']; ?>">
                </video>
            </div>
            <form method="POST" class="admin-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="video_id" value="<?php echo $editVideo['id']; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" value="<?php echo $editVideo['title']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Uploader</label>
                        <input type="text" value="<?php echo $editVideo['username']; ?>" disabled>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4"><?php echo $editVideo['description']; ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="public" <?php echo $editVideo['status'] === 'public' ? 'selected' : ''; ?>>Public</option>
                            <option value="private" <?php echo $editVideo['status'] === 'private' ? 'selected' : ''; ?>>Private</option>
                            <option value="unlisted" <?php echo $editVideo['status'] === 'unlisted' ? 'selected' : ''; ?>>Unlisted</option>
                            <option value="blocked" <?php echo $editVideo['status'] === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id">
                            <option value="">Uncategorized</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $editVideo['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo $cat['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="featured" <?php echo $editVideo['featured'] ? 'checked' : ''; ?>>
                            <span>Featured Video</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="allow_comments" <?php echo $editVideo['allow_comments'] ? 'checked' : ''; ?>>
                            <span>Allow Comments</span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Changes</button>
                    <a href="videos.php" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
        <?php endif; endif; ?>

        <!-- Filters -->
        <div class="admin-section">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search videos..." value="<?php echo $search; ?>">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="public" <?php echo $statusFilter === 'public' ? 'selected' : ''; ?>>Public</option>
                        <option value="private" <?php echo $statusFilter === 'private' ? 'selected' : ''; ?>>Private</option>
                        <option value="unlisted" <?php echo $statusFilter === 'unlisted' ? 'selected' : ''; ?>>Unlisted</option>
                        <option value="blocked" <?php echo $statusFilter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                        <option value="processing" <?php echo $statusFilter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    </select>
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo $cat['name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="search"></i> Filter</button>
                    <a href="videos.php" class="btn btn-ghost btn-sm">Clear</a>
                </div>
            </form>
        </div>

        <!-- Videos Table -->
        <div class="admin-section">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Video</th>
                            <th>Uploader</th>
                            <th>Views</th>
                            <th>Likes</th>
                            <th>Status</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($videos as $video): ?>
                        <tr>
                            <td><?php echo $video['id']; ?></td>
                            <td>
                                <div class="video-cell">
                                    <img src="<?php echo getThumbnailUrl($video['thumbnail']); ?>" alt="">
                                    <span><?php echo truncateText($video['title'], 40); ?></span>
                                </div>
                            </td>
                            <td><?php echo $video['username']; ?></td>
                            <td><?php echo formatViews($video['views']); ?></td>
                            <td><?php echo number_format($video['likes_count']); ?></td>
                            <td><span class="badge-status <?php echo $video['status']; ?>"><?php echo ucfirst($video['status']); ?></span></td>
                            <td><?php echo formatFileSize($video['file_size']); ?></td>
                            <td><?php echo timeAgo($video['created_at']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="../watch.php?v=<?php echo $video['id']; ?>" class="btn-icon" title="Watch" target="_blank"><i data-lucide="play"></i></a>
                                    <a href="videos.php?action=edit&id=<?php echo $video['id']; ?>" class="btn-icon" title="Edit"><i data-lucide="edit-2"></i></a>
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete this video?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                        <button type="submit" class="btn-icon btn-danger" title="Delete"><i data-lucide="trash-2"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php renderPagination($pagination, 'videos.php'); ?>
        </div>
    </div>
</div>

<?php 
function truncateText($text, $length) {
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}
require_once __DIR__ . '/../includes/footer.php'; 
?>
