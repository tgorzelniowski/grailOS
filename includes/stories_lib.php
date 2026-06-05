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

// Mapa aktywnych storek dla zbioru autorów (1 zapytanie) — do feedów.
// Zwraca [user_id => ['unseen' => bool]] tylko dla autorów widocznych dla oglądającego.
if (!function_exists('active_stories_map')) {
    function active_stories_map(PDO $pdo, array $ids, int $viewer): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids || !$viewer) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $is_admin = !empty($_SESSION['is_admin']);

        $sql = "SELECT s.user_id,
                       SUM(CASE WHEN sv.id IS NULL THEN 1 ELSE 0 END) AS unseen
                FROM stories s
                LEFT JOIN story_views sv ON sv.story_id = s.id AND sv.user_id = ?
                WHERE s.created_at > NOW() - INTERVAL 24 HOUR AND s.user_id IN ($in)";
        $params = array_merge([$viewer], $ids);
        if (!$is_admin) {
            $sql .= " AND (s.user_id = ? OR s.user_id IN (SELECT following_id FROM follows WHERE follower_id = ?))";
            $params[] = $viewer; $params[] = $viewer;
        }
        $sql .= " GROUP BY s.user_id";

        $map = [];
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            foreach ($st->fetchAll() as $r) {
                $map[(int)$r['user_id']] = ['unseen' => ((int)$r['unseen']) > 0];
            }
        } catch (Exception $e) {}
        return $map;
    }
}

// Render: klasa ringu dla avatara w feedzie (lub '' gdy brak storki)
if (!function_exists('story_ring_class')) {
    function story_ring_class(array $map, int $uid): string {
        if (!isset($map[$uid])) return '';
        return $map[$uid]['unseen'] ? 'av-story-ring unseen' : 'av-story-ring seen';
    }
}
