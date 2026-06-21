<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$settings = db()->fetchAll("SELECT * FROM settings ORDER BY setting_key");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', 'Invalid request token.');
    } else {
        foreach ($_POST['settings'] as $key => $value) {
            updateSetting($key, $value);
        }
        setFlash('success', 'Settings updated successfully.');
    }
    header('Location: settings.php');
    exit;
}

$pageTitle = 'Site Settings';
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
            <a href="comments.php"><i data-lucide="message-square"></i> Comments</a>
            <a href="settings.php" class="active"><i data-lucide="settings"></i> Settings</a>
            <a href="../logout.php"><i data-lucide="log-out"></i> Logout</a>
        </nav>
    </aside>

    <div class="admin-content">
        <div class="admin-header">
            <h1>Site Settings</h1>
        </div>

        <div class="admin-section">
            <form method="POST" class="admin-form">
                <?php echo csrfField(); ?>

                <?php foreach ($settings as $setting): ?>
                <div class="form-group">
                    <label for="setting_<?php echo $setting['setting_key']; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                        <span class="form-hint"><?php echo $setting['description']; ?> (Type: <?php echo $setting['setting_type']; ?>)</span>
                    </label>

                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                        <label class="toggle-switch">
                            <input type="checkbox" name="settings[<?php echo $setting['setting_key']; ?>]" value="1" 
                                <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    <?php elseif ($setting['setting_type'] === 'integer'): ?>
                        <input type="number" id="setting_<?php echo $setting['setting_key']; ?>" 
                               name="settings[<?php echo $setting['setting_key']; ?>]" 
                               value="<?php echo $setting['setting_value']; ?>">
                    <?php else: ?>
                        <input type="text" id="setting_<?php echo $setting['setting_key']; ?>" 
                               name="settings[<?php echo $setting['setting_key']; ?>]" 
                               value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
