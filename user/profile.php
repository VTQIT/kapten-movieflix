<?php
require_once __DIR__ . '/../includes/functions.php';

$userId = intval($_GET['id'] ?? ($_SESSION['user_id'] ?? 0));
if (!$userId) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user = db()->fetch("SELECT id, username, avatar, bio, created_at FROM users WHERE id = ? AND status = 'active'", [$userId]);
if (!$user) {
    setFlash('error', 'User not found.');
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$isOwnProfile = isLoggedIn() && $_SESSION['user_id'] == $userId;
$isSubscribed = false;

if (isLoggedIn() && !$isOwnProfile) {
    $isSubscribed = db()->fetch("SELECT id FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?", [$_SESSION['user_id'], $userId]);
}

$subCount = db()->fetch("SELECT COUNT(*) as c FROM subscriptions WHERE channel_id = ?", [$userId])['c'];

$page = max(1, intval($_GET['page'] ?? 1));
$total = db()->fetch("SELECT COUNT(*) as count FROM videos WHERE user_id = ? AND status = 'public'", [$userId])['count'];
$pagination = getPagination($total, $page, 12);

$videos = db()->fetchAll(
    "SELECT v.* FROM videos v 
     WHERE v.user_id = ? AND v.status = 'public' 
     ORDER BY v.created_at DESC 
     LIMIT ? OFFSET ?",
    [$userId, $pagination['perPage'], $pagination['offset']]
);

$stats = db()->fetch(
    "SELECT COUNT(*) as total, SUM(views) as views, SUM(likes_count) as likes FROM videos WHERE user_id = ?",
    [$userId]
);

$pageTitle = $user['username'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">
    <div class="profile-header">
        <div class="profile-info">
            <img src="<?php echo getAvatarUrl($user['avatar'] ?? 'default-avatar.png'); ?>" alt="" class="profile-avatar-lg">
            <div>
                <h1><?php echo $user['username']; ?></h1>
                <p><?php echo $user['bio'] ?? 'No bio yet.'; ?></p>
                <span class="profile-joined">Joined <?php echo date('F Y', strtotime($user['created_at'])); ?></span>
            </div>
        </div>
        <div class="profile-actions">
            <div class="profile-stats">
                <div class="profile-stat">
                    <span class="profile-stat-value"><?php echo formatViews($subCount); ?></span>
                    <span class="profile-stat-label">Subscribers</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-value"><?php echo number_format($stats['total'] ?? 0); ?></span>
                    <span class="profile-stat-label">Videos</span>
                </div>
                <div class="profile-stat">
                    <span class="profile-stat-value"><?php echo formatViews($stats['views'] ?? 0); ?></span>
                    <span class="profile-stat-label">Views</span>
                </div>
            </div>

            <?php if ($isOwnProfile): ?>
            <a href="my-videos.php" class="btn btn-primary"><i data-lucide="video"></i> My Videos</a>
            <?php elseif (isLoggedIn()): ?>
            <button class="btn btn-primary subscribe-btn" data-channel="<?php echo $userId; ?>" data-subscribed="<?php echo $isSubscribed ? '1' : '0'; ?>" onclick="toggleSubscribe(this)">
                <?php echo $isSubscribed ? 'Subscribed' : 'Subscribe'; ?>
            </button>
            <?php else: ?>
            <a href="../login.php" class="btn btn-primary">Subscribe</a>
            <?php endif; ?>
        </div>
    </div>

    <h2 class="section-title">Videos</h2>

    <?php if (!empty($videos)): ?>
    <div class="video-grid">
        <?php foreach ($videos as $video): ?>
        <a href="../watch.php?v=<?php echo $video['id']; ?>" class="video-card">
            <div class="video-thumb">
                <img src="<?php echo getThumbnailUrl($video['thumbnail']); ?>" alt="" loading="lazy">
                <span class="video-duration"><?php echo formatDuration($video['duration']); ?></span>
                <div class="video-overlay">
                    <i data-lucide="play-circle"></i>
                </div>
            </div>
            <div class="video-info">
                <h3 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                <div class="video-meta">
                    <span><?php echo formatViews($video['views']); ?> views</span>
                    <span class="dot"></span>
                    <span><?php echo timeAgo($video['created_at']); ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php renderPagination($pagination, "profile.php?id=$userId&"); ?>
    <?php else: ?>
    <div class="empty-state">
        <i data-lucide="video-off"></i>
        <h3>No public videos</h3>
        <p>This user hasn't uploaded any public videos yet.</p>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleSubscribe(btn) {
    const channelId = btn.dataset.channel;
    fetch('../ajax/subscribe.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'channel_id=' + channelId + '&<?php echo CSRF_TOKEN_NAME; ?>=<?php echo generateCSRFToken(); ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btn.textContent = data.subscribed ? 'Subscribed' : 'Subscribe';
            btn.dataset.subscribed = data.subscribed ? '1' : '0';
            btn.classList.toggle('btn-primary', !data.subscribed);
            btn.classList.toggle('btn-secondary', data.subscribed);
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
