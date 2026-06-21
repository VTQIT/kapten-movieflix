<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

$videoId = intval($_POST['video_id'] ?? 0);
$content = trim($_POST['content'] ?? '');
$userId = $_SESSION['user_id'];

if (empty($content)) {
    echo json_encode(['success' => false, 'error' => 'Comment cannot be empty']);
    exit;
}

if (strlen($content) > 2000) {
    echo json_encode(['success' => false, 'error' => 'Comment too long (max 2000 chars)']);
    exit;
}

// Check if comments are allowed
$video = db()->fetch("SELECT user_id, title, allow_comments FROM videos WHERE id = ?", [$videoId]);
if (!$video || !$video['allow_comments']) {
    echo json_encode(['success' => false, 'error' => 'Comments are disabled for this video']);
    exit;
}

$commentId = db()->insert(
    "INSERT INTO comments (video_id, user_id, content) VALUES (?, ?, ?)",
    [$videoId, $userId, $content]
);

// Notify video owner
if ($video['user_id'] != $userId) {
    createNotification($video['user_id'], 'comment', 'New Comment', $_SESSION['username'] . ' commented on "' . $video['title'] . '"', 'watch.php?v=' . $videoId);
}

echo json_encode(['success' => true, 'comment_id' => $commentId]);
?>
