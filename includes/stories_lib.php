<?php
// Pomocnik storek — czy autor ma aktywną (24h) storkę widoczną dla oglądającego.
// Zwraca: ['allowed'=>bool, 'has'=>bool, 'unseen'=>bool]
if (!function_exists('active_story_meta')) {
    function active_story_meta(PDO $pdo, int $author_id, int $viewer_id): array {
        $out = ['allowed' => false, 'has' => false, 'unseen' => false];
        if (!$author_id || !$viewer_id) return $out;

        // Dostęp: autor, admin lub obserwujący
        $allowed = ($author_id === $viewer_id) || !empty($_SESSION['is_admin']);
        if (!$allowed) {
            $f = $pdo->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?");
            $f->execute([$viewer_id, $author_id]);
            $allowed = (bool)$f->fetchColumn();
        }
        $out['allowed'] = $allowed;
        if (!$allowed) return $out;

        try {
            $q = $pdo->prepare("
                SELECT COUNT(*) AS total,
                       SUM(CASE WHEN sv.id IS NULL THEN 1 ELSE 0 END) AS unseen
                FROM stories s
                LEFT JOIN story_views sv ON sv.story_id = s.id AND sv.user_id = ?
                WHERE s.user_id = ? AND s.created_at > NOW() - INTERVAL 24 HOUR
            ");
            $q->execute([$viewer_id, $author_id]);
            $row = $q->fetch();
            $out['has']    = ((int)($row['total'] ?? 0)) > 0;
            $out['unseen'] = ((int)($row['unseen'] ?? 0)) > 0;
        } catch (Exception $e) {}
        return $out;
    }
}
