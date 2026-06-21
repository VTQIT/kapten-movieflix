<?php
require_once __DIR__ . '/includes/functions.php';

$videoId = intval($_GET['v'] ?? 0);
if (!$videoId) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$video = db()->fetch(
    "SELECT v.*, u.username, u.avatar as user_avatar, c.name as category_name, c.slug as category_slug 
     FROM videos v 
     JOIN users u ON v.user_id = u.id 
     LEFT JOIN categories c ON v.category_id = c.id 
     WHERE v.id = ? AND (v.status = 'public' OR v.user_id = ? OR ? = 1)",
    [$videoId, $_SESSION['user_id'] ?? 0, isAdmin() ? 1 : 0]
);

if (!$video) {
    setFlash('error', 'Video not found or unavailable.');
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

// Increment views
db()->update("UPDATE videos SET views = views + 1 WHERE id = ?", [$videoId]);
$video['views']++;

// Watch history
if (isLoggedIn()) {
    db()->query(
        "INSERT INTO watch_history (user_id, video_id, progress) VALUES (?, ?, 0) 
         ON DUPLICATE KEY UPDATE watched_at = NOW()",
        [$_SESSION['user_id'], $videoId]
    );
}

// Check if user liked
$userLiked = false;
$userDisliked = false;
if (isLoggedIn()) {
    $likeRecord = db()->fetch(
        "SELECT type FROM likes WHERE video_id = ? AND user_id = ?",
        [$videoId, $_SESSION['user_id']]
    );
    if ($likeRecord) {
        $userLiked = $likeRecord['type'] === 'like';
        $userDisliked = $likeRecord['type'] === 'dislike';
    }
}

// Comments
$comments = db()->fetchAll(
    "SELECT c.*, u.username, u.avatar 
     FROM comments c 
     JOIN users u ON c.user_id = u.id 
     WHERE c.video_id = ? AND c.status = 'active' AND c.parent_id IS NULL 
     ORDER BY c.is_pinned DESC, c.created_at DESC 
     LIMIT 50",
    [$videoId]
);

// Related videos
$related = db()->fetchAll(
    "SELECT v.*, u.username FROM videos v 
     JOIN users u ON v.user_id = u.id 
     WHERE v.status = 'public' AND v.id != ? AND (v.category_id = ? OR v.category_id IS NULL) 
     ORDER BY RAND() LIMIT 8",
    [$videoId, $video['category_id'] ?? 0]
);

$pageTitle = $video['title'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="watch-page">
    <div class="watch-layout">
        <!-- Main Video Area -->
        <div class="watch-main">
            <div class="video-player-container">
                <video id="mainPlayer" class="video-player" controls poster="<?php echo getThumbnailUrl($video['thumbnail']); ?>" preload="metadata">
                    <source src="<?php echo getVideoUrl($video['filename']); ?>" type="<?php echo $video['file_type']; ?>">
                    Your browser does not support the video tag.
                </video>
            </div>

            <div class="video-details">
                <h1 class="video-page-title"><?php echo htmlspecialchars($video['title']); ?></h1>

                <div class="video-stats-bar">
                    <div class="video-stats">
                        <span><i data-lucide="eye"></i> <?php echo formatViews($video['views']); ?> views</span>
                        <span class="dot"></span>
                        <span><?php echo timeAgo($video['created_at']); ?></span>
                        <?php if ($video['category_name']): ?>
                        <span class="dot"></span>
                        <a href="categories.php?id=<?php echo $video['category_id']; ?>" class="category-link"><?php echo $video['category_name']; ?></a>
                        <?php endif; ?>
                    </div>

                    <div class="video-actions-bar">
                        <?php if (isLoggedIn()): ?>
                        <button class="action-btn <?php echo $userLiked ? 'active' : ''; ?>" onclick="handleLike(<?php echo $videoId; ?>, 'like')" id="btnLike">
                            <i data-lucide="thumbs-up"></i>
                            <span id="likeCount"><?php echo formatViews($video['likes_count']); ?></span>
                        </button>
                        <button class="action-btn <?php echo $userDisliked ? 'active' : ''; ?>" onclick="handleLike(<?php echo $videoId; ?>, 'dislike')" id="btnDislike">
                            <i data-lucide="thumbs-down"></i>
                        </button>
                        <?php else: ?>
                        <a href="login.php" class="action-btn">
                            <i data-lucide="thumbs-up"></i>
                            <span><?php echo formatViews($video['likes_count']); ?></span>
                        </a>
                        <?php endif; ?>

                        <?php if (isLoggedIn() && ($video['user_id'] == ($_SESSION['user_id'] ?? 0) || isAdmin())): ?>
                        <a href="user/edit-video.php?id=<?php echo $videoId; ?>" class="action-btn">
                            <i data-lucide="edit-2"></i> Edit
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Uploader Info -->
                <div class="uploader-info">
                    <a href="user/profile.php?id=<?php echo $video['user_id']; ?>" class="uploader-avatar">
                        <img src="<?php echo getAvatarUrl($video['user_avatar'] ?? 'default-avatar.png'); ?>" alt="<?php echo $video['username']; ?>">
                    </a>
                    <div class="uploader-details">
                        <a href="user/profile.php?id=<?php echo $video['user_id']; ?>" class="uploader-name"><?php echo $video['username']; ?></a>
                        <span class="uploader-subs"><?php echo formatViews(db()->fetch("SELECT COUNT(*) as c FROM subscriptions WHERE channel_id = ?", [$video['user_id']])['c'] ?? 0); ?> subscribers</span>
                    </div>
                    <?php if (isLoggedIn() && $video['user_id'] != ($_SESSION['user_id'] ?? 0)): 
                        $isSubscribed = db()->fetch("SELECT id FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?", [$_SESSION['user_id'], $video['user_id']]);
                    ?>
                    <button class="btn btn-primary btn-sm subscribe-btn" data-channel="<?php echo $video['user_id']; ?>" data-subscribed="<?php echo $isSubscribed ? '1' : '0'; ?>" onclick="toggleSubscribe(this)">
                        <?php echo $isSubscribed ? 'Subscribed' : 'Subscribe'; ?>
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Description -->
                <div class="video-description">
                    <p><?php echo nl2br(htmlspecialchars($video['description'] ?? 'No description provided.')); ?></p>
                    <?php if ($video['tags']): ?>
                    <div class="video-tags">
                        <?php foreach (explode(',', $video['tags']) as $tag): 
                            $tag = trim($tag); if (!$tag) continue; ?>
                        <a href="search.php?q=<?php echo urlencode($tag); ?>" class="tag">#<?php echo htmlspecialchars($tag); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Comments -->
                <div class="comments-section">
                    <h3><i data-lucide="message-square"></i> <?php echo number_format($video['comments_count']); ?> Comments</h3>

                    <?php if (isLoggedIn() && $video['allow_comments']): ?>
                    <form class="comment-form" id="commentForm" onsubmit="return postComment(event, <?php echo $videoId; ?>)">
                        <?php echo csrfField(); ?>
                        <img src="<?php echo getAvatarUrl($currentUser['avatar'] ?? 'default-avatar.png'); ?>" alt="" class="comment-avatar">
                        <div class="comment-input-wrap">
                            <textarea name="content" placeholder="Add a comment..." rows="2" required></textarea>
                            <div class="comment-actions">
                                <button type="button" class="btn btn-ghost btn-sm" onclick="this.closest('form').reset()">Cancel</button>
                                <button type="submit" class="btn btn-primary btn-sm">Comment</button>
                            </div>
                        </div>
                    </form>
                    <?php elseif (!isLoggedIn()): ?>
                    <div class="comment-login-prompt">
                        <a href="login.php">Sign in</a> to leave a comment.
                    </div>
                    <?php endif; ?>

                    <div class="comments-list" id="commentsList">
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment-item <?php echo $comment['is_pinned'] ? 'pinned' : ''; ?>">
                            <?php if ($comment['is_pinned']): ?>
                            <span class="pinned-badge"><i data-lucide="pin"></i> Pinned</span>
                            <?php endif; ?>
                            <div class="comment-header">
                                <img src="<?php echo getAvatarUrl($comment['avatar'] ?? 'default-avatar.png'); ?>" alt="" class="comment-avatar">
                                <div class="comment-meta">
                                    <a href="user/profile.php?id=<?php echo $comment['user_id']; ?>" class="comment-author"><?php echo $comment['username']; ?></a>
                                    <span class="comment-time"><?php echo timeAgo($comment['created_at']); ?></span>
                                </div>
                            </div>
                            <div class="comment-body">
                                <p><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                            </div>
                            <div class="comment-footer">
                                <button class="comment-like" onclick="likeComment(this, <?php echo $comment['id']; ?>)">
                                    <i data-lucide="thumbs-up"></i> <span><?php echo $comment['likes_count']; ?></span>
                                </button>
                                <?php if (isAdmin() || (isLoggedIn() && $comment['user_id'] == $_SESSION['user_id'])): ?>
                                <form method="POST" class="inline-form" action="" onsubmit="return confirm('Delete this comment?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="delete_comment" value="<?php echo $comment['id']; ?>">
                                    <button type="submit" class="comment-delete"><i data-lucide="trash-2"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php if (empty($comments)): ?>
                        <div class="no-comments">
                            <i data-lucide="message-circle"></i>
                            <p>No comments yet. Be the first to comment!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar: Related Videos -->
        <aside class="watch-sidebar">
            <h3 class="sidebar-title">Up Next</h3>
            <div class="related-videos">
                <?php foreach ($related as $rel): ?>
                <a href="watch.php?v=<?php echo $rel['id']; ?>" class="related-card">
                    <div class="related-thumb">
                        <img src="<?php echo getThumbnailUrl($rel['thumbnail']); ?>" alt="" loading="lazy">
                        <span class="related-duration"><?php echo formatDuration($rel['duration']); ?></span>
                    </div>
                    <div class="related-info">
                        <h4><?php echo htmlspecialchars($rel['title']); ?></h4>
                        <span class="related-author"><?php echo $rel['username']; ?></span>
                        <span class="related-stats"><?php echo formatViews($rel['views']); ?> views &bull; <?php echo timeAgo($rel['created_at']); ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
</div>

<script>
function handleLike(videoId, type) {
    fetch('ajax/like.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'video_id=' + videoId + '&type=' + type + '&<?php echo CSRF_TOKEN_NAME; ?>=<?php echo generateCSRFToken(); ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('likeCount').textContent = data.likes_count;
            document.getElementById('btnLike').classList.toggle('active', data.user_liked);
            document.getElementById('btnDislike').classList.toggle('active', data.user_disliked);
        }
    });
}

function toggleSubscribe(btn) {
    const channelId = btn.dataset.channel;
    fetch('ajax/subscribe.php', {
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

function postComment(e, videoId) {
    e.preventDefault();
    const form = e.target;
    const content = form.querySelector('textarea').value.trim();
    if (!content) return false;

    const formData = new FormData(form);
    formData.append('video_id', videoId);

    fetch('ajax/comment.php', {method: 'POST', body: formData})
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
    return false;
}

function likeComment(btn, commentId) {
    // Placeholder for comment like functionality
    btn.classList.toggle('active');
}

// Save watch progress
const player = document.getElementById('mainPlayer');
if (player) {
    let saveTimer;
    player.addEventListener('timeupdate', function() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            if (player.currentTime > 5) {
                fetch('ajax/progress.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'video_id=<?php echo $videoId; ?>&progress=' + Math.floor(player.currentTime) + '&<?php echo CSRF_TOKEN_NAME; ?>=<?php echo generateCSRFToken(); ?>'
                });
            }
        }, 5000);
    });
}
</script>

<?php 
// Handle comment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    $cid = intval($_POST['delete_comment']);
    $comment = db()->fetch("SELECT user_id FROM comments WHERE id = ?", [$cid]);
    if ($comment && (isAdmin() || $comment['user_id'] == ($_SESSION['user_id'] ?? 0))) {
        db()->delete("DELETE FROM comments WHERE id = ?", [$cid]);
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}
require_once __DIR__ . '/includes/footer.php'; 
?>
