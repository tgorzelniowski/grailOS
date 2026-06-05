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
    $content   = trim($_POST['content'] ?? '');
    $parent_id = (int)($_POST['parent_id'] ?? 0) ?: null;

    if (!$content) {
        $reply_error = 'Odpowiedź nie może być pusta.';
    } elseif (strlen($content) > 500) {
        $reply_error = 'Odpowiedź zbyt długa (max 500 znaków).';
    } else {
        $pdo->prepare("INSERT INTO post_replies (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)")
            ->execute([$id, $_SESSION['user_id'], $content, $parent_id]);
        header("Location: /grailos/post.php?id=$id");
        exit;
    }
}

// Pobierz odpowiedzi — podziel na top-level i zagnieżdżone
$all_replies = $pdo->prepare("
    SELECT post_replies.*, users.username, users.avatar_url
    FROM post_replies
    LEFT JOIN users ON post_replies.user_id = users.id
    WHERE post_replies.post_id = ?
    ORDER BY post_replies.created_at ASC
");
$all_replies->execute([$id]);
$all_replies = $all_replies->fetchAll();

// Drzewko: pogrupuj po parent_id (NULL = odpowiedź najwyższego poziomu)
$top_replies   = [];
$child_replies = [];   // [parent_id] => [reply, ...]
foreach ($all_replies as $r) {
    if ($r['parent_id']) {
        $child_replies[$r['parent_id']][] = $r;
    } else {
        $top_replies[] = $r;
    }
}

$liked = false;
if (isset($_SESSION['user_id'])) {
    $l = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
    $l->execute([$id, $_SESSION['user_id']]);
    $liked = (bool)$l->fetch();
}

require_once 'includes/header.php';
require_once __DIR__ . '/includes/stories_lib.php';
$story_author_ids = array_merge([$post['user_id']], array_column($all_replies, 'user_id'));
$story_map = active_stories_map($pdo, $story_author_ids, (int)($_SESSION['user_id'] ?? 0));

// Avatar opakowany w link: storka jeśli aktywna, inaczej profil
function story_avatar_link(int $uid, array $u, int $size): string {
    global $story_map;
    $ring = story_ring_class($story_map, $uid);
    $href = $ring ? '/grailos/story_view.php?user=' . $uid
                  : '/grailos/profile.php?username=' . urlencode($u['username']);
    return "<a href='" . $href . "' class='post-av-link " . $ring . "' onclick='event.stopPropagation()'>"
         . renderAvatar($u, $size) . "</a>";
}

function renderAvatar($user, $size = 40) {
    $s = (int)$size;
    if ($user['avatar_url']) {
        return "<img src='" . htmlspecialchars($user['avatar_url']) . "'
                style='width:{$s}px;height:{$s}px;border-radius:50%;object-fit:cover;border:1px solid var(--border);' alt=''>";
    }
    $fs = (int)($size * 0.45);
    return "<span style='width:{$s}px;height:{$s}px;background:var(--card);border-radius:50%;
                          display:flex;align-items:center;justify-content:center;
                          font-family:Bebas Neue,sans-serif;font-size:{$fs}px;color:var(--red);flex-shrink:0;'>
              " . strtoupper(htmlspecialchars($user['username'][0])) . "
            </span>";
}

// Rekurencyjne drzewko odpowiedzi
function renderPostReply($r, $child_replies, $depth = 0) {
    $children = $child_replies[$r['id']] ?? [];
    $av_size  = $depth === 0 ? 36 : 28;
    $can_del  = isset($_SESSION['user_id']) &&
                ($r['user_id'] == $_SESSION['user_id'] || !empty($_SESSION['is_admin']));
    ?>
    <div class="reply-thread" id="reply-<?= $r['id'] ?>">
      <div class="post-card reply-card <?= $depth > 0 ? 'nested-reply' : '' ?>">
        <div class="post-card-header">
          <div class="post-card-meta">
            <?= story_avatar_link((int)$r['user_id'], $r, $av_size) ?>
            <div>
              <a href="/grailos/profile.php?username=<?= urlencode($r['username']) ?>"
                 class="post-user-link">@<?= htmlspecialchars($r['username']) ?></a>
              <div class="post-date"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></div>
            </div>
          </div>
          <div style="display:flex; gap:6px; align-items:center;">
            <?php if (isset($_SESSION['user_id']) && $r['user_id'] != $_SESSION['user_id']): ?>
              <button class="report-btn" onclick="grailReport('post_reply', <?= (int)$r['id'] ?>, 'Zgłaszasz odpowiedź @<?= htmlspecialchars($r['username']) ?>')">⚑</button>
            <?php endif; ?>
            <?php if ($can_del): ?>
              <form method="POST" action="/grailos/delete_post_reply.php">
                <?= csrf_field() ?>
                <input type="hidden" name="reply_id" value="<?= $r['id'] ?>">
                <input type="hidden" name="post_id" value="<?= $r['post_id'] ?>">
                <button type="button" class="post-delete-btn"
                        onclick="grailConfirm('Usunąć odpowiedź?', () => this.closest('form').submit())">✕</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
        <div class="post-card-content"><?= nl2br(htmlspecialchars($r['content'])) ?></div>
        <?php if (isset($_SESSION['user_id'])): ?>
          <button class="reply-toggle-btn" onclick="toggleReplyForm('rf-<?= $r['id'] ?>')">↩ Odpowiedz</button>
          <form method="POST" class="nested-reply-form" id="rf-<?= $r['id'] ?>" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="reply" value="1">
            <input type="hidden" name="parent_id" value="<?= $r['id'] ?>">
            <div class="nested-reply-input">
              <textarea name="content" class="compose-textarea"
                        placeholder="Odpowiadasz @<?= htmlspecialchars($r['username']) ?>..."
                        rows="2" maxlength="500"></textarea>
              <div style="display:flex; gap:8px; margin-top:8px;">
                <button type="submit" class="btn" style="font-size:11px; padding:6px 14px;">Wyślij →</button>
                <button type="button" class="btn btn-outline" style="font-size:11px; padding:6px 14px;"
                        onclick="toggleReplyForm('rf-<?= $r['id'] ?>')">Anuluj</button>
              </div>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($children): ?>
        <div class="nested-replies">
          <?php foreach ($children as $child) renderPostReply($child, $child_replies, $depth + 1); ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}
?>

<div style="max-width:720px; margin:0 auto; padding:0 40px 80px;">

  <!-- ORYGINALNY POST -->
  <div class="post-card post-single">
    <div class="post-card-header">
      <div class="post-card-meta">
        <?= story_avatar_link((int)$post['user_id'], $post, 48) ?>
        <div>
          <a href="/grailos/profile.php?username=<?= urlencode($post['username']) ?>"
             class="post-user-link">@<?= htmlspecialchars($post['username']) ?></a>
          <div class="post-date"><?= date('d M Y, H:i', strtotime($post['created_at'])) ?></div>
        </div>
      </div>
    </div>

    <?php if ($post['content']): ?>
      <div class="post-card-content post-content-large">
        <?= nl2br(htmlspecialchars($post['content'])) ?>
      </div>
    <?php endif; ?>

    <?php if ($post['image_url']): ?>
      <div class="post-img-wrap">
        <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="">
      </div>
    <?php endif; ?>

    <div class="post-card-footer">
      <span class="post-stat">💬 <?= count($all_replies) ?> odpowiedzi</span>
      <?php if (isset($_SESSION['user_id'])): ?>
        <button class="post-stat post-like-btn <?= $liked ? 'liked' : '' ?>"
                onclick="likePost(<?= $post['id'] ?>, this)">
          ♥ <span class="like-count"><?= $post['likes'] ?></span>
        </button>
      <?php else: ?>
        <span class="post-stat">♥ <?= $post['likes'] ?></span>
      <?php endif; ?>
      <a href="/grailos/forum.php" class="post-stat">← Forum</a>
      <?php if (isset($_SESSION['user_id']) && $post['user_id'] != $_SESSION['user_id']): ?>
        <button class="report-btn" style="margin-left:auto;"
                onclick="grailReport('post', <?= (int)$post['id'] ?>, 'Zgłaszasz post @<?= htmlspecialchars($post['username']) ?>')">⚑ Zgłoś</button>
      <?php endif; ?>
    </div>
  </div>

  <!-- ODPOWIEDZI -->
  <div class="section-label" style="padding:20px 0; border-color:var(--border);">
    /// <?= count($all_replies) ?> odpowiedzi
  </div>

  <?php if ($reply_error): ?>
    <div class="form-error" style="margin-bottom:16px;"><?= htmlspecialchars($reply_error) ?></div>
  <?php endif; ?>

  <?php foreach ($top_replies as $r) renderPostReply($r, $child_replies); ?>

  <!-- NOWA ODPOWIEDŹ GŁÓWNA -->
  <?php if (isset($_SESSION['user_id'])): ?>
    <div style="margin-top:24px;">
      <form method="POST" class="compose-card">
        <?= csrf_field() ?>
        <input type="hidden" name="reply" value="1">
        <textarea name="content" class="compose-textarea"
                  placeholder="Napisz odpowiedź..."
                  maxlength="500" rows="3"></textarea>
        <div class="compose-actions">
          <span class="compose-hint">max 500 znaków</span>
          <button type="submit" class="btn">Odpowiedz →</button>
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
function toggleReplyForm(id) {
  const form = document.getElementById(id);
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
  if (form.style.display === 'block') form.querySelector('textarea').focus();
}

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