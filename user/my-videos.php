<?php
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$page = max(1, intval($_GET['page'] ?? 1));
$statusFilter = sanitizeInput($_GET['status'] ?? '');

$where = ["user_id = ?"];
$params = [$_SESSION['user_id']];

if ($statusFilter) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

$whereStr = implode(' AND ', $where);

$total = db()->fetch("SELECT COUNT(*) as count FROM videos WHERE $whereStr", $params)['count'];
$pagination = getPagination($total, $page, 12);

$videos = db()->fetchAll(
    "SELECT v.*, c.name as category_name FROM videos v 
     LEFT JOIN categories c ON v.category_id = c.id 
     WHERE $whereStr 
     ORDER BY v.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [$pagination['perPage'], $pagination['offset']])
);

$stats = db()->fetch(
    "SELECT COUNT(*) as total, SUM(views) as views, SUM(likes_count) as likes, SUM(file_size) as storage 
     FROM videos WHERE user_id = ?",
    [$_SESSION['user_id']]
);

$pageTitle = 'My Videos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
    <div class="profile-header">
        <div class="profile-info">
            <img src="<?php echo getAvatarUrl($currentUser['avatar'] ?? 'default-avatar.png'); ?>" alt="" class="profile-avatar-lg">
            <div>
                <h1><?php echo $currentUser['username']; ?></h1>
                <p><?php echo $currentUser['email']; ?></p>
            </div>
        </div>
        <div class="profile-stats">
            <div class="profile-stat">
                <span class="profile-stat-value"><?php echo number_format($stats['total'] ?? 0); ?></span>
                <span class="profile-stat-label">Videos</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?php echo formatViews($stats['views'] ?? 0); ?></span>
                <span class="profile-stat-label">Views</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?php echo formatViews($stats['likes'] ?? 0); ?></span>
                <span class="profile-stat-label">Likes</span>
            </div>
            <div class="profile-stat">
                <span class="profile-stat-value"><?php echo formatFileSize($stats['storage'] ?? 0); ?></span>
                <span class="profile-stat-label">Storage</span>
            </div>
        </div>
    </div>

    <div class="my-videos-header">
        <h2>My Videos</h2>
        <div class="my-videos-filters">
            <a href="my-videos.php" class="btn btn-sm <?php echo !$statusFilter ? 'btn-primary' : 'btn-ghost'; ?>">All</a>
            <a href="my-videos.php?status=public" class="btn btn-sm <?php echo $statusFilter === 'public' ? 'btn-primary' : 'btn-ghost'; ?>">Public</a>
            <a href="my-videos.php?status=private" class="btn btn-sm <?php echo $statusFilter === 'private' ? 'btn-primary' : 'btn-ghost'; ?>">Private</a>
            <a href="my-videos.php?status=unlisted" class="btn btn-sm <?php echo $statusFilter === 'unlisted' ? 'btn-primary' : 'btn-ghost'; ?>">Unlisted</a>
            <a href="upload.php" class="btn btn-sm btn-primary"><i data-lucide="upload"></i> Upload</a>
        </div>
    </div>

    <?php if (!empty($videos)): ?>
    <div class="video-grid">
        <?php foreach ($videos as $video): ?>
        <div class="video-card video-card-manage">
            <a href="../watch.php?v=<?php echo $video['id']; ?>" class="video-thumb">
                <img src="<?php echo getThumbnailUrl($video['thumbnail']); ?>" alt="" loading="lazy">
                <span class="video-duration"><?php echo formatDuration($video['duration']); ?></span>
                <div class="video-overlay">
                    <i data-lucide="play-circle"></i>
                </div>
            </a>
            <div class="video-info">
                <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                <div class="video-meta">
                    <span class="badge-status <?php echo $video['status']; ?>"><?php echo ucfirst($video['status']); ?></span>
                    <span><?php echo formatViews($video['views']); ?> views</span>
                    <span><?php echo formatViews($video['likes_count']); ?> likes</span>
                </div>
                <div class="video-manage-actions">
                    <a href="edit-video.php?id=<?php echo $video['id']; ?>" class="btn-icon" title="Edit"><i data-lucide="edit-2"></i></a>
                    <a href="../watch.php?v=<?php echo $video['id']; ?>" class="btn-icon" title="Watch"><i data-lucide="play"></i></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php renderPagination($pagination, "my-videos.php" . ($statusFilter ? "?status=$statusFilter&" : "?")); ?>
    <?php else: ?>
    <div class="empty-state">
        <i data-lucide="video-off"></i>
        <h3>No videos yet</h3>
        <p>Upload your first video to get started!</p>
        <a href="upload.php" class="btn btn-primary"><i data-lucide="upload"></i> Upload Video</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
