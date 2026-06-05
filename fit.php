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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_comment_id'])) {
    if (!isset($_SESSION['user_id'])) { header('Location: /grailos/login.php'); exit; }
    csrf_verify();
    $comment_id      = (int)($_POST['reply_comment_id'] ?? 0);
    $parent_reply_id = (int)($_POST['parent_reply_id'] ?? 0) ?: null;
    $content         = trim($_POST['reply_content'] ?? '');
    if ($content && $comment_id) {
        try {
            $pdo->prepare("INSERT INTO fit_comment_replies (comment_id, parent_reply_id, user_id, content) VALUES (?, ?, ?, ?)")
                ->execute([$comment_id, $parent_reply_id, $_SESSION['user_id'], $content]);
        } catch (Exception $e) {
            // Fallback gdy migracja parent_reply_id jeszcze nie uruchomiona
            $pdo->prepare("INSERT INTO fit_comment_replies (comment_id, user_id, content) VALUES (?, ?, ?)")
                ->execute([$comment_id, $_SESSION['user_id'], $content]);
        }
    }
    header("Location: /grailos/fit.php?id=$id#comment-$comment_id");
    exit;
}

$stmt2 = $pdo->prepare("
    SELECT fit_comments.*, users.username, users.avatar_url
    FROM fit_comments
    LEFT JOIN users ON fit_comments.user_id = users.id
    WHERE fit_comments.fit_id = ?
    ORDER BY fit_comments.created_at ASC
");
$stmt2->execute([$id]);
$comments = $stmt2->fetchAll();

// Odpowiedzi: bezpośrednie pod komentarzem (parent_reply_id = NULL)
// oraz zagnieżdżone pod inną odpowiedzią (drzewko)
$direct_replies = [];   // [comment_id] => [reply, ...]   (parent_reply_id NULL)
$child_replies  = [];   // [parent_reply_id] => [reply, ...]
$total_reply_count = 0;
if ($comments) {
    $comment_ids  = array_column($comments, 'id');
    $placeholders = implode(',', array_fill(0, count($comment_ids), '?'));
    try {
        $rstmt = $pdo->prepare("
            SELECT fit_comment_replies.*, users.username, users.avatar_url
            FROM fit_comment_replies
            LEFT JOIN users ON fit_comment_replies.user_id = users.id
            WHERE fit_comment_replies.comment_id IN ($placeholders)
            ORDER BY fit_comment_replies.created_at ASC
        ");
        $rstmt->execute($comment_ids);
        $has_parent_col = true;
        foreach ($rstmt->fetchAll() as $reply) {
            $total_reply_count++;
            $pid = array_key_exists('parent_reply_id', $reply) ? $reply['parent_reply_id'] : null;
            if ($pid) {
                $child_replies[$pid][] = $reply;
            } else {
                $direct_replies[$reply['comment_id']][] = $reply;
            }
        }
    } catch (Exception $e) {}
}

$user_voted = false;
if (isset($_SESSION['user_id'])) {
    $v = $pdo->prepare("SELECT id FROM fit_votes WHERE fit_id = ? AND user_id = ?");
    $v->execute([$id, $_SESSION['user_id']]);
    $user_voted = (bool)$v->fetch();
}

// Dodatkowe zdjęcia (tabela fit_images)
$extra_fit_imgs = [];
try {
    $ei = $pdo->prepare("SELECT * FROM fit_images WHERE fit_id = ? ORDER BY sort_order ASC");
    $ei->execute([$id]);
    $extra_fit_imgs = $ei->fetchAll();
} catch (Exception $e) {}

$all_fit_imgs = array_merge([$fit['image_url']], array_column($extra_fit_imgs, 'image_url'));

// Policz wszystkie komentarze + odpowiedzi (z drzewkiem)
$total_comment_count = count($comments) + $total_reply_count;

$multi = count($all_fit_imgs) > 1;

// Rekurencyjne renderowanie drzewka odpowiedzi
function renderReplyTree($reply, $comment_id, $child_replies, $fit_id, $depth = 0) {
    $children = $child_replies[$reply['id']] ?? [];
    $can_del  = isset($_SESSION['user_id']) &&
                ($reply['user_id'] == $_SESSION['user_id'] || !empty($_SESSION['is_admin']));
    ?>
    <div class="comment-reply" id="creply-<?= $reply['id'] ?>">
      <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
        <a href="/grailos/profile.php?username=<?= urlencode($reply['username']) ?>" class="post-av-link">
          <?php if ($reply['avatar_url']): ?>
            <img src="<?= htmlspecialchars($reply['avatar_url']) ?>"
                 style="width:22px;height:22px;border-radius:50%;object-fit:cover;" alt="">
          <?php else: ?>
            <span style="width:22px;height:22px;background:var(--card);border-radius:50%;
                         display:flex;align-items:center;justify-content:center;
                         font-family:'Bebas Neue',sans-serif;font-size:12px;color:var(--red);">
              <?= strtoupper($reply['username'][0]) ?>
            </span>
          <?php endif; ?>
        </a>
        <a href="/grailos/profile.php?username=<?= urlencode($reply['username']) ?>"
           style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:var(--red);text-decoration:none;">
          @<?= htmlspecialchars($reply['username']) ?>
        </a>
        <span style="font-size:11px;color:#444;"><?= date('d M, H:i', strtotime($reply['created_at'])) ?></span>
        <?php if (isset($_SESSION['user_id']) && $reply['user_id'] != $_SESSION['user_id']): ?>
          <button class="report-btn" style="margin-left:auto;"
                  onclick="grailReport('fit_reply', <?= (int)$reply['id'] ?>, 'Zgłaszasz odpowiedź @<?= htmlspecialchars($reply['username']) ?>')">⚑</button>
        <?php endif; ?>
        <?php if ($can_del): ?>
          <form method="POST" action="/grailos/delete_fit_comment.php" style="margin-left:auto;">
            <?= csrf_field() ?>
            <input type="hidden" name="type" value="reply">
            <input type="hidden" name="id" value="<?= $reply['id'] ?>">
            <input type="hidden" name="fit_id" value="<?= $fit_id ?>">
            <button type="button" class="comment-del-btn"
                    onclick="grailConfirm('Usunąć odpowiedź?', () => this.closest('form').submit())">✕</button>
          </form>
        <?php endif; ?>
      </div>
      <div style="font-size:13px;color:var(--gray);line-height:1.5;">
        <?= htmlspecialchars($reply['content']) ?>
      </div>
      <?php if (isset($_SESSION['user_id'])): ?>
        <button class="reply-toggle-btn" onclick="toggleReply('rrf-<?= $reply['id'] ?>')">Odpowiedz</button>
        <form method="POST" class="reply-form" id="rrf-<?= $reply['id'] ?>" style="display:none;">
          <?= csrf_field() ?>
          <input type="hidden" name="reply_comment_id" value="<?= $comment_id ?>">
          <input type="hidden" name="parent_reply_id" value="<?= $reply['id'] ?>">
          <div class="form-group" style="margin-top:8px;">
            <textarea name="reply_content" class="form-input form-textarea"
                      placeholder="Odpowiadasz @<?= htmlspecialchars($reply['username']) ?>..."
                      rows="2" maxlength="500"></textarea>
          </div>
          <div style="display:flex; gap:8px;">
            <button type="submit" class="btn" style="font-size:11px; padding:6px 14px;">Wyślij →</button>
            <button type="button" class="btn btn-outline" style="font-size:11px; padding:6px 14px;"
                    onclick="toggleReply('rrf-<?= $reply['id'] ?>')">Anuluj</button>
          </div>
        </form>
      <?php endif; ?>
      <?php if ($children): ?>
        <div class="comment-replies">
          <?php foreach ($children as $child) renderReplyTree($child, $comment_id, $child_replies, $fit_id, $depth + 1); ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

require_once 'includes/header.php';
?>

<div class="<?= $multi ? 'item-page-v2' : 'fit-page-v2' ?>">

  <!-- KAFELKI PO LEWEJ (tylko gdy multi) -->
  <?php if ($multi): ?>
    <div class="item-thumbs-v">
      <?php foreach ($all_fit_imgs as $gi => $gimg): ?>
        <button class="item-thumb-v <?= $gi === 0 ? 'active' : '' ?>"
                onclick="goSlide(<?= $gi ?>)">
          <img src="<?= htmlspecialchars($gimg) ?>" alt="">
        </button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ZDJĘCIE GŁÓWNE -->
  <div class="<?= $multi ? 'item-main-img' : 'fit-page-img' ?>">
    <?php if ($multi): ?>
      <?php foreach ($all_fit_imgs as $gi => $gimg): ?>
        <div class="item-slide <?= $gi === 0 ? 'active' : '' ?>">
          <img src="<?= htmlspecialchars($gimg) ?>" alt="Fit">
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <img src="<?= htmlspecialchars($all_fit_imgs[0]) ?>" alt="Fit"
           style="width:100%; height:100%; object-fit:contain; display:block;">
    <?php endif; ?>
  </div>

  <!-- INFO + KOMENTARZE -->
  <div class="fit-page-info">

    <!-- UŻYTKOWNIK -->
    <div class="fit-page-user">
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

    <!-- AKCJE: like + edit + delete -->
    <div class="fit-page-actions">
      <?php if (isset($_SESSION['user_id'])): ?>
        <button class="vote-btn <?= $user_voted ? 'voted' : '' ?>"
                onclick="handleVote(<?= $fit['id'] ?>, this)">
          ♥ <span class="vote-count"><?= $fit['votes'] ?></span>
        </button>
      <?php else: ?>
        <span class="vote-count-static" style="font-size:15px;">♥ <?= $fit['votes'] ?></span>
      <?php endif; ?>

      <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
        <?php if (isset($_SESSION['user_id']) && $fit['user_id'] != $_SESSION['user_id']): ?>
          <button class="report-btn"
                  onclick="grailReport('fit', <?= (int)$fit['id'] ?>, 'Zgłaszasz fit @<?= htmlspecialchars($fit['username']) ?>')">⚑ Zgłoś</button>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id']) && $fit['user_id'] == $_SESSION['user_id']): ?>
          <a href="/grailos/edit_fit.php?id=<?= $fit['id'] ?>"
             class="btn btn-outline" style="font-size:11px; padding:5px 12px;">Edytuj</a>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id']) && ($fit['user_id'] == $_SESSION['user_id'] || $_SESSION['is_admin'])): ?>
          <form method="POST" action="/grailos/delete_fit.php">
            <?= csrf_field() ?>
            <input type="hidden" name="fit_id" value="<?= $fit['id'] ?>">
            <button type="button" class="btn-delete" style="font-size:11px; padding:5px 12px;"
                    onclick="grailConfirm('Usunąć ten fit?', () => this.closest('form').submit())">Usuń</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- OPIS -->
    <?php if ($fit['description']): ?>
      <p class="fit-page-desc"><?= htmlspecialchars($fit['description']) ?></p>
    <?php endif; ?>

    <!-- KOMENTARZE -->
    <div class="comments-section" id="comments">
      <button class="comments-toggle-btn" onclick="toggleComments()">
        <span>/// <?= $total_comment_count ?> <?= $total_comment_count == 1 ? 'komentarz' : 'komentarzy' ?></span>
        <span id="commentsArrow">▼</span>
      </button>
      <div id="commentsBody">

      <?php foreach ($comments as $c):
        $c_replies = $direct_replies[$c['id']] ?? [];
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
            <div style="display:flex; align-items:center; gap:10px;">
              <span class="comment-date"><?= date('d M, H:i', strtotime($c['created_at'])) ?></span>
              <?php if (isset($_SESSION['user_id']) && $c['user_id'] != $_SESSION['user_id']): ?>
                <button class="report-btn" onclick="grailReport('fit_comment', <?= (int)$c['id'] ?>, 'Zgłaszasz komentarz @<?= htmlspecialchars($c['username']) ?>')">⚑</button>
              <?php endif; ?>
              <?php if (isset($_SESSION['user_id']) && ($c['user_id'] == $_SESSION['user_id'] || !empty($_SESSION['is_admin']))): ?>
                <form method="POST" action="/grailos/delete_fit_comment.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="type" value="comment">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <input type="hidden" name="fit_id" value="<?= $fit['id'] ?>">
                  <button type="button" class="comment-del-btn"
                          onclick="grailConfirm('Usunąć komentarz?', () => this.closest('form').submit())">✕</button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <div class="comment-content"><?= htmlspecialchars($c['content']) ?></div>

          <?php if (isset($_SESSION['user_id'])): ?>
            <button class="reply-toggle-btn"
                    onclick="toggleReply('reply-form-<?= $c['id'] ?>')">Odpowiedz</button>
          <?php endif; ?>

          <?php if ($c_replies): ?>
            <div class="comment-replies">
              <?php foreach ($c_replies as $r) renderReplyTree($r, $c['id'], $child_replies, $fit['id']); ?>
            </div>
          <?php endif; ?>

          <?php if (isset($_SESSION['user_id'])): ?>
            <form method="POST" class="reply-form"
                  id="reply-form-<?= $c['id'] ?>" style="display:none;">
              <?= csrf_field() ?>
              <input type="hidden" name="reply_comment_id" value="<?= $c['id'] ?>">
              <div class="form-group" style="margin-top:8px;">
                <textarea name="reply_content" class="form-input form-textarea"
                          placeholder="Odpowiedz na komentarz..."
                          rows="2" maxlength="500"></textarea>
              </div>
              <div style="display:flex; gap:8px;">
                <button type="submit" class="btn" style="font-size:11px; padding:6px 14px;">Wyślij →</button>
                <button type="button" class="btn btn-outline" style="font-size:11px; padding:6px 14px;"
                        onclick="toggleReply('reply-form-<?= $c['id'] ?>')">Anuluj</button>
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
        <form method="POST" class="comment-form" style="margin-top:8px;">
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
      </div><!-- /commentsBody -->
    </div>

  </div>
</div>

<script>
// Galeria multi-image (item-page-v2 slide system)
let current = 0;
const slides  = document.querySelectorAll('.item-slide');
const thumbsV = document.querySelectorAll('.item-thumb-v');

function goSlide(n) {
  if (!slides.length) return;
  slides[current].classList.remove('active');
  if (thumbsV[current]) thumbsV[current].classList.remove('active');
  current = (n + slides.length) % slides.length;
  slides[current].classList.add('active');
  if (thumbsV[current]) thumbsV[current].classList.add('active');
}

function toggleComments() {
  const body  = document.getElementById('commentsBody');
  const arrow = document.getElementById('commentsArrow');
  const open  = body.style.display !== 'none';
  body.style.display = open ? 'none' : 'block';
  arrow.textContent  = open ? '▶' : '▼';
}

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
