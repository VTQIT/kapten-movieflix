<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    echo json_encode(['success' => false]);
    exit;
}

$videoId = intval($_POST['video_id'] ?? 0);
$progress = intval($_POST['progress'] ?? 0);
$userId = $_SESSION['user_id'];

// Get video duration to check if completed
$video = db()->fetch("SELECT duration FROM videos WHERE id = ?", [$videoId]);
$completed = $video && $video['duration'] > 0 && $progress >= $video['duration'] * 0.9;

db()->query(
    "INSERT INTO watch_history (user_id, video_id, progress, completed) VALUES (?, ?, ?, ?) 
     ON DUPLICATE KEY UPDATE progress = ?, completed = ?, watched_at = NOW()",
    [$userId, $videoId, $progress, $completed ? 1 : 0, $progress, $completed ? 1 : 0]
);

echo json_encode(['success' => true]);
?>
