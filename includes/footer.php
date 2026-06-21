<?php
$currentYear = date('Y');
?>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-brand">
                <span class="logo"><i data-lucide="play-circle"></i> <?php echo SITE_NAME; ?></span>
                <p>Your personal video streaming platform. Share, discover, and enjoy.</p>
            </div>
            <div class="footer-links">
                <div class="footer-col">
                    <h4>Platform</h4>
                    <a href="<?php echo SITE_URL; ?>/index.php">Home</a>
                    <a href="<?php echo SITE_URL; ?>/categories.php">Categories</a>
                    <a href="<?php echo SITE_URL; ?>/search.php">Search</a>
                </div>
                <div class="footer-col">
                    <h4>Account</h4>
                    <?php if (isLoggedIn()): ?>
                        <a href="<?php echo SITE_URL; ?>/user/profile.php">Profile</a>
                        <a href="<?php echo SITE_URL; ?>/user/my-videos.php">My Videos</a>
                        <a href="<?php echo SITE_URL; ?>/user/upload.php">Upload</a>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/login.php">Sign In</a>
                        <a href="<?php echo SITE_URL; ?>/register.php">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo $currentYear; ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="<?php echo SITE_URL; ?>/js/main.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
