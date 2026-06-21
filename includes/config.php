<?php
/**
 * StreamFlix - Configuration File
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'video_platform');

// Site configuration
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/videos');
define('SITE_NAME', 'Kapten MovieFlix');
define('SITE_DESCRIPTION', 'Your personal video streaming platform');

// Upload configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('THUMBNAIL_DIR', __DIR__ . '/../thumbnails/');
define('MAX_UPLOAD_SIZE', 1024 * 1024 * 1024); // 1GB
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska']);
define('ALLOWED_VIDEO_EXTS', ['mp4', 'webm', 'mov', 'mkv']);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('THUMBNAIL_WIDTH', 640);
define('THUMBNAIL_HEIGHT', 360);

// Pagination
define('VIDEOS_PER_PAGE', 24);
define('COMMENTS_PER_PAGE', 20);

// Timezone
date_default_timezone_set('UTC');

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 6);

// Create upload directories if they don't exist
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
if (!is_dir(THUMBNAIL_DIR)) mkdir(THUMBNAIL_DIR, 0755, true);
?>
