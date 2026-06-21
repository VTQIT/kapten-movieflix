<?php
/**
 * StreamFlix - Helper Functions
 */
require_once __DIR__ . '/db.php';

// CSRF Protection
function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function csrfField() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . generateCSRFToken() . '">';
}

// Authentication helpers
function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isModerator() {
    return isLoggedIn() && isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'moderator']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        header('Location: ' . SITE_URL . '/index.php');
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return db()->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

// Flash messages
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function showFlash() {
    $flash = getFlash();
    if ($flash) {
        $icons = [
            'success' => 'check-circle',
            'error' => 'x-circle',
            'warning' => 'alert-triangle',
            'info' => 'info'
        ];
        $icon = $icons[$flash['type']] ?? 'info';
        echo '<div class="flash-message flash-' . $flash['type'] . '">';
        echo '<i data-lucide="' . $icon . '"></i>';
        echo '<span>' . htmlspecialchars($flash['message']) . '</span>';
        echo '</div>';
    }
}

// Formatting helpers
function formatDuration($seconds) {
    if ($seconds < 60) return '0:' . str_pad($seconds, 2, '0', STR_PAD_LEFT);
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    if ($minutes < 60) return $minutes . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours . ':' . str_pad($mins, 2, '0', STR_PAD_LEFT) . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
}

function formatViews($num) {
    if ($num >= 1000000) return round($num / 1000000, 1) . 'M';
    if ($num >= 1000) return round($num / 1000, 1) . 'K';
    return $num;
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $unitIndex = 0;
    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }
    return round($bytes, 2) . ' ' . $units[$unitIndex];
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
    if ($diff < 31536000) return floor($diff / 2592000) . 'mo ago';
    return floor($diff / 31536000) . 'y ago';
}

// Security helpers
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generateSlug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

function generateUniqueFilename($extension) {
    return uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
}

// Video helpers
function getVideoUrl($filename) {
    return SITE_URL . '/uploads/' . $filename;
}

function getThumbnailUrl($filename) {
    return SITE_URL . '/thumbnails/' . $filename;
}

function getAvatarUrl($avatar) {
    if (empty($avatar) || $avatar === 'default-avatar.png') {
        return SITE_URL . '/css/default-avatar.png';
    }
    return SITE_URL . '/thumbnails/' . $avatar;
}

// Pagination
function getPagination($total, $page, $perPage = VIDEOS_PER_PAGE) {
    $totalPages = max(1, ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    return [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'totalPages' => $totalPages,
        'offset' => $offset,
        'hasPrev' => $page > 1,
        'hasNext' => $page < $totalPages
    ];
}

function renderPagination($pagination, $baseUrl) {
    if ($pagination['totalPages'] <= 1) return;

    echo '<div class="pagination">';

    if ($pagination['hasPrev']) {
        echo '<a href="' . $baseUrl . '?page=' . ($pagination['page'] - 1) . '" class="page-btn"><i data-lucide="chevron-left"></i></a>';
    }

    $start = max(1, $pagination['page'] - 2);
    $end = min($pagination['totalPages'], $pagination['page'] + 2);

    if ($start > 1) {
        echo '<a href="' . $baseUrl . '?page=1" class="page-btn">1</a>';
        if ($start > 2) echo '<span class="page-dots">...</span>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['page'] ? 'active' : '';
        echo '<a href="' . $baseUrl . '?page=' . $i . '" class="page-btn ' . $active . '">' . $i . '</a>';
    }

    if ($end < $pagination['totalPages']) {
        if ($end < $pagination['totalPages'] - 1) echo '<span class="page-dots">...</span>';
        echo '<a href="' . $baseUrl . '?page=' . $pagination['totalPages'] . '" class="page-btn">' . $pagination['totalPages'] . '</a>';
    }

    if ($pagination['hasNext']) {
        echo '<a href="' . $baseUrl . '?page=' . ($pagination['page'] + 1) . '" class="page-btn"><i data-lucide="chevron-right"></i></a>';
    }

    echo '</div>';
}

// Notification helpers
function createNotification($userId, $type, $title, $message, $link = null) {
    db()->insert(
        "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)",
        [$userId, $type, $title, $message, $link]
    );
}

function getUnreadNotificationsCount($userId) {
    $result = db()->fetch("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", [$userId]);
    return $result['count'] ?? 0;
}

// Settings helpers
function getSetting($key, $default = null) {
    $setting = db()->fetch("SELECT setting_value, setting_type FROM settings WHERE setting_key = ?", [$key]);
    if (!$setting) return $default;

    switch ($setting['setting_type']) {
        case 'integer': return (int)$setting['setting_value'];
        case 'boolean': return (bool)$setting['setting_value'];
        case 'json': return json_decode($setting['setting_value'], true);
        default: return $setting['setting_value'];
    }
}

function updateSetting($key, $value) {
    $setting = db()->fetch("SELECT setting_type FROM settings WHERE setting_key = ?", [$key]);
    if (!$setting) return false;

    if ($setting['setting_type'] === 'json') $value = json_encode($value);

    db()->update("UPDATE settings SET setting_value = ? WHERE setting_key = ?", [$value, $key]);
    return true;
}
?>
