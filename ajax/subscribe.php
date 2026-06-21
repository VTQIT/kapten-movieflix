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

$channelId = intval($_POST['channel_id'] ?? 0);
$subscriberId = $_SESSION['user_id'];

if ($channelId === $subscriberId) {
    echo json_encode(['success' => false, 'error' => 'Cannot subscribe to yourself']);
    exit;
}

$existing = db()->fetch("SELECT id FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?", [$subscriberId, $channelId]);

if ($existing) {
    db()->delete("DELETE FROM subscriptions WHERE id = ?", [$existing['id']]);
    $subscribed = false;
} else {
    db()->insert("INSERT INTO subscriptions (subscriber_id, channel_id) VALUES (?, ?)", [$subscriberId, $channelId]);
    $subscribed = true;

    // Notify channel owner
    $user = db()->fetch("SELECT username FROM users WHERE id = ?", [$subscriberId]);
    createNotification($channelId, 'subscribe', 'New Subscriber', $user['username'] . ' subscribed to your channel', 'user/profile.php?id=' . $subscriberId);
}

echo json_encode(['success' => true, 'subscribed' => $subscribed]);
?>
