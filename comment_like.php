<?php
require_once 'config/db.php';
require_once __DIR__ . '/includes/notifications.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false]); exit; }

$type = $_POST['type'] ?? '';
$id   = (int)($_POST['id'] ?? 0);
$me   = (int)$_SESSION['user_id'];
if (!$id || !in_array($type, ['fit_comment', 'fit_reply', 'post_reply'], true)) {
    echo json_encode(['success' => false]); exit;
}

try {
    $ex = $pdo->prepare("SELECT id FROM comment_likes WHERE target_type = ? AND target_id = ? AND user_id = ?");
    $ex->execute([$type, $id, $me]);
    if ($ex->fetch()) {
        $pdo->prepare("DELETE FROM comment_likes WHERE target_type = ? AND target_id = ? AND user_id = ?")
            ->execute([$type, $id, $me]);
        $liked = false;
    } else {
        $pdo->prepare("INSERT INTO comment_likes (target_type, target_id, user_id) VALUES (?,?,?)")
            ->execute([$type, $id, $me]);
        $liked = true;

        // Powiadomienie dla autora komentarza/odpowiedzi
        if ($type === 'fit_comment') {
            $q = $pdo->prepare("SELECT user_id, fit_id FROM fit_comments WHERE id = ?");
            $q->execute([$id]); $r = $q->fetch();
            if ($r) notify($pdo, (int)$r['user_id'], $me, 'comment_like', (int)$r['fit_id']);
        } elseif ($type === 'fit_reply') {
            $q = $pdo->prepare("SELECT fcr.user_id, fc.fit_id FROM fit_comment_replies fcr JOIN fit_comments fc ON fcr.comment_id = fc.id WHERE fcr.id = ?");
            $q->execute([$id]); $r = $q->fetch();
            if ($r) notify($pdo, (int)$r['user_id'], $me, 'comment_like', (int)$r['fit_id']);
        } else { // post_reply
            $q = $pdo->prepare("SELECT user_id, post_id FROM post_replies WHERE id = ?");
            $q->execute([$id]); $r = $q->fetch();
            if ($r) notify($pdo, (int)$r['user_id'], $me, 'reply_like', (int)$r['post_id']);
        }
    }

    $c = $pdo->prepare("SELECT COUNT(*) FROM comment_likes WHERE target_type = ? AND target_id = ?");
    $c->execute([$type, $id]);
    echo json_encode(['success' => true, 'liked' => $liked, 'likes' => (int)$c->fetchColumn()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'migracja']);
}
