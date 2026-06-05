<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php'); exit;
}
csrf_verify();

$reply_id = (int)($_POST['reply_id'] ?? 0);
$post_id  = (int)($_POST['post_id'] ?? 0);
$is_admin = !empty($_SESSION['is_admin']);

if ($reply_id) {
    $stmt = $pdo->prepare("SELECT user_id, post_id FROM post_replies WHERE id = ?");
    $stmt->execute([$reply_id]);
    $row = $stmt->fetch();
    if ($row && ($row['user_id'] == $_SESSION['user_id'] || $is_admin)) {
        $post_id = $row['post_id'];
        $pdo->prepare("DELETE FROM post_replies WHERE id = ?")->execute([$reply_id]);
    }
}

header('Location: /grailos/post.php?id=' . $post_id);
exit;
