<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// Statistics
$stats = [
    'total_users' => db()->fetch("SELECT COUNT(*) as count FROM users")['count'],
    'total_videos' => db()->fetch("SELECT COUNT(*) as count FROM videos")['count'],
    'total_views' => db()->fetch("SELECT SUM(views) as total FROM videos")['total'] ?? 0,
    'total_comments' => db()->fetch("SELECT COUNT(*) as count FROM comments")['count'],
    'total_likes' => db()->fetch("SELECT COUNT(*) as count FROM likes")['count'],
    'storage_used' => db()->fetch("SELECT SUM(file_size) as total FROM videos")['total'] ?? 0,
    'pending_reports' => db()->fetch("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")['count'],
    'active_today' => db()->fetch("SELECT COUNT(DISTINCT user_id) as count FROM watch_history WHERE watched_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['count'],
];

// Recent users
$recentUsers = db()->fetchAll("SELECT id, username, email, role, status, created_at FROM users ORDER BY created_at DESC LIMIT 10");

// Recent videos
$recentVideos = db()->fetchAll(
    "SELECT v.*, u.username FROM videos v JOIN users u ON v.user_id = u.id ORDER BY v.created_at DESC LIMIT 10"
);

// Recent registrations chart data (last 7 days)
$registrationData = db()->fetchAll(
    "SELECT DATE(created_at) as date, COUNT(*) as count FROM users 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
     GROUP BY DATE(created_at) ORDER BY date"
);

// Video uploads chart data (last 7 days)
$uploadData = db()->fetchAll(
    "SELECT DATE(created_at) as date, COUNT(*) as count FROM videos 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
     GROUP BY DATE(created_at) ORDER BY date"
);

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-page">
    <aside class="admin-sidebar">
        <div class="sidebar-header">
            <i data-lucide="shield"></i>
            <span>Admin Panel</span>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="active">
                <i data-lucide="layout-dashboard"></i> Dashboard
            </a>
            <a href="users.php">
                <i data-lucide="users"></i> Users
            </a>
            <a href="videos.php">
                <i data-lucide="film"></i> Videos
            </a>
            <a href="comments.php">
                <i data-lucide="message-square"></i> Comments
            </a>
            <a href="settings.php">
                <i data-lucide="settings"></i> Settings
            </a>
            <a href="../logout.php">
                <i data-lucide="log-out"></i> Logout
            </a>
        </nav>
    </aside>

    <div class="admin-content">
        <div class="admin-header">
            <h1>Dashboard</h1>
            <div class="admin-actions">
                <span class="admin-user">
                    <img src="<?php echo getAvatarUrl($currentUser['avatar']); ?>" alt="">
                    <?php echo $currentUser['username']; ?>
                </span>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(229, 9, 20, 0.1); color: #E50914;">
                    <i data-lucide="users"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo number_format($stats['total_users']); ?></span>
                    <span class="stat-label">Total Users</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(70, 211, 105, 0.1); color: #46D369;">
                    <i data-lucide="film"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo number_format($stats['total_videos']); ?></span>
                    <span class="stat-label">Total Videos</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(0, 113, 235, 0.1); color: #0071EB;">
                    <i data-lucide="eye"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo formatViews($stats['total_views']); ?></span>
                    <span class="stat-label">Total Views</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245, 197, 24, 0.1); color: #F5C518;">
                    <i data-lucide="message-square"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo number_format($stats['total_comments']); ?></span>
                    <span class="stat-label">Comments</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(229, 9, 20, 0.1); color: #E50914;">
                    <i data-lucide="heart"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo number_format($stats['total_likes']); ?></span>
                    <span class="stat-label">Likes</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(86, 77, 77, 0.1); color: #888;">
                    <i data-lucide="hard-drive"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?php echo formatFileSize($stats['storage_used']); ?></span>
                    <span class="stat-label">Storage Used</span>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="admin-sections">
            <div class="admin-section">
                <h2><i data-lucide="bar-chart-2"></i> Activity Overview</h2>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>

            <div class="admin-section">
                <h2><i data-lucide="alert-circle"></i> Quick Stats</h2>
                <div class="quick-stats">
                    <div class="quick-stat">
                        <span class="quick-value"><?php echo $stats['active_today']; ?></span>
                        <span class="quick-label">Active Users (24h)</span>
                    </div>
                    <div class="quick-stat">
                        <span class="quick-value"><?php echo $stats['pending_reports']; ?></span>
                        <span class="quick-label">Pending Reports</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Users -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i data-lucide="users"></i> Recent Users</h2>
                <a href="users.php" class="btn btn-sm btn-ghost">View All</a>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <img src="<?php echo getAvatarUrl($user['avatar'] ?? 'default-avatar.png'); ?>" alt="">
                                    <span><?php echo $user['username']; ?></span>
                                </div>
                            </td>
                            <td><?php echo $user['email']; ?></td>
                            <td><span class="badge-role <?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td><span class="badge-status <?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                            <td><?php echo timeAgo($user['created_at']); ?></td>
                            <td>
                                <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="btn-icon" title="Edit">
                                    <i data-lucide="edit-2"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Videos -->
        <div class="admin-section">
            <div class="section-header">
                <h2><i data-lucide="film"></i> Recent Uploads</h2>
                <a href="videos.php" class="btn btn-sm btn-ghost">View All</a>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Video</th>
                            <th>Uploader</th>
                            <th>Views</th>
                            <th>Status</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentVideos as $video): ?>
                        <tr>
                            <td>
                                <div class="video-cell">
                                    <img src="<?php echo getThumbnailUrl($video['thumbnail']); ?>" alt="">
                                    <span><?php echo truncateText($video['title'], 40); ?></span>
                                </div>
                            </td>
                            <td><?php echo $video['username']; ?></td>
                            <td><?php echo formatViews($video['views']); ?></td>
                            <td><span class="badge-status <?php echo $video['status']; ?>"><?php echo ucfirst($video['status']); ?></span></td>
                            <td><?php echo timeAgo($video['created_at']); ?></td>
                            <td>
                                <a href="../watch.php?v=<?php echo $video['id']; ?>" class="btn-icon" title="Watch" target="_blank">
                                    <i data-lucide="play"></i>
                                </a>
                                <a href="videos.php?action=edit&id=<?php echo $video['id']; ?>" class="btn-icon" title="Edit">
                                    <i data-lucide="edit-2"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const regData = <?php echo json_encode($registrationData); ?>;
const upData = <?php echo json_encode($uploadData); ?>;

const labels = [];
const regCounts = [];
const upCounts = [];

for (let i = 6; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    const dateStr = d.toISOString().split('T')[0];
    labels.push(d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));

    const reg = regData.find(r => r.date === dateStr);
    regCounts.push(reg ? parseInt(reg.count) : 0);

    const up = upData.find(u => u.date === dateStr);
    upCounts.push(up ? parseInt(up.count) : 0);
}

new Chart(document.getElementById('activityChart'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'New Users',
                data: regCounts,
                borderColor: '#E50914',
                backgroundColor: 'rgba(229, 9, 20, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Video Uploads',
                data: upCounts,
                borderColor: '#46D369',
                backgroundColor: 'rgba(70, 211, 105, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#fff' } }
        },
        scales: {
            y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#888' } },
            x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#888' } }
        }
    }
});
</script>

<?php 
function truncateText($text, $length) {
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}
require_once __DIR__ . '/../includes/footer.php'; 
?>
