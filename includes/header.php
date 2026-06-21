<?php
require_once __DIR__ . '/auth.php';
$currentUser = getCurrentUser();
$unreadNotifications = $currentUser ? getUnreadNotificationsCount($currentUser['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="<?php echo SITE_URL; ?>/index.php" class="logo">
                <i data-lucide="play-circle"></i>
                <span><?php echo SITE_NAME; ?></span>
            </a>

            <div class="nav-search">
                <form action="<?php echo SITE_URL; ?>/search.php" method="GET">
                    <div class="search-box">
                        <i data-lucide="search"></i>
                        <input type="text" name="q" placeholder="Search videos..." 
                               value="<?php echo isset($_GET['q']) ? sanitizeInput($_GET['q']) : ''; ?>">
                    </div>
                </form>
            </div>

            <div class="nav-actions">
                <?php if (isLoggedIn()): ?>
                    <a href="<?php echo SITE_URL; ?>/user/upload.php" class="btn btn-primary btn-sm">
                        <i data-lucide="upload"></i>
                        <span>Upload</span>
                    </a>

                    <div class="nav-dropdown">
                        <button class="nav-btn">
                            <i data-lucide="bell"></i>
                            <?php if ($unreadNotifications > 0): ?>
                                <span class="badge"><?php echo $unreadNotifications; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <div class="nav-dropdown">
                        <button class="nav-avatar">
                            <img src="<?php echo getAvatarUrl($currentUser['avatar']); ?>" alt="<?php echo $currentUser['username']; ?>">
                            <i data-lucide="chevron-down" class="dropdown-icon"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="<?php echo SITE_URL; ?>/user/profile.php?id=<?php echo $currentUser['id']; ?>">
                                <i data-lucide="user"></i> Profile
                            </a>
                            <a href="<?php echo SITE_URL; ?>/user/my-videos.php">
                                <i data-lucide="video"></i> My Videos
                            </a>
                            <a href="<?php echo SITE_URL; ?>/user/upload.php">
                                <i data-lucide="upload-cloud"></i> Upload
                            </a>
                            <?php if (isAdmin()): ?>
                                <div class="dropdown-divider"></div>
                                <a href="<?php echo SITE_URL; ?>/admin/dashboard.php">
                                    <i data-lucide="shield"></i> Admin Panel
                                </a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo SITE_URL; ?>/logout.php">
                                <i data-lucide="log-out"></i> Logout
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-ghost">Sign In</a>
                    <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-primary">Get Started</a>
                <?php endif; ?>
            </div>

            <button class="mobile-menu-btn">
                <i data-lucide="menu"></i>
            </button>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu">
        <div class="mobile-menu-header">
            <span class="logo"><i data-lucide="play-circle"></i> <?php echo SITE_NAME; ?></span>
            <button class="mobile-menu-close"><i data-lucide="x"></i></button>
        </div>
        <div class="mobile-menu-links">
            <a href="<?php echo SITE_URL; ?>/index.php"><i data-lucide="home"></i> Home</a>
            <a href="<?php echo SITE_URL; ?>/categories.php"><i data-lucide="grid"></i> Categories</a>
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo SITE_URL; ?>/user/my-videos.php"><i data-lucide="video"></i> My Videos</a>
                <a href="<?php echo SITE_URL; ?>/user/upload.php"><i data-lucide="upload"></i> Upload</a>
                <a href="<?php echo SITE_URL; ?>/user/profile.php"><i data-lucide="user"></i> Profile</a>
                <?php if (isAdmin()): ?>
                    <a href="<?php echo SITE_URL; ?>/admin/dashboard.php"><i data-lucide="shield"></i> Admin</a>
                <?php endif; ?>
                <a href="<?php echo SITE_URL; ?>/logout.php"><i data-lucide="log-out"></i> Logout</a>
            <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/login.php"><i data-lucide="log-in"></i> Sign In</a>
                <a href="<?php echo SITE_URL; ?>/register.php"><i data-lucide="user-plus"></i> Register</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="overlay"></div>

    <!-- Main Content -->
    <main class="main-content">
        <?php showFlash(); ?>
