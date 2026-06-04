<?php
require_once 'config/db.php';

$post_error = '';

// Cały POST processing PRZED require header
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_post'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /grailos/login.php'); exit;
    }
    csrf_verify();
    $content   = trim($_POST['content'] ?? '');
    $image_url = null;

    if (!$content && empty($_FILES['image']['name'])) {
        $post_error = 'Post nie może być pusty.';
    } elseif (strlen($content) > 1000) {
        $post_error = 'Post zbyt długi (max 1000 znaków).';
    } else {
        if (!empty($_FILES['image']['name'])) {
            $result = upload_image($_FILES['image'], 'posts');
            if (isset($result['error'])) {
                $post_error = $result['error'];
            } else {
                $image_url = $result['path'];
            }
        }
        if (!$post_error) {
            $pdo->prepare("INSERT INTO posts (user_id, content, image_url) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user_id'], $content, $image_url]);
            header('Location: /grailos/forum.php');
            exit;
        }
    }
}

$search_user = trim($_GET['user'] ?? '');
$search_kw   = trim($_GET['kw']   ?? '');
$where_parts = [];
$filter_params = [];

if ($search_user !== '') {
    $where_parts[]  = "users.username LIKE ?";
    $filter_params[] = '%' . $search_user . '%';
}
if ($search_kw !== '') {
    $where_parts[]  = "posts.content LIKE ?";
    $filter_params[] = '%' . $search_kw . '%';
}
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$stmt = $pdo->prepare("
    SELECT posts.*, users.username, users.avatar_url,
           COUNT(DISTINCT post_likes.id)   AS likes,
           COUNT(DISTINCT post_replies.id) AS replies
    FROM posts
    LEFT JOIN users        ON posts.user_id = users.id
    LEFT JOIN post_likes   ON posts.id = post_likes.post_id
    LEFT JOIN post_replies ON posts.id = post_replies.post_id
    $where
    GROUP BY posts.id
    ORDER BY posts.created_at DESC
");
$stmt->execute($filter_params);
$posts = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<section class="page-header">
  <div class="page-header-tag">/// Community</div>
  <h1 class="page-title">FORUM</h1>
  <p class="page-sub">Pytaj, odpowiadaj, dziel się.</p>
</section>
<!-- WYSZUKIWARKA -->
<div class="fits-filter-bar">
  <form method="GET" action="/grailos/forum.php" class="fits-filter-form">
    <input type="text" name="user" placeholder="@użytkownik"
           value="<?= htmlspecialchars($search_user) ?>"
           class="fits-filter-input">
    <input type="text" name="kw" placeholder="Słowo kluczowe w treści..."
           value="<?= htmlspecialchars($search_kw) ?>"
           class="fits-filter-input">
    <button type="submit" class="btn">Szukaj</button>
    <?php if ($search_user !== '' || $search_kw !== ''): ?>
      <a href="/grailos/forum.php" class="btn btn-outline">Resetuj ×</a>
    <?php endif; ?>
  </form>
</div>

<!-- COMPOSE -->
<div class="compose-wrap" style="padding-left:0; padding-right:0;">
<div class="forum-center">
  <?php if (isset($_SESSION['user_id'])): ?>
    <?php if ($post_error): ?>
      <div class="form-error" style="margin-bottom:16px;"><?= htmlspecialchars($post_error) ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" class="compose-card">
      <?= csrf_field() ?>
      <div class="compose-header">
        <a href="/grailos/profile.php?username=<?= urlencode($_SESSION['username']) ?>"
           class="compose-av-link">
          <?php if (!empty($_SESSION['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['avatar_url']) ?>"
                 class="compose-av-img" alt="">
          <?php else: ?>
            <span class="compose-avatar">
              <?= strtoupper(htmlspecialchars($_SESSION['username'][0])) ?>
            </span>
          <?php endif; ?>
        </a>
        <span class="compose-name">@<?= htmlspecialchars($_SESSION['username']) ?></span>
      </div>
      <textarea name="content" class="compose-textarea"
                placeholder="Co słychać? Zapytaj, podziel się..."
                maxlength="1000" rows="3"></textarea>

      <!-- Image preview -->
      <div class="compose-img-preview" id="composePreview" style="display:none;">
        <img id="previewImg" src="" alt="">
        <button type="button" class="compose-img-remove"
                onclick="removePreview()">✕</button>
      </div>

      <div class="compose-actions">
        <label class="compose-img-btn" title="Dodaj zdjęcie">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <circle cx="8.5" cy="8.5" r="1.5"/>
            <polyline points="21 15 16 10 5 21"/>
          </svg>
          <input type="file" name="image" id="postImage"
                 accept="image/jpeg,image/png,image/webp"
                 onchange="previewImage(this)" style="display:none">
        </label>
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
</div>

<div class="forum-center" style="padding-top:16px; padding-bottom:80px;">
<div style="font-size:11px; letter-spacing:4px; text-transform:uppercase; color:var(--gray); padding:0 0 12px;">
  /// <?= count($posts) ?> postów
</div>

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
      <div class="fpc post-card-clickable"
           data-href="/grailos/post.php?id=<?= $post['id'] ?>">

        <!-- HEADER: user + date -->
        <div class="fpc-head">
          <div class="fpc-meta">
            <a href="/grailos/profile.php?username=<?= urlencode($post['username']) ?>"
               class="post-av-link" onclick="event.stopPropagation()">
              <?php if ($post['avatar_url']): ?>
                <img src="<?= htmlspecialchars($post['avatar_url']) ?>"
                     class="post-av-img" alt="">
              <?php else: ?>
                <span class="post-avatar">
                  <?= strtoupper(htmlspecialchars($post['username'][0])) ?>
                </span>
              <?php endif; ?>
            </a>
            <a href="/grailos/profile.php?username=<?= urlencode($post['username']) ?>"
               class="fpc-username" onclick="event.stopPropagation()">
              @<?= htmlspecialchars($post['username']) ?>
            </a>
            <span class="fpc-time"><?= date('d M Y, H:i', strtotime($post['created_at'])) ?></span>
          </div>
          <?php if ($can_delete): ?>
            <form method="POST" action="/grailos/delete_post.php" onclick="event.stopPropagation()">
              <?= csrf_field() ?>
              <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
              <button type="button" class="post-delete-btn"
                      onclick="event.stopPropagation(); grailConfirm('Usunąć ten post?', () => this.closest('form').submit())">✕</button>
            </form>
          <?php endif; ?>
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

        <!-- FOOTER: stats + akcje -->
        <div class="fpc-foot">
          <?php if (isset($_SESSION['user_id'])): ?>
            <button class="fpc-btn post-like-btn <?= $liked ? 'liked' : '' ?>"
                    onclick="event.stopPropagation(); likePost(<?= $post['id'] ?>, this)">
              ♥ <span class="like-count"><?= $post['likes'] ?></span>
            </button>
          <?php else: ?>
            <span class="fpc-btn">♥ <?= $post['likes'] ?></span>
          <?php endif; ?>
          <a href="/grailos/post.php?id=<?= $post['id'] ?>" class="fpc-btn"
             onclick="event.stopPropagation()">
            💬 <?= $post['replies'] ?> <?= $post['replies'] == 1 ? 'odpowiedź' : 'odpowiedzi' ?>
          </a>
          <a href="/grailos/post.php?id=<?= $post['id'] ?>" class="fpc-btn"
             style="margin-left:auto;" onclick="event.stopPropagation()">
            Odpowiedz →
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div style="padding:80px 0; text-align:center; color:#444; letter-spacing:3px; text-transform:uppercase; font-size:13px;">
      Brak postów. Zacznij rozmowę.
    </div>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.post-card-clickable').forEach(card => {
  card.addEventListener('click', e => {
    if (!e.target.closest('a, button, form')) {
      window.location.href = card.dataset.href;
    }
  });
});

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

function previewImage(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById('previewImg').src = e.target.result;
      document.getElementById('composePreview').style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function removePreview() {
  document.getElementById('postImage').value = '';
  document.getElementById('previewImg').src = '';
  document.getElementById('composePreview').style.display = 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>