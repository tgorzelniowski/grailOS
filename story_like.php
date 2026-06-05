<?php
require_once 'config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'auth']); exit;
}

$story_id = (int)($_POST['story_id'] ?? 0);
$me       = (int)$_SESSION['user_id'];
if (!$story_id) { echo json_encode(['success' => false]); exit; }

// Storka istnieje i jest aktywna?
$chk = $pdo->prepare("SELECT user_id FROM stories WHERE id = ? AND created_at > NOW() - INTERVAL 24 HOUR");
$chk->execute([$story_id]);
$st = $chk->fetch();
if (!$st) { echo json_encode(['success' => false]); exit; }

// Toggle
$ex = $pdo->prepare("SELECT id FROM story_likes WHERE story_id = ? AND user_id = ?");
$ex->execute([$story_id, $me]);
if ($ex->fetch()) {
    $pdo->prepare("DELETE FROM story_likes WHERE story_id = ? AND user_id = ?")->execute([$story_id, $me]);
    $liked = false;
} else {
    $pdo->prepare("INSERT INTO story_likes (story_id, user_id) VALUES (?, ?)")->execute([$story_id, $me]);
    $liked = true;
}

$cnt = $pdo->prepare("SELECT COUNT(*) FROM story_likes WHERE story_id = ?");
$cnt->execute([$story_id]);

echo json_encode(['success' => true, 'liked' => $liked, 'likes' => (int)$cnt->fetchColumn()]);
