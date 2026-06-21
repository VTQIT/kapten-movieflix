<?php
require_once __DIR__ . '/includes/functions.php';

$catId = intval($_GET['id'] ?? 0);

if ($catId) {
    $category = db()->fetch("SELECT * FROM categories WHERE id = ? AND is_active = 1", [$catId]);
    if (!$category) {
        setFlash('error', 'Category not found.');
        header('Location: ' . SITE_URL . '/categories.php');
        exit;
    }

    $page = max(1, intval($_GET['page'] ?? 1));
    $total = db()->fetch("SELECT COUNT(*) as count FROM videos WHERE status = 'public' AND category_id = ?", [$catId])['count'];
    $pagination = getPagination($total, $page);

    $videos = db()->fetchAll(
        "SELECT v.*, u.username FROM videos v 
         JOIN users u ON v.user_id = u.id 
         WHERE v.status = 'public' AND v.category_id = ? 
         ORDER BY v.created_at DESC 
         LIMIT ? OFFSET ?",
        [$catId, $pagination['perPage'], $pagination['offset']]
    );

    $pageTitle = $category['name'];
} else {
    $categories = db()->fetchAll(
        "SELECT c.*, COUNT(v.id) as video_count FROM categories c 
         LEFT JOIN videos v ON c.id = v.category_id AND v.status = 'public' 
         WHERE c.is_active = 1 
         GROUP BY c.id 
         ORDER BY c.sort_order, c.name"
    );
    $pageTitle = 'Categories';
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-content">
    <?php if ($catId): ?>

    <div class="category-header" style="border-left-color: <?php echo $category['color']; ?>">
        <div class="category-icon" style="background: <?php echo $category['color']; ?>20; color: <?php echo $category['color']; ?>">
            <i data-lucide="<?php echo $category['icon']; ?>"></i>
        </div>
        <div>
            <h1><?php echo $category['name']; ?></h1>
            <p><?php echo $category['description']; ?></p>
        </div>
    </div>

    <?php if (!empty($videos)): ?>
    <div class="video-grid">
        <?php foreach ($videos as $video): ?>
        <a href="watch.php?v=<?php echo $video['id']; ?>" class="video-card">
            <div class="video-thumb">
                <img src="<?php echo getThumbnailUrl($video['thumbnail']); ?>" alt="<?php echo htmlspecialchars($video['title']); ?>" loading="lazy">
                <span class="video-duration"><?php echo formatDuration($video['duration']); ?></span>
                <div class="video-overlay">
                    <i data-lucide="play-circle"></i>
                </div>
            </div>
            <div class="video-info">
                <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                <div class="video-meta">
                    <span class="video-author"><?php echo $video['username']; ?></span>
                    <span class="dot"></span>
                    <span><?php echo formatViews($video['views']); ?> views</span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php renderPagination($pagination, "categories.php?id=$catId&"); ?>
    <?php else: ?>
    <div class="empty-state">
        <i data-lucide="film"></i>
        <h3>No videos in this category yet</h3>
        <p>Be the first to upload!</p>
    </div>
    <?php endif; ?>

    <?php else: ?>

    <h1 class="page-title">Browse Categories</h1>
    <div class="categories-grid">
        <?php foreach ($categories as $cat): ?>
        <a href="categories.php?id=<?php echo $cat['id']; ?>" class="category-card" style="--cat-color: <?php echo $cat['color']; ?>">
            <div class="category-card-icon" style="background: <?php echo $cat['color']; ?>20; color: <?php echo $cat['color']; ?>">
                <i data-lucide="<?php echo $cat['icon']; ?>"></i>
            </div>
            <h3><?php echo $cat['name']; ?></h3>
            <p><?php echo number_format($cat['video_count']); ?> video<?php echo $cat['video_count'] !== 1 ? 's' : ''; ?></p>
        </a>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
