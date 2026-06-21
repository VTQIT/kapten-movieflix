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
$type = in_array($_POST['type'] ?? '', ['like', 'dislike']) ? $_POST['type'] : 'like';
$userId = $_SESSION['user_id'];

$existing = db()->fetch("SELECT id, type FROM likes WHERE video_id = ? AND user_id = ?", [$videoId, $userId]);

if ($existing) {
    if ($existing['type'] === $type) {
        // Remove like/dislike (toggle off)
        db()->delete("DELETE FROM likes WHERE id = ?", [$existing['id']]);
        $userLiked = false;
        $userDisliked = false;
    } else {
        // Switch type
        db()->update("UPDATE likes SET type = ? WHERE id = ?", [$type, $existing['id']]);
        $userLiked = $type === 'like';
        $userDisliked = $type === 'dislike';
    }
} else {
    // New like/dislike
    db()->insert("INSERT INTO likes (video_id, user_id, type) VALUES (?, ?, ?)", [$videoId, $userId, $type]);
    $userLiked = $type === 'like';
    $userDisliked = $type === 'dislike';

    // Notify video owner
    $video = db()->fetch("SELECT user_id, title FROM videos WHERE id = ?", [$videoId]);
    if ($video && $video['user_id'] != $userId) {
        createNotification($video['user_id'], 'like', 'New Like', $_SESSION['username'] . ' liked your video "' . $video['title'] . '"', 'watch.php?v=' . $videoId);
    }
}

$count = db()->fetch("SELECT likes_count FROM videos WHERE id = ?", [$videoId])['likes_count'] ?? 0;

echo json_encode([
    'success' => true,
    'likes_count' => $count,
    'user_liked' => $userLiked,
    'user_disliked' => $userDisliked
]);
?>
