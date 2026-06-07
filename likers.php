<?php
require_once 'includes/header.php';

$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);
if (!$id || !in_array($type, ['post', 'fit'], true)) { header('Location: /'); exit; }

if ($type === 'post') {
    $sql = "SELECT u.username, u.avatar_url, u.created_at
            FROM post_likes pl JOIN users u ON pl.user_id = u.id
            WHERE pl.post_id = ? AND u.is_banned = 0
            ORDER BY pl.created_at DESC";
    $back = '/post.php?id=' . $id;
    $title = 'Polubienia posta';
} else {
    $sql = "SELECT u.username, u.avatar_url, u.created_at
            FROM fit_votes fv JOIN users u ON fv.user_id = u.id
            WHERE fv.fit_id = ? AND u.is_banned = 0
            ORDER BY fv.created_at DESC";
    $back = '/fit.php?id=' . $id;
    $title = 'Polubienia fitu';
}
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$people = $stmt->fetchAll();
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
