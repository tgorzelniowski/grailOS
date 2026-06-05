<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php'); exit;
}
csrf_verify();

$story_id = (int)($_POST['story_id'] ?? 0);
$is_admin = !empty($_SESSION['is_admin']);

if ($story_id) {
    $stmt = $pdo->prepare("SELECT user_id FROM stories WHERE id = ?");
    $stmt->execute([$story_id]);
    $row = $stmt->fetch();
    if ($row && ($row['user_id'] == $_SESSION['user_id'] || $is_admin)) {
        $pdo->prepare("DELETE FROM stories WHERE id = ?")->execute([$story_id]);
    }
}

header('Location: /grailos/');
exit;
