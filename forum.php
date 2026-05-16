<?php require_once 'includes/header.php'; ?>

<section class="page-header">
  <div class="page-header-tag">/// Community</div>
  <h1 class="page-title">FORUM</h1>
  <p class="page-sub">Pytaj, odpowiadaj, dziel się.</p>
</section>

<?php
$post_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_post'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /grailos/login.php'); exit;
    }
    csrf_verify();
    $content = trim($_POST['content'] ?? '');
    if (!$content) {
        $post_error = 'Post nie może być pusty.';
    } elseif (strlen($content) > 1000) {
        $post_error = 'Post zbyt długi (max 1000 znaków).';
    } else {
        $pdo->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)")
            ->execute([$_SESSION['user_id'], $content]);
        header('Location: /grailos/forum.php'); exit;
    }
}

$posts = $pdo->query("
    SELECT posts.*, users.username,
           COUNT(DISTINCT post_likes.id)   AS likes,
           COUNT(DISTINCT post_replies.id) AS replies
    FROM posts
    LEFT JOIN users        ON posts.user_id = users.id
    LEFT JOIN post_likes   ON posts.id = post_likes.post_id
    LEFT JOIN post_replies ON posts.id = post_replies.post_id
    GROUP BY posts.id
    ORDER BY posts.created_at DESC
")->fetchAll();
?>

<!-- COMPOSE BOX -->
<div class="compose-wrap">
  <?php if (isset($_SESSION['user_id'])): ?>
    <?php if ($post_error): ?>
      <div class="form-error" style="margin-bottom:16px;"><?= htmlspecialchars($post_error) ?></div>
    <?php endif; ?>
    <form method="POST" class="compose-card">
      <?= csrf_field() ?>
      <div class="compose-header">
        <span class="compose-avatar">@<?= htmlspecialchars(strtoupper($_SESSION['username'][0])) ?></span>
        <span class="compose-name">@<?= htmlspecialchars($_SESSION['username']) ?></span>
      </div>
      <textarea name="content" class="compose-textarea"
                placeholder="Co słychać? Zapytaj, podziel się, pokaż grail..."
                maxlength="1000" rows="3"></textarea>
      <div class="compose-actions">
        <span class="compose-hint">max 1000 znaków</span>
        <button type="submit" name="new_post" class="btn">Opublikuj →</button>
      </div>
    </form>
  <?php else: ?>
    <div class="compose-locked">
      <span style="color:var(--gray); letter-spacing:2px; text-transform:uppercase; font-size:13px;">
        Dołącz do rozmowy
      </span>
      <div style="display:flex; gap:12px;">
        <a href="/grailos/login.php" class="btn">Login</a>
        <a href="/grailos/register.php" class="btn btn-outline">Register</a>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="section-label">/// <?= count($posts) ?> postów</div>

<!-- FEED -->
<div class="forum-feed">
  <?php if ($posts): ?>
    <?php foreach ($posts as $post):
      $liked = false;
      if (isset($_SESSION['user_id'])) {
        $lcheck = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
        $lcheck->execute([$post['id'], $_SESSION['user_id']]);
        $liked = (bool) $lcheck->fetch();
      }
      $can_delete = isset($_SESSION['user_id']) &&
                    ($post['user_id'] == $_SESSION['user_id'] || $_SESSION['is_admin']);
    ?>
      <div class="post-card">
        <div class="post-card-header">
          <div class="post-card-meta">
            <span class="post-avatar">@<?= strtoupper(htmlspecialchars($post['username'][0])) ?></span>
            <div>
              <div class="post-user">@<?= htmlspecialchars($post['username']) ?></div>
              <div class="post-date"><?= date('d M Y, H:i', strtotime($post['created_at'])) ?></div>
            </div>
          </div>
          <?php if ($can_delete): ?>
            <form method="POST" action="/grailos/delete_post.php"
                  onsubmit="return confirm('Usunac ten post?')">
              <?= csrf_field() ?>
              <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
              <button type="submit" class="post-delete-btn" title="Usuń post">✕</button>
            </form>
          <?php endif; ?>
        </div>

        <div class="post-card-content"><?= nl2br(htmlspecialchars($post['content'])) ?></div>

        <div class="post-card-footer">
          <a href="/grailos/post.php?id=<?= $post['id'] ?>" class="post-stat">
            💬 <?= $post['replies'] ?> <?= $post['replies'] == 1 ? 'odpowiedź' : 'odpowiedzi' ?>
          </a>
          <?php if (isset($_SESSION['user_id'])): ?>
            <button class="post-stat post-like-btn <?= $liked ? 'liked' : '' ?>"
                    onclick="likePost(<?= $post['id'] ?>, this)">
              ♥ <span class="like-count"><?= $post['likes'] ?></span>
            </button>
          <?php else: ?>
            <span class="post-stat">♥ <?= $post['likes'] ?></span>
          <?php endif; ?>
          <a href="/grailos/post.php?id=<?= $post['id'] ?>" class="post-stat post-reply-link">
            Odpowiedz →
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="padding:80px 40px; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:13px;">
      Brak postów. Zacznij rozmowę.
    </div>
  <?php endif; ?>
</div>

<script>
async function likePost(postId, btn) {
  const res  = await fetch('/grailos/post_like.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `post_id=${postId}`
  });
  const data = await res.json();
  if (data.success) {
    btn.classList.toggle('liked', data.liked);
    btn.querySelector('.like-count').textContent = data.likes;
  }
}
</script>

<?php require_once 'includes/footer.php'; ?>