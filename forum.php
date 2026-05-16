<?php require_once 'includes/header.php'; ?>

<section class="page-header">
  <div class="page-header-tag">/// Community</div>
  <h1 class="page-title">FORUM</h1>
  <p class="page-sub">Pytaj, odpowiadaj, dziel się.</p>
</section>

<?php
// Dodaj post
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
        header('Location: /grailos/forum.php');
        exit;
    }
}

// Pobierz posty
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

<!-- FORMULARZ NOWEGO POSTA -->
<?php if (isset($_SESSION['user_id'])): ?>
<div class="forum-compose">
  <?php if ($post_error): ?>
    <div class="form-error" style="margin-bottom:16px;"><?= htmlspecialchars($post_error) ?></div>
  <?php endif; ?>
  <form method="POST" class="compose-form">
    <?= csrf_field() ?>
    <div class="compose-top">
      <span class="compose-user">@<?= htmlspecialchars($_SESSION['username']) ?></span>
    </div>
    <textarea name="content" class="compose-input"
              placeholder="Co słychać? Zapytaj, podziel się..."
              maxlength="1000" rows="3"></textarea>
    <div class="compose-footer">
      <span class="compose-hint">max 1000 znaków</span>
      <button type="submit" name="new_post" class="btn">Opublikuj →</button>
    </div>
  </form>
</div>
<?php else: ?>
<div class="forum-login-prompt">
  <a href="/grailos/login.php" class="btn">Zaloguj się by pisać</a>
</div>
<?php endif; ?>

<div class="section-label">/// <?= count($posts) ?> postów</div>

<!-- FEED POSTÓW -->
<div class="forum-feed">
  <?php if ($posts): ?>
    <?php foreach ($posts as $post):
      $liked = false;
      if (isset($_SESSION['user_id'])) {
        $lcheck = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
        $lcheck->execute([$post['id'], $_SESSION['user_id']]);
        $liked = (bool) $lcheck->fetch();
      }
    ?>
      <div class="forum-post">
        <div class="forum-post-header">
          <span class="post-user">@<?= htmlspecialchars($post['username']) ?></span>
          <span class="post-date"><?= date('d M Y, H:i', strtotime($post['created_at'])) ?></span>
        </div>
        <div class="forum-post-content"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
        <div class="forum-post-footer">
          <a href="/grailos/post.php?id=<?= $post['id'] ?>" class="post-action">
            💬 <?= $post['replies'] ?> odpowiedzi
          </a>
          <?php if (isset($_SESSION['user_id'])): ?>
            <button class="post-action post-like <?= $liked ? 'liked' : '' ?>"
                    onclick="likePost(<?= $post['id'] ?>, this)">
              ♥ <span class="like-count"><?= $post['likes'] ?></span>
            </button>
          <?php else: ?>
            <span class="post-action">♥ <?= $post['likes'] ?></span>
          <?php endif; ?>
          <a href="/grailos/post.php?id=<?= $post['id'] ?>" class="post-action post-open">
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