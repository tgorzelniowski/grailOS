<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php'); exit;
}
csrf_verify();

$type    = $_POST['type'] ?? '';
$cid     = (int)($_POST['id'] ?? 0);
$fit_id  = (int)($_POST['fit_id'] ?? 0);
$is_admin = !empty($_SESSION['is_admin']);

if ($cid && in_array($type, ['comment', 'reply'], true)) {
    if ($type === 'comment') {
        $stmt = $pdo->prepare("SELECT user_id, fit_id FROM fit_comments WHERE id = ?");
        $stmt->execute([$cid]);
        $row = $stmt->fetch();
        if ($row && ($row['user_id'] == $_SESSION['user_id'] || $is_admin)) {
            $fit_id = $row['fit_id'];
            $pdo->prepare("DELETE FROM fit_comments WHERE id = ?")->execute([$cid]);
        }
    } else {
        $stmt = $pdo->prepare("
            SELECT fcr.user_id, fc.fit_id
            FROM fit_comment_replies fcr
            INNER JOIN fit_comments fc ON fcr.comment_id = fc.id
            WHERE fcr.id = ?
        ");
        $stmt->execute([$cid]);
        $row = $stmt->fetch();
        if ($row && ($row['user_id'] == $_SESSION['user_id'] || $is_admin)) {
            $fit_id = $row['fit_id'];
            $pdo->prepare("DELETE FROM fit_comment_replies WHERE id = ?")->execute([$cid]);
        }
    }
}

header('Location: /grailos/fit.php?id=' . $fit_id . '#comments');
exit;
