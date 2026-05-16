<?php
require_once 'includes/header.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { header('Location: /grailos/fits.php'); exit; }

$stmt = $pdo->prepare("
    SELECT fits.*, users.username,
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

// Dodaj komentarz
$comment_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /grailos/login.php'); exit;
    }
    csrf_verify();
    $content = trim($_POST['comment'] ?? '');
    if (!$content) {
        $comment_error = 'Komentarz nie może być pusty.';
    } elseif (strlen($content) > 500) {
        $comment_error = 'Komentarz zbyt długi (max 500 znaków).';
    } else {
        $pdo->prepare("INSERT INTO fit_comments (fit_id, user_id, content) VALUES (?, ?, ?)")
            ->execute([$id, $_SESSION['user_id'], $content]);
        header("Location: /grailos/fit.php?id=$id");
        exit;
    }
}

// Pobierz komentarze
$stmt = $pdo->prepare("
    SELECT fit_comments.*, users.username
    FROM fit_comments
    LEFT JOIN users ON fit_comments.user_id = users.id
    WHERE fit_comments.fit_id = ?
    ORDER BY fit_comments.created_at ASC
");
$stmt->execute([$id]);
$comments = $stmt->fetchAll();

// Czy user głosował?
$user_voted = false;
if (isset($_SESSION['user_id'])) {
    $v = $pdo->prepare("SELECT id FROM fit_votes WHERE fit_id = ? AND user_id = ?");
    $v->execute([$id, $_SESSION['user_id']]);
    $user_voted = (bool) $v->fetch();
}
?>

<div class="fit-single-wrap">

  <!-- ZDJĘCIE -->
  <div class="fit-single-img">
    <img src="<?= htmlspecialchars($fit['image_url']) ?>"
         alt="Fit by @<?= htmlspecialchars($fit['username']) ?>">
  </div>

  <!-- PRAWA KOLUMNA -->
  <div class="fit-single-content">

    <div class="fit-single-header">
  <div>
    <div class="fit-user" style="font-size:16px;">@<?= htmlspecialchars($fit['username']) ?></div>
    <div class="fit-date"><?= date('d M Y, H:i', strtotime($fit['created_at'])) ?></div>
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
      <form method="POST" action="/grailos/delete_fit.php"
            onsubmit="return confirm('Usunac ten fit?')">
        <?= csrf_field() ?>
        <input type="hidden" name="fit_id" value="<?= $fit['id'] ?>">
        <button type="submit" class="btn-delete">Usuń fit</button>
      </form>
    <?php endif; ?>
  </div>
</div>

    <?php if ($fit['description']): ?>
      <p class="fit-single-desc"><?= htmlspecialchars($fit['description']) ?></p>
    <?php endif; ?>

    <!-- KOMENTARZE -->
    <div class="comments-section">
      <div class="comments-title">/// <?= count($comments) ?> komentarzy</div>

      <?php foreach ($comments as $c): ?>
        <div class="comment">
          <div class="comment-header">
            <span class="comment-user">@<?= htmlspecialchars($c['username']) ?></span>
            <span class="comment-date"><?= date('d M, H:i', strtotime($c['created_at'])) ?></span>
          </div>
          <div class="comment-content"><?= htmlspecialchars($c['content']) ?></div>
        </div>
      <?php endforeach; ?>

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
async function handleVote(fitId, btn) {
  const res  = await fetch('/grailos/vote.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
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