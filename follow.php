<?php
require_once 'config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]); exit;
}

csrf_verify();

$target_id = (int) ($_POST['target_id'] ?? 0);
$user_id   = $_SESSION['user_id'];

if (!$target_id || $target_id === $user_id) {
    echo json_encode(['success' => false]); exit;
}

$check = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
$check->execute([$user_id, $target_id]);

if ($check->fetch()) {
    $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?")
        ->execute([$user_id, $target_id]);
    $following = false;
} else {
    $pdo->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)")
        ->execute([$user_id, $target_id]);
    $following = true;
}

$count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
$count->execute([$target_id]);

echo json_encode(['success' => true, 'following' => $following, 'followers' => (int)$count->fetchColumn()]);
?>