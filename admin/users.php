<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$action = $_GET['action'] ?? 'list';
$message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', 'Invalid request token.');
        header('Location: users.php');
        exit;
    }

    $userId = intval($_POST['user_id'] ?? 0);

    switch ($_POST['action']) {
        case 'update':
            $username = sanitizeInput($_POST['username']);
            $email = sanitizeInput($_POST['email']);
            $role = in_array($_POST['role'], ['user', 'admin', 'moderator']) ? $_POST['role'] : 'user';
            $status = in_array($_POST['status'], ['active', 'suspended', 'banned']) ? $_POST['status'] : 'active';
            $storageLimit = intval($_POST['storage_limit']) * 1024 * 1024 * 1024;

            db()->update(
                "UPDATE users SET username = ?, email = ?, role = ?, status = ?, storage_limit = ? WHERE id = ?",
                [$username, $email, $role, $status, $storageLimit, $userId]
            );

            if (!empty($_POST['new_password'])) {
                Auth::resetPassword($userId, $_POST['new_password']);
            }

            setFlash('success', 'User updated successfully.');
            header('Location: users.php');
            exit;

        case 'delete':
            if ($userId == $_SESSION['user_id']) {
                setFlash('error', 'You cannot delete your own account.');
            } else {
                db()->delete("DELETE FROM users WHERE id = ?", [$userId]);
                setFlash('success', 'User deleted successfully.');
            }
            header('Location: users.php');
            exit;

        case 'toggle_status':
            $user = db()->fetch("SELECT status FROM users WHERE id = ?", [$userId]);
            $newStatus = $user['status'] === 'active' ? 'suspended' : 'active';
            db()->update("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $userId]);
            setFlash('success', 'User status updated to ' . $newStatus . '.');
            header('Location: users.php');
            exit;
    }
}

// Get users list
$page = max(1, intval($_GET['page'] ?? 1));
$search = sanitizeInput($_GET['search'] ?? '');
$roleFilter = sanitizeInput($_GET['role'] ?? '');
$statusFilter = sanitizeInput($_GET['status'] ?? '');

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($roleFilter) {
    $where[] = "role = ?";
    $params[] = $roleFilter;
}
if ($statusFilter) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

$whereStr = implode(' AND ', $where);

$totalUsers = db()->fetch("SELECT COUNT(*) as count FROM users WHERE $whereStr", $params)['count'];
$pagination = getPagination($totalUsers, $page, 20);

$users = db()->fetchAll(
    "SELECT u.*, 
        (SELECT COUNT(*) FROM videos WHERE user_id = u.id) as video_count,
        (SELECT SUM(views) FROM videos WHERE user_id = u.id) as total_views
     FROM users u 
     WHERE $whereStr 
     ORDER BY u.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [$pagination['perPage'], $pagination['offset']])
);

$pageTitle = 'Manage Users';
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
            <a href="users.php" class="active"><i data-lucide="users"></i> Users</a>
            <a href="videos.php"><i data-lucide="film"></i> Videos</a>
            <a href="comments.php"><i data-lucide="message-square"></i> Comments</a>
            <a href="settings.php"><i data-lucide="settings"></i> Settings</a>
            <a href="../logout.php"><i data-lucide="log-out"></i> Logout</a>
        </nav>
    </aside>

    <div class="admin-content">
        <div class="admin-header">
            <h1>Manage Users</h1>
        </div>

        <?php if ($action === 'edit' && isset($_GET['id'])): 
            $editUser = db()->fetch("SELECT * FROM users WHERE id = ?", [intval($_GET['id'])]);
            if ($editUser):
        ?>
        <div class="admin-section">
            <h2>Edit User: <?php echo $editUser['username']; ?></h2>
            <form method="POST" class="admin-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?php echo $editUser['username']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo $editUser['email']; ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role">
                            <option value="user" <?php echo $editUser['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                            <option value="moderator" <?php echo $editUser['role'] === 'moderator' ? 'selected' : ''; ?>>Moderator</option>
                            <option value="admin" <?php echo $editUser['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active" <?php echo $editUser['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo $editUser['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="banned" <?php echo $editUser['status'] === 'banned' ? 'selected' : ''; ?>>Banned</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Storage Limit (GB)</label>
                        <input type="number" name="storage_limit" value="<?php echo round($editUser['storage_limit'] / 1073741824); ?>" min="1" max="1000">
                    </div>
                    <div class="form-group">
                        <label>New Password (leave blank to keep current)</label>
                        <input type="password" name="new_password" placeholder="Enter new password">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Changes</button>
                    <a href="users.php" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
        <?php endif; endif; ?>

        <!-- Filters -->
        <div class="admin-section">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Search users..." value="<?php echo $search; ?>">
                    <select name="role">
                        <option value="">All Roles</option>
                        <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="moderator" <?php echo $roleFilter === 'moderator' ? 'selected' : ''; ?>>Moderator</option>
                        <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="banned" <?php echo $statusFilter === 'banned' ? 'selected' : ''; ?>>Banned</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="search"></i> Filter</button>
                    <a href="users.php" class="btn btn-ghost btn-sm">Clear</a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="admin-section">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Videos</th>
                            <th>Views</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td>
                                <div class="user-cell">
                                    <img src="<?php echo getAvatarUrl($user['avatar'] ?? 'default-avatar.png'); ?>" alt="">
                                    <span><?php echo $user['username']; ?></span>
                                </div>
                            </td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo number_format($user['video_count']); ?></td>
                            <td><?php echo formatViews($user['total_views'] ?? 0); ?></td>
                            <td><span class="badge-role <?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td><span class="badge-status <?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                            <td><?php echo timeAgo($user['created_at']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="btn-icon" title="Edit"><i data-lucide="edit-2"></i></a>
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Toggle status for <?php echo $user['username']; ?>?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn-icon" title="Toggle Status">
                                            <i data-lucide="<?php echo $user['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                        </button>
                                    </form>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete <?php echo $user['username']; ?>? This cannot be undone.')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn-icon btn-danger" title="Delete"><i data-lucide="trash-2"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php renderPagination($pagination, 'users.php'); ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
