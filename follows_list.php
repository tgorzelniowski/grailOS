<?php
require_once 'includes/header.php';

$username = trim($_GET['username'] ?? '');
$type     = $_GET['type'] ?? 'followers';
if (!in_array($type, ['followers', 'following'], true)) $type = 'followers';

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_banned = 0");
$stmt->execute([$username]);
$user = $stmt->fetch();
if (!$user) { header('Location: /grailos/'); exit; }

$is_own       = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id'];
$viewer_admin = !empty($_SESSION['is_admin']);
$show_follows = (int)($user['show_follows'] ?? 1) === 1;

if (!$is_own && !$viewer_admin && !$show_follows) {
    echo '<section class="page-header"><div class="page-header-tag">/// Prywatne</div>'
       . '<h1 class="page-title">UKRYTE</h1>'
       . '<p class="page-sub">Ten użytkownik ukrył listę obserwacji.</p></section>';
    require_once 'includes/footer.php';
    exit;
}

if ($type === 'followers') {
    // Kto obserwuje tego użytkownika
    $list = $pdo->prepare("
        SELECT u.id, u.username, u.avatar_url, u.created_at
        FROM follows f
        JOIN users u ON f.follower_id = u.id
        WHERE f.following_id = ? AND u.is_banned = 0
        ORDER BY f.created_at DESC
    ");
    $title = 'Obserwujący';
} else {
    // Kogo ten użytkownik obserwuje
    $list = $pdo->prepare("
        SELECT u.id, u.username, u.avatar_url, u.created_at
        FROM follows f
        JOIN users u ON f.following_id = u.id
        WHERE f.follower_id = ? AND u.is_banned = 0
        ORDER BY f.created_at DESC
    ");
    $title = 'Obserwuje';
}
$list->execute([$user['id']]);
$people = $list->fetchAll();
?>

<section class="page-header">
  <div class="page-header-tag">/// @<?= htmlspecialchars($user['username']) ?></div>
  <h1 class="page-title"><?= mb_strtoupper($title) ?></h1>
</section>

<div style="display:flex; gap:0; border-bottom:1px solid var(--border);">
  <a href="/grailos/follows_list.php?username=<?= urlencode($user['username']) ?>&type=followers"
     class="follows-tab <?= $type === 'followers' ? 'active' : '' ?>">Obserwujący</a>
  <a href="/grailos/follows_list.php?username=<?= urlencode($user['username']) ?>&type=following"
     class="follows-tab <?= $type === 'following' ? 'active' : '' ?>">Obserwuje</a>
</div>

<div class="users-grid">
  <?php if ($people): ?>
    <?php foreach ($people as $p): ?>
      <a href="/grailos/profile.php?username=<?= urlencode($p['username']) ?>" class="user-card">
        <?php if ($p['avatar_url']): ?>
          <img src="<?= htmlspecialchars($p['avatar_url']) ?>" class="user-avatar"
               style="object-fit:cover;" alt="">
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
      <?= $type === 'followers' ? 'Brak obserwujących' : 'Nikogo nie obserwuje' ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
