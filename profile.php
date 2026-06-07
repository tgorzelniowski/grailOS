<?php
require_once 'includes/header.php';

$username = trim($_GET['username'] ?? '');
if (!$username) { header('Location: /'); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_banned = 0");
$stmt->execute([$username]);
$user = $stmt->fetch();
if (!$user) { header('Location: /'); exit; }

$is_own    = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id'];
$viewer_admin = !empty($_SESSION['is_admin']);

require_once __DIR__ . '/includes/stories_lib.php';
$story_meta = active_story_meta($pdo, (int)$user['id'], (int)($_SESSION['user_id'] ?? 0));

// Widoczność sekcji — właściciel i admin widzą zawsze; inni tylko gdy flaga = 1
$can_see = function($flag) use ($user, $is_own, $viewer_admin) {
    if ($is_own || $viewer_admin) return true;
    return (int)($user[$flag] ?? 1) === 1;
};
$see_fits     = $can_see('show_fits');
$see_outfits  = $can_see('show_outfits');
$see_wardrobe = $can_see('show_wardrobe');
$see_posts    = $can_see('show_posts');
$see_follows  = $can_see('show_follows');

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
    SELECT posts.*,
           (SELECT COUNT(*) FROM post_replies WHERE post_replies.post_id = posts.id) AS replies,
           (SELECT COUNT(*) FROM post_likes   WHERE post_likes.post_id   = posts.id) AS likes
    FROM posts
    WHERE posts.user_id = ?
    ORDER BY posts.created_at DESC LIMIT 10
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
  <?php
    $story_open = $story_meta['allowed'] && $story_meta['has'];
    if ($story_open):
      $ring_cls = $story_meta['unseen'] ? 'profile-av-ring unseen' : 'profile-av-ring seen';
  ?>
    <a href="/story_view.php?user=<?= (int)$user['id'] ?>" class="<?= $ring_cls ?>"
       title="Zobacz storkę">
      <?php if ($user['avatar_url']): ?>
        <img src="<?= htmlspecialchars($user['avatar_url']) ?>" class="profile-avatar-img" alt="">
      <?php else: ?>
        <div class="profile-avatar"><?= strtoupper(htmlspecialchars($user['username'][0])) ?></div>
      <?php endif; ?>
    </a>
  <?php elseif ($user['avatar_url']): ?>
    <img src="<?= htmlspecialchars($user['avatar_url']) ?>"
         class="profile-avatar-img" alt="">
  <?php else: ?>
    <div class="profile-avatar">
      <?= strtoupper(htmlspecialchars($user['username'][0])) ?>
    </div>
  <?php endif; ?>

  <?php if ($is_own): ?>
    <div class="avatar-actions">
      <form method="POST" action="/upload_avatar.php"
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
        <form method="POST" action="/delete_avatar.php">
          <?= csrf_field() ?>
          <button type="button" class="avatar-action-btn avatar-delete-btn" title="Usuń zdjęcie"
                  onclick="grailConfirm('Usunąć zdjęcie profilowe?', () => this.closest('form').submit())">✕</button>
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

  <?php if (!empty($user['bio'])): ?>
    <p class="profile-bio"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
  <?php endif; ?>

  <?php if ($is_own): ?>
    <a href="/settings.php" class="btn btn-outline"
       style="margin-top:16px; font-size:11px; padding:6px 16px;">⚙ Ustawienia konta</a>
  <?php endif; ?>

  <?php if (!$is_own && isset($_SESSION['user_id'])): ?>
    <div style="display:flex; align-items:center; gap:14px; margin-top:16px;">
      <button class="follow-btn <?= $is_following ? 'following' : '' ?>"
              id="followBtn" style="margin-top:0;"
              onclick="toggleFollow(<?= $user['id'] ?>)">
        <span class="follow-label"><?= $is_following ? 'Obserwujesz' : '+ Obserwuj' ?></span>
        <span class="unfollow-label">Unfollow</span>
      </button>
      <button class="report-btn"
              onclick="grailReport('profile', <?= (int)$user['id'] ?>, 'Zgłaszasz profil @<?= htmlspecialchars($user['username']) ?>')">⚑ Zgłoś</button>
    </div>
  <?php endif; ?>
  </div>
</section>

<!-- STATS -->
<div class="profile-stats">
  <div class="profile-stat">
    <div class="profile-stat-num"><?= $fit_count ?></div>
    <div class="profile-stat-label">Fity</div>
  </div>
  <?php if ($see_follows): ?>
    <a class="profile-stat profile-stat-link"
       href="/follows_list.php?username=<?= urlencode($user['username']) ?>&type=followers">
      <div class="profile-stat-num" id="followersCount"><?= $followers_count ?></div>
      <div class="profile-stat-label">Obserwujących</div>
    </a>
    <a class="profile-stat profile-stat-link"
       href="/follows_list.php?username=<?= urlencode($user['username']) ?>&type=following">
      <div class="profile-stat-num"><?= $following_count ?></div>
      <div class="profile-stat-label">Obserwuje</div>
    </a>
  <?php else: ?>
    <div class="profile-stat">
      <div class="profile-stat-num" id="followersCount"><?= $followers_count ?></div>
      <div class="profile-stat-label">Obserwujących</div>
    </div>
    <div class="profile-stat">
      <div class="profile-stat-num"><?= $following_count ?></div>
      <div class="profile-stat-label">Obserwuje</div>
    </div>
  <?php endif; ?>
  <div class="profile-stat">
    <div class="profile-stat-num"><?= $wardrobe_count ?></div>
    <div class="profile-stat-label">Szafa</div>
  </div>
</div>

<!-- FITY -->
<?php if ($see_fits): ?>
<div class="section-label" style="display:flex; justify-content:space-between; align-items:center;">
  <span>/// Fity (<?= count($fits) ?>)</span>
  <?php if (count($fits) > 4): ?>
    <button class="btn btn-outline" style="margin-right:40px; font-size:11px; padding:6px 14px;"
            onclick="toggleCollapse(this, '.fit-collapse-item')">Pokaż więcej ↓</button>
  <?php endif; ?>
</div>
<div class="fits-grid">
  <?php if ($fits): ?>
    <?php foreach ($fits as $fidx => $fit): ?>
      <a href="/fit.php?id=<?= $fit['id'] ?>"
         class="fit-card <?= $fidx >= 4 ? 'fit-collapse-item' : '' ?>">
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
<?php endif; ?>

<!-- OUTFITY -->
<?php if ($see_outfits): ?>
<div class="section-label" style="display:flex; justify-content:space-between; align-items:center;">
  <span>/// Outfity (<?= count($outfits) ?>)</span>
  <div style="display:flex; gap:10px; align-items:center; margin-right:40px;">
    <?php if (count($outfits) > 4): ?>
      <button class="btn btn-outline" style="font-size:11px; padding:6px 14px;"
              onclick="toggleCollapse(this, '.outfit-collapse-item')">Pokaż więcej ↓</button>
    <?php endif; ?>
    <?php if ($is_own): ?>
      <a href="/create_outfit.php" class="btn" style="font-size:11px; padding:6px 16px;">+ Nowy outfit</a>
    <?php endif; ?>
  </div>
</div>
<div class="outfits-grid outfits-grid--profile">
  <?php if ($outfits): ?>
    <?php foreach ($outfits as $oidx => $outfit):
      $outfit_imgs = $pdo->prepare("
          SELECT clothing.image_url FROM outfit_items
          LEFT JOIN wardrobe_items ON outfit_items.wardrobe_item_id = wardrobe_items.id
          LEFT JOIN clothing ON wardrobe_items.clothing_id = clothing.id
          WHERE outfit_items.outfit_id = ? LIMIT 4
      ");
      $outfit_imgs->execute([$outfit['id']]);
      $imgs = $outfit_imgs->fetchAll(PDO::FETCH_COLUMN);
    ?>
      <div class="outfit-card profile-clickable <?= $oidx >= 4 ? 'outfit-collapse-item' : '' ?>"
           data-href="/view_outfit.php?id=<?= $outfit['id'] ?>" style="position:relative; cursor:pointer;">
        <?php if ($is_own): ?>
          <form method="POST" action="/delete_outfit.php" class="outfit-delete-form">
            <?= csrf_field() ?>
            <input type="hidden" name="outfit_id" value="<?= $outfit['id'] ?>">
            <button type="button" class="inline-delete-btn"
                    onclick="grailConfirm('Usunąć outfit?', () => this.closest(&quot;form&quot;).submit())"
                    style="opacity:1;">✕</button>
          </form>
        <?php endif; ?>
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
    <div style="grid-column:1/-1; padding:40px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">
      <?= $is_own ? 'Brak outfitów. <a href="/create_outfit.php" style="color:var(--red)">Stwórz pierwszy →</a>' : 'Brak outfitów' ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- SZAFA -->
<?php if ($see_wardrobe): ?>
<div class="section-label" style="display:flex; justify-content:space-between; align-items:center;">
  <span>/// Szafa (<?= $wardrobe_count ?> itemów)</span>
  <?php if ($wardrobe_count > 4): ?>
    <button class="btn btn-outline" style="margin-right:40px; font-size:11px; padding:6px 14px;"
            onclick="toggleCollapse(this, '.wardrobe-collapse-item')">Pokaż więcej ↓</button>
  <?php endif; ?>
</div>
<div class="clothing-grid clothing-grid--profile">
  <?php if ($wardrobe): ?>
    <?php foreach ($wardrobe as $widx => $item): ?>
  <div class="clothing-card <?= $widx >= 4 ? 'wardrobe-collapse-item' : '' ?> <?= $item['clothing_id'] ? 'profile-clickable' : '' ?>"
       <?= $item['clothing_id'] ? 'data-href="/item.php?id=' . (int)$item['clothing_id'] . '" style="position:relative; cursor:pointer;"' : 'style="position:relative;"' ?>>
    <div class="clothing-card-img">
      <?php if ($item['image_url']): ?>
        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="">
      <?php else: ?>NO IMG<?php endif; ?>
    </div>
    <div class="clothing-card-brand"><?= htmlspecialchars($item['brand'] ?? '—') ?></div>
    <div class="clothing-card-name"><?= htmlspecialchars($item['name'] ?? '—') ?></div>
    <div class="clothing-card-price"><?= $item['price'] ? number_format($item['price'],2).' zł' : '—' ?></div>
    <?php if ($is_own): ?>
      <form method="POST" action="/wardrobe_remove.php"
            style="position:absolute; top:8px; right:8px;">
        <?= csrf_field() ?>
        <input type="hidden" name="wardrobe_item_id" value="<?= $item['id'] ?>">
        <button type="button" class="inline-delete-btn"
                onclick="grailConfirm('Usunąć z szafy?', () => this.closest('form').submit())"
                style="opacity:1;">✕</button>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
  <?php else: ?>
    <div style="grid-column:1/-1; padding:40px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">Szafa pusta</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- POSTY -->
<?php if ($see_posts): ?>
<div class="section-label" style="display:flex; justify-content:space-between; align-items:center;">
  <span>/// Ostatnie posty (<?= count($posts) ?>)</span>
  <?php if (count($posts) > 3): ?>
    <button class="profile-posts-toggle btn btn-outline"
            style="margin-right:40px; font-size:11px; padding:6px 16px;"
            onclick="toggleProfilePosts(this)">
      Pokaż więcej ↓
    </button>
  <?php endif; ?>
</div>
<div class="forum-center profile-posts-feed" style="margin-bottom:80px; padding-top:16px;">
  <?php if ($posts): ?>
    <?php foreach ($posts as $i => $post): ?>
      <div class="fpc post-card-clickable profile-post-item <?= $i >= 3 ? 'profile-post-hidden' : '' ?>"
           data-href="/post.php?id=<?= $post['id'] ?>">

        <!-- HEADER: avatar + username + date -->
        <div class="fpc-head">
          <div class="fpc-meta">
            <a href="/profile.php?username=<?= urlencode($user['username']) ?>"
               class="post-av-link" onclick="event.stopPropagation()">
              <?php if ($user['avatar_url']): ?>
                <img src="<?= htmlspecialchars($user['avatar_url']) ?>" class="post-av-img" alt="">
              <?php else: ?>
                <span class="post-avatar"><?= strtoupper(htmlspecialchars($user['username'][0])) ?></span>
              <?php endif; ?>
            </a>
            <a href="/profile.php?username=<?= urlencode($user['username']) ?>"
               class="fpc-username" onclick="event.stopPropagation()">@<?= htmlspecialchars($user['username']) ?></a>
            <span class="fpc-time"><?= date('d M Y, H:i', strtotime($post['created_at'])) ?></span>
          </div>
        </div>

        <!-- BODY: tekst -->
        <?php if ($post['content']): ?>
          <div class="fpc-body"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
        <?php endif; ?>

        <!-- ZDJĘCIE -->
        <?php if ($post['image_url']): ?>
          <div class="fpc-img">
            <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="">
          </div>
        <?php endif; ?>

        <!-- FOOTER: lajki + komentarze -->
        <div class="fpc-foot">
          <span class="fpc-btn">♥ <?= $post['likes'] ?></span>
          <a href="/post.php?id=<?= $post['id'] ?>" class="fpc-btn"
             onclick="event.stopPropagation()">
            💬 <?= $post['replies'] ?> <?= $post['replies'] == 1 ? 'odpowiedź' : 'odpowiedzi' ?>
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="padding:40px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:12px;">Brak postów</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
function toggleProfilePosts(btn) {
  const hidden = document.querySelectorAll('.profile-post-hidden');
  const expanded = btn.getAttribute('data-expanded') === '1';
  hidden.forEach(el => el.style.display = expanded ? 'none' : 'block');
  btn.setAttribute('data-expanded', expanded ? '0' : '1');
  btn.textContent = expanded ? 'Pokaż więcej ↓' : 'Zwiń ↑';
}

function toggleCollapse(btn, selector) {
  const hidden = document.querySelectorAll(selector);
  const expanded = btn.getAttribute('data-expanded') === '1';
  hidden.forEach(el => el.style.display = expanded ? 'none' : 'block');
  btn.setAttribute('data-expanded', expanded ? '0' : '1');
  btn.textContent = expanded ? 'Pokaż więcej ↓' : 'Zwiń ↑';
}

document.querySelectorAll('.profile-posts-feed .post-card-clickable, .profile-clickable').forEach(card => {
  card.addEventListener('click', e => {
    if (!e.target.closest('a, button, form')) {
      window.location.href = card.dataset.href;
    }
  });
});
</script>

<script>
async function toggleFollow(userId) {
  const btn = document.getElementById('followBtn');
  const res  = await fetch('/follow.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `target_id=${userId}&csrf_token=<?= csrf_token() ?>`
  });
  const data = await res.json();
  if (data.success) {
    btn.textContent  = data.following ? 'Obserwujesz' : '+ Obserwuj';
    btn.className    = 'follow-btn' + (data.following ? ' following' : '');
    const fc = document.getElementById('followersCount');
    if (fc) fc.textContent = data.followers;
  }
}
</script>

<?php require_once 'includes/footer.php'; ?>