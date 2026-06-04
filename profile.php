<?php
require_once 'includes/header.php';

$username = trim($_GET['username'] ?? '');
if (!$username) { header('Location: /grailos/'); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_banned = 0");
$stmt->execute([$username]);
$user = $stmt->fetch();
if (!$user) { header('Location: /grailos/'); exit; }

$is_own    = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id'];

// Counts
$fit_count      = $pdo->prepare("SELECT COUNT(*) FROM fits WHERE user_id = ?");
$fit_count->execute([$user['id']]);
$fit_count = (int)$fit_count->fetchColumn();

$post_count     = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
$post_count->execute([$user['id']]);
$post_count = (int)$post_count->fetchColumn();

$wardrobe_count = $pdo->prepare("SELECT COUNT(*) FROM wardrobe_items WHERE user_id = ?");
$wardrobe_count->execute([$user['id']]);
$wardrobe_count = (int)$wardrobe_count->fetchColumn();

$followers_count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE following_id = ?");
$followers_count->execute([$user['id']]);
$followers_count = (int)$followers_count->fetchColumn();

$following_count = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
$following_count->execute([$user['id']]);
$following_count = (int)$following_count->fetchColumn();

$is_following = false;
if (isset($_SESSION['user_id']) && !$is_own) {
    $fcheck = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
    $fcheck->execute([$_SESSION['user_id'], $user['id']]);
    $is_following = (bool)$fcheck->fetch();
}

// Fity
$fits = $pdo->prepare("
    SELECT fits.*, COUNT(fit_votes.id) AS votes
    FROM fits LEFT JOIN fit_votes ON fits.id = fit_votes.fit_id
    WHERE fits.user_id = ?
    GROUP BY fits.id ORDER BY fits.created_at DESC
");
$fits->execute([$user['id']]);
$fits = $fits->fetchAll();

// Posty
$posts = $pdo->prepare("
    SELECT posts.*, COUNT(post_replies.id) AS replies
    FROM posts LEFT JOIN post_replies ON posts.id = post_replies.post_id
    WHERE posts.user_id = ?
    GROUP BY posts.id ORDER BY posts.created_at DESC LIMIT 5
");
$posts->execute([$user['id']]);
$posts = $posts->fetchAll();

// Szafa
$wardrobe = $pdo->prepare("
    SELECT wardrobe_items.*, clothing.name, clothing.brand,
           clothing.image_url, clothing.price
    FROM wardrobe_items
    LEFT JOIN clothing ON wardrobe_items.clothing_id = clothing.id
    WHERE wardrobe_items.user_id = ?
    ORDER BY wardrobe_items.created_at DESC
");
$wardrobe->execute([$user['id']]);
$wardrobe = $wardrobe->fetchAll();

// Outfity
$outfits = $pdo->prepare("
    SELECT outfits.*,
           COUNT(outfit_items.id) AS item_count
    FROM outfits
    LEFT JOIN outfit_items ON outfits.id = outfit_items.outfit_id
    WHERE outfits.user_id = ?
    GROUP BY outfits.id
    ORDER BY outfits.created_at DESC
");
$outfits->execute([$user['id']]);
$outfits = $outfits->fetchAll();
?>

<!-- PROFILE HEADER -->
<section class="profile-header">
  <div class="profile-avatar-wrap">
  <?php if ($user['avatar_url']): ?>
    <img src="<?= htmlspecialchars($user['avatar_url']) ?>"
         class="profile-avatar-img" alt="">
  <?php else: ?>
    <div class="profile-avatar">
      <?= strtoupper(htmlspecialchars($user['username'][0])) ?>
    </div>
  <?php endif; ?>

  <?php if ($is_own): ?>
    <div class="avatar-actions">
      <form method="POST" action="/grailos/upload_avatar.php"
            enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label class="avatar-action-btn" title="Zmień zdjęcie">
          ✎
          <input type="file" name="avatar"
                 accept="image/jpeg,image/png,image/webp"
                 onchange="this.form.submit()" style="display:none">
        </label>
      </form>
      <?php if ($user['avatar_url']): ?>
        <form method="POST" action="/grailos/delete_avatar.php"
              onsubmit="return confirm('Usunąć zdjęcie profilowe?')">
          <?= csrf_field() ?>
          <button type="submit" class="avatar-action-btn avatar-delete-btn" title="Usuń zdjęcie">✕</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

  <div class="profile-info">
  <div class="profile-username">
    @<?= htmlspecialchars($user['username']) ?>
    <?php if ($user['is_admin']): ?>
      <span class="profile-badge">ADMIN</span>
    <?php endif; ?>
  </div>
  <div class="profile-joined">Dołączył <?= date('F Y', strtotime($user['created_at'])) ?></div>

  <?php if ($is_own): ?>
    <a href="/grailos/settings.php" class="btn btn-outline"
       style="margin-top:16px; font-size:11px; padding:6px 16px;">⚙ Ustawienia konta</a>
  <?php endif; ?>

  <?php if (!$is_own && isset($_SESSION['user_id'])): ?>
    <button class="follow-btn <?= $is_following ? 'following' : '' ?>"
            id="followBtn"
            onclick="toggleFollow(<?= $user['id'] ?>)">
      <span class="follow-label"><?= $is_following ? 'Obserwujesz' : '+ Obserwuj' ?></span>
      <span class="unfollow-label">Unfollow</span>
    </button>
  <?php endif; ?>
  </div>
</section>

<!-- STATS -->
<div class="profile-stats">
  <div class="profile-stat">
    <div class="profile-stat-num"><?= $fit_count ?></div>
    <div class="profile-stat-label">Fity</div>
  </div>
  <div class="profile-stat">
    <div class="profile-stat-num"><?= $followers_count ?></div>
    <div class="profile-stat-label">Obserwujących</div>
  </div>
  <div class="profile-stat">
    <div class="profile-stat-num"><?= $following_count ?></div>
    <div class="profile-stat-label">Obserwuje</div>
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
    <div style="grid-column:span 3; padding:40px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">Brak fitów</div>
  <?php endif; ?>
</div>

<!-- OUTFITY -->
<div class="section-label" style="display:flex; justify-content:space-between; align-items:center;">
  <span>/// Outfity (<?= count($outfits) ?>)</span>
  <?php if ($is_own): ?>
    <a href="/grailos/create_outfit.php" class="btn" style="margin-right:40px; font-size:11px; padding:6px 16px;">+ Nowy outfit</a>
  <?php endif; ?>
</div>
<div class="outfits-grid">
  <?php if ($outfits): ?>
    <?php foreach ($outfits as $outfit):
      $outfit_imgs = $pdo->prepare("
          SELECT clothing.image_url FROM outfit_items
          LEFT JOIN wardrobe_items ON outfit_items.wardrobe_item_id = wardrobe_items.id
          LEFT JOIN clothing ON wardrobe_items.clothing_id = clothing.id
          WHERE outfit_items.outfit_id = ? LIMIT 4
      ");
      $outfit_imgs->execute([$outfit['id']]);
      $imgs = $outfit_imgs->fetchAll(PDO::FETCH_COLUMN);
    ?>
      <div class="outfit-card">
        <div class="outfit-card-imgs">
          <?php foreach (array_pad($imgs, 4, null) as $img): ?>
            <div class="outfit-card-img">
              <?php if ($img): ?>
                <img src="<?= htmlspecialchars($img) ?>" alt="">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="outfit-card-info">
          <div class="outfit-card-name"><?= htmlspecialchars($outfit['name']) ?></div>
          <div class="outfit-card-count"><?= $outfit['item_count'] ?> items</div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="grid-column:span 3; padding:40px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
      <?= $is_own ? 'Brak outfitów. <a href="/grailos/create_outfit.php" style="color:var(--red)">Stwórz pierwszy →</a>' : 'Brak outfitów' ?>
    </div>
  <?php endif; ?>
</div>

<!-- SZAFA -->
<div class="section-label">/// Szafa (<?= $wardrobe_count ?> itemów)</div>
<div class="clothing-grid">
  <?php if ($wardrobe): ?>
    <?php foreach ($wardrobe as $item): ?>
  <div class="clothing-card" style="position:relative;">
    <div class="clothing-card-img">
      <?php if ($item['image_url']): ?>
        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="">
      <?php else: ?>NO IMG<?php endif; ?>
    </div>
    <div class="clothing-card-brand"><?= htmlspecialchars($item['brand'] ?? '—') ?></div>
    <div class="clothing-card-name"><?= htmlspecialchars($item['name'] ?? '—') ?></div>
    <div class="clothing-card-price"><?= $item['price'] ? number_format($item['price'],2).' zł' : '—' ?></div>
    <?php if ($is_own): ?>
      <form method="POST" action="/grailos/wardrobe_remove.php"
            style="position:absolute; top:8px; right:8px;">
        <?= csrf_field() ?>
        <input type="hidden" name="wardrobe_item_id" value="<?= $item['id'] ?>">
        <button type="submit" class="inline-delete-btn"
                onclick="return confirm('Usunąć z szafy?')"
                style="opacity:1;">✕</button>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
  <?php else: ?>
    <div style="grid-column:span 3; padding:40px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">Szafa pusta</div>
  <?php endif; ?>
</div>

<!-- POSTY -->
<div class="section-label">/// Ostatnie posty</div>
<div class="forum-feed" style="margin-bottom:80px;">
  <?php if ($posts): ?>
    <?php foreach ($posts as $post): ?>
      <a href="/grailos/post.php?id=<?= $post['id'] ?>" class="post-card post-card-link">
        <?php if ($post['content']): ?>
        <div class="post-card-content" style="padding-left:0;">
          <?= htmlspecialchars(mb_substr($post['content'], 0, 200)) ?>
        </div>
        <?php endif; ?>
        <?php if ($post['image_url']): ?>
        <div class="post-img-wrap" style="margin-left:0; max-width:100%;">
          <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="">
        </div>
        <?php endif; ?>
        <div class="post-card-footer" style="padding-left:0;">
          <span class="post-stat"><?= date('d M Y', strtotime($post['created_at'])) ?></span>
          <span class="post-stat">💬 <?= $post['replies'] ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="padding:40px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">Brak postów</div>
  <?php endif; ?>
</div>

<script>
async function toggleFollow(userId) {
  const btn = document.getElementById('followBtn');
  const res  = await fetch('/grailos/follow.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `target_id=${userId}&csrf_token=<?= csrf_token() ?>`
  });
  const data = await res.json();
  if (data.success) {
    btn.textContent  = data.following ? 'Obserwujesz' : '+ Obserwuj';
    btn.className    = 'follow-btn' + (data.following ? ' following' : '');
    document.querySelector('.profile-stat-num').textContent = data.followers;
  }
}
</script>

<?php require_once 'includes/footer.php'; ?>