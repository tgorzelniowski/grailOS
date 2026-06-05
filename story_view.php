<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /grailos/login.php'); exit;
}
$me       = (int)$_SESSION['user_id'];
$author   = (int)($_GET['user'] ?? 0);
$is_admin = !empty($_SESSION['is_admin']);
if (!$author) { header('Location: /grailos/'); exit; }

// Autor istnieje?
$u = $pdo->prepare("SELECT id, username, avatar_url FROM users WHERE id = ? AND is_banned = 0");
$u->execute([$author]);
$author_user = $u->fetch();
if (!$author_user) { header('Location: /grailos/'); exit; }

// Dostęp: autor, admin, lub obserwujący autora
$allowed = ($author === $me) || $is_admin;
if (!$allowed) {
    $f = $pdo->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
    $f->execute([$me, $author]);
    $allowed = (bool)$f->fetch();
}
if (!$allowed) { header('Location: /grailos/'); exit; }

// Storki autora z ostatnich 24h
$st = $pdo->prepare("
    SELECT * FROM stories
    WHERE user_id = ? AND created_at > NOW() - INTERVAL 24 HOUR
    ORDER BY created_at ASC
");
$st->execute([$author]);
$stories = $st->fetchAll();
if (!$stories) { header('Location: /grailos/'); exit; }

$count = count($stories);
$i     = (int)($_GET['i'] ?? 0);
if ($i < 0) $i = 0;
if ($i >= $count) $i = $count - 1;
$story = $stories[$i];

// Oznacz jako obejrzane (nie liczymy własnych wyświetleń autora)
if ($author !== $me) {
    $pdo->prepare("INSERT IGNORE INTO story_views (story_id, user_id) VALUES (?, ?)")
        ->execute([$story['id'], $me]);
}

// Licznik wyświetleń (tylko dla autora)
$views = 0;
$is_owner = ($author === $me);
if ($is_owner) {
    $vc = $pdo->prepare("SELECT COUNT(*) FROM story_views WHERE story_id = ?");
    $vc->execute([$story['id']]);
    $views = (int)$vc->fetchColumn();
}

// Lajki bieżącej storki
$likes_count = 0;
$user_liked  = false;
try {
    $lc = $pdo->prepare("SELECT COUNT(*) FROM story_likes WHERE story_id = ?");
    $lc->execute([$story['id']]);
    $likes_count = (int)$lc->fetchColumn();

    $ul = $pdo->prepare("SELECT 1 FROM story_likes WHERE story_id = ? AND user_id = ?");
    $ul->execute([$story['id'], $me]);
    $user_liked = (bool)$ul->fetchColumn();
} catch (Exception $e) {}

// Czas trwania jednej storki (sekundy) → auto-przewijanie
$story_duration = 6;

require_once 'includes/header.php';
?>

<div class="story-viewer">

  <!-- Progres -->
  <div class="story-progress">
    <?php for ($p = 0; $p < $count; $p++): ?>
      <div class="story-progress-seg <?= $p < $i ? 'done' : '' ?>">
        <span class="story-progress-fill <?= $p === $i ? 'active' : '' ?>"></span>
      </div>
    <?php endfor; ?>
  </div>

  <!-- Nagłówek -->
  <div class="story-viewer-head">
    <a href="/grailos/profile.php?username=<?= urlencode($author_user['username']) ?>" class="story-viewer-user">
      <?php if ($author_user['avatar_url']): ?>
        <img src="<?= htmlspecialchars($author_user['avatar_url']) ?>" alt="">
      <?php else: ?>
        <span class="story-init"><?= strtoupper(htmlspecialchars($author_user['username'][0])) ?></span>
      <?php endif; ?>
      <span>@<?= htmlspecialchars($author_user['username']) ?></span>
    </a>
    <span class="story-viewer-time"><?= date('d M, H:i', strtotime($story['created_at'])) ?></span>
    <div style="margin-left:auto; display:flex; gap:10px; align-items:center;">
      <?php if ($is_owner): ?>
        <span class="story-views" title="Wyświetlenia">👁 <?= $views ?></span>
        <form method="POST" action="/grailos/delete_story.php">
          <?= csrf_field() ?>
          <input type="hidden" name="story_id" value="<?= $story['id'] ?>">
          <input type="hidden" name="user" value="<?= $author ?>">
          <button type="button" class="story-del-btn"
                  onclick="grailConfirm('Usunąć storkę?', () => this.closest('form').submit())">✕ Usuń</button>
        </form>
      <?php endif; ?>
      <a href="/grailos/" class="story-close">✕</a>
    </div>
  </div>

  <!-- Zdjęcie -->
  <div class="story-stage">
    <?php if ($i > 0): ?>
      <a href="/grailos/story_view.php?user=<?= $author ?>&i=<?= $i - 1 ?>" class="story-nav story-nav-prev">‹</a>
    <?php endif; ?>

    <img src="<?= htmlspecialchars($story['image_url']) ?>" alt="Storka" class="story-image">

    <?php if ($i < $count - 1): ?>
      <a href="/grailos/story_view.php?user=<?= $author ?>&i=<?= $i + 1 ?>" class="story-nav story-nav-next">›</a>
    <?php else: ?>
      <a href="/grailos/" class="story-nav story-nav-next">›</a>
    <?php endif; ?>
  </div>

  <!-- Lajk -->
  <div class="story-actions">
    <button class="story-like-btn <?= $user_liked ? 'liked' : '' ?>" id="storyLikeBtn"
            onclick="toggleStoryLike(<?= $story['id'] ?>)">
      <span class="story-heart">♥</span>
      <span class="story-like-count"><?= $likes_count ?></span>
    </button>
  </div>

</div>

<?php
$next_url = $i < $count - 1
    ? "/grailos/story_view.php?user=$author&i=" . ($i + 1)
    : "/grailos/";
?>
<script>
const STORY_DURATION = <?= (int)$story_duration ?> * 1000;
const STORY_NEXT = <?= json_encode($next_url) ?>;

// Auto-przewijanie + animacja paska postępu
(function () {
  const fill = document.querySelector('.story-progress-fill.active');
  if (fill) {
    fill.style.transition = 'width ' + STORY_DURATION + 'ms linear';
    requestAnimationFrame(() => { fill.style.width = '100%'; });
  }
  let timer = setTimeout(() => { window.location.href = STORY_NEXT; }, STORY_DURATION);

  // Pauza przy przytrzymaniu na zdjęciu
  const stage = document.querySelector('.story-stage');
  if (stage) {
    const pause = () => { clearTimeout(timer); if (fill) fill.style.animationPlayState = 'paused'; };
    stage.addEventListener('mousedown', pause);
    stage.addEventListener('touchstart', pause, {passive:true});
  }
})();

async function toggleStoryLike(storyId) {
  const btn = document.getElementById('storyLikeBtn');
  const res = await fetch('/grailos/story_like.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `story_id=${storyId}`
  });
  const data = await res.json();
  if (data.success) {
    btn.classList.toggle('liked', data.liked);
    btn.querySelector('.story-like-count').textContent = data.likes;
  }
}
</script>

<?php require_once 'includes/footer.php'; ?>
