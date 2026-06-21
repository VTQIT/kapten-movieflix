<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', 'Invalid request token.');
        header('Location: comments.php');
        exit;
    }

    $commentId = intval($_POST['comment_id'] ?? 0);

    switch ($_POST['action']) {
        case 'delete':
            db()->delete("DELETE FROM comments WHERE id = ?", [$commentId]);
            setFlash('success', 'Comment deleted.');
            header('Location: comments.php');
            exit;

        case 'toggle_status':
            $comment = db()->fetch("SELECT status FROM comments WHERE id = ?", [$commentId]);
            $newStatus = $comment['status'] === 'active' ? 'hidden' : 'active';
            db()->update("UPDATE comments SET status = ? WHERE id = ?", [$newStatus, $commentId]);
            setFlash('success', 'Comment status updated.');
            header('Location: comments.php');
            exit;
    }
}

$page = max(1, intval($_GET['page'] ?? 1));
$search = sanitizeInput($_GET['search'] ?? '');
$statusFilter = sanitizeInput($_GET['status'] ?? '');

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "c.content LIKE ?";
    $params[] = "%$search%";
}
if ($statusFilter) {
    $where[] = "c.status = ?";
    $params[] = $statusFilter;
}

$whereStr = implode(' AND ', $where);

$totalComments = db()->fetch("SELECT COUNT(*) as count FROM comments c WHERE $whereStr", $params)['count'];
$pagination = getPagination($totalComments, $page, 30);

$comments = db()->fetchAll(
    "SELECT c.*, u.username, u.avatar, v.title as video_title, v.id as video_id
     FROM comments c 
     JOIN users u ON c.user_id = u.id 
     JOIN videos v ON c.video_id = v.id 
     WHERE $whereStr 
     ORDER BY c.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [$pagination['perPage'], $pagination['offset']])
);

$pageTitle = 'Moderate Comments';
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
            <a href="videos.php"><i data-lucide="film"></i> Videos</a>
            <a href="comments.php" class="active"><i data-lucide="message-square"></i> Comments</a>
            <a href="settings.php"><i data-lucide="settings"></i> Settings</a>
            <a href="../logout.php"><i data-lucide="log-out"></i> Logout</a>
        </nav>
    </aside>

    <div class="admin-content">
        <div class="admin-header">
            <h1>Moderate Comments</h1>
        </div>

        <!-- Filters -->
        <div class="admin-section">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search comments..." value="<?php echo $search; ?>">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="hidden" <?php echo $statusFilter === 'hidden' ? 'selected' : ''; ?>>Hidden</option>
                        <option value="flagged" <?php echo $statusFilter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="search"></i> Filter</button>
                    <a href="comments.php" class="btn btn-ghost btn-sm">Clear</a>
                </div>
            </form>
        </div>

        <!-- Comments Table -->
        <div class="admin-section">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Comment</th>
                            <th>Video</th>
                            <th>Status</th>
                            <th>Posted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comments as $comment): ?>
                        <tr>
                            <td><?php echo $comment['id']; ?></td>
                            <td>
                                <div class="user-cell">
                                    <img src="<?php echo getAvatarUrl($comment['avatar'] ?? 'default-avatar.png'); ?>" alt="">
                                    <span><?php echo $comment['username']; ?></span>
                                </div>
                            </td>
                            <td class="comment-cell"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></td>
                            <td><a href="../watch.php?v=<?php echo $comment['video_id']; ?>" target="_blank"><?php echo truncateText($comment['video_title'], 30); ?></a></td>
                            <td><span class="badge-status <?php echo $comment['status']; ?>"><?php echo ucfirst($comment['status']); ?></span></td>
                            <td><?php echo timeAgo($comment['created_at']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Toggle status?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                        <button type="submit" class="btn-icon" title="Toggle Status">
                                            <i data-lucide="<?php echo $comment['status'] === 'active' ? 'eye-off' : 'eye'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete this comment?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                        <button type="submit" class="btn-icon btn-danger" title="Delete"><i data-lucide="trash-2"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php renderPagination($pagination, 'comments.php'); ?>
        </div>
    </div>
</div>

<?php 
function truncateText($text, $length) {
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}
require_once __DIR__ . '/../includes/footer.php'; 
?>
