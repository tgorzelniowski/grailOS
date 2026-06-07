<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /forum.php'); exit;
}

csrf_verify();

$post_id = (int) ($_POST['post_id'] ?? 0);
if (!$post_id) { header('Location: /forum.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) { header('Location: /forum.php'); exit; }

if ($post['user_id'] !== $_SESSION['user_id'] && !$_SESSION['is_admin']) {
    header('Location: /forum.php'); exit;
}

$pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$post_id]);
header('Location: /forum.php');
exit;
?>