<?php
/**
 * Lajki komentarzy — pomocnik renderowania.
 * clike_meta($pdo,$type,$id,$me) → ['count'=>int,'liked'=>bool]
 * clike_button($type,$id,$meta) → HTML przycisku ♥
 */
if (!function_exists('clike_meta')) {
    function clike_meta(PDO $pdo, string $type, int $id, int $me): array {
        try {
            $c = $pdo->prepare("SELECT COUNT(*) FROM comment_likes WHERE target_type = ? AND target_id = ?");
            $c->execute([$type, $id]);
            $count = (int)$c->fetchColumn();
            $liked = false;
            if ($me > 0) {
                $l = $pdo->prepare("SELECT 1 FROM comment_likes WHERE target_type = ? AND target_id = ? AND user_id = ?");
                $l->execute([$type, $id, $me]);
                $liked = (bool)$l->fetchColumn();
            }
            return ['count' => $count, 'liked' => $liked];
        } catch (Exception $e) {
            return ['count' => 0, 'liked' => false];
        }
    }
}

if (!function_exists('clike_button')) {
    function clike_button(string $type, int $id, array $meta, bool $logged): string {
        $cls = 'clike-btn' . ($meta['liked'] ? ' liked' : '');
        if ($logged) {
            return "<button class='$cls' onclick=\"likeComment('$type',$id,this)\">"
                 . "♥ <span class='clike-count'>{$meta['count']}</span></button>";
        }
        return "<span class='clike-btn'>♥ <span class='clike-count'>{$meta['count']}</span></span>";
    }
}
