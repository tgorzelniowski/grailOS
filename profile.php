<?php
require_once 'includes/header.php';

$username = trim($_GET['username'] ?? '');
if (!$username) { header('Location: /grailos/'); exit; }

$stmt = $pdo->prepare("SELECT id, username, created_at, is_admin FROM users WHERE username = ? AND is_banned = 0");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) { header('Location: /grailos/'); exit; }

// Statystyki
$fit_count  = $pdo->prepare("SELECT COUNT(*) FROM fits WHERE user_id = ?");
$fit_count->execute([$user['id']]);
$fit_count = (int) $fit_count->fetchColumn();

$post_count = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
$post_count->execute([$user['id']]);
$post_count = (int) $post_count->fetchColumn();

$wardrobe_count = $pdo->prepare("SELECT COUNT(*) FROM wardrobe_items WHERE user_id = ?");
$wardrobe_count->execute([$user['id']]);
$wardrobe_count = (int) $wardrobe_count->fetchColumn();

// Fity
$fits = $pdo->prepare("
    SELECT fits.*, COUNT(fit_votes.id) AS votes
    FROM fits
    LEFT JOIN fit_votes ON fits.id = fit_votes.fit_id
    WHERE fits.user_id = ?
    GROUP BY fits.id
    ORDER BY fits.created_at DESC
");
$fits->execute([$user['id']]);
$fits = $fits->fetchAll();

// Posty
$posts = $pdo->prepare("
    SELECT posts.*, COUNT(post_replies.id) AS replies
    FROM posts
    LEFT JOIN post_replies ON posts.id = post_replies.post_id
    WHERE posts.user_id = ?
    GROUP BY posts.id
    ORDER BY posts.created_at DESC
    LIMIT 5
");
$posts->execute([$user['id']]);
$posts = $posts->fetchAll();

// Szafa
$wardrobe = $pdo->prepare("
    SELECT wardrobe_items.*, clothing.name, clothing.brand,
           clothing.image_url, clothing.price, clothing.category
    FROM wardrobe_items
    LEFT JOIN clothing ON wardrobe_items.clothing_id = clothing.id
    WHERE wardrobe_items.user_id = ?
    ORDER BY wardrobe_items.created_at DESC
");
$wardrobe->execute([$user['id']]);
$wardrobe = $wardrobe->fetchAll();
?>

<!-- PROFILE HEADER -->
<section class="profile-header">
  <div class="profile-avatar">
    <?= strtoupper(htmlspecialchars($user['username'][0])) ?>
  </div>
  <div class="profile-info">
    <div class="profile-username">
      @<?= htmlspecialchars($user['username']) ?>
      <?php if ($user['is_admin']): ?>
        <span class="profile-badge">ADMIN</span>
      <?php endif; ?>
    </div>
    <div class="profile-joined">Dołączył <?= date('F Y', strtotime($user['created_at'])) ?></div>
  </div>
</section>

<!-- STATS -->
<div class="profile-stats">
  <div class="profile-stat">
    <div class="profile-stat-num"><?= $fit_count ?></div>
    <div class="profile-stat-label">Fity</div>
  </div>
  <div class="profile-stat">
    <div class="profile-stat-num"><?= $post_count ?></div>
    <div class="profile-stat-label">Posty</div>
  </div>
  <div class="profile-stat">
    <div class="profile-stat-num"><?= $wardrobe_count ?></div>
    <div class="profile-stat-label">Szafa</div>
  </div>
</div>

<!-- FITY -->
<div class="section-label">/// Fity</div>
<div class="fits-grid">
  <?php if ($fits): ?>
    <?php foreach ($fits as $fit): ?>
      <a href="/grailos/fit.php?id=<?= $fit['id'] ?>" class="fit-card">
        <div class="fit-img-wrap">
          <img src="<?= htmlspecialchars($fit['image_url']) ?>" alt="Fit">
        </div>
        <div class="fit-info">
          <div class="fit-footer">
            <span class="fit-date"><?= date('d M Y', strtotime($fit['created_at'])) ?></span>
            <span class="vote-count-static">♥ <?= $fit['votes'] ?></span>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="grid-column:span 3; padding:40px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
      Brak fitów
    </div>
  <?php endif; ?>
</div>

<!-- SZAFA -->
<div class="section-label">/// Szafa (<?= $wardrobe_count ?> itemów)</div>
<div class="clothing-grid">
  <?php if ($wardrobe): ?>
    <?php foreach ($wardrobe as $item): ?>
      <div class="clothing-card">
        <div class="clothing-card-img">
          <?php if ($item['image_url']): ?>
            <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
          <?php else: ?>
            NO IMG
          <?php endif; ?>
        </div>
        <div class="clothing-card-brand"><?= htmlspecialchars($item['brand'] ?? '—') ?></div>
        <div class="clothing-card-name"><?= htmlspecialchars($item['name'] ?? '—') ?></div>
        <div class="clothing-card-price">
          <?= $item['price'] ? number_format($item['price'], 2) . ' zł' : '—' ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="grid-column:span 3; padding:40px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
      Szafa pusta
    </div>
  <?php endif; ?>
</div>

<!-- POSTY -->
<div class="section-label">/// Ostatnie posty</div>
<div class="forum-feed">
  <?php if ($posts): ?>
    <?php foreach ($posts as $post): ?>
      <a href="/grailos/post.php?id=<?= $post['id'] ?>" class="post-card post-card-link">
        <div class="post-card-content" style="padding-left:0;">
          <?= htmlspecialchars(mb_substr($post['content'], 0, 200)) ?>
        </div>
        <div class="post-card-footer" style="padding-left:0;">
          <span class="post-stat"><?= date('d M Y', strtotime($post['created_at'])) ?></span>
          <span class="post-stat">💬 <?= $post['replies'] ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="padding:40px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
      Brak postów
    </div>
  <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>