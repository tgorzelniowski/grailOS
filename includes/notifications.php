<?php
/**
 * Powiadomienia — helper.
 * notify($pdo, odbiorca, aktor, typ, object_id) — tworzy powiadomienie
 * (pomija akcje na samym sobie; cicho ignoruje brak tabeli przed migracją).
 */
if (!function_exists('notify')) {
    function notify(PDO $pdo, int $recipient, int $actor, string $type, ?int $object_id = null): void {
        if ($recipient <= 0 || $actor <= 0 || $recipient === $actor) return;
        try {
            $pdo->prepare("INSERT INTO notifications (user_id, actor_id, type, object_id) VALUES (?,?,?,?)")
                ->execute([$recipient, $actor, $type, $object_id]);
        } catch (Exception $e) { /* tabela jeszcze nie zmigrowana */ }
    }
}

// Liczba nieprzeczytanych (dla dzwonka w navbarze)
if (!function_exists('notif_unread_count')) {
    function notif_unread_count(PDO $pdo, int $user_id): int {
        if ($user_id <= 0) return 0;
        try {
            $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $s->execute([$user_id]);
            return (int)$s->fetchColumn();
        } catch (Exception $e) { return 0; }
    }
}

// Tekst + link powiadomienia
if (!function_exists('notif_render')) {
    function notif_render(array $n): array {
        $who = '@' . htmlspecialchars($n['actor_name'] ?? 'ktoś');
        switch ($n['type']) {
            case 'post_like':    return [$who.' polubił(a) Twój post',        '/post.php?id='.(int)$n['object_id']];
            case 'fit_vote':     return [$who.' polubił(a) Twój fit',          '/fit.php?id='.(int)$n['object_id']];
            case 'fit_comment':  return [$who.' skomentował(a) Twój fit',      '/fit.php?id='.(int)$n['object_id'].'#comments'];
            case 'post_reply':   return [$who.' odpowiedział(a) na Twój post', '/post.php?id='.(int)$n['object_id']];
            case 'comment_like': return [$who.' polubił(a) Twój komentarz',    '/fit.php?id='.(int)$n['object_id'].'#comments'];
            case 'follow':       return [$who.' zaczął(ęła) Cię obserwować',   '/profile.php?username='.urlencode($n['actor_name'] ?? '')];
            default:             return [$who.' — nowa aktywność', '/'];
        }
    }
}
