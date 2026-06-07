<?php
require_once 'includes/header.php';

$type  = $_GET['type'] ?? '';
$id    = (int)($_GET['id'] ?? 0);
$valid = ['post', 'fit', 'fit_comment', 'fit_reply', 'post_reply'];
if (!$id || !in_array($type, $valid, true)) { echo '<div style="padding:60px;text-align:center;">Nie znaleziono.</div>'; require_once 'includes/footer.php'; exit; }

$back  = '/';
$title = 'Polubienia';

try {
    if ($type === 'post') {
        $sql = "SELECT u.username, u.avatar_url, u.created_at
                FROM post_likes pl JOIN users u ON pl.user_id = u.id
                WHERE pl.post_id = ? AND u.is_banned = 0 ORDER BY pl.created_at DESC";
        $back = '/post.php?id=' . $id;  $title = 'Polubienia posta';
    } elseif ($type === 'fit') {
        $sql = "SELECT u.username, u.avatar_url, u.created_at
                FROM fit_votes fv JOIN users u ON fv.user_id = u.id
                WHERE fv.fit_id = ? AND u.is_banned = 0 ORDER BY fv.created_at DESC";
        $back = '/fit.php?id=' . $id;  $title = 'Polubienia fitu';
    } else {
        // Lajki komentarzy/odpowiedzi (tabela comment_likes)
        $sql = "SELECT u.username, u.avatar_url, u.created_at
                FROM comment_likes cl JOIN users u ON cl.user_id = u.id
                WHERE cl.target_type = ? AND cl.target_id = ? AND u.is_banned = 0
                ORDER BY cl.created_at DESC";
        $title = 'Polubienia komentarza';
        // Ustal stronę powrotu (rodzic: fit lub post)
        if ($type === 'fit_comment') {
            $p = $pdo->prepare("SELECT fit_id FROM fit_comments WHERE id = ?");
            $p->execute([$id]); $back = '/fit.php?id=' . (int)$p->fetchColumn() . '#comments';
        } elseif ($type === 'fit_reply') {
            $p = $pdo->prepare("SELECT fc.fit_id FROM fit_comment_replies fcr JOIN fit_comments fc ON fcr.comment_id = fc.id WHERE fcr.id = ?");
            $p->execute([$id]); $back = '/fit.php?id=' . (int)$p->fetchColumn() . '#comments';
        } else {
            $p = $pdo->prepare("SELECT post_id FROM post_replies WHERE id = ?");
            $p->execute([$id]); $back = '/post.php?id=' . (int)$p->fetchColumn();
        }
    }

    if ($type === 'fit_comment' || $type === 'fit_reply' || $type === 'post_reply') {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$type, $id]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
    }
    $people = $stmt->fetchAll();
} catch (Exception $e) {
    $people = [];
}
?>

<section class="page-header">
  <div class="page-header-tag">/// Polubienia</div>
  <h1 class="page-title"><?= mb_strtoupper($title) ?></h1>
  <p class="page-sub"><?= count($people) ?> osób</p>
</section>

<div class="users-grid">
  <?php if ($people): ?>
    <?php foreach ($people as $p): ?>
      <a href="/profile.php?username=<?= urlencode($p['username']) ?>" class="user-card">
        <?php if ($p['avatar_url']): ?>
          <img src="<?= htmlspecialchars($p['avatar_url']) ?>" class="user-avatar" style="object-fit:cover;" alt="">
        <?php else: ?>
          <span class="user-avatar"><?= strtoupper(htmlspecialchars($p['username'][0])) ?></span>
        <?php endif; ?>
        <div>
          <div class="user-card-name">@<?= htmlspecialchars($p['username']) ?></div>
          <div class="user-card-date">Dołączył <?= date('M Y', strtotime($p['created_at'])) ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="grid-column:1/-1; padding:60px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
      Jeszcze nikt nie polubił
    </div>
  <?php endif; ?>
</div>

<div style="text-align:center; padding:32px 0 80px;">
  <a href="<?= $back ?>" class="btn btn-outline" style="font-size:11px; padding:8px 18px;">← Wróć</a>
</div>

<?php require_once 'includes/footer.php'; ?>
