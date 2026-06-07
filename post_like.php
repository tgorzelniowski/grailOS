<?php
require_once 'config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]); exit;
}

$post_id = (int) ($_POST['post_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$post_id) {
    echo json_encode(['success' => false]); exit;
}

$stmt = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
$stmt->execute([$post_id, $user_id]);

if ($stmt->fetch()) {
    $pdo->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?")->execute([$post_id, $user_id]);
    $liked = false;
} else {
    $pdo->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)")->execute([$post_id, $user_id]);
    $liked = true;
    require_once __DIR__ . '/includes/notifications.php';
    $o = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $o->execute([$post_id]);
    notify($pdo, (int)$o->fetchColumn(), (int)$user_id, 'post_like', $post_id);
}

$count = $pdo->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id = ?");
$count->execute([$post_id]);

echo json_encode(['success' => true, 'liked' => $liked, 'likes' => (int) $count->fetchColumn()]);
?>