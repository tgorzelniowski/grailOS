<?php
require_once 'includes/header.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { header('Location: /grailos/forum.php'); exit; }

$stmt = $pdo->prepare("
    SELECT posts.*, users.username,
           COUNT(DISTINCT post_likes.id) AS likes
    FROM posts
    LEFT JOIN users      ON posts.user_id = users.id
    LEFT JOIN post_likes ON posts.id = post_likes.post_id
    WHERE posts.id = ?
    GROUP BY posts.id
");
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) { header('Location: /grailos/forum.php'); exit; }

// Dodaj odpowiedź
$reply_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /grailos/login.php'); exit;
    }
    csrf_verify();
    $content = trim($_POST['content'] ?? '');
    if (!$content) {
        $reply_error = 'Odpowiedź nie może być pusta.';
    } elseif (strlen($content) > 500) {
        $reply_error = 'Odpowiedź zbyt długa (max 500 znaków).';
    } else {
        $pdo->prepare("INSERT INTO post_replies (post_id, user_id, content) VALUES (?, ?, ?)")
            ->execute([$id, $_SESSION['user_id'], $content]);
        header("Location: /grailos/post.php?id=$id");
        exit;
    }
}

// Pobierz odpowiedzi
$replies = $pdo->prepare("
    SELECT post_replies.*, users.username
    FROM post_replies
    LEFT JOIN users ON post_replies.user_id = users.id
    WHERE post_replies.post_id = ?
    ORDER BY post_replies.created_at ASC
");
$replies->execute([$id]);
$replies = $replies->fetchAll();

// Czy lajkował?
$liked = false;
if (isset($_SESSION['user_id'])) {
    $l = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
    $l->execute([$id, $_SESSION['user_id']]);
    $liked = (bool) $l->fetch();
}
?>

<div style="max-width:720px; margin:0 auto; padding:0 40px;">

  <!-- ORYGINALNY POST -->
  <div class="forum-post post-single">
    <div class="forum-post-header">
      <span class="post-user">@<?= htmlspecialchars($post['username']) ?></span>
      <span class="post-date"><?= date('d M Y, H:i', strtotime($post['created_at'])) ?></span>
    </div>
    <div class="forum-post-content post-content-large">
      <?= nl2br(htmlspecialchars($post['content'])) ?>
    </div>
    <div class="forum-post-footer">
      <span class="post-action">💬 <?= count($replies) ?> odpowiedzi</span>
      <?php if (isset($_SESSION['user_id'])): ?>
        <button class="post-action post-like <?= $liked ? 'liked' : '' ?>"
                onclick="likePost(<?= $post['id'] ?>, this)">
          ♥ <span class="like-count"><?= $post['likes'] ?></span>
        </button>
      <?php else: ?>
        <span class="post-action">♥ <?= $post['likes'] ?></span>
      <?php endif; ?>
      <a href="/grailos/forum.php" class="post-action">← Wróć do forum</a>
    </div>
  </div>

  <!-- ODPOWIEDZI -->
  <div class="section-label" style="padding:20px 0; border-color:var(--border);">
    /// <?= count($replies) ?> odpowiedzi
  </div>

  <?php foreach ($replies as $r): ?>
    <div class="forum-post reply">
      <div class="forum-post-header">
        <span class="post-user">@<?= htmlspecialchars($r['username']) ?></span>
        <span class="post-date"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></span>
      </div>
      <div class="forum-post-content"><?= nl2br(htmlspecialchars($r['content'])) ?></div>
    </div>
  <?php endforeach; ?>

  <!-- FORMULARZ ODPOWIEDZI -->
  <?php if (isset($_SESSION['user_id'])): ?>
    <div class="forum-compose" style="margin:24px 0;">
      <?php if ($reply_error): ?>
        <div class="form-error" style="margin-bottom:16px;"><?= htmlspecialchars($reply_error) ?></div>
      <?php endif; ?>
      <form method="POST" class="compose-form">
        <?= csrf_field() ?>
        <textarea name="content" class="compose-input"
                  placeholder="Napisz odpowiedź..."
                  maxlength="500" rows="3"></textarea>
        <div class="compose-footer">
          <span class="compose-hint">max 500 znaków</span>
          <button type="submit" name="reply" class="btn">Odpowiedz →</button>
        </div>
      </form>
    </div>
  <?php else: ?>
    <div class="forum-login-prompt">
      <a href="/grailos/login.php" class="btn">Zaloguj się by odpowiedzieć</a>
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