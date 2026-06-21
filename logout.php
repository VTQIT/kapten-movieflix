<?php
require_once __DIR__ . '/includes/auth.php';

Auth::logout();
setFlash('success', 'You have been logged out successfully.');
header('Location: ' . SITE_URL . '/login.php');
exit;
?>
