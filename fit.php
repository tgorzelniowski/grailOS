<?php
require_once 'config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /grailos/fits.php'); exit; }

$stmt = $pdo->prepare("
    SELECT fits.*, users.username, users.avatar_url,
           COUNT(DISTINCT fit_votes.id) AS votes
    FROM fits
    LEFT JOIN users     ON fits.user_id = users.id
    LEFT JOIN fit_votes ON fits.id = fit_votes.fit_id
    WHERE fits.id = ?
    GROUP BY fits.id
");
$stmt->execute([$id]);
$fit = $stmt->fetch();
if (!$fit) { header('Location: /grailos/fits.php'); exit; }

$comment_error = '';

// Nowy komentarz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    if (!isset($_SESSION['user_id'])) { header('Location: /grailos/login.php'); exit; }
    csrf_verify();
    $content = trim($_POST['comment'] ?? '');
    if (!$content) {
        $comment_error = 'Komentarz nie może być pusty.';
    } elseif (strlen($content) > 500) {
        $comment_error = 'Komentarz zbyt długi (max 500 znaków).';
    } else {
        $pdo->prepare("INSERT INTO fit_comments (fit_id, user_id, content) VALUES (?, ?, ?)")
            ->execute([$id, $_SESSION['user_id'], $content]);
        header("Location: /grailos/fit.php?id=$id#comments");
        exit;
    }
}

// Odpowiedź na komentarz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_comment_id'])) {
    if (!isset($_SESSION['user_id'])) { header('Location: /grailos/login.php'); exit; }
    csrf_verify();
    $comment_id = (int)($_POST['reply_comment_id'] ?? 0);
    $content    = trim($_POST['reply_content'] ?? '');
    if ($content && $comment_id) {
        $pdo->prepare("INSERT INTO fit_comment_replies (comment_id, user_id, content) VALUES (?, ?, ?)")
            ->execute([$comment_id, $_SESSION['user_id'], $content]);
    }
    header("Location: /grailos/fit.php?id=$id#comment-$comment_id");
    exit;
}

// Pobierz komentarze
$stmt2 = $pdo->prepare("
    SELECT fit_comments.*, users.username, users.avatar_url
    FROM fit_comments
    LEFT JOIN users ON fit_comments.user_id = users.id
    WHERE fit_comments.fit_id = ?
    ORDER BY fit_comments.created_at ASC
");
$stmt2->execute([$id]);
$comments = $stmt2->fetchAll();

// Pobierz odpowiedzi do każdego komentarza
$replies_map = [];
if ($comments) {
    $comment_ids  = array_column($comments, 'id');
    $placeholders = implode(',', array_fill(0, count($comment_ids), '?'));
    $rstmt = $pdo->prepare("
        SELECT fit_comment_replies.*, users.username, users.avatar_url
        FROM fit_comment_replies
        LEFT JOIN users ON fit_comment_replies.user_id = users.id
        WHERE fit_comment_replies.comment_id IN ($placeholders)
        ORDER BY fit_comment_replies.created_at ASC
    ");
    $rstmt->execute($comment_ids);
    foreach ($rstmt->fetchAll() as $reply) {
        $replies_map[$reply['comment_id']][] = $reply;
    }
}

// Czy głosował
$user_voted = false;
if (isset($_SESSION['user_id'])) {
    $v = $pdo->prepare("SELECT id FROM fit_votes WHERE fit_id = ? AND user_id = ?");
    $v->execute([$id, $_SESSION['user_id']]);
    $user_voted = (bool)$v->fetch();
}

require_once 'includes/header.php';
?>

<div class="fit-single-wrap">
  <div class="fit-single-img">
    <img src="<?= htmlspecialchars($fit['image_url']) ?>" alt="Fit">
  </div>

  <div class="fit-single-content">
    <div class="fit-single-header">
      <div class="post-card-meta">
        <a href="/grailos/profile.php?username=<?= urlencode($fit['username']) ?>"
           class="post-av-link">
          <?php if ($fit['avatar_url']): ?>
            <img src="<?= htmlspecialchars($fit['avatar_url']) ?>"
                 class="post-av-img post-av-lg" alt="">
          <?php else: ?>
            <span class="post-avatar post-avatar-lg">
              <?= strtoupper($fit['username'][0]) ?>
            </span>
          <?php endif; ?>
        </a>
        <div>
          <a href="/grailos/profile.php?username=<?= urlencode($fit['username']) ?>"
             class="post-user-link">@<?= htmlspecialchars($fit['username']) ?></a>
          <div class="post-date"><?= date('d M Y, H:i', strtotime($fit['created_at'])) ?></div>
        </div>
      </div>
      <div style="display:flex; gap:12px; align-items:center;">
        <?php if (isset($_SESSION['user_id'])): ?>
          <button class="vote-btn <?= $user_voted ? 'voted' : '' ?>"
                  style="font-size:16px; padding:10px 24px;"
                  onclick="handleVote(<?= $fit['id'] ?>, this)">
            ♥ <span class="vote-count"><?= $fit['votes'] ?></span>
          </button>
        <?php else: ?>
          <span class="vote-count-static" style="font-size:16px;">♥ <?= $fit['votes'] ?></span>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id']) && ($fit['user_id'] == $_SESSION['user_id'] || $_SESSION['is_admin'])): ?>
          <form method="POST" action="/grailos/delete_fit.php">
            <?= csrf_field() ?>
            <input type="hidden" name="fit_id" value="<?= $fit['id'] ?>">
            <button type="button" class="btn-delete"
                    onclick="grailConfirm('Usunąć ten fit?', () => this.closest('form').submit())">Usuń</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($fit['description']): ?>
      <p class="fit-single-desc"><?= htmlspecialchars($fit['description']) ?></p>
    <?php endif; ?>

    <!-- KOMENTARZE -->
    <div class="comments-section" id="comments">
      <div class="comments-title">
        /// <?= count($comments) ?> komentarzy
      </div>

      <?php foreach ($comments as $c):
        $c_replies = $replies_map[$c['id']] ?? [];
      ?>
        <div class="comment" id="comment-<?= $c['id'] ?>">
          <div class="comment-header">
            <div style="display:flex; align-items:center; gap:8px;">
              <a href="/grailos/profile.php?username=<?= urlencode($c['username']) ?>"
                 class="post-av-link">
                <?php if ($c['avatar_url']): ?>
                  <img src="<?= htmlspecialchars($c['avatar_url']) ?>"
                       style="width:28px;height:28px;border-radius:50%;object-fit:cover;" alt="">
                <?php else: ?>
                  <span style="width:28px;height:28px;background:var(--card);border-radius:50%;
                               display:flex;align-items:center;justify-content:center;
                               font-family:'Bebas Neue',sans-serif;font-size:14px;color:var(--red);">
                    <?= strtoupper($c['username'][0]) ?>
                  </span>
                <?php endif; ?>
              </a>
              <a href="/grailos/profile.php?username=<?= urlencode($c['username']) ?>"
                 class="comment-user">@<?= htmlspecialchars($c['username']) ?></a>
            </div>
            <span class="comment-date"><?= date('d M, H:i', strtotime($c['created_at'])) ?></span>
          </div>

          <div class="comment-content"><?= htmlspecialchars($c['content']) ?></div>

          <?php if (isset($_SESSION['user_id'])): ?>
            <button class="reply-toggle-btn"
                    onclick="toggleReply('reply-form-<?= $c['id'] ?>')">
              Odpowiedz
            </button>
          <?php endif; ?>

          <!-- ODPOWIEDZI NA KOMENTARZ -->
          <?php if ($c_replies): ?>
            <div class="comment-replies">
              <?php foreach ($c_replies as $r): ?>
                <div class="comment-reply" id="creply-<?= $r['id'] ?>">
                  <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                    <a href="/grailos/profile.php?username=<?= urlencode($r['username']) ?>"
                       class="post-av-link">
                      <?php if ($r['avatar_url']): ?>
                        <img src="<?= htmlspecialchars($r['avatar_url']) ?>"
                             style="width:22px;height:22px;border-radius:50%;object-fit:cover;" alt="">
                      <?php else: ?>
                        <span style="width:22px;height:22px;background:var(--card);border-radius:50%;
                                     display:flex;align-items:center;justify-content:center;
                                     font-family:'Bebas Neue',sans-serif;font-size:12px;color:var(--red);">
                          <?= strtoupper($r['username'][0]) ?>
                        </span>
                      <?php endif; ?>
                    </a>
                    <a href="/grailos/profile.php?username=<?= urlencode($r['username']) ?>"
                       style="font-size:11px;letter-spacing:2px;text-transform:uppercase;
                              color:var(--red);text-decoration:none;">
                      @<?= htmlspecialchars($r['username']) ?>
                    </a>
                    <span style="font-size:11px;color:#444;letter-spacing:1px;">
                      <?= date('d M, H:i', strtotime($r['created_at'])) ?>
                    </span>
                  </div>
                  <div style="font-size:13px;color:var(--gray);line-height:1.5;letter-spacing:0.3px;">
                    <?= htmlspecialchars($r['content']) ?>
                  </div>
                  <?php if (isset($_SESSION['user_id'])): ?>
                    <button class="reply-toggle-btn"
                            onclick="toggleReply('rrf-<?= $r['id'] ?>')">
                      Odpowiedz
                    </button>
                    <form method="POST" class="reply-form"
                          id="rrf-<?= $r['id'] ?>" style="display:none;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="reply_comment_id" value="<?= $c['id'] ?>">
                      <div class="form-group" style="margin-top:8px;">
                        <textarea name="reply_content" class="form-input form-textarea"
                                  placeholder="Odpowiadasz @<?= htmlspecialchars($r['username']) ?>..."
                                  rows="2" maxlength="500"></textarea>
                      </div>
                      <div style="display:flex; gap:8px;">
                        <button type="submit" class="btn"
                                style="font-size:11px; padding:6px 14px;">Wyślij →</button>
                        <button type="button" class="btn btn-outline"
                                style="font-size:11px; padding:6px 14px;"
                                onclick="toggleReply('rrf-<?= $r['id'] ?>')">Anuluj</button>
                      </div>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <!-- FORMULARZ ODPOWIEDZI -->
          <?php if (isset($_SESSION['user_id'])): ?>
            <form method="POST"
                  class="reply-form"
                  id="reply-form-<?= $c['id'] ?>"
                  style="display:none;">
              <?= csrf_field() ?>
              <input type="hidden" name="reply_comment_id" value="<?= $c['id'] ?>">
              <div class="form-group" style="margin-top:8px;">
                <textarea name="reply_content" class="form-input form-textarea"
                          placeholder="Odpowiedz na komentarz..."
                          rows="2" maxlength="500"></textarea>
              </div>
              <div style="display:flex; gap:8px;">
                <button type="submit" class="btn"
                        style="font-size:11px; padding:6px 14px;">Wyślij →</button>
                <button type="button" class="btn btn-outline"
                        style="font-size:11px; padding:6px 14px;"
                        onclick="toggleReply('reply-form-<?= $c['id'] ?>')">
                  Anuluj
                </button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <!-- NOWY KOMENTARZ -->
      <?php if (isset($_SESSION['user_id'])): ?>
        <?php if ($comment_error): ?>
          <div class="form-error" style="margin:16px 0;"><?= htmlspecialchars($comment_error) ?></div>
        <?php endif; ?>
        <form method="POST" class="comment-form">
          <?= csrf_field() ?>
          <div class="form-group">
            <textarea name="comment" class="form-input form-textarea"
                      placeholder="Napisz komentarz..."
                      rows="3" maxlength="500"></textarea>
          </div>
          <button type="submit" class="btn">Dodaj komentarz →</button>
        </form>
      <?php else: ?>
        <p class="auth-switch" style="margin-top:20px;">
          <a href="/grailos/login.php">Zaloguj się</a> by komentować.
        </p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function toggleReply(id) {
  const form = document.getElementById(id);
  form.style.display = form.style.display === 'none' ? 'block' : 'none';
  if (form.style.display === 'block') form.querySelector('textarea').focus();
}

async function handleVote(fitId, btn) {
  const res  = await fetch('/grailos/vote.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `fit_id=${fitId}`
  });
  const data = await res.json();
  if (data.success) {
    btn.classList.toggle('voted', data.voted);
    btn.querySelector('.vote-count').textContent = data.votes;
  }
}
</script>

<?php require_once 'includes/footer.php'; ?>