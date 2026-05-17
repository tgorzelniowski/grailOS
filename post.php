<?php
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /grailos/forum.php'); exit; }

$stmt = $pdo->prepare("
    SELECT posts.*, users.username, users.avatar_url,
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

$reply_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    if (!isset($_SESSION['user_id'])) { header('Location: /grailos/login.php'); exit; }
    csrf_verify();
    $content = trim($_POST['content'] ?? '');
    if (!$content) {
        $reply_error = 'Odpowiedź nie może być pusta.';
    } elseif (strlen($content) > 500) {
        $reply_error = 'Odpowiedź zbyt długa (max 500 znaków).';
    } else {
        $pdo->prepare("INSERT INTO post_replies (post_id, user_id, content) VALUES (?, ?, ?)")
            ->execute([$id, $_SESSION['user_id'], $content]);
        header("Location: /grailos/post.php?id=$id"); exit;
    }
}

$replies = $pdo->prepare("
    SELECT post_replies.*, users.username, users.avatar_url
    FROM post_replies
    LEFT JOIN users ON post_replies.user_id = users.id
    WHERE post_replies.post_id = ?
    ORDER BY post_replies.created_at ASC
");
$replies->execute([$id]);
$replies = $replies->fetchAll();

$liked = false;
if (isset($_SESSION['user_id'])) {
    $l = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
    $l->execute([$id, $_SESSION['user_id']]);
    $liked = (bool)$l->fetch();
}

require_once 'includes/header.php';
?>

<div style="max-width:720px; margin:0 auto; padding:0 40px 80px;">

  <!-- ORYGINALNY POST -->
  <div class="post-card post-single">
    <div class="post-card-header">
      <div class="post-card-meta">
        <a href="/grailos/profile.php?username=<?= urlencode($post['username']) ?>"
           class="post-av-link">
          <?php if ($post['avatar_url']): ?>
            <img src="<?= htmlspecialchars($post['avatar_url']) ?>"
                 class="post-av-img post-av-lg" alt="">
          <?php else: ?>
            <span class="post-avatar post-avatar-lg">
              <?= strtoupper(htmlspecialchars($post['username'][0])) ?>
            </span>
          <?php endif; ?>
        </a>
        <div>
          <a href="/grailos/profile.php?username=<?= urlencode($post['username']) ?>"
             class="post-user-link">@<?= htmlspecialchars($post['username']) ?></a>
          <div class="post-date"><?= date('d M Y, H:i', strtotime($post['created_at'])) ?></div>
        </div>
      </div>
    </div>

    <?php if ($post['content']): ?>
      <div class="post-card-content post-content-large"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
    <?php endif; ?>

    <?php if ($post['image_url']): ?>
      <div class="post-img-wrap">
        <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="">
      </div>
    <?php endif; ?>

    <div class="post-card-footer">
      <span class="post-stat">💬 <?= count($replies) ?> odpowiedzi</span>
      <?php if (isset($_SESSION['user_id'])): ?>
        <button class="post-stat post-like-btn <?= $liked ? 'liked' : '' ?>"
                onclick="likePost(<?= $post['id'] ?>, this)">
          ♥ <span class="like-count"><?= $post['likes'] ?></span>
        </button>
      <?php else: ?>
        <span class="post-stat">♥ <?= $post['likes'] ?></span>
      <?php endif; ?>
      <a href="/grailos/forum.php" class="post-stat">← Forum</a>
    </div>
  </div>

  <!-- REPLIES -->
  <div class="section-label" style="padding:20px 0; border-color:var(--border);">
    /// <?= count($replies) ?> odpowiedzi
  </div>

  <?php foreach ($replies as $r): ?>
    <div class="post-card reply-card">
      <div class="post-card-header">
        <div class="post-card-meta">
          <a href="/grailos/profile.php?username=<?= urlencode($r['username']) ?>"
             class="post-av-link">
            <?php if ($r['avatar_url']): ?>
              <img src="<?= htmlspecialchars($r['avatar_url']) ?>"
                   class="post-av-img" alt="">
            <?php else: ?>
              <span class="post-avatar"><?= strtoupper($r['username'][0]) ?></span>
            <?php endif; ?>
          </a>
          <div>
            <a href="/grailos/profile.php?username=<?= urlencode($r['username']) ?>"
               class="post-user-link">@<?= htmlspecialchars($r['username']) ?></a>
            <div class="post-date"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></div>
          </div>
        </div>
      </div>
      <div class="post-card-content"><?= nl2br(htmlspecialchars($r['content'])) ?></div>
    </div>
  <?php endforeach; ?>

  <!-- FORMULARZ ODPOWIEDZI -->
  <?php if (isset($_SESSION['user_id'])): ?>
    <div style="margin-top:24px;">
      <?php if ($reply_error): ?>
        <div class="form-error" style="margin-bottom:16px;"><?= htmlspecialchars($reply_error) ?></div>
      <?php endif; ?>
      <form method="POST" class="compose-card">
        <?= csrf_field() ?>
        <textarea name="content" class="compose-textarea"
                  placeholder="Napisz odpowiedź..."
                  maxlength="500" rows="3"></textarea>
        <div class="compose-actions">
          <span class="compose-hint">max 500 znaków</span>
          <button type="submit" name="reply" class="btn">Odpowiedz →</button>
        </div>
      </form>
    </div>
  <?php else: ?>
    <div class="compose-locked" style="margin-top:24px;">
      <a href="/grailos/login.php" class="btn">Zaloguj się by odpowiedzieć</a>
    </div>
  <?php endif; ?>

</div>

<script>
async function likePost(postId, btn) {
  const res  = await fetch('/grailos/post_like.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
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