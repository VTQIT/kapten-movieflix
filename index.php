<?php
require_once __DIR__ . '/includes/functions.php';

// Hero video (featured)
$heroVideo = db()->fetch(
    "SELECT v.*, u.username, u.avatar FROM videos v 
     JOIN users u ON v.user_id = u.id 
     WHERE v.status = 'public' AND v.featured = 1 
     ORDER BY v.created_at DESC LIMIT 1"
);

// If no featured, get most viewed
if (!$heroVideo) {
    $heroVideo = db()->fetch(
        "SELECT v.*, u.username, u.avatar FROM videos v 
         JOIN users u ON v.user_id = u.id 
         WHERE v.status = 'public' 
         ORDER BY v.views DESC LIMIT 1"
    );
}

// Categories with videos
$categories = db()->fetchAll(
    "SELECT c.*, COUNT(v.id) as video_count FROM categories c 
     LEFT JOIN videos v ON c.id = v.category_id AND v.status = 'public' 
     WHERE c.is_active = 1 
     GROUP BY c.id 
     HAVING video_count > 0 
     ORDER BY c.sort_order, c.name"
);

// Trending videos (last 7 days)
$trending = db()->fetchAll(
    "SELECT v.*, u.username FROM videos v 
     JOIN users u ON v.user_id = u.id 
     WHERE v.status = 'public' 
     ORDER BY v.views DESC 
     LIMIT 12"
);

// Recent uploads
$recent = db()->fetchAll(
    "SELECT v.*, u.username FROM videos v 
     JOIN users u ON v.user_id = u.id 
     WHERE v.status = 'public' 
     ORDER BY v.created_at DESC 
     LIMIT 12"
);

// Most liked
$mostLiked = db()->fetchAll(
    "SELECT v.*, u.username FROM videos v 
     JOIN users u ON v.user_id = u.id 
     WHERE v.status = 'public' 
     ORDER BY v.likes_count DESC 
     LIMIT 12"
);

$pageTitle = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<?php if ($heroVideo): ?>
<div class="hero-section" style="background-image: linear-gradient(to top, var(--bg) 0%, transparent 60%), url('<?php echo getThumbnailUrl($heroVideo['thumbnail']); ?>');">
    <div class="hero-content">
        <div class="hero-meta">
            <span class="hero-badge">Featured</span>
            <span class="hero-views"><i data-lucide="eye"></i> <?php echo formatViews($heroVideo['views']); ?> views</span>
        </div>
        <h1 class="hero-title"><?php echo htmlspecialchars($heroVideo['title']); ?></h1>
        <p class="hero-description"><?php echo htmlspecialchars(substr($heroVideo['description'] ?? '', 0, 200)); ?><?php echo strlen($heroVideo['description'] ?? '') > 200 ? '...' : ''; ?></p>
        <div class="hero-actions">
            <a href="watch.php?v=<?php echo $heroVideo['id']; ?>" class="btn btn-primary btn-lg">
                <i data-lucide="play"></i> Watch Now
            </a>
            <a href="watch.php?v=<?php echo $heroVideo['id']; ?>" class="btn btn-secondary btn-lg">
                <i data-lucide="info"></i> More Info
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="page-content">
    <!-- Trending -->
    <?php if (!empty($trending)): ?>
    <section class="video-section">
        <div class="section-header-row">
            <h2 class="section-title"><i data-lucide="trending-up"></i> Trending Now</h2>
            <a href="search.php?sort=views" class="see-all">See All <i data-lucide="chevron-right"></i></a>
        </div>
        <div class="video-grid">
            <?php foreach ($trending as $video): ?>
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
    </section>
    <?php endif; ?>

    <!-- Recent Uploads -->
    <?php if (!empty($recent)): ?>
    <section class="video-section">
        <div class="section-header-row">
            <h2 class="section-title"><i data-lucide="clock"></i> New Releases</h2>
            <a href="search.php?sort=newest" class="see-all">See All <i data-lucide="chevron-right"></i></a>
        </div>
        <div class="video-grid">
            <?php foreach ($recent as $video): ?>
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
    </section>
    <?php endif; ?>

    <!-- Categories -->
    <?php foreach ($categories as $cat): 
        $catVideos = db()->fetchAll(
            "SELECT v.*, u.username FROM videos v 
             JOIN users u ON v.user_id = u.id 
             WHERE v.status = 'public' AND v.category_id = ? 
             ORDER BY v.created_at DESC LIMIT 8",
            [$cat['id']]
        );
        if (empty($catVideos)) continue;
    ?>
    <section class="video-section">
        <div class="section-header-row">
            <h2 class="section-title" style="color: <?php echo $cat['color']; ?>">
                <i data-lucide="<?php echo $cat['icon']; ?>"></i> <?php echo $cat['name']; ?>
            </h2>
            <a href="categories.php?id=<?php echo $cat['id']; ?>" class="see-all">See All <i data-lucide="chevron-right"></i></a>
        </div>
        <div class="video-grid">
            <?php foreach ($catVideos as $video): ?>
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
    </section>
    <?php endforeach; ?>

    <!-- Most Liked -->
    <?php if (!empty($mostLiked)): ?>
    <section class="video-section">
        <div class="section-header-row">
            <h2 class="section-title"><i data-lucide="heart"></i> Most Liked</h2>
            <a href="search.php?sort=likes" class="see-all">See All <i data-lucide="chevron-right"></i></a>
        </div>
        <div class="video-grid">
            <?php foreach ($mostLiked as $video): ?>
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
                        <span><i data-lucide="heart" style="width:12px;height:12px;display:inline;vertical-align:middle;"></i> <?php echo formatViews($video['likes_count']); ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
