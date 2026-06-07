<?php require_once 'includes/header.php'; ?>

<?php
$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';

$clothing = $users = $posts = [];

if ($q) {
    $clothing = $pdo->prepare("SELECT * FROM clothing WHERE name LIKE ? OR brand LIKE ? LIMIT 6");
    $clothing->execute([$like, $like]);
    $clothing = $clothing->fetchAll();

    $users = $pdo->prepare("SELECT id, username, created_at FROM users WHERE username LIKE ? AND is_banned = 0 LIMIT 6");
    $users->execute([$like]);
    $users = $users->fetchAll();

    $posts = $pdo->prepare("
        SELECT posts.*, users.username
        FROM posts
        LEFT JOIN users ON posts.user_id = users.id
        WHERE posts.content LIKE ?
        LIMIT 6
    ");
    $posts->execute([$like]);
    $posts = $posts->fetchAll();
}
?>

<section class="page-header">
  <div class="page-header-tag">/// Search</div>
  <h1 class="page-title">SZUKAJ</h1>
</section>

<div class="search-bar-wrap">
  <form method="GET" action="/search.php" class="search-form-big">
    <input type="text" name="q" class="search-input-big"
           placeholder="Szukaj produktów, userów, postów..."
           value="<?= htmlspecialchars($q) ?>" autofocus>
    <button type="submit" class="btn">Szukaj</button>
  </form>
</div>

<?php if ($q): ?>

  <!-- PRODUKTY -->
  <div class="section-label">/// Produkty (<?= count($clothing) ?>)</div>
  <?php if ($clothing): ?>
    <div class="clothing-grid">
      <?php foreach ($clothing as $item): ?>
        <div class="clothing-card">
          <div class="clothing-card-img">
            <?php if ($item['image_url']): ?>
              <img src="<?= htmlspecialchars($item['image_url']) ?>"
                   alt="<?= htmlspecialchars($item['name']) ?>">
            <?php else: ?>
              NO IMG
            <?php endif; ?>
          </div>
          <div class="clothing-card-brand"><?= htmlspecialchars($item['brand']) ?></div>
          <div class="clothing-card-name"><?= htmlspecialchars($item['name']) ?></div>
          <div class="clothing-card-price">
            <?= $item['price'] ? number_format($item['price'], 2) . ' zł' : '—' ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="search-empty">Brak produktów dla "<?= htmlspecialchars($q) ?>"</div>
  <?php endif; ?>

  <!-- UŻYTKOWNICY -->
  <div class="section-label">/// Użytkownicy (<?= count($users) ?>)</div>
  <?php if ($users): ?>
    <div class="users-grid">
      <?php foreach ($users as $user): ?>
        <a href="/profile.php?username=<?= urlencode($user['username']) ?>"
           class="user-card">
          <div class="user-avatar">@<?= strtoupper(htmlspecialchars($user['username'][0])) ?></div>
          <div class="user-card-info">
            <div class="user-card-name">@<?= htmlspecialchars($user['username']) ?></div>
            <div class="user-card-date">Dołączył <?= date('M Y', strtotime($user['created_at'])) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="search-empty">Brak użytkowników dla "<?= htmlspecialchars($q) ?>"</div>
  <?php endif; ?>

  <!-- POSTY -->
  <div class="section-label">/// Posty z forum (<?= count($posts) ?>)</div>
  <?php if ($posts): ?>
    <div class="forum-feed">
      <?php foreach ($posts as $post): ?>
        <a href="/post.php?id=<?= $post['id'] ?>" class="post-card post-card-link">
          <div class="forum-post-header">
            <span class="post-user">@<?= htmlspecialchars($post['username']) ?></span>
            <span class="post-date"><?= date('d M Y', strtotime($post['created_at'])) ?></span>
          </div>
          <div class="post-card-content">
            <?= htmlspecialchars(mb_substr($post['content'], 0, 200)) ?>...
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="search-empty">Brak postów dla "<?= htmlspecialchars($q) ?>"</div>
  <?php endif; ?>

<?php else: ?>
  <div class="search-empty">Wpisz frazę żeby szukać.</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>