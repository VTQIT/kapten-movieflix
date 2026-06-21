<?php
require_once __DIR__ . '/includes/functions.php';

$query = sanitizeInput($_GET['q'] ?? '');
$sort = sanitizeInput($_GET['sort'] ?? 'relevance');
$category = intval($_GET['category'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));

$where = ["v.status = 'public'"];
$params = [];

if ($query) {
    $where[] = "(v.title LIKE ? OR v.description LIKE ? OR v.tags LIKE ?)";
    $params[] = "%$query%";
    $params[] = "%$query%";
    $params[] = "%$query%";
}
if ($category) {
    $where[] = "v.category_id = ?";
    $params[] = $category;
}

$whereStr = implode(' AND ', $where);

$orderBy = match($sort) {
    'newest' => 'v.created_at DESC',
    'oldest' => 'v.created_at ASC',
    'views' => 'v.views DESC',
    'likes' => 'v.likes_count DESC',
    default => 'MATCH(v.title, v.description, v.tags) AGAINST(? IN BOOLEAN MODE) DESC, v.views DESC'
};

if ($sort === 'relevance' && $query) {
    $params[] = $query;
}

$total = db()->fetch("SELECT COUNT(*) as count FROM videos v WHERE $whereStr", $params)['count'];
$pagination = getPagination($total, $page);

$videos = db()->fetchAll(
    "SELECT v.*, u.username FROM videos v 
     JOIN users u ON v.user_id = u.id 
     WHERE $whereStr 
     ORDER BY $orderBy 
     LIMIT ? OFFSET ?",
    array_merge($params, [$pagination['perPage'], $pagination['offset']])
);

$categories = db()->fetchAll("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");

$pageTitle = $query ? "Search: $query" : 'Browse Videos';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-content">
    <div class="search-header">
        <h1><?php echo $query ? 'Search Results' : 'Browse All Videos'; ?></h1>
        <?php if ($query): ?>
        <p><?php echo number_format($total); ?> result<?php echo $total !== 1 ? 's' : ''; ?> for "<?php echo htmlspecialchars($query); ?>"</p>
        <?php endif; ?>
    </div>

    <div class="search-filters">
        <form method="GET" class="filter-bar">
            <?php if ($query): ?>
            <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
            <?php endif; ?>
            <select name="sort" onchange="this.form.submit()">
                <option value="relevance" <?php echo $sort === 'relevance' ? 'selected' : ''; ?>>Relevance</option>
                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                <option value="views" <?php echo $sort === 'views' ? 'selected' : ''; ?>>Most Viewed</option>
                <option value="likes" <?php echo $sort === 'likes' ? 'selected' : ''; ?>>Most Liked</option>
            </select>
            <select name="category" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo $cat['name']; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
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
                    <span class="dot"></span>
                    <span><?php echo timeAgo($video['created_at']); ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php renderPagination($pagination, "search.php" . ($query ? "?q=" . urlencode($query) . "&" : "?")); ?>

    <?php else: ?>
    <div class="empty-state">
        <i data-lucide="search-x"></i>
        <h3>No videos found</h3>
        <p>Try adjusting your search or filters.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
